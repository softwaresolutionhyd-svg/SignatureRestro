@extends('layouts.admin')

@section('title', 'Edit BoM — Manufacturing — ' . config('app.name'))

@section('content')
<div class="mb-3">
    <h4 class="fw-bold mb-0">Edit Bill of Materials</h4>
</div>

@include('manufacturing.partials.subnav')

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

@php
    $bomReturn = old('return', $bomReturnPath ?? '');
    $bomReturnCancelHref = $bomReturn === ''
        ? route('manufacturing.boms.index')
        : (preg_match('#^https?://#i', $bomReturn) ? $bomReturn : url($bomReturn));
@endphp
<form method="POST" action="{{ route('manufacturing.boms.update', $bom) }}" id="bomForm">
    @csrf
    @method('PUT')
    @if($bomReturn !== '')
        <input type="hidden" name="return" value="{{ $bomReturn }}">
    @endif
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">Header</div>
        <div class="card-body row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label">Finished product <span class="text-danger">*</span></label>
                @php
                    $selectedFinishedId = (string) old('finished_product_id', $bom->finished_product_id);
                    $selectedFinishedLabel = '';
                    foreach ($products as $p) {
                        if ((string) $p->id === $selectedFinishedId) {
                            $selectedFinishedLabel = $p->sku.' — '.$p->name.' ('.$p->uom.')';
                            break;
                        }
                    }
                @endphp
                <input type="hidden" name="finished_product_id" id="finishedProductIdInput" value="{{ $selectedFinishedId }}">
                <input type="text"
                       id="finishedProductSearch"
                       list="bomFinishedProductList"
                       class="form-control @error('finished_product_id') is-invalid @enderror"
                       value="{{ $selectedFinishedLabel }}"
                       placeholder="Type SKU or name and select suggestion"
                       autocomplete="off"
                       required>
                <div class="form-text">Type karen, suggestion me se product select karen.</div>
                @error('finished_product_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">BoM name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $bom->name) }}" required maxlength="120">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">Batch qty <span class="text-danger">*</span></label>
                <input type="number" name="batch_qty" class="form-control @error('batch_qty') is-invalid @enderror" value="{{ old('batch_qty', $bom->batch_qty) }}" step="0.001" min="0.001" required>
                @error('batch_qty')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label class="form-label">Notes</label>
                <input type="text" name="notes" class="form-control" value="{{ old('notes', $bom->notes) }}" maxlength="500">
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="active" value="1" id="active" @checked(old('active', $bom->active))>
                    <label class="form-check-label" for="active">Active</label>
                </div>
            </div>
            <div class="col-12">
                <div class="row g-2 small">
                    <div class="col-md-4">
                        <div class="border rounded p-2 bg-light h-100">
                            <div class="text-secondary">Material cost (this batch)</div>
                            <div class="fw-bold fs-6" id="bomBatchCostLive">—</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-2 bg-light h-100">
                            <div class="text-secondary">Std. cost / finished unit <span class="text-muted">(live)</span></div>
                            <div class="fw-bold fs-6 text-primary" id="bomStdCostLive">—</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-2 bg-light h-100">
                            <div class="text-secondary">Finished product cost (saved)</div>
                            <div class="fw-bold fs-6" id="bomSavedFinishedCost">{{ fmt_num((float) ($bom->finishedProduct->cost ?? 0), 2) }}</div>
                            <div class="text-muted mt-1" style="font-size:11px;">Save BoM to sync. Also updates when any component FIFO cost changes.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <span class="fw-semibold">Components</span>
            <button type="button" class="btn btn-sm btn-outline-primary" id="addLine"><i class="bi bi-plus-lg"></i> Add line</button>
        </div>
        <div class="card-body p-0">
            <p class="small text-secondary px-3 pt-3 mb-0">
                <strong>Unit</strong> column = is line ki qty kis naap mein hai.
                Component list mein sirf <strong>Ingredients</strong> category ke warehouse products dikhte hain.
                Component base <code>kg</code> ho aur Units library mein <code>g</code> set ho to <strong>g</strong> choose karke grams likho — e.g. <strong>25 g</strong>.
            </p>
            <div class="table-responsive">
                <table class="table mb-0 align-middle" id="linesTable">
                    <thead class="table-light">
                    <tr>
                        <th style="min-width:220px;">Component</th>
                        <th style="width:100px;">Unit</th>
                        <th style="width:120px;">Qty / batch</th>
                        <th style="width:100px;" class="text-end">Line cost</th>
                        <th style="width:48px;"></th>
                    </tr>
                    </thead>
                    <tbody id="linesBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">Update BoM</button>
        <a href="{{ $bomReturnCancelHref }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<datalist id="bomFinishedProductList">
    @foreach(($finishedProducts ?? $products) as $p)
        <option value="{{ $p->sku }} — {{ $p->name }} ({{ $p->uom }})" data-id="{{ $p->id }}"></option>
    @endforeach
</datalist>
<datalist id="bomIngredientList">
    @foreach(($ingredientProducts ?? collect()) as $p)
        <option value="{{ $p->sku }} — {{ $p->name }} ({{ $p->uom }})" data-id="{{ $p->id }}"></option>
    @endforeach
</datalist>
@endsection

@section('scripts')
@php
    $bomInitialLines = $bom->lines->map(function ($l) {
        return [
            'component_product_id' => $l->component_product_id,
            'qty' => (float) $l->qty,
            'uom' => $l->uom ?: ($l->component?->uom ?? ''),
        ];
    })->values();
@endphp
<script>
(function () {
    const productsMeta = @json($bomProductsMeta);
    const finishedMeta = @json($finishedProductsMeta ?? $bomProductsMeta);
    const ingredientMeta = @json($ingredientProductsMeta ?? []);
    const productEditBaseUrl = @json(url('inventory/products'));
    const body = document.getElementById('linesBody');
    let idx = 0;

    const initial = @json($bomInitialLines);

    function fmtNum(n, maxD) {
        if (n == null || !Number.isFinite(n)) return '—';
        let s = n.toFixed(maxD);
        if (s.includes('.')) s = s.replace(/\.?0+$/, '');
        return s === '-0' ? '0' : s;
    }

    function esc(s) {
        return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
    }

    function metaById(id, list) {
        const src = list || productsMeta;
        return src.find(p => String(p.id) === String(id)) || null;
    }

    function metaByLabel(label, list) {
        const norm = String(label || '').trim().toLowerCase();
        if (!norm) return null;
        const src = list || productsMeta;
        return src.find(p => String(p.label || '').trim().toLowerCase() === norm) || null;
    }

    function labelById(id) {
        const m = metaById(id, ingredientMeta) || metaById(id, productsMeta);
        return m ? m.label : '';
    }

    function rowHtml(i, selId, qty, uomVal) {
        const q = qty != null && qty !== '' ? qty : '';
        const selectedLabel = labelById(selId);
        return `<tr data-line>
            <td>
                <input type="hidden" name="lines[${i}][component_product_id]" class="bom-comp-id" value="${esc(selId || '')}">
                <input type="text" class="form-control form-control-sm bom-comp-search" list="bomIngredientList" placeholder="Type ingredient" autocomplete="off" required value="${esc(selectedLabel)}">
            </td>
            <td><select name="lines[${i}][uom]" class="form-select form-select-sm bom-uom" required data-line-uom></select></td>
            <td><input type="number" name="lines[${i}][qty]" class="form-control form-control-sm bom-qty" step="0.001" min="0.001" required value="${q}"></td>
            <td class="text-end small text-secondary bom-line-cost" data-line-cost>—</td>
            <td>
                <div class="d-inline-flex align-items-center gap-1">
                    <a href="#" class="btn btn-sm btn-outline-secondary bom-edit-product disabled" aria-disabled="true" title="Select component first">Edit</a>
                    <button type="button" class="btn btn-sm btn-outline-danger rm" title="Remove">&times;</button>
                </div>
            </td>
        </tr>`;
    }

    function productEditHref(productId) {
        if (!productId) return '';
        // Keep return path simple to avoid nested query-string redirect issues.
        const returnPath = window.location.pathname;
        return productEditBaseUrl + '/' + encodeURIComponent(String(productId)) + '/edit'
            + '?return=' + encodeURIComponent(returnPath);
    }

    function preferredUomCode(code) {
        const c = String(code || '').trim().toLowerCase();
        if (['g', 'gm', 'gram', 'grams'].includes(c)) return 'g';
        if (['kg', 'kgs', 'kilogram', 'kilograms'].includes(c)) return 'kg';
        if (['ml', 'milliliter', 'millilitre', 'milliliters', 'millilitres'].includes(c)) return 'ml';
        if (['l', 'ltr', 'lt', 'liter', 'litre', 'liters', 'litres'].includes(c)) return 'ltr';
        return String(code || '').trim();
    }

    function collapseUomsForSelect(uoms, baseUom) {
        const base = String(baseUom || '').trim();
        const baseFam = preferredUomCode(base).toLowerCase();
        const seen = new Set();
        const out = [];
        (uoms || []).forEach((u) => {
            const raw = String(u?.uom || '').trim();
            if (!raw) return;
            const factor = Number(u.factor);
            const fam = preferredUomCode(raw).toLowerCase();
            if (seen.has(fam)) return;
            seen.add(fam);
            const isBase = (Number.isFinite(factor) && Math.abs(factor - 1) < 1e-9)
                || fam === baseFam;
            out.push({
                uom: isBase ? (base || raw) : preferredUomCode(raw),
                factor: Number.isFinite(factor) ? factor : 1,
            });
        });
        return out;
    }

    function fillUomSelect(tr, meta, selectedUom) {
        const sel = tr.querySelector('[data-line-uom]');
        if (!sel || !meta) {
            if (sel) sel.innerHTML = '';
            return;
        }
        const base = String(meta.base_uom || '');
        const uoms = collapseUomsForSelect(meta.uoms || [], base);
        const selectedFam = preferredUomCode(selectedUom || '').toLowerCase();
        sel.innerHTML = uoms.map(u => {
            const code = String(u.uom).trim();
            const fam = preferredUomCode(code).toLowerCase();
            const selAttr = selectedFam && fam === selectedFam ? ' selected' : '';
            const factor = Number(u.factor);
            let label = code;
            // Show short unit code only (aliases already collapsed to preferred e.g. g).
            return `<option value="${esc(code)}"${selAttr}>${esc(label)}</option>`;
        }).join('');
        if (selectedUom && !Array.from(sel.options).some((o) => preferredUomCode(o.value).toLowerCase() === selectedFam)) {
            // Keep legacy line unit visible if not in current list
            sel.insertAdjacentHTML('beforeend', `<option value="${esc(selectedUom)}" selected>${esc(selectedUom)}</option>`);
        }
    }

    function lineCost(meta, uom, qty) {
        if (!meta || !qty || qty <= 0) return null;
        const fam = preferredUomCode(uom).toLowerCase();
        const u = (meta.uoms || []).find((x) => preferredUomCode(x.uom).toLowerCase() === fam)
            || (meta.uoms || []).find((x) => String(x.uom).toLowerCase() === String(uom).toLowerCase());
        const factor = u ? Number(u.factor) : 1;
        if (!Number.isFinite(factor)) return null;
        const baseQty = Number(qty) * factor;
        return baseQty * Number(meta.cost || 0);
    }

    function updateBomTotals() {
        const batchInput = document.querySelector('#bomForm input[name="batch_qty"]');
        const batchQty = batchInput ? parseFloat(batchInput.value) : NaN;
        let sum = 0;
        document.querySelectorAll('#linesBody tr[data-line]').forEach(tr => {
            const compId = tr.querySelector('.bom-comp-id');
            const uomSel = tr.querySelector('.bom-uom');
            const qtyInp = tr.querySelector('.bom-qty');
            const meta = metaById(compId && compId.value, ingredientMeta) || metaById(compId && compId.value, productsMeta);
            const v = meta && uomSel && qtyInp ? lineCost(meta, uomSel.value, parseFloat(qtyInp.value)) : null;
            if (v != null && Number.isFinite(v)) sum += v;
        });
        const elB = document.getElementById('bomBatchCostLive');
        const elU = document.getElementById('bomStdCostLive');
        if (elB) elB.textContent = Number.isFinite(sum) ? fmtNum(sum, 2) : '—';
        if (elU) {
            elU.textContent = (Number.isFinite(batchQty) && batchQty > 0 && Number.isFinite(sum))
                ? fmtNum(sum / batchQty, 4)
                : '—';
        }
    }

    function updateLineCost(tr) {
        const compId = tr.querySelector('.bom-comp-id');
        const uomSel = tr.querySelector('.bom-uom');
        const qtyInp = tr.querySelector('.bom-qty');
        const out = tr.querySelector('[data-line-cost]');
        const meta = metaById(compId && compId.value, ingredientMeta) || metaById(compId && compId.value, productsMeta);
        if (!meta || !uomSel || !qtyInp || !out) {
            updateBomTotals();
            return;
        }
        const v = lineCost(meta, uomSel.value, parseFloat(qtyInp.value));
        out.textContent = v != null && Number.isFinite(v) ? fmtNum(v, 2) : '—';
        updateBomTotals();
    }

    function wireRow(tr, presetUom) {
        const compSearch = tr.querySelector('.bom-comp-search');
        const compId = tr.querySelector('.bom-comp-id');
        const uom = tr.querySelector('.bom-uom');
        const qty = tr.querySelector('.bom-qty');
        const editBtn = tr.querySelector('.bom-edit-product');
        const refreshEditButton = () => {
            if (!editBtn) return;
            const id = compId && compId.value ? String(compId.value) : '';
            if (!id) {
                editBtn.href = '#';
                editBtn.classList.add('disabled');
                editBtn.setAttribute('aria-disabled', 'true');
                editBtn.title = 'Select component first';
                return;
            }
            editBtn.href = productEditHref(id);
            editBtn.classList.remove('disabled');
            editBtn.removeAttribute('aria-disabled');
            editBtn.title = 'Edit this component';
        };
        if (compId.value) {
            const m = metaById(compId.value, ingredientMeta) || metaById(compId.value, productsMeta);
            fillUomSelect(tr, m, presetUom || (m && m.base_uom));
        }
        const resolveComponent = () => {
            const m = metaByLabel(compSearch.value, ingredientMeta);
            compId.value = m ? String(m.id) : '';
            const preferG = m && collapseUomsForSelect(m.uoms || [], m.base_uom).some(u => preferredUomCode(u.uom).toLowerCase() === 'g');
            fillUomSelect(tr, m, preferG ? 'g' : (m && m.base_uom));
            refreshEditButton();
            updateLineCost(tr);
        };
        compSearch.addEventListener('change', resolveComponent);
        compSearch.addEventListener('input', resolveComponent);
        uom.addEventListener('change', () => updateLineCost(tr));
        qty.addEventListener('input', () => updateLineCost(tr));
        qty.addEventListener('keydown', (e) => {
            if (e.key !== 'Tab' || e.shiftKey) return;
            const val = parseFloat(qty.value);
            if (!Number.isFinite(val) || val <= 0) return;
            const rows = Array.from(body.querySelectorAll('tr[data-line]'));
            if (tr !== rows[rows.length - 1]) return;
            e.preventDefault();
            addRow('', '', '');
            body.lastElementChild?.querySelector('.bom-comp-search')?.focus();
        });
        tr.querySelector('.rm').addEventListener('click', () => {
            tr.remove();
            if (!body.querySelector('tr[data-line]')) addRow('', '', '');
            updateBomTotals();
        });
        refreshEditButton();
        updateLineCost(tr);
    }

    function addRow(selId, qty, uomVal) {
        body.insertAdjacentHTML('beforeend', rowHtml(idx++, selId, qty, uomVal));
        wireRow(body.lastElementChild, uomVal);
    }

    document.getElementById('addLine').addEventListener('click', () => addRow('', '', ''));

    if (initial.length) {
        initial.forEach(r => addRow(r.component_product_id, r.qty, r.uom));
    } else {
        addRow('', '', '');
    }

    const batchQtyInput = document.querySelector('#bomForm input[name="batch_qty"]');
    if (batchQtyInput) {
        batchQtyInput.addEventListener('input', updateBomTotals);
        batchQtyInput.addEventListener('change', updateBomTotals);
    }

    const finishedSearch = document.getElementById('finishedProductSearch');
    const finishedIdInput = document.getElementById('finishedProductIdInput');
    function syncFinishedProduct() {
        if (!finishedSearch || !finishedIdInput) return;
        const m = metaByLabel(finishedSearch.value, finishedMeta);
        finishedIdInput.value = m ? String(m.id) : '';
    }
    finishedSearch?.addEventListener('change', syncFinishedProduct);
    finishedSearch?.addEventListener('input', syncFinishedProduct);
    syncFinishedProduct();

    updateBomTotals();

    document.getElementById('bomForm').addEventListener('submit', function (e) {
        // Drop trailing / blank lines that were auto-added via Tab.
        Array.from(body.querySelectorAll('tr[data-line]')).forEach((tr) => {
            const idInp = tr.querySelector('.bom-comp-id');
            const qtyInp = tr.querySelector('.bom-qty');
            const hasId = idInp && idInp.value;
            const qty = parseFloat(qtyInp?.value || '');
            const hasQty = Number.isFinite(qty) && qty > 0;
            if (!hasId && !hasQty) tr.remove();
        });
        if (!body.querySelector('tr[data-line]')) {
            e.preventDefault();
            addRow('', '', '');
            alert('Add at least one component line.');
            return;
        }
        if (!finishedIdInput || !finishedIdInput.value) {
            e.preventDefault();
            alert('Please select a finished product from suggestions.');
            return;
        }
        const hasInvalidComponent = Array.from(body.querySelectorAll('tr[data-line]')).some(tr => {
            const idInp = tr.querySelector('.bom-comp-id');
            return !idInp || !idInp.value;
        });
        if (hasInvalidComponent) {
            e.preventDefault();
            alert('Please select each component from suggestions.');
        }
    });
})();
</script>
@endsection
