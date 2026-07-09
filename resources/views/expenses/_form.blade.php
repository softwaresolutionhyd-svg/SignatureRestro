@if ($errors->any())
<div class="alert alert-danger mb-4">
    <ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<div class="row g-4">
    {{-- Left column --}}
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold py-3">Expense Details</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Description <span class="text-danger">*</span></label>
                    <input type="text" name="description" class="form-control @error('description') is-invalid @enderror"
                        value="{{ old('description', $expense?->description) }}"
                        placeholder="e.g. Client lunch, Travel to HQ" required>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Employee <span class="text-danger">*</span></label>
                        <select name="employee_id" class="form-select @error('employee_id') is-invalid @enderror" required>
                            <option value="">Select Employee</option>
                            @foreach($employees as $emp)
                                <option value="{{ $emp->id }}"
                                    {{ old('employee_id', $expense?->employee_id ?? $myEmployee?->id) == $emp->id ? 'selected' : '' }}>
                                    {{ $emp->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('employee_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
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
                    <div class="col-md-4">
                        <label class="form-label">Expense Date <span class="text-danger">*</span></label>
                        <input type="date" name="expense_date" class="form-control @error('expense_date') is-invalid @enderror"
                            value="{{ old('expense_date', $expense?->expense_date?->format('Y-m-d') ?? date('Y-m-d')) }}" required>
                        @error('expense_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <hr class="my-3">

                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Quantity <span class="text-danger">*</span></label>
                        <input type="number" name="qty" id="fQty" class="form-control @error('qty') is-invalid @enderror"
                            value="{{ old('qty', $expense?->qty ?? 1) }}"
                            step="0.001" min="0.001" required>
                        @error('qty')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Unit Cost <span class="text-danger">*</span></label>
                        <input type="number" name="unit_amount" id="fUnit" class="form-control @error('unit_amount') is-invalid @enderror"
                            value="{{ old('unit_amount', $expense?->unit_amount ?? 0) }}"
                            step="0.01" min="0" required>
                        @error('unit_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tax %</label>
                        <input type="number" name="tax_percent" id="fTax" class="form-control @error('tax_percent') is-invalid @enderror"
                            value="{{ old('tax_percent', $expense?->tax_percent ?? 0) }}"
                            step="0.001" min="0" max="100">
                        @error('tax_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Total (incl. tax)</label>
                        <input type="text" id="fTotal" class="form-control bg-light" readonly>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3"
                        placeholder="Internal notes or justification…">{{ old('notes', $expense?->notes) }}</textarea>
                </div>
            </div>
        </div>
    </div>

    {{-- Right column --}}
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

                {{-- Preview for new image uploads --}}
                <img id="receiptPreview" class="mt-3 rounded w-100 d-none" style="max-height:180px;object-fit:cover;" alt="preview">
            </div>
        </div>

        {{-- Live total summary card --}}
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

@section('scripts')
<script>
(function () {
    const qty  = document.getElementById('fQty');
    const unit = document.getElementById('fUnit');
    const tax  = document.getElementById('fTax');

    function fmtMoney(n) {
        if (!Number.isFinite(n)) return '0';
        let s = (Math.round(n * 100) / 100).toFixed(2);
        if (s.includes('.')) s = s.replace(/\.?0+$/, '');
        return s === '-0' ? '0' : s;
    }

    function recalc() {
        const q  = parseFloat(qty.value)  || 0;
        const u  = parseFloat(unit.value) || 0;
        const t  = parseFloat(tax.value)  || 0;
        const sub = q * u;
        const txAmt = sub * t / 100;
        const tot = sub + txAmt;

        document.getElementById('fTotal').value      = fmtMoney(tot);
        document.getElementById('sumSubtotal').textContent = fmtMoney(sub);
        document.getElementById('sumTax').textContent      = fmtMoney(txAmt);
        document.getElementById('sumTotal').textContent    = fmtMoney(tot);
    }

    [qty, unit, tax].forEach(el => el.addEventListener('input', recalc));
    recalc();

    // Receipt image preview
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
