@extends('layouts.admin')
@section('title', 'Restaurant POS — ' . config('app.name'))
@section('page-title', 'Restaurant POS')

@push('head')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/restaurant-pos.css') }}?v=32">
@endpush

@section('content')
@php
    $defaultServiceType = old('service_type', $posSettings['resume_service_type'] ?? 'dine_in');
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
            'cost' => (float) $p->cost,
            'gas_charges' => (float) $p->gasChargesAmount(),
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
        // POS tabs: direct sub-categories under a top-level root (e.g. Menu / All Products).
        if ($p->category->parent->parent_id !== null) {
            continue;
        }
        $cat = $p->category;
        $menuCategoryMap[$cat->id] = [
            'id' => (int) $cat->id,
            'name' => (string) $cat->name,
            'parent_id' => (int) $cat->parent_id,
            'parent_name' => (string) $cat->parent->name,
            'is_sub' => true,
            'sort' => strtolower($cat->name),
        ];
    }
    $menuCategories = collect($menuCategoryMap)
        ->sortBy('sort')
        ->values()
        ->all();
    $resumeItems = collect($resumedOrder?->items ?? [])->map(fn ($i) => [
        'product_id' => $i->product_id,
        'uom' => $i->uom,
        'qty' => (float) $i->qty,
        'unit_price' => (float) $i->unit_price,
        'tax_percent' => (float) $i->tax_percent,
        'notes' => (string) ($i->notes ?? ''),
        'kitchen_served' => $i->isKitchenServed(),
        'kitchen_pending' => (bool) $i->kitchen_pending,
    ])->values();
    $resumeStub = str_replace('999999999', '__ID__', route('restaurant-pos.resume', ['order' => 999999999]));
@endphp

<div class="restaurant-pos-app">
    <header class="rp-topbar">
        <div class="rp-topbar-brand">
            <span class="rp-brand-mark" aria-hidden="true"><i class="bi bi-cup-hot-fill"></i></span>
            <div class="rp-brand-text">
                <span class="rp-brand-title">Restaurant</span>
                <span class="rp-brand-sub">{{ $session->business_date?->format('d M Y') ?? now()->format('d M Y') }}</span>
            </div>
        </div>
        <div class="rp-search">
            <i class="bi bi-search rp-search-icon" aria-hidden="true"></i>
            <input type="search" id="rpProductSearch" class="form-control form-control-sm" placeholder="Search menu…" autocomplete="off">
        </div>
        <div class="rp-topbar-actions">
            @if($resumedOrder)
                <span class="badge rp-badge-order">{{ $resumedOrder->order_no }}</span>
            @endif
            <button type="button" class="btn btn-sm rp-order-tab" id="rpTabMenu" data-mode="menu">
                <i class="bi bi-grid-3x3-gap-fill"></i> Menu
            </button>
            <button type="button" class="btn btn-sm rp-order-tab" id="rpTabCart" data-mode="cart">
                <i class="bi bi-bag-check"></i> Cart
            </button>
            <button type="button" class="btn btn-sm rp-order-tab" id="rpTabPending" data-mode="pending">
                <i class="bi bi-hourglass-split"></i> Pending
                <span class="badge rp-badge-count rp-badge-pending" id="rpPendingCount">{{ $heldOrders->count() }}</span>
            </button>
            <button type="button" class="btn btn-sm rp-order-tab" id="rpTabPaid" data-mode="paid">
                <i class="bi bi-check-circle"></i> Paid
                <span class="badge rp-badge-count rp-badge-paid" id="rpPaidCount">{{ $paidOrders->count() }}</span>
            </button>
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

    <div class="rp-order-zone">
        <div class="rp-order-fields" id="rpOrderFieldsPanel">
            <input type="hidden" id="rpServiceType" value="{{ $defaultServiceType }}">
            <div class="rp-order-bar">
                <div class="rp-service-types" id="rpServiceTypes" role="tablist" aria-label="Order type">
                    @foreach(\App\Models\PosOrder::serviceTypeLabels() as $key => $label)
                        <button type="button"
                                class="rp-service-type{{ $defaultServiceType === $key ? ' is-active' : '' }}"
                                data-type="{{ $key }}"
                                role="tab"
                                aria-selected="{{ $defaultServiceType === $key ? 'true' : 'false' }}">{{ $label }}</button>
                    @endforeach
                </div>

                <div class="rp-order-bar-fields" id="rpServiceDetails">
                    <div class="rp-service-panel rp-service-panel--inline d-none" id="rpDineInPanel" data-service="dine_in">
                        @if($posSettings['enable_tables'] ?? false)
                            <select id="rpTable" class="form-select form-select-sm" aria-label="Table No.">
                                <option value="">Table…</option>
                                @foreach($tables as $t)
                                    <option value="{{ $t->id }}" @selected(($posSettings['resume_table_id'] ?? null) === (int) $t->id)>{{ $t->name }}</option>
                                @endforeach
                            </select>
                        @else
                            <input type="text" id="rpTableNo" class="form-control form-control-sm" maxlength="50"
                                   value="{{ old('guest_name', $posSettings['resume_guest_name'] ?? '') }}"
                                   placeholder="Table No." aria-label="Table No.">
                        @endif
                    </div>
                    <div class="rp-service-panel rp-service-panel--inline rp-service-panel--delivery d-none" id="rpDeliveryPanel" data-service="delivery">
                        <input type="text" id="rpDeliveryName" class="form-control form-control-sm" maxlength="120"
                               value="{{ old('guest_name', $posSettings['resume_guest_name'] ?? '') }}"
                               placeholder="Customer Name" aria-label="Customer Name">
                        <input type="text" id="rpDeliveryPhone" class="form-control form-control-sm" maxlength="50"
                               value="{{ old('room_no', $posSettings['resume_room_no'] ?? '') }}"
                               placeholder="Phone No." aria-label="Phone No.">
                        <input type="text" id="rpDeliveryAddress" class="form-control form-control-sm rp-field-address" maxlength="1000"
                               value="{{ old('order_notes', $posSettings['resume_order_notes'] ?? '') }}"
                               placeholder="Address" aria-label="Address">
                    </div>
                </div>

                @if($posSettings['show_customer_section'] ?? true)
                    <div class="rp-credit-inline" id="rpCreditBlock">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="rpCreditToggle"
                                   @checked(old('is_credit', $posSettings['resume_is_credit'] ?? false))>
                            <label class="form-check-label small text-danger fw-semibold" for="rpCreditToggle">Credit</label>
                        </div>
                        <input type="text" id="rpContactSearch" class="form-control form-control-sm rp-contact-search"
                               placeholder="Contact…" autocomplete="off" aria-label="Search contact">
                        <div id="rpSelectedContactWrap" class="d-none small px-2 py-0 rounded border bg-light d-inline-flex align-items-center gap-1 rp-selected-contact">
                            <span id="rpSelectedContact"></span>
                            <button type="button" class="btn btn-sm btn-link text-danger p-0 lh-1" id="rpClearContact" aria-label="Clear contact">×</button>
                        </div>
                        <div id="rpContactDropdown" class="dropdown-menu show d-none border shadow-sm"></div>
                    </div>
                @endif
            </div>
        </div>

        <div class="rp-order-line-wrap d-none" id="rpOrderLinePanel">
            <div class="rp-order-line" id="rpOrderLine"></div>
        </div>
    </div>

    <div class="rp-body">
        <div class="rp-menu-panel">
            <div class="rp-menu-head">
                <div class="rp-menu-cats" id="rpMenuCats" role="tablist" aria-label="Menu categories"></div>
            </div>
            <div class="rp-menu-grid" id="rpMenuGrid"></div>
        </div>

        <aside class="rp-checkout">
            <div class="rp-checkout-head">
                <div class="rp-checkout-head-main">
                    <i class="bi bi-receipt-cutoff" aria-hidden="true"></i>
                    <span>Your order</span>
                    <span class="rp-cart-count" id="rpCartCount">0</span>
                </div>
                <button type="button" class="btn btn-sm rp-cart-view-btn" id="rpToggleCartView" title="Cart full view">
                    <i class="bi bi-arrows-fullscreen"></i>
                </button>
            </div>

            <div class="rp-cart-lines" id="rpCartLines"></div>
        </aside>
    </div>

    <div class="rp-pay-dock">
        <div class="rp-checkout-foot">
            <div class="rp-bill-summary">
                <div class="rp-bill-summary-head">Bill Summary</div>
                <div class="rp-total-row"><span>Items</span><span id="rpSumItems">0</span></div>
                <div class="rp-total-row"><span>Subtotal</span><span id="rpSumSubtotal">0.00</span></div>
                @if($posSettings['show_discount'] ?? true)
                    <div class="rp-total-row rp-total-row-adjust" id="rpDiscountRow">
                        <span class="rp-adjust-label">
                            Discount
                            <input type="number" id="rpBillDiscount" class="form-control form-control-sm rp-summary-pct"
                                   min="0" step="0.01" title="Bill discount %"
                                   value="{{ $posSettings['resume_bill_discount_percent'] ?? 0 }}">
                            <span class="rp-pct-sym">%</span>
                        </span>
                        <span id="rpSumDiscount">0.00</span>
                    </div>
                @endif
                <div class="rp-total-row grand"><span>Total</span><span id="rpSumGrand">0.00</span></div>
            </div>

            <div id="rpPaymentsBlock" class="rp-pay-method">
                <label class="form-label small mb-0">Payment</label>
                <select id="rpPayMethod" class="form-select form-select-sm">
                    <option value="cash">Cash</option>
                    <option value="card">Card</option>
                    <option value="bank">Bank</option>
                </select>
            </div>

            <div class="rp-actions">
                @if($posSettings['show_hold_button'] ?? true)
                    <button type="button" class="btn btn-outline-warning btn-sm" id="rpHoldBtn">
                        <i class="bi bi-send"></i> Hold &amp; Kitchen
                    </button>
                @endif
                <button type="button" class="btn btn-sm btn-rp-whatsapp d-none" id="rpWhatsappBtn" title="Customer ko WhatsApp par order confirm karein">
                    <i class="bi bi-whatsapp"></i> WhatsApp
                </button>
                <button type="button" class="btn btn-sm btn-rp-primary" id="rpPayBtn">
                    <i class="bi bi-credit-card"></i> Pay Now
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="rpRemoveReasonModal" tabindex="-1" aria-labelledby="rpRemoveReasonModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rp-pay-modal">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="rpRemoveReasonModalLabel">Item remove karein</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="small text-secondary mb-2">Ye item kitchen ko bhej diya gaya hai. Hataane ka reason likhein:</p>
                <p class="fw-semibold mb-2" id="rpRemoveItemName"></p>
                <label for="rpRemoveReason" class="form-label fw-semibold mb-1">Reason</label>
                <textarea class="form-control" id="rpRemoveReason" rows="3" maxlength="500" placeholder="Masalan: galat item select ho gaya"></textarea>
                <p class="text-danger small mb-0 mt-2 d-none" id="rpRemoveReasonError">Kam az kam 3 characters ka reason likhein.</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="rpRemoveConfirm">
                    <i class="bi bi-trash"></i> Remove
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="rpPayModal" tabindex="-1" aria-labelledby="rpPayModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered rp-pay-modal-dialog">
        <div class="modal-content rp-pay-modal">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="rpPayModalLabel">Cash Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
                <div class="rp-pay-modal-total">
                    <span class="rp-pay-modal-label">Total Amount</span>
                    <span class="rp-pay-modal-amount" id="rpPayModalTotal">0.00</span>
                </div>
                <div class="mb-3">
                    <label for="rpCashTendered" class="form-label fw-semibold mb-1">Customer ne diye</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text">Rs.</span>
                        <input type="number" class="form-control" id="rpCashTendered" min="0" step="0.01" inputmode="decimal" placeholder="0.00" autocomplete="off">
                    </div>
                </div>
                <div class="rp-pay-modal-return">
                    <span class="rp-pay-modal-label">Return</span>
                    <span class="rp-pay-modal-change" id="rpCashChange">0.00</span>
                </div>
                <p class="text-danger small mb-0 mt-2 d-none" id="rpCashInsufficient">Amount kam hai — bill se zyada ya barabar enter karein.</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-rp-primary" id="rpPayModalConfirm" disabled>
                    <i class="bi bi-printer"></i> Pay &amp; Print Bill
                </button>
            </div>
        </div>
    </div>
</div>

<form id="rpSubmitForm" method="POST" action="{{ route('restaurant-pos.checkout') }}" class="d-none">
    @csrf
    <input type="hidden" name="type" value="sale">
    <input type="hidden" name="sale_mode" value="customer">
    <input type="hidden" name="staff_include_gas" value="0">
    <input type="hidden" name="customer_type" value="mess_use">
    <input type="hidden" name="service_type" value="{{ $defaultServiceType }}">
    <input type="hidden" name="resume_order_id" value="{{ $resumedOrder?->id ?? '' }}">
    <input type="hidden" name="is_credit" value="0">
    <input type="hidden" name="contact_id" value="">
    <input type="hidden" name="table_id" value="">
    <input type="hidden" name="guest_name" value="">
    <input type="hidden" name="room_no" value="">
    <input type="hidden" name="order_notes" value="">
    <input type="hidden" name="items" value="">
    <input type="hidden" name="payments" value="">
    <input type="hidden" name="bill_tax_percent" value="0">
    <input type="hidden" name="bill_discount_percent" value="0">
    <input type="hidden" name="cash_tendered" value="">
    <input type="hidden" name="cash_change" value="">
    <input type="hidden" name="kitchen_voids" value="">
</form>
@endsection

@section('scripts')
@php
    $receiptStub = str_replace('999999999', '__ID__', route('restaurant-pos.receipt', ['order' => 999999999]));
    $restaurantBootstrap = [
        'csrf' => csrf_token(),
        'products' => $productJs,
        'menuCategories' => $menuCategories,
        'contacts' => $contacts->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'phone' => $c->phone])->values(),
        'settings' => $posSettings,
        'resumeItems' => $resumeItems,
        'resumeOrderId' => $resumedOrder?->id,
        'pendingBillsDetail' => $pendingBillsDetail ?? [],
        'paidBillsDetail' => $paidBillsDetail ?? [],
        'serviceTypeLabels' => \App\Models\PosOrder::serviceTypeLabels(),
        'tablesEnabled' => (bool) ($posSettings['enable_tables'] ?? false),
        'restaurantName' => config('app.name'),
        'routes' => [
            'checkout' => route('restaurant-pos.checkout'),
            'hold' => route('restaurant-pos.hold'),
            'sync' => route('restaurant-pos.sync'),
            'resume' => $resumeStub . '?ui=restaurant',
            'receipt' => $receiptStub,
        ],
    ];
@endphp
<script>
window.RESTAURANT_POS_BOOTSTRAP = @json($restaurantBootstrap);
</script>
<script src="{{ asset('js/restaurant-pos-app.js') }}?v=28"></script>
@endsection
