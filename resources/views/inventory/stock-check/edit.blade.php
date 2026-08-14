@extends('layouts.admin')

@section('title', 'Edit stock check — ' . config('app.name'))

@section('content')
    @include('inventory.partials.subnav')

    <div class="mb-3">
        <a href="{{ route('inventory.stock-check.show', $stockCheck) }}" class="text-decoration-none small">&larr; {{ $stockCheck->number }}</a>
        <h4 class="fw-bold mt-2 mb-0">Edit draft — {{ $stockCheck->number }}</h4>
    </div>

    <form method="POST" action="{{ route('inventory.stock-check.update', $stockCheck) }}" class="card shadow-sm" id="stockCheckForm">
        @csrf
        @method('PUT')
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label" for="title">Title (optional)</label>
                <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror"
                       value="{{ old('title', $stockCheck->title) }}" maxlength="200">
                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            @error('lines')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <div class="fw-semibold">Lines</div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addLineBtn"><i class="bi bi-plus-circle me-1"></i> Add line</button>
            </div>

            <div class="table-responsive border rounded-3">
                <table class="table mb-0 align-middle">
                    <thead class="table-light">
                    <tr>
                        <th style="min-width: 280px;">Product</th>
                        <th class="text-end" style="min-width: 140px;">Book</th>
                        <th style="min-width: 150px;">UOM</th>
                        <th class="text-end" style="min-width: 160px;">Counted</th>
                        <th style="width:1%;"></th>
                    </tr>
                    </thead>
                    <tbody id="linesBody"></tbody>
                </table>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary">Save draft</button>
                <a href="{{ route('inventory.stock-check.show', $stockCheck) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </form>

    @php
        $productsJs = $products->map(fn ($p) => [
            'id' => $p->id,
            'label' => $p->sku . ' — ' . $p->name,
            'uom' => (string) $p->uom,
            'uoms' => $p->uomsForForms(),
            'book' => (float) $p->qty_on_hand,
        ])->values();
        $oldLines = old('lines');
        if (is_array($oldLines)) {
            $initialLines = $oldLines;
        } else {
            $initialLines = $stockCheck->lines->map(fn ($l) => [
                'product_id' => $l->product_id,
                'uom' => (string) ($l->product?->uom ?? ''),
                'qty' => $l->counted_qty !== null ? (string) $l->counted_qty : '',
            ])->values()->all();
        }
    @endphp
    @include('inventory.stock-check.partials.lines-editor')
@endsection
