@php
    $productReturnPath = $productReturnPath ?? route('inventory.products.edit', $product, false);
@endphp

@if($productBoms->isEmpty())
    <p class="text-secondary small mb-0">No recipe yet. Create one to define ingredients and quantities for this product.</p>
@else
    @foreach($productBoms as $bom)
        @php
            $materialPerBatch = (float) ($bomLineCosts[$bom->id] ?? $bom->materialCostPerBatch());
            $standardPerUnit = (float) $bom->standardCostPerFinishedUnit();
            $batchQty = (float) $bom->batch_qty;
        @endphp
        <div @class(['recipe-block', 'mb-4 pb-4 border-bottom' => ! $loop->last, 'mb-0' => $loop->last])>
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
                <div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="fw-semibold fs-6">{{ $bom->name }}</span>
                        @if($bom->active)
                            <span class="badge bg-success bg-opacity-10 text-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </div>
                    <div class="small text-secondary mt-1">
                        Makes <strong>{{ fmt_num($batchQty, 3) }} {{ $product->uom }}</strong> per batch
                        · {{ $bom->lines->count() }} ingredient{{ $bom->lines->count() === 1 ? '' : 's' }}
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('manufacturing.boms.show', ['bom' => $bom, 'return' => $productReturnPath]) }}" class="btn btn-sm btn-outline-secondary">Full view</a>
                    <a href="{{ route('manufacturing.boms.edit', ['bom' => $bom, 'return' => $productReturnPath]) }}" class="btn btn-sm btn-outline-primary">Edit recipe</a>
                </div>
            </div>

            @if($bom->lines->isEmpty())
                <p class="text-secondary small mb-0">No ingredients added yet.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Ingredient</th>
                            <th class="text-end">Quantity</th>
                            <th class="text-end">Rate</th>
                            <th class="text-end">Amount</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($bom->lines as $line)
                            @php
                                $component = $line->component;
                                $qty = (float) $line->qty;
                                $uom = $line->effectiveUom();
                                $lineAmount = (float) $line->lineMaterialCostPerBatch();
                                $ratePerQtyUom = $qty > 0 ? ($lineAmount / $qty) : (float) ($component?->cost ?? 0);
                            @endphp
                            <tr>
                                <td class="text-secondary small">{{ $loop->iteration }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $component?->name ?? '—' }}</div>
                                    @if($component?->sku)
                                        <div class="small text-secondary">{{ $component->sku }}</div>
                                    @endif
                                </td>
                                <td class="text-end fw-semibold">{{ fmt_num($qty, 3) }} {{ $uom }}</td>
                                <td class="text-end text-secondary">{{ fmt_num($ratePerQtyUom, 4) }} / {{ $uom }}</td>
                                <td class="text-end fw-semibold">{{ fmt_num($lineAmount, 2) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot class="table-light">
                        <tr>
                            <th colspan="4" class="text-end">Total recipe cost (per batch)</th>
                            <th class="text-end">{{ fmt_num($materialPerBatch, 2) }}</th>
                        </tr>
                        @if($batchQty > 0)
                            <tr>
                                <th colspan="4" class="text-end">Cost per {{ $product->uom }} (÷ batch qty)</th>
                                <th class="text-end text-primary">{{ fmt_num($standardPerUnit, 4) }}</th>
                            </tr>
                        @endif
                        </tfoot>
                    </table>
                </div>
            @endif

            @if($bom->notes)
                <div class="small text-secondary mt-2"><strong>Notes:</strong> {{ $bom->notes }}</div>
            @endif
        </div>
    @endforeach
@endif
