@extends('layouts.admin')

@section('title', $bom->name . ' — BoM — ' . config('app.name'))

@section('content')
<div class="mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
        <h4 class="fw-bold mb-0">{{ $bom->name }}</h4>
        <div class="text-secondary small">
            Produces <strong>{{ $bom->finishedProduct->name }}</strong>
            ({{ fmt_num((float) $bom->batch_qty, 3) }} {{ $bom->finishedProduct->uom }} per batch)
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @if(!empty($bomReturnPath))
            <a href="{{ preg_match('#^https?://#i', $bomReturnPath) ? $bomReturnPath : url($bomReturnPath) }}" class="btn btn-outline-secondary btn-sm">Back to product</a>
        @endif
        <a href="{{ route('manufacturing.boms.edit', array_filter(['bom' => $bom, 'return' => $bomReturnPath ?? null], fn ($v) => $v !== null && $v !== '')) }}" class="btn btn-outline-primary btn-sm">Edit</a>
        <a href="{{ route('manufacturing.orders.create', ['bom_id' => $bom->id]) }}" class="btn btn-success btn-sm">New order from this BoM</a>
    </div>
</div>

@include('manufacturing.partials.subnav')

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card shadow-sm h-100 border-0 bg-light">
            <div class="card-body py-3">
                <div class="small text-secondary">Material cost / batch</div>
                <div class="fs-5 fw-bold">{{ fmt_num((float) $materialPerBatch, 2) }}</div>
                <div class="small text-secondary mt-1">Σ line costs (qty in stock UOM × component FIFO cost)</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100 border-0 bg-light">
            <div class="card-body py-3">
                <div class="small text-secondary">Standard cost / {{ $bom->finishedProduct->uom }} output</div>
                <div class="fs-5 fw-bold text-primary">{{ fmt_num((float) $standardPerFinished, 4) }}</div>
                <div class="small text-secondary mt-1">Material ÷ batch qty → written to finished product <strong>Cost</strong></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100 border-0 bg-light">
            <div class="card-body py-3">
                <div class="small text-secondary">Finished product cost (inventory)</div>
                <div class="fs-5 fw-bold">{{ fmt_num((float) $bom->finishedProduct->cost, 2) }}</div>
                <div class="small text-secondary mt-1">
                    <a href="{{ route('inventory.products.edit', $bom->finishedProduct) }}" class="text-decoration-none">Open product</a>
                    — auto-updates when BoM is saved or any component’s FIFO cost changes.
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold">Components (per batch)</div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead class="table-light">
            <tr>
                <th>SKU</th>
                <th>Component</th>
                <th>Qty (BoM unit)</th>
                <th>In stock UOM</th>
                <th class="text-end">Line cost</th>
                <th>On hand</th>
                <th class="text-end">Action</th>
            </tr>
            </thead>
            <tbody>
            @foreach($bom->lines as $line)
                <tr>
                    <td class="small text-secondary">{{ $line->component->sku }}</td>
                    <td>{{ $line->component->name }}</td>
                    <td class="fw-semibold">{{ fmt_num((float) $line->qty, 3) }} {{ $line->effectiveUom() }}</td>
                    <td class="text-secondary small">{{ fmt_num($line->qtyInBasePerBatch(), 4) }} {{ $line->component->uom }}</td>
                    <td class="text-end">{{ fmt_num($line->lineMaterialCostPerBatch(), 2) }}</td>
                    <td>{{ fmt_num((float) $line->component->qty_on_hand, 3) }} {{ $line->component->uom }}</td>
                    <td class="text-end">
                        <a href="{{ route('manufacturing.boms.edit', array_filter(['bom' => $bom, 'return' => $bomReturnPath ?? null], fn ($v) => $v !== null && $v !== '')) }}" class="btn btn-sm btn-outline-primary">Edit in BoM</a>
                        <a href="{{ route('inventory.products.edit', $line->component) }}" class="btn btn-sm btn-outline-secondary">Edit Product</a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="alert alert-light border small mb-0">
    <strong>Units:</strong> Purchase rice in <code>kg</code>, add alternate unit <code>g</code> with factor <code>0.001</code> on the product. BoM can then use <code>250</code> <code>g</code> per batch; stock always moves in <code>kg</code> (0.25 kg per batch line).
</div>

@if($bom->notes)
<div class="text-secondary small mt-3">{{ $bom->notes }}</div>
@endif
@endsection
