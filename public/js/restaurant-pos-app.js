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
    const posServiceChargeEnabled = settings.service_charge_enabled === true;
    const posServiceChargePercent = posServiceChargeEnabled ? Number(settings.service_charge_percent || 0) : 0;
    const canPosPay = boot.canPosPay === true;
    const canPosDiscount = boot.canPosDiscount === true;
    const canPosDiscountCredit = boot.canPosDiscountCredit === true;
    const canViewKitchenVoids = boot.canViewKitchenVoids === true;
    const posShowDiscount = canPosDiscount && settings.show_discount !== false;
    const resumeBillDiscount = Number(settings.resume_bill_discount_percent || 0);
    const resumeOwnerDiscount = settings.resume_is_owner_discount === true;
    let discountMode = 'percent'; // 'percent' | 'amount' (Rs)
    const posTablesEnabled = boot.tablesEnabled !== undefined ? !!boot.tablesEnabled : !!settings.enable_tables;
    const posShowCustomerSection = settings.show_customer_section !== false;
    const canVoidKitchenItems = boot.canVoidKitchenItems === true;
    // Pre-kitchen print: har POS user qty kam / remove kar sakta hai.
    // Post-kitchen (locked): sirf manager/admin void.
    const canReduceCartItems = true;
    const requireItemChangeReason = false;
    const canReopenPaidBill = boot.canReopenPaidBill === true;
    const posCustomProductId = Number(settings.custom_product_id || 0);
    const posCustomProductSku = String(settings.custom_product_sku || 'POS-CUSTOM');
    let onDemandModalInstance = null;

    let cart = [];
    let kitchenVoids = [];
    let itemReductions = [];
    let kitchenVoidsSessionList = [];
    let kitchenVoidsLoading = false;
    let pendingChangeAction = null;
    let removeReasonModalInstance = null;
    let cancelWholeOrderPending = false;
    let resumeSaveLock = Promise.resolve();
    let kitchenPrintBusy = false;
    let holdSubmitLock = false;
    let checkoutInFlight = false;
    let lastHeldOrderId = null;
    try {
        // Only keep session hold id when this page load is resuming that same bill.
        const stored = Number(sessionStorage.getItem('rp_last_held_order_id') || 0);
        const resumeBoot = Number(boot.resumeOrderId || 0);
        if (resumeBoot > 0 && Number.isFinite(stored) && stored === resumeBoot) {
            lastHeldOrderId = stored;
        } else {
            sessionStorage.removeItem('rp_last_held_order_id');
        }
    } catch (_) { /* ignore */ }
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

    function updateTableSelectAppearance() {
        const sel = $('#rpTable');
        if (!sel) return;
        sel.classList.remove('rp-table-select--free', 'rp-table-select--occupied');
        if (!sel.value) return;
        const status = sel.selectedOptions[0]?.dataset?.status;
        if (status === 'free') sel.classList.add('rp-table-select--free');
        else if (status === 'occupied') sel.classList.add('rp-table-select--occupied');
    }

    function applyTableBoard(board) {
        if (Array.isArray(board)) {
            boot.tableBoard = board;
        }
        const sel = $('#rpTable');
        if (!sel || !Array.isArray(boot.tableBoard)) return;

        sel.querySelectorAll('option[value]').forEach((opt) => {
            const row = boot.tableBoard.find((t) => String(t.id) === opt.value);
            const status = row?.status === 'occupied' ? 'occupied' : 'free';
            opt.dataset.status = status;
            opt.classList.remove('rp-table--free', 'rp-table--occupied');
            opt.classList.add(status === 'occupied' ? 'rp-table--occupied' : 'rp-table--free');
        });
        updateTableSelectAppearance();
    }

    function setTableBoardStatus(tableId, status) {
        if (!tableId || !Array.isArray(boot.tableBoard)) return;
        const row = boot.tableBoard.find((t) => Number(t.id) === Number(tableId));
        if (row) {
            row.status = status === 'occupied' ? 'occupied' : 'free';
        }
        applyTableBoard(boot.tableBoard);
    }

    function tableBoardRow(tableId) {
        return (boot.tableBoard || []).find((t) => Number(t.id) === Number(tableId)) || null;
    }

    function validateTableSelection() {
        if (!posTablesEnabled || selectedServiceType() !== 'dine_in') {
            return true;
        }
        const tableId = Number($('#rpTable')?.value || 0);
        if (!tableId) {
            return true;
        }
        const row = tableBoardRow(tableId);
        if (!row || row.status !== 'occupied') {
            return true;
        }
        const occupiedOrderId = Number(row.order_id || 0);
        if (resumeOrderId && Number(resumeOrderId) === occupiedOrderId) {
            return true;
        }
        alert(`Table ${row.name} pehle se reserved hai (${row.order_no || 'order'}). Pending se resume karein.`);
        return false;
    }

    function handleReservedTableSelection(tableId) {
        const row = tableBoardRow(tableId);
        if (!row || row.status !== 'occupied') {
            updateTableSelectAppearance();
            return;
        }
        const occupiedOrderId = Number(row.order_id || 0);
        if (resumeOrderId && Number(resumeOrderId) === occupiedOrderId) {
            updateTableSelectAppearance();
            return;
        }
        alert(`Table ${row.name} pehle se reserved hai (${row.order_no || 'order'}). Wahi order open ho rahi hai.`);
        if (occupiedOrderId && routes.resume) {
            window.location.assign(routes.resume.replace('__ID__', String(occupiedOrderId)));
            return;
        }
        if ($('#rpTable')) {
            $('#rpTable').value = '';
        }
        updateTableSelectAppearance();
    }

    function selectedServiceType() {
        return $('#rpServiceType')?.value || 'dine_in';
    }

    function serviceChargeApplies() {
        return posServiceChargeEnabled && selectedServiceType() === 'dine_in';
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
        renderTotals();
        if (type === 'takeaway') {
            setTimeout(() => $('#rpTakeawayContact')?.focus(), 0);
        }
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
        if (on && !canPosDiscountCredit) {
            isCreditMode = false;
            updateCheckoutActions();
            return;
        }
        isCreditMode = !!on;
        const toggle = $('#rpCreditToggle');
        if (toggle) toggle.checked = isCreditMode;
        updateCheckoutActions();
    }

    function updateCheckoutActions() {
        const payBtn = $('#rpPayBtn');
        const paymentsBlock = $('#rpPaymentsBlock');

        if (paymentsBlock) {
            paymentsBlock.classList.toggle('d-none', isCreditMode || !canPosPay);
        }

        if (!payBtn) return;

        if (canPosPay && !isCreditMode) {
            payBtn.classList.remove('d-none', 'btn-danger');
            payBtn.classList.add('btn-rp-primary');
            payBtn.innerHTML = '<i class="bi bi-credit-card"></i> Pay Now';
            return;
        }

        if (canPosDiscountCredit && isCreditMode) {
            payBtn.classList.remove('d-none', 'btn-rp-primary');
            payBtn.classList.add('btn-danger');
            payBtn.innerHTML = '<i class="bi bi-journal-text"></i> Record Credit';
            return;
        }

        payBtn.classList.add('d-none');
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

    function orderIsSplit(order) {
        if (order?.is_split) return true;
        return / · Split /.test(String(order?.guest_name || ''));
    }

    function orderSplitLabel(order) {
        const fromApi = String(order?.split_label || '').trim();
        if (fromApi) return fromApi;
        const m = String(order?.guest_name || '').match(/ · Split (.+)$/);
        return m ? m[1].trim() : 'Split';
    }

    function orderSplitIconHtml(order) {
        if (!orderIsSplit(order)) return '';
        const tip = `Split bill — ${orderSplitLabel(order)}`;
        return `<span class="rp-oc-split-icon" title="${escHtml(tip)}" aria-label="${escHtml(tip)}"><i class="bi bi-scissors" aria-hidden="true"></i></span>`;
    }

    function orderTableBanner(order) {
        const guestRaw = String(order.guest_name || '').trim();
        const guestBase = guestRaw.includes(' · Split ') ? guestRaw.split(' · Split ')[0].trim() : guestRaw;
        const service = String(order.service_type || '').toLowerCase();
        const isDelivery = service === 'delivery' || order.service_type_label === 'Delivery';
        const isTakeaway = service === 'takeaway' || order.service_type_label === 'Takeaway';
        let label = '';
        if (order.table_name) {
            label = String(order.table_name).trim();
        } else if (isDelivery || isTakeaway) {
            // room_no holds phone/contact for delivery & takeaway — never prefix "Room".
            label = String(order.room_no || guestBase || '').trim();
        } else if ((service === 'dine_in' || order.service_type_label === 'Dine-in') && guestBase) {
            label = guestBase;
        } else if (order.room_no) {
            label = 'Room ' + String(order.room_no).trim();
        }
        if (!label) return '';
        // Pending/Paid cards: show bare table no (FT27), not "Table FT27".
        if (!isDelivery && !isTakeaway) {
            label = label.replace(/^(table|room)\s+/i, '').trim() || label;
        }
        return `<div class="rp-oc-table">${escHtml(label)}</div>`;
    }

    function orderMetaDetail(order) {
        const parts = [];
        const guestRaw = String(order.guest_name || '').trim();
        const guestBase = guestRaw.includes(' · Split ') ? guestRaw.split(' · Split ')[0].trim() : guestRaw;
        if (order.service_type === 'dine_in' || order.service_type_label === 'Dine-in') {
            // Table shown in rp-oc-table banner — keep other meta only.
            if (!order.table_name && !guestBase && order.room_no) parts.push(order.room_no);
        } else if (order.service_type === 'delivery' || order.service_type_label === 'Delivery') {
            if (guestBase) parts.push(guestBase);
            if (order.room_no) parts.push(order.room_no);
        } else if (guestBase) {
            parts.push(guestBase);
        }
        return parts.join(' · ');
    }

    function orderPunchedByHtml(order) {
        const name = String(order.punched_by || order.waiter_name || '').trim();
        if (!name) return '';
        return `<div class="rp-oc-by">by: ${escHtml(name)}</div>`;
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
        if (!p || !p.for_pos) return false;
        if (String(p.sku || '') === posCustomProductSku) return false;
        return true;
    }

    function customCartKey(name, price) {
        return `custom:${String(name || '').trim().toLowerCase()}:${Number(price).toFixed(2)}`;
    }

    function addOnDemandProduct(name, price, qty) {
        const itemName = String(name || '').trim();
        const unitPrice = Math.round(Number(price) * 100) / 100;
        const addQty = Math.round(Number(qty) * 1000) / 1000;
        if (!itemName || !Number.isFinite(unitPrice) || unitPrice < 0 || !Number.isFinite(addQty) || addQty <= 0) {
            return false;
        }
        if (!posCustomProductId) {
            alert('On Demand product setup missing — page refresh karein.');
            return false;
        }
        const key = customCartKey(itemName, unitPrice);
        const existing = cart.find((r) => r.is_custom && r.cart_key === key);
        if (existing) {
            existing.qty = Math.round((Number(existing.qty) + addQty) * 1000) / 1000;
        } else {
            cart.push({
                product_id: posCustomProductId,
                is_custom: true,
                item_name: itemName,
                cart_key: key,
                name: itemName,
                uom: 'unit',
                qty: addQty,
                unit_price: unitPrice,
                tax_percent: 0,
                notes: '',
                kitchen_served: false,
                kitchen_pending: false,
                kitchen_locked_qty: 0,
            });
        }
        renderAll();
        return true;
    }

    function getOnDemandModal() {
        const el = $('#rpOnDemandModal');
        if (!el || !window.bootstrap?.Modal) return null;
        if (!onDemandModalInstance) {
            onDemandModalInstance = new window.bootstrap.Modal(el, { backdrop: 'static', keyboard: true });
        }
        return onDemandModalInstance;
    }

    function openOnDemandModal() {
        const err = $('#rpOnDemandError');
        if (err) err.classList.add('d-none');
        const nameEl = $('#rpOnDemandName');
        const priceEl = $('#rpOnDemandPrice');
        const qtyEl = $('#rpOnDemandQty');
        if (nameEl) nameEl.value = '';
        if (priceEl) priceEl.value = '';
        if (qtyEl) qtyEl.value = '1';
        getOnDemandModal()?.show();
        setTimeout(() => nameEl?.focus(), 200);
    }

    function confirmOnDemandAdd() {
        const name = String($('#rpOnDemandName')?.value || '').trim();
        const price = parseFloat(String($('#rpOnDemandPrice')?.value || '').replace(',', '.'));
        const qty = parseFloat(String($('#rpOnDemandQty')?.value || '1').replace(',', '.'));
        const err = $('#rpOnDemandError');
        if (!name || !Number.isFinite(price) || price < 0 || !Number.isFinite(qty) || qty <= 0) {
            if (err) err.classList.remove('d-none');
            return;
        }
        if (!addOnDemandProduct(name, price, qty)) return;
        getOnDemandModal()?.hide();
        if (resumeOrderId) {
            saveResumedDraftChanges().catch((e) => alert(e.message || 'Order save nahi ho saki.'));
        }
    }

    function cartSubtotalOnly() {
        let subtotal = 0;
        cart.forEach((r) => {
            subtotal += Number(r.qty) * Number(r.unit_price);
        });
        return Math.round(subtotal * 100) / 100;
    }

    function setDiscountMode(mode, { resetValue = false } = {}) {
        discountMode = mode === 'amount' ? 'amount' : 'percent';
        const pctBtn = $('#rpDiscModePct');
        const rsBtn = $('#rpDiscModeRs');
        const unit = $('#rpDiscUnit');
        const discInput = $('#rpBillDiscount');
        pctBtn?.classList.toggle('is-active', discountMode === 'percent');
        rsBtn?.classList.toggle('is-active', discountMode === 'amount');
        if (unit) unit.textContent = discountMode === 'amount' ? 'Rs' : '%';
        discInput?.classList.toggle('rp-summary-amount', discountMode === 'amount');
        if (discInput) {
            discInput.title = discountMode === 'amount' ? 'Bill discount (Rs)' : 'Bill discount %';
            discInput.max = discountMode === 'percent' ? '100' : '';
            if (resetValue && !ownerDiscountActive) {
                discInput.value = '0';
            }
        }
    }

    function getBillDiscountInputValue() {
        if (ownerDiscountActive) return 100;
        if (posShowDiscount) return Math.max(0, Number($('#rpBillDiscount')?.value || 0));
        if (resumeOwnerDiscount) return 100;
        return resumeBillDiscount;
    }

    /** Always returns equivalent % for server (0–100). */
    function getBillDiscountPercent() {
        if (ownerDiscountActive || (!posShowDiscount && resumeOwnerDiscount)) {
            return 100;
        }
        const raw = getBillDiscountInputValue();
        if (discountMode !== 'amount') {
            return Math.min(100, raw);
        }
        const subtotal = cartSubtotalOnly();
        if (subtotal <= 0 || raw <= 0) return 0;
        const amount = Math.min(raw, subtotal);
        return Math.round((amount / subtotal) * 100000) / 1000; // 3 dp like backend
    }

    function updateOwnerDiscountButton() {
        $('#rpOwnerDiscountBtn')?.classList.toggle('is-active', ownerDiscountActive);
    }

    function clearOwnerDiscount(reRender = true) {
        ownerDiscountActive = false;
        const discInput = $('#rpBillDiscount');
        if (discInput) {
            discInput.readOnly = false;
        }
        updateOwnerDiscountButton();
        if (reRender) {
            renderTotals();
        }
    }

    function applyOwnerDiscount() {
        if (!canPosDiscountCredit) {
            alert('Owner discount sirf manager de sakta hai.');
            return;
        }
        if (!cart.length) {
            alert('Pehle item add karein.');
            return;
        }
        if (isCreditMode) {
            alert('Credit bill par Owner 100% Discount use nahi ho sakta.');
            return;
        }
        if (!posShowDiscount) {
            alert('Discount option disabled hai.');
            return;
        }

        ownerDiscountActive = true;
        setDiscountMode('percent');
        const discInput = $('#rpBillDiscount');
        if (discInput) {
            discInput.value = '100';
            discInput.readOnly = true;
        }
        renderTotals();
        updateOwnerDiscountButton();
        if (canPosPay) {
            openPayModal();
        }
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
        let discount = 0;
        let billDiscPct = 0;
        if (ownerDiscountActive || (!posShowDiscount && resumeOwnerDiscount)) {
            billDiscPct = 100;
            discount = subtotal;
        } else if (discountMode === 'amount' && posShowDiscount) {
            const rawRs = Math.max(0, Number($('#rpBillDiscount')?.value || 0));
            discount = Math.min(rawRs, subtotal);
            discount = Math.round(discount * 100) / 100;
            billDiscPct = subtotal > 0 ? Math.round((discount / subtotal) * 100000) / 1000 : 0;
        } else {
            billDiscPct = getBillDiscountPercent();
            discount = billDiscPct > 0 ? Math.round(subtotal * billDiscPct / 100 * 100) / 100 : 0;
        }
        const tax = 0;
        const net = Math.round((subtotal - discount) * 100) / 100;
        const serviceCharge = serviceChargeApplies() && posServiceChargePercent > 0
            ? Math.round(net * posServiceChargePercent / 100 * 100) / 100
            : 0;
        const grand = Math.round((net + tax + serviceCharge) * 100) / 100;
        return { subtotal, discount, tax, serviceCharge, grand, lineSubs, billDiscPct };
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
        // Sirf actual kitchen print / served lock. Hold-only / pending-print bilkul free.
        if (ri.kitchen_served || ri.kitchen_printed) {
            return qty;
        }
        return 0;
    }

    function sanitizeCartKitchenLocks() {
        cart.forEach((r) => {
            // Hold-only lines kabhi lock na hon — stale locked_qty clear.
            if (!(r.kitchen_served || r.kitchen_printed)) {
                r.kitchen_locked_qty = 0;
                r.kitchen_printed = false;
                return;
            }
            const qty = Number(r.qty) || 0;
            if ((Number(r.kitchen_locked_qty) || 0) < qty) {
                r.kitchen_locked_qty = qty;
            }
        });
    }

    function isCartLineKitchenLocked(row) {
        return (Number(row?.kitchen_locked_qty) || 0) > 0.0005;
    }

    function canEditUnlockedCartLine() {
        return true;
    }

    function canReduceOrRemoveCartLine(row) {
        if (!isCartLineKitchenLocked(row)) {
            return canEditUnlockedCartLine();
        }
        return canVoidKitchenItems;
    }

    function findMergeableCartRow(productId, uom) {
        return cart.find((r) => (
            !r.is_custom
            && Number(r.product_id) === Number(productId)
            && String(r.uom) === String(uom || '')
            && (Number(r.kitchen_locked_qty) || 0) <= 0.0005
        )) || null;
    }

    function pushNewCartProductRow(p, qty) {
        cart.push({
            product_id: p.id,
            name: p.name,
            uom: p.uom,
            qty: Math.round(Number(qty) * 1000) / 1000,
            unit_price: unitPriceForProduct(p, p.uom),
            tax_percent: 0,
            notes: '',
            kitchen_served: false,
            kitchen_pending: false,
            kitchen_printed: false,
            kitchen_locked_qty: 0,
        });
    }

    function focusProductSearch({ clear = false } = {}) {
        const search = $('#rpProductSearch');
        if (!search) return;
        if (clear) search.value = '';
        window.setTimeout(() => {
            search.focus();
            try { search.select(); } catch (_) { /* ignore */ }
        }, 0);
    }

    function focusBillsTableSearch() {
        window.setTimeout(() => {
            const tableSearch = $('#rpBillsTableSearch');
            if (tableSearch) {
                tableSearch.focus();
                try { tableSearch.select(); } catch (_) { /* ignore */ }
                return;
            }
            // Takeaway / Delivery filter: Table search hidden — top bill search.
            if (orderListMode === 'pending' || orderListMode === 'paid') {
                $('#rpProductSearch')?.focus();
            }
        }, 0);
    }

    function addOrIncrementProduct(id) {
        const p = products.find((x) => Number(x.id) === Number(id));
        if (!p || !isProductVisible(p)) return;
        const mergeable = findMergeableCartRow(id, p.uom);
        if (mergeable) {
            mergeable.qty = Math.round((Number(mergeable.qty) + 1) * 1000) / 1000;
            mergeable.unit_price = unitPriceForProduct(p, mergeable.uom);
        } else {
            // Kitchen-printed line ke baad same item → naya cart card (merge nahi).
            pushNewCartProductRow(p, 1);
        }
        const search = $('#rpProductSearch');
        const hadSearch = !orderListMode && !!search?.value.trim();
        if (hadSearch) {
            search.value = '';
        }
        renderAll();
        if (!orderListMode) {
            focusProductSearch();
        }
    }

    function increaseProductQtyBy(productId, addQty) {
        const p = products.find((x) => Number(x.id) === Number(productId));
        if (!p || addQty <= 0) return;
        const mergeable = findMergeableCartRow(productId, p.uom);
        if (mergeable) {
            mergeable.qty = Math.round((Number(mergeable.qty) + addQty) * 1000) / 1000;
            mergeable.unit_price = unitPriceForProduct(p, mergeable.uom);
        } else {
            pushNewCartProductRow(p, addQty);
        }
    }

    function setCartProductQty(productId, targetQty, reason) {
        const current = cartQtyForProduct(productId);
        const next = Math.round(Number(targetQty) * 1000) / 1000;
        if (!Number.isFinite(next)) {
            renderCart();
            return;
        }
        if (next <= 0) {
            if (!canEditUnlockedCartLine()) {
                alert('Quantity kam nahi ho sakti.');
                renderCart();
                return;
            }
            changeCartQty(productId, -current, reason);
            return;
        }
        const delta = Math.round((next - current) * 1000) / 1000;
        if (Math.abs(delta) < 0.0005) {
            return;
        }
        if (delta > 0) {
            increaseProductQtyBy(productId, delta);
            renderAll();
            if (resumeOrderId) {
                saveResumedDraftChanges().catch((e) => alert(e.message || 'Order save nahi ho saki.'));
            }
            return;
        }
        changeCartQty(productId, delta, reason);
    }

    function commitCartQtyInput(input) {
        if (input.dataset.index !== undefined && input.dataset.index !== '') {
            const index = Number(input.dataset.index);
            if (!Number.isFinite(index) || !cart[index]) return;
            const parsed = parseFloat(String(input.value).trim().replace(',', '.'));
            if (!Number.isFinite(parsed) || parsed <= 0) {
                renderCart();
                return;
            }
            const next = Math.round(parsed * 1000) / 1000;
            const current = Number(cart[index].qty) || 0;
            if (Math.abs(next - current) < 0.0005) {
                input.value = fmtQty(current);
                return;
            }
            if (next < current && !canEditUnlockedCartLine()) {
                alert('Quantity kam nahi ho sakti.');
                renderCart();
                return;
            }
            const locked = Number(cart[index].kitchen_locked_qty) || 0;
            if (next < locked) {
                if (!canVoidKitchenItems) {
                    alert('Kitchen print ke baad quantity sirf manager/admin kam kar sakta hai.');
                    renderCart();
                    return;
                }
                openItemChangeReasonModal({ type: 'setQty-custom', index, targetQty: next, voidKitchen: true });
                return;
            }
            setCartLineQty(index, next);
            return;
        }

        const productId = Number(input.dataset.id);
        if (!Number.isFinite(productId)) return;

        const parsed = parseFloat(String(input.value).trim().replace(',', '.'));
        const current = cartQtyForProduct(productId);

        if (!Number.isFinite(parsed) || parsed <= 0) {
            renderCart();
            return;
        }

        const next = Math.round(parsed * 1000) / 1000;
        if (Math.abs(next - current) < 0.0005) {
            input.value = fmtQty(current);
            return;
        }

        if (next < current) {
            if (!canEditUnlockedCartLine()) {
                alert('Quantity kam nahi ho sakti.');
                renderCart();
                return;
            }
            const locked = cartLockedQtyForProduct(productId);
            if (next < locked) {
                if (!canVoidKitchenItems) {
                    alert('Kitchen print ke baad quantity sirf manager/admin kam kar sakta hai.');
                    renderCart();
                    return;
                }
                openItemChangeReasonModal({ type: 'setQty', productId, targetQty: next, voidKitchen: true });
                return;
            }
        }

        setCartProductQty(productId, next);
    }

    function buildReductionEntry(row, qty, reason) {
        const orderItemId = Number(row.order_item_id) || 0;
        const isCustom = !!row.is_custom;
        return {
            product_id: row.product_id,
            uom: row.uom,
            qty: Math.round(Number(qty) * 1000) / 1000,
            reason: String(reason || '').trim(),
            notes: String(row.notes || '').trim(),
            name: row.name,
            // Non-custom: empty item_name (server fingerprints product+uom only).
            item_name: isCustom ? String(row.item_name || row.name || '').trim() : '',
            is_custom: isCustom,
            order_item_id: orderItemId > 0 ? orderItemId : null,
        };
    }

    function findCartRowForProduct(productId) {
        return cart.find((r) => Number(r.product_id) === Number(productId)) || null;
    }

    function kitchenVoidQtyForRow(row) {
        const qty = Math.max(0, Number(row?.qty) || 0);
        const locked = Math.max(0, Number(row?.kitchen_locked_qty) || 0);
        if (row?.kitchen_printed || row?.kitchen_served || locked > 0.0005) {
            return Math.round(Math.max(qty, locked) * 1000) / 1000;
        }
        return 0;
    }

    /** After server reload, strip qty that was just kitchen-voided (prevents cancelled item stuck in cart). */
    function applyKitchenVoidsToCart(voids) {
        if (!Array.isArray(voids) || !voids.length) return false;
        let changed = false;
        voids.forEach((v) => {
            let left = Math.round((Number(v.qty) || 0) * 1000) / 1000;
            if (left <= 0.0005) return;

            const oid = Number(v.order_item_id) || 0;
            if (oid > 0) {
                const idx = cart.findIndex((r) => Number(r.order_item_id) === oid);
                if (idx >= 0) {
                    const row = cart[idx];
                    const take = Math.min(Number(row.qty) || 0, left);
                    if (take > 0.0005) {
                        row.qty = Math.round(((Number(row.qty) || 0) - take) * 1000) / 1000;
                        const locked = Number(row.kitchen_locked_qty) || 0;
                        if (locked > 0) {
                            row.kitchen_locked_qty = Math.max(0, Math.round((locked - take) * 1000) / 1000);
                        }
                        left = Math.round((left - take) * 1000) / 1000;
                        changed = true;
                        if (row.qty <= 0.0005) cart.splice(idx, 1);
                    }
                }
            }

            if (left <= 0.0005) return;

            const wantCustom = !!v.is_custom;
            const wantUom = String(v.uom || '').trim().toLowerCase();
            const wantPid = Number(v.product_id) || 0;
            for (let i = cart.length - 1; i >= 0 && left > 0.0005; i--) {
                const row = cart[i];
                if (Number(row.product_id) !== wantPid) continue;
                if (!!row.is_custom !== wantCustom) continue;
                if (String(row.uom || '').trim().toLowerCase() !== wantUom) continue;
                const take = Math.min(Number(row.qty) || 0, left);
                if (take <= 0.0005) continue;
                row.qty = Math.round(((Number(row.qty) || 0) - take) * 1000) / 1000;
                const locked = Number(row.kitchen_locked_qty) || 0;
                if (locked > 0) {
                    row.kitchen_locked_qty = Math.max(0, Math.round((locked - take) * 1000) / 1000);
                }
                left = Math.round((left - take) * 1000) / 1000;
                changed = true;
                if (row.qty <= 0.0005) cart.splice(i, 1);
            }
        });
        return changed;
    }

    async function removeCartLine(index, reason) {
        syncItemNotesFromDom();
        const row = cart[index];
        if (!row) return;

        // Kitchen print se pehle — kabhi lock mat mano (sirf printed/served lock).
        const lockedQty = Number(row.kitchen_locked_qty) || 0;
        if (lockedQty > 0.0005 && !(row.kitchen_printed || row.kitchen_served)) {
            row.kitchen_locked_qty = 0;
        }

        if (!canReduceOrRemoveCartLine(row)) {
            if (isCartLineKitchenLocked(row)) {
                alert('Kitchen print ke baad item sirf manager/admin remove kar sakta hai.');
            } else {
                alert('Item remove nahi ho sakti.');
            }
            return;
        }

        const voidQty = kitchenVoidQtyForRow(row);
        // Reason sirf tab jab kitchen print ho chuka ho (locked qty).
        const needsReason = voidQty > 0.0005 && !String(reason || '').trim();
        if (needsReason) {
            openItemChangeReasonModal({ type: 'remove', index });
            return;
        }

        const voidsBefore = kitchenVoids.length;
        const reductionsBefore = itemReductions.length;
        const reasonText = String(reason || '').trim();

        if (voidQty > 0.0005 && reasonText) {
            kitchenVoids.push(buildReductionEntry(row, voidQty, reasonText));
        }
        // Pre-kitchen deletions: no reason log required.
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
            itemReductions.length = reductionsBefore;
            renderAll();
            alert(e.message || 'Item remove nahi ho saki.');
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

    function openItemChangeReasonModal(action) {
        pendingChangeAction = action;
        const title = $('#rpRemoveReasonModalLabel');
        const hint = $('#rpRemoveReasonHint');
        const confirmBtn = $('#rpRemoveConfirm');
        const cancelOrderChip = $('#rpReasonChipCancelOrder');

        let label = '';
        if (action.type === 'cancel-order') {
            const lockedLines = cart.filter((r) => (Number(r.kitchen_locked_qty) || 0) > 0);
            const itemCount = lockedLines.length;
            const qtyTotal = lockedLines.reduce((s, r) => s + (Number(r.kitchen_locked_qty) || 0), 0);
            label = `${itemCount} item(s) · ${fmtQty(qtyTotal)} qty kitchen cancel`;
            if (title) title.textContent = 'Cancel whole order';
            if (hint) hint.textContent = 'Poora order cancel karne ka reason select karein. Kitchen ko Removed Items slip jayegi:';
            if (confirmBtn) confirmBtn.innerHTML = '<i class="bi bi-x-circle"></i> Cancel Order';
            cancelOrderChip?.classList.remove('d-none');
        } else if (action.type === 'remove') {
            const row = cart[action.index];
            label = row ? `${fmtQty(row.qty)}× ${row.name}` : '';
            if (title) title.textContent = 'Item hataein';
            if (hint) {
                hint.textContent = Number(row?.kitchen_locked_qty) > 0 || row?.kitchen_printed || row?.kitchen_served
                    ? 'Kitchen item hataane ka reason select karein:'
                    : 'Item hataane ka reason select karein:';
            }
            if (confirmBtn) confirmBtn.innerHTML = '<i class="bi bi-trash"></i> Remove';
            cancelOrderChip?.classList.add('d-none');
        } else {
            const p = products.find((x) => Number(x.id) === Number(action.productId));
            label = p ? p.name : 'Item';
            if (title) title.textContent = 'Quantity kam karein';
            if (hint) {
                hint.textContent = action.voidKitchen
                    ? 'Kitchen quantity kam karne ka reason select karein:'
                    : 'Quantity kam karne ka reason select karein:';
            }
            if (confirmBtn) confirmBtn.innerHTML = '<i class="bi bi-check-lg"></i> Confirm';
            cancelOrderChip?.classList.add('d-none');
        }

        const nameEl = $('#rpRemoveItemName');
        if (nameEl) nameEl.textContent = label;

        const input = $('#rpRemoveReason');
        if (input) {
            input.value = '';
            input.readOnly = false;
        }
        $('#rpRemoveReasonError')?.classList.add('d-none');
        document.querySelectorAll('.rp-reason-chip').forEach((btn) => {
            btn.classList.remove('active', 'btn-primary', 'btn-secondary');
            btn.classList.add(btn.dataset.custom === '1' ? 'btn-outline-primary' : 'btn-outline-secondary');
        });
        getRemoveReasonModal()?.show();
        setTimeout(() => input?.focus(), 280);
    }

    function selectRemoveReasonTemplate(btn) {
        if (!btn) return;
        const input = $('#rpRemoveReason');
        const isCustom = btn.dataset.custom === '1';
        const reason = String(btn.dataset.reason || '');

        document.querySelectorAll('.rp-reason-chip').forEach((chip) => {
            const custom = chip.dataset.custom === '1';
            chip.classList.remove('active', 'btn-primary', 'btn-secondary');
            chip.classList.add(custom ? 'btn-outline-primary' : 'btn-outline-secondary');
        });
        btn.classList.remove('btn-outline-primary', 'btn-outline-secondary');
        btn.classList.add('active', isCustom ? 'btn-primary' : 'btn-secondary');

        if (!input) return;
        if (isCustom) {
            input.readOnly = false;
            input.value = '';
            input.placeholder = 'Apna reason yahan likhein…';
            input.focus();
            return;
        }
        input.readOnly = false;
        input.value = reason;
        input.placeholder = 'Template select karein ya yahan custom reason likhein';
        $('#rpRemoveReasonError')?.classList.add('d-none');
    }

    async function confirmRemoveWithReason() {
        const reason = ($('#rpRemoveReason')?.value || '').trim();
        if (reason.length < 3) {
            $('#rpRemoveReasonError')?.classList.remove('d-none');
            return;
        }

        const action = pendingChangeAction;
        pendingChangeAction = null;
        getRemoveReasonModal()?.hide();

        if (!action) return;

        try {
            if (action.type === 'cancel-order') {
                await cancelWholeOrder(reason);
            } else if (action.type === 'remove') {
                await removeCartLine(action.index, reason);
            } else if (action.type === 'dec') {
                changeCartQty(action.productId, -1, reason);
            } else if (action.type === 'dec-custom') {
                changeCustomCartQty(action.index, -1, reason);
            } else if (action.type === 'setQty') {
                setCartProductQty(action.productId, action.targetQty, reason);
            } else if (action.type === 'setQty-custom') {
                setCustomCartQty(action.index, action.targetQty, reason);
            }
        } catch (e) {
            alert(e.message || 'Change save nahi ho saki.');
        }
    }

    function cancelRemoveReasonModal() {
        pendingChangeAction = null;
        getRemoveReasonModal()?.hide();
        renderCart();
    }

    function changeCartQty(productId, delta, reason) {
        const sample = findCartRowForProduct(productId);
        if (sample?.is_custom) {
            const index = cart.indexOf(sample);
            if (index >= 0) changeCustomCartQty(index, delta, reason);
            return;
        }
        const p = products.find((x) => Number(x.id) === Number(productId));
        if (delta > 0) {
            addOrIncrementProduct(productId);
            return;
        }

        if (!canEditUnlockedCartLine() && cartLockedQtyForProduct(productId) <= 0) {
            alert('Quantity kam nahi ho sakti.');
            return;
        }

        const locked = cartLockedQtyForProduct(productId);
        const totalQty = cartQtyForProduct(productId);
        const next = Math.round((totalQty + delta) * 1000) / 1000;
        const reasonText = String(reason || '').trim();

        // Kitchen-printed qty se kam karne par hi reason mangna.
        if (next < locked) {
            if (!canVoidKitchenItems) {
                alert('Kitchen print ke baad quantity sirf manager/admin kam kar sakta hai.');
                return;
            }
            if (!reasonText) {
                openItemChangeReasonModal({ type: 'dec', productId, voidKitchen: true });
                return;
            }
            const voidQty = Math.round((locked - next) * 1000) / 1000;
            const sample = findCartRowForProduct(productId);
            if (voidQty > 0 && sample) {
                kitchenVoids.push(buildReductionEntry(sample, voidQty, reasonText));
            }
        }

        if (next <= 0) {
            cart = cart.filter((r) => Number(r.product_id) !== Number(productId));
            renderAll();
            if (resumeOrderId) {
                saveResumedDraftChanges().catch((e) => alert(e.message || 'Order save nahi ho saki.'));
            }
            return;
        }

        let remaining = Math.abs(delta);
        for (let i = cart.length - 1; i >= 0 && remaining > 0; i--) {
            const row = cart[i];
            if (Number(row.product_id) !== Number(productId)) continue;
            const rowLocked = Number(row.kitchen_locked_qty) || 0;
            const voidingKitchen = next < locked;
            const reducible = voidingKitchen
                ? Math.max(0, Number(row.qty))
                : Math.max(0, Number(row.qty) - rowLocked);
            const take = Math.min(reducible, remaining);
            if (take <= 0) continue;
            row.qty = Math.round((Number(row.qty) - take) * 1000) / 1000;
            if (voidingKitchen && rowLocked > 0) {
                const lockedTake = Math.min(rowLocked, take);
                row.kitchen_locked_qty = Math.max(0, Math.round((rowLocked - lockedTake) * 1000) / 1000);
            }
            remaining -= take;
            if (p) row.unit_price = unitPriceForProduct(p, row.uom);
        }
        cart = cart.filter((r) => Number(r.qty) > 0.0005);
        renderAll();
        if (resumeOrderId) {
            saveResumedDraftChanges().catch((e) => alert(e.message || 'Order save nahi ho saki.'));
        }
    }

    function changeCustomCartQty(index, delta, reason) {
        changeCartLineQty(index, delta, reason);
    }

    function changeCartLineQty(index, delta, reason) {
        const row = cart[index];
        if (!row) return;

        if (delta > 0) {
            const locked = Number(row.kitchen_locked_qty) || 0;
            const qty = Number(row.qty) || 0;
            // Fully kitchen-locked card pe + → alag New card (printed qty merge na ho).
            if (!row.is_custom && locked > 0.0005 && qty <= locked + 0.0005) {
                addOrIncrementProduct(row.product_id);
                if (resumeOrderId) {
                    saveResumedDraftChanges().catch((e) => alert(e.message || 'Order save nahi ho saki.'));
                }
                return;
            }
            row.qty = Math.round((qty + delta) * 1000) / 1000;
            if (!row.is_custom) {
                const p = products.find((x) => Number(x.id) === Number(row.product_id));
                if (p) row.unit_price = unitPriceForProduct(p, row.uom);
            }
            renderAll();
            if (resumeOrderId) {
                saveResumedDraftChanges().catch((e) => alert(e.message || 'Order save nahi ho saki.'));
            }
            return;
        }

        if (!canReduceOrRemoveCartLine(row) && delta < 0) {
            if (isCartLineKitchenLocked(row)) {
                alert('Kitchen print ke baad quantity sirf manager/admin kam kar sakta hai.');
            } else {
                alert('Quantity kam nahi ho sakti.');
            }
            return;
        }

        const locked = Number(row.kitchen_locked_qty) || 0;
        const totalQty = Number(row.qty) || 0;
        const next = Math.round((totalQty + delta) * 1000) / 1000;
        const reasonText = String(reason || '').trim();

        if (next < locked) {
            if (!canVoidKitchenItems) {
                alert('Kitchen print ke baad quantity sirf manager/admin kam kar sakta hai.');
                return;
            }
            if (!reasonText) {
                openItemChangeReasonModal({ type: 'dec-custom', index, voidKitchen: true });
                return;
            }
            const voidQty = Math.round((locked - next) * 1000) / 1000;
            if (voidQty > 0) {
                kitchenVoids.push(buildReductionEntry(row, voidQty, reasonText));
            }
        }

        if (next <= 0) {
            cart.splice(index, 1);
            renderAll();
            if (resumeOrderId) {
                saveResumedDraftChanges().catch((e) => alert(e.message || 'Order save nahi ho saki.'));
            }
            return;
        }

        const take = Math.abs(delta);
        row.qty = Math.round((Number(row.qty) - take) * 1000) / 1000;
        if (next < locked && locked > 0) {
            const lockedTake = Math.min(locked, take);
            row.kitchen_locked_qty = Math.max(0, Math.round((locked - lockedTake) * 1000) / 1000);
        }
        if (Number(row.qty) <= 0.0005) {
            cart.splice(index, 1);
        }
        renderAll();
        if (resumeOrderId) {
            saveResumedDraftChanges().catch((e) => alert(e.message || 'Order save nahi ho saki.'));
        }
    }

    function setCustomCartQty(index, targetQty, reason) {
        setCartLineQty(index, targetQty, reason);
    }

    function setCartLineQty(index, targetQty, reason) {
        const row = cart[index];
        if (!row) return;
        const current = Number(row.qty) || 0;
        const next = Math.round(Number(targetQty) * 1000) / 1000;
        if (!Number.isFinite(next)) {
            renderCart();
            return;
        }
        const delta = Math.round((next - current) * 1000) / 1000;
        if (Math.abs(delta) < 0.0005) return;
        if (delta > 0) {
            const locked = Number(row.kitchen_locked_qty) || 0;
            if (!row.is_custom && locked > 0.0005 && current <= locked + 0.0005) {
                // Locked card pe qty badhao → unlocked New card par add / create.
                increaseProductQtyBy(row.product_id, delta);
                renderAll();
                if (resumeOrderId) {
                    saveResumedDraftChanges().catch((e) => alert(e.message || 'Order save nahi ho saki.'));
                }
                return;
            }
        }
        changeCartLineQty(index, delta, reason);
    }

    function applySaleModePricing() {
        cart.forEach((r) => {
            if (r.is_custom) return;
            const p = products.find((x) => Number(x.id) === Number(r.product_id));
            if (p) r.unit_price = unitPriceForProduct(p, r.uom);
        });
    }

    function productMatchesMenuCategory(p) {
        if (!selectedMenuCategoryId) return true;
        return Number(p.category_id) === Number(selectedMenuCategoryId);
    }

    function renderMenuCategories() {
        if (orderListMode) {
            updateBillsMenuHead();
            return;
        }
        clearBillsMenuHead();
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
        if (orderListMode) {
            renderOrderCards();
            return;
        }
        const grid = $('#rpMenuGrid');
        grid?.classList.remove('rp-bills-grid', 'rp-kitchen-voids-grid');
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
            const canDec = qty > 0 && (
                canVoidKitchenItems || qty > locked
            );
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
        const orderHasKitchen = cart.some((r) => (
            (Number(r.kitchen_locked_qty) || 0) > 0.0005
            || r.kitchen_printed
            || r.kitchen_served
        ));
        wrap.innerHTML = cart.map((r, i) => {
            const total = lineRowTotal(r, totals, i);
            const locked = Number(r.kitchen_locked_qty) || 0;
            const showRemove = canReduceOrRemoveCartLine(r);
            const isNewAddon = orderHasKitchen && locked <= 0.0005;
            const kitchenBadge = locked > 0
                ? `<span class="rp-kitchen-pill ${r.kitchen_served ? 'rp-kitchen-pill--served' : 'rp-kitchen-pill--pending'}" title="Kitchen me bheja hua">
                    <i class="bi ${r.kitchen_served ? 'bi-check-circle-fill' : 'bi-fire'}"></i>
                    ${r.kitchen_served ? 'Served' : 'Kitchen'}
                   </span>`
                : (isNewAddon
                    ? `<span class="rp-kitchen-pill rp-kitchen-pill--new" title="Kitchen print ke baad naya item">
                        <i class="bi bi-stars"></i> New
                       </span>`
                    : '');
            const removeTitle = locked > 0 ? 'Kitchen item — reason required' : 'Remove item';
            const canDec = Number(r.qty) > 0 && (
                canVoidKitchenItems || Number(r.qty) > locked
            );
            const noteVal = escHtml(r.notes || '');
            return `<div class="rp-cart-line${locked > 0 ? ' is-kitchen-locked' : ''}${isNewAddon ? ' is-kitchen-new' : ''}${r.is_custom ? ' is-on-demand' : ''}" data-cart-index="${i}" data-product-id="${r.product_id}">
                <div class="rp-cl-row">
                    <div class="rp-cl-main">
                        <div class="rp-cl-qty-ctrl" role="group" aria-label="Quantity">
                            <button type="button" class="rp-cl-qty-btn" data-action="cart-dec" data-index="${i}"${canDec ? '' : ' disabled'} aria-label="Decrease">−</button>
                            <input type="text" inputmode="decimal" class="rp-cl-qty-input" data-index="${i}" value="${fmtQty(r.qty)}" aria-label="Quantity" autocomplete="off" spellcheck="false">
                            <button type="button" class="rp-cl-qty-btn" data-action="cart-inc" data-index="${i}" aria-label="Increase">+</button>
                        </div>
                        <span class="rp-cl-name">${escHtml(r.name)}${r.is_custom ? ' <span class="rp-on-demand-tag">On Demand</span>' : ''}</span>
                        ${kitchenBadge}
                    </div>
                    <div class="rp-cl-actions">
                        <div class="rp-cl-total">${fmtMoney(total)}</div>
                        ${showRemove ? `<button type="button" class="rp-cl-remove" data-action="remove-line" data-index="${i}" aria-label="Remove item" title="${removeTitle}">
                            <i class="bi bi-x-lg"></i>
                        </button>` : ''}
                    </div>
                </div>
                <input type="text" class="rp-cl-note" data-index="${i}" maxlength="255"
                       value="${noteVal}" placeholder="Item instruction…"
                       aria-label="Instruction for ${escHtml(r.name)}">
            </div>`;
        }).join('');
    }

    function renderTotals() {
        const { subtotal, discount, tax, serviceCharge, grand } = calcCartTotals();
        const el = (id, v) => { const n = $(id); if (n) n.textContent = typeof v === 'number' ? fmtMoney(v) : String(v); };
        const itemQty = cart.reduce((s, r) => s + Number(r.qty), 0);
        el('#rpSumItems', cart.length ? `${fmtQty(itemQty)} (${cart.length})` : '0');
        el('#rpSumSubtotal', subtotal);
        el('#rpSumDiscount', discount);
        el('#rpSumGrand', grand);
        const serviceRow = $('#rpServiceChargeRow');
        if (serviceRow) {
            serviceRow.style.display = serviceChargeApplies() && serviceCharge > 0 ? '' : 'none';
        }
        el('#rpSumServiceCharge', serviceCharge);
        const countEl = $('#rpCartCount');
        if (countEl) countEl.textContent = String(cart.length);
        if (autoPaymentAmount && payments.length === 1) {
            payments[0].amount = grand;
        }
    }

    let orderListMode = null;
    let billsServiceFilter = 'all'; // all | dine_in | takeaway | delivery
    let billsTableSearch = '';
    let panelView = 'split';
    let ownerDiscountActive = false;

    function setPanelView(view) {
        const app = document.querySelector('.restaurant-pos-app');
        if (!app) return;

        panelView = view;
        app.classList.remove('rp-view-menu', 'rp-view-cart');
        if (view === 'menu') app.classList.add('rp-view-menu');
        if (view === 'cart') app.classList.add('rp-view-cart');
        if (view === 'cart' && orderListMode) {
            showMenuPanel();
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

    function updateBillsMenuHead() {
        const head = $('#rpMenuHead');
        const cats = $('#rpMenuCats');
        if (!head || !orderListMode) return;

        const activeEl = document.activeElement;
        const keepTableSearchFocus = activeEl && activeEl.id === 'rpBillsTableSearch';
        const selStart = keepTableSearchFocus ? activeEl.selectionStart : null;
        const selEnd = keepTableSearchFocus ? activeEl.selectionEnd : null;
        if (keepTableSearchFocus) {
            billsTableSearch = activeEl.value || '';
        }

        cats?.classList.add('d-none');
        let billsHead = $('#rpBillsHead');
        if (!billsHead) {
            billsHead = document.createElement('div');
            billsHead.id = 'rpBillsHead';
            billsHead.className = 'rp-bills-head';
            head.appendChild(billsHead);
        }

        if (orderListMode === 'kitchen-voids') {
            const rows = filterKitchenVoidsForSearch(kitchenVoidsSessionList);
            billsHead.innerHTML = `
                <div class="rp-bills-head-main">
                    <span class="rp-bills-head-title">Kitchen Cancelled</span>
                    <span class="rp-bills-head-count">${rows.length} item${rows.length === 1 ? '' : 's'}</span>
                </div>
                <span class="rp-bills-head-hint">Kitchen print ke baad bill se hataaye gaye items aur un ka reason.</span>
            `;
            return;
        }

        const isPending = orderListMode === 'pending';
        const allOrders = isPending ? (boot.pendingBillsDetail || []) : (boot.paidBillsDetail || []);
        const filtered = filterOrdersForSearch(allOrders);
        const counts = countOrdersByService(allOrders);
        const showTableSearch = billsServiceFilter === 'all' || billsServiceFilter === 'dine_in';
        const tabs = [
            { key: 'all', label: 'All', count: counts.all },
            { key: 'dine_in', label: 'Dine In', count: counts.dine_in },
            { key: 'takeaway', label: 'Takeaway', count: counts.takeaway },
            { key: 'delivery', label: 'Delivery', count: counts.delivery },
        ];
        billsHead.innerHTML = `
            <div class="rp-bills-head-main">
                <span class="rp-bills-head-title">${isPending ? 'Pending Bills' : 'Paid Bills'}</span>
                <span class="rp-bills-head-count">${filtered.length} bill${filtered.length === 1 ? '' : 's'}</span>
                <button type="button" class="btn btn-sm rp-punch-new-order" id="rpPunchNewOrder"><i class="bi bi-plus-lg me-1"></i>Punch New Order</button>
            </div>
            <div class="rp-bills-service-tabs" role="tablist" aria-label="Filter by service type">
                ${tabs.map((t) => `
                    <button type="button" class="rp-bills-service-tab${billsServiceFilter === t.key ? ' is-active' : ''}"
                            data-service-filter="${t.key}" role="tab" aria-selected="${billsServiceFilter === t.key ? 'true' : 'false'}">
                        ${t.label}
                        <span class="rp-bills-service-tab-count">${t.count}</span>
                    </button>
                `).join('')}
            </div>
            ${showTableSearch ? `
            <div class="rp-bills-table-search">
                <div class="rp-bills-table-search-box">
                    <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i>
                    <input type="search" id="rpBillsTableSearch" class="rp-bills-table-search-input"
                           value="${escHtml(billsTableSearch)}"
                           placeholder="Table No. search…"
                           autocomplete="off" enterkeyhint="search">
                    <button type="button" class="btn btn-sm rp-bills-table-search-clear${billsTableSearch.trim() ? '' : ' d-none'}" id="rpBillsTableSearchClear" title="Clear">×</button>
                </div>
            </div>` : ''}
            <span class="rp-bills-head-hint">${isPending ? 'Bill kholne ke liye card par click karein.' : (canReopenPaidBill ? 'Receipt ya Reopen ke liye card par action use karein.' : 'Receipt ke liye card par click karein.')}</span>
        `;

        $('#rpPunchNewOrder')?.addEventListener('click', punchNewOrder);
        billsHead.querySelectorAll('[data-service-filter]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const next = btn.getAttribute('data-service-filter') || 'all';
                if (billsServiceFilter === next) return;
                billsServiceFilter = next;
                if (next !== 'all' && next !== 'dine_in') {
                    billsTableSearch = '';
                }
                updateBillsMenuHead();
                renderOrderCards();
                if (orderListMode === 'pending' || orderListMode === 'paid') {
                    focusBillsTableSearch();
                }
            });
        });

        const tableSearchInput = $('#rpBillsTableSearch');
        const clearBtn = $('#rpBillsTableSearchClear');

        const syncTableSearchUi = () => {
            const isPendingMode = orderListMode === 'pending';
            const source = isPendingMode ? (boot.pendingBillsDetail || []) : (boot.paidBillsDetail || []);
            const filteredNow = filterOrdersForSearch(source);
            const countEl = billsHead.querySelector('.rp-bills-head-count');
            if (countEl) {
                countEl.textContent = `${filteredNow.length} bill${filteredNow.length === 1 ? '' : 's'}`;
            }
            clearBtn?.classList.toggle('d-none', !String(billsTableSearch || '').trim());
            // Do not rebuild bills head — that steals focus after every letter.
            renderOrderCards({ skipHead: true });
        };

        tableSearchInput?.addEventListener('input', () => {
            billsTableSearch = tableSearchInput.value || '';
            syncTableSearchUi();
        });
        clearBtn?.addEventListener('click', () => {
            billsTableSearch = '';
            if (tableSearchInput) tableSearchInput.value = '';
            syncTableSearchUi();
            tableSearchInput?.focus();
        });
        tableSearchInput?.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                billsTableSearch = '';
                tableSearchInput.value = '';
                syncTableSearchUi();
            }
        });

        if (keepTableSearchFocus) {
            const again = $('#rpBillsTableSearch');
            if (again) {
                again.focus();
                try {
                    const start = selStart == null ? again.value.length : selStart;
                    const end = selEnd == null ? again.value.length : selEnd;
                    again.setSelectionRange(start, end);
                } catch (_) { /* ignore */ }
            }
        }
    }

    function punchNewOrder() {
        resetForNewBill();
        showMenuPanel();
        setPanelView('split');
    }

    function reopenPaidBill(orderId, orderNo) {
        const label = orderNo ? `Bill ${orderNo}` : 'Ye bill';
        const msg = `${label} reopen karein?\n\nPayment reverse hogi aur bill dubara edit ke liye khul jayegi.`;
        if (!confirm(msg)) {
            return;
        }

        const url = (routes.reopen || '').replace('__ID__', String(orderId));
        if (!url || !csrf) {
            alert('Reopen route missing.');
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        form.style.display = 'none';

        const token = document.createElement('input');
        token.type = 'hidden';
        token.name = '_token';
        token.value = csrf;
        form.appendChild(token);

        document.body.appendChild(form);
        form.submit();
    }

    let moveTableModalEl = null;
    let moveTableModalInstance = null;
    let moveTableOrderId = null;

    function openMoveTableModal(orderId, orderNo) {
        moveTableOrderId = orderId;

        if (!moveTableModalEl) {
            moveTableModalEl = document.createElement('div');
            moveTableModalEl.id = 'rpMoveTableModal';
            moveTableModalEl.className = 'modal fade';
            moveTableModalEl.setAttribute('tabindex', '-1');
            moveTableModalEl.innerHTML = `
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="rpMoveTableTitle">Select New Table</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div id="rpMoveTableAreaTabs" class="rp-mt-area-tabs px-3 pt-2"></div>
                        <div class="modal-body" id="rpMoveTableBody" style="max-height:55vh;overflow-y:auto;"></div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </div>
                </div>`;
            document.body.appendChild(moveTableModalEl);
            moveTableModalInstance = new window.bootstrap.Modal(moveTableModalEl, { backdrop: 'static' });

            // Area tab click
            moveTableModalEl.querySelector('#rpMoveTableAreaTabs').addEventListener('click', (e) => {
                const tab = e.target.closest('.rp-mt-area-tab');
                if (!tab) return;
                moveTableModalEl.querySelectorAll('.rp-mt-area-tab').forEach(b => b.classList.remove('is-active'));
                tab.classList.add('is-active');
                const key = tab.dataset.areaKey;
                moveTableModalEl.querySelectorAll('.rp-mt-area-section').forEach(sec => {
                    sec.classList.toggle('d-none', key !== 'all' && sec.dataset.areaKey !== key);
                });
            });

            moveTableModalEl.querySelector('#rpMoveTableBody').addEventListener('click', (e) => {
                const btn = e.target.closest('.rp-mt-table-btn');
                if (!btn || btn.disabled) return;
                const tableId = Number(btn.dataset.tableId);
                const tableName = btn.dataset.tableName || '';
                if (!tableId) return;
                confirmMoveTable(tableId, tableName);
            });
        }

        const board = boot.tableBoard || [];
        const currentOrder = (boot.pendingBillsDetail || []).find(o => Number(o.id) === Number(orderId));
        const currentTableId = currentOrder?.table_id || 0;

        moveTableModalEl.querySelector('#rpMoveTableTitle').textContent = `Select New Table — ${orderNo || 'Order'}`;

        // Build grouped areas
        const visibleTables = board.filter(t => Number(t.id) !== Number(currentTableId));
        const areaMap = {};
        visibleTables.forEach(t => {
            const aKey = t.sitting_area_id != null ? String(t.sitting_area_id) : 'none';
            const aName = t.sitting_area_name || 'Other';
            if (!areaMap[aKey]) areaMap[aKey] = { name: aName, tables: [] };
            areaMap[aKey].tables.push(t);
        });
        const areas = Object.entries(areaMap);
        const multiArea = areas.length > 1;

        // Build area tabs
        const tabsEl = moveTableModalEl.querySelector('#rpMoveTableAreaTabs');
        if (multiArea) {
            tabsEl.innerHTML = `<div class="rp-mt-area-tabs-inner">
                <button type="button" class="rp-mt-area-tab is-active" data-area-key="all">All</button>
                ${areas.map(([key, area]) => `<button type="button" class="rp-mt-area-tab" data-area-key="${escHtml(key)}">${escHtml(area.name)}</button>`).join('')}
            </div>`;
            tabsEl.style.display = '';
        } else {
            tabsEl.innerHTML = '';
            tabsEl.style.display = 'none';
        }

        const body = moveTableModalEl.querySelector('#rpMoveTableBody');
        if (!visibleTables.length) {
            body.innerHTML = '<div class="text-center text-secondary py-4">Koi table nahi mili.</div>';
        } else {
            body.innerHTML = areas.map(([key, area]) => `
                <div class="rp-mt-area-section" data-area-key="${escHtml(key)}">
                    ${multiArea ? `<div class="rp-mt-area-title">${escHtml(area.name)}</div>` : ''}
                    <div class="rp-mt-grid">
                        ${area.tables.map(t => {
                            const isFree = t.status === 'free';
                            const cls = isFree ? 'rp-mt-table-btn--free' : 'rp-mt-table-btn--occupied';
                            return `<button type="button" class="rp-mt-table-btn ${cls}"
                                    data-table-id="${t.id}" data-table-name="${escHtml(t.name)}"
                                    ${isFree ? '' : 'disabled'}>
                                <span class="rp-mt-shape">
                                    <span class="rp-mt-chair rp-mt-chair--n"></span>
                                    <span class="rp-mt-chair rp-mt-chair--e"></span>
                                    <span class="rp-mt-chair rp-mt-chair--s"></span>
                                    <span class="rp-mt-chair rp-mt-chair--w"></span>
                                    <span class="rp-mt-top"><span class="rp-mt-name">${escHtml(t.name)}</span></span>
                                </span>
                                <span class="rp-mt-label">${isFree ? 'Free' : (t.order_no || 'Occupied')}</span>
                            </button>`;
                        }).join('')}
                    </div>
                </div>`).join('');
        }

        moveTableModalInstance.show();
    }

    async function confirmMoveTable(tableId, tableName) {
        if (!moveTableOrderId) return;
        const url = (routes.moveTable || '').replace('__ID__', String(moveTableOrderId));
        if (!url) { alert('Move table route missing.'); return; }

        const btns = moveTableModalEl.querySelectorAll('.rp-mt-table-btn');
        btns.forEach(b => { b.disabled = true; });

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ table_id: tableId }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.ok) {
                alert(data.message || 'Table move fail ho gayi.');
                btns.forEach(b => { b.disabled = false; });
                return;
            }

            const pending = boot.pendingBillsDetail || [];
            const idx = pending.findIndex(o => Number(o.id) === Number(moveTableOrderId));
            if (idx >= 0) {
                pending[idx].table_id = data.to_table_id;
                pending[idx].table_name = data.to_table_name;
            }

            if (Array.isArray(data.table_board)) {
                boot.tableBoard = data.table_board;
                applyTableBoard(data.table_board);
            }

            moveTableModalInstance.hide();
            renderOrderCards();

            const print = data.print || {};
            const printedOk = Array.isArray(print.results)
                ? print.results.filter((r) => r && r.ok).map((r) => r.department).filter(Boolean)
                : [];
            const printedFail = Array.isArray(print.results)
                ? print.results.filter((r) => r && !r.ok).map((r) => r.department || 'Printer').filter(Boolean)
                : [];

            let msg = data.message || `Table move: ${tableName}`;
            if (printedOk.length) {
                msg += '\n\nMOVE TABLE slip print: ' + printedOk.join(', ');
            } else if (print.message) {
                msg += '\n\nPrint: ' + print.message;
            }
            if (printedFail.length) {
                msg += '\nFail: ' + printedFail.join(', ');
            }
            alert(msg);
        } catch (err) {
            alert('Table move request fail ho gayi.');
            btns.forEach(b => { b.disabled = false; });
        }
    }

    function clearBillsMenuHead() {
        $('#rpBillsHead')?.remove();
        $('#rpMenuCats')?.classList.remove('d-none');
    }

    function showMenuPanel() {
        orderListMode = null;
        $('#rpTabPending')?.classList.remove('is-active');
        $('#rpTabPaid')?.classList.remove('is-active');
        $('#rpTabKitchenVoids')?.classList.remove('is-active');
        clearBillsMenuHead();
        const search = $('#rpProductSearch');
        if (search) {
            search.placeholder = 'Search menu…';
            search.value = '';
        }
        renderAll();
        focusProductSearch();
    }

    async function loadSessionKitchenVoids() {
        if (!canViewKitchenVoids || !routes.kitchenVoids) {
            return [];
        }
        if (kitchenVoidsLoading) {
            return kitchenVoidsSessionList;
        }
        kitchenVoidsLoading = true;
        try {
            const res = await fetch(routes.kitchenVoids, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                throw new Error(data.message || 'Cancelled items load nahi ho saki.');
            }
            kitchenVoidsSessionList = Array.isArray(data.items) ? data.items : [];
            updateKitchenVoidCount();
            return kitchenVoidsSessionList;
        } catch (e) {
            console.warn('kitchen voids load failed', e);
            return kitchenVoidsSessionList;
        } finally {
            kitchenVoidsLoading = false;
        }
    }

    function updateKitchenVoidCount() {
        const el = $('#rpKitchenVoidCount');
        if (!el) return;
        const n = kitchenVoidsSessionList.length;
        el.textContent = String(n);
        el.style.display = n > 0 ? '' : 'none';
    }

    function filterKitchenVoidsForSearch(items) {
        const q = ($('#rpProductSearch')?.value || '').trim().toLowerCase();
        if (!q) return items;
        return items.filter((row) => {
            const hay = [
                row.order_no,
                row.product,
                row.reason,
                row.cancelled_by,
                row.cancelled_at,
                row.uom,
            ].join(' ').toLowerCase();
            return hay.includes(q);
        });
    }

    function orderServiceTypeKey(order) {
        const raw = String(order?.service_type || '').toLowerCase().trim();
        if (raw === 'dine_in' || raw === 'takeaway' || raw === 'delivery') {
            return raw;
        }
        const label = String(order?.service_type_label || '').toLowerCase();
        if (label.includes('takeaway') || label.includes('take away')) return 'takeaway';
        if (label.includes('delivery')) return 'delivery';
        if (label.includes('dine')) return 'dine_in';
        return 'dine_in';
    }

    function filterOrdersByService(orders) {
        if (!billsServiceFilter || billsServiceFilter === 'all') {
            return orders;
        }
        return orders.filter((o) => orderServiceTypeKey(o) === billsServiceFilter);
    }

    function countOrdersByService(orders) {
        const counts = { all: orders.length, dine_in: 0, takeaway: 0, delivery: 0 };
        orders.forEach((o) => {
            const key = orderServiceTypeKey(o);
            if (counts[key] !== undefined) counts[key] += 1;
        });
        return counts;
    }

    function filterOrdersByTable(orders) {
        if (billsServiceFilter !== 'all' && billsServiceFilter !== 'dine_in') {
            return orders;
        }
        const q = String(billsTableSearch || '').trim().toLowerCase();
        if (!q) return orders;

        const qCompact = q.replace(/\s+/g, '');
        return orders.filter((o) => {
            // Table search is for dine-in tables; in All tab skip non-dine bills.
            if (billsServiceFilter === 'all' && orderServiceTypeKey(o) !== 'dine_in') {
                return false;
            }
            const parts = [
                o.table_name,
                o.table_room,
                o.guest_name,
                o.room_no,
            ].filter(Boolean).map((v) => String(v).toLowerCase());
            const hay = parts.join(' ');
            const hayCompact = hay.replace(/\s+/g, '');
            return hay.includes(q) || hayCompact.includes(qCompact);
        });
    }

    function filterOrdersForSearch(orders) {
        let list = filterOrdersByService(orders);
        list = filterOrdersByTable(list);
        const q = ($('#rpProductSearch')?.value || '').trim().toLowerCase();
        if (!q) return list;
        return list.filter((o) => {
            const hay = [
                o.order_no,
                o.table_name,
                orderMetaLabel(o),
                orderMetaDetail(o),
                o.punched_by,
                o.waiter_name,
                o.payment_label,
                o.paid_at,
                o.paid_at_full,
            ].join(' ').toLowerCase();
            return hay.includes(q);
        });
    }

    function setOrderListMode(mode, { force = false } = {}) {
        const tabPending = $('#rpTabPending');
        const tabPaid = $('#rpTabPaid');
        const tabKitchenVoids = $('#rpTabKitchenVoids');

        if (!force && orderListMode === mode) {
            showMenuPanel();
            return;
        }

        // Bills list #rpMenuGrid mein dikhti hai — cart-only view mein panel hidden rehta hai.
        if (panelView !== 'split') {
            setPanelView('split');
        }

        orderListMode = mode;
        $('#rpOrderLinePanel')?.classList.add('d-none');

        tabPending?.classList.toggle('is-active', mode === 'pending');
        tabPaid?.classList.toggle('is-active', mode === 'paid');
        tabKitchenVoids?.classList.toggle('is-active', mode === 'kitchen-voids');
        $('#rpTabMenu')?.classList.remove('is-active');
        $('#rpTabCart')?.classList.remove('is-active');

        const search = $('#rpProductSearch');
        if (search) {
            if (mode === 'pending') search.placeholder = 'Search pending bill…';
            else if (mode === 'paid') search.placeholder = 'Search paid bill…';
            else if (mode === 'kitchen-voids') search.placeholder = 'Search cancelled item…';
            search.value = '';
        }

        // Keep service filter when switching Pending ↔ Paid; reset only for kitchen voids.
        if (mode === 'kitchen-voids') {
            billsServiceFilter = 'all';
            billsTableSearch = '';
        }

        if (mode === 'kitchen-voids') {
            loadSessionKitchenVoids().then(() => {
                updateBillsMenuHead();
                renderOrderCards();
                focusProductSearch();
            });
            return;
        }

        updateBillsMenuHead();
        renderOrderCards();
        if (mode === 'pending' || mode === 'paid') {
            focusBillsTableSearch();
        }
    }

    function showPaidTabAfterCheckout() {
        billsServiceFilter = 'all';
        billsTableSearch = '';
        if (panelView !== 'split') {
            setPanelView('split');
        }
        // force: pehle se Paid pe hon to bhi toggle-off (menu) mat karo
        setOrderListMode('paid', { force: true });
    }

    function renderKitchenVoidCards() {
        const grid = $('#rpMenuGrid');
        if (!grid) return;

        updateBillsMenuHead();
        grid.classList.remove('rp-bills-grid');
        grid.classList.add('rp-kitchen-voids-grid');

        const rows = filterKitchenVoidsForSearch(kitchenVoidsSessionList);
        if (!rows.length) {
            grid.innerHTML = `<div class="rp-empty rp-empty--menu">
                <span class="rp-empty-icon"><i class="bi bi-x-octagon"></i></span>
                <span>${kitchenVoidsSessionList.length ? 'Is search se koi cancelled item nahi mili.' : 'Is session mein kitchen print ke baad koi item cancel nahi hua.'}</span>
            </div>`;
            return;
        }

        grid.innerHTML = `<div class="rp-kitchen-voids-table">
            <div class="rp-kv-row rp-kv-head">
                <span>Bill</span>
                <span>Item</span>
                <span class="rp-kv-num">Qty</span>
                <span>Reason</span>
                <span>By</span>
                <span>Time</span>
            </div>
            ${rows.map((row) => `<div class="rp-kv-row">
                <span class="rp-kv-bill">${escHtml(row.order_no)}</span>
                <span class="rp-kv-product">${escHtml(row.product)}${row.uom ? ` <span class="rp-kv-uom">(${escHtml(row.uom)})</span>` : ''}</span>
                <span class="rp-kv-num">${escHtml(fmtQty(row.qty))}</span>
                <span class="rp-kv-reason">${escHtml(row.reason || '—')}</span>
                <span class="rp-kv-by">${escHtml(row.cancelled_by)}</span>
                <span class="rp-kv-time">${escHtml(row.cancelled_at)}</span>
            </div>`).join('')}
        </div>`;
    }

    function renderOrderCards({ skipHead = false } = {}) {
        const grid = $('#rpMenuGrid');
        if (!grid || !orderListMode) return;

        if (orderListMode === 'kitchen-voids') {
            renderKitchenVoidCards();
            return;
        }

        grid.classList.remove('rp-kitchen-voids-grid');
        if (!skipHead) {
            updateBillsMenuHead();
        }
        grid.classList.add('rp-bills-grid');

        if (orderListMode === 'pending') {
            const orders = filterOrdersForSearch(boot.pendingBillsDetail || []);
            if (!orders.length) {
                grid.innerHTML = `<div class="rp-empty rp-empty--menu">
                    <span class="rp-empty-icon"><i class="bi bi-hourglass-split"></i></span>
                    <span>${(boot.pendingBillsDetail || []).length
                        ? (billsTableSearch
                            ? 'Is table No. se koi pending bill nahi mili.'
                            : (billsServiceFilter !== 'all'
                                ? 'Is type ki koi pending bill nahi.'
                                : 'Is search se koi pending bill nahi mili.'))
                        : 'Koi pending order nahi.'}</span>
                </div>`;
                return;
            }
            grid.innerHTML = orders.map((o) => {
                const showMoveBtn = posTablesEnabled && o.table_id && o.service_type === 'dine_in';
                const showPrintBtn = settings.allow_bill_print !== false;
                const showPayBtn = canPosPay;
                const moveBtn = showMoveBtn
                    ? `<button type="button" class="btn btn-sm rp-oc-move-table" data-action="move-table" data-order-id="${escHtml(String(o.id))}" data-order-no="${escHtml(o.order_no)}"><i class="bi bi-arrow-left-right"></i> Move Table</button>`
                    : '';
                const printBtn = showPrintBtn
                    ? `<button type="button" class="btn btn-sm rp-oc-print-unpaid" data-action="print-unpaid" data-order-id="${escHtml(String(o.id))}" data-order-no="${escHtml(o.order_no)}"><i class="bi bi-printer"></i> Print Unpaid Bill</button>`
                    : '';
                const payBtn = showPayBtn
                    ? `<button type="button" class="btn btn-sm rp-oc-pay-now" data-action="pay-now" data-order-id="${escHtml(String(o.id))}" data-order-no="${escHtml(o.order_no)}"><i class="bi bi-credit-card"></i> Pay Now</button>`
                    : '';
                const actions = (payBtn || printBtn || moveBtn)
                    ? `<div class="rp-oc-move-wrap">${payBtn}${printBtn}${moveBtn}</div>`
                    : '';
                return `<div class="rp-order-card rp-order-card--grid rp-order-card--pending-wrap">
                    <button type="button" class="rp-order-card-link" data-action="open-pending" data-order-id="${escHtml(String(o.id))}">
                        ${orderTableBanner(o)}
                        <div class="rp-oc-no">${escHtml(o.order_no)}${orderSplitIconHtml(o)}</div>
                        <div class="rp-oc-meta">${escHtml(orderMetaLabel(o))}${orderMetaDetail(o) ? ' · ' + escHtml(orderMetaDetail(o)) : ''}</div>
                        ${orderPunchedByHtml(o)}
                        <div class="rp-oc-meta">${escHtml(fmtMoney(o.grand_total))} · ${o.items_count || 0} items</div>
                        <div class="rp-oc-open">Open bill <i class="bi bi-arrow-right-short"></i></div>
                    </button>
                    ${actions}
                </div>`;
            }).join('');
            return;
        }

        const paid = filterOrdersForSearch(boot.paidBillsDetail || []);
        if (!paid.length) {
            grid.innerHTML = `<div class="rp-empty rp-empty--menu">
                <span class="rp-empty-icon"><i class="bi bi-check-circle"></i></span>
                <span>${(boot.paidBillsDetail || []).length
                    ? (billsTableSearch
                        ? 'Is table No. se koi paid bill nahi mili.'
                        : (billsServiceFilter !== 'all'
                            ? 'Is type ki koi paid bill nahi.'
                            : 'Is search se koi paid bill nahi mili.'))
                    : 'Aaj koi paid order nahi.'}</span>
            </div>`;
            return;
        }
        grid.innerHTML = paid.map((o) => {
            const receiptUrl = (routes.receipt || '').replace('__ID__', String(o.id));
            const paidAt = o.paid_at_full || o.paid_at || '';
            const reopenBtn = canReopenPaidBill && routes.reopen
                ? `<button type="button" class="btn btn-sm rp-oc-reopen" data-action="reopen-paid" data-order-id="${escHtml(String(o.id))}" data-order-no="${escHtml(o.order_no)}">
                    <i class="bi bi-arrow-counterclockwise"></i> Reopen
                </button>`
                : '';
            return `<div class="rp-order-card rp-order-card-paid rp-order-card--grid">
                ${orderTableBanner(o)}
                <div class="rp-oc-no">${escHtml(o.order_no)}</div>
                <div class="rp-oc-meta">${escHtml(orderMetaLabel(o))}${orderMetaDetail(o) ? ' · ' + escHtml(orderMetaDetail(o)) : ''}</div>
                ${orderPunchedByHtml(o)}
                <div class="rp-oc-meta">${escHtml(fmtMoney(o.grand_total))} · ${escHtml(o.payment_label || 'Paid')}</div>
                ${paidAt ? `<div class="rp-oc-pay">${escHtml(paidAt)}</div>` : ''}
                <div class="rp-oc-actions">
                    <a class="rp-oc-receipt" href="${escHtml(receiptUrl)}" target="_blank" rel="noopener">
                        View receipt <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                    ${reopenBtn}
                </div>
            </div>`;
        }).join('');
    }

    function renderAll() {
        renderMenuCategories();
        renderMenuGrid();
        renderCart();
        renderTotals();
        syncWhatsappButton();
        updateCancelOrderButton();
        if (autoPaymentAmount && payments.length === 1) {
            payments[0].amount = calcCartTotals().grand;
        }
    }

    function hasKitchenLockedItems() {
        return cart.some((r) => (Number(r.kitchen_locked_qty) || 0) > 0);
    }

    /** Pending/resume bill pe pehle se kitchen print hui hai ya nahi (cart empty hone ke baad bhi). */
    function resumedOrderHasKitchenPrint() {
        if (hasKitchenLockedItems()) {
            return true;
        }
        const oid = Number(resumeOrderId || 0);
        if (!oid) return false;
        const order = (boot.pendingBillsDetail || []).find((o) => Number(o.id) === oid);
        if (!order || !Array.isArray(order.items)) {
            return false;
        }
        return order.items.some((i) => !!(i.kitchen_printed || i.kitchen_served));
    }

    function updateCancelOrderButton() {
        const btn = $('#rpCancelOrderBtn');
        if (!btn) return;
        const show = canVoidKitchenItems && !!resumeOrderId && hasKitchenLockedItems();
        btn.classList.toggle('d-none', !show);
        btn.disabled = !show;
    }

    function requestCancelWholeOrder() {
        if (!canVoidKitchenItems) {
            alert('Poora order sirf manager/admin cancel kar sakta hai.');
            return;
        }
        if (!resumeOrderId) {
            alert('Pehle kitchen print / hold order zaroori hai.');
            return;
        }
        if (!hasKitchenLockedItems()) {
            alert('Kitchen print ke baad hi poora order cancel ho sakta hai.');
            return;
        }
        openItemChangeReasonModal({ type: 'cancel-order', voidKitchen: true });
    }

    async function cancelWholeOrder(reason) {
        if (!canVoidKitchenItems) {
            throw new Error('Poora order sirf manager/admin cancel kar sakta hai.');
        }
        if (!resumeOrderId) {
            throw new Error('Pending order nahi mili.');
        }

        const reasonText = String(reason || '').trim();
        if (reasonText.length < 3) {
            openItemChangeReasonModal({ type: 'cancel-order', voidKitchen: true });
            return;
        }

        const voids = [];
        cart.forEach((row) => {
            const locked = Number(row.kitchen_locked_qty) || 0;
            if (locked > 0) {
                voids.push(buildReductionEntry(row, locked, reasonText));
            }
        });
        if (!voids.length) {
            throw new Error('Kitchen print ke baad hi poora order cancel ho sakta hai.');
        }

        const cartSnapshot = cart.map((r) => ({ ...r }));
        const voidsBefore = kitchenVoids.slice();
        const reductionsBefore = itemReductions.slice();
        const btn = $('#rpCancelOrderBtn');
        if (btn) btn.disabled = true;
        cancelWholeOrderPending = true;
        kitchenVoids = voids;
        itemReductions = [];
        cart.length = 0;
        renderAll();

        try {
            await discardResumedDraft();
        } catch (e) {
            cancelWholeOrderPending = false;
            kitchenVoids = voidsBefore;
            itemReductions = reductionsBefore;
            cart.length = 0;
            cartSnapshot.forEach((r) => cart.push(r));
            renderAll();
            if (btn) btn.disabled = false;
            throw e;
        }
    }

    let autoPaymentAmount = true;

    function cartItemsForSubmit() {
        syncItemNotesFromDom();
        const totals = calcCartTotals();
        return cart.map((r, idx) => ({
            product_id: r.product_id,
            is_custom: !!r.is_custom,
            item_name: r.is_custom ? String(r.item_name || r.name || '').trim() : null,
            uom: r.uom,
            qty: r.qty,
            unit_price: r.unit_price,
            discount_percent: 0,
            tax_percent: 0,
            notes: String(r.notes || '').trim(),
            line_total: lineRowTotal(r, totals, idx),
            // Server uses this so New cards are never swallowed into printed qty pool.
            kitchen_locked_qty: Math.round((Number(r.kitchen_locked_qty) || 0) * 1000) / 1000,
            order_item_id: Number(r.order_item_id) > 0 ? Number(r.order_item_id) : null,
        }));
    }

    function syncItemNotesFromDom() {
        $$('#rpCartLines .rp-cl-note').forEach((input) => {
            const idx = Number(input.dataset.index);
            if (!Number.isFinite(idx) || !cart[idx]) return;
            cart[idx].notes = String(input.value || '');
        });
    }

    function prepareSubmit(mode, opts = {}) {
        if (!cart.length) {
            alert('Pehle item add karein.');
            return false;
        }

        // Kitchen print: contact optional. Unpaid / Hold / Pay: takeaway+delivery meta zaroori.
        const requireGuestMeta = opts.requireGuestMeta !== false;

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
            if (requireGuestMeta) {
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
        } else if (serviceType === 'takeaway') {
            if (requireGuestMeta && !($('#rpTakeawayContact')?.value || '').trim()) {
                alert('Contact No. enter karein.');
                $('#rpTakeawayContact')?.focus();
                return false;
            }
        }

        if (isCreditMode && mode === 'checkout' && !selectedContactId) {
            alert('Credit sale ke liye contact select karein.');
            return false;
        }

        if (!validateTableSelection()) {
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

        if (mode === 'checkout') {
            if (isCreditMode && !canPosDiscountCredit) {
                alert('Credit sirf manager de sakta hai.');
                return false;
            }
            if (!isCreditMode && !canPosPay) {
                alert('Pay sirf cashier ya manager kar sakta hai.');
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
        } else if (serviceType === 'takeaway') {
            const contact = ($('#rpTakeawayContact')?.value || '').trim();
            form.querySelector('[name="table_id"]').value = '';
            form.querySelector('[name="guest_name"]').value = contact;
            form.querySelector('[name="room_no"]').value = contact;
            form.querySelector('[name="order_notes"]').value = '';
        } else {
            form.querySelector('[name="table_id"]').value = '';
            form.querySelector('[name="guest_name"]').value = '';
            form.querySelector('[name="room_no"]').value = '';
            form.querySelector('[name="order_notes"]').value = '';
        }

        const kitchenNotesInput = form.querySelector('[name="kitchen_notes"]');
        if (kitchenNotesInput) {
            kitchenNotesInput.value = ($('#rpBillKitchenNotes')?.value || '').trim();
        }

        form.querySelector('[name="items"]').value = JSON.stringify(cartItemsForSubmit());
        form.querySelector('[name="payments"]').value = JSON.stringify(
            mode === 'hold'
                ? [{ method: 'cash', amount: 0 }]
                : (isCreditMode ? [] : payments)
        );
        form.querySelector('[name="bill_tax_percent"]').value = '0';
        form.querySelector('[name="bill_discount_percent"]').value = String(calcCartTotals().billDiscPct);
        form.querySelector('[name="is_owner_discount"]').value = (ownerDiscountActive || resumeOwnerDiscount) ? '1' : '0';
        form.querySelector('[name="resume_order_id"]').value = resumeOrderId ? String(resumeOrderId) : '';
        const kitchenVoidsInput = form.querySelector('[name="kitchen_voids"]');
        if (kitchenVoidsInput) {
            kitchenVoidsInput.value = JSON.stringify(kitchenVoids);
        }
        const itemReductionsInput = form.querySelector('[name="item_reductions"]');
        if (itemReductionsInput) {
            itemReductionsInput.value = JSON.stringify(itemReductions);
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

    async function postCheckout(extraFields = {}, { skipPrint = false } = {}) {
        if (checkoutInFlight) return false;
        if (!prepareSubmit('checkout')) return false;

        // Stable for this Pay attempt — retries reuse same id so server can block twin bills.
        if (!extraFields.client_request_id) {
            extraFields = { ...extraFields, client_request_id: newClientRequestId() };
        }

        const formData = checkoutFormData(extraFields);
        if (!formData) return false;

        checkoutInFlight = true;
        try {
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
                const errMsg = data.message || validationMsg || 'Payment failed.';
                if (data.already_paid || isStaleOrderResponse(res, data, errMsg)) {
                    applyCheckoutSuccess({
                        order_id: data.order_id || resumeOrderId,
                        order_no: data.order_no,
                        order: data.order,
                        table_board: data.table_board,
                    });
                    alert(data.message || 'Order pehle se paid hai.');
                    return true;
                }
                throw new Error(errMsg);
            }

            // UI pehle update — thermal print wait mat karo (server queue / background).
            applyCheckoutSuccess(data);
            if (!skipPrint) {
                queuePaidBillPrint(data);
            }

            return true;
        } finally {
            checkoutInFlight = false;
        }
    }

    function getPayModal() {
        const el = $('#rpPayModal');
        if (!el || !window.bootstrap?.Modal) return null;
        if (!payModalInstance) {
            payModalInstance = new window.bootstrap.Modal(el, { backdrop: 'static', keyboard: true });
        }
        return payModalInstance;
    }

    function fmtCashChip(n) {
        const v = Math.round(Number(n) * 100) / 100;
        if (!Number.isFinite(v)) return 'Rs 0';
        const hasDec = Math.abs(v - Math.round(v)) > 0.001;
        const body = hasDec
            ? v.toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            : Math.round(v).toLocaleString('en-PK');
        return `Rs ${body}`;
    }

    function buildCashSuggestions(total) {
        const exact = Math.round(Number(total) * 100) / 100;
        if (!Number.isFinite(exact) || exact < 0) return [0];

        const seen = new Set();
        const out = [];
        const add = (raw) => {
            const n = Math.round(Number(raw) * 100) / 100;
            if (!Number.isFinite(n) || n + 0.001 < exact) return;
            const key = n.toFixed(2);
            if (seen.has(key)) return;
            seen.add(key);
            out.push(n);
        };

        add(exact);

        // Common note round-ups (10 / 50 / 100 / 500)
        [10, 50, 100, 500].forEach((step) => {
            add(Math.ceil(exact / step) * step);
        });

        // Next thousand notes
        let base = Math.ceil(exact / 1000) * 1000;
        if (base <= exact + 0.001) base += 1000;
        for (let i = 0; i < 8 && out.length < 8; i += 1) {
            add(base + (i * 1000));
        }

        return out.slice(0, 8);
    }

    function setCashTendered(amount, { manual = false } = {}) {
        const input = $('#rpCashTendered');
        if (input) {
            if (amount === '' || amount === null || amount === undefined) {
                input.value = '';
            } else {
                const v = Math.round(Number(amount) * 100) / 100;
                input.value = Number.isFinite(v) ? String(v) : '';
            }
        }
        const wrap = $('#rpCashManualWrap');
        if (manual) {
            wrap?.classList.remove('d-none');
        } else {
            wrap?.classList.add('d-none');
        }
        const activeAmount = amount === '' || amount === null || amount === undefined
            ? NaN
            : Number(amount);
        syncCashSuggestionActive(activeAmount, manual);
        updatePayModalAmounts();
    }

    function syncCashSuggestionActive(amount, manual = false) {
        const wrap = $('#rpCashSuggestions');
        if (!wrap) return;
        const target = Math.round(Number(amount) * 100) / 100;
        wrap.querySelectorAll('.rp-cash-chip').forEach((btn) => {
            if (btn.dataset.action === 'manual') {
                btn.classList.toggle('is-active', !!manual);
                return;
            }
            const val = Number(btn.dataset.amount);
            btn.classList.toggle('is-active', !manual && Number.isFinite(val) && Math.abs(val - target) < 0.001);
        });
    }

    function renderCashSuggestions(total) {
        const wrap = $('#rpCashSuggestions');
        if (!wrap) return;
        const suggestions = buildCashSuggestions(total);
        wrap.innerHTML = suggestions.map((amt) => (
            `<button type="button" class="rp-cash-chip" data-amount="${amt}">${escHtml(fmtCashChip(amt))}</button>`
        )).join('') + `
            <button type="button" class="rp-cash-chip rp-cash-chip--amount" data-action="manual">
                <i class="bi bi-grid-3x3-gap-fill"></i> Amount
            </button>`;
    }

    function updatePayModalAmounts() {
        const grand = calcCartTotals().grand;
        const tendered = Number($('#rpCashTendered')?.value || 0);
        const change = Math.max(0, Math.round((tendered - grand) * 100) / 100);
        const ok = tendered >= grand - 0.001;

        if ($('#rpPayModalTotal')) $('#rpPayModalTotal').textContent = fmtMoney(grand);
        if ($('#rpCashChange')) $('#rpCashChange').textContent = fmtMoney(change);
        const disablePay = !ok;
        if ($('#rpPayModalConfirm')) $('#rpPayModalConfirm').disabled = disablePay;
        if ($('#rpPayModalMarkPaid')) $('#rpPayModalMarkPaid').disabled = disablePay;
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
        renderCashSuggestions(grand);
        setCashTendered(grand <= 0 ? 0 : grand, { manual: false });

        const modal = getPayModal();
        if (!modal) {
            submitOrder('checkout');
            return;
        }
        modal.show();
    }

    async function confirmPayModal({ printBill = true } = {}) {
        const grand = calcCartTotals().grand;
        const tendered = Number($('#rpCashTendered')?.value || 0);
        if (tendered < grand - 0.001) {
            updatePayModalAmounts();
            return;
        }

        const change = Math.max(0, Math.round((tendered - grand) * 100) / 100);
        const confirmBtn = printBill ? $('#rpPayModalConfirm') : $('#rpPayModalMarkPaid');
        const otherBtn = printBill ? $('#rpPayModalMarkPaid') : $('#rpPayModalConfirm');
        if (confirmBtn) confirmBtn.disabled = true;
        if (otherBtn) otherBtn.disabled = true;

        try {
            await postCheckout({
                cash_tendered: tendered,
                cash_change: change,
            }, { skipPrint: !printBill });
            getPayModal()?.hide();
            // Modal close ke baad dubara ensure — layout/focus Paid tab pe rahe
            requestAnimationFrame(() => showPaidTabAfterCheckout());
        } catch (e) {
            alert(e.message || 'Payment failed.');
            updatePayModalAmounts();
        } finally {
            if (confirmBtn) confirmBtn.disabled = false;
            if (otherBtn) otherBtn.disabled = false;
            updatePayModalAmounts();
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

    function upsertPaidBill(order) {
        if (!order?.id) return;
        const list = Array.isArray(boot.paidBillsDetail) ? [...boot.paidBillsDetail] : [];
        const idx = list.findIndex((o) => Number(o.id) === Number(order.id));
        if (idx >= 0) {
            list[idx] = order;
        } else {
            list.unshift(order);
        }
        boot.paidBillsDetail = list;
    }

    function removePendingBill(orderId) {
        if (!orderId) return;
        boot.pendingBillsDetail = (boot.pendingBillsDetail || []).filter(
            (o) => Number(o.id) !== Number(orderId)
        );
    }

    function applyCheckoutSuccess(data) {
        const orderId = data.order_id || resumeOrderId;
        removePendingBill(orderId);

        if (data.order) {
            upsertPaidBill(data.order);
        } else if (orderId) {
            upsertPaidBill({
                id: orderId,
                order_no: data.order_no || `#${orderId}`,
                grand_total: calcCartTotals().grand,
                paid_at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
            });
        }

        if (Array.isArray(data.table_board)) {
            applyTableBoard(data.table_board);
        }

        resetForNewBill();
        updateOrderTabCounts();
        showPaidTabAfterCheckout();
    }

    function resetForNewBill() {
        cart.length = 0;
        kitchenVoids = [];
        itemReductions = [];
        pendingRemoveIndex = null;
        resumeOrderId = null;
        lastHeldOrderId = null;
        try { sessionStorage.removeItem('rp_last_held_order_id'); } catch (_) { /* ignore */ }
        // Har nayi bill Cash se start — Bank/Card previous bill se sticky na rahe.
        if ($('#rpPayMethod')) $('#rpPayMethod').value = 'cash';
        payments = [{ method: 'cash', amount: 0 }];
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
        if ($('#rpTakeawayContact')) $('#rpTakeawayContact').value = '';
        if ($('#rpBillKitchenNotes')) $('#rpBillKitchenNotes').value = '';
        selectedContactId = null;
        $('#rpSelectedContactWrap')?.classList.add('d-none');
        if ($('#rpContactSearch')) $('#rpContactSearch').value = '';
        setCreditMode(false);
        clearOwnerDiscount(false);

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

    function buildHoldFormData(sendToKitchen = false, clientRequestId = null, opts = {}) {
        if (!cart.length) {
            return null;
        }
        if (!prepareSubmit('hold', opts)) {
            return null;
        }
        const form = $('#rpSubmitForm');
        if (!form) {
            return null;
        }

        // Only attach resume id when this cart is already that pending bill.
        const rid = Number(resumeOrderId || 0);
        if (Number.isFinite(rid) && rid > 0) {
            resumeOrderId = rid;
            form.querySelector('[name="resume_order_id"]').value = String(rid);
        }

        const totals = calcCartTotals();
        const formData = new FormData(form);
        formData.set('items', JSON.stringify(cartItemsForSubmit()));
        formData.set('kitchen_voids', JSON.stringify(kitchenVoids));
        formData.set('item_reductions', JSON.stringify(itemReductions));
        formData.set('send_to_kitchen', sendToKitchen ? '1' : '0');
        formData.set('client_grand_total', String(totals.grand));
        formData.set('client_subtotal', String(totals.subtotal));
        formData.set('client_discount_total', String(totals.discount));
        formData.set('client_tax_total', String(totals.tax));
        formData.set('client_service_charge_total', String(totals.serviceCharge || 0));
        if (clientRequestId) {
            formData.set('client_request_id', String(clientRequestId));
        }
        if (Number.isFinite(rid) && rid > 0) {
            formData.set('resume_order_id', String(rid));
        }
        return formData;
    }

    function newClientRequestId() {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
        return 'pos-' + Date.now() + '-' + Math.random().toString(16).slice(2);
    }

    function rememberHeldOrder(order) {
        if (!order?.id) return;
        lastHeldOrderId = Number(order.id);
        try { sessionStorage.setItem('rp_last_held_order_id', String(order.id)); } catch (_) { /* ignore */ }
        setResumeStateFromOrder(order);
    }

    function parseOrderQty(qty) {
        const v = parseFloat(String(qty ?? '').replace(/,/g, ''));
        return Number.isFinite(v) ? v : 1;
    }

    function reloadCartFromOrder(order) {
        if (!order || !Array.isArray(order.items)) {
            return;
        }
        cart.length = 0;
        order.items.forEach((ri) => {
            const p = products.find((x) => Number(x.id) === Number(ri.product_id));
            const isCustom = !!ri.is_custom;
            const itemName = String(ri.item_name || ri.name || p?.name || 'Item').trim();
            const unitPrice = Number(ri.unit_price) || (isCustom ? 0 : (p ? unitPriceForProduct(p, ri.uom || p.uom) : 0));
            cart.push({
                product_id: Number(ri.product_id),
                is_custom: isCustom,
                item_name: isCustom ? itemName : null,
                cart_key: isCustom ? customCartKey(itemName, unitPrice) : null,
                name: itemName,
                uom: isCustom ? 'unit' : (ri.uom || p?.uom || ''),
                qty: parseOrderQty(ri.qty),
                unit_price: unitPrice,
                tax_percent: Number(ri.tax_percent) || 0,
                notes: ri.notes || '',
                kitchen_served: !!ri.kitchen_served,
                kitchen_pending: !!ri.kitchen_pending,
                kitchen_printed: !!ri.kitchen_printed,
                kitchen_locked_qty: kitchenLockedFromResume(ri),
                order_item_id: ri.id ? Number(ri.id) : null,
            });
        });
        sanitizeCartKitchenLocks();
        renderAll();
    }

    function setResumeStateFromOrder(order) {
        if (!order?.id) {
            return;
        }
        resumeOrderId = order.id;
        lastHeldOrderId = Number(order.id);
        try { sessionStorage.setItem('rp_last_held_order_id', String(order.id)); } catch (_) { /* ignore */ }
        const form = $('#rpSubmitForm');
        if (form) {
            form.querySelector('[name="resume_order_id"]').value = String(order.id);
        }
        let badge = document.querySelector('.rp-badge-order');
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'badge rp-badge-order';
            $('#rpTabMenu')?.parentElement?.prepend(badge);
        }
        badge.textContent = order.order_no || String(order.id);
        if ($('#rpBillKitchenNotes') && order.kitchen_notes !== undefined) {
            $('#rpBillKitchenNotes').value = order.kitchen_notes || '';
        }
    }

    function applyPendingOrderToCheckout(order) {
        kitchenVoids = [];
        itemReductions = [];
        cancelWholeOrderPending = false;
        ownerDiscountActive = !!order.is_owner_discount;

        const serviceType = order.service_type || 'dine_in';
        setServiceType(serviceType);

        if (serviceType === 'dine_in') {
            if (posTablesEnabled && $('#rpTable')) {
                $('#rpTable').value = order.table_id ? String(order.table_id) : '';
            } else if ($('#rpTableNo')) {
                $('#rpTableNo').value = order.guest_name || order.table_name || '';
            }
        } else if (serviceType === 'delivery') {
            if ($('#rpDeliveryName')) $('#rpDeliveryName').value = order.guest_name || '';
            if ($('#rpDeliveryPhone')) $('#rpDeliveryPhone').value = order.room_no || '';
            if ($('#rpDeliveryAddress')) $('#rpDeliveryAddress').value = order.order_notes || '';
        } else if (serviceType === 'takeaway') {
            if ($('#rpTakeawayContact')) {
                $('#rpTakeawayContact').value = order.room_no || order.guest_name || '';
            }
        }

        if ($('#rpBillKitchenNotes')) {
            $('#rpBillKitchenNotes').value = order.kitchen_notes || '';
        }

        const discInput = $('#rpBillDiscount');
        if (discInput) {
            if (ownerDiscountActive && canPosDiscountCredit) {
                setDiscountMode('percent');
                discInput.value = '100';
                discInput.readOnly = true;
            } else {
                discInput.readOnly = false;
                setDiscountMode('percent');
                discInput.value = String(Number(order.bill_discount_percent) || 0);
            }
        }

        setCreditMode(false);
        setResumeStateFromOrder(order);
        reloadCartFromOrder(order);
        updateOwnerDiscountButton();
        updateCheckoutActions();
        updateCancelOrderButton();

        // Pending bill open → Payment hamesha Cash default (Bank/Card sticky na rahe).
        if ($('#rpPayMethod')) $('#rpPayMethod').value = 'cash';
        const grand = calcCartTotals().grand;
        payments = [{ method: 'cash', amount: grand }];
        autoPaymentAmount = true;
    }

    async function payPendingBillNow(orderId) {
        if (!canPosPay) {
            throw new Error('Pay sirf cashier ya manager kar sakta hai.');
        }

        let order = (boot.pendingBillsDetail || []).find((o) => Number(o.id) === Number(orderId));
        if (!order?.items?.length) {
            await pollSync();
            order = (boot.pendingBillsDetail || []).find((o) => Number(o.id) === Number(orderId));
        }
        if (!order) {
            throw new Error('Pending bill nahi mili.');
        }
        if (!Array.isArray(order.items) || !order.items.length) {
            throw new Error('Bill items load nahi ho sake.');
        }

        applyPendingOrderToCheckout(order);
        openPayModal();
    }

    async function openPendingBillFast(orderId) {
        let order = (boot.pendingBillsDetail || []).find((o) => Number(o.id) === Number(orderId));
        if (!order?.items?.length) {
            await pollSync();
            order = (boot.pendingBillsDetail || []).find((o) => Number(o.id) === Number(orderId));
        }
        if (!order) {
            throw new Error('Pending bill nahi mili.');
        }
        if (!Array.isArray(order.items) || !order.items.length) {
            // Fallback: full page resume when items missing from sync payload.
            const resumeUrl = (routes.resume || '').replace('__ID__', String(orderId));
            if (resumeUrl) {
                window.location.assign(resumeUrl);
                return;
            }
            throw new Error('Bill items load nahi ho sake.');
        }

        applyPendingOrderToCheckout(order);
        showMenuPanel();
        setPanelView('split');
        renderAll();
    }

    function printUrlInHiddenFrame(url) {
        return new Promise((resolve, reject) => {
            document.getElementById('rpPrintFrame')?.remove();

            const iframe = document.createElement('iframe');
            iframe.id = 'rpPrintFrame';
            iframe.title = 'Print';
            iframe.style.cssText = 'position:fixed;width:0;height:0;border:0;opacity:0;pointer-events:none;';
            iframe.setAttribute('aria-hidden', 'true');

            let settled = false;
            const finish = (err) => {
                if (settled) return;
                settled = true;
                window.setTimeout(() => iframe.remove(), 400);
                if (err) reject(err);
                else resolve();
            };

            iframe.onload = () => {
                window.setTimeout(() => {
                    try {
                        const win = iframe.contentWindow;
                        if (!win) {
                            finish(new Error('Print tayyar nahi ho saki.'));
                            return;
                        }
                        win.focus();
                        win.print();
                        finish();
                    } catch (e) {
                        finish(e);
                    }
                }, 60);
            };

            iframe.onerror = () => finish(new Error('Print load failed.'));

            document.body.appendChild(iframe);
            iframe.src = url;
        });
    }

    function browserPrintKitchenSlip(orderId) {
        const base = (routes.kitchen || '').replace('__ID__', String(orderId));
        if (!base) {
            throw new Error('Kitchen print route missing.');
        }
        return printUrlInHiddenFrame(`${base}?noprint=1`);
    }

    function markUnlockedCartLinesKitchenPrinted(printedIds = null) {
        const idSet = Array.isArray(printedIds) && printedIds.length
            ? new Set(printedIds.map((id) => Number(id)).filter((id) => id > 0))
            : null;
        let changed = false;
        cart.forEach((r) => {
            const qty = Number(r.qty) || 0;
            if (qty <= 0.0005) return;
            const locked = Number(r.kitchen_locked_qty) || 0;
            if (locked >= qty - 0.0005) return;
            if (idSet) {
                const oid = Number(r.order_item_id) || 0;
                if (!oid || !idSet.has(oid)) return;
            }
            r.kitchen_printed = true;
            r.kitchen_pending = true;
            r.kitchen_served = false;
            r.kitchen_locked_qty = qty;
            changed = true;
        });
        if (changed) {
            renderAll();
        }
    }

    function cartHasUnprintedKitchenLines() {
        return cart.some((r) => {
            const qty = Number(r.qty) || 0;
            if (qty <= 0.0005) return false;
            const locked = Number(r.kitchen_locked_qty) || 0;
            return locked < qty - 0.0005;
        });
    }

    async function printKitchenSlip(orderId, attempt = 1) {
        const maxAttempts = 2;
        const netUrl = (routes.kitchenPrint || '').replace('__ID__', String(orderId));

        const applyOrder = (order) => {
            if (!order) return;
            upsertPendingBill(order, true);
            reloadCartFromOrder(order);
            setResumeStateFromOrder(order);
        };

        if (netUrl && csrf) {
            try {
                const res = await fetch(netUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                    },
                });
                const data = await res.json().catch(() => ({}));

                // No network printers configured → browser slip (marks remaining pending).
                if (data.fallback) {
                    await browserPrintKitchenSlip(orderId);
                    applyOrder(data.order);
                    if (!data.order) markUnlockedCartLinesKitchenPrinted();
                    return true;
                }

                if (data.empty_pending) {
                    applyOrder(data.order);
                    // If cart still shows New lines, save/print desync — force browser once.
                    if (cartHasUnprintedKitchenLines() && attempt < maxAttempts) {
                        await browserPrintKitchenSlip(orderId);
                        markUnlockedCartLinesKitchenPrinted();
                    }
                    return true;
                }

                const failed = (data.results || []).filter((r) => !r.ok);
                const stillPrinter = Number(data.remaining_with_printer || 0);
                const gotSomething = res.ok && (data.complete || data.ok || (data.printed_item_ids || []).length > 0);

                if (gotSomething) {
                    // Unrouted (no dept printer): browser slip so those items are never missed.
                    if (Number(data.unrouted || 0) > 0) {
                        try {
                            await browserPrintKitchenSlip(orderId);
                        } catch (brErr) {
                            console.warn('Unrouted kitchen browser slip failed', brErr);
                        }
                    }
                    applyOrder(data.order);
                    if (!data.order) {
                        markUnlockedCartLinesKitchenPrinted(data.printed_item_ids || null);
                    }
                    if (stillPrinter > 0 && attempt < maxAttempts) {
                        await new Promise((r) => setTimeout(r, 200));
                        return printKitchenSlip(orderId, attempt + 1);
                    }
                    if (stillPrinter > 0) {
                        throw new Error(
                            stillPrinter + ' kitchen item(s) print miss ho gayi. Kitchen Print dubara dabayein.'
                        );
                    }
                    if (failed.length && attempt === 1) {
                        // Soft notice — retry already ran server-side.
                        console.warn('Kitchen printer soft-fail', failed);
                    }
                    return true;
                }

                if (attempt < maxAttempts) {
                    await new Promise((r) => setTimeout(r, 220));
                    return printKitchenSlip(orderId, attempt + 1);
                }

                if (failed.length) {
                    throw new Error(
                        'Kitchen print fail:\n' +
                        failed.map((r) => `• ${r.department}: ${r.error || 'error'}`).join('\n')
                    );
                }
                throw new Error(data.message || 'Kitchen print complete nahi hui. Dubara try karein.');
            } catch (e) {
                const msg = String(e?.message || '');
                if (msg.includes('Kitchen print') || msg.includes('print miss') || msg.includes('print fail')) {
                    throw e;
                }
                if (attempt < maxAttempts) {
                    await new Promise((r) => setTimeout(r, 200));
                    return printKitchenSlip(orderId, attempt + 1);
                }
                // Last resort: browser slip for remaining unprinted pending.
            }
        }

        await browserPrintKitchenSlip(orderId);
        markUnlockedCartLinesKitchenPrinted();
        return true;
    }

    async function tryCashierNetworkPrint(orderId) {
        const url = (routes.cashierPrint || '').replace('__ID__', String(orderId));
        if (!url || !csrf) return false;
        try {
            const ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
            // Server ~4s; no auto-retry (retry after late OK = duplicate slip).
            const timer = ctrl ? window.setTimeout(() => ctrl.abort(), 10000) : null;
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                },
                signal: ctrl?.signal,
            });
            if (timer) window.clearTimeout(timer);
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.ok) return true;
            if (!data.fallback && data.message) {
                const msg = String(data.message || '');
                if (/connect|timeout|band \/ network|did not properly respond/i.test(msg)) {
                    alert('Cashier printer connect nahi ho rahi (IP / power / LAN check karein).\n\n' + msg);
                } else {
                    alert(msg);
                }
            }
            return false;
        } catch (e) {
            return false;
        }
    }

    /** Paid bill: exactly one cashier thermal print (API). Receipt opens with noprint if ok. */
    function queuePaidBillPrint(data) {
        if (!data) return;
        const orderId = data.order_id;
        const openReceipt = (qs) => {
            if (!data.receipt_url) return;
            window.open(
                data.receipt_url + (data.receipt_url.includes('?') ? '&' : '?') + qs,
                '_blank',
                'noopener,noreferrer'
            );
        };

        if (!orderId) {
            openReceipt('autoprint=1');
            return;
        }

        // Single print path — do not also rely on server afterResponse queue.
        tryCashierNetworkPrint(orderId).then((ok) => {
            openReceipt(ok ? 'noprint=1' : 'autoprint=1');
        });
    }

    function splitBillMode() {
        const checked = document.querySelector('.rp-split-mode-check:checked');
        return checked?.value || 'item';
    }

    function syncSplitBillPanes() {
        const mode = splitBillMode();
        $('#rpSplitItemPane')?.classList.toggle('d-none', mode !== 'item');
        $('#rpSplitMemberPane')?.classList.toggle('d-none', mode !== 'member');
    }

    function onSplitModeToggle(e) {
        const box = e.target.closest('.rp-split-mode-check');
        if (!box) return;
        if (box.checked) {
            document.querySelectorAll('.rp-split-mode-check').forEach((el) => {
                if (el !== box) el.checked = false;
            });
        } else {
            // Always keep one selected
            box.checked = true;
        }
        syncSplitBillPanes();
    }

    function splitBillError(msg) {
        const el = $('#rpSplitBillError');
        if (!el) return;
        if (!msg) {
            el.classList.add('d-none');
            el.textContent = '';
            return;
        }
        el.textContent = msg;
        el.classList.remove('d-none');
    }

    function openSplitBillModal() {
        if (!resumeOrderId) {
            alert('Pehle pending bill open/resume karein, phir Split Bill use karein.');
            return;
        }
        if (!cart.length) {
            alert('Bill me items nahi hain.');
            return;
        }

        const list = $('#rpSplitItemList');
        if (list) {
            const rows = cart.filter((r) => Number(r.order_item_id) > 0);
            const source = rows.length ? rows : (boot.resumeItems || []).filter((r) => Number(r.id) > 0);
            if (!source.length) {
                alert('Split ke liye pehle Hold Order / Kitchen Print se bill save karein.');
                return;
            }
            list.innerHTML = source.map((r, idx) => {
                const id = Number(r.order_item_id || r.id);
                const name = escHtml(r.name || r.item_name || 'Item');
                const qty = escHtml(fmtQty(r.qty));
                const amount = escHtml(fmtMoney((Number(r.qty) || 0) * (Number(r.unit_price) || 0)));
                return `<label class="rp-split-item-row">
                    <input type="checkbox" class="rp-split-item-check" value="${id}" data-idx="${idx}">
                    <span class="rp-split-item-main">
                        <strong>${name}</strong>
                        <small>Qty ${qty} · ${amount}</small>
                    </span>
                </label>`;
            }).join('');
        }

        const memberInput = $('#rpSplitMemberCount');
        if (memberInput) memberInput.value = '2';
        const itemCheck = document.querySelector('.rp-split-mode-check[value="item"]');
        const memberCheck = document.querySelector('.rp-split-mode-check[value="member"]');
        if (itemCheck) itemCheck.checked = true;
        if (memberCheck) memberCheck.checked = false;
        syncSplitBillPanes();
        splitBillError('');

        const modalEl = $('#rpSplitBillModal');
        if (modalEl && window.bootstrap?.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }

    async function confirmSplitBill() {
        if (!resumeOrderId) {
            splitBillError('Pending bill open nahi.');
            return;
        }
        const mode = splitBillMode();
        const payload = { mode };
        if (mode === 'item') {
            const ids = Array.from(document.querySelectorAll('.rp-split-item-check:checked'))
                .map((el) => Number(el.value))
                .filter((id) => id > 0);
            if (!ids.length) {
                splitBillError('Kam az kam aik item select karein.');
                return;
            }
            payload.item_ids = ids;
        } else {
            const members = Number($('#rpSplitMemberCount')?.value || 0);
            if (!Number.isFinite(members) || members < 2) {
                splitBillError('Members kam az kam 2 likhein.');
                return;
            }
            payload.members = Math.min(20, Math.floor(members));
        }

        const url = (routes.splitBill || '').replace('__ID__', String(resumeOrderId));
        if (!url || !csrf) {
            splitBillError('Split route missing.');
            return;
        }

        const btn = $('#rpSplitBillConfirm');
        if (btn) btn.disabled = true;
        splitBillError('');

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.ok) {
                splitBillError(data.message || 'Bill split fail ho gayi.');
                return;
            }

            // Merge created + updated original into pending list
            const updates = Array.isArray(data.pending_updates) ? data.pending_updates : [];
            let pending = Array.isArray(boot.pendingBillsDetail) ? [...boot.pendingBillsDetail] : [];
            updates.forEach((row) => {
                const idx = pending.findIndex((o) => Number(o.id) === Number(row.id));
                if (idx >= 0) pending[idx] = { ...pending[idx], ...row };
                else pending.unshift(row);
            });
            boot.pendingBillsDetail = pending;
            if (Array.isArray(data.table_board)) {
                boot.tableBoard = data.table_board;
                applyTableBoard(data.table_board);
            }
            updateOrderTabCounts();

            const modalEl = $('#rpSplitBillModal');
            if (modalEl && window.bootstrap?.Modal) {
                window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            }

            alert(data.message || 'Bill split ho gayi.');
            // Reload original (first share) so cart matches DB
            const resumeUrl = (routes.resume || '').replace('__ID__', String(data.original?.id || resumeOrderId));
            if (resumeUrl) {
                window.location.href = resumeUrl;
            } else {
                window.location.reload();
            }
        } catch (e) {
            splitBillError('Split request fail ho gayi.');
        } finally {
            if (btn) btn.disabled = false;
        }
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
        itemReductions = [];
        cancelWholeOrderPending = false;
        resetForNewBill();
        if (message) {
            alert(message);
        }
    }

    function isStaleOrderResponse(res, data, message) {
        if (res.status === 404) {
            return true;
        }
        if (data && data.already_paid) {
            return true;
        }
        const text = String(message || data.message || '').toLowerCase();
        if (text.includes('pehle se paid')) {
            return true;
        }
        return text.includes('no query results for model') && text.includes('posorder');
    }

    function enqueueResumeSave(task) {
        const run = resumeSaveLock.then(async () => {
            let waits = 0;
            while (kitchenPrintBusy && waits < 200) {
                await new Promise((r) => setTimeout(r, 50));
                waits += 1;
            }
            return task();
        }, async () => {
            let waits = 0;
            while (kitchenPrintBusy && waits < 200) {
                await new Promise((r) => setTimeout(r, 50));
                waits += 1;
            }
            return task();
        });
        resumeSaveLock = run.catch(() => {});
        return run;
    }

    async function discardResumedDraft() {
        if (!resumeOrderId) {
            return;
        }

        const orderId = resumeOrderId;
        const voidsSnapshot = kitchenVoids.slice();
        const url = (routes.discardHold || '').replace('__ID__', String(orderId));
        if (!url) {
            throw new Error('Discard route missing.');
        }

        const body = new FormData();
        body.append('_token', csrf);
        body.append('_method', 'DELETE');
        if (voidsSnapshot.length) {
            body.append('kitchen_voids', JSON.stringify(voidsSnapshot));
        }
        if (cancelWholeOrderPending) {
            body.append('cancel_whole_order', '1');
        }

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

        notifyRemovedPrintResult(data.removed_print, voidsSnapshot.length > 0);

        const discardedOrder = (boot.pendingBillsDetail || []).find(
            (o) => Number(o.id) === Number(orderId)
        );

        boot.pendingBillsDetail = (boot.pendingBillsDetail || []).filter(
            (o) => Number(o.id) !== Number(orderId)
        );
        if (discardedOrder?.table_id) {
            setTableBoardStatus(discardedOrder.table_id, 'free');
        }
        updateOrderTabCounts();
        if (orderListMode === 'pending') {
            renderOrderCards();
        }
        if (voidsSnapshot.length && canViewKitchenVoids) {
            loadSessionKitchenVoids().then(() => {
                if (orderListMode === 'kitchen-voids') {
                    renderOrderCards();
                }
            });
        }
        kitchenVoids = [];
        itemReductions = [];
        cancelWholeOrderPending = false;
        resetForNewBill();
    }

    function notifyRemovedPrintResult(result, expected = false) {
        if (!expected && (!result || !Array.isArray(result.results) || result.results.length === 0) && !(result?.unrouted > 0)) {
            return;
        }
        if (result && result.ok === true && !(result.unrouted > 0)) {
            return;
        }
        const parts = [];
        if (result?.message) {
            parts.push(String(result.message));
        }
        if (result?.unrouted > 0) {
            parts.push(result.unrouted + ' item(s) ka department printer nahi mila.');
        }
        (result?.results || []).forEach((r) => {
            if (r && r.ok === false) {
                parts.push((r.department || 'Printer') + ': ' + (r.error || 'print fail'));
            }
        });
        if (!parts.length && expected) {
            parts.push('Removed Items slip department printer par nahi gayi.');
        }
        if (parts.length) {
            alert('Removed Items slip print issue:\n' + parts.join('\n'));
        }
    }

    async function saveResumedDraftChanges(sendToKitchen = false) {
        if (!resumeOrderId) {
            return null;
        }

        return enqueueResumeSave(async () => {
            setCartSaving(true);
            try {
                if (!cart.length) {
                    // Empty cart:
                    // - Kitchen-printed bill → sirf manager Cancel Order (voids) se delete.
                    // - Hold-only (no kitchen print) → pending draft discard OK, items free remove.
                    if (cancelWholeOrderPending && kitchenVoids.length > 0) {
                        await discardResumedDraft();
                        return null;
                    }
                    if (!resumedOrderHasKitchenPrint()) {
                        await discardResumedDraft();
                        return null;
                    }
                    throw new Error(
                        'Cart khali hai. Kitchen print wali pending bill cancel ke bina delete nahi hoti — Cancel Order (manager) use karein ya items wapas add karein.'
                    );
                }

                const formData = buildHoldFormData(sendToKitchen, null, {
                    // Kitchen update: contact optional. Unpaid/hold update: contact zaroori.
                    requireGuestMeta: !sendToKitchen,
                });
                if (!formData) {
                    throw new Error(sendToKitchen
                        ? 'Order save tayyar nahi ho saki.'
                        : 'Contact / guest details likhein, phir unpaid slip print karein.');
                }

                const voidsSnapshot = kitchenVoids.slice();
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
                    return null;
                }

                if (!res.ok) {
                    throw new Error(errMsg);
                }

                const hadKitchenVoids = voidsSnapshot.length > 0;
                if (data.order) {
                    upsertPendingBill(data.order, true);
                    // Always reload from server so kitchen-locked lines never vanish from cart UI.
                    reloadCartFromOrder(data.order);
                    // Cancelled kitchen lines must not reappear if server briefly re-appended them.
                    if (hadKitchenVoids && applyKitchenVoidsToCart(voidsSnapshot)) {
                        renderAll();
                    }
                    setResumeStateFromOrder(data.order);
                    if (data.order.table_id) {
                        setTableBoardStatus(data.order.table_id, 'occupied');
                    }
                }
                kitchenVoids = [];
                itemReductions = [];
                notifyRemovedPrintResult(data.removed_print, hadKitchenVoids);
                if (hadKitchenVoids && canViewKitchenVoids) {
                    loadSessionKitchenVoids().then(() => {
                        if (orderListMode === 'kitchen-voids') {
                            renderOrderCards();
                        }
                    });
                }
                return data.order || null;
            } finally {
                setCartSaving(false);
            }
        });
    }

    function setCartSaving(isSaving) {
        const wrap = $('#rpCartLines');
        if (!wrap) return;
        wrap.classList.toggle('is-saving', isSaving);
        wrap.querySelectorAll('.rp-cl-remove, .rp-cl-qty-btn, .rp-cl-note').forEach((btn) => {
            btn.disabled = isSaving;
        });
        const billNotes = $('#rpBillKitchenNotes');
        if (billNotes) billNotes.disabled = isSaving;
    }

    async function ensureHeldOrderForPrint() {
        // Never revive a stale sessionStorage bill id onto a fresh cart.
        const existingId = Number(resumeOrderId || 0);
        if (Number.isFinite(existingId) && existingId > 0) {
            resumeOrderId = existingId;
            const form = $('#rpSubmitForm');
            if (form) {
                form.querySelector('[name="resume_order_id"]').value = String(existingId);
            }
            // Quick save so unpaid slip latest cart + contact dikhaye — same bill update, naya order nahi.
            await saveResumedDraftChanges(false);
            return existingId;
        }

        const formData = buildHoldFormData(false, newClientRequestId());
        if (!formData) {
            throw new Error('Contact / guest details likhein, phir unpaid slip print karein.');
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
        if (!res.ok) {
            const validationMsg = data.errors ? Object.values(data.errors).flat()[0] : null;
            throw new Error(data.message || validationMsg || 'Hold failed.');
        }

        const order = data.order || null;
        const orderId = Number(order?.id || 0);
        if (!orderId) {
            throw new Error('Order save nahi ho saki.');
        }
        if (order) {
            upsertPendingBill(order, !!data.updated);
            reloadCartFromOrder(order);
            rememberHeldOrder(order);
            if (order.table_id) {
                setTableBoardStatus(order.table_id, 'occupied');
            }
        }
        return orderId;
    }

    async function printUnpaidBill() {
        if (settings.allow_bill_print === false) {
            return;
        }
        if (!cart.length) {
            alert('Pehle item add karein.');
            return;
        }

        const btn = $('#rpPrintUnpaidBtn');
        if (btn) btn.disabled = true;
        try {
            const orderId = await ensureHeldOrderForPrint();
            await printUnpaidBillForOrder(orderId);
        } catch (e) {
            alert(e.message || 'Unpaid bill print nahi ho saki.');
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    async function printUnpaidBillForOrder(orderId) {
        if (settings.allow_bill_print === false) {
            return;
        }
        const id = Number(orderId);
        if (!Number.isFinite(id) || id <= 0) {
            throw new Error('Order id missing.');
        }
        if (await tryCashierNetworkPrint(id)) {
            return;
        }
        const base = (routes.receiptUnpaid || '').replace('__ID__', String(id));
        if (!base) {
            throw new Error('Print route missing.');
        }
        window.open(`${base}?autoprint=1`, '_blank', 'noopener,noreferrer');
    }

    async function submitHoldOrder() {
        if (holdSubmitLock || kitchenPrintBusy) return;
        if (!prepareSubmit('hold')) return;

        const holdBtn = $('#rpHoldBtn');
        holdSubmitLock = true;
        if (holdBtn) holdBtn.disabled = true;
        let savedOk = false;
        try {
            if (resumeOrderId) {
                await saveResumedDraftChanges(false);
                resetForNewBill();
                savedOk = true;
                return;
            }

            const formData = buildHoldFormData(false, newClientRequestId());
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
                if (data.order.table_id) {
                    setTableBoardStatus(data.order.table_id, 'occupied');
                }
            } else if (typeof data.held_count === 'number') {
                updateOrderTabCounts();
            }

            savedOk = true;
            resetForNewBill();
        } catch (e) {
            alert(e.message || 'Hold failed.');
        } finally {
            const unlockMs = savedOk ? 2500 : 0;
            window.setTimeout(() => {
                holdSubmitLock = false;
                if (holdBtn) holdBtn.disabled = false;
            }, unlockMs);
        }
    }

    async function submitKitchenPrint() {
        if (holdSubmitLock || kitchenPrintBusy) return;
        if (!cart.length) {
            alert('Pehle item add karein.');
            return;
        }
        // Contact optional on kitchen — unpaid bill pe number maanga jayega.
        if (!prepareSubmit('hold', { requireGuestMeta: false })) return;

        const kitchenBtn = $('#rpKitchenPrintBtn');
        if (kitchenBtn) kitchenBtn.disabled = true;
        holdSubmitLock = true;
        kitchenPrintBusy = true;
        let savedOk = false;
        try {
            let order = null;
            const existingId = Number(resumeOrderId || 0);
            if (Number.isFinite(existingId) && existingId > 0) {
                resumeOrderId = existingId;
                order = await saveResumedDraftChanges(true);
            } else {
                const formData = buildHoldFormData(true, newClientRequestId(), { requireGuestMeta: false });
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
                    throw new Error(data.message || validationMsg || 'Kitchen print failed.');
                }

                order = data.order || null;
                if (order) {
                    upsertPendingBill(order, !!data.updated);
                    reloadCartFromOrder(order);
                    rememberHeldOrder(order);
                    if (order.table_id) {
                        setTableBoardStatus(order.table_id, 'occupied');
                    }
                }
            }

            const orderId = order?.id || resumeOrderId;
            if (!orderId) {
                throw new Error('Order save nahi ho saki.');
            }
            if (order) {
                rememberHeldOrder(order);
            } else {
                resumeOrderId = Number(orderId);
                lastHeldOrderId = Number(orderId);
            }
            savedOk = true;
            const hadNewBeforePrint = cartHasUnprintedKitchenLines();
            await printKitchenSlip(orderId);

            // Final safety: agar ab bhi New/unprinted lines hain to ek aur forced pass.
            if (hadNewBeforePrint && cartHasUnprintedKitchenLines()) {
                await printKitchenSlip(orderId, 1);
            }
            if (cartHasUnprintedKitchenLines()) {
                alert('Warning: kuch items ab bhi Kitchen print pending hain (New tag). Kitchen Print dubara dabayein.');
            } else {
                // Hold jaisa: order pending list mein reh jaye, cart nayi bill ke liye khali.
                resetForNewBill();
            }
        } catch (e) {
            alert(e.message || 'Kitchen print failed.');
        } finally {
            kitchenPrintBusy = false;
            const unlockMs = savedOk ? 2500 : 0;
            window.setTimeout(() => {
                holdSubmitLock = false;
                if (kitchenBtn) kitchenBtn.disabled = false;
            }, unlockMs);
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
        $('#rpProductSearch')?.addEventListener('input', () => {
            if (orderListMode) {
                if (orderListMode === 'pending' || orderListMode === 'paid') {
                    updateBillsMenuHead();
                }
                renderOrderCards();
            }
            else renderMenuGrid();
        });
        $('#rpProductSearch')?.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter' || orderListMode) return;
            e.preventDefault();
            const q = ($('#rpProductSearch')?.value || '').trim().toLowerCase();
            if (!q) return;
            const list = products.filter((p) => isProductVisible(p) && productMatchesMenuCategory(p) && (
                String(p.name).toLowerCase().includes(q) || String(p.sku || '').toLowerCase().includes(q)
            ));
            if (!list.length) return;
            addOrIncrementProduct(list[0].id);
        });
        $('#rpMenuCats')?.addEventListener('click', (e) => {
            const btn = e.target.closest('.rp-menu-cat');
            if (!btn) return;
            setMenuCategory(btn.dataset.catId || null);
        });
        $('#rpMenuGrid')?.addEventListener('click', (e) => {
            const moveBtn = e.target.closest('[data-action="move-table"]');
            if (moveBtn) {
                e.preventDefault();
                e.stopPropagation();
                openMoveTableModal(moveBtn.dataset.orderId, moveBtn.dataset.orderNo || '');
                return;
            }

            const printBtn = e.target.closest('[data-action="print-unpaid"]');
            if (printBtn) {
                e.preventDefault();
                e.stopPropagation();
                const btn = printBtn;
                btn.disabled = true;
                printUnpaidBillForOrder(btn.dataset.orderId)
                    .catch((err) => alert(err.message || 'Unpaid bill print nahi ho saki.'))
                    .finally(() => { btn.disabled = false; });
                return;
            }

            const payNowBtn = e.target.closest('[data-action="pay-now"]');
            if (payNowBtn) {
                e.preventDefault();
                e.stopPropagation();
                const btn = payNowBtn;
                btn.disabled = true;
                payPendingBillNow(btn.dataset.orderId)
                    .catch((err) => alert(err.message || 'Pay Now fail ho gaya.'))
                    .finally(() => { btn.disabled = false; });
                return;
            }

            const openPendingBtn = e.target.closest('[data-action="open-pending"]');
            if (openPendingBtn) {
                e.preventDefault();
                e.stopPropagation();
                const btn = openPendingBtn;
                btn.disabled = true;
                openPendingBillFast(btn.dataset.orderId)
                    .catch((err) => alert(err.message || 'Bill open nahi ho saki.'))
                    .finally(() => { btn.disabled = false; });
                return;
            }

            const reopenBtn = e.target.closest('[data-action="reopen-paid"]');
            if (reopenBtn) {
                e.preventDefault();
                e.stopPropagation();
                reopenPaidBill(reopenBtn.dataset.orderId, reopenBtn.dataset.orderNo || '');
                return;
            }

            const btn = e.target.closest('button[data-action]');
            if (!btn) return;
            const id = Number(btn.dataset.id);
            if (btn.dataset.action === 'inc') addOrIncrementProduct(id);
            if (btn.dataset.action === 'dec') changeCartQty(id, -1);
        });
        $('#rpCartLines')?.addEventListener('click', async (e) => {
            if (e.target.closest('.rp-cl-qty-input')) {
                return;
            }
            const qtyBtn = e.target.closest('[data-action="cart-inc"], [data-action="cart-dec"]');
            if (qtyBtn && !qtyBtn.disabled) {
            if (qtyBtn.dataset.index !== undefined && qtyBtn.dataset.index !== '') {
                    const index = Number(qtyBtn.dataset.index);
                    if (!Number.isFinite(index)) return;
                    if (qtyBtn.dataset.action === 'cart-inc') {
                        changeCartLineQty(index, 1);
                    } else {
                        changeCartLineQty(index, -1);
                    }
                    return;
                }
                const id = Number(qtyBtn.dataset.id);
                if (!Number.isFinite(id)) return;
                if (qtyBtn.dataset.action === 'cart-inc') {
                    addOrIncrementProduct(id);
                } else {
                    changeCartQty(id, -1);
                }
                return;
            }

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
        $('#rpCartLines')?.addEventListener('focusin', (e) => {
            if (e.target.matches('.rp-cl-qty-input')) {
                e.target.select();
            }
        });
        $('#rpCartLines')?.addEventListener('keydown', (e) => {
            if (e.target.matches('.rp-cl-qty-input') && e.key === 'Enter') {
                e.preventDefault();
                e.target.blur();
            }
        });
        $('#rpCartLines')?.addEventListener('blur', (e) => {
            if (e.target.matches('.rp-cl-qty-input')) {
                commitCartQtyInput(e.target);
            }
        }, true);
        $('#rpCartLines')?.addEventListener('input', (e) => {
            if (!e.target.matches('.rp-cl-note')) return;
            const idx = Number(e.target.dataset.index);
            if (!Number.isFinite(idx) || !cart[idx]) return;
            cart[idx].notes = String(e.target.value || '');
        });
        $('#rpServiceTypes')?.addEventListener('click', (e) => {
            const btn = e.target.closest('.rp-service-type');
            if (!btn?.dataset.type) return;
            setServiceType(btn.dataset.type);
        });
        $('#rpTable')?.addEventListener('change', () => {
            const tableId = Number($('#rpTable')?.value || 0);
            if (!tableId) {
                updateTableSelectAppearance();
                return;
            }
            handleReservedTableSelection(tableId);
        });
        $('#rpHoldBtn')?.addEventListener('click', () => submitHoldOrder());
        $('#rpKitchenPrintBtn')?.addEventListener('click', () => submitKitchenPrint());
        $('#rpCancelOrderBtn')?.addEventListener('click', () => requestCancelWholeOrder());
        $('#rpSplitBillBtn')?.addEventListener('click', () => openSplitBillModal());
        document.querySelectorAll('.rp-split-mode-check').forEach((el) => {
            el.addEventListener('change', onSplitModeToggle);
        });
        $('#rpSplitBillConfirm')?.addEventListener('click', () => confirmSplitBill());
        $('#rpPrintUnpaidBtn')?.addEventListener('click', () => printUnpaidBill());
        $('#rpWhatsappBtn')?.addEventListener('click', () => openDeliveryWhatsapp());
        $('#rpPayBtn')?.addEventListener('click', () => openPayModal());
        $('#rpPayModalConfirm')?.addEventListener('click', () => confirmPayModal({ printBill: true }));
        $('#rpPayModalMarkPaid')?.addEventListener('click', () => confirmPayModal({ printBill: false }));
        $('#rpCashSuggestions')?.addEventListener('click', (e) => {
            const btn = e.target.closest('.rp-cash-chip');
            if (!btn) return;
            if (btn.dataset.action === 'manual') {
                const current = Number($('#rpCashTendered')?.value || 0);
                setCashTendered(current > 0 ? current : '', { manual: true });
                setTimeout(() => {
                    const input = $('#rpCashTendered');
                    input?.focus();
                    input?.select();
                }, 50);
                return;
            }
            const amount = Number(btn.dataset.amount);
            if (!Number.isFinite(amount)) return;
            setCashTendered(amount, { manual: false });
        });
        $('#rpCashTendered')?.addEventListener('input', () => {
            syncCashSuggestionActive(Number($('#rpCashTendered')?.value || 0), true);
            updatePayModalAmounts();
        });
        $('#rpCashTendered')?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (!$('#rpPayModalConfirm')?.disabled) {
                    confirmPayModal();
                }
            }
        });
        $('#rpOwnerDiscountBtn')?.addEventListener('click', applyOwnerDiscount);
        $('#rpTabPending')?.addEventListener('click', () => setOrderListMode('pending'));
        $('#rpTabPaid')?.addEventListener('click', () => setOrderListMode('paid'));
        $('#rpTabKitchenVoids')?.addEventListener('click', () => setOrderListMode('kitchen-voids'));
        $('#rpTabMenu')?.addEventListener('click', () => {
            if (orderListMode) showMenuPanel();
            else focusProductSearch();
            togglePanelView('menu');
        });
        $('#rpTabCart')?.addEventListener('click', () => togglePanelView('cart'));
        $('#rpToggleCartView')?.addEventListener('click', () => togglePanelView('cart'));
        $('#rpBillDiscount')?.addEventListener('input', () => {
            if (ownerDiscountActive) {
                const raw = Number($('#rpBillDiscount')?.value || 0);
                if (discountMode === 'percent' && raw !== 100) {
                    clearOwnerDiscount(false);
                } else if (discountMode === 'amount') {
                    clearOwnerDiscount(false);
                }
            }
            if (discountMode === 'percent') {
                const el = $('#rpBillDiscount');
                if (el && Number(el.value) > 100) el.value = '100';
            }
            renderTotals();
        });
        $('#rpDiscModePct')?.addEventListener('click', () => {
            if (ownerDiscountActive) return;
            if (discountMode === 'percent') return;
            const totals = calcCartTotals();
            setDiscountMode('percent');
            const el = $('#rpBillDiscount');
            if (el) {
                el.value = totals.subtotal > 0
                    ? String(Math.round((totals.discount / totals.subtotal) * 10000) / 100)
                    : '0';
            }
            renderTotals();
        });
        $('#rpDiscModeRs')?.addEventListener('click', () => {
            if (ownerDiscountActive) return;
            if (discountMode === 'amount') return;
            const totals = calcCartTotals();
            setDiscountMode('amount');
            const el = $('#rpBillDiscount');
            if (el) el.value = String(totals.discount || 0);
            renderTotals();
        });

        $('#rpCreditToggle')?.addEventListener('change', (e) => {
            if (!canPosDiscountCredit) {
                e.target.checked = false;
                return;
            }
            setCreditMode(e.target.checked);
        });

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
        $('#rpRemoveReasonTemplates')?.addEventListener('click', (e) => {
            const chip = e.target.closest('.rp-reason-chip');
            if (!chip) return;
            selectRemoveReasonTemplate(chip);
        });
        $('#rpRemoveReason')?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                confirmRemoveWithReason();
            }
        });
        $('#rpRemoveReason')?.addEventListener('input', () => {
            $('#rpRemoveReasonError')?.classList.add('d-none');
        });
        $('#rpRemoveReasonModal')?.addEventListener('hidden.bs.modal', () => {
            pendingChangeAction = null;
        });

        $('#rpOnDemandBtn')?.addEventListener('click', () => openOnDemandModal());
        $('#rpOnDemandAdd')?.addEventListener('click', () => confirmOnDemandAdd());
        $('#rpOnDemandName')?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#rpOnDemandPrice')?.focus();
            }
        });
        $('#rpOnDemandPrice')?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                confirmOnDemandAdd();
            }
        });
        $('#rpOnDemandQty')?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                confirmOnDemandAdd();
            }
        });
    }

    function loadResumeItems() {
        const items = boot.resumeItems || [];
        items.forEach((ri) => {
            const p = products.find((x) => Number(x.id) === Number(ri.product_id));
            const isCustom = !!ri.is_custom;
            const itemName = String(ri.item_name || ri.name || p?.name || 'Item').trim();
            if (!itemName) return;
            const unitPrice = Number(ri.unit_price) || (isCustom ? 0 : (p ? unitPriceForProduct(p, ri.uom || p.uom) : 0));
            cart.push({
                product_id: Number(ri.product_id) || posCustomProductId,
                is_custom: isCustom,
                item_name: isCustom ? itemName : null,
                cart_key: isCustom ? customCartKey(itemName, unitPrice) : null,
                name: itemName,
                uom: isCustom ? 'unit' : (ri.uom || p?.uom || ''),
                qty: parseOrderQty(ri.qty),
                unit_price: unitPrice,
                tax_percent: Number(ri.tax_percent) || 0,
                notes: ri.notes || '',
                kitchen_served: !!ri.kitchen_served,
                kitchen_pending: !!ri.kitchen_pending,
                kitchen_printed: !!ri.kitchen_printed,
                kitchen_locked_qty: kitchenLockedFromResume(ri),
                order_item_id: ri.id ? Number(ri.id) : null,
            });
        });
        sanitizeCartKitchenLocks();
    }

    async function pollSync() {
        if (!routes.sync) return;
        try {
            const res = await fetch(routes.sync, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) return;
            const data = await res.json();
            if (Array.isArray(data.pending)) {
                boot.pendingBillsDetail = data.pending;
            }
            if (Array.isArray(data.paid)) {
                boot.paidBillsDetail = data.paid;
            }
            if (Array.isArray(data.pending) || Array.isArray(data.paid)) {
                updateOrderTabCounts();
                if (orderListMode === 'pending' || orderListMode === 'paid') {
                    renderOrderCards();
                }
            }
            if (Array.isArray(data.table_board)) {
                applyTableBoard(data.table_board);
            }
        } catch (_) { /* ignore */ }
    }

    function init() {
        if (settings.resume_service_type) {
            setServiceType(settings.resume_service_type);
            if (settings.resume_service_type === 'takeaway' && settings.resume_room_no && $('#rpTakeawayContact')) {
                $('#rpTakeawayContact').value = settings.resume_room_no;
            }
        } else {
            syncServiceDetailPanels();
        }
        if (posShowCustomerSection && canPosDiscountCredit && settings.resume_is_credit) {
            setCreditMode(true);
        }
        restoreResumeContact();
        loadResumeItems();
        if (settings.resume_is_owner_discount) {
            ownerDiscountActive = true;
            if (canPosDiscountCredit) {
                setDiscountMode('percent');
                const discInput = $('#rpBillDiscount');
                if (discInput) {
                    discInput.value = '100';
                    discInput.readOnly = true;
                }
            }
        } else {
            setDiscountMode('percent');
        }
        bindEvents();
        applyTableBoard(boot.tableBoard || []);
        updateOrderTabCounts();
        if (canViewKitchenVoids) {
            loadSessionKitchenVoids();
        }
        updateOwnerDiscountButton();
        updateCheckoutActions();
        renderAll();
        payments = [{ method: 'cash', amount: 0 }];
        if (boot.activeOrderTab === 'paid') {
            showPaidTabAfterCheckout();
        } else if (boot.activeOrderTab === 'pending') {
            setOrderListMode('pending', { force: true });
        } else {
            focusProductSearch();
        }
        setInterval(pollSync, 20000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
