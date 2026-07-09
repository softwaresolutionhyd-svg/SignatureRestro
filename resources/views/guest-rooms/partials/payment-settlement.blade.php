@php
    $advance = (float) ($advance ?? 0);
    $total = (float) ($total ?? 0);
    $balance = isset($balance) ? (float) $balance : max(0, $total - $advance);
    $formAction = $formAction ?? '#';
    $formMethod = $formMethod ?? 'POST';
    $submitLabel = $submitLabel ?? 'Received';
    $showDiscountTax = $showDiscountTax ?? false;
    $booking = $booking ?? null;
    $advanceLabel = $advanceLabel ?? 'Advance (already paid)';
    $balanceLabel = $balanceLabel ?? 'Balance (remaining)';
@endphp

<div class="border rounded p-3 bg-light mb-3" id="payment-settlement">
    <div class="fw-semibold mb-3">Payment</div>
    <div class="row g-2 mb-2">
        <div class="col-6">
            <label class="form-label small mb-0">{{ $advanceLabel }}</label>
            <input type="text" class="form-control bg-white" id="ps-advance-display" value="{{ number_format($advance, 2) }}" readonly>
            <input type="hidden" name="advance_amount" id="ps-advance-hidden" value="{{ $advance }}">
        </div>
        <div class="col-6">
            <label class="form-label small mb-0">{{ $balanceLabel }}</label>
            <input type="text" class="form-control bg-white fw-bold text-danger" id="ps-balance-display" value="{{ number_format($balance, 2) }}" readonly>
        </div>
    </div>
    <div class="row g-2 mb-2">
        <div class="col-md-5">
            <label class="form-label small mb-0">Payment method *</label>
            <select name="payment_method" class="form-select" id="ps-payment-method" @if($balance > 0) required @endif>
                <option value="">Select</option>
                <option value="cash" @selected(old('payment_method') === 'cash')>Cash</option>
                <option value="bank" @selected(old('payment_method') === 'bank')>Bank</option>
            </select>
        </div>
        <div class="col-md-7">
            <label class="form-label small mb-0">Received now (balance only)</label>
            <input type="number" step="0.01" min="0" name="amount_received" id="ps-amount-received" class="form-control"
                value="{{ old('amount_received', $balance > 0 ? $balance : 0) }}" max="{{ $balance }}">
        </div>
    </div>
    <p class="small text-secondary mb-0" id="ps-hint">
        Total bill: <strong id="ps-total-display">{{ number_format($total, 2) }}</strong>
        — after receive: paid <strong id="ps-paid-after">{{ number_format($advance + min($balance, (float) old('amount_received', $balance)), 2) }}</strong>
    </p>
</div>
