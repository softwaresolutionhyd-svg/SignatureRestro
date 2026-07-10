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
                        <input
                            type="text"
                            id="issueProductSearch"
                            class="form-control @error('product_id') is-invalid @enderror"
                            placeholder="SKU ya naam likhein — list filter hoti jayegi..."
                            autocomplete="off"
                            list="issueProductSearchOptions"
                        >
                        <datalist id="issueProductSearchOptions"></datalist>
                        <input type="hidden" name="product_id" id="issueProductId" value="{{ old('product_id') }}" required>
                        <select id="issueProductSelect" class="d-none" aria-hidden="true">
                            <option value="">Select product...</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}" @selected((string) old('product_id') === (string) $p->id)
                                        data-uom="{{ $p->uom }}"
                                        data-warehouse-qty="{{ (float) ($p->warehouse_qty ?? 0) }}">
                                    {{ $p->sku }} — {{ $p->name }} (Warehouse: {{ fmt_num((float) ($p->warehouse_qty ?? 0), 3) }} {{ $p->uom }})
                                </option>
                            @endforeach
                        </select>
                        @error('product_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="form-text" id="warehouseQtyHint">Type karein ya list se select karein — warehouse stock neeche dikhega.</div>
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
    const productSearch = document.getElementById('issueProductSearch');
    const productSearchOptions = document.getElementById('issueProductSearchOptions');
    const productIdInput = document.getElementById('issueProductId');
    const productSelect = document.getElementById('issueProductSelect');
    const uomInput = document.getElementById('issueUomInput');
    const hint = document.getElementById('warehouseQtyHint');
    const initialProductId = @json(old('product_id'));

    const productOptions = Array.from(productSelect?.options || [])
        .filter((opt) => !!opt.value)
        .map((opt) => ({
            id: String(opt.value),
            label: String(opt.text).trim(),
            normalized: String(opt.text).toLowerCase(),
            uom: opt.dataset.uom || '',
            warehouseQty: Number(opt.dataset.warehouseQty || 0),
        }));

    function syncProductMeta(option) {
        if (!option) {
            if (uomInput) uomInput.value = '';
            if (hint) hint.textContent = 'Type karein ya list se select karein — warehouse stock neeche dikhega.';
            return;
        }
        if (uomInput) uomInput.value = option.uom;
        if (hint) {
            hint.textContent = `Warehouse me available: ${option.warehouseQty.toFixed(3)} ${option.uom}`;
        }
    }

    function findProductByLabel(label) {
        const value = (label || '').trim().toLowerCase();
        if (!value) return null;
        return productOptions.find((opt) => opt.normalized === value) ?? null;
    }

    function findProductByContains(term) {
        const value = (term || '').trim().toLowerCase();
        if (!value) return null;
        return productOptions.find((opt) => opt.normalized.includes(value)) ?? null;
    }

    function setSelectedProduct(option) {
        if (!option) {
            productIdInput.value = '';
            productSelect.value = '';
            productSearch.value = '';
            syncProductMeta(null);
            return;
        }
        productIdInput.value = option.id;
        productSelect.value = option.id;
        productSearch.value = option.label;
        syncProductMeta(option);
    }

    function buildProductSearchOptions(term) {
        const query = (term || '').trim().toLowerCase();
        const list = !query
            ? productOptions.slice(0, 50)
            : productOptions.filter((opt) => opt.normalized.includes(query)).slice(0, 100);

        productSearchOptions.innerHTML = list
            .map((opt) => `<option value="${opt.label}"></option>`)
            .join('');
    }

    productSearch?.addEventListener('focus', () => {
        buildProductSearchOptions(productSearch.value);
    });

    productSearch?.addEventListener('input', () => {
        const exact = findProductByLabel(productSearch.value);
        if (exact) {
            setSelectedProduct(exact);
        } else {
            productIdInput.value = '';
            syncProductMeta(null);
        }
        buildProductSearchOptions(productSearch.value);
    });

    productSearch?.addEventListener('blur', () => {
        const exact = findProductByLabel(productSearch.value);
        const contains = findProductByContains(productSearch.value);
        if (exact) {
            setSelectedProduct(exact);
        } else if (contains) {
            setSelectedProduct(contains);
        } else if (!productSearch.value.trim()) {
            setSelectedProduct(null);
        }
    });

    productSearch?.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const exact = findProductByLabel(productSearch.value);
        const contains = findProductByContains(productSearch.value);
        if (exact) setSelectedProduct(exact);
        else if (contains) setSelectedProduct(contains);
    });

    productSearch?.closest('form')?.addEventListener('submit', (e) => {
        if (!productIdInput.value) {
            e.preventDefault();
            productSearch.classList.add('is-invalid');
            if (hint) hint.textContent = 'Product list se select karein (type karke suggestion choose karein).';
            productSearch.focus();
        }
    });

    buildProductSearchOptions('');
    if (initialProductId) {
        const selected = productOptions.find((opt) => opt.id === String(initialProductId));
        if (selected) setSelectedProduct(selected);
    }
})();
</script>
@endsection
