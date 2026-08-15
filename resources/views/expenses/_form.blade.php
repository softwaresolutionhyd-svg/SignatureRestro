@if ($errors->any())
<div class="alert alert-danger mb-4">
    <ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

@php
    $oldLines = old('lines');
    if (! is_array($oldLines)) {
        if ($expense && $expense->relationLoaded('lines') && $expense->lines->isNotEmpty()) {
            $oldLines = $expense->lines->map(fn ($l) => [
                'description' => $l->description,
                'qty' => (float) $l->qty,
                'unit_amount' => (float) $l->unit_amount,
                'tax_percent' => (float) $l->tax_percent,
                'line_total' => (float) $l->line_total,
            ])->values()->all();
        } elseif ($expense) {
            $oldLines = [[
                'description' => (string) $expense->description,
                'qty' => (float) ($expense->qty ?? 1),
                'unit_amount' => (float) ($expense->unit_amount ?? 0),
                'tax_percent' => (float) ($expense->tax_percent ?? 0),
                'line_total' => (float) ($expense->grand_total ?? 0),
            ]];
        } else {
            $oldLines = [[
                'description' => old('description', ''),
                'qty' => 1,
                'unit_amount' => 0,
                'tax_percent' => 0,
                'line_total' => 0,
            ]];
        }
    }
@endphp

<div class="row g-4">
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold py-3">Expense Details</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Description <span class="text-danger">*</span></label>
                    <input type="text" name="description" id="expenseTitle" class="form-control @error('description') is-invalid @enderror"
                        value="{{ old('description', $expense?->description) }}"
                        placeholder="e.g. Client lunch, Travel to HQ" required>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select @error('category_id') is-invalid @enderror">
                            <option value="">— None —</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}"
                                    {{ old('category_id', $expense?->category_id) == $cat->id ? 'selected' : '' }}>
                                    {{ $cat->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Expense Date <span class="text-danger">*</span></label>
                        <input type="date" name="expense_date" class="form-control @error('expense_date') is-invalid @enderror"
                            value="{{ old('expense_date', $expense?->expense_date?->format('Y-m-d') ?? date('Y-m-d')) }}" required>
                        @error('expense_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <hr class="my-3">

                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="fw-semibold">Lines</div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="expAddLineBtn">
                        <i class="bi bi-plus-lg me-1"></i> Add line
                    </button>
                </div>
                @error('lines')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

                <div class="table-responsive border rounded-3">
                    <table class="table table-sm align-middle mb-0" id="expLinesTable">
                        <thead class="table-light">
                        <tr>
                            <th>Description</th>
                            <th style="width:100px;">Qty</th>
                            <th style="width:120px;">Unit Cost</th>
                            <th style="width:100px;">Tax %</th>
                            <th style="width:130px;">Total</th>
                            <th style="width:44px;"></th>
                        </tr>
                        </thead>
                        <tbody id="expLinesBody"></tbody>
                    </table>
                </div>
                <div class="form-text mt-1">Total edit karne se Unit Cost auto adjust hota hai.</div>

                <div class="mt-3">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3"
                        placeholder="Internal notes or justification…">{{ old('notes', $expense?->notes) }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold py-3">Receipt / Attachment</div>
            <div class="card-body">
                <input type="file" name="receipt" id="receiptInput"
                    class="form-control @error('receipt') is-invalid @enderror"
                    accept=".jpg,.jpeg,.png,.pdf">
                @error('receipt')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">JPG, PNG or PDF — max 5 MB</div>

                @if($expense?->receipt_path)
                <div class="mt-3 p-2 rounded border d-flex align-items-center gap-2 small">
                    <svg width="16" height="16" fill="none" viewBox="0 0 20 20"><path d="M4 4h12v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" stroke="currentColor" stroke-width="1.5"/><path d="M8 4V2h4v2" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
                    <a href="{{ Storage::url($expense->receipt_path) }}" target="_blank" class="text-primary">View current receipt</a>
                </div>
                @endif

                <img id="receiptPreview" class="mt-3 rounded w-100 d-none" style="max-height:180px;object-fit:cover;" alt="preview">
            </div>
        </div>

        <div class="card border-0 shadow-sm" style="background:linear-gradient(135deg,#14b8a620,#14b8a605);">
            <div class="card-body">
                <div class="text-secondary small mb-3">Amount Summary</div>
                <div class="d-flex justify-content-between small mb-1">
                    <span>Subtotal</span><span id="sumSubtotal">0.00</span>
                </div>
                <div class="d-flex justify-content-between small mb-1">
                    <span>Tax</span><span id="sumTax">0.00</span>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between fw-bold">
                    <span>Total</span><span id="sumTotal" style="color:#14b8a6;">0.00</span>
                </div>
            </div>
        </div>
    </div>
</div>

<template id="expLineRowTpl">
    <tr class="exp-line-row">
        <td>
            <input type="text" name="lines[__i__][description]" class="form-control form-control-sm exp-desc" placeholder="Line description" required>
        </td>
        <td>
            <input type="number" name="lines[__i__][qty]" class="form-control form-control-sm exp-qty" step="0.001" min="0.001" value="1" required>
        </td>
        <td>
            <input type="number" name="lines[__i__][unit_amount]" class="form-control form-control-sm exp-unit" step="0.01" min="0" value="0" required>
        </td>
        <td>
            <input type="number" name="lines[__i__][tax_percent]" class="form-control form-control-sm exp-tax" step="0.001" min="0" max="100" value="0">
        </td>
        <td>
            <input type="number" name="lines[__i__][line_total]" class="form-control form-control-sm exp-total" step="0.01" min="0" value="0">
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 exp-remove" title="Remove line">&times;</button>
        </td>
    </tr>
</template>

@section('scripts')
<script>
(function () {
    const body = document.getElementById('expLinesBody');
    const tpl = document.getElementById('expLineRowTpl');
    const addBtn = document.getElementById('expAddLineBtn');
    const titleInput = document.getElementById('expenseTitle');
    const initial = @json($oldLines);
    let idx = 0;

    function fmtMoney(n) {
        if (!Number.isFinite(n)) return '0';
        let s = (Math.round(n * 100) / 100).toFixed(2);
        if (s.includes('.')) s = s.replace(/\.?0+$/, '');
        return s === '-0' ? '0' : s;
    }

    function paintSummary() {
        let sub = 0, tax = 0, tot = 0;
        body.querySelectorAll('.exp-line-row').forEach(row => {
            const q = parseFloat(row.querySelector('.exp-qty').value) || 0;
            const u = parseFloat(row.querySelector('.exp-unit').value) || 0;
            const t = parseFloat(row.querySelector('.exp-tax').value) || 0;
            const s = q * u;
            const tx = s * t / 100;
            sub += s;
            tax += tx;
            tot += s + tx;
        });
        document.getElementById('sumSubtotal').textContent = fmtMoney(sub);
        document.getElementById('sumTax').textContent = fmtMoney(tax);
        document.getElementById('sumTotal').textContent = fmtMoney(tot);
    }

    function recalcRowFromUnit(row) {
        if (row._editingTotal) return;
        const q = parseFloat(row.querySelector('.exp-qty').value) || 0;
        const u = parseFloat(row.querySelector('.exp-unit').value) || 0;
        const t = parseFloat(row.querySelector('.exp-tax').value) || 0;
        const s = q * u;
        const tot = s + (s * t / 100);
        row.querySelector('.exp-total').value = fmtMoney(tot);
        paintSummary();
    }

    function recalcRowFromTotal(row) {
        row._editingTotal = true;
        const q = parseFloat(row.querySelector('.exp-qty').value) || 0;
        const t = parseFloat(row.querySelector('.exp-tax').value) || 0;
        const tot = parseFloat(row.querySelector('.exp-total').value) || 0;
        if (q > 0) {
            const factor = 1 + (t / 100);
            const sub = factor > 0 ? (tot / factor) : tot;
            row.querySelector('.exp-unit').value = fmtMoney(Math.max(0, sub / q));
        }
        row._editingTotal = false;
        paintSummary();
    }

    function bindRow(row) {
        row.querySelectorAll('.exp-qty, .exp-unit, .exp-tax').forEach(el => {
            el.addEventListener('input', () => recalcRowFromUnit(row));
        });
        const total = row.querySelector('.exp-total');
        total.addEventListener('input', () => recalcRowFromTotal(row));
        total.addEventListener('change', () => recalcRowFromTotal(row));
        row.querySelector('.exp-remove').addEventListener('click', () => {
            if (body.querySelectorAll('.exp-line-row').length <= 1) return;
            row.remove();
            paintSummary();
        });
    }

    function addRow(data) {
        const html = tpl.innerHTML.replaceAll('__i__', String(idx++));
        body.insertAdjacentHTML('beforeend', html);
        const row = body.lastElementChild;
        if (data) {
            row.querySelector('.exp-desc').value = data.description || '';
            row.querySelector('.exp-qty').value = data.qty ?? 1;
            row.querySelector('.exp-unit').value = data.unit_amount ?? 0;
            row.querySelector('.exp-tax').value = data.tax_percent ?? 0;
            if (data.line_total != null) {
                row.querySelector('.exp-total').value = data.line_total;
            }
        }
        bindRow(row);
        recalcRowFromUnit(row);
        return row;
    }

    (initial.length ? initial : [{}]).forEach(addRow);

    addBtn?.addEventListener('click', () => {
        const title = (titleInput?.value || '').trim();
        addRow({ description: title, qty: 1, unit_amount: 0, tax_percent: 0 });
    });

    document.getElementById('receiptInput')?.addEventListener('change', function () {
        const file = this.files[0];
        const prev = document.getElementById('receiptPreview');
        if (file && file.type.startsWith('image/')) {
            prev.src = URL.createObjectURL(file);
            prev.classList.remove('d-none');
        } else {
            prev.classList.add('d-none');
        }
    });
})();
</script>
@endsection
