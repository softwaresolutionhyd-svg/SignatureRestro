(() => {
    const boot = window.ORDER_TAKER_BOOTSTRAP || {};
    const products = boot.products || [];
    const menuCategories = boot.menuCategories || [];
    const settings = boot.settings || {};
    const routes = boot.routes || {};
    const serviceTypeLabels = boot.serviceTypeLabels || {
        dine_in: 'Dine-in',
        takeaway: 'Takeaway',
        delivery: 'Delivery',
    };
    const posTaxMode = settings.tax_mode || 'line';
    const posDefaultLineTax = Number(settings.default_tax_rate || 0);
    const posServiceChargeEnabled = settings.service_charge_enabled === true;
    const posServiceChargePercent = posServiceChargeEnabled ? Number(settings.service_charge_percent || 0) : 0;
    const posTablesEnabled = settings.enable_tables !== false;
    const canVoidKitchenItems = boot.canVoidKitchenItems === true;
    // Kitchen print se pehle delete/reduce; print ke baad sirf admin/manager.
    const canReduceCartItems = true;

    let cart = [];
    let selectedMenuCategoryId = null;
    let panelView = 'split';
    let editOrderId = boot.resumeOrderId || null;
    let selectedTableId = boot.resumeTableId || boot.startTableId || null;
    let selectedTableName = boot.resumeTableName || null;
    let pendingMode = !!editOrderId;
    let moveTableOrderId = null;
    let boardTab = 'tables';

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
        return $('#otServiceType')?.value || 'dine_in';
    }

    function serviceChargeApplies() {
        return posServiceChargeEnabled && selectedServiceType() === 'dine_in';
    }

    function serviceTypeLabel(type) {
        return serviceTypeLabels[type] || serviceTypeLabels.dine_in || 'Dine-in';
    }

    function setServiceType(type) {
        const input = $('#otServiceType');
        if (input) input.value = type;
        $$('.rp-service-type').forEach((btn) => {
            const active = btn.dataset.type === type;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        syncServiceDetailPanels();
        updateOrderHeader();
        renderTotals();
        if (type === 'takeaway') {
            setTimeout(() => $('#otTakeawayContact')?.focus(), 0);
        }
    }

    function syncServiceDetailPanels() {
        const type = selectedServiceType();
        $$('.rp-service-panel').forEach((panel) => {
            panel.classList.toggle('d-none', panel.dataset.service !== type);
        });
        const chip = $('#otSelectedTableChip');
        if (chip && selectedTableName) {
            chip.textContent = `Table ${selectedTableName}`;
        }
    }

    function lockServiceTypeFields(locked) {
        $$('#otServiceTypes .rp-service-type').forEach((btn) => {
            btn.disabled = locked;
        });
    }

    function productById(id) {
        return products.find((p) => Number(p.id) === Number(id));
    }

    function isProductVisible(p) {
        return !!p.for_pos;
    }

    function unitPriceForProduct(p, uom) {
        const row = (p.uoms || []).find((u) => String(u.uom).toLowerCase() === String(uom).toLowerCase());
        const factor = row ? Number(row.factor) : (String(p.uom).toLowerCase() === String(uom).toLowerCase() ? 1 : 0);
        return factor > 0 ? Math.round(Number(p.price) * factor * 100) / 100 : Number(p.price);
    }

    function defaultUom(p) {
        return (p.uoms && p.uoms[0]) ? p.uoms[0].uom : p.uom;
    }

    function cartQtyForProduct(productId) {
        return cart
            .filter((r) => Number(r.product_id) === Number(productId))
            .reduce((s, r) => s + Number(r.qty), 0);
    }

    function cartLockedQtyForProduct(productId) {
        return cart
            .filter((r) => Number(r.product_id) === Number(productId))
            .reduce((s, r) => s + (Number(r.kitchen_locked_qty) || 0), 0);
    }

    function calcCartTotals() {
        let subtotal = 0;
        let tax = 0;
        cart.forEach((r) => {
            const lineSub = Number(r.qty) * Number(r.unit_price);
            const lineTax = posTaxMode === 'line' ? lineSub * (posDefaultLineTax / 100) : 0;
            subtotal += lineSub;
            tax += lineTax;
        });
        if (posTaxMode === 'bill') {
            tax = subtotal * (posDefaultLineTax / 100);
        }
        subtotal = Math.round(subtotal * 100) / 100;
        tax = Math.round(tax * 100) / 100;
        const serviceCharge = serviceChargeApplies() && posServiceChargePercent > 0
            ? Math.round(subtotal * posServiceChargePercent / 100 * 100) / 100
            : 0;
        const grand = Math.round((subtotal + tax + serviceCharge) * 100) / 100;
        return { subtotal, tax, serviceCharge, grand };
    }

    function lineRowTotal(r) {
        const lineSub = Number(r.qty) * Number(r.unit_price);
        const lineTax = posTaxMode === 'line' ? lineSub * (posDefaultLineTax / 100) : 0;
        return Math.round((lineSub + lineTax) * 100) / 100;
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

    function productMatchesMenuCategory(p) {
        if (!selectedMenuCategoryId) return true;
        return Number(p.category_id) === Number(selectedMenuCategoryId);
    }

    function renderMenuCategories() {
        const wrap = $('#otMenuCats');
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

    function renderMenuGrid() {
        const grid = $('#otMenuGrid');
        const q = ($('#otProductSearch')?.value || '').trim().toLowerCase();
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
        const wrap = $('#otCartLines');
        if (!wrap) return;
        if (!cart.length) {
            wrap.innerHTML = `<div class="rp-empty">
                <span class="rp-empty-icon"><i class="bi bi-bag"></i></span>
                <span>Cart khali hai — menu se item add karein.</span>
            </div>`;
            return;
        }
        wrap.innerHTML = cart.map((r, i) => {
            const total = lineRowTotal(r);
            const locked = Number(r.kitchen_locked_qty) || 0;
            const showRemove = (locked <= 0 && canReduceCartItems) || (locked > 0 && canVoidKitchenItems);
            const kitchenBadge = locked > 0
                ? `<span class="rp-kitchen-pill ${r.kitchen_served ? 'rp-kitchen-pill--served' : 'rp-kitchen-pill--pending'}" title="Kitchen me bheja hua">
                    <i class="bi ${r.kitchen_served ? 'bi-check-circle-fill' : 'bi-fire'}"></i>
                    ${r.kitchen_served ? 'Served' : 'Kitchen'}
                   </span>`
                : '';
            const removeTitle = locked > 0 ? 'Kitchen item — remove nahi ho sakta' : 'Remove item';
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
        const { subtotal, grand } = calcCartTotals();
        const itemQty = cart.reduce((s, r) => s + Number(r.qty), 0);
        const el = (id, v) => { const n = $(id); if (n) n.textContent = String(v); };
        el('#otSumItems', cart.length ? `${fmtQty(itemQty)} (${cart.length})` : '0');
        el('#otSumSubtotal', fmtMoney(subtotal));
        el('#otSumGrand', fmtMoney(grand));
        el('#otBarTotal', fmtMoney(grand));
        el('#otCartCount', String(cart.length));
        el('#otCartTabCount', String(cart.length));
    }

    function renderAll() {
        renderMenuGrid();
        renderCart();
        renderTotals();
    }

    function addOrIncrementProduct(productId) {
        const p = productById(productId);
        if (!p || !isProductVisible(p)) return;
        const uom = defaultUom(p);
        const existing = cart.find((r) => Number(r.product_id) === Number(productId) && String(r.uom) === String(uom));
        if (existing) {
            existing.qty = Math.round((Number(existing.qty) + 1) * 1000) / 1000;
            existing.unit_price = unitPriceForProduct(p, existing.uom);
        } else {
            cart.push({
                product_id: p.id,
                name: p.name,
                uom,
                qty: 1,
                unit_price: unitPriceForProduct(p, uom),
                notes: '',
                kitchen_served: false,
                kitchen_pending: false,
                kitchen_locked_qty: 0,
            });
        }
        renderAll();
    }

    function increaseProductQtyBy(productId, addQty) {
        const p = productById(productId);
        if (!p || addQty <= 0) return;
        const uom = defaultUom(p);
        const existing = cart.find((r) => Number(r.product_id) === Number(productId) && String(r.uom) === String(uom));
        if (existing) {
            existing.qty = Math.round((Number(existing.qty) + addQty) * 1000) / 1000;
            existing.unit_price = unitPriceForProduct(p, existing.uom);
        } else {
            cart.push({
                product_id: p.id,
                name: p.name,
                uom,
                qty: Math.round(addQty * 1000) / 1000,
                unit_price: unitPriceForProduct(p, uom),
                notes: '',
                kitchen_served: false,
                kitchen_pending: false,
                kitchen_locked_qty: 0,
            });
        }
    }

    function addProductToCart(productId, delta) {
        if (delta > 0) {
            addOrIncrementProduct(productId);
            return;
        }
        adjustProductQty(productId, delta);
    }

    function adjustProductQty(productId, delta) {
        const p = productById(productId);
        const locked = cartLockedQtyForProduct(productId);
        const totalQty = cartQtyForProduct(productId);
        const next = Math.round((totalQty + delta) * 1000) / 1000;
        if (next < locked) {
            alert('Kitchen print ke baad quantity kam nahi ho sakti.');
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

    function setCartProductQty(productId, targetQty) {
        const current = cartQtyForProduct(productId);
        const next = Math.round(Number(targetQty) * 1000) / 1000;
        if (!Number.isFinite(next)) {
            renderCart();
            return;
        }
        if (next <= 0) {
            adjustProductQty(productId, -current);
            return;
        }
        const delta = Math.round((next - current) * 1000) / 1000;
        if (Math.abs(delta) < 0.0005) return;
        if (delta > 0) {
            increaseProductQtyBy(productId, delta);
            renderAll();
            return;
        }
        adjustProductQty(productId, delta);
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

        const locked = cartLockedQtyForProduct(productId);
        if (next < locked) {
            alert('Kitchen print ke baad quantity kam nahi ho sakti.');
            renderCart();
            return;
        }

        setCartProductQty(productId, next);
    }

    function removeCartLine(index) {
        const row = cart[index];
        if (!row) return;
        const locked = Number(row.kitchen_locked_qty) || 0;
        if (locked > 0 && !canVoidKitchenItems) {
            alert('Kitchen print ke baad item delete nahi ho sakta.');
            return;
        }
        cart.splice(index, 1);
        renderAll();
    }

    function syncItemNotesFromDom() {
        $$('#otCartLines .rp-cl-note').forEach((input) => {
            const idx = Number(input.dataset.index);
            if (!Number.isFinite(idx) || !cart[idx]) return;
            cart[idx].notes = String(input.value || '');
        });
    }

    function isNarrowScreen() {
        // Tablets (incl. landscape ~1024–1366) also need Menu/Cart tabs, not desktop split.
        return window.matchMedia('(max-width: 1399.98px)').matches;
    }

    function setPanelView(view) {
        const app = document.querySelector('.order-taker-pos-app');
        if (!app) return;

        let next = view;
        if (next !== 'menu' && next !== 'cart' && next !== 'split') {
            next = isNarrowScreen() ? 'menu' : 'split';
        }
        if (next === 'split' && isNarrowScreen()) {
            next = 'menu';
        }

        panelView = next;
        app.classList.remove('rp-view-menu', 'rp-view-cart');
        if (next === 'menu') app.classList.add('rp-view-menu');
        if (next === 'cart') app.classList.add('rp-view-cart');

        const tabMenu = document.getElementById('otTabMenu');
        const tabCart = document.getElementById('otTabCart');
        tabMenu?.classList.toggle('is-active', next !== 'cart');
        tabCart?.classList.toggle('is-active', next === 'cart');

        const expandBtn = document.getElementById('otToggleCartView');
        const icon = expandBtn?.querySelector('i');
        if (icon) icon.className = next === 'cart' ? 'bi bi-layout-sidebar-reverse' : 'bi bi-arrows-fullscreen';
        if (expandBtn) {
            expandBtn.title = next === 'cart' ? 'Back to menu' : 'Open cart';
        }
    }

    function preferredPanelView(force) {
        if (force === 'menu' || force === 'cart' || force === 'split') {
            if (isNarrowScreen() && force === 'split') return 'menu';
            return force;
        }
        return isNarrowScreen() ? 'menu' : 'split';
    }

    function showOrderScreen() {
        document.querySelector('.order-taker-pos-app')?.classList.add('ot-screen-order');
        $('#otOrderScreen')?.classList.remove('d-none');
        updateOrderHeader();
        syncServiceFields();
        setPanelView(preferredPanelView());
        renderAll();
    }

    function showTableBoard() {
        document.querySelector('.order-taker-pos-app')?.classList.remove('ot-screen-order');
        $('#otOrderScreen')?.classList.add('d-none');
        setBoardTab('tables');
        window.history.replaceState({}, '', routes.index || '/order-taker');
    }

    function setBoardTab(nextTab) {
        boardTab = nextTab === 'pending' ? 'pending' : 'tables';
        const isPending = boardTab === 'pending';
        $('#otBoardTabTables')?.classList.toggle('is-active', !isPending);
        $('#otBoardTabTables')?.setAttribute('aria-selected', isPending ? 'false' : 'true');
        $('#otBoardTabPending')?.classList.toggle('is-active', isPending);
        $('#otBoardTabPending')?.setAttribute('aria-selected', isPending ? 'true' : 'false');
        $('#otBoardPanelTables')?.classList.toggle('d-none', isPending);
        $('#otBoardPanelPending')?.classList.toggle('d-none', !isPending);
        $('#otAreaFilters')?.classList.toggle('d-none', isPending);
        $('.ot-table-legend')?.classList.toggle('d-none', isPending);
    }

    function updateOrderHeader() {
        const label = $('#otTableLabel');
        const type = pendingMode ? (boot.resumeServiceType || selectedServiceType()) : selectedServiceType();
        if (label) {
            if (type === 'dine_in' && selectedTableName) {
                label.textContent = `${serviceTypeLabel(type)} · Table ${selectedTableName}`;
            } else {
                label.textContent = serviceTypeLabel(type);
            }
        }
        const badge = $('#otOrderNoBadge');
        if (badge) {
            if (pendingMode && boot.resumeOrderNo) {
                badge.textContent = boot.resumeOrderNo;
                badge.classList.remove('d-none');
            } else {
                badge.classList.add('d-none');
            }
        }
        const sendLabel = $('#otSendBtnLabel');
        if (sendLabel) sendLabel.textContent = pendingMode ? 'Update Bill' : 'Send to Kitchen';
    }

    function syncServiceFields() {
        lockServiceTypeFields(pendingMode);
        if (pendingMode) {
            setServiceType(boot.resumeServiceType || 'dine_in');
            if (boot.resumeServiceType === 'takeaway') {
                if (boot.resumeRoomNo && $('#otTakeawayContact')) $('#otTakeawayContact').value = boot.resumeRoomNo;
                else if (boot.resumeGuestName && $('#otTakeawayContact')) $('#otTakeawayContact').value = boot.resumeGuestName;
            } else {
                if (boot.resumeGuestName && $('#otDeliveryName')) $('#otDeliveryName').value = boot.resumeGuestName;
                if (boot.resumeRoomNo && $('#otDeliveryPhone')) $('#otDeliveryPhone').value = boot.resumeRoomNo;
                if (boot.resumeOrderNotes && $('#otDeliveryAddress')) $('#otDeliveryAddress').value = boot.resumeOrderNotes;
            }
            if ($('#otTableNo') && boot.resumeGuestName) $('#otTableNo').value = boot.resumeGuestName;
            if ($('#otBillKitchenNotes') && boot.resumeKitchenNotes !== undefined) {
                $('#otBillKitchenNotes').value = boot.resumeKitchenNotes || '';
            }
            return;
        }
        if ($('#otBillKitchenNotes')) $('#otBillKitchenNotes').value = '';
        setServiceType(boot.startServiceType || boot.defaultServiceType || 'dine_in');
        syncServiceDetailPanels();
    }

    function resolveTableName(tableId) {
        const row = (boot.tableBoard || []).find((t) => Number(t.id) === Number(tableId));
        return row ? row.name : String(tableId);
    }

    function loadResumeCart() {
        const items = boot.resumeItems || [];
        cart = items.map((r) => ({
            product_id: r.product_id,
            name: r.name || productById(r.product_id)?.name || '',
            uom: r.uom,
            qty: Number(r.qty),
            unit_price: Number(r.unit_price),
            notes: r.notes || '',
            kitchen_served: !!r.kitchen_served,
            kitchen_pending: r.kitchen_pending !== false,
            kitchen_locked_qty: Number(r.kitchen_locked_qty) || (r.kitchen_served || r.kitchen_pending || r.kitchen_printed ? Number(r.qty) : 0),
        }));
    }

    function startNewOrder(tableId, tableName, serviceType) {
        editOrderId = null;
        pendingMode = false;
        selectedTableId = tableId || null;
        selectedTableName = tableName || null;
        cart = [];
        boot.resumeOrderNo = null;
        boot.resumeKitchenNotes = null;
        boot.startServiceType = serviceType || 'dine_in';
        showOrderScreen();
        setServiceType(serviceType || 'dine_in');
    }

    function startServiceOrder(serviceType) {
        startNewOrder(null, null, serviceType);
    }

    function startEditOrder(orderId, tableId, tableName) {
        if (Number(boot.resumeOrderId) === Number(orderId) && Array.isArray(boot.resumeItems) && boot.resumeItems.length) {
            selectedTableId = tableId || boot.resumeTableId;
            selectedTableName = tableName || boot.resumeTableName || resolveTableName(selectedTableId);
            loadResumeCart();
            showOrderScreen();
            return;
        }
        openOrderFast(orderId, tableId, tableName);
    }

    async function openOrderFast(orderId, tableId, tableName) {
        const url = (routes.orderData || '').replace('__ID__', String(orderId));
        if (!url) {
            window.location.href = `${routes.index}?order_id=${orderId}`;
            return;
        }
        try {
            const res = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.ok || !data.order) {
                window.location.href = `${routes.index}?order_id=${orderId}`;
                return;
            }
            const o = data.order;
            editOrderId = o.id;
            pendingMode = true;
            boot.resumeOrderId = o.id;
            boot.resumeOrderNo = o.order_no;
            boot.resumeTableId = o.table_id;
            boot.resumeTableName = o.table_name || tableName || null;
            boot.resumeServiceType = o.service_type || 'dine_in';
            boot.resumeGuestName = o.guest_name || '';
            boot.resumeRoomNo = o.room_no || '';
            boot.resumeOrderNotes = o.order_notes || '';
            boot.resumeKitchenNotes = o.kitchen_notes || '';
            boot.resumeItems = o.items || [];
            selectedTableId = o.table_id || tableId || null;
            selectedTableName = o.table_name || tableName || resolveTableName(selectedTableId);
            loadResumeCart();
            showOrderScreen();
            window.history.replaceState({}, '', `${routes.index}?order_id=${o.id}`);
        } catch (e) {
            window.location.href = `${routes.index}?order_id=${orderId}`;
        }
    }

    function areaKeyForTable(t) {
        if (t.sitting_area_id != null) return String(t.sitting_area_id);
        return t.sitting_area_name ? `name:${t.sitting_area_name}` : 'none';
    }

    function renderTableBoardFromData(board, groups) {
        const grid = $('#otTableGrid');
        if (!grid) return;
        boot.tableBoard = Array.isArray(board) ? board : (boot.tableBoard || []);

        let areas = Array.isArray(groups) && groups.length
            ? groups
            : null;
        if (!areas) {
            const map = {};
            (boot.tableBoard || []).forEach((t) => {
                const key = areaKeyForTable(t);
                if (!map[key]) {
                    map[key] = {
                        id: t.sitting_area_id ?? null,
                        name: t.sitting_area_name || 'Other',
                        tables: [],
                    };
                }
                map[key].tables.push(t);
            });
            areas = Object.values(map);
        }

        const activeKey = $('#otAreaFilters .ot-area-filter-btn.is-active')?.dataset.areaKey || 'all';
        const filters = $('#otAreaFilters');
        if (filters && areas.length > 1) {
            filters.innerHTML = [
                `<button type="button" class="ot-area-filter-btn${activeKey === 'all' ? ' is-active' : ''}" data-area-key="all" role="tab" aria-selected="${activeKey === 'all' ? 'true' : 'false'}">All</button>`,
                ...areas.map((area) => {
                    const key = area.id != null ? String(area.id) : `name:${area.name}`;
                    const selected = activeKey === key;
                    return `<button type="button" class="ot-area-filter-btn${selected ? ' is-active' : ''}" data-area-key="${escHtml(key)}" role="tab" aria-selected="${selected ? 'true' : 'false'}">${escHtml(area.name)}</button>`;
                }),
            ].join('');
        }

        grid.innerHTML = areas.map((area) => {
            const key = area.id != null ? String(area.id) : `name:${area.name}`;
            const hide = activeKey !== 'all' && activeKey !== key;
            const tables = area.tables || [];
            return `<section class="ot-sitting-area${hide ? ' d-none' : ''}" data-area-key="${escHtml(key)}">
                <h3 class="ot-sitting-area-title">${escHtml(area.name)}</h3>
                <div class="ot-table-grid">
                    ${tables.map((t) => {
                        const occupied = t.status === 'occupied';
                        return `<button type="button"
                                class="ot-table-box ot-table-box--${escHtml(t.status)}"
                                data-table-id="${t.id}"
                                data-table-name="${escHtml(t.name)}"
                                data-status="${escHtml(t.status)}"
                                data-order-id="${t.order_id || ''}"
                                data-amendable="${t.amendable ? '1' : '0'}">
                            <span class="ot-table-shape" aria-hidden="true">
                                <span class="ot-chair ot-chair--n"></span>
                                <span class="ot-chair ot-chair--e"></span>
                                <span class="ot-chair ot-chair--s"></span>
                                <span class="ot-chair ot-chair--w"></span>
                                <span class="ot-table-top">
                                    <span class="ot-table-box-no">${escHtml(t.name)}</span>
                                </span>
                            </span>
                            ${occupied
                                ? `<span class="ot-table-box-meta">${escHtml(t.order_no || '')}</span>
                                   <span class="ot-table-box-meta">${Number(t.items_count || 0)} items</span>
                                   ${t.occupied_at ? `<span class="ot-table-box-meta ot-table-timer" data-occupied-at="${escHtml(t.occupied_at)}" title="Order punch ke baad ka time">00:00</span>` : ''}`
                                : `<span class="ot-table-box-meta ot-table-box-meta--free">Available</span>`}
                        </button>`;
                    }).join('')}
                </div>
            </section>`;
        }).join('');

        tickOccupiedTimers();
    }

    function renderPendingOrdersFromData(orders) {
        const grid = $('#otPendingOrdersGrid');
        if (!grid) return;
        boot.allOrders = Array.isArray(orders) ? orders : [];
        const count = boot.allOrders.length;
        const countEl = $('#otPendingCount');
        if (countEl) countEl.textContent = String(count);
        const headCount = document.querySelector('#otBoardPanelPending .rp-bills-head-count');
        if (headCount) headCount.textContent = `${count} bill${count === 1 ? '' : 's'}`;

        if (!count) {
            grid.innerHTML = `<div class="rp-bills-empty"><i class="bi bi-inbox"></i><span>Koi pending order nahi.</span></div>`;
            return;
        }

        const currency = boot.currency || 'Rs.';
        grid.innerHTML = boot.allOrders.map((mo) => {
            const canOpen = !!mo.amendable;
            const canMove = mo.service_type === 'dine_in' && mo.table_id;
            return `<div class="rp-order-card rp-order-card--grid rp-order-card--pending-wrap${canOpen ? '' : ' opacity-75'}"
                     data-order-id="${mo.id}"
                     data-order-no="${escHtml(mo.order_no)}"
                     data-service-type="${escHtml(mo.service_type || '')}"
                     data-table-id="${mo.table_id || ''}"
                     data-amendable="${canOpen ? '1' : '0'}">
                <button type="button"
                        class="rp-order-card-link text-start bg-transparent border-0 w-100"
                        data-action="open-order"
                        data-order-id="${mo.id}"
                        data-amendable="${canOpen ? '1' : '0'}"
                        ${canOpen ? '' : 'disabled'}>
                    ${mo.table_name ? `<div class="rp-oc-table">Table ${escHtml(mo.table_name)}</div>` : ''}
                    <div class="rp-oc-no">
                        ${escHtml(mo.order_no)}
                        ${mo.is_split ? `<span class="rp-oc-split-icon" title="Split bill"><i class="bi bi-scissors" aria-hidden="true"></i></span>` : ''}
                    </div>
                    <div class="rp-oc-meta">${escHtml(mo.service_label || 'Order')}</div>
                    <div class="rp-oc-by">by: ${escHtml(mo.punched_by || '—')}</div>
                    <div class="rp-oc-meta">${escHtml(currency)}${Math.round(Number(mo.grand_total || 0))} · ${Number(mo.items_count || 0)} items</div>
                    <div class="rp-oc-meta">${escHtml(mo.punched_at || '')}</div>
                    <div class="rp-oc-open">Open order <i class="bi bi-arrow-right-short"></i></div>
                </button>
                ${canMove ? `<div class="rp-oc-move-wrap">
                    <button type="button" class="btn btn-sm rp-oc-move-table"
                        data-action="move-table"
                        data-order-id="${mo.id}"
                        data-order-no="${escHtml(mo.order_no)}"
                        data-table-id="${mo.table_id}">
                        <i class="bi bi-arrow-left-right"></i> Move Table
                    </button>
                </div>` : ''}
            </div>`;
        }).join('');
    }

    function applyBoardPayload(data) {
        if (!data) return;
        if (data.table_board) {
            renderTableBoardFromData(data.table_board, data.table_board_groups);
        }
        if (data.all_orders) {
            renderPendingOrdersFromData(data.all_orders);
        }
    }

    async function submitOrder() {
        if (!cart.length) {
            alert('Kam az kam aik item add karein.');
            return;
        }

        const serviceType = pendingMode ? (boot.resumeServiceType || 'dine_in') : selectedServiceType();

        if (!pendingMode) {
            if (serviceType === 'dine_in') {
                if (posTablesEnabled) {
                    if (!selectedTableId) {
                        alert('Table select karein.');
                        showTableBoard();
                        return;
                    }
                } else if (!($('#otTableNo')?.value || '').trim()) {
                    alert('Table No. enter karein.');
                    $('#otTableNo')?.focus();
                    return;
                }
            } else if (serviceType === 'delivery') {
                if (!($('#otDeliveryName')?.value || '').trim()) {
                    alert('Customer name likhein.');
                    $('#otDeliveryName')?.focus();
                    return;
                }
                if (!($('#otDeliveryPhone')?.value || '').trim()) {
                    alert('Phone number likhein.');
                    $('#otDeliveryPhone')?.focus();
                    return;
                }
            } else if (serviceType === 'takeaway') {
                if (!($('#otTakeawayContact')?.value || '').trim()) {
                    alert('Contact No. likhein.');
                    $('#otTakeawayContact')?.focus();
                    return;
                }
            }
        }

        syncItemNotesFromDom();

        const itemsPayload = cart.map((r) => ({
            product_id: r.product_id,
            uom: r.uom,
            qty: Number(r.qty),
            notes: r.notes || '',
        }));

        let guestName = '';
        let roomNo = '';
        let orderNotes = '';
        let tableId = '';

        if (pendingMode) {
            guestName = boot.resumeGuestName || '';
            roomNo = boot.resumeRoomNo || '';
            orderNotes = boot.resumeOrderNotes || '';
            tableId = boot.resumeTableId ? String(boot.resumeTableId) : '';
        } else if (serviceType === 'dine_in') {
            tableId = selectedTableId ? String(selectedTableId) : '';
            if (!posTablesEnabled) {
                guestName = ($('#otTableNo')?.value || '').trim();
            }
        } else if (serviceType === 'delivery') {
            guestName = ($('#otDeliveryName')?.value || '').trim();
            roomNo = ($('#otDeliveryPhone')?.value || '').trim();
            orderNotes = ($('#otDeliveryAddress')?.value || '').trim();
        } else if (serviceType === 'takeaway') {
            const contact = ($('#otTakeawayContact')?.value || '').trim();
            guestName = contact;
            roomNo = contact;
        }

        const kitchenNotes = ($('#otBillKitchenNotes')?.value || '').trim();
        const payload = {
            customer_type: 'mess_use',
            service_type: serviceType,
            guest_name: guestName,
            room_no: roomNo,
            order_notes: orderNotes,
            table_id: tableId || null,
            kitchen_notes: kitchenNotes,
            items: itemsPayload,
        };

        let url = routes.store;
        let method = 'POST';
        if (pendingMode && editOrderId) {
            url = (routes.update || '').replace('__ID__', String(editOrderId));
            method = 'PUT';
        }

        const sendBtn = $('#otSendBtn');
        const confirmBtn = $('#otConfirmBillSubmit');
        if (sendBtn) sendBtn.disabled = true;
        if (confirmBtn) confirmBtn.disabled = true;

        try {
            const body = new URLSearchParams();
            Object.entries(payload).forEach(([k, v]) => {
                if (k === 'items') {
                    body.set('items', JSON.stringify(v));
                } else if (v !== null && v !== undefined) {
                    body.set(k, String(v));
                }
            });
            if (method === 'PUT') {
                body.set('_method', 'PUT');
            }
            body.set('_token', boot.csrf || '');

            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': boot.csrf || '',
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: body.toString(),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data.ok === false) {
                const msg = data.message
                    || (data.errors && Object.values(data.errors).flat()[0])
                    || 'Order save fail ho gayi.';
                alert(msg);
                return;
            }

            applyBoardPayload(data);
            editOrderId = null;
            pendingMode = false;
            cart = [];
            boot.resumeOrderId = null;
            boot.resumeItems = [];
            showTableBoard();
            if (data.message) {
                // Soft toast via alert would block — brief flash on board
                const board = $('#otTableBoard');
                if (board) {
                    let flash = board.querySelector('.ot-ajax-flash');
                    if (!flash) {
                        flash = document.createElement('div');
                        flash.className = 'alert alert-success py-2 mx-3 mt-2 mb-0 ot-ajax-flash';
                        board.insertBefore(flash, board.children[1] || null);
                    }
                    flash.textContent = data.message;
                    setTimeout(() => flash.remove(), 3500);
                }
            }
        } catch (err) {
            alert('Network error — order save nahi hui. Dubara try karein.');
        } finally {
            if (sendBtn) sendBtn.disabled = false;
            if (confirmBtn) confirmBtn.disabled = false;
        }
    }

    function renderMoveTableModal(currentTableId) {
        const tabsWrap = $('#otMoveTableAreaTabs');
        const body = $('#otMoveTableBody');
        if (!tabsWrap || !body) return;

        const areaMap = {};
        (boot.tableBoard || []).forEach((t) => {
            const key = t.sitting_area_id != null ? String(t.sitting_area_id) : 'none';
            const name = t.sitting_area_name || 'Other';
            if (!areaMap[key]) areaMap[key] = { name, tables: [] };
            areaMap[key].tables.push(t);
        });
        const areas = Object.entries(areaMap);
        const multiArea = areas.length > 1;

        if (multiArea) {
            tabsWrap.innerHTML = `<div class="ot-mt-area-tabs-inner">
                <button type="button" class="ot-mt-area-tab is-active" data-area-key="all">All</button>
                ${areas.map(([key, area]) => `<button type="button" class="ot-mt-area-tab" data-area-key="${escHtml(key)}">${escHtml(area.name)}</button>`).join('')}
            </div>`;
        } else {
            tabsWrap.innerHTML = '';
        }

        body.innerHTML = areas.map(([key, area]) => `
            <section class="ot-mt-area-section" data-area-key="${escHtml(key)}">
                ${multiArea ? `<div class="ot-mt-area-title">${escHtml(area.name)}</div>` : ''}
                <div class="ot-mt-grid">
                    ${area.tables.map((t) => {
                        const isCurrent = Number(t.id) === Number(currentTableId);
                        const isFree = !isCurrent && t.status === 'free';
                        const statusClass = isFree ? 'ot-table-box--free' : 'ot-table-box--occupied';
                        const label = isCurrent ? 'Current' : (isFree ? 'Available' : (t.order_no || 'Reserved'));
                        return `<button type="button"
                                class="ot-table-box ${statusClass} ot-mt-pick"
                                data-table-id="${t.id}"
                                data-table-name="${escHtml(t.name)}"
                                ${isFree ? '' : 'disabled data-locked="1"'}>
                            <span class="ot-table-shape" aria-hidden="true">
                                <span class="ot-chair ot-chair--n"></span>
                                <span class="ot-chair ot-chair--e"></span>
                                <span class="ot-chair ot-chair--s"></span>
                                <span class="ot-chair ot-chair--w"></span>
                                <span class="ot-table-top">
                                    <span class="ot-table-box-no">${escHtml(t.name)}</span>
                                </span>
                            </span>
                            <span class="ot-table-box-meta${isFree ? ' ot-table-box-meta--free' : ''}">${escHtml(label)}</span>
                        </button>`;
                    }).join('')}
                </div>
            </section>
        `).join('');
    }

    async function submitMoveTable(tableId) {
        if (!moveTableOrderId || !routes.moveTable || !boot.csrf) return;
        const body = $('#otMoveTableBody');
        body?.querySelectorAll('.ot-mt-pick').forEach((b) => { b.disabled = true; });
        try {
            const url = routes.moveTable.replace('__ID__', String(moveTableOrderId));
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': boot.csrf,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ table_id: tableId }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.ok) {
                alert(data.message || 'Table move fail ho gayi.');
                body?.querySelectorAll('.ot-mt-pick').forEach((b) => { if (!b.dataset.locked) b.disabled = false; });
                return;
            }

            const print = data.print || {};
            const printedOk = Array.isArray(print.results)
                ? print.results.filter((r) => r && r.ok).map((r) => r.department).filter(Boolean)
                : [];
            const printedFail = Array.isArray(print.results)
                ? print.results.filter((r) => r && !r.ok).map((r) => r.department || 'Printer').filter(Boolean)
                : [];

            let msg = data.message || 'Table move ho gayi.';
            if (printedOk.length) {
                msg += '\n\nMOVE TABLE slip print: ' + printedOk.join(', ');
            } else if (print.message) {
                msg += '\n\nPrint: ' + print.message;
            }
            if (printedFail.length) {
                msg += '\nFail: ' + printedFail.join(', ');
            }
            alert(msg);
            applyBoardPayload(data);
            const modalEl = $('#otMoveTableModal');
            if (modalEl && window.bootstrap?.Modal) {
                window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            }
            moveTableOrderId = null;
        } catch (err) {
            alert('Table move request fail ho gayi.');
            body?.querySelectorAll('.ot-mt-pick').forEach((b) => { if (!b.dataset.locked) b.disabled = false; });
        }
    }

    function buildConfirmBillMeta() {
        const serviceType = pendingMode ? (boot.resumeServiceType || 'dine_in') : selectedServiceType();
        const parts = [serviceTypeLabel(serviceType)];
        if (serviceType === 'dine_in') {
            const tableName = pendingMode
                ? (boot.resumeTableName || selectedTableName || resolveTableName(boot.resumeTableId))
                : (selectedTableName || resolveTableName(selectedTableId));
            if (tableName) parts.push('Table ' + tableName);
            else if (!posTablesEnabled) {
                const tableNo = ($('#otTableNo')?.value || '').trim();
                if (tableNo) parts.push('Table ' + tableNo);
            }
        } else if (serviceType === 'delivery') {
            const name = ($('#otDeliveryName')?.value || boot.resumeGuestName || '').trim();
            const phone = ($('#otDeliveryPhone')?.value || boot.resumeRoomNo || '').trim();
            if (name) parts.push(name);
            if (phone) parts.push(phone);
        } else if (serviceType === 'takeaway') {
            const contact = ($('#otTakeawayContact')?.value || boot.resumeRoomNo || boot.resumeGuestName || '').trim();
            if (contact) parts.push(contact);
        }
        if (pendingMode && boot.resumeOrderNo) {
            parts.unshift(boot.resumeOrderNo);
        }
        return parts.filter(Boolean).join(' · ');
    }

    function openConfirmBillModal() {
        if (!cart.length) {
            alert('Kam az kam aik item add karein.');
            return;
        }

        const serviceType = pendingMode ? (boot.resumeServiceType || 'dine_in') : selectedServiceType();
        if (!pendingMode) {
            if (serviceType === 'dine_in') {
                if (posTablesEnabled) {
                    if (!selectedTableId) {
                        alert('Table select karein.');
                        showTableBoard();
                        return;
                    }
                } else if (!($('#otTableNo')?.value || '').trim()) {
                    alert('Table No. enter karein.');
                    $('#otTableNo')?.focus();
                    return;
                }
            } else if (serviceType === 'delivery') {
                if (!($('#otDeliveryName')?.value || '').trim()) {
                    alert('Customer name likhein.');
                    $('#otDeliveryName')?.focus();
                    return;
                }
                if (!($('#otDeliveryPhone')?.value || '').trim()) {
                    alert('Phone number likhein.');
                    $('#otDeliveryPhone')?.focus();
                    return;
                }
            } else if (serviceType === 'takeaway') {
                if (!($('#otTakeawayContact')?.value || '').trim()) {
                    alert('Contact No. likhein.');
                    $('#otTakeawayContact')?.focus();
                    return;
                }
            }
        }

        syncItemNotesFromDom();

        const meta = $('#otConfirmBillMeta');
        const lines = $('#otConfirmBillLines');
        const notesWrap = $('#otConfirmBillNotesWrap');
        const notesEl = $('#otConfirmBillNotes');
        const totalEl = $('#otConfirmBillTotal');
        const title = $('#otConfirmBillTitle');
        const submitLabel = $('#otConfirmBillSubmitLabel');

        if (title) title.textContent = pendingMode ? 'Confirm Update Bill' : 'Confirm Bill';
        if (submitLabel) submitLabel.textContent = pendingMode ? 'Confirm & Update' : 'Confirm & Send';
        if (meta) meta.textContent = buildConfirmBillMeta();

        if (lines) {
            lines.innerHTML = cart.map((r) => {
                const note = String(r.notes || '').trim();
                return `<div class="ot-confirm-line">
                    <div class="ot-confirm-line-main">
                        <span class="ot-confirm-line-qty">${escHtml(fmtQty(r.qty))}×</span>
                        <span class="ot-confirm-line-name">${escHtml(displayProductName(r.name))}</span>
                    </div>
                    <div class="ot-confirm-line-amt">${escHtml(fmtMoney(lineRowTotal(r)))}</div>
                    ${note ? `<div class="ot-confirm-line-note">${escHtml(note)}</div>` : ''}
                </div>`;
            }).join('');
        }

        const kitchenNotes = ($('#otBillKitchenNotes')?.value || '').trim();
        if (notesWrap && notesEl) {
            if (kitchenNotes) {
                notesEl.textContent = kitchenNotes;
                notesWrap.classList.remove('d-none');
            } else {
                notesEl.textContent = '';
                notesWrap.classList.add('d-none');
            }
        }

        if (totalEl) totalEl.textContent = fmtMoney(calcCartTotals().grand);

        const modalEl = $('#otConfirmBillModal');
        if (modalEl && window.bootstrap?.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
            return;
        }
        // Fallback if Bootstrap modal missing
        if (window.confirm('Bill confirm karein? Kitchen slip chali jayegi.')) {
            submitOrder();
        }
    }

    function bindEvents() {
        $('#otAreaFilters')?.addEventListener('click', (e) => {
            const btn = e.target.closest('.ot-area-filter-btn');
            if (!btn) return;
            const key = btn.dataset.areaKey || 'all';
            $$('#otAreaFilters .ot-area-filter-btn').forEach((b) => {
                const on = b === btn;
                b.classList.toggle('is-active', on);
                b.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            $$('#otTableGrid .ot-sitting-area').forEach((section) => {
                const show = key === 'all' || section.dataset.areaKey === key;
                section.classList.toggle('d-none', !show);
            });
        });

        $('#otTableGrid')?.addEventListener('click', (e) => {
            const btn = e.target.closest('.ot-table-box');
            if (!btn) return;
            const status = btn.dataset.status;
            const tableId = Number(btn.dataset.tableId);
            const tableName = btn.dataset.tableName || resolveTableName(tableId);
            if (status === 'occupied') {
                const orderId = Number(btn.dataset.orderId);
                const amendable = btn.dataset.amendable === '1';
                if (!orderId) return;
                if (!amendable) {
                    alert('Yeh table reserved hai — is order ko ab edit nahi kiya ja sakta.');
                    return;
                }
                startEditOrder(orderId, tableId, tableName);
                return;
            }
            startNewOrder(tableId, tableName, 'dine_in');
        });

        $('#otBoardTabs')?.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-board-tab]');
            if (!btn) return;
            setBoardTab(btn.dataset.boardTab || 'tables');
        });

        $('#otPendingOrdersGrid')?.addEventListener('click', (e) => {
            const moveBtn = e.target.closest('[data-action="move-table"]');
            if (moveBtn) {
                e.preventDefault();
                e.stopPropagation();
                moveTableOrderId = Number(moveBtn.dataset.orderId);
                const orderNo = moveBtn.dataset.orderNo || '';
                const currentTableId = Number(moveBtn.dataset.tableId);
                const title = $('#otMoveTableTitle');
                if (title) title.textContent = `Select New Table — ${orderNo}`;
                renderMoveTableModal(currentTableId);
                const modalEl = $('#otMoveTableModal');
                if (modalEl && window.bootstrap?.Modal) {
                    window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
                return;
            }

            const row = e.target.closest('[data-action="open-order"]');
            if (!row || row.disabled) return;
            const orderId = Number(row.dataset.orderId);
            const amendable = row.dataset.amendable === '1';
            if (!orderId || !amendable) return;
            const board = (boot.tableBoard || []).find((t) => Number(t.order_id) === orderId);
            startEditOrder(orderId, board?.id || null, board?.name || null);
        });

        $('#otMoveTableAreaTabs')?.addEventListener('click', (e) => {
            const tab = e.target.closest('.ot-mt-area-tab');
            if (!tab) return;
            $$('#otMoveTableAreaTabs .ot-mt-area-tab').forEach((b) => b.classList.toggle('is-active', b === tab));
            const key = tab.dataset.areaKey || 'all';
            $$('#otMoveTableBody .ot-mt-area-section').forEach((section) => {
                const show = key === 'all' || section.dataset.areaKey === key;
                section.classList.toggle('d-none', !show);
            });
        });

        $('#otMoveTableBody')?.addEventListener('click', (e) => {
            const btn = e.target.closest('.ot-mt-pick');
            if (!btn || btn.disabled) return;
            const tableId = Number(btn.dataset.tableId);
            if (!tableId) return;
            submitMoveTable(tableId);
        });

        $$('.ot-quick-type').forEach((btn) => {
            btn.addEventListener('click', () => {
                startServiceOrder(btn.dataset.service || 'takeaway');
            });
        });

        $('#otServiceTypes')?.addEventListener('click', (e) => {
            const btn = e.target.closest('.rp-service-type');
            if (!btn || btn.disabled || pendingMode) return;
            setServiceType(btn.dataset.type || 'dine_in');
        });

        $('#otBackTables')?.addEventListener('click', showTableBoard);

        $('#otMenuCats')?.addEventListener('click', (e) => {
            const btn = e.target.closest('.rp-menu-cat');
            if (!btn) return;
            selectedMenuCategoryId = btn.dataset.catId ? String(btn.dataset.catId) : null;
            renderMenuCategories();
            renderMenuGrid();
        });

        $('#otMenuGrid')?.addEventListener('click', (e) => {
            const item = e.target.closest('.rp-menu-item');
            if (!item) return;
            const btn = e.target.closest('button[data-action]');
            const productId = Number(item.dataset.productId);
            if (btn) {
                addProductToCart(productId, btn.dataset.action === 'inc' ? 1 : -1);
                return;
            }
            addProductToCart(productId, 1);
        });

        $('#otCartLines')?.addEventListener('click', (e) => {
            if (e.target.closest('.rp-cl-qty-input')) return;

            const qtyBtn = e.target.closest('[data-action="cart-inc"], [data-action="cart-dec"]');
            if (qtyBtn && !qtyBtn.disabled) {
                const id = Number(qtyBtn.dataset.id);
                if (!Number.isFinite(id)) return;
                if (qtyBtn.dataset.action === 'cart-inc') {
                    addOrIncrementProduct(id);
                } else {
                    adjustProductQty(id, -1);
                }
                return;
            }

            const btn = e.target.closest('[data-action="remove-line"]');
            if (!btn || btn.disabled) return;
            removeCartLine(Number(btn.dataset.index));
        });

        $('#otCartLines')?.addEventListener('focusin', (e) => {
            if (e.target.matches('.rp-cl-qty-input')) {
                e.target.select();
            }
        });

        $('#otCartLines')?.addEventListener('keydown', (e) => {
            if (e.target.matches('.rp-cl-qty-input') && e.key === 'Enter') {
                e.preventDefault();
                e.target.blur();
            }
        });

        $('#otCartLines')?.addEventListener('blur', (e) => {
            if (e.target.matches('.rp-cl-qty-input')) {
                commitCartQtyInput(e.target);
            }
        }, true);

        $('#otCartLines')?.addEventListener('input', (e) => {
            if (!e.target.matches('.rp-cl-note')) return;
            const idx = Number(e.target.dataset.index);
            if (!Number.isFinite(idx) || !cart[idx]) return;
            cart[idx].notes = String(e.target.value || '');
        });

        $('#otProductSearch')?.addEventListener('input', renderMenuGrid);

        // Event delegation — tablet/touch pe bhi Cart/Menu tabs reliably switch hote hain
        document.getElementById('otOrderScreen')?.addEventListener('click', (e) => {
            const tab = e.target.closest('#otTabMenu, #otTabCart, #otToggleCartView');
            if (!tab) return;
            e.preventDefault();
            e.stopPropagation();
            if (tab.id === 'otTabMenu') {
                setPanelView('menu');
                return;
            }
            if (tab.id === 'otTabCart' || tab.id === 'otToggleCartView') {
                setPanelView(panelView === 'cart' ? 'menu' : 'cart');
            }
        });

        window.addEventListener('resize', () => {
            if (!document.querySelector('.order-taker-pos-app')?.classList.contains('ot-screen-order')) {
                return;
            }
            // Sirf split↔menu default adjust; user ke cart/menu selection ko mat undo karo
            if (isNarrowScreen() && panelView === 'split') {
                setPanelView('menu');
            }
        });

        document.getElementById('otSendBtn')?.addEventListener('click', (e) => {
            e.preventDefault();
            openConfirmBillModal();
        });

        document.getElementById('otConfirmBillSubmit')?.addEventListener('click', () => {
            const modalEl = $('#otConfirmBillModal');
            if (modalEl && window.bootstrap?.Modal) {
                window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            }
            submitOrder();
        });
    }

    function formatOccupiedElapsed(ms) {
        const totalSec = Math.max(0, Math.floor(ms / 1000));
        const h = Math.floor(totalSec / 3600);
        const m = Math.floor((totalSec % 3600) / 60);
        const s = totalSec % 60;
        const pad = (n) => String(n).padStart(2, '0');
        if (h > 0) return `${h}:${pad(m)}:${pad(s)}`;
        return `${pad(m)}:${pad(s)}`;
    }

    function tickOccupiedTimers() {
        const now = Date.now();
        $$('.ot-table-timer[data-occupied-at]').forEach((el) => {
            const start = Date.parse(el.getAttribute('data-occupied-at') || '');
            if (!Number.isFinite(start)) return;
            el.textContent = formatOccupiedElapsed(now - start);
        });
    }

    let occupiedTimerInterval = null;
    function startOccupiedTimers() {
        tickOccupiedTimers();
        if (occupiedTimerInterval) clearInterval(occupiedTimerInterval);
        occupiedTimerInterval = setInterval(tickOccupiedTimers, 1000);
    }

    function init() {
        renderMenuCategories();
        bindEvents();
        setBoardTab('tables');
        startOccupiedTimers();

        if (boot.resumeOrderId) {
            editOrderId = boot.resumeOrderId;
            pendingMode = true;
            selectedTableId = boot.resumeTableId;
            selectedTableName = boot.resumeTableName || resolveTableName(selectedTableId);
            loadResumeCart();
            showOrderScreen();
            return;
        }

        if (boot.startTableId) {
            selectedTableId = boot.startTableId;
            selectedTableName = resolveTableName(selectedTableId);
            startNewOrder(selectedTableId, selectedTableName, 'dine_in');
            return;
        }

        if (boot.startServiceType && boot.startServiceType !== 'dine_in') {
            startServiceOrder(boot.startServiceType);
        }
    }

    init();
})();
