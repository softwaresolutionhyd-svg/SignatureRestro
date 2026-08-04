@extends('layouts.admin')
@section('title', 'Order Taker — ' . config('app.name'))
@section('page-title', 'Order Taker')

@push('head')
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
<link rel="stylesheet" href="{{ asset('css/restaurant-pos.css') }}?v=64">
<link rel="stylesheet" href="{{ asset('css/order-taker-pos.css') }}?v=25">
@endpush

@section('content')
@php
    $defaultServiceType = old('service_type', $resumedOrder?->serviceTypeKey() ?? $startServiceType ?? 'dine_in');
    if (! array_key_exists($defaultServiceType, \App\Models\PosOrder::serviceTypeLabels())) {
        $defaultServiceType = 'dine_in';
    }
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
        'kitchen_printed' => $i->kitchen_printed_at !== null,
        'kitchen_locked_qty' => ($i->isKitchenServed() || $i->kitchen_pending || $i->kitchen_printed_at !== null) ? (float) $i->qty : 0,
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
                    <p>POS session abhi open nahi hui. Jab tak cashier ya manager POS session open na kare, order punch nahi ho sakta.</p>
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
                <span class="ot-legend-sep"></span>
                <button type="button" class="btn btn-sm ot-quick-type" data-service="takeaway">Takeaway</button>
                <button type="button" class="btn btn-sm ot-quick-type" data-service="delivery">Delivery</button>
            </div>
            @if(count($tableBoardGroups ?? []) > 0)
                <div class="ot-area-filters" id="otAreaFilters" role="tablist" aria-label="Sitting areas">
                    <button type="button" class="ot-area-filter-btn" data-area-key="all" role="tab">All</button>
                    @foreach(($tableBoardGroups ?? []) as $idx => $area)
                        <button type="button"
                                class="ot-area-filter-btn{{ $idx === 0 ? ' is-active' : '' }}"
                                data-area-key="{{ $area['id'] ?? ('name:'.$area['name']) }}"
                                role="tab"
                                aria-selected="{{ $idx === 0 ? 'true' : 'false' }}">{{ $area['name'] }}</button>
                    @endforeach
                </div>
            @endif
            <div class="ot-board-tabs" id="otBoardTabs" role="tablist" aria-label="Order taker panels">
                <button type="button" class="btn btn-sm rp-order-tab is-active" id="otBoardTabTables" data-board-tab="tables" role="tab" aria-selected="true">
                    <i class="bi bi-grid-3x3-gap-fill"></i> Tables
                </button>
                <button type="button" class="btn btn-sm rp-order-tab" id="otBoardTabPending" data-board-tab="pending" role="tab" aria-selected="false">
                    <i class="bi bi-hourglass-split"></i> Pending Orders
                    <span class="badge rp-badge-count rp-badge-pending" id="otPendingCount">{{ count($allOrders ?? []) }}</span>
                </button>
            </div>

            <div class="ot-table-board-body">
                <div class="ot-board-panel" id="otBoardPanelTables" data-board-panel="tables">
                    <div class="ot-table-areas" id="otTableGrid">
                    @foreach(($tableBoardGroups ?? []) as $idx => $area)
                        <section class="ot-sitting-area{{ $idx === 0 ? '' : ' d-none' }}"
                                 data-area-key="{{ $area['id'] ?? ('name:'.$area['name']) }}">
                            <h3 class="ot-sitting-area-title">{{ $area['name'] }}</h3>
                            <div class="ot-table-grid">
                                @foreach($area['tables'] as $t)
                                    <button type="button"
                                            class="ot-table-box ot-table-box--{{ $t['status'] }}"
                                            data-table-id="{{ $t['id'] }}"
                                            data-table-name="{{ $t['name'] }}"
                                            data-status="{{ $t['status'] }}"
                                            data-order-id="{{ $t['order_id'] ?? '' }}"
                                            data-amendable="{{ $t['amendable'] ? '1' : '0' }}"
                                            aria-label="{{ $area['name'] }} — Table {{ $t['name'] }} — {{ $t['status'] === 'free' ? 'free' : 'reserved' }}">
                                        <span class="ot-table-shape" aria-hidden="true">
                                            <span class="ot-chair ot-chair--n"></span>
                                            <span class="ot-chair ot-chair--e"></span>
                                            <span class="ot-chair ot-chair--s"></span>
                                            <span class="ot-chair ot-chair--w"></span>
                                            <span class="ot-table-top">
                                                <span class="ot-table-box-no">{{ $t['name'] }}</span>
                                            </span>
                                        </span>
                                        @if($t['status'] === 'occupied')
                                            <span class="ot-table-box-meta">{{ $t['order_no'] }}</span>
                                            <span class="ot-table-box-meta">{{ $t['items_count'] }} items</span>
                                            @if(!empty($t['occupied_at']))
                                                <span class="ot-table-box-meta ot-table-timer"
                                                      data-occupied-at="{{ $t['occupied_at'] }}"
                                                      title="Order punch ke baad ka time">00:00</span>
                                            @endif
                                        @else
                                            <span class="ot-table-box-meta ot-table-box-meta--free">Available</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                    </div>
                </div>

                <div class="ot-board-panel d-none" id="otBoardPanelPending" data-board-panel="pending" aria-label="Pending orders">
                    <div class="rp-bills-head ot-pending-head">
                        <div class="rp-bills-head-main">
                            <span class="rp-bills-head-title">Pending Orders</span>
                            <span class="rp-bills-head-count">{{ count($allOrders ?? []) }} bill{{ count($allOrders ?? []) === 1 ? '' : 's' }}</span>
                        </div>
                        <span class="rp-bills-head-hint">Order kholne ke liye card par click karein.</span>
                    </div>
                    <div class="rp-menu-grid rp-bills-grid ot-pending-grid" id="otPendingOrdersGrid">
                        @forelse(($allOrders ?? []) as $mo)
                            @php
                                $canOpen = (bool) ($mo['amendable'] ?? false);
                                $canMove = ($mo['service_type'] ?? null) === 'dine_in' && ! empty($mo['table_id']);
                            @endphp
                            <div class="rp-order-card rp-order-card--grid rp-order-card--pending-wrap{{ $canOpen ? '' : ' opacity-75' }}"
                                 data-order-id="{{ $mo['id'] }}"
                                 data-order-no="{{ $mo['order_no'] }}"
                                 data-service-type="{{ $mo['service_type'] ?? '' }}"
                                 data-table-id="{{ $mo['table_id'] ?? '' }}"
                                 data-amendable="{{ $canOpen ? '1' : '0' }}">
                                <button type="button"
                                        class="rp-order-card-link text-start bg-transparent border-0 w-100"
                                        data-action="open-order"
                                        data-order-id="{{ $mo['id'] }}"
                                        data-amendable="{{ $canOpen ? '1' : '0' }}"
                                        @if(! $canOpen) disabled @endif>
                                        @if(!empty($mo['table_name']))
                                            <div class="rp-oc-table">Table {{ $mo['table_name'] }}</div>
                                        @endif
                                    <div class="rp-oc-no">
                                        {{ $mo['order_no'] }}
                                        @if(!empty($mo['is_split']))
                                            @php
                                                $splitTip = 'Split bill — ' . ($mo['split_label'] ?? 'Split');
                                            @endphp
                                            <span class="rp-oc-split-icon" title="{{ $splitTip }}" aria-label="{{ $splitTip }}">
                                                <i class="bi bi-scissors" aria-hidden="true"></i>
                                            </span>
                                        @endif
                                    </div>
                                    <div class="rp-oc-meta">
                                        {{ $mo['service_label'] }}
                                    </div>
                                    <div class="rp-oc-by">by: {{ $mo['punched_by'] ?? '—' }}</div>
                                    <div class="rp-oc-meta">{{ $currency }}{{ number_format((float) $mo['grand_total'], 0) }} · {{ $mo['items_count'] }} items</div>
                                    <div class="rp-oc-meta">{{ $mo['punched_at'] }}</div>
                                    <div class="rp-oc-open">Open order <i class="bi bi-arrow-right-short"></i></div>
                                </button>
                                @if($canMove)
                                    <div class="rp-oc-move-wrap">
                                        <button type="button"
                                            class="btn btn-sm rp-oc-move-table"
                                            data-action="move-table"
                                            data-order-id="{{ $mo['id'] }}"
                                            data-order-no="{{ $mo['order_no'] }}"
                                            data-table-id="{{ $mo['table_id'] }}">
                                            <i class="bi bi-arrow-left-right"></i> Move Table
                                        </button>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="rp-empty rp-empty--menu">
                                <span class="rp-empty-icon"><i class="bi bi-hourglass-split"></i></span>
                                <span>Koi pending order nahi.</span>
                            </div>
                        @endforelse
                    </div>
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
                <input type="hidden" id="otServiceType" value="{{ $defaultServiceType }}">
                <div class="rp-order-bar">
                    <div class="rp-service-types" id="otServiceTypes" role="tablist" aria-label="Order type">
                        @foreach(\App\Models\PosOrder::serviceTypeLabels() as $key => $label)
                            <button type="button"
                                    class="rp-service-type{{ $defaultServiceType === $key ? ' is-active' : '' }}"
                                    data-type="{{ $key }}"
                                    role="tab"
                                    aria-selected="{{ $defaultServiceType === $key ? 'true' : 'false' }}">{{ $label }}</button>
                        @endforeach
                    </div>

                    <div class="rp-order-bar-fields" id="otServiceDetails">
                        <div class="rp-service-panel rp-service-panel--inline{{ $defaultServiceType === 'dine_in' ? '' : ' d-none' }}" id="otDineInPanel" data-service="dine_in">
                            @if($enableTables)
                                <span class="ot-readonly-chip" id="otSelectedTableChip">Table —</span>
                            @else
                                <input type="text" id="otTableNo" class="form-control form-control-sm" maxlength="50" placeholder="Table No." aria-label="Table No.">
                            @endif
                        </div>
                        <div class="rp-service-panel rp-service-panel--inline{{ $defaultServiceType === 'takeaway' ? '' : ' d-none' }}" id="otTakeawayPanel" data-service="takeaway">
                            <input type="tel" id="otTakeawayContact" class="form-control form-control-sm" maxlength="50"
                                   value="{{ old('room_no', ($resumedOrder?->serviceTypeKey() ?? $defaultServiceType) === 'takeaway' ? ($resumedOrder?->room_no ?? '') : '') }}"
                                   placeholder="Contact No." aria-label="Contact No." inputmode="tel">
                        </div>
                        <div class="rp-service-panel rp-service-panel--inline rp-service-panel--delivery{{ $defaultServiceType === 'delivery' ? '' : ' d-none' }}" id="otDeliveryPanel" data-service="delivery">
                            <input type="text" id="otDeliveryName" class="form-control form-control-sm" maxlength="120"
                                   value="{{ old('guest_name', $resumedOrder?->guest_name ?? '') }}"
                                   placeholder="Customer Name" aria-label="Customer Name">
                            <input type="text" id="otDeliveryPhone" class="form-control form-control-sm" maxlength="50"
                                   value="{{ old('room_no', $resumedOrder?->room_no ?? '') }}"
                                   placeholder="Phone No." aria-label="Phone No.">
                            <input type="text" id="otDeliveryAddress" class="form-control form-control-sm rp-field-address" maxlength="1000"
                                   value="{{ old('order_notes', $resumedOrder?->order_notes ?? '') }}"
                                   placeholder="Address" aria-label="Address">
                        </div>
                    </div>

                    <div class="ot-order-bar-actions">
                        <span class="ot-order-bar-total" id="otBarTotal">0.00</span>
                        <button type="button" class="btn btn-sm btn-rp-primary ot-send-bar-btn" id="otSendBtn">
                            <i class="bi bi-send"></i> <span id="otSendBtnLabel">Send to Kitchen</span>
                        </button>
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
                <div class="rp-bill-kitchen-notes-wrap">
                    <label class="rp-bill-kitchen-label" for="otBillKitchenNotes">Bill instructions</label>
                    <textarea id="otBillKitchenNotes" class="form-control form-control-sm rp-bill-kitchen-notes"
                              rows="2" maxlength="1000" placeholder="Complete bill note for kitchen…"
                              aria-label="Complete bill instructions">{{ old('kitchen_notes', $resumedOrder?->kitchen_notes ?? '') }}</textarea>
                </div>
            </aside>
        </div>

        <div class="rp-pay-dock ot-pay-dock-hidden" aria-hidden="true">
            <div class="rp-checkout-foot">
                <div class="rp-bill-summary">
                    <div class="rp-bill-summary-head">Bill Summary</div>
                    <div class="rp-total-row"><span>Items</span><span id="otSumItems">0</span></div>
                    <div class="rp-total-row"><span>Subtotal</span><span id="otSumSubtotal">0.00</span></div>
                    <div class="rp-total-row grand"><span>Total</span><span id="otSumGrand">0.00</span></div>
                </div>
            </div>
        </div>
    </div>
</div>

<form id="otSubmitForm" method="POST" action="{{ route('order-taker.store') }}" class="d-none">
    @csrf
    <input type="hidden" name="_method" id="otFormMethod" value="POST">
    <input type="hidden" name="customer_type" value="mess_use">
    <input type="hidden" name="service_type" id="otFormServiceType" value="{{ $defaultServiceType }}">
    <input type="hidden" name="guest_name" id="otFormGuestName" value="">
    <input type="hidden" name="room_no" id="otFormRoomNo" value="">
    <input type="hidden" name="order_notes" id="otFormOrderNotes" value="">
    <input type="hidden" name="table_id" id="otFormTableId" value="">
    <input type="hidden" name="kitchen_notes" id="otFormKitchenNotes" value="">
    <input type="hidden" name="items" id="otFormItems" value="">
</form>

<div class="modal fade" id="otMoveTableModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="otMoveTableTitle">Select New Table</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="px-3 pt-2" id="otMoveTableAreaTabs"></div>
            <div class="modal-body" id="otMoveTableBody" style="max-height:55vh;overflow-y:auto;"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="otConfirmBillModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content ot-confirm-bill-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="otConfirmBillTitle">Confirm Bill</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="ot-confirm-meta" id="otConfirmBillMeta"></div>
                <div class="ot-confirm-lines" id="otConfirmBillLines"></div>
                <div class="ot-confirm-notes d-none" id="otConfirmBillNotesWrap">
                    <div class="ot-confirm-notes-label">Bill instructions</div>
                    <div class="ot-confirm-notes-text" id="otConfirmBillNotes"></div>
                </div>
                <div class="ot-confirm-total">
                    <span>Total</span>
                    <strong id="otConfirmBillTotal">0.00</strong>
                </div>
                <p class="ot-confirm-hint mb-0">Confirm karein to kitchen slip jayegi. Ghalt item ho to Cancel karke theek karein.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal" id="otConfirmBillCancel">
                    Cancel
                </button>
                <button type="button" class="btn btn-rp-primary" id="otConfirmBillSubmit">
                    <i class="bi bi-send me-1"></i>
                    <span id="otConfirmBillSubmitLabel">Confirm & Send</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@php
    $moveTableStub = str_replace('999999999', '__ID__', route('order-taker.move-table', ['order' => 999999999]));
    $otBootstrap = [
        'csrf' => csrf_token(),
        'currency' => $currency,
        'products' => $productJs,
        'menuCategories' => $menuCategories,
        'tableBoard' => $tableBoard,
        'allOrders' => $allOrders ?? [],
        'pendingOrdersCount' => count($allOrders ?? []),
        'settings' => [
            'tax_mode' => $taxMode,
            'default_tax_rate' => $defaultTaxRate,
            'enable_tables' => $enableTables,
            'service_charge_enabled' => $serviceChargeEnabled ?? false,
            'service_charge_percent' => (float) ($serviceChargePercent ?? 0),
        ],
        'serviceTypeLabels' => \App\Models\PosOrder::serviceTypeLabels(),
        'defaultServiceType' => $defaultServiceType,
        'resumeOrderId' => $resumedOrder?->id,
        'resumeOrderNo' => $resumedOrder?->order_no,
        'resumeTableId' => $resumedOrder?->table_id,
        'resumeTableName' => $resumedOrder?->table?->name,
        'resumeServiceType' => $resumedOrder?->serviceTypeKey(),
        'resumeGuestName' => $resumedOrder?->guest_name,
        'resumeRoomNo' => $resumedOrder?->room_no,
        'resumeOrderNotes' => $resumedOrder?->order_notes,
        'resumeKitchenNotes' => $resumedOrder?->kitchen_notes,
        'resumeItems' => $resumeItems,
        'startTableId' => $startTableId,
        'startServiceType' => $startServiceType,
        'hasSession' => $session !== null,
        'canVoidKitchenItems' => auth()->user()?->bypassesModulePermissions() ?? false,
        'routes' => [
            'store' => route('order-taker.store'),
            'update' => $updateStub,
            'index' => route('order-taker.index'),
            'board' => route('order-taker.board'),
            'orderData' => str_replace('999999999', '__ID__', route('order-taker.order-data', ['order' => 999999999])),
            'moveTable' => $moveTableStub,
        ],
    ];
@endphp
<script>
window.ORDER_TAKER_BOOTSTRAP = @json($otBootstrap);
</script>
<script src="{{ asset('js/order-taker-app.js') }}?v=25"></script>
@endsection
