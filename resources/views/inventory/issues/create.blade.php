@extends('layouts.admin')

@section('title', 'New Issue - Inventory - ' . config('app.name'))
@section('page_title', 'Inventory / Issue to Department')

@section('content')
    @include('inventory.partials.subnav')

    @php
        $oldLines = old('lines');
        if (! is_array($oldLines) || $oldLines === []) {
            $oldLines = [[
                'product_id' => old('product_id', ''),
                'qty_uom' => old('qty_uom', ''),
                'uom' => old('uom', ''),
            ]];
        }

        $productOptionsJs = $products->map(function ($p) {
            $label = $p->sku.' — '.$p->name.' (Warehouse: '.fmt_num((float) ($p->warehouse_qty ?? 0), 3).' '.$p->uom.')';

            return [
                'id' => (string) $p->id,
                'label' => $label,
                'normalized' => mb_strtolower($label),
                'search' => mb_strtolower(trim($p->sku.' '.$p->name)),
                'uom' => (string) $p->uom,
                'warehouseQty' => (float) ($p->warehouse_qty ?? 0),
            ];
        })->values()->all();
    @endphp

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <div class="fw-semibold">Warehouse se issue karein</div>
            <a href="{{ route('inventory.issues.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('inventory.issues.store') }}" id="issueStockForm">
                @csrf

                <div class="row g-3 mb-3">
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
                    <div class="col-12 col-md-3">
                        <label class="form-label">Reference</label>
                        <input type="text" name="reference" value="{{ old('reference') }}"
                               class="form-control @error('reference') is-invalid @enderror" maxlength="80">
                        @error('reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Note</label>
                        <input type="text" name="note" value="{{ old('note') }}"
                               class="form-control @error('note') is-invalid @enderror" maxlength="255"
                               placeholder="Optional note">
                        @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="fw-semibold">Lines</div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="issueAddLineBtn" @disabled($departments->isEmpty())>
                        <i class="bi bi-plus-lg me-1"></i> Add line
                    </button>
                </div>
                @error('lines')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                @error('lines.*')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

                <div class="table-responsive border rounded-3">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th style="min-width:280px;">Product</th>
                            <th style="width:110px;">Qty</th>
                            <th style="width:100px;">UOM</th>
                            <th style="min-width:160px;">Warehouse</th>
                            <th style="width:44px;"></th>
                        </tr>
                        </thead>
                        <tbody id="issueLinesBody"></tbody>
                    </table>
                </div>
                <div class="form-text mt-1">Har line warehouse se selected department ko issue hogi.</div>

                <div class="d-flex gap-2 mt-4">
                    <button class="btn btn-primary" type="submit" @disabled($departments->isEmpty())>
                        <i class="bi bi-box-arrow-right me-1"></i> Issue Stock
                    </button>
                    <a href="{{ route('inventory.issues.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <datalist id="issueProductSearchOptionsShared"></datalist>

    <template id="issueLineRowTpl">
        <tr class="issue-line-row">
            <td>
                <input type="text" class="form-control form-control-sm issue-product-search"
                       placeholder="SKU ya naam…" autocomplete="off" list="issueProductSearchOptionsShared">
                <input type="hidden" name="lines[__i__][product_id]" class="issue-product-id" value="">
            </td>
            <td>
                <input type="number" step="0.001" min="0.001" name="lines[__i__][qty_uom]"
                       class="form-control form-control-sm issue-qty" value="" required>
            </td>
            <td>
                <input type="text" name="lines[__i__][uom]" class="form-control form-control-sm issue-uom" value="" required maxlength="30" readonly>
            </td>
            <td>
                <div class="small text-secondary issue-wh-hint">—</div>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 issue-remove" title="Remove">&times;</button>
            </td>
        </tr>
    </template>
@endsection

@section('scripts')
<script>
(() => {
    const body = document.getElementById('issueLinesBody');
    const tpl = document.getElementById('issueLineRowTpl');
    const addBtn = document.getElementById('issueAddLineBtn');
    const sharedList = document.getElementById('issueProductSearchOptionsShared');
    const initialLines = @json($oldLines);
    const productOptions = @json($productOptionsJs);

    let idx = 0;

    function findByLabel(label) {
        const value = (label || '').trim().toLowerCase();
        if (!value) return null;
        return productOptions.find((opt) => opt.normalized === value) || null;
    }

    function findByContains(term) {
        const value = (term || '').trim().toLowerCase();
        if (!value) return null;
        return productOptions.find((opt) => opt.search.includes(value) || opt.normalized.includes(value)) || null;
    }

    function findById(id) {
        return productOptions.find((opt) => opt.id === String(id)) || null;
    }

    function buildSharedOptions(term) {
        const query = (term || '').trim().toLowerCase();
        const list = !query
            ? productOptions.slice(0, 50)
            : productOptions.filter((opt) => opt.search.includes(query) || opt.normalized.includes(query)).slice(0, 100);
        sharedList.innerHTML = list.map((opt) => `<option value="${opt.label.replace(/"/g, '&quot;')}"></option>`).join('');
    }

    function setProduct(row, option) {
        const search = row.querySelector('.issue-product-search');
        const idInput = row.querySelector('.issue-product-id');
        const uom = row.querySelector('.issue-uom');
        const hint = row.querySelector('.issue-wh-hint');
        if (!option) {
            idInput.value = '';
            search.classList.remove('is-valid');
            uom.value = '';
            hint.textContent = '—';
            return;
        }
        idInput.value = option.id;
        search.value = option.label;
        search.classList.remove('is-invalid');
        uom.value = option.uom;
        hint.textContent = `Available: ${Number(option.warehouseQty).toFixed(3)} ${option.uom}`;
    }

    function bindRow(row) {
        const search = row.querySelector('.issue-product-search');

        search.addEventListener('focus', () => buildSharedOptions(search.value));
        search.addEventListener('input', () => {
            const exact = findByLabel(search.value);
            if (exact) setProduct(row, exact);
            else {
                row.querySelector('.issue-product-id').value = '';
                row.querySelector('.issue-uom').value = '';
                row.querySelector('.issue-wh-hint').textContent = '—';
            }
            buildSharedOptions(search.value);
        });
        search.addEventListener('blur', () => {
            const exact = findByLabel(search.value);
            const contains = findByContains(search.value);
            if (exact) setProduct(row, exact);
            else if (contains) setProduct(row, contains);
            else if (!search.value.trim()) setProduct(row, null);
        });
        search.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            const exact = findByLabel(search.value);
            const contains = findByContains(search.value);
            if (exact) setProduct(row, exact);
            else if (contains) setProduct(row, contains);
        });

        row.querySelector('.issue-remove').addEventListener('click', () => {
            if (body.querySelectorAll('.issue-line-row').length <= 1) return;
            row.remove();
        });
    }

    function addRow(data) {
        const html = tpl.innerHTML.replaceAll('__i__', String(idx++));
        body.insertAdjacentHTML('beforeend', html);
        const row = body.lastElementChild;
        bindRow(row);
        if (data && data.product_id) {
            const opt = findById(data.product_id);
            if (opt) setProduct(row, opt);
        }
        if (data && data.qty_uom !== undefined && data.qty_uom !== null && data.qty_uom !== '') {
            row.querySelector('.issue-qty').value = data.qty_uom;
        }
        if (data && data.uom) {
            row.querySelector('.issue-uom').value = data.uom;
        }
        return row;
    }

    (initialLines.length ? initialLines : [{}]).forEach(addRow);
    buildSharedOptions('');

    addBtn?.addEventListener('click', () => addRow({}));

    document.getElementById('issueStockForm')?.addEventListener('submit', (e) => {
        let ok = true;
        body.querySelectorAll('.issue-line-row').forEach((row) => {
            const id = row.querySelector('.issue-product-id').value;
            const search = row.querySelector('.issue-product-search');
            if (!id) {
                ok = false;
                search.classList.add('is-invalid');
            }
        });
        if (!ok) {
            e.preventDefault();
            alert('Har line pe product select karein.');
        }
    });
})();
</script>
@endsection
