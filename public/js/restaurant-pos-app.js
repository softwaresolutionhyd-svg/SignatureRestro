(() => {
    const boot = window.RESTAURANT_POS_BOOTSTRAP || {};
    const products = boot.products || [];
    const menuCategories = boot.menuCategories || [];
    const contacts = boot.contacts || [];
    const settings = boot.settings || {};
    const routes = boot.routes || {};
    const csrf = boot.csrf || '';
    const serviceTypeLabels = boot.serviceTypeLabels || {
        dine_in: 'Dine-in',
        takeaway: 'Takeaway',
        delivery: 'Delivery',
    };
    const posTaxMode = settings.tax_mode || 'line';
    const posDefaultLineTax = Number(settings.default_tax_rate || 0);
    const posShowDiscount = settings.show_discount !== false;
    const posTablesEnabled = boot.tablesEnabled !== undefined ? !!boot.tablesEnabled : !!settings.enable_tables;
    const posShowCustomerSection = settings.show_customer_section !== false;

    let cart = [];
    let kitchenVoids = [];
    let pendingRemoveIndex = null;
    let removeReasonModalInstance = null;
    let resumeSaveLock = Promise.resolve();
    let payments = [{ method: 'cash', amount: 0 }];
    let orderType = 'sale';
    let isCreditMode = false;
    let selectedContactId = null;
    let resumeOrderId = boot.resumeOrderId || null;
    let selectedMenuCategoryId = null;
    let payModalInstance = null;

    const $ = (sel) => document.querySelector(sel);
    const $$ = (sel) => Array.from(document.querySelectorAll(sel));

    function escHtml(s) {
        return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function fmtMoney(n) {
        const v = Number(n);
        return Number.isFinite(v) ? v.toFixed(2) : '0.00';
    }

    function fmtQty(n) {
        const v = Number(n);
        if (!Number.isFinite(v)) return '0';
        if (Math.abs(v - Math.round(v)) < 1e-6) return String(Math.round(v));
        return parseFloat(v.toFixed(3)).toString();
    }

    function selectedServiceType() {
        return $('#rpServiceType')?.value || 'dine_in';
    }

    function serviceTypeLabel(type) {
        return serviceTypeLabels[type] || serviceTypeLabels.dine_in || 'Dine-in';
    }

    function setServiceType(type) {
        const input = $('#rpServiceType');
        if (input) input.value = type;
        $$('.rp-service-type').forEach((btn) => {
            const active = btn.dataset.type === type;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        syncServiceDetailPanels();
        syncWhatsappButton();
    }

    function syncServiceDetailPanels() {
        const type = selectedServiceType();
        $$('.rp-service-panel').forEach((panel) => {
            panel.classList.toggle('d-none', panel.dataset.service !== type);
        });
    }

    function syncWhatsappButton() {
        const btn = $('#rpWhatsappBtn');
        if (!btn) return;
        const show = selectedServiceType() === 'delivery';
        btn.classList.toggle('d-none', !show);
        btn.disabled = !show || cart.length === 0;
    }

    function normalizeWhatsappPhone(raw) {
        let digits = String(raw || '').replace(/\D/g, '');
        if (digits === '') return '';
        if (digits.startsWith('00')) {
            digits = digits.slice(2);
        }
        if (digits.startsWith('0') && digits.length === 11) {
            digits = '92' + digits.slice(1);
        }
        if (digits.length === 10 && digits.startsWith('3')) {
            digits = '92' + digits;
        }
        return digits;
    }

    function buildDeliveryWhatsappMessage() {
        const customerName = ($('#rpDeliveryName')?.value || '').trim() || 'Customer';
        const restaurantName = boot.restaurantName || 'Restaurant';
        const totals = calcCartTotals();
        const lines = cart.map((r, idx) => {
            const lineTotal = lineRowTotal(r, totals, idx);
            return `• ${fmtQty(r.qty)}× ${r.name} — Rs. ${fmtMoney(lineTotal)}`;
        });

        return [
            `Assalam o Alaikum ${customerName}!`,
            '',
            `Aap ne *${restaurantName}* se ye order kiya hai:`,
            '',
            ...lines,
            '',
            `*Total Amount: Rs. ${fmtMoney(totals.grand)}*`,
            '',
            'Aapka order *40-45 minutes* mein deliver ho jayega.',
            '',
            'Shukriya!',
        ].join('\n');
    }

    function whatsappSendUrl(phone, encodedText) {
        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        if (isMobile) {
            return `https://wa.me/${phone}?text=${encodedText}`;
        }

        return `whatsapp://send?phone=${phone}&text=${encodedText}`;
    }

    function openDeliveryWhatsapp() {
        if (selectedServiceType() !== 'delivery') return;
        if (!cart.length) {
            alert('Pehle item add karein.');
            return;
        }
        const phoneRaw = ($('#rpDeliveryPhone')?.value || '').trim();
        if (!phoneRaw) {
            alert('Delivery ke liye Phone No. enter karein.');
            $('#rpDeliveryPhone')?.focus();
            return;
        }
        const phone = normalizeWhatsappPhone(phoneRaw);
        if (phone.length < 10) {
            alert('Sahi WhatsApp number enter karein.');
            return;
        }
        const text = encodeURIComponent(buildDeliveryWhatsappMessage());
        const url = whatsappSendUrl(phone, text);
        if (url.startsWith('whatsapp://')) {
            window.location.href = url;
            return;
        }
        window.open(url, '_blank', 'noopener,noreferrer');
    }

    function setCreditMode(on) {
        isCreditMode = !!on;
        const toggle = $('#rpCreditToggle');
        if (toggle) toggle.checked = isCreditMode;
        $('#rpPaymentsBlock')?.classList.toggle('d-none', isCreditMode);
        $('#rpPayBtn')?.classList.toggle('btn-rp-primary', !isCreditMode);
        $('#rpPayBtn')?.classList.toggle('btn-danger', isCreditMode);
        if ($('#rpPayBtn')) {
            $('#rpPayBtn').textContent = isCreditMode ? 'Record Credit' : 'Pay Now';
        }
    }

    function filterContacts(q) {
        const needle = q.toLowerCase();
        return contacts.filter((c) =>
            String(c.name || '').toLowerCase().includes(needle) || String(c.phone || '').toLowerCase().includes(needle)
        ).slice(0, 12);
    }

    function selectContact(id, name, phone) {
        selectedContactId = String(id);
        const label = $('#rpSelectedContact');
        if (label) label.textContent = name + (phone ? ' · ' + phone : '');
        $('#rpSelectedContactWrap')?.classList.remove('d-none');
        $('#rpContactDropdown')?.classList.add('d-none');
        if ($('#rpContactSearch')) $('#rpContactSearch').value = '';
    }

    function restoreResumeContact() {
        if (!settings.resume_contact_id) return;
        const c = contacts.find((x) => Number(x.id) === Number(settings.resume_contact_id));
        if (c) {
            selectContact(c.id, c.name, c.phone || '');
        }
    }

    function orderMetaLabel(order) {
        if (order.service_type_label) return order.service_type_label;
        if (order.service_type) return serviceTypeLabel(order.service_type);
        if (order.guest_name) return order.guest_name;
        return 'Dine-in';
    }

    function orderMetaDetail(order) {
        const parts = [];
        if (order.service_type === 'dine_in' || order.service_type_label === 'Dine-in') {
            if (order.table_name) parts.push('Table ' + order.table_name);
            else if (order.guest_name) parts.push('Table ' + order.guest_name);
        } else if (order.service_type === 'delivery' || order.service_type_label === 'Delivery') {
            if (order.guest_name) parts.push(order.guest_name);
            if (order.room_no) parts.push(order.room_no);
        } else if (order.guest_name) {
            parts.push(order.guest_name);
        }
        return parts.join(' · ') || '—';
    }

    function factorForUom(p, uomCode) {
        const u = String(uomCode ?? '').trim();
        if (!p || !u) return 1;
        if (String(p.uom).toLowerCase() === u.toLowerCase()) return 1;
        const row = (p.uoms || []).find((x) => String(x.uom).toLowerCase() === u.toLowerCase());
        return row && Number(row.factor) > 0 ? Number(row.factor) : 1;
    }

    function unitPriceForProduct(p, uomCode) {
        const factor = factorForUom(p, uomCode);
        return Math.round(Number(p.price || 0) * factor * 100) / 100;
    }

    function isProductVisible(p) {
        return !!p.for_pos;
    }

    function getBillDiscountPercent() {
        return posShowDiscount ? Number($('#rpBillDiscount')?.value || 0) : 0;
    }

    function calcCartTotals() {
        let subtotal = 0;
        const lineSubs = [];
        cart.forEach((r) => {
            const s = Number(r.qty) * Number(r.unit_price);
            lineSubs.push(s);
            subtotal += s;
        });
        subtotal = Math.round(subtotal * 100) / 100;
        const billDiscPct = getBillDiscountPercent();
        let discount = posShowDiscount && billDiscPct > 0 ? Math.round(subtotal * billDiscPct / 100 * 100) / 100 : 0;
        const tax = 0;
        const grand = Math.round((subtotal - discount) * 100) / 100;
        return { subtotal, discount, tax, grand, lineSubs, billDiscPct };
    }

    function lineRowTotal(r, totals, idx) {
        const lineSub = totals.lineSubs[idx] ?? (Number(r.qty) * Number(r.unit_price));
        let lineDisc = 0;
        if (totals.discount > 0 && totals.subtotal > 0) {
            lineDisc = Math.round(totals.discount * (lineSub / totals.subtotal) * 100) / 100;
        }
        const lineNet = lineSub - lineDisc;
        return Math.round(lineNet * 100) / 100;
    }

    function cartQtyForProduct(productId) {
        return cart.filter((r) => Number(r.product_id) === Number(productId)).reduce((s, r) => s + Number(r.qty), 0);
    }

    function cartLockedQtyForProduct(productId) {
        return cart
            .filter((r) => Number(r.product_id) === Number(productId))
            .reduce((s, r) => s + (Number(r.kitchen_locked_qty) || 0), 0);
    }

    function kitchenLockedFromResume(ri) {
        const qty = Number(ri.qty) || 0;
        return (ri.kitchen_served || ri.kitchen_pending) ? qty : 0;
    }

    function addOrIncrementProduct(id) {
        const p = products.find((x) => Number(x.id) === Number(id));
        if (!p || !isProductVisible(p)) return;
        const existing = cart.find((r) => Number(r.product_id) === Number(id) && String(r.uom) === String(p.uom));
        if (existing) {
            existing.qty = Math.round((Number(existing.qty) + 1) * 1000) / 1000;
            existing.unit_price = unitPriceForProduct(p, existing.uom);
        } else {
            cart.push({
                product_id: p.id,
                name: p.name,
                uom: p.uom,
                qty: 1,
                unit_price: unitPriceForProduct(p, p.uom),
                tax_percent: 0,
                notes: '',
                kitchen_served: false,
                kitchen_pending: false,
                kitchen_locked_qty: 0,
            });
        }
        renderAll();
    }

    async function removeCartLine(index, reason) {
        const row = cart[index];
        if (!row) return;

        const locked = Number(row.kitchen_locked_qty) || 0;
        const voidsBefore = kitchenVoids.length;
        if (locked > 0) {
            const reasonText = String(reason || '').trim();
            if (!reasonText) {
                openRemoveReasonModal(index);
                return;
            }
            kitchenVoids.push({
                product_id: row.product_id,
                uom: row.uom,
                qty: locked,
                reason: reasonText,
                notes: String(row.notes || '').trim(),
                name: row.name,
            });
        }

        cart.splice(index, 1);
        renderAll();

        if (!resumeOrderId) {
            return;
        }

        try {
            await saveResumedDraftChanges();
        } catch (e) {
            cart.splice(index, 0, row);
            kitchenVoids.length = voidsBefore;
            renderAll();
            throw e;
        }
    }

    function getRemoveReasonModal() {
        const el = $('#rpRemoveReasonModal');
        if (!el || !window.bootstrap?.Modal) return null;
        if (!removeReasonModalInstance) {
            removeReasonModalInstance = new window.bootstrap.Modal(el, { backdrop: 'static', keyboard: true });
        }
        return removeReasonModalInstance;
    }

    function openRemoveReasonModal(index) {
        pendingRemoveIndex = index;
        const row = cart[index];
        const label = $('#rpRemoveItemName');
        if (label) {
            label.textContent = row ? `${fmtQty(row.qty)}× ${row.name}` : '';
        }
        const input = $('#rpRemoveReason');
        if (input) {
            input.value = '';
        }
        $('#rpRemoveReasonError')?.classList.add('d-none');
        getRemoveReasonModal()?.show();
        setTimeout(() => input?.focus(), 280);
    }

    async function confirmRemoveWithReason() {
        const reason = ($('#rpRemoveReason')?.value || '').trim();
        if (reason.length < 3) {
            $('#rpRemoveReasonError')?.classList.remove('d-none');
            return;
        }
        if (pendingRemoveIndex === null) return;
        const idx = pendingRemoveIndex;
        pendingRemoveIndex = null;
        getRemoveReasonModal()?.hide();
        try {
            await removeCartLine(idx, reason);
        } catch (e) {
            alert(e.message || 'Item remove save nahi ho saki.');
        }
    }

    function cancelRemoveReasonModal() {
        pendingRemoveIndex = null;
        getRemoveReasonModal()?.hide();
    }

    function changeCartQty(productId, delta) {
        const p = products.find((x) => Number(x.id) === Number(productId));
        if (delta > 0) {
            addOrIncrementProduct(productId);
            return;
        }
        const locked = cartLockedQtyForProduct(productId);
        const totalQty = cartQtyForProduct(productId);
        const next = Math.round((totalQty + delta) * 1000) / 1000;
        if (next < locked) {
            alert('Kitchen me bheji hui quantity kam nahi ho sakti.');
            return;
        }
        if (next <= 0) {
            cart = cart.filter((r) => Number(r.product_id) !== Number(productId));
            renderAll();
            return;
        }
        let remaining = Math.abs(delta);
        for (let i = cart.length - 1; i >= 0 && remaining > 0; i--) {
            const row = cart[i];
            if (Number(row.product_id) !== Number(productId)) continue;
            const rowLocked = Number(row.kitchen_locked_qty) || 0;
            const reducible = Math.max(0, Number(row.qty) - rowLocked);
            const take = Math.min(reducible, remaining);
            if (take <= 0) continue;
            row.qty = Math.round((Number(row.qty) - take) * 1000) / 1000;
            remaining -= take;
            if (p) row.unit_price = unitPriceForProduct(p, row.uom);
        }
        cart = cart.filter((r) => Number(r.qty) > 0.0005);
        renderAll();
    }

    function applySaleModePricing() {
        cart.forEach((r) => {
            const p = products.find((x) => Number(x.id) === Number(r.product_id));
            if (p) r.unit_price = unitPriceForProduct(p, r.uom);
        });
    }

    function productMatchesMenuCategory(p) {
        if (!selectedMenuCategoryId) return true;
        return Number(p.category_id) === Number(selectedMenuCategoryId);
    }

    function renderMenuCategories() {
        const wrap = $('#rpMenuCats');
        if (!wrap) return;

        if (!menuCategories.length) {
            wrap.innerHTML = '';
            wrap.classList.add('d-none');
            return;
        }

        wrap.classList.remove('d-none');
        const allActive = !selectedMenuCategoryId;
        let html = `<button type="button" class="rp-menu-cat${allActive ? ' is-active' : ''}" data-cat-id="">All</button>`;
        html += menuCategories.map((c) => {
            const active = String(selectedMenuCategoryId) === String(c.id);
            return `<button type="button" class="rp-menu-cat${active ? ' is-active' : ''}" data-cat-id="${c.id}">${escHtml(c.name)}</button>`;
        }).join('');
        wrap.innerHTML = html;
    }

    function setMenuCategory(catId) {
        selectedMenuCategoryId = catId ? String(catId) : null;
        renderMenuCategories();
        renderMenuGrid();
    }

    function displayProductName(name) {
        const s = String(name || '').trim();
        if (!s) return '';
        const letters = s.replace(/[^a-zA-Z]/g, '');
        if (letters.length >= 4 && letters === letters.toUpperCase()) {
            return s.toLowerCase().replace(/\b\w/g, (c) => c.toUpperCase());
        }
        return s;
    }

    function renderMenuGrid() {
        const grid = $('#rpMenuGrid');
        const q = ($('#rpProductSearch')?.value || '').trim().toLowerCase();
        if (!grid) return;
        const list = products.filter((p) => isProductVisible(p) && productMatchesMenuCategory(p) && (
            !q || String(p.name).toLowerCase().includes(q) || String(p.sku || '').toLowerCase().includes(q)
        ));
        if (!list.length) {
            grid.innerHTML = `<div class="rp-empty rp-empty--menu">
                <span class="rp-empty-icon"><i class="bi bi-search"></i></span>
                <span>${selectedMenuCategoryId ? 'Is category mein koi product nahi.' : 'Koi product nahi mili.'}</span>
            </div>`;
            return;
        }
        grid.innerHTML = list.map((p) => {
            const qty = cartQtyForProduct(p.id);
            const locked = cartLockedQtyForProduct(p.id);
            const canDec = qty > locked;
            const price = unitPriceForProduct(p, p.uom);
            const label = displayProductName(p.name);
            const img = p.image_url
                ? `<img src="${escHtml(p.image_url)}" alt="" class="rp-mi-photo">`
                : `<div class="rp-mi-photo rp-mi-photo--empty"><i class="bi bi-image"></i></div>`;
            return `<div class="rp-menu-item${qty > 0 ? ' has-qty' : ''}" data-product-id="${p.id}">
                ${img}
                <div class="rp-mi-name">${escHtml(label)}</div>
                <div class="rp-mi-price">${fmtMoney(price)}</div>
                <div class="rp-mi-qty">
                    <button type="button" data-action="dec" data-id="${p.id}"${canDec ? '' : ' disabled'} aria-label="Decrease">−</button>
                    <span class="rp-mi-qty-val">${qty > 0 ? fmtQty(qty) : '0'}</span>
                    <button type="button" data-action="inc" data-id="${p.id}" aria-label="Increase">+</button>
                </div>
            </div>`;
        }).join('');
    }

    function renderCart() {
        const wrap = $('#rpCartLines');
        if (!wrap) return;
        if (!cart.length) {
            wrap.innerHTML = `<div class="rp-empty">
                <span class="rp-empty-icon"><i class="bi bi-bag"></i></span>
                <span>Cart khali hai — menu se item add karein.</span>
            </div>`;
            return;
        }
        const totals = calcCartTotals();
        wrap.innerHTML = cart.map((r, i) => {
            const total = lineRowTotal(r, totals, i);
            const locked = Number(r.kitchen_locked_qty) || 0;
            const kitchenBadge = locked > 0
                ? `<span class="rp-kitchen-pill ${r.kitchen_served ? 'rp-kitchen-pill--served' : 'rp-kitchen-pill--pending'}" title="Kitchen me bheja hua">
                    <i class="bi ${r.kitchen_served ? 'bi-check-circle-fill' : 'bi-fire'}"></i>
                    ${r.kitchen_served ? 'Served' : 'Kitchen'}
                   </span>`
                : '';
            return `<div class="rp-cart-line${locked > 0 ? ' is-kitchen-locked' : ''}" data-cart-index="${i}">
                <div class="rp-cl-main">
                    <span class="rp-cl-qty">${fmtQty(r.qty)}×</span>
                    <span class="rp-cl-name">${escHtml(r.name)}</span>
                    ${kitchenBadge}
                </div>
                <div class="rp-cl-actions">
                    <div class="rp-cl-total">${fmtMoney(total)}</div>
                    <button type="button" class="rp-cl-remove" data-action="remove-line" data-index="${i}" aria-label="Remove item" title="${locked > 0 ? 'Kitchen item — reason required' : 'Remove item'}">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>`;
        }).join('');
    }

    function renderTotals() {
        const { subtotal, discount, tax, grand } = calcCartTotals();
        const el = (id, v) => { const n = $(id); if (n) n.textContent = typeof v === 'number' ? fmtMoney(v) : String(v); };
        const itemQty = cart.reduce((s, r) => s + Number(r.qty), 0);
        el('#rpSumItems', cart.length ? `${fmtQty(itemQty)} (${cart.length})` : '0');
        el('#rpSumSubtotal', subtotal);
        el('#rpSumDiscount', discount);
        el('#rpSumGrand', grand);
        const countEl = $('#rpCartCount');
        if (countEl) countEl.textContent = String(cart.length);
        if (autoPaymentAmount && payments.length === 1) {
            payments[0].amount = grand;
        }
    }

    let orderListMode = null;
    let panelView = 'split';

    function setPanelView(view) {
        const app = document.querySelector('.restaurant-pos-app');
        if (!app) return;

        panelView = view;
        app.classList.remove('rp-view-menu', 'rp-view-cart');
        if (view === 'menu') app.classList.add('rp-view-menu');
        if (view === 'cart') app.classList.add('rp-view-cart');
        if (view === 'cart' && orderListMode) {
            setOrderListMode(orderListMode);
        }

        $('#rpTabMenu')?.classList.toggle('is-active', view === 'menu');
        $('#rpTabCart')?.classList.toggle('is-active', view === 'cart');

        const expandBtn = $('#rpToggleCartView');
        const icon = expandBtn?.querySelector('i');
        if (icon) {
            icon.className = view === 'cart' ? 'bi bi-layout-sidebar-reverse' : 'bi bi-arrows-fullscreen';
        }
        if (expandBtn) {
            expandBtn.title = view === 'cart' ? 'Menu dikhayen' : 'Cart full view';
        }
    }

    function togglePanelView(view) {
        setPanelView(panelView === view ? 'split' : view);
    }

    function updateOrderTabCounts() {
        const pendingCount = (boot.pendingBillsDetail || []).length;
        const paidCount = (boot.paidBillsDetail || []).length;
        const pendingEl = $('#rpPendingCount');
        const paidEl = $('#rpPaidCount');
        if (pendingEl) pendingEl.textContent = String(pendingCount);
        if (paidEl) paidEl.textContent = String(paidCount);
    }

    function setOrderListMode(mode) {
        const panel = $('#rpOrderLinePanel');
        const tabPending = $('#rpTabPending');
        const tabPaid = $('#rpTabPaid');
        if (orderListMode === mode) {
            orderListMode = null;
            panel?.classList.add('d-none');
            tabPending?.classList.remove('is-active');
            tabPaid?.classList.remove('is-active');
            return;
        }
        orderListMode = mode;
        panel?.classList.remove('d-none');
        tabPending?.classList.toggle('is-active', mode === 'pending');
        tabPaid?.classList.toggle('is-active', mode === 'paid');
        renderOrderCards();
    }

    function renderOrderCards() {
        const wrap = $('#rpOrderLine');
        if (!wrap || !orderListMode) return;

        if (orderListMode === 'pending') {
            const orders = boot.pendingBillsDetail || [];
            if (!orders.length) {
                wrap.innerHTML = `<div class="rp-empty" style="min-height:0;padding:0.5rem;">
                    <span class="text-secondary small">Koi pending order nahi.</span>
                </div>`;
                return;
            }
            wrap.innerHTML = orders.map((o) => {
                const resumeUrl = (routes.resume || '').replace('__ID__', String(o.id));
                return `<a class="rp-order-card" href="${escHtml(resumeUrl)}">
                    <div class="rp-oc-no">${escHtml(o.order_no)}</div>
                    <div class="rp-oc-meta">${escHtml(orderMetaLabel(o))} · ${escHtml(orderMetaDetail(o))}</div>
                    <div class="rp-oc-meta">${escHtml(fmtMoney(o.grand_total))} · ${o.items_count || 0} items</div>
                </a>`;
            }).join('');
            return;
        }

        const paid = boot.paidBillsDetail || [];
        if (!paid.length) {
            wrap.innerHTML = `<div class="rp-empty" style="min-height:0;padding:0.5rem;">
                <span class="text-secondary small">Aaj koi paid order nahi.</span>
            </div>`;
            return;
        }
        wrap.innerHTML = paid.map((o) => {
            const receiptUrl = (routes.receipt || '').replace('__ID__', String(o.id));
            const paidAt = o.paid_at_full || o.paid_at || '';
            return `<a class="rp-order-card rp-order-card-paid" href="${escHtml(receiptUrl)}" target="_blank" rel="noopener">
                <div class="rp-oc-no">${escHtml(o.order_no)}</div>
                <div class="rp-oc-meta">${escHtml(orderMetaLabel(o))} · ${escHtml(orderMetaDetail(o))}</div>
                <div class="rp-oc-meta">${escHtml(fmtMoney(o.grand_total))} · ${o.payment_label || 'Paid'}</div>
                ${paidAt ? `<div class="rp-oc-pay">${escHtml(paidAt)}</div>` : ''}
            </a>`;
        }).join('');
    }

    function renderAll() {
        renderMenuCategories();
        renderMenuGrid();
        renderCart();
        renderTotals();
        syncWhatsappButton();
        if (autoPaymentAmount && payments.length === 1) {
            payments[0].amount = calcCartTotals().grand;
        }
    }

    let autoPaymentAmount = true;

    function cartItemsForSubmit() {
        const totals = calcCartTotals();
        return cart.map((r, idx) => ({
            product_id: r.product_id,
            uom: r.uom,
            qty: r.qty,
            unit_price: r.unit_price,
            discount_percent: 0,
            tax_percent: 0,
            notes: String(r.notes || '').trim(),
            line_total: lineRowTotal(r, totals, idx),
        }));
    }

    function prepareSubmit(mode) {
        if (!cart.length) {
            alert('Pehle item add karein.');
            return false;
        }

        const serviceType = selectedServiceType();
        if (serviceType === 'dine_in') {
            if (posTablesEnabled) {
                if (!($('#rpTable')?.value || '').trim()) {
                    alert('Table No. select karein.');
                    return false;
                }
            } else if (!($('#rpTableNo')?.value || '').trim()) {
                alert('Table No. enter karein.');
                return false;
            }
        } else if (serviceType === 'delivery') {
            if (!($('#rpDeliveryName')?.value || '').trim()) {
                alert('Customer Name enter karein.');
                return false;
            }
            if (!($('#rpDeliveryPhone')?.value || '').trim()) {
                alert('Phone No. enter karein.');
                return false;
            }
            if (!($('#rpDeliveryAddress')?.value || '').trim()) {
                alert('Address enter karein.');
                return false;
            }
        }

        if (isCreditMode && mode === 'checkout' && !selectedContactId) {
            alert('Credit sale ke liye contact select karein.');
            return false;
        }

        if (mode === 'checkout' && !isCreditMode && orderType === 'sale') {
            const grand = calcCartTotals().grand;
            if (autoPaymentAmount && payments.length === 1) {
                payments[0].amount = grand;
                payments[0].method = $('#rpPayMethod')?.value || payments[0].method || 'cash';
            }
            const paySum = payments.reduce((s, p) => s + Number(p.amount || 0), 0);
            if (Math.abs(paySum - grand) > 0.02) {
                alert('Payment total match nahi kar raha.');
                return false;
            }
        }

        applySaleModePricing();
        const form = $('#rpSubmitForm');
        if (!form) return false;

        form.querySelector('[name="type"]').value = orderType;
        form.querySelector('[name="sale_mode"]').value = 'customer';
        form.querySelector('[name="staff_include_gas"]').value = '0';
        form.querySelector('[name="customer_type"]').value = 'mess_use';
        form.querySelector('[name="service_type"]').value = serviceType;
        form.querySelector('[name="is_credit"]').value = (isCreditMode && mode === 'checkout') ? '1' : '0';
        form.querySelector('[name="contact_id"]').value = (isCreditMode && mode === 'checkout') ? (selectedContactId || '') : '';

        if (serviceType === 'dine_in') {
            form.querySelector('[name="table_id"]').value = posTablesEnabled ? ($('#rpTable')?.value || '') : '';
            form.querySelector('[name="guest_name"]').value = posTablesEnabled ? '' : ($('#rpTableNo')?.value || '').trim();
            form.querySelector('[name="room_no"]').value = '';
            form.querySelector('[name="order_notes"]').value = '';
        } else if (serviceType === 'delivery') {
            form.querySelector('[name="table_id"]').value = '';
            form.querySelector('[name="guest_name"]').value = ($('#rpDeliveryName')?.value || '').trim();
            form.querySelector('[name="room_no"]').value = ($('#rpDeliveryPhone')?.value || '').trim();
            form.querySelector('[name="order_notes"]').value = ($('#rpDeliveryAddress')?.value || '').trim();
        } else {
            form.querySelector('[name="table_id"]').value = '';
            form.querySelector('[name="guest_name"]').value = '';
            form.querySelector('[name="room_no"]').value = '';
            form.querySelector('[name="order_notes"]').value = '';
        }

        form.querySelector('[name="items"]').value = JSON.stringify(cartItemsForSubmit());
        form.querySelector('[name="payments"]').value = JSON.stringify(
            mode === 'hold'
                ? [{ method: 'cash', amount: 0 }]
                : (isCreditMode ? [] : payments)
        );
        form.querySelector('[name="bill_tax_percent"]').value = '0';
        form.querySelector('[name="bill_discount_percent"]').value = posShowDiscount ? String(getBillDiscountPercent()) : '0';
        form.querySelector('[name="resume_order_id"]').value = resumeOrderId ? String(resumeOrderId) : '';
        const kitchenVoidsInput = form.querySelector('[name="kitchen_voids"]');
        if (kitchenVoidsInput) {
            kitchenVoidsInput.value = JSON.stringify(kitchenVoids);
        }
        const cashTenderedInput = form.querySelector('[name="cash_tendered"]');
        const cashChangeInput = form.querySelector('[name="cash_change"]');
        if (cashTenderedInput) cashTenderedInput.value = '';
        if (cashChangeInput) cashChangeInput.value = '';
        form.action = mode === 'hold' ? routes.hold : routes.checkout;
        return true;
    }

    function checkoutFormData(extraFields = {}) {
        const form = $('#rpSubmitForm');
        if (!form) return null;

        const totals = calcCartTotals();
        const formData = new FormData(form);
        formData.set('items', JSON.stringify(cartItemsForSubmit()));
        if (!isCreditMode) {
            const payMethod = $('#rpPayMethod')?.value || 'cash';
            formData.set('payments', JSON.stringify([{ method: payMethod, amount: totals.grand }]));
        }
        Object.entries(extraFields).forEach(([key, value]) => {
            formData.set(key, String(value));
        });
        return formData;
    }

    async function postCheckout(extraFields = {}) {
        if (!prepareSubmit('checkout')) return false;

        const formData = checkoutFormData(extraFields);
        if (!formData) return false;

        const res = await fetch(routes.checkout, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        });

        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            const validationMsg = data.errors ? Object.values(data.errors).flat()[0] : null;
            throw new Error(data.message || validationMsg || 'Payment failed.');
        }

        const receiptUrl = data.receipt_url
            || (data.order_id ? (routes.receipt || '').replace('__ID__', String(data.order_id)) : '');
        if (receiptUrl) {
            window.location.assign(receiptUrl);
            return true;
        }

        if (data.redirect_url) {
            window.location.assign(data.redirect_url);
            return true;
        }

        resetForNewBill();
        return true;
    }

    function getPayModal() {
        const el = $('#rpPayModal');
        if (!el || !window.bootstrap?.Modal) return null;
        if (!payModalInstance) {
            payModalInstance = new window.bootstrap.Modal(el, { backdrop: 'static', keyboard: true });
        }
        return payModalInstance;
    }

    function updatePayModalAmounts() {
        const grand = calcCartTotals().grand;
        const tendered = Number($('#rpCashTendered')?.value || 0);
        const change = Math.max(0, Math.round((tendered - grand) * 100) / 100);
        const ok = tendered >= grand - 0.001;

        if ($('#rpPayModalTotal')) $('#rpPayModalTotal').textContent = fmtMoney(grand);
        if ($('#rpCashChange')) $('#rpCashChange').textContent = fmtMoney(change);
        if ($('#rpPayModalConfirm')) $('#rpPayModalConfirm').disabled = !ok;
        $('#rpCashInsufficient')?.classList.toggle('d-none', ok || tendered <= 0);
    }

    function openPayModal() {
        if (!prepareSubmit('checkout')) return;

        if (isCreditMode) {
            submitOrder('checkout');
            return;
        }

        const payMethod = $('#rpPayMethod')?.value || 'cash';
        if (payMethod !== 'cash') {
            submitOrder('checkout');
            return;
        }

        const grand = calcCartTotals().grand;
        const input = $('#rpCashTendered');
        if (input) {
            input.value = '';
        }
        updatePayModalAmounts();

        const modal = getPayModal();
        if (!modal) {
            submitOrder('checkout');
            return;
        }
        modal.show();
        setTimeout(() => {
            input?.focus();
            input?.select();
        }, 280);
    }

    async function confirmPayModal() {
        const grand = calcCartTotals().grand;
        const tendered = Number($('#rpCashTendered')?.value || 0);
        if (tendered < grand - 0.001) {
            updatePayModalAmounts();
            return;
        }

        const change = Math.max(0, Math.round((tendered - grand) * 100) / 100);
        const confirmBtn = $('#rpPayModalConfirm');
        if (confirmBtn) confirmBtn.disabled = true;

        try {
            await postCheckout({
                cash_tendered: tendered,
                cash_change: change,
            });
            getPayModal()?.hide();
        } catch (e) {
            alert(e.message || 'Payment failed.');
            updatePayModalAmounts();
        } finally {
            if (confirmBtn) confirmBtn.disabled = false;
        }
    }

    function upsertPendingBill(order, updated) {
        const list = Array.isArray(boot.pendingBillsDetail) ? [...boot.pendingBillsDetail] : [];
        const idx = list.findIndex((o) => Number(o.id) === Number(order.id));
        if (idx >= 0) {
            list[idx] = order;
        } else if (!updated) {
            list.unshift(order);
        } else {
            list.unshift(order);
        }
        boot.pendingBillsDetail = list;
        updateOrderTabCounts();
        if (orderListMode === 'pending') {
            renderOrderCards();
        }
    }

    function resetForNewBill() {
        cart.length = 0;
        kitchenVoids = [];
        pendingRemoveIndex = null;
        resumeOrderId = null;
        payments = [{ method: $('#rpPayMethod')?.value || 'cash', amount: 0 }];
        autoPaymentAmount = true;

        const form = $('#rpSubmitForm');
        if (form) {
            form.querySelector('[name="resume_order_id"]').value = '';
        }

        if ($('#rpTable')) $('#rpTable').value = '';
        if ($('#rpTableNo')) $('#rpTableNo').value = '';
        if ($('#rpDeliveryName')) $('#rpDeliveryName').value = '';
        if ($('#rpDeliveryPhone')) $('#rpDeliveryPhone').value = '';
        if ($('#rpDeliveryAddress')) $('#rpDeliveryAddress').value = '';
        selectedContactId = null;
        $('#rpSelectedContactWrap')?.classList.add('d-none');
        if ($('#rpContactSearch')) $('#rpContactSearch').value = '';
        setCreditMode(false);

        document.querySelector('.rp-badge-order')?.remove();

        const url = new URL(window.location.href);
        if (url.searchParams.has('resume_order')) {
            url.searchParams.delete('resume_order');
            window.history.replaceState({}, '', url.pathname + url.search);
        }

        setServiceType('dine_in');
        renderAll();
        $('#rpProductSearch')?.focus();
    }

    function buildHoldFormData() {
        if (!cart.length) {
            return null;
        }
        if (!prepareSubmit('hold')) {
            return null;
        }
        const form = $('#rpSubmitForm');
        if (!form) {
            return null;
        }

        const totals = calcCartTotals();
        const formData = new FormData(form);
        formData.set('items', JSON.stringify(cartItemsForSubmit()));
        formData.set('kitchen_voids', JSON.stringify(kitchenVoids));
        formData.set('client_grand_total', String(totals.grand));
        formData.set('client_subtotal', String(totals.subtotal));
        formData.set('client_discount_total', String(totals.discount));
        formData.set('client_tax_total', String(totals.tax));
        return formData;
    }

    function clearStaleResumeState(message) {
        if (resumeOrderId) {
            boot.pendingBillsDetail = (boot.pendingBillsDetail || []).filter(
                (o) => Number(o.id) !== Number(resumeOrderId)
            );
            updateOrderTabCounts();
            if (orderListMode === 'pending') {
                renderOrderCards();
            }
        }
        kitchenVoids = [];
        resetForNewBill();
        if (message) {
            alert(message);
        }
    }

    function isStaleOrderResponse(res, data, message) {
        if (res.status === 404) {
            return true;
        }
        const text = String(message || data.message || '').toLowerCase();
        return text.includes('no query results for model') && text.includes('posorder');
    }

    function enqueueResumeSave(task) {
        const run = resumeSaveLock.then(task, task);
        resumeSaveLock = run.catch(() => {});
        return run;
    }

    async function discardResumedDraft() {
        if (!resumeOrderId) {
            return;
        }

        const orderId = resumeOrderId;
        const url = (routes.discardHold || '').replace('__ID__', String(orderId));
        if (!url) {
            throw new Error('Discard route missing.');
        }

        const body = new FormData();
        body.append('_token', csrf);
        body.append('_method', 'DELETE');

        const res = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body,
        });
        const data = await res.json().catch(() => ({}));

        if (isStaleOrderResponse(res, data, data.message)) {
            clearStaleResumeState('Ye pending order pehle se band ho chuki hai.');
            return;
        }

        if (!res.ok && !data.already_discarded) {
            throw new Error(data.message || 'Pending order discard nahi ho saki.');
        }

        boot.pendingBillsDetail = (boot.pendingBillsDetail || []).filter(
            (o) => Number(o.id) !== Number(orderId)
        );
        updateOrderTabCounts();
        if (orderListMode === 'pending') {
            renderOrderCards();
        }
        kitchenVoids = [];
        resetForNewBill();
    }

    async function saveResumedDraftChanges() {
        if (!resumeOrderId) {
            return;
        }

        return enqueueResumeSave(async () => {
            setCartSaving(true);
            try {
                if (!cart.length) {
                    await discardResumedDraft();
                    return;
                }

                const formData = buildHoldFormData();
                if (!formData) {
                    throw new Error('Order save tayyar nahi ho saki.');
                }

                const res = await fetch(routes.hold, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: formData,
                });
                const data = await res.json().catch(() => ({}));
                const validationMsg = data.errors ? Object.values(data.errors).flat()[0] : null;
                const errMsg = data.message || validationMsg || 'Order save nahi ho saki.';

                if (isStaleOrderResponse(res, data, errMsg)) {
                    clearStaleResumeState('Ye pending order pehle se band ho chuki hai.');
                    return;
                }

                if (!res.ok) {
                    throw new Error(errMsg);
                }

                if (data.order) {
                    upsertPendingBill(data.order, true);
                }
                kitchenVoids = [];
            } finally {
                setCartSaving(false);
            }
        });
    }

    function setCartSaving(isSaving) {
        const wrap = $('#rpCartLines');
        if (!wrap) return;
        wrap.classList.toggle('is-saving', isSaving);
        wrap.querySelectorAll('.rp-cl-remove').forEach((btn) => {
            btn.disabled = isSaving;
        });
    }

    async function submitHoldOrder() {
        if (!prepareSubmit('hold')) return;

        const holdBtn = $('#rpHoldBtn');
        if (holdBtn) holdBtn.disabled = true;
        try {
            if (resumeOrderId) {
                await saveResumedDraftChanges();
                resetForNewBill();
                return;
            }

            const formData = buildHoldFormData();
            if (!formData) return;

            const res = await fetch(routes.hold, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                const validationMsg = data.errors ? Object.values(data.errors).flat()[0] : null;
                throw new Error(data.message || validationMsg || 'Hold failed.');
            }

            if (data.order) {
                upsertPendingBill(data.order, !!data.updated);
            } else if (typeof data.held_count === 'number') {
                updateOrderTabCounts();
            }

            resetForNewBill();
        } catch (e) {
            alert(e.message || 'Hold failed.');
        } finally {
            if (holdBtn) holdBtn.disabled = false;
        }
    }

    async function submitOrder(mode) {
        if (mode === 'checkout' && !isCreditMode) {
            const confirmBtn = $('#rpPayBtn');
            if (confirmBtn) confirmBtn.disabled = true;
            try {
                await postCheckout();
            } catch (e) {
                alert(e.message || 'Payment failed.');
            } finally {
                if (confirmBtn) confirmBtn.disabled = false;
            }
            return;
        }

        if (!prepareSubmit(mode)) return;
        $('#rpSubmitForm')?.submit();
    }

    function bindEvents() {
        $('#rpProductSearch')?.addEventListener('input', renderMenuGrid);
        $('#rpMenuCats')?.addEventListener('click', (e) => {
            const btn = e.target.closest('.rp-menu-cat');
            if (!btn) return;
            setMenuCategory(btn.dataset.catId || null);
        });
        $('#rpMenuGrid')?.addEventListener('click', (e) => {
            const btn = e.target.closest('button[data-action]');
            if (!btn) return;
            const id = Number(btn.dataset.id);
            if (btn.dataset.action === 'inc') addOrIncrementProduct(id);
            if (btn.dataset.action === 'dec') changeCartQty(id, -1);
        });
        $('#rpCartLines')?.addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-action="remove-line"]');
            if (!btn || btn.disabled) return;
            const index = Number(btn.dataset.index);
            if (!Number.isFinite(index)) return;
            try {
                await removeCartLine(index);
            } catch (err) {
                alert(err.message || 'Item remove save nahi ho saki.');
            }
        });
        $('#rpServiceTypes')?.addEventListener('click', (e) => {
            const btn = e.target.closest('.rp-service-type');
            if (!btn?.dataset.type) return;
            setServiceType(btn.dataset.type);
        });
        $('#rpHoldBtn')?.addEventListener('click', () => submitHoldOrder());
        $('#rpWhatsappBtn')?.addEventListener('click', () => openDeliveryWhatsapp());
        $('#rpPayBtn')?.addEventListener('click', () => openPayModal());
        $('#rpPayModalConfirm')?.addEventListener('click', () => confirmPayModal());
        $('#rpCashTendered')?.addEventListener('input', updatePayModalAmounts);
        $('#rpCashTendered')?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (!$('#rpPayModalConfirm')?.disabled) {
                    confirmPayModal();
                }
            }
        });
        $('#rpTabPending')?.addEventListener('click', () => setOrderListMode('pending'));
        $('#rpTabPaid')?.addEventListener('click', () => setOrderListMode('paid'));
        $('#rpTabMenu')?.addEventListener('click', () => togglePanelView('menu'));
        $('#rpTabCart')?.addEventListener('click', () => togglePanelView('cart'));
        $('#rpToggleCartView')?.addEventListener('click', () => togglePanelView('cart'));
        $('#rpBillDiscount')?.addEventListener('input', renderTotals);

        $('#rpCreditToggle')?.addEventListener('change', (e) => setCreditMode(e.target.checked));

        const contactSearch = $('#rpContactSearch');
        const contactDrop = $('#rpContactDropdown');
        contactSearch?.addEventListener('input', () => {
            const q = contactSearch.value.trim();
            if (q.length < 1) {
                contactDrop?.classList.add('d-none');
                return;
            }
            const rows = filterContacts(q);
            contactDrop.innerHTML = rows.map((c) =>
                `<button type="button" class="dropdown-item" data-id="${c.id}" data-name="${escHtml(c.name)}" data-phone="${escHtml(c.phone || '')}">${escHtml(c.name)} <span class="text-secondary">${escHtml(c.phone || '')}</span></button>`
            ).join('') || '<div class="dropdown-item-text text-secondary small">No contact</div>';
            contactDrop.classList.remove('d-none');
        });
        contactDrop?.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-id]');
            if (!btn) return;
            selectContact(btn.dataset.id, btn.dataset.name, btn.dataset.phone || '');
        });
        $('#rpClearContact')?.addEventListener('click', () => {
            selectedContactId = null;
            $('#rpSelectedContactWrap')?.classList.add('d-none');
        });

        $('#rpPayMethod')?.addEventListener('change', () => {
            payments = [{ method: $('#rpPayMethod')?.value || 'cash', amount: calcCartTotals().grand }];
        });

        $('#rpRemoveConfirm')?.addEventListener('click', () => confirmRemoveWithReason());
        $('#rpRemoveReason')?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                confirmRemoveWithReason();
            }
        });
        $('#rpRemoveReasonModal')?.addEventListener('hidden.bs.modal', () => {
            pendingRemoveIndex = null;
        });
    }

    function loadResumeItems() {
        const items = boot.resumeItems || [];
        items.forEach((ri) => {
            const p = products.find((x) => Number(x.id) === Number(ri.product_id));
            if (!p) return;
            cart.push({
                product_id: ri.product_id,
                name: p.name,
                uom: ri.uom || p.uom,
                qty: Number(ri.qty) || 1,
                unit_price: Number(ri.unit_price) || unitPriceForProduct(p, ri.uom || p.uom),
                tax_percent: Number(ri.tax_percent) || 0,
                notes: ri.notes || '',
                kitchen_served: !!ri.kitchen_served,
                kitchen_pending: !!ri.kitchen_pending,
                kitchen_locked_qty: kitchenLockedFromResume(ri),
            });
        });
    }

    async function pollSync() {
        if (!routes.sync) return;
        try {
            const res = await fetch(routes.sync, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) return;
            const data = await res.json();
            if (Array.isArray(data.pending)) {
                boot.pendingBillsDetail = data.pending;
                updateOrderTabCounts();
                if (orderListMode === 'pending') {
                    renderOrderCards();
                }
            }
        } catch (_) { /* ignore */ }
    }

    function init() {
        if (settings.resume_service_type) {
            setServiceType(settings.resume_service_type);
        } else {
            syncServiceDetailPanels();
        }
        if (posShowCustomerSection && settings.resume_is_credit) {
            setCreditMode(true);
        }
        restoreResumeContact();
        loadResumeItems();
        bindEvents();
        updateOrderTabCounts();
        renderAll();
        payments = [{ method: 'cash', amount: 0 }];
        setInterval(pollSync, 20000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
