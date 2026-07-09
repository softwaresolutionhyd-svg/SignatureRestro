@extends('layouts.admin')

@section('title', 'New Issue - Inventory - ' . config('app.name'))
@section('page_title', 'Inventory / Issue to Department')

@section('content')
    @include('inventory.partials.subnav')

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <div class="fw-semibold">Warehouse se issue karein</div>
            <a href="{{ route('inventory.issues.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('inventory.issues.store') }}">
                @csrf

                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <label class="form-label">Product</label>
                        <select name="product_id" id="issueProductSelect" class="form-select @error('product_id') is-invalid @enderror" required>
                            <option value="">Select product...</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}" @selected((string) old('product_id') === (string) $p->id)
                                        data-uom="{{ $p->uom }}"
                                        data-warehouse-qty="{{ (float) ($p->warehouse_qty ?? 0) }}">
                                    {{ $p->sku }} — {{ $p->name }} (Warehouse: {{ fmt_num((float) ($p->warehouse_qty ?? 0), 3) }} {{ $p->uom }})
                                </option>
                            @endforeach
                        </select>
                        @error('product_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text" id="warehouseQtyHint">Warehouse stock select karne par dikhega.</div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label">Issue to department</label>
                        <select name="to_department_id" class="form-select @error('to_department_id') is-invalid @enderror" required>
                            <option value="">Select department...</option>
                            @foreach($departments as $dep)
                                <option value="{{ $dep->id }}" @selected((string) old('to_department_id') === (string) $dep->id)>{{ $dep->name }}</option>
                            @endforeach
                        </select>
                        @error('to_department_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @if($departments->isEmpty())
                            <div class="form-text text-warning">Pehle <a href="{{ route('inventory.departments.create') }}">department banaein</a> (Warehouse ke alawa).</div>
                        @endif
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label">Quantity</label>
                        <input type="number" step="0.001" min="0.001" name="qty_uom" value="{{ old('qty_uom') }}"
                               class="form-control @error('qty_uom') is-invalid @enderror" required>
                        @error('qty_uom')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label">UOM</label>
                        <input type="text" name="uom" id="issueUomInput" value="{{ old('uom') }}"
                               class="form-control @error('uom') is-invalid @enderror" required maxlength="30" readonly>
                        @error('uom')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label">Reference</label>
                        <input type="text" name="reference" value="{{ old('reference') }}"
                               class="form-control @error('reference') is-invalid @enderror" maxlength="80">
                        @error('reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label">Note</label>
                        <input type="text" name="note" value="{{ old('note') }}"
                               class="form-control @error('note') is-invalid @enderror" maxlength="255"
                               placeholder="Optional note">
                        @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="d-flex gap-2 mt-4">
                    <button class="btn btn-primary" type="submit" @disabled($departments->isEmpty())>
                        <i class="bi bi-box-arrow-right me-1"></i> Issue Stock
                    </button>
                    <a href="{{ route('inventory.issues.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
<script>
(() => {
    const productSelect = document.getElementById('issueProductSelect');
    const uomInput = document.getElementById('issueUomInput');
    const hint = document.getElementById('warehouseQtyHint');

    function syncProduct() {
        const opt = productSelect?.selectedOptions?.[0];
        if (!opt || !opt.value) {
            if (uomInput) uomInput.value = '';
            if (hint) hint.textContent = 'Warehouse stock select karne par dikhega.';
            return;
        }
        if (uomInput) uomInput.value = opt.dataset.uom || '';
        const qty = Number(opt.dataset.warehouseQty || 0);
        if (hint) hint.textContent = `Warehouse me available: ${qty.toFixed(3)} ${opt.dataset.uom || ''}`;
    }

    productSelect?.addEventListener('change', syncProduct);
    syncProduct();
})();
</script>
@endsection
