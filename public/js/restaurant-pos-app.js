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
    const posTablesEnabled = boot.tablesEnabled !== undefined ? !!boot.tablesEnabled : !!settings.enable_tables;
    const posShowCustomerSection = settings.show_customer_section !== false;
    const canVoidKitchenItems = boot.canVoidKitchenItems === true;
    // Delete / qty-kam: cashier pre-kitchen allowed; kitchen-locked voids sirf manager/admin.
    const canReduceCartItems = boot.canReduceCartItems === true || canVoidKitchenItems;
    // Legacy flag — reason ab sirf kitchen-locked items par mangte hain.
    const requireItemChangeReason = false;
    const canReopenPaidBill = boot.canReopenPaidBill === true;

    let cart = [];
    let kitchenVoids = [];
    let itemReductions = [];
    let kitchenVoidsSessionList = [];
    let kitchenVoidsLoading = false;
    let pendingChangeAction = null;
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
        return !!p.for_pos;
    }

    function getBillDiscountPercent() {
        if (ownerDiscountActive) return 100;
        if (posShowDiscount) return Number($('#rpBillDiscount')?.value || 0);
        if (resumeOwnerDiscount) return 100;
        return resumeBillDiscount;
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
        const billDiscPct = getBillDiscountPercent();
        let discount = billDiscPct > 0 ? Math.round(subtotal * billDiscPct / 100 * 100) / 100 : 0;
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
        return (ri.kitchen_served || ri.kitchen_pending || ri.kitchen_printed) ? qty : 0;
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

    function increaseProductQtyBy(productId, addQty) {
        const p = products.find((x) => Number(x.id) === Number(productId));
        if (!p || addQty <= 0) return;
        const existing = cart.find((r) => Number(r.product_id) === Number(productId) && String(r.uom) === String(p.uom));
        if (existing) {
            existing.qty = Math.round((Number(existing.qty) + addQty) * 1000) / 1000;
            existing.unit_price = unitPriceForProduct(p, existing.uom);
        } else {
            cart.push({
                product_id: p.id,
                name: p.name,
                uom: p.uom,
                qty: Math.round(addQty * 1000) / 1000,
                unit_price: unitPriceForProduct(p, p.uom),
                tax_percent: 0,
                notes: '',
                kitchen_served: false,
                kitchen_pending: false,
                kitchen_locked_qty: 0,
            });
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
            if (!canReduceCartItems) {
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
            if (!canReduceCartItems) {
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
        return {
            product_id: row.product_id,
            uom: row.uom,
            qty: Math.round(Number(qty) * 1000) / 1000,
            reason: String(reason || '').trim(),
            notes: String(row.notes || '').trim(),
            name: row.name,
        };
    }

    function findCartRowForProduct(productId) {
        return cart.find((r) => Number(r.product_id) === Number(productId)) || null;
    }

    async function removeCartLine(index, reason) {
        const row = cart[index];
        if (!row) return;

        if (!canReduceCartItems) {
            alert('Item remove nahi ho sakti.');
            return;
        }

        const locked = Number(row.kitchen_locked_qty) || 0;
        if (locked > 0 && !canVoidKitchenItems) {
            alert('Kitchen print ke baad item sirf manager/admin remove kar sakta hai.');
            return;
        }

        // Reason sirf tab jab kitchen print ho chuka ho (locked qty).
        const needsReason = locked > 0 && !String(reason || '').trim();
        if (needsReason) {
            openItemChangeReasonModal({ type: 'remove', index });
            return;
        }

        const voidsBefore = kitchenVoids.length;
        const reductionsBefore = itemReductions.length;
        const reasonText = String(reason || '').trim();

        if (locked > 0 && reasonText) {
            kitchenVoids.push(buildReductionEntry(row, locked, reasonText));
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

        let label = '';
        if (action.type === 'remove') {
            const row = cart[action.index];
            label = row ? `${fmtQty(row.qty)}× ${row.name}` : '';
            if (title) title.textContent = 'Item hataein';
            if (hint) {
                hint.textContent = Number(row?.kitchen_locked_qty) > 0
                    ? 'Kitchen item hataane ka reason likhein:'
                    : 'Item hataane ka reason likhein:';
            }
            if (confirmBtn) confirmBtn.innerHTML = '<i class="bi bi-trash"></i> Remove';
        } else {
            const p = products.find((x) => Number(x.id) === Number(action.productId));
            label = p ? p.name : 'Item';
            if (title) title.textContent = 'Quantity kam karein';
            if (hint) {
                hint.textContent = action.voidKitchen
                    ? 'Kitchen quantity kam karne ka reason likhein:'
                    : 'Quantity kam karne ka reason likhein:';
            }
            if (confirmBtn) confirmBtn.innerHTML = '<i class="bi bi-check-lg"></i> Confirm';
        }

        const nameEl = $('#rpRemoveItemName');
        if (nameEl) nameEl.textContent = label;

        const input = $('#rpRemoveReason');
        if (input) input.value = '';
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

        const action = pendingChangeAction;
        pendingChangeAction = null;
        getRemoveReasonModal()?.hide();

        if (!action) return;

        try {
            if (action.type === 'remove') {
                await removeCartLine(action.index, reason);
            } else if (action.type === 'dec') {
                changeCartQty(action.productId, -1, reason);
            } else if (action.type === 'setQty') {
                setCartProductQty(action.productId, action.targetQty, reason);
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
        const p = products.find((x) => Number(x.id) === Number(productId));
        if (delta > 0) {
            addOrIncrementProduct(productId);
            return;
        }

        if (!canReduceCartItems) {
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
                canVoidKitchenItems || (canReduceCartItems && qty > locked)
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
        wrap.innerHTML = cart.map((r, i) => {
            const total = lineRowTotal(r, totals, i);
            const locked = Number(r.kitchen_locked_qty) || 0;
            const showRemove = (locked <= 0 && canReduceCartItems) || (locked > 0 && canVoidKitchenItems);
            const kitchenBadge = locked > 0
                ? `<span class="rp-kitchen-pill ${r.kitchen_served ? 'rp-kitchen-pill--served' : 'rp-kitchen-pill--pending'}" title="Kitchen me bheja hua">
                    <i class="bi ${r.kitchen_served ? 'bi-check-circle-fill' : 'bi-fire'}"></i>
                    ${r.kitchen_served ? 'Served' : 'Kitchen'}
                   </span>`
                : '';
            const removeTitle = locked > 0 ? 'Kitchen item — reason required' : 'Remove item';
            const canDec = Number(r.qty) > 0 && (
                canVoidKitchenItems || (canReduceCartItems && Number(r.qty) > locked)
            );
            const noteVal = escHtml(r.notes || '');
            return `<div class="rp-cart-line${locked > 0 ? ' is-kitchen-locked' : ''}" data-cart-index="${i}" data-product-id="${r.product_id}">
                <div class="rp-cl-row">
                    <div class="rp-cl-main">
                        <div class="rp-cl-qty-ctrl" role="group" aria-label="Quantity">
                            <button type="button" class="rp-cl-qty-btn" data-action="cart-dec" data-id="${r.product_id}"${canDec ? '' : ' disabled'} aria-label="Decrease">−</button>
                            <input type="text" inputmode="decimal" class="rp-cl-qty-input" data-id="${r.product_id}" value="${fmtQty(r.qty)}" aria-label="Quantity" autocomplete="off" spellcheck="false">
                            <button type="button" class="rp-cl-qty-btn" data-action="cart-inc" data-id="${r.product_id}" aria-label="Increase">+</button>
                        </div>
                        <span class="rp-cl-name">${escHtml(r.name)}</span>
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
        const orders = isPending ? (boot.pendingBillsDetail || []) : (boot.paidBillsDetail || []);
        billsHead.innerHTML = `
            <div class="rp-bills-head-main">
                <span class="rp-bills-head-title">${isPending ? 'Pending Bills' : 'Paid Bills'}</span>
                <span class="rp-bills-head-count">${orders.length} bill${orders.length === 1 ? '' : 's'}</span>
                ${isPending ? '<button type="button" class="btn btn-sm rp-punch-new-order" id="rpPunchNewOrder"><i class="bi bi-plus-lg me-1"></i>Punch New Order</button>' : ''}
            </div>
            <span class="rp-bills-head-hint">${isPending ? 'Bill kholne ke liye card par click karein.' : (canReopenPaidBill ? 'Receipt ya Reopen ke liye card par action use karein.' : 'Receipt ke liye card par click karein.')}</span>
        `;

        if (isPending) {
            $('#rpPunchNewOrder')?.addEventListener('click', punchNewOrder);
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
        if (search) search.placeholder = 'Search menu…';
        renderAll();
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

    function filterOrdersForSearch(orders) {
        const q = ($('#rpProductSearch')?.value || '').trim().toLowerCase();
        if (!q) return orders;
        return orders.filter((o) => {
            const hay = [
                o.order_no,
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

    function setOrderListMode(mode) {
        const tabPending = $('#rpTabPending');
        const tabPaid = $('#rpTabPaid');
        const tabKitchenVoids = $('#rpTabKitchenVoids');

        if (orderListMode === mode) {
            showMenuPanel();
            return;
        }

        orderListMode = mode;
        $('#rpOrderLinePanel')?.classList.add('d-none');

        tabPending?.classList.toggle('is-active', mode === 'pending');
        tabPaid?.classList.toggle('is-active', mode === 'paid');
        tabKitchenVoids?.classList.toggle('is-active', mode === 'kitchen-voids');
        $('#rpTabMenu')?.classList.remove('is-active');

        if (panelView === 'cart') {
            setPanelView('split');
        }

        const search = $('#rpProductSearch');
        if (search) {
            if (mode === 'pending') search.placeholder = 'Search pending bill…';
            else if (mode === 'paid') search.placeholder = 'Search paid bill…';
            else if (mode === 'kitchen-voids') search.placeholder = 'Search cancelled item…';
            search.value = '';
        }

        if (mode === 'kitchen-voids') {
            loadSessionKitchenVoids().then(() => {
                updateBillsMenuHead();
                renderOrderCards();
            });
            return;
        }

        updateBillsMenuHead();
        renderOrderCards();
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

    function renderOrderCards() {
        const grid = $('#rpMenuGrid');
        if (!grid || !orderListMode) return;

        if (orderListMode === 'kitchen-voids') {
            renderKitchenVoidCards();
            return;
        }

        grid.classList.remove('rp-kitchen-voids-grid');
        updateBillsMenuHead();
        grid.classList.add('rp-bills-grid');

        if (orderListMode === 'pending') {
            const orders = filterOrdersForSearch(boot.pendingBillsDetail || []);
            if (!orders.length) {
                grid.innerHTML = `<div class="rp-empty rp-empty--menu">
                    <span class="rp-empty-icon"><i class="bi bi-hourglass-split"></i></span>
                    <span>${(boot.pendingBillsDetail || []).length ? 'Is search se koi pending bill nahi mili.' : 'Koi pending order nahi.'}</span>
                </div>`;
                return;
            }
            grid.innerHTML = orders.map((o) => {
                const resumeUrl = (routes.resume || '').replace('__ID__', String(o.id));
                return `<a class="rp-order-card rp-order-card--grid" href="${escHtml(resumeUrl)}">
                    <div class="rp-oc-no">${escHtml(o.order_no)}</div>
                    <div class="rp-oc-meta">${escHtml(orderMetaLabel(o))} · ${escHtml(orderMetaDetail(o))}</div>
                    ${orderPunchedByHtml(o)}
                    <div class="rp-oc-meta">${escHtml(fmtMoney(o.grand_total))} · ${o.items_count || 0} items</div>
                    <div class="rp-oc-open">Open bill <i class="bi bi-arrow-right-short"></i></div>
                </a>`;
            }).join('');
            return;
        }

        const paid = filterOrdersForSearch(boot.paidBillsDetail || []);
        if (!paid.length) {
            grid.innerHTML = `<div class="rp-empty rp-empty--menu">
                <span class="rp-empty-icon"><i class="bi bi-check-circle"></i></span>
                <span>${(boot.paidBillsDetail || []).length ? 'Is search se koi paid bill nahi mili.' : 'Aaj koi paid order nahi.'}</span>
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
                <div class="rp-oc-no">${escHtml(o.order_no)}</div>
                <div class="rp-oc-meta">${escHtml(orderMetaLabel(o))} · ${escHtml(orderMetaDetail(o))}</div>
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
        if (autoPaymentAmount && payments.length === 1) {
            payments[0].amount = calcCartTotals().grand;
        }
    }

    let autoPaymentAmount = true;

    function cartItemsForSubmit() {
        syncItemNotesFromDom();
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

    function syncItemNotesFromDom() {
        $$('#rpCartLines .rp-cl-note').forEach((input) => {
            const idx = Number(input.dataset.index);
            if (!Number.isFinite(idx) || !cart[idx]) return;
            cart[idx].notes = String(input.value || '');
        });
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
                alert('Pay sirf cashier kar sakta hai.');
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
        form.querySelector('[name="bill_discount_percent"]').value = String(getBillDiscountPercent());
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

        const networkPrinted = data.order_id && await tryCashierNetworkPrint(data.order_id);

        applyCheckoutSuccess(data);

        if (data.receipt_url) {
            const qs = networkPrinted ? 'noprint=1' : 'autoprint=1';
            window.open(
                data.receipt_url + (data.receipt_url.includes('?') ? '&' : '?') + qs,
                '_blank',
                'noopener,noreferrer'
            );
        }

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
        renderCashSuggestions(grand);
        setCashTendered(grand <= 0 ? 0 : grand, { manual: false });

        const modal = getPayModal();
        if (!modal) {
            submitOrder('checkout');
            return;
        }
        modal.show();
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

        if (orderListMode === 'paid') {
            updateBillsMenuHead();
            renderOrderCards();
        } else {
            setOrderListMode('paid');
        }
    }

    function resetForNewBill() {
        cart.length = 0;
        kitchenVoids = [];
        itemReductions = [];
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

    function buildHoldFormData(sendToKitchen = false) {
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
        formData.set('item_reductions', JSON.stringify(itemReductions));
        formData.set('send_to_kitchen', sendToKitchen ? '1' : '0');
        formData.set('client_grand_total', String(totals.grand));
        formData.set('client_subtotal', String(totals.subtotal));
        formData.set('client_discount_total', String(totals.discount));
        formData.set('client_tax_total', String(totals.tax));
        formData.set('client_service_charge_total', String(totals.serviceCharge || 0));
        return formData;
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
            cart.push({
                product_id: Number(ri.product_id),
                name: ri.name || p?.name || 'Item',
                uom: ri.uom || p?.uom || '',
                qty: parseOrderQty(ri.qty),
                unit_price: Number(ri.unit_price) || (p ? unitPriceForProduct(p, ri.uom || p.uom) : 0),
                tax_percent: Number(ri.tax_percent) || 0,
                notes: ri.notes || '',
                kitchen_served: !!ri.kitchen_served,
                kitchen_pending: !!ri.kitchen_pending,
                kitchen_locked_qty: kitchenLockedFromResume(ri),
            });
        });
        renderAll();
    }

    function setResumeStateFromOrder(order) {
        if (!order?.id) {
            return;
        }
        resumeOrderId = order.id;
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
                window.setTimeout(() => iframe.remove(), 1500);
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
                }, 350);
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

    async function printKitchenSlip(orderId) {
        // Try direct network printing first: each product routes to its department's printer.
        const netUrl = (routes.kitchenPrint || '').replace('__ID__', String(orderId));
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

                if (res.ok && data.ok) {
                    const failed = (data.results || []).filter((r) => !r.ok);
                    if (failed.length) {
                        alert('Kuch printers par print nahi hua:\n' +
                            failed.map((r) => `• ${r.department}: ${r.error || 'error'}`).join('\n'));
                    }
                    return;
                }

                // fallback === true means no department printer configured → use browser print.
                if (!data.fallback) {
                    const failed = (data.results || []).filter((r) => !r.ok);
                    if (failed.length) {
                        alert('Network print fail hua (browser print try kar rahe hain):\n' +
                            failed.map((r) => `• ${r.department}: ${r.error || 'error'}`).join('\n'));
                    } else if (data.message) {
                        alert(data.message);
                    }
                }
            } catch (e) {
                // Ignore and fall back to browser print below.
            }
        }

        return browserPrintKitchenSlip(orderId);
    }

    async function tryCashierNetworkPrint(orderId) {
        const url = (routes.cashierPrint || '').replace('__ID__', String(orderId));
        if (!url || !csrf) return false;
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                },
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.ok) return true;
            if (!data.fallback && data.message) alert(data.message);
            return false;
        } catch (e) {
            return false;
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
        kitchenVoids = [];
        itemReductions = [];
        resetForNewBill();
    }

    async function saveResumedDraftChanges(sendToKitchen = false) {
        if (!resumeOrderId) {
            return null;
        }

        return enqueueResumeSave(async () => {
            setCartSaving(true);
            try {
                if (!cart.length) {
                    await discardResumedDraft();
                    return null;
                }

                const formData = buildHoldFormData(sendToKitchen);
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
                    return null;
                }

                if (!res.ok) {
                    throw new Error(errMsg);
                }

                const hadKitchenVoids = kitchenVoids.length > 0;
                if (data.order) {
                    upsertPendingBill(data.order, true);
                    reloadCartFromOrder(data.order);
                    setResumeStateFromOrder(data.order);
                    if (data.order.table_id) {
                        setTableBoardStatus(data.order.table_id, 'occupied');
                    }
                }
                kitchenVoids = [];
                itemReductions = [];
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
        if (resumeOrderId) {
            await saveResumedDraftChanges();
            return resumeOrderId;
        }

        const formData = buildHoldFormData();
        if (!formData) {
            throw new Error('Bill print ke liye pehle item add karein.');
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
            throw new Error(data.message || validationMsg || 'Order save nahi ho saki.');
        }

        const orderId = data.order?.id;
        if (!orderId) {
            throw new Error('Order save ho gaya lekin print ke liye ID nahi mili.');
        }

        resumeOrderId = orderId;
        const form = $('#rpSubmitForm');
        if (form) {
            form.querySelector('[name="resume_order_id"]').value = String(orderId);
        }
        if (data.order) {
            upsertPendingBill(data.order, !!data.updated);
        }

        let badge = document.querySelector('.rp-badge-order');
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'badge rp-badge-order';
            $('#rpTabMenu')?.parentElement?.prepend(badge);
        }
        badge.textContent = data.order.order_no || String(orderId);

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
            if (await tryCashierNetworkPrint(orderId)) {
                return;
            }
            const base = (routes.receiptUnpaid || '').replace('__ID__', String(orderId));
            if (!base) {
                throw new Error('Print route missing.');
            }
            window.open(`${base}?autoprint=1`, '_blank', 'noopener,noreferrer');
        } catch (e) {
            alert(e.message || 'Unpaid bill print nahi ho saki.');
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    async function submitHoldOrder() {
        if (!prepareSubmit('hold')) return;

        const holdBtn = $('#rpHoldBtn');
        if (holdBtn) holdBtn.disabled = true;
        try {
            if (resumeOrderId) {
                await saveResumedDraftChanges(false);
                resetForNewBill();
                return;
            }

            const formData = buildHoldFormData(false);
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

            resetForNewBill();
        } catch (e) {
            alert(e.message || 'Hold failed.');
        } finally {
            if (holdBtn) holdBtn.disabled = false;
        }
    }

    async function submitKitchenPrint() {
        if (!cart.length) {
            alert('Pehle item add karein.');
            return;
        }
        if (!prepareSubmit('hold')) return;

        const kitchenBtn = $('#rpKitchenPrintBtn');
        if (kitchenBtn) kitchenBtn.disabled = true;
        try {
            let order = null;
            if (resumeOrderId) {
                order = await saveResumedDraftChanges(true);
            } else {
                const formData = buildHoldFormData(true);
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
                    setResumeStateFromOrder(order);
                    if (order.table_id) {
                        setTableBoardStatus(order.table_id, 'occupied');
                    }
                }
            }

            const orderId = order?.id || resumeOrderId;
            if (!orderId) {
                throw new Error('Order save nahi ho saki.');
            }
            await printKitchenSlip(orderId);
        } catch (e) {
            alert(e.message || 'Kitchen print failed.');
        } finally {
            if (kitchenBtn) kitchenBtn.disabled = false;
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
            if (orderListMode) renderOrderCards();
            else renderMenuGrid();
        });
        $('#rpMenuCats')?.addEventListener('click', (e) => {
            const btn = e.target.closest('.rp-menu-cat');
            if (!btn) return;
            setMenuCategory(btn.dataset.catId || null);
        });
        $('#rpMenuGrid')?.addEventListener('click', (e) => {
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
        $('#rpPrintUnpaidBtn')?.addEventListener('click', () => printUnpaidBill());
        $('#rpWhatsappBtn')?.addEventListener('click', () => openDeliveryWhatsapp());
        $('#rpPayBtn')?.addEventListener('click', () => openPayModal());
        $('#rpPayModalConfirm')?.addEventListener('click', () => confirmPayModal());
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
            togglePanelView('menu');
        });
        $('#rpTabCart')?.addEventListener('click', () => togglePanelView('cart'));
        $('#rpToggleCartView')?.addEventListener('click', () => togglePanelView('cart'));
        $('#rpBillDiscount')?.addEventListener('input', () => {
            if (ownerDiscountActive) {
                const raw = Number($('#rpBillDiscount')?.value || 0);
                if (raw !== 100) {
                    clearOwnerDiscount(false);
                }
            }
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
        $('#rpRemoveReason')?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                confirmRemoveWithReason();
            }
        });
        $('#rpRemoveReasonModal')?.addEventListener('hidden.bs.modal', () => {
            pendingChangeAction = null;
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
            if (Array.isArray(data.table_board)) {
                applyTableBoard(data.table_board);
            }
        } catch (_) { /* ignore */ }
    }

    function init() {
        if (settings.resume_service_type) {
            setServiceType(settings.resume_service_type);
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
                const discInput = $('#rpBillDiscount');
                if (discInput) {
                    discInput.value = '100';
                    discInput.readOnly = true;
                }
            }
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
        setInterval(pollSync, 20000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
