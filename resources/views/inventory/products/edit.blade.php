@extends('layouts.admin')

@section('title', 'Edit Product - Inventory - ' . config('app.name'))
@section('page_title', 'Inventory / Products / Edit')

@section('content')
    @include('inventory.partials.subnav')

    @if(session('pos_resume_hint'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <div class="fw-semibold mb-1">POS sale ruki — component stock kam thi</div>
            @if(session('error'))
                <div class="small mb-2">{{ session('error') }}</div>
            @endif
            <a href="{{ session('pos_resume_hint') }}" class="btn btn-sm btn-dark">POS par wapas</a>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

@php
    $productReturn = old('return', $productReturnPath ?? '');
    $productReturnCancelHref = $productReturn === ''
        ? route('inventory.products.index')
        : (preg_match('#^https?://#i', $productReturn) ? $productReturn : url($productReturn));
@endphp

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card kpi-card shadow-sm">
                <div class="card-body">
                    <div class="kpi-label">Purchased This Month</div>
                    <div class="kpi-value">{{ fmt_num((float)$purchaseSummary['month_qty_base'], 3) }}</div>
                    <div class="small text-secondary">{{ $purchaseSummary['base_uom'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card kpi-card shadow-sm">
                <div class="card-body">
                    <div class="kpi-label">Last Purchase Rate</div>
                    <div class="kpi-value">{{ fmt_num((float)$purchaseSummary['last_rate_base'], 4) }}</div>
                    <div class="small text-secondary">per {{ $purchaseSummary['base_uom'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card kpi-card shadow-sm">
                <div class="card-body">
                    <div class="kpi-label">Purchase Entries (Month)</div>
                    <div class="kpi-value">{{ fmt_num($purchaseSummary['rows_count_this_month'], 0) }}</div>
                    <div class="small text-secondary">received lines</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card kpi-card shadow-sm">
                <div class="card-body">
                    <div class="kpi-label">Total Sale This Month</div>
                    <div class="kpi-value">{{ fmt_num((float)$purchaseSummary['sale_month_qty_base'], 3) }}</div>
                    <div class="small text-secondary">{{ $purchaseSummary['base_uom'] }} (Amount: {{ fmt_num((float)$purchaseSummary['sale_month_amount'], 2) }})</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <div class="fw-semibold">Edit product</div>
            <span class="text-secondary small">On hand: <span class="fw-semibold">{{ fmt_num((float)$product->qty_on_hand, 3) }}</span> {{ $product->uom }}</span>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('inventory.products.update', $product) }}" enctype="multipart/form-data">
                @method('PUT')
                @if($productReturn !== '')
                    <input type="hidden" name="return" value="{{ $productReturn }}">
                @endif
                @include('inventory.products.form', ['bomStandardCost' => $bomStandardCost ?? null])
            </form>
        </div>
    </div>

    <div class="card shadow-sm mt-3 border-0 product-purchase-section" data-requires-purchase style="border-left:4px solid #f59e0b!important;@if(!($product->for_purchase ?? true))display:none;@endif">
        <div class="card-header bg-white fw-semibold d-flex align-items-center justify-content-between">
            <span><i class="bi bi-exclamation-triangle me-1 text-warning"></i> Record Wastage</span>
            <span class="text-secondary small">Current stock: {{ fmt_num((float)$product->qty_on_hand, 3) }} {{ $product->uom }}</span>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('inventory.moves.store') }}" class="row g-2 align-items-end">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="type" value="wastage">

                <div class="col-12 col-md-2">
                    <label class="form-label small fw-semibold">Qty</label>
                    <input type="number" step="0.001" min="0.001" name="qty_uom" class="form-control" required placeholder="0.000">
                </div>

                <div class="col-12 col-md-2">
                    <label class="form-label small fw-semibold">UOM</label>
                    <select name="uom" class="form-select" required>
                        @foreach($product->uomsForForms() as $uomRow)
                            <option value="{{ $uomRow['uom'] }}">{{ $uomRow['uom'] }}@if((float) ($uomRow['factor'] ?? 0) === 1.0) (base) @endif</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label small fw-semibold">Reference (optional)</label>
                    <input type="text" name="reference" class="form-control" maxlength="80" placeholder="e.g. spoilage-2026-04">
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label small fw-semibold">Reason</label>
                    <input type="text" name="note" class="form-control" maxlength="255" required placeholder="e.g. Expired / damaged during handling">
                </div>

                <div class="col-12 col-md-1 d-grid">
                    <button type="submit" class="btn btn-warning">Save</button>
                </div>
            </form>
            <div class="form-text mt-2">This will reduce stock and create a WASTAGE move entry.</div>
        </div>
    </div>

    @if(!empty($canManufacturing))
    @php
        $productReturnPath = route('inventory.products.edit', $product, false);
    @endphp
    <div class="card shadow-sm mt-3 border-0" style="border-left:4px solid #6366f1!important;">
        <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="fw-semibold"><i class="bi bi-diagram-3 me-1 text-primary"></i> Recipe</div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('manufacturing.boms.index', ['finished_product' => $product->id, 'return' => $productReturnPath]) }}" class="btn btn-sm btn-outline-secondary">View all for this product</a>
                <a href="{{ route('manufacturing.boms.create', ['finished_product_id' => $product->id, 'return' => $productReturnPath]) }}" class="btn btn-sm btn-primary">+ New Recipe</a>
            </div>
        </div>
        <div class="card-body py-3">
            @include('inventory.products.partials.recipe-detail', [
                'product' => $product,
                'productBoms' => $productBoms,
                'bomLineCosts' => $bomLineCosts,
                'productReturnPath' => $productReturnPath,
            ])
        </div>
    </div>
    @endif

    @if(!empty($canManufacturing))
    @php
        $productReturnPath = route('inventory.products.edit', $product, false);
    @endphp
    <div class="card shadow-sm mt-3 border-0" style="border-left:4px solid #0ea5e9!important;">
        <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="fw-semibold"><i class="bi bi-boxes me-1 text-info"></i> Use as Ingredient in Recipes</div>
            <a href="{{ route('manufacturing.boms.index', ['q' => $product->sku, 'return' => $productReturnPath]) }}" class="btn btn-sm btn-outline-secondary">Open recipe list</a>
        </div>
        <div class="card-body py-2">
            @if(($componentUsedInBoms ?? collect())->isEmpty())
                <p class="text-secondary small mb-0">This product is not used as an ingredient in any recipe yet.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>Recipe</th>
                            <th>Finished Product</th>
                            <th>Lines</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($componentUsedInBoms as $bom)
                            <tr>
                                <td class="fw-semibold">
                                    <a href="{{ route('manufacturing.boms.show', ['bom' => $bom, 'return' => $productReturnPath]) }}" class="text-decoration-none">{{ $bom->name }}</a>
                                </td>
                                <td>
                                    <span class="text-secondary small">{{ $bom->finishedProduct->sku ?? '' }}</span>
                                    {{ $bom->finishedProduct->name ?? '—' }}
                                </td>
                                <td>{{ $bom->lines_count }}</td>
                                <td>
                                    @if($bom->active)
                                        <span class="badge bg-success bg-opacity-10 text-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('manufacturing.boms.show', ['bom' => $bom, 'return' => $productReturnPath]) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                    <a href="{{ route('manufacturing.boms.edit', ['bom' => $bom, 'return' => $productReturnPath]) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
    @endif

    <div class="card shadow-sm mt-3" data-requires-purchase @if(!($product->for_purchase ?? true)) style="display:none" @endif>
        <div class="card-header bg-white fw-semibold">Purchase History (Date-wise)</div>
        @if($errors->purchaseHistoryEdit->any())
            <div class="alert alert-danger m-3 mb-0 py-2">
                <ul class="mb-0 small">
                    @foreach($errors->purchaseHistoryEdit->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <div class="table-responsive">
            @php $canEditPurchaseHistory = auth()->user()?->isPlatformSuperAdmin(); @endphp
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>PO</th>
                    <th>Vendor</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Rate</th>
                    <th class="text-end">Base Qty</th>
                    <th class="text-end">Base Rate</th>
                    @if($canEditPurchaseHistory)
                        <th class="text-end">Action</th>
                    @endif
                </tr>
                </thead>
                <tbody>
                @forelse($history as $h)
                    <tr>
                        <td class="text-secondary">{{ $h['date'] }}</td>
                        <td class="fw-semibold">{{ $h['po_number'] }}</td>
                        <td class="text-secondary">{{ $h['vendor'] }}</td>
                        <td class="text-end">{{ fmt_num((float)$h['qty'], 3) }} {{ $h['uom'] }}</td>
                        <td class="text-end">{{ fmt_num((float)$h['rate'], 4) }} / {{ $h['uom'] }}</td>
                        <td class="text-end">{{ fmt_num((float)$h['qty_base'], 3) }} {{ $h['base_uom'] }}</td>
                        <td class="text-end fw-semibold">{{ fmt_num((float)$h['rate_base'], 4) }} / {{ $h['base_uom'] }}</td>
                        @if($canEditPurchaseHistory)
                            <td class="text-end">
                                <details class="d-inline-block text-start">
                                    <summary class="btn btn-sm btn-outline-primary">Edit</summary>
                                    <div class="card mt-2 p-2" style="min-width:360px;">
                                        <form method="POST" action="{{ route('inventory.products.purchase-lines.update', [$product, $h['line_id']]) }}" class="row g-2">
                                            @csrf
                                            @method('PUT')
                                            @if($productReturn !== '')
                                                <input type="hidden" name="return" value="{{ $productReturn }}">
                                            @endif
                                            <div class="col-4">
                                                <label class="form-label small mb-1">Qty</label>
                                                <input type="number" step="0.001" min="0.001" name="qty" class="form-control form-control-sm" required
                                                       value="{{ old('qty', $h['qty']) }}">
                                            </div>
                                            <div class="col-4">
                                                <label class="form-label small mb-1">UOM</label>
                                                <select name="uom" class="form-select form-select-sm" required>
                                                    @foreach($product->uomsForForms() as $uomRow)
                                                        <option value="{{ $uomRow['uom'] }}" @selected((string) old('uom', $h['uom']) === (string) $uomRow['uom'])>
                                                            {{ $uomRow['uom'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-4">
                                                <label class="form-label small mb-1">Rate</label>
                                                <input type="number" step="0.0001" min="0" name="unit_price" class="form-control form-control-sm" required
                                                       value="{{ old('unit_price', $h['rate']) }}">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label small mb-1">Description (optional)</label>
                                                <input type="text" name="description" class="form-control form-control-sm" maxlength="255"
                                                       value="{{ old('description') }}" placeholder="Optional note">
                                            </div>
                                            <div class="col-12 d-flex justify-content-end">
                                                <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-text small">This edits purchase line values and PO totals for correction history.</div>
                                            </div>
                                        </form>
                                    </div>
                                </details>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $canEditPurchaseHistory ? 8 : 7 }}" class="text-center text-secondary py-4">No purchase history yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    (function () {
        const purchaseSwitch = document.getElementById('forPurchaseSwitch');
        const purchaseSections = document.querySelectorAll('[data-requires-purchase]');
        if (!purchaseSwitch || !purchaseSections.length) return;

        function togglePurchaseSections() {
            const show = purchaseSwitch.checked;
            purchaseSections.forEach(function (el) {
                el.style.display = show ? '' : 'none';
            });
        }

        purchaseSwitch.addEventListener('change', togglePurchaseSections);
        togglePurchaseSections();
    })();
</script>
@endsection
