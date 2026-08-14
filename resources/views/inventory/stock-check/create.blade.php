@extends('layouts.admin')

@section('title', 'New stock check — ' . config('app.name'))

@section('content')
    @include('inventory.partials.subnav')

    <div class="mb-3">
        <a href="{{ route('inventory.stock-check.index') }}" class="text-decoration-none small">&larr; List</a>
        <h4 class="fw-bold mt-2 mb-0">Naya stock check (draft)</h4>
        <p class="text-secondary small mb-0">List khali start hoti hai. <em>Add line</em> se product type karke chuno. Base qty aur inner qty dono ke saath UOM change kar sakte ho (pkt, g, kg…). Save par dono mil kar base stock ban jati hai.</p>
    </div>

    <form method="POST" action="{{ route('inventory.stock-check.store') }}" class="card shadow-sm" id="stockCheckForm">
        @csrf
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label" for="title">Title (optional)</label>
                <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror"
                       value="{{ old('title') }}" maxlength="200" placeholder="e.g. Warehouse A — March">
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
                        <th class="text-end" style="min-width: 160px;">Book</th>
                        <th class="text-end" style="min-width: 140px;">Base qty</th>
                        <th class="text-end" style="min-width: 140px;">Inner qty</th>
                        <th style="width:1%;"></th>
                    </tr>
                    </thead>
                    <tbody id="linesBody"></tbody>
                </table>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary">Save draft</button>
                <a href="{{ route('inventory.stock-check.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </form>

    @php
        $productsJs = $products->map(fn ($p) => [
            'id' => $p->id,
            'label' => $p->sku . ' — ' . $p->name,
            'uom' => (string) $p->uom,
            'pkg_qty' => $p->hasPackageContents() ? (float) $p->package_contents_qty : null,
            'pkg_uom' => $p->hasPackageContents() ? trim((string) $p->package_contents_uom) : '',
            'uoms' => $p->uomsForForms(),
            'book' => (float) $p->qty_on_hand,
        ])->values();
        $oldLines = old('lines');
        $initialLines = is_array($oldLines) ? $oldLines : [];
    @endphp
    @include('inventory.stock-check.partials.lines-editor')
@endsection
