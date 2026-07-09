@extends('layouts.admin')

@section('title', 'Bills of Materials — Manufacturing — ' . config('app.name'))

@section('content')
<div class="mb-3">
    <h4 class="fw-bold mb-0">Bills of Materials</h4>
    <div class="text-secondary small">Each BoM lists components consumed to produce a finished inventory product (per batch quantity).</div>
</div>

@include('manufacturing.partials.subnav')

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

@if(!empty($finishedProductId) && $filterProduct)
    <div class="alert border-0 py-2 px-3 mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2"
         style="background:#eef2ff;border-left:4px solid #6366f1!important;">
        <div class="small">
            <strong>Product filter:</strong> {{ $filterProduct->sku }} — {{ $filterProduct->name }}
            <a href="{{ route('inventory.products.edit', $filterProduct) }}" class="ms-2">Inventory → Edit product</a>
        </div>
        <a href="{{ route('manufacturing.boms.index', array_filter(['return' => $bomReturnPath ?? null], fn ($v) => $v !== null && $v !== '')) }}" class="btn btn-sm btn-outline-primary">Clear filter</a>
    </div>
@endif

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <form class="d-flex gap-2 flex-wrap align-items-center" method="GET" action="{{ route('manufacturing.boms.index') }}">
            @if(!empty($finishedProductId))
                <input type="hidden" name="finished_product" value="{{ $finishedProductId }}">
            @endif
            @if(!empty($bomReturnPath))
                <input type="hidden" name="return" value="{{ $bomReturnPath }}">
            @endif
            <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Search name, SKU…" style="max-width:260px;">
            <button class="btn btn-outline-primary" type="submit">Search</button>
        </form>
        <div class="d-flex align-items-center gap-2">
            <span class="badge text-bg-light border">{{ $boms->total() }} total</span>
            <a href="{{ route('manufacturing.boms.create', array_filter([
                'finished_product_id' => $finishedProductId ?: null,
                'return' => $bomReturnPath ?? null,
            ], fn ($v) => $v !== null && $v !== '')) }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> New BoM</a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Finished Product</th>
                        <th class="text-end">Batch Qty</th>
                        <th class="text-center">Lines</th>
                        <th class="text-end">Line Cost</th>
                        <th>Status</th>
                        <th class="text-end" style="min-width:180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($boms as $bom)
                        <tr>
                            <td class="fw-semibold">
                                <a href="{{ route('manufacturing.boms.show', array_filter(['bom' => $bom, 'return' => $bomReturnPath ?? null], fn ($v) => $v !== null && $v !== '')) }}" class="text-decoration-none">
                                    {{ $bom->name }}
                                </a>
                            </td>
                            <td>
                                <div>{{ $bom->finishedProduct->name ?? '—' }}</div>
                            </td>
                            <td class="text-end">{{ fmt_num((float) $bom->batch_qty, 3) }} {{ $bom->finishedProduct->uom ?? '' }}</td>
                            <td class="text-center">{{ $bom->lines_count }}</td>
                            <td class="text-end fw-semibold">{{ fmt_num((float) ($bom->line_cost_per_batch ?? 0), 2) }}</td>
                            <td>
                                @if($bom->active)
                                    <span class="badge bg-success bg-opacity-10 text-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="{{ route('manufacturing.boms.edit', array_filter(['bom' => $bom, 'return' => $bomReturnPath ?? null], fn ($v) => $v !== null && $v !== '')) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                    <form action="{{ route('manufacturing.boms.destroy', $bom) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this BoM?');">
                                        @csrf
                                        @method('DELETE')
                                        @if(!empty($bomReturnPath))
                                            <input type="hidden" name="return" value="{{ $bomReturnPath }}">
                                        @endif
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-secondary py-4">No BoMs yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($boms->hasPages())
        <div class="card-body border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="small text-secondary">
                Showing {{ $boms->firstItem() }} to {{ $boms->lastItem() }} of {{ $boms->total() }} BoMs
            </div>
            <div class="d-inline-flex gap-1">
                @if($boms->onFirstPage())
                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Previous</button>
                @else
                    <a href="{{ $boms->previousPageUrl() }}" class="btn btn-sm btn-outline-secondary">Previous</a>
                @endif

                @if($boms->hasMorePages())
                    <a href="{{ $boms->nextPageUrl() }}" class="btn btn-sm btn-outline-secondary">Next</a>
                @else
                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Next</button>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
