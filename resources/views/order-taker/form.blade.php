@extends('layouts.admin')
@section('title', ($pendingPosMode ? 'Pending Bill' : 'Naya Order') . ' — Order Taker')
@section('page-title', 'Order Taker')

@section('content')

@push('head')
<style>
    .ot-app .ot-qty-stepper {
        width: 7.5rem;
        max-width: 100%;
        flex-wrap: nowrap;
    }
    .ot-app .ot-qty-stepper .btn {
        padding: 0;
        font-size: 1.1rem;
        line-height: 1;
        min-width: 2.25rem;
        width: 2.25rem;
        height: 2.25rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .ot-app .ot-qty-stepper .ot-qty-input {
        padding: 0 0.2rem;
        font-size: 1rem;
        text-align: center;
        min-width: 0;
        height: 2.25rem;
        -moz-appearance: textfield;
    }
    .ot-app .ot-qty-stepper .ot-qty-input::-webkit-outer-spin-button,
    .ot-app .ot-qty-stepper .ot-qty-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    .ot-app .ot-cart-item {
        border: 1px solid var(--app-border, #dee2e6);
        border-radius: 0.75rem;
        background: #fff;
    }
    .ot-app .ot-cart-item + .ot-cart-item {
        margin-top: 0.65rem;
    }
    .ot-app .ot-cart-item-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.5rem;
    }
    .ot-app .ot-cart-item-name {
        font-weight: 700;
        line-height: 1.25;
        word-break: break-word;
    }
    .ot-app .ot-cart-item-meta {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
        margin-top: 0.65rem;
    }
    .ot-app .ot-cart-item-meta .span-2 {
        grid-column: 1 / -1;
    }
    .ot-app .ot-cart-item-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        margin-top: 0.65rem;
        flex-wrap: wrap;
    }
    .ot-app .ot-cart-empty-mobile {
        text-align: center;
        color: #6c757d;
        padding: 1.5rem 0.75rem;
    }
    .ot-app .ot-line-product {
        position: relative;
        min-width: 0;
        overflow: visible;
    }
    .ot-app .ot-product-suggestions {
        top: 100%;
        left: 0;
        right: 0;
        z-index: 2000;
        max-height: 220px;
        overflow-y: auto;
        margin-top: 2px;
        box-shadow: 0 0.35rem 1rem rgba(0, 0, 0, 0.12);
        -webkit-overflow-scrolling: touch;
    }
    .ot-app .ot-product-suggestions--float {
        position: fixed;
        top: 0;
        left: 0;
        width: 18rem;
        z-index: 1080;
        max-height: min(280px, 45vh);
        margin: 0;
        background: #fff;
        border: 1px solid var(--app-border, #dee2e6);
        border-radius: 0.5rem;
        box-shadow: 0 0.5rem 1.25rem rgba(0, 0, 0, 0.18);
    }
    .ot-app .ot-cart-table-wrap {
        overflow: visible !important;
    }
    .ot-app .ot-cart-table tbody td {
        overflow: visible;
        vertical-align: middle;
    }
    .ot-app .ot-product-suggestions .list-group-item {
        cursor: pointer;
        padding: 0.45rem 0.65rem;
        font-size: 0.9rem;
        touch-action: manipulation;
        -webkit-tap-highlight-color: rgba(255, 193, 7, 0.35);
        user-select: none;
    }
    .ot-app .ot-product-suggestions .list-group-item:hover,
    .ot-app .ot-product-suggestions .list-group-item.active {
        background: #fff3cd;
    }
    .ot-app .ot-order-line-card {
        border: 1px solid var(--app-border, #dee2e6);
        border-radius: 0.75rem;
        background: #fff;
        padding: 0.75rem;
        overflow: visible;
        position: relative;
    }
    .ot-app .ot-order-line-card + .ot-order-line-card {
        margin-top: 0.65rem;
    }
    .ot-app .ot-order-line-card.is-empty {
        border-style: dashed;
        background: #fafafa;
    }
    @media (max-width: 767.98px) {
        .ot-app .card-body {
            overflow: visible !important;
        }
        .ot-app #orderLinesMobile {
            overflow: visible;
        }
        .ot-app .ot-product-suggestions .list-group-item {
            min-height: 2.75rem;
            padding: 0.65rem 0.75rem;
        }
        .ot-app .ot-action-bar .btn {
            flex: 1 1 calc(50% - 0.35rem);
            min-height: 2.75rem;
        }
        .ot-app .ot-action-bar .btn:only-child {
            flex-basis: 100%;
        }
    }
</style>
@endpush

<div class="ot-app">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div>
            <h4 class="fw-bold mb-0">
                @if($pendingPosMode)
                    Pending bill — items add karein
                @else
                    Naya Order
                @endif
            </h4>
            @if($order->exists)
                <div class="text-secondary small">{{ $order->order_no }}</div>
            @endif
        </div>
        <a href="{{ route('order-taker.index') }}" class="btn btn-outline-secondary btn-sm">← Back</a>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    @if($pendingPosMode)
        <div class="alert alert-warning py-2 mb-3">
            Yeh bill POS par pending hai. Naye products add karein, qty/notes change karein — save par POS bill update ho jayegi.
        </div>
    @endif

    <form method="POST" action="{{ $order->exists ? route('order-taker.update', $order) : route('order-taker.store') }}" id="otForm">
        @csrf
        @if($order->exists) @method('PUT') @endif
        <input type="hidden" name="items" id="itemsJson" value="">

        <div class="row g-3">
            <div class="col-lg-4 order-1 order-lg-1">
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-white fw-semibold">Guest info @if($pendingPosMode)<span class="text-secondary fw-normal small">(read only)</span>@endif</div>
                    <div class="card-body row g-2">
                        <div class="col-12">
                            <label class="form-label">Type of customer</label>
                            @php
                                $selectedCustomerType = old('customer_type', $order->customerTypeKey() ?: 'mess_use');
                                if ($selectedCustomerType === 'booking') {
                                    $selectedCustomerType = 'mess_use';
                                }
                            @endphp
                            <select name="customer_type" id="customerType" class="form-select" @disabled($pendingPosMode)>
                                <option value="mess_use" @selected($selectedCustomerType === 'mess_use')>Walk-In</option>
                                <option value="ast_offr" @selected($selectedCustomerType === 'ast_offr')>{{ \App\Models\PosOrder::MESS_BILL_LABEL }}</option>
                            </select>
                        </div>
                        <div class="col-12" id="guestNameWrap">
                            <label class="form-label" id="guestNameLabel">Guest name</label>
                            <input type="text" name="guest_name" id="guestName" class="form-control" value="{{ old('guest_name', $order->guest_name) }}" placeholder="Guest ka naam" @disabled($pendingPosMode)>
                        </div>
                        <div class="col-12" id="waiterWrap">
                            <label class="form-label">Waiter</label>
                            <select name="waiter_name" id="waiterName" class="form-select" @disabled($pendingPosMode)>
                                <option value="">— Waiter —</option>
                                @foreach($waiters as $w)
                                    <option value="{{ $w->name }}" @selected(old('waiter_name', $order->waiter_name) === $w->name)>{{ $w->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12" id="serveScheduleWrap">
                            @php
                                $selectedServeMeal = old('serve_meal', $order->serve_meal ?? '');
                            @endphp
                            <label class="form-label" for="serveMeal">Meal / Serve for</label>
                            <select name="serve_meal" id="serveMeal" class="form-select mb-2" @disabled($pendingPosMode)>
                                <option value="">— Meal choose karein —</option>
                                @foreach($serveMealsJson as $meal)
                                    <option value="{{ $meal['key'] }}" data-time="{{ $meal['time'] }}" @selected($selectedServeMeal === $meal['key'])>{{ $meal['label'] }}</option>
                                @endforeach
                            </select>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label small text-secondary mb-1" for="serveDate">Serve date</label>
                                    <input type="date" name="serve_date" id="serveDate" class="form-control"
                                           value="{{ old('serve_date', $order->serve_date ? \Illuminate\Support\Carbon::parse($order->serve_date)->format('Y-m-d') : now()->format('Y-m-d')) }}" @disabled($pendingPosMode)>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small text-secondary mb-1" for="serveTime">Serve time</label>
                                    <input type="time" name="serve_time" id="serveTime" class="form-control"
                                           value="{{ old('serve_time', $order->serve_time ?? '') }}" @disabled($pendingPosMode)>
                                </div>
                            </div>
                            <div class="form-text">Meal select karte hi agla serve slot auto set ho jata hai — date/time zarurat ho to change kar sakte hain.</div>
                        </div>
                        @if($tables->isNotEmpty())
                        <div class="col-12" id="tableWrap">
                            <label class="form-label">Table</label>
                            <select name="table_id" id="tableId" class="form-select" @disabled($pendingPosMode)>
                                <option value="">Walk-in / No table</option>
                                @foreach($tables as $t)
                                    <option value="{{ $t->id }}" @selected((string) old('table_id', $order->table_id) === (string) $t->id)>{{ $t->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-8 order-2 order-lg-2">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span>Order items</span>
                        <span class="ot-grand">{{ $currency }} <span id="grandTotal">0.00</span></span>
                    </div>
                    <div class="card-body p-0">
                        <div class="p-2 border-bottom bg-light small text-secondary d-none d-md-block">
                            Har line par product type karke choose karein, qty set karein, phir agla item add karein.
                        </div>
                        <div id="orderLinesMobile" class="d-md-none p-2"></div>
                        <div class="table-responsive ot-cart-table-wrap d-none d-md-block">
                            <table class="table ot-cart-table mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="min-width:14rem">Product</th>
                                        <th style="width:6rem">UOM</th>
                                        <th style="width:8rem" class="text-center">Qty</th>
                                        <th>Notes</th>
                                        <th class="text-end" style="width:5rem">Total</th>
                                        <th style="width:2.5rem"></th>
                                    </tr>
                                </thead>
                                <tbody id="orderLinesBody"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white">
                        <button type="button" class="btn btn-outline-primary btn-sm mb-2" id="addOrderLineBtn">
                            <i class="bi bi-plus-lg"></i> Add item
                        </button>
                        <div class="d-flex flex-wrap gap-2 justify-content-end ot-action-bar">
                        @if($pendingPosMode)
                            <button type="button" class="btn btn-success" id="saveBtn">Update bill</button>
                        @else
                            <button type="button" class="btn btn-success" id="sendPosBtn">Send to POS &amp; Kitchen</button>
                        @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div id="otProductSuggestionsFloat" class="ot-product-suggestions ot-product-suggestions--float list-group d-none" role="listbox" aria-label="Product suggestions"></div>
</div>

<script>
(() => {
    const products = @json($productsJson);
    const initialCart = @json($cartJson);
    const currency = @json($currency);

    const orderLinesBody = document.getElementById('orderLinesBody');
    const orderLinesMobile = document.getElementById('orderLinesMobile');
    const grandTotalEl = document.getElementById('grandTotal');
    const itemsJson = document.getElementById('itemsJson');
    const otForm = document.getElementById('otForm');
    const customerType = document.getElementById('customerType');
    const guestNameWrap = document.getElementById('guestNameWrap');
    const roomNoWrap = document.getElementById('roomNoWrap');
    const waiterWrap = document.getElementById('waiterWrap');
    const serveScheduleWrap = document.getElementById('serveScheduleWrap');
    const serveDate = document.getElementById('serveDate');
    const serveTime = document.getElementById('serveTime');
    const serveMeal = document.getElementById('serveMeal');
    const serveMeals = @json($serveMealsJson);
    const guestNameLabel = document.getElementById('guestNameLabel');
    const tableWrap = document.getElementById('tableWrap');
    const roomNo = document.getElementById('roomNo');
    const addOrderLineBtn = document.getElementById('addOrderLineBtn');
    const suggestFloat = document.getElementById('otProductSuggestionsFloat');

    let suppressOutsideClose = false;
    let hideSuggestionsTimer = null;
    let pickGuardUntil = 0;
    let activeSuggestInput = null;

    function isMobileView() {
        return window.matchMedia('(max-width: 767.98px)').matches;
    }

    function emptyLine(fromCart) {
        return {
            product_id: fromCart?.product_id ?? null,
            name: fromCart?.name ?? '',
            uom: fromCart?.uom ?? '',
            qty: fromCart?.qty ?? 1,
            unit_price: fromCart?.unit_price ?? 0,
            notes: fromCart?.notes ?? '',
            kitchen_served: !!fromCart?.kitchen_served,
            kitchen_pending: fromCart?.kitchen_pending !== false,
            search: fromCart?.name ?? '',
        };
    }

    let lines = [];
    if (Array.isArray(initialCart) && initialCart.length > 0) {
        lines = initialCart.map(r => emptyLine(r));
    } else {
        lines = [emptyLine()];
    }

    function unitPriceForUom(p, uom) {
        const row = (p.uoms || []).find(u => String(u.uom).toLowerCase() === String(uom).toLowerCase());
        const factor = row ? Number(row.factor_to_base || 0) : (String(p.base_uom).toLowerCase() === String(uom).toLowerCase() ? 1 : 0);
        return factor > 0 ? Math.round(Number(p.price) * factor * 100) / 100 : Number(p.price);
    }

    function defaultUom(p) {
        return (p.uoms && p.uoms[0]) ? p.uoms[0].uom : p.base_uom;
    }

    function escHtml(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');
    }

    function isProductVisibleForCustomerType(product) {
        return !!product.for_pos;
    }

    function filteredProducts(query) {
        const q = query.trim().toLowerCase();
        return products
            .filter(p => isProductVisibleForCustomerType(p) && (!q || p.name.toLowerCase().includes(q) || String(p.sku).toLowerCase().includes(q)))
            .slice(0, 12);
    }

    function productById(id) {
        return products.find(x => x.id === id);
    }

    function filledLines() {
        return lines.filter(r => r.product_id);
    }

    function syncItemsJson() {
        itemsJson.value = JSON.stringify(filledLines().map(r => ({
            product_id: r.product_id,
            uom: r.uom,
            qty: Number(r.qty),
            notes: r.notes || '',
        })));
    }

    function ensureTrailingEmptyLine() {
        const last = lines[lines.length - 1];
        if (!last || last.product_id) {
            lines.push(emptyLine());
        }
    }

    function setLineProduct(idx, product) {
        if (!product || !isProductVisibleForCustomerType(product)) return;
        const uom = defaultUom(product);
        lines[idx].product_id = product.id;
        lines[idx].name = product.name;
        lines[idx].search = product.name;
        lines[idx].uom = uom;
        lines[idx].unit_price = unitPriceForUom(product, uom);
        if (!lines[idx].qty || Number(lines[idx].qty) <= 0) {
            lines[idx].qty = 1;
        }
        if (idx === lines.length - 1) {
            lines.push(emptyLine());
        }
        renderLines();
        setTimeout(() => {
            if (isMobileView()) {
                const qty = document.querySelector(`#orderLinesMobile .line-qty[data-idx="${idx}"]`);
                qty?.focus({ preventScroll: false });
                qty?.select?.();
            } else {
                document.querySelectorAll('.line-product-search')[idx + 1]?.focus();
            }
        }, 50);
    }

    function qtyStep(idx, delta) {
        const current = Number(lines[idx]?.qty) || 1;
        lines[idx].qty = Math.max(0.001, Math.round((current + delta) * 1000) / 1000);
        renderLines();
    }

    function qtyStepperHtml(idx, qty, disabled) {
        if (disabled) {
            return `<span class="small">${escHtml(String(qty))}</span>`;
        }
        return `
            <div class="input-group input-group-sm ot-qty-stepper">
                <button type="button" class="btn btn-outline-secondary line-qty-minus" data-idx="${idx}" aria-label="Decrease quantity">−</button>
                <input type="number" min="0.001" step="0.001" class="form-control ot-qty-input text-center line-qty" data-idx="${idx}" value="${qty}">
                <button type="button" class="btn btn-outline-secondary line-qty-plus" data-idx="${idx}" aria-label="Increase quantity">+</button>
            </div>`;
    }

    function uomSelectHtml(idx, p, r, disabled) {
        if (disabled || !r.product_id) {
            return `<span class="small text-secondary">${escHtml(r.uom || '—')}</span>`;
        }
        return `<select class="form-select form-select-sm line-uom" data-idx="${idx}">
            ${(p?.uoms || [{uom: r.uom, factor_to_base: 1}]).map(u => `<option value="${escHtml(u.uom)}" ${u.uom === r.uom ? 'selected' : ''}>${escHtml(u.uom)}</option>`).join('')}
        </select>`;
    }

    function productSearchHtml(idx, r, disabled) {
        if (disabled && r.product_id) {
            return `<div class="fw-semibold">${escHtml(r.name)}</div>`;
        }
        return `
            <div class="ot-line-product">
                <input type="text" class="form-control form-control-sm line-product-search" data-idx="${idx}"
                    value="${escHtml(r.search || r.name || '')}" placeholder="Product type karein..." autocomplete="off">
            </div>`;
    }

    function notesInputHtml(idx, notes, disabled) {
        if (disabled) {
            return `<span class="small text-secondary">${escHtml(notes || '—')}</span>`;
        }
        return `<input type="text" class="form-control form-control-sm line-notes" data-idx="${idx}"
            maxlength="200" value="${escHtml(notes || '')}" placeholder="Notes (optional)">`;
    }

    function hideAllSuggestions() {
        if (Date.now() < pickGuardUntil) return;
        activeSuggestInput = null;
        suggestFloat?.classList.add('d-none');
        if (suggestFloat) suggestFloat.innerHTML = '';
    }

    function positionSuggestionFloat(inputEl) {
        if (!suggestFloat || !inputEl || suggestFloat.classList.contains('d-none')) return;

        const rect = inputEl.getBoundingClientRect();
        const gap = 4;
        const maxH = Math.min(280, Math.round(window.innerHeight * 0.45));
        const width = Math.max(rect.width, 260);
        let left = rect.left;
        let top = rect.bottom + gap;

        suggestFloat.style.width = `${width}px`;
        suggestFloat.style.maxHeight = `${maxH}px`;

        const panelH = Math.min(suggestFloat.scrollHeight, maxH);
        if (top + panelH > window.innerHeight - 8) {
            top = rect.top - gap - panelH;
        }
        if (top < 8) {
            top = rect.bottom + gap;
        }
        if (left + width > window.innerWidth - 8) {
            left = window.innerWidth - width - 8;
        }

        suggestFloat.style.left = `${Math.max(8, left)}px`;
        suggestFloat.style.top = `${Math.max(8, top)}px`;
    }

    function suggestionBoxForInput(inputEl) {
        if (activeSuggestInput === inputEl && suggestFloat && !suggestFloat.classList.contains('d-none')) {
            return suggestFloat;
        }
        return null;
    }

    function pickProduct(el) {
        if (!el) return;
        if (el.dataset.picking === '1') return;
        el.dataset.picking = '1';
        pickGuardUntil = Date.now() + 400;
        suppressOutsideClose = true;
        clearTimeout(hideSuggestionsTimer);
        const idx = Number(el.dataset.idx);
        const p = productById(Number(el.dataset.id));
        if (p) setLineProduct(idx, p);
        hideAllSuggestions();
        setTimeout(() => {
            suppressOutsideClose = false;
            delete el.dataset.picking;
        }, 400);
    }

    function onProductPickEvent(e) {
        const pick = e.target.closest('.line-product-pick');
        if (!pick) return;
        e.preventDefault();
        e.stopPropagation();
        pickProduct(pick);
    }

    function showSuggestions(inputEl, query) {
        if (!suggestFloat) return;
        const idx = Number(inputEl.dataset.idx);
        const matches = filteredProducts(query);
        if (matches.length === 0 || !query.trim()) {
            hideAllSuggestions();
            return;
        }
        activeSuggestInput = inputEl;
        suggestFloat.innerHTML = matches.map(p => `
            <div role="button" tabindex="0" class="list-group-item list-group-item-action line-product-pick" data-idx="${idx}" data-id="${p.id}">
                <div class="fw-semibold">${escHtml(p.name)}</div>
                <div class="text-secondary small">${currency} ${unitPriceForUom(p, defaultUom(p)).toFixed(2)}</div>
            </div>
        `).join('');
        suggestFloat.classList.remove('d-none');
        requestAnimationFrame(() => positionSuggestionFloat(inputEl));
    }

    function bindPickDelegation() {
        const bindContainer = (container) => {
            if (!container || container.dataset.pickBound === '1') return;
            container.dataset.pickBound = '1';
            container.addEventListener('pointerdown', onProductPickEvent);
            container.addEventListener('touchend', onProductPickEvent, { passive: false });
            container.addEventListener('click', onProductPickEvent);
        };
        [orderLinesBody, orderLinesMobile, suggestFloat].forEach(bindContainer);
    }

    function bindLineEvents(scope) {
        scope.querySelectorAll('.line-product-search').forEach(el => {
            if (el.dataset.bound === '1') return;
            el.dataset.bound = '1';
            el.addEventListener('input', e => {
                const idx = Number(e.target.dataset.idx);
                lines[idx].search = e.target.value;
                if (!e.target.value.trim()) {
                    lines[idx].product_id = null;
                    lines[idx].name = '';
                    lines[idx].uom = '';
                    lines[idx].unit_price = 0;
                }
                showSuggestions(e.target, e.target.value);
                syncItemsJson();
            });
            el.addEventListener('focus', e => {
                clearTimeout(hideSuggestionsTimer);
                showSuggestions(e.target, e.target.value);
            });
            el.addEventListener('blur', () => {
                hideSuggestionsTimer = setTimeout(() => {
                    if (!suppressOutsideClose) hideAllSuggestions();
                }, 280);
            });
            el.addEventListener('keydown', e => {
                const idx = Number(e.target.dataset.idx);
                const box = suggestionBoxForInput(e.target);
                const items = box ? [...box.querySelectorAll('.line-product-pick')] : [];
                if (e.key === 'Escape') {
                    hideAllSuggestions();
                    return;
                }
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const active = items.find(i => i.classList.contains('active')) || items[0];
                    if (active) pickProduct(active);
                    return;
                }
                if (e.key === 'ArrowDown' && items.length) {
                    e.preventDefault();
                    let activeIdx = items.findIndex(i => i.classList.contains('active'));
                    activeIdx = activeIdx < items.length - 1 ? activeIdx + 1 : 0;
                    items.forEach(i => i.classList.remove('active'));
                    items[activeIdx].classList.add('active');
                    items[activeIdx].scrollIntoView({ block: 'nearest' });
                }
                if (e.key === 'ArrowUp' && items.length) {
                    e.preventDefault();
                    let activeIdx = items.findIndex(i => i.classList.contains('active'));
                    activeIdx = activeIdx > 0 ? activeIdx - 1 : items.length - 1;
                    items.forEach(i => i.classList.remove('active'));
                    items[activeIdx].classList.add('active');
                    items[activeIdx].scrollIntoView({ block: 'nearest' });
                }
            });
        });

        scope.querySelectorAll('.line-qty').forEach(el => {
            if (el.dataset.bound === '1') return;
            el.dataset.bound = '1';
            el.addEventListener('change', e => {
                const i = Number(e.target.dataset.idx);
                lines[i].qty = Math.max(0.001, Number(e.target.value) || 1);
                renderLines();
            });
        });
        scope.querySelectorAll('.line-qty-minus').forEach(el => {
            el.addEventListener('click', e => qtyStep(Number(e.currentTarget.dataset.idx), -1));
        });
        scope.querySelectorAll('.line-qty-plus').forEach(el => {
            el.addEventListener('click', e => qtyStep(Number(e.currentTarget.dataset.idx), 1));
        });
        scope.querySelectorAll('.line-uom').forEach(el => {
            el.addEventListener('change', e => {
                const i = Number(e.target.dataset.idx);
                const p = productById(lines[i].product_id);
                lines[i].uom = e.target.value;
                lines[i].unit_price = unitPriceForUom(p, lines[i].uom);
                renderLines();
            });
        });
        scope.querySelectorAll('.line-notes').forEach(el => {
            el.addEventListener('input', e => {
                lines[Number(e.target.dataset.idx)].notes = e.target.value;
                syncItemsJson();
            });
        });
        scope.querySelectorAll('.line-rm').forEach(el => {
            el.addEventListener('click', e => {
                const i = Number(e.currentTarget.dataset.idx);
                if (lines[i].kitchen_served) return;
                lines.splice(i, 1);
                if (lines.length === 0) lines.push(emptyLine());
                ensureTrailingEmptyLine();
                renderLines();
            });
        });
    }

    function renderLines() {
        ensureTrailingEmptyLine();
        orderLinesBody.innerHTML = '';
        if (orderLinesMobile) orderLinesMobile.innerHTML = '';

        let grand = 0;

        lines.forEach((r, idx) => {
            const p = productById(r.product_id);
            const locked = !!r.kitchen_served;
            const hasProduct = !!r.product_id;
            const lineTotal = hasProduct ? Number(r.qty) * Number(r.unit_price) : 0;
            if (hasProduct) grand += lineTotal;

            const tr = document.createElement('tr');
            if (!hasProduct) tr.classList.add('table-light');
            tr.innerHTML = `
                <td>${productSearchHtml(idx, r, locked)}</td>
                <td>${uomSelectHtml(idx, p, r, locked)}</td>
                <td class="text-center">${qtyStepperHtml(idx, r.qty, locked || !hasProduct)}</td>
                <td>${notesInputHtml(idx, r.notes, locked)}</td>
                <td class="text-end fw-semibold">${hasProduct ? lineTotal.toFixed(2) : '—'}</td>
                <td class="text-end">${locked || (!hasProduct && idx === lines.length - 1) ? '' : `<button type="button" class="btn btn-sm btn-outline-danger line-rm" data-idx="${idx}">×</button>`}</td>`;
            orderLinesBody.appendChild(tr);

            if (orderLinesMobile) {
                const card = document.createElement('div');
                card.className = 'ot-order-line-card' + (!hasProduct ? ' is-empty' : '');
                card.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <div class="flex-grow-1">${productSearchHtml(idx, r, locked)}</div>
                        ${locked || (!hasProduct && idx === lines.length - 1) ? '' : `<button type="button" class="btn btn-sm btn-outline-danger line-rm flex-shrink-0" data-idx="${idx}">×</button>`}
                    </div>
                    ${hasProduct ? `
                    <div class="row g-2 align-items-end">
                        <div class="col-4">
                            <div class="small text-secondary mb-1">UOM</div>
                            ${uomSelectHtml(idx, p, r, locked)}
                        </div>
                        <div class="col-4">
                            <div class="small text-secondary mb-1">Qty</div>
                            ${qtyStepperHtml(idx, r.qty, locked)}
                        </div>
                        <div class="col-4 text-end">
                            <div class="small text-secondary mb-1">Total</div>
                            <div class="fw-bold">${lineTotal.toFixed(2)}</div>
                        </div>
                        <div class="col-12">
                            <div class="small text-secondary mb-1">Notes</div>
                            ${notesInputHtml(idx, r.notes, locked)}
                        </div>
                    </div>` : `<div class="small text-secondary">Product search karein, phir qty set karein.</div>`}
                    ${locked ? '<div class="mt-2"><span class="badge text-bg-success">Served</span></div>' : ''}`;
                orderLinesMobile.appendChild(card);
            }
        });

        grandTotalEl.textContent = grand.toFixed(2);
        syncItemsJson();
        bindLineEvents(orderLinesBody);
        if (orderLinesMobile) bindLineEvents(orderLinesMobile);
    }

    function syncCustomerTypeUi() {
        const type = customerType.value;
        const booking = type === 'booking';
        const messBill = type === 'ast_offr';
        const walkIn = type === 'mess_use';

        guestNameWrap.classList.toggle('d-none', booking);
        roomNoWrap.classList.toggle('d-none', !booking);
        waiterWrap.classList.toggle('d-none', !walkIn);
        serveScheduleWrap?.classList.toggle('d-none', !walkIn);
        if (tableWrap) tableWrap.classList.toggle('d-none', booking);

        if (guestNameLabel) {
            guestNameLabel.textContent = messBill ? 'Officer / Guest name' : 'Guest name';
        }
        if (guestNameWrap && messBill) {
            guestNameWrap.classList.remove('d-none');
        }
    }

    function localDateStr(d) {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    }

    function applyServeMeal(mealKey) {
        if (!serveDate || !serveTime) return;
        const meal = serveMeals.find(m => m.key === mealKey);
        if (!meal) return;

        const now = new Date();
        const parts = String(meal.time).split(':');
        const hour = Number(parts[0] || 0);
        const minute = Number(parts[1] || 0);
        const slot = new Date(now.getFullYear(), now.getMonth(), now.getDate(), hour, minute, 0);
        if (now >= slot) {
            slot.setDate(slot.getDate() + 1);
        }

        serveDate.value = localDateStr(slot);
        serveTime.value = meal.time;
    }

    serveMeal?.addEventListener('change', () => {
        if (serveMeal.value) {
            applyServeMeal(serveMeal.value);
        }
    });

    document.addEventListener('click', e => {
        if (suppressOutsideClose) return;
        if (!e.target.closest('.ot-line-product') && !e.target.closest('#otProductSuggestionsFloat')) hideAllSuggestions();
    });
    document.addEventListener('touchstart', e => {
        if (suppressOutsideClose) return;
        if (!e.target.closest('.ot-line-product') && !e.target.closest('#otProductSuggestionsFloat')) hideAllSuggestions();
    }, { passive: true });

    window.addEventListener('scroll', () => {
        if (activeSuggestInput) positionSuggestionFloat(activeSuggestInput);
    }, true);
    window.addEventListener('resize', () => {
        if (activeSuggestInput) positionSuggestionFloat(activeSuggestInput);
    });

    customerType.addEventListener('change', () => {
        syncCustomerTypeUi();
        lines = lines.filter(l => l.product_id);
        if (lines.length === 0) {
            lines = [emptyLine()];
        } else {
            lines.push(emptyLine());
        }
        renderLines();
    });
    roomNo?.addEventListener('change', () => {
        const opt = roomNo.selectedOptions[0];
        if (opt?.dataset.guest) {
            document.getElementById('guestName').value = opt.dataset.guest;
        }
    });

    addOrderLineBtn?.addEventListener('click', () => {
        lines.push(emptyLine());
        renderLines();
        const root = isMobileView() ? orderLinesMobile : orderLinesBody;
        const inputs = root?.querySelectorAll('.line-product-search');
        inputs?.[inputs.length - 1]?.focus();
    });

    const saveBtn = document.getElementById('saveBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', () => {
            if (filledLines().length === 0) {
                alert('Kam az kam aik product hona chahiye.');
                return;
            }
            otForm.requestSubmit();
        });
    }

    const sendPosBtn = document.getElementById('sendPosBtn');
    if (sendPosBtn) {
        sendPosBtn.addEventListener('click', () => {
            if (filledLines().length === 0) {
                alert('Pehle product add karein.');
                return;
            }
            if (!confirm('Order POS aur kitchen screen par bhejein?')) return;
            otForm.requestSubmit();
        });
    }

    syncCustomerTypeUi();
    bindPickDelegation();
    renderLines();
})();
</script>
@endsection




