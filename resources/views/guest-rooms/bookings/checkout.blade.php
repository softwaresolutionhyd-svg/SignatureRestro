@extends('layouts.admin')
@section('title', 'Checkout')
@section('content')
@include('guest-rooms.partials.subnav')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

@include('guest-rooms.bookings.partials.pending-pos-bills-alert')

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Checkout — {{ $booking->guestDisplayName() }} ({{ $booking->booking_no }})</div>
            <div class="card-body">
                <p class="mb-1">Room(s): <strong>{{ $booking->roomNumbersLabel() }}</strong> @if($booking->billableRoomCount() > 1)({{ $booking->billableRoomCount() }} rooms)@endif
                    <a href="{{ route('guest-rooms.bookings.change-rooms', $booking) }}" class="small ms-1">Change</a></p>
                <p class="mb-1">Stay from: <strong>{{ $booking->checkInDisplayLabel() }}</strong>
                    @if($booking->actual_check_in && $booking->check_in_date && $booking->actual_check_in->format('Y-m-d') !== $booking->check_in_date->format('Y-m-d'))
                        <span class="text-secondary small">(system check-in recorded {{ fmt_datetime($booking->actual_check_in) }})</span>
                    @endif
                </p>
                @if($booking->isOnlineBooking())
                <p class="mb-1" id="co-summary-room-line">Room charges: <strong id="co-room-charges-display">{{ number_format($booking->room_charges, 2) }}</strong>
                    <span class="badge bg-success ms-1">Paid in advance</span>
                    <span class="text-secondary small" id="co-nights-wrap"> · <span id="co-nights-display">{{ (int) $booking->nights }}</span> night(s)</span></p>
                <p class="mb-3" id="co-summary-extra-line">Mattress / laundry / other: <strong id="co-extra-display">{{ number_format($booking->extra_charges, 2) }}</strong>
                    <span class="text-danger small d-block">Due at checkout (balance below).</span></p>
                @else
                <p class="mb-3" id="co-summary-manual-line">
                    Room charges: <strong id="co-room-charges-display">{{ number_format($booking->room_charges, 2) }}</strong>
                    | Extra: <strong id="co-extra-display">{{ number_format($booking->extra_charges, 2) }}</strong>
                    <span class="text-secondary small d-block" id="co-nights-wrap">
                        <span id="co-nights-display">{{ (int) $booking->nights }}</span> night(s) from check-in to checkout date.
                        Totals update automatically when you change checkout date.
                    </span>
                </p>
                @endif
                <form method="POST" action="{{ route('guest-rooms.bookings.checkout.store', $booking) }}" id="checkout-form">
                    @csrf
                    <input type="hidden" id="co-preview-url" value="{{ route('guest-rooms.bookings.checkout.preview', $booking) }}">
                    <div class="mb-3">
                        @include('partials.form-date-dmy', [
                            'name' => 'checkout_date',
                            'label' => 'Checkout date',
                            'value' => $defaultCheckout,
                            'required' => true,
                            'min' => $checkInMin,
                            'max' => $checkoutMax,
                            'hint' => 'Pick any date from stay start through '.fmt_date($checkoutMax).' (past months allowed).'.($booking->check_out_date ? ' Planned: '.fmt_date($booking->check_out_date).'.' : ''),
                        ])
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Discount</label>
                        <input type="number" step="0.01" name="discount" id="co-discount" class="form-control" value="{{ $booking->discount }}">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Tax %</label>
                        <input type="number" step="0.01" name="tax_percent" id="co-tax" class="form-control" value="{{ $booking->tax_percent }}">
                    </div>

                    @php
                        $isOnlineCheckout = $booking->isOnlineBooking();
                        $discount = (float) $booking->discount;
                        $taxPct = (float) $booking->tax_percent;
                        if ($isOnlineCheckout) {
                            $advance = (float) $booking->onlineRoomPrepaidAmount();
                            $estimatedTotal = (float) $booking->total_amount;
                            $checkoutDue = (float) $booking->onlineExtrasDueAmount();
                        } else {
                            $roomExtra = (float) $booking->room_charges + (float) $booking->extra_charges;
                            $subtotal = max(0, $roomExtra - $discount);
                            $taxAmt = round($subtotal * ($taxPct / 100), 2);
                            $estimatedTotal = $subtotal + $taxAmt;
                            $advance = (float) $booking->paid_amount;
                            $checkoutDue = max(0, $estimatedTotal - $advance);
                        }
                    @endphp

                    <input type="hidden" id="co-online-checkout" value="{{ $isOnlineCheckout ? '1' : '0' }}">
                    @if($isOnlineCheckout)
                    <input type="hidden" name="advance_amount" id="ps-advance-hidden" value="{{ $advance }}">
                    @endif
                    @include('guest-rooms.partials.payment-settlement', [
                        'advance' => $advance,
                        'total' => $isOnlineCheckout ? ($advance + $checkoutDue) : $estimatedTotal,
                        'balance' => $checkoutDue,
                        'advanceLabel' => $isOnlineCheckout ? 'Room charges (prepaid)' : 'Advance (already paid)',
                        'balanceLabel' => $isOnlineCheckout ? 'Due now (mattress / laundry / other)' : 'Balance (remaining)',
                    ])

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2">{{ $booking->notes }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-outline-warning w-100" @disabled(isset($pendingPosBills) && $pendingPosBills->isNotEmpty())>
                        @if(isset($pendingPosBills) && $pendingPosBills->isNotEmpty())
                            <i class="bi bi-lock-fill me-1"></i>Checkout (POS bill pending)
                        @else
                            Complete Checkout &amp; Generate Bill
                        @endif
                    </button>
                    <a href="{{ route('guest-rooms.bookings.show', $booking) }}" class="btn btn-outline-secondary w-100 mt-2">Back to booking</a>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        @include('guest-rooms.bookings.partials.damage-charges', ['booking' => $booking, 'redirectTo' => 'checkout'])
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const previewUrl = document.getElementById('co-preview-url')?.value;
    const checkoutDateEl = document.getElementById('checkout_date');
    const discountEl = document.getElementById('co-discount');
    const taxEl = document.getElementById('co-tax');
    const advanceHidden = document.getElementById('ps-advance-hidden');
    const advanceDisplay = document.getElementById('ps-advance-display');
    const balanceDisplay = document.getElementById('ps-balance-display');
    const totalDisplay = document.getElementById('ps-total-display');
    const paidAfter = document.getElementById('ps-paid-after');
    const amountReceived = document.getElementById('ps-amount-received');
    const roomChargesDisplay = document.getElementById('co-room-charges-display');
    const extraDisplay = document.getElementById('co-extra-display');
    const nightsDisplay = document.getElementById('co-nights-display');
    const paymentMethod = document.getElementById('ps-payment-method');
    let previewTimer = null;
    let lastBalance = parseFloat(String(balanceDisplay?.value || '0').replace(/,/g, '')) || 0;

    function fmt(n) {
        return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function parseMoney(el) {
        return parseFloat(String(el?.value || '0').replace(/,/g, '')) || 0;
    }

    function applyPreview(data) {
        if (roomChargesDisplay) roomChargesDisplay.textContent = fmt(data.room_charges);
        if (extraDisplay) extraDisplay.textContent = fmt(data.extra_charges);
        if (nightsDisplay) nightsDisplay.textContent = String(data.nights);

        const advance = parseFloat(data.advance) || 0;
        const balance = parseFloat(data.balance) || 0;
        const total = parseFloat(data.total) || 0;

        if (advanceHidden) advanceHidden.value = advance.toFixed(2);
        if (advanceDisplay) advanceDisplay.value = fmt(advance);
        if (totalDisplay) totalDisplay.textContent = fmt(total);
        if (balanceDisplay) balanceDisplay.value = fmt(balance);

        if (amountReceived) {
            amountReceived.max = balance;
            if (lastBalance !== balance) {
                amountReceived.value = balance > 0 ? balance.toFixed(2) : '0';
            } else if (parseFloat(amountReceived.value || 0) > balance) {
                amountReceived.value = balance > 0 ? balance.toFixed(2) : '0';
            }
            lastBalance = balance;
        }

        if (paymentMethod) {
            paymentMethod.required = balance > 0.009;
        }

        updatePaidAfter();
    }

    function updatePaidAfter() {
        const advance = parseMoney(advanceHidden || advanceDisplay);
        const received = parseFloat(amountReceived?.value || 0);
        if (paidAfter) paidAfter.textContent = fmt(advance + received);
    }

    function fetchPreview() {
        if (!previewUrl || !checkoutDateEl?.value) {
            return;
        }

        clearTimeout(previewTimer);
        previewTimer = setTimeout(function () {
            const body = new URLSearchParams();
            body.set('checkout_date', checkoutDateEl.value);
            body.set('discount', discountEl?.value || '0');
            body.set('tax_percent', taxEl?.value || '0');
            body.set('_token', document.querySelector('#checkout-form input[name="_token"]')?.value || '');

            fetch(previewUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: body.toString(),
            })
                .then(function (r) {
                    if (!r.ok) throw new Error('preview failed');
                    return r.json();
                })
                .then(applyPreview)
                .catch(function () { /* keep last values */ });
        }, 200);
    }

    checkoutDateEl?.addEventListener('change', fetchPreview);
    checkoutDateEl?.addEventListener('blur', fetchPreview);
    discountEl?.addEventListener('input', fetchPreview);
    taxEl?.addEventListener('input', fetchPreview);
    amountReceived?.addEventListener('input', updatePaidAfter);

    function hookFlatpickr() {
        if (checkoutDateEl?._flatpickr) {
            checkoutDateEl._flatpickr.config.onChange.push(function () {
                fetchPreview();
            });
        }
    }

    hookFlatpickr();
    setTimeout(hookFlatpickr, 400);

    fetchPreview();
})();
</script>
@endsection
