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
            $uoms = collect($p->uomsForForms())->map(fn ($row) => [
                'uom' => (string) ($row['uom'] ?? ''),
                'factor' => (float) ($row['factor'] ?? 1),
            ])->values()->all();

            return [
                'id' => (string) $p->id,
                'label' => $label,
                'normalized' => mb_strtolower($label),
                'search' => mb_strtolower(trim($p->sku.' '.$p->name)),
                'uom' => (string) $p->uom,
                'warehouseQty' => (float) ($p->warehouse_qty ?? 0),
                'unitCostBase' => round((float) ($p->cost ?? 0), 6),
                'uoms' => $uoms,
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
                            <th style="min-width:260px;">Product</th>
                            <th style="width:110px;">Qty</th>
                            <th style="width:120px;">UOM</th>
                            <th style="min-width:140px;">Warehouse</th>
                            <th style="width:120px;" class="text-end">Amount</th>
                            <th style="width:44px;"></th>
                        </tr>
                        </thead>
                        <tbody id="issueLinesBody"></tbody>
                        <tfoot>
                        <tr class="table-light">
                            <td colspan="4" class="text-end fw-semibold">Total</td>
                            <td class="text-end fw-semibold" id="issueLinesTotal">0</td>
                            <td></td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="form-text mt-1">Har line warehouse se selected department ko issue hogi. Last line pe Qty se Tab = nayi line.</div>

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
                <select name="lines[__i__][uom]" class="form-select form-select-sm issue-uom" required>
                    <option value="">UOM...</option>
                </select>
            </td>
            <td>
                <div class="small text-secondary issue-wh-hint">—</div>
            </td>
            <td class="text-end">
                <div class="fw-semibold issue-line-amount">0</div>
                <div class="small text-secondary issue-rate-hint"></div>
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
    const totalEl = document.getElementById('issueLinesTotal');
    const initialLines = @json($oldLines);
    const productOptions = @json($productOptionsJs);

    let idx = 0;

    function fmtNum(value, digits = 2) {
        const num = Number(value || 0);
        return num.toLocaleString(undefined, {
            minimumFractionDigits: 0,
            maximumFractionDigits: digits,
        });
    }

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

    function getFactor(option, uom) {
        if (!option) return 1;
        const row = (option.uoms || []).find((u) => String(u.uom).toLowerCase() === String(uom || '').toLowerCase());
        return row ? Number(row.factor || 0) || 1 : 1;
    }

    function syncRowAmount(row) {
        const option = findById(row.querySelector('.issue-product-id')?.value || '');
        const qty = Number(row.querySelector('.issue-qty')?.value || 0);
        const uom = row.querySelector('.issue-uom')?.value || '';
        const amountEl = row.querySelector('.issue-line-amount');
        const rateHint = row.querySelector('.issue-rate-hint');
        const hint = row.querySelector('.issue-wh-hint');

        if (!option || !uom) {
            if (amountEl) amountEl.textContent = '0';
            if (rateHint) rateHint.textContent = '';
            refreshTotal();
            return;
        }

        const factor = getFactor(option, uom);
        const rate = Number(option.unitCostBase || 0) * factor;
        const amount = qty > 0 ? qty * rate : 0;
        if (amountEl) amountEl.textContent = fmtNum(amount, 2);
        if (rateHint) rateHint.textContent = rate > 0 ? `@ ${fmtNum(rate, 4)}/${uom}` : '';

        const stockInUom = factor > 0 ? (Number(option.warehouseQty || 0) / factor) : 0;
        if (hint) {
            hint.textContent = `Available: ${fmtNum(stockInUom, 3)} ${uom}`;
            hint.className = 'small issue-wh-hint ' + (stockInUom > 0 ? 'text-primary fw-semibold' : 'text-secondary');
        }

        refreshTotal();
    }

    function refreshTotal() {
        let total = 0;
        body.querySelectorAll('.issue-line-row').forEach((row) => {
            const option = findById(row.querySelector('.issue-product-id')?.value || '');
            const qty = Number(row.querySelector('.issue-qty')?.value || 0);
            const uom = row.querySelector('.issue-uom')?.value || '';
            if (!option || !uom || qty <= 0) return;
            const factor = getFactor(option, uom);
            total += qty * Number(option.unitCostBase || 0) * factor;
        });
        if (totalEl) totalEl.textContent = fmtNum(total, 2);
    }

    function fillUomSelect(select, option, preferUom = null) {
        const list = option?.uoms || [];
        const current = preferUom || select.value || option?.uom || '';
        select.innerHTML = '<option value="">UOM...</option>' + list.map((u) => {
            const stockInUom = u.factor > 0 ? (Number(option.warehouseQty || 0) / u.factor) : 0;
            const selected = String(current).toLowerCase() === String(u.uom).toLowerCase() ? 'selected' : '';
            return `<option value="${u.uom}" ${selected}>${u.uom} (${fmtNum(stockInUom, 3)})</option>`;
        }).join('');

        if (current && list.some((u) => String(u.uom).toLowerCase() === String(current).toLowerCase())) {
            select.value = list.find((u) => String(u.uom).toLowerCase() === String(current).toLowerCase()).uom;
        } else if (list.length > 0) {
            select.value = list[0].uom;
        }
    }

    function setProduct(row, option, preferUom = null) {
        const search = row.querySelector('.issue-product-search');
        const idInput = row.querySelector('.issue-product-id');
        const uom = row.querySelector('.issue-uom');
        const hint = row.querySelector('.issue-wh-hint');
        if (!option) {
            idInput.value = '';
            search.classList.remove('is-valid');
            uom.innerHTML = '<option value="">UOM...</option>';
            hint.textContent = '—';
            hint.className = 'small text-secondary issue-wh-hint';
            syncRowAmount(row);
            return;
        }
        idInput.value = option.id;
        search.value = option.label;
        search.classList.remove('is-invalid');
        fillUomSelect(uom, option, preferUom || option.uom);
        syncRowAmount(row);
    }

    function bindRow(row) {
        const search = row.querySelector('.issue-product-search');
        const qty = row.querySelector('.issue-qty');
        const uom = row.querySelector('.issue-uom');

        search.addEventListener('focus', () => buildSharedOptions(search.value));
        search.addEventListener('input', () => {
            const exact = findByLabel(search.value);
            if (exact) setProduct(row, exact);
            else {
                row.querySelector('.issue-product-id').value = '';
                row.querySelector('.issue-uom').innerHTML = '<option value="">UOM...</option>';
                row.querySelector('.issue-wh-hint').textContent = '—';
                syncRowAmount(row);
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

        qty.addEventListener('input', () => syncRowAmount(row));
        uom.addEventListener('change', () => syncRowAmount(row));

        qty.addEventListener('keydown', (e) => {
            if (e.key !== 'Tab' || e.shiftKey) return;
            const rows = [...body.querySelectorAll('.issue-line-row')];
            if (rows[rows.length - 1] !== row) return;
            e.preventDefault();
            const next = addRow({});
            next?.querySelector('.issue-product-search')?.focus();
        });

        row.querySelector('.issue-remove').addEventListener('click', () => {
            if (body.querySelectorAll('.issue-line-row').length <= 1) return;
            row.remove();
            refreshTotal();
        });
    }

    function addRow(data) {
        const html = tpl.innerHTML.replaceAll('__i__', String(idx++));
        body.insertAdjacentHTML('beforeend', html);
        const row = body.lastElementChild;
        bindRow(row);
        if (data && data.product_id) {
            const opt = findById(data.product_id);
            if (opt) setProduct(row, opt, data.uom || null);
        }
        if (data && data.qty_uom !== undefined && data.qty_uom !== null && data.qty_uom !== '') {
            row.querySelector('.issue-qty').value = data.qty_uom;
            syncRowAmount(row);
        }
        if (data && data.uom && row.querySelector('.issue-product-id').value) {
            const opt = findById(row.querySelector('.issue-product-id').value);
            if (opt) fillUomSelect(row.querySelector('.issue-uom'), opt, data.uom);
            syncRowAmount(row);
        }
        return row;
    }

    (initialLines.length ? initialLines : [{}]).forEach(addRow);
    buildSharedOptions('');
    refreshTotal();

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
