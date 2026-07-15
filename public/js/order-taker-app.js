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
    // Order taker cannot delete/reduce — sirf admin/manager (bypass) kar sakta hai.
    const canReduceCartItems = canVoidKitchenItems;

    let cart = [];
    let selectedMenuCategoryId = null;
    let panelView = 'split';
    let editOrderId = boot.resumeOrderId || null;
    let selectedTableId = boot.resumeTableId || boot.startTableId || null;
    let selectedTableName = boot.resumeTableName || null;
    let pendingMode = !!editOrderId;

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
            const canDec = qty > 0 && canReduceCartItems;
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
            const showRemove = canReduceCartItems;
            const kitchenBadge = locked > 0
                ? `<span class="rp-kitchen-pill ${r.kitchen_served ? 'rp-kitchen-pill--served' : 'rp-kitchen-pill--pending'}">
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
                    ${showRemove ? `<button type="button" class="rp-cl-remove" data-action="remove-line" data-index="${i}" aria-label="Remove item">
                        <i class="bi bi-x-lg"></i>
                    </button>` : ''}
                </div>
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
        el('#otCartCount', String(cart.length));
        el('#otCartTabCount', String(cart.length));
    }

    function renderAll() {
        renderMenuGrid();
        renderCart();
        renderTotals();
    }

    function addProductToCart(productId, delta) {
        const p = productById(productId);
        if (!p || !isProductVisible(p)) return;
        if (delta > 0) {
            const uom = defaultUom(p);
            cart.push({
                product_id: p.id,
                name: p.name,
                uom,
                qty: delta,
                unit_price: unitPriceForProduct(p, uom),
                notes: '',
                kitchen_served: false,
                kitchen_pending: true,
                kitchen_locked_qty: 0,
            });
            renderAll();
            return;
        }
        adjustProductQty(productId, delta);
    }

    function adjustProductQty(productId, delta) {
        const p = productById(productId);
        if (delta < 0 && !canReduceCartItems) {
            alert('Quantity kam sirf manager/admin kar sakta hai.');
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

    function removeCartLine(index) {
        const row = cart[index];
        if (!row) return;
        if (!canReduceCartItems) {
            alert('Item remove sirf manager/admin kar sakta hai.');
            return;
        }
        const locked = Number(row.kitchen_locked_qty) || 0;
        if (locked > 0 && !canVoidKitchenItems) return;
        cart.splice(index, 1);
        renderAll();
    }

    function isNarrowScreen() {
        return window.matchMedia('(max-width: 991.98px)').matches;
    }

    function preferredPanelView(force) {
        if (force === 'menu' || force === 'cart' || force === 'split') {
            if (isNarrowScreen() && force === 'split') {
                return 'menu';
            }
            return force;
        }
        return isNarrowScreen() ? 'menu' : 'split';
    }

    function setPanelView(view) {
        const app = document.querySelector('.order-taker-pos-app');
        if (!app) return;
        const next = preferredPanelView(view);
        panelView = next;
        app.classList.remove('rp-view-menu', 'rp-view-cart');
        if (next === 'menu') app.classList.add('rp-view-menu');
        if (next === 'cart') app.classList.add('rp-view-cart');
        $('#otTabMenu')?.classList.toggle('is-active', next === 'menu' || next === 'split');
        $('#otTabCart')?.classList.toggle('is-active', next === 'cart');
        const expandBtn = $('#otToggleCartView');
        const icon = expandBtn?.querySelector('i');
        if (icon) icon.className = next === 'cart' ? 'bi bi-layout-sidebar-reverse' : 'bi bi-arrows-fullscreen';
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
        window.history.replaceState({}, '', routes.index || '/order-taker');
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
            if (boot.resumeGuestName && $('#otDeliveryName')) $('#otDeliveryName').value = boot.resumeGuestName;
            if (boot.resumeRoomNo && $('#otDeliveryPhone')) $('#otDeliveryPhone').value = boot.resumeRoomNo;
            if (boot.resumeOrderNotes && $('#otDeliveryAddress')) $('#otDeliveryAddress').value = boot.resumeOrderNotes;
            if ($('#otTableNo') && boot.resumeGuestName) $('#otTableNo').value = boot.resumeGuestName;
            return;
        }
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
            kitchen_locked_qty: Number(r.kitchen_locked_qty) || (r.kitchen_served || r.kitchen_pending ? Number(r.qty) : 0),
        }));
    }

    function startNewOrder(tableId, tableName, serviceType) {
        editOrderId = null;
        pendingMode = false;
        selectedTableId = tableId || null;
        selectedTableName = tableName || null;
        cart = [];
        boot.resumeOrderNo = null;
        boot.startServiceType = serviceType || 'dine_in';
        showOrderScreen();
        setServiceType(serviceType || 'dine_in');
    }

    function startServiceOrder(serviceType) {
        startNewOrder(null, null, serviceType);
    }

    function startEditOrder(orderId, tableId, tableName) {
        if (Number(boot.resumeOrderId) === Number(orderId)) {
            selectedTableId = tableId || boot.resumeTableId;
            selectedTableName = tableName || boot.resumeTableName || resolveTableName(selectedTableId);
            loadResumeCart();
            showOrderScreen();
            return;
        }
        window.location.href = `${routes.index}?order_id=${orderId}`;
    }

    function submitOrder() {
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
            }
        }

        const form = $('#otSubmitForm');
        if (!form) return;

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
        }

        $('#otFormServiceType').value = serviceType;
        $('#otFormGuestName').value = guestName;
        $('#otFormRoomNo').value = roomNo;
        $('#otFormOrderNotes').value = orderNotes;
        $('#otFormTableId').value = tableId;
        $('#otFormItems').value = JSON.stringify(itemsPayload);

        if (pendingMode && editOrderId) {
            form.action = (routes.update || '').replace('__ID__', String(editOrderId));
            $('#otFormMethod').value = 'PUT';
        } else {
            form.action = routes.store || form.action;
            $('#otFormMethod').value = 'POST';
        }

        form.submit();
    }

    function bindEvents() {
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
            const btn = e.target.closest('[data-action="remove-line"]');
            if (!btn) return;
            removeCartLine(Number(btn.dataset.index));
        });

        $('#otProductSearch')?.addEventListener('input', renderMenuGrid);

        $('#otTabMenu')?.addEventListener('click', () => {
            setPanelView(isNarrowScreen() ? 'menu' : (panelView === 'menu' ? 'split' : 'menu'));
        });
        $('#otTabCart')?.addEventListener('click', () => {
            setPanelView(isNarrowScreen() ? 'cart' : (panelView === 'cart' ? 'split' : 'cart'));
        });
        $('#otToggleCartView')?.addEventListener('click', () => {
            setPanelView(panelView === 'cart' ? preferredPanelView() : 'cart');
        });

        window.addEventListener('resize', () => {
            if (!document.querySelector('.order-taker-pos-app')?.classList.contains('ot-screen-order')) {
                return;
            }
            if (isNarrowScreen() && panelView === 'split') {
                setPanelView('menu');
            }
        });

        $('#otSendBtn')?.addEventListener('click', submitOrder);
    }

    function init() {
        renderMenuCategories();
        bindEvents();

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
