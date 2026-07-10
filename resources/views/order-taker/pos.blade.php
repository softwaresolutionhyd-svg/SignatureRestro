@extends('layouts.admin')
@section('title', 'Order Taker — ' . config('app.name'))
@section('page-title', 'Order Taker')

@push('head')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/restaurant-pos.css') }}?v=33">
<link rel="stylesheet" href="{{ asset('css/order-taker-pos.css') }}?v=1">
@endpush

@section('content')
@php
    $productJs = $products->map(function ($p) {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku,
            'image_url' => $p->imageUrl(),
            'uom' => $p->uom,
            'price' => (float) $p->price,
            'for_pos' => (bool) ($p->for_pos ?? false),
            'for_purchase' => (bool) ($p->for_purchase ?? true),
            'category_id' => $p->category_id ? (int) $p->category_id : null,
            'category_parent_id' => $p->category?->parent_id ? (int) $p->category->parent_id : null,
            'uoms' => collect($p->uomsForForms())->map(fn ($row) => [
                'uom' => $row['uom'],
                'factor' => (float) $row['factor'],
            ])->values()->all(),
        ];
    })->values();

    $menuCategoryMap = [];
    foreach ($products as $p) {
        if (! $p->category_id || ! $p->category || ! $p->category->parent_id || ! $p->category->parent) {
            continue;
        }
        if ($p->category->parent->parent_id !== null) {
            continue;
        }
        $cat = $p->category;
        $menuCategoryMap[$cat->id] = [
            'id' => (int) $cat->id,
            'name' => (string) $cat->name,
            'sort' => strtolower($cat->name),
        ];
    }
    $menuCategories = collect($menuCategoryMap)->sortBy('sort')->values()->all();

    $resumeItems = collect($resumedOrder?->items ?? [])->map(fn ($i) => [
        'product_id' => $i->product_id,
        'name' => (string) ($i->product?->name ?? ''),
        'uom' => $i->uom,
        'qty' => (float) $i->qty,
        'unit_price' => (float) $i->unit_price,
        'notes' => (string) ($i->notes ?? ''),
        'kitchen_served' => $i->isKitchenServed(),
        'kitchen_pending' => (bool) $i->kitchen_pending,
        'kitchen_locked_qty' => $i->isKitchenServed() || $i->kitchen_pending ? (float) $i->qty : 0,
    ])->values();

    $updateStub = str_replace('999999999', '__ID__', route('order-taker.update', ['order' => 999999999]));
@endphp

<div class="restaurant-pos-app order-taker-pos-app">
    {{-- Table selection screen --}}
    <div id="otTableBoard" class="ot-table-board">
        <header class="rp-topbar ot-table-topbar">
            <div class="rp-topbar-brand">
                <span class="rp-brand-mark" aria-hidden="true"><i class="bi bi-table"></i></span>
                <div class="rp-brand-text">
                    <span class="rp-brand-title">Order Taker</span>
                    <span class="rp-brand-sub">Table select karein</span>
                </div>
            </div>
            <div class="rp-topbar-actions">
                <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-secondary rp-link-exit" title="Dashboard">
                    <i class="bi bi-box-arrow-left"></i>
                </a>
            </div>
        </header>

        @if(session('success'))
            <div class="alert alert-success py-2 mx-3 mt-2 mb-0">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger py-2 mx-3 mt-2 mb-0">{{ session('error') }}</div>
        @endif

        @if(! $session)
            <div class="ot-table-board-body">
                <div class="ot-no-session">
                    <i class="bi bi-exclamation-triangle"></i>
                    <p>POS session open nahi hai. Pehle cashier se session open karwayein.</p>
                </div>
            </div>
        @elseif($tableBoard === [])
            <div class="ot-table-board-body">
                <div class="ot-no-session">
                    <i class="bi bi-table"></i>
                    <p>Koi table configure nahi — Settings se tables enable karein.</p>
                </div>
            </div>
        @else
            <div class="ot-table-legend">
                <span class="ot-legend-item"><span class="ot-legend-dot ot-legend-dot--free"></span> Free</span>
                <span class="ot-legend-item"><span class="ot-legend-dot ot-legend-dot--occupied"></span> Reserved</span>
            </div>
            <div class="ot-table-board-body">
                <div class="ot-table-grid" id="otTableGrid">
                    @foreach($tableBoard as $t)
                        <button type="button"
                                class="ot-table-box ot-table-box--{{ $t['status'] }}"
                                data-table-id="{{ $t['id'] }}"
                                data-table-name="{{ $t['name'] }}"
                                data-status="{{ $t['status'] }}"
                                data-order-id="{{ $t['order_id'] ?? '' }}"
                                data-amendable="{{ $t['amendable'] ? '1' : '0' }}"
                                aria-label="Table {{ $t['name'] }} — {{ $t['status'] === 'free' ? 'free' : 'reserved' }}">
                            <span class="ot-table-box-no">{{ $t['name'] }}</span>
                            @if($t['status'] === 'occupied')
                                <span class="ot-table-box-meta">{{ $t['order_no'] }}</span>
                                <span class="ot-table-box-meta">{{ $t['items_count'] }} items</span>
                            @else
                                <span class="ot-table-box-meta ot-table-box-meta--free">Available</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Order punch screen --}}
    <div id="otOrderScreen" class="d-none">
        <header class="rp-topbar">
            <div class="rp-topbar-brand">
                <button type="button" class="btn btn-sm btn-outline-secondary ot-back-tables" id="otBackTables" title="Tables">
                    <i class="bi bi-arrow-left"></i>
                </button>
                <span class="rp-brand-mark" aria-hidden="true"><i class="bi bi-cup-hot-fill"></i></span>
                <div class="rp-brand-text">
                    <span class="rp-brand-title">Order Taker</span>
                    <span class="rp-brand-sub" id="otTableLabel">Table —</span>
                </div>
            </div>
            <div class="rp-search">
                <i class="bi bi-search rp-search-icon" aria-hidden="true"></i>
                <input type="search" id="otProductSearch" class="form-control form-control-sm" placeholder="Search menu…" autocomplete="off">
            </div>
            <div class="rp-topbar-actions">
                <span class="badge rp-badge-order d-none" id="otOrderNoBadge"></span>
                <button type="button" class="btn btn-sm rp-order-tab is-active" id="otTabMenu" data-mode="menu">
                    <i class="bi bi-grid-3x3-gap-fill"></i> Menu
                </button>
                <button type="button" class="btn btn-sm rp-order-tab" id="otTabCart" data-mode="cart">
                    <i class="bi bi-bag-check"></i> Cart
                    <span class="badge rp-badge-count rp-badge-pending" id="otCartTabCount">0</span>
                </button>
            </div>
        </header>

        <div class="rp-order-zone">
            <div class="rp-order-fields" id="otOrderFieldsPanel">
                <div class="rp-order-bar">
                    <div class="rp-order-bar-fields ot-guest-fields" id="otGuestFields">
                        <input type="text" id="otGuestName" class="form-control form-control-sm" maxlength="120" placeholder="Guest name" aria-label="Guest name">
                        <select id="otWaiterName" class="form-select form-select-sm" aria-label="Waiter">
                            <option value="">Waiter…</option>
                            @foreach($waiters as $w)
                                <option value="{{ $w->name }}">{{ $w->name }}</option>
                            @endforeach
                        </select>
                        <select id="otServeMeal" class="form-select form-select-sm" aria-label="Meal">
                            <option value="">Meal…</option>
                            @foreach($serveMealsJson as $meal)
                                <option value="{{ $meal['key'] }}" data-time="{{ $meal['time'] }}">{{ $meal['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="rp-order-bar-fields ot-guest-fields ot-guest-fields--readonly d-none" id="otGuestReadonly">
                        <span class="ot-readonly-chip" id="otReadonlyGuest"></span>
                        <span class="ot-readonly-chip" id="otReadonlyWaiter"></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="rp-body">
            <div class="rp-menu-panel">
                <div class="rp-menu-head">
                    <div class="rp-menu-cats" id="otMenuCats" role="tablist" aria-label="Menu categories"></div>
                </div>
                <div class="rp-menu-grid" id="otMenuGrid"></div>
            </div>

            <aside class="rp-checkout">
                <div class="rp-checkout-head">
                    <div class="rp-checkout-head-main">
                        <i class="bi bi-receipt-cutoff" aria-hidden="true"></i>
                        <span>Your order</span>
                        <span class="rp-cart-count" id="otCartCount">0</span>
                    </div>
                    <button type="button" class="btn btn-sm rp-cart-view-btn" id="otToggleCartView" title="Cart full view">
                        <i class="bi bi-arrows-fullscreen"></i>
                    </button>
                </div>
                <div class="rp-cart-lines" id="otCartLines"></div>
            </aside>
        </div>

        <div class="rp-pay-dock">
            <div class="rp-checkout-foot">
                <div class="rp-bill-summary">
                    <div class="rp-bill-summary-head">Bill Summary</div>
                    <div class="rp-total-row"><span>Items</span><span id="otSumItems">0</span></div>
                    <div class="rp-total-row"><span>Subtotal</span><span id="otSumSubtotal">0.00</span></div>
                    <div class="rp-total-row grand"><span>Total</span><span id="otSumGrand">0.00</span></div>
                </div>
                <div class="rp-actions">
                    <button type="button" class="btn btn-sm btn-rp-primary" id="otSendBtn">
                        <i class="bi bi-send"></i> <span id="otSendBtnLabel">Send to Kitchen</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<form id="otSubmitForm" method="POST" action="{{ route('order-taker.store') }}" class="d-none">
    @csrf
    <input type="hidden" name="_method" id="otFormMethod" value="POST">
    <input type="hidden" name="customer_type" value="mess_use">
    <input type="hidden" name="guest_name" id="otFormGuestName" value="">
    <input type="hidden" name="waiter_name" id="otFormWaiterName" value="">
    <input type="hidden" name="table_id" id="otFormTableId" value="">
    <input type="hidden" name="serve_meal" id="otFormServeMeal" value="">
    <input type="hidden" name="serve_date" id="otFormServeDate" value="">
    <input type="hidden" name="serve_time" id="otFormServeTime" value="">
    <input type="hidden" name="items" id="otFormItems" value="">
</form>
@endsection

@section('scripts')
@php
    $otBootstrap = [
        'csrf' => csrf_token(),
        'currency' => $currency,
        'products' => $productJs,
        'menuCategories' => $menuCategories,
        'tableBoard' => $tableBoard,
        'settings' => [
            'tax_mode' => $taxMode,
            'default_tax_rate' => $defaultTaxRate,
        ],
        'serveMeals' => $serveMealsJson,
        'resumeOrderId' => $resumedOrder?->id,
        'resumeOrderNo' => $resumedOrder?->order_no,
        'resumeTableId' => $resumedOrder?->table_id,
        'resumeTableName' => $resumedOrder?->table?->name,
        'resumeGuestName' => $resumedOrder?->guest_name,
        'resumeWaiterName' => $resumedOrder?->waiter_name,
        'resumeItems' => $resumeItems,
        'startTableId' => $startTableId,
        'hasSession' => $session !== null,
        'canVoidKitchenItems' => auth()->user()?->bypassesModulePermissions() ?? false,
        'routes' => [
            'store' => route('order-taker.store'),
            'update' => $updateStub,
            'index' => route('order-taker.index'),
        ],
    ];
@endphp
<script>
window.ORDER_TAKER_BOOTSTRAP = @json($otBootstrap);
</script>
<script src="{{ asset('js/order-taker-app.js') }}?v=1"></script>
@endsection
