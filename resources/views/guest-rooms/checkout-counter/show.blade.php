@extends('layouts.admin')
@section('title', 'Checkout Desk')
@section('content')
@include('guest-rooms.partials.subnav')

<div class="mb-3">
    <a href="{{ route('guest-rooms.checkout-counter.index') }}" class="text-secondary small">&larr; Checkout Counter</a>
    <h4 class="fw-bold mb-0 mt-1">{{ $booking->guestDisplayName() }} — {{ $booking->booking_no }}</h4>
    <div class="text-secondary small">Room(s): <strong>{{ $booking->roomNumbersLabel() }}</strong></div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('info'))<div class="alert alert-info">{{ session('info') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

@if($isLateCheckout)
<div class="alert alert-warning py-2 small mb-3">
    <i class="bi bi-clock-history me-1"></i>
    Scheduled checkout {{ fmt_date($scheduledCheckout) }} tha — late checkout charge add kar sakte hain.
</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>Complete Bill Summary</span>
                <a href="{{ route('guest-rooms.checkout-counter.unpaid-bill', ['booking' => $booking, 'print' => 1]) }}" class="btn btn-sm btn-outline-secondary" target="_blank">
                    <i class="bi bi-printer me-1"></i>Unpaid Bill Print
                </a>
                <span class="badge text-bg-secondary">{{ $booking->nights }} night(s)</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 align-middle">
                    <tbody>
                        <tr class="table-light">
                            <td colspan="2" class="fw-semibold ps-3">Room Charges</td>
                        </tr>
                        <tr>
                            <td class="ps-4 text-secondary">Room rent</td>
                            <td class="text-end pe-3 fw-semibold">{{ fmt_num($summary['room_charges'], 2) }}</td>
                        </tr>
                        <tr>
                            <td class="ps-4 text-secondary">Breakage / mattress / laundry</td>
                            <td class="text-end pe-3 fw-semibold">{{ fmt_num($summary['extra_charges'] - $summary['late_checkout_total'], 2) }}</td>
                        </tr>
                        @if($summary['late_checkout_total'] > 0)
                        <tr>
                            <td class="ps-4 text-secondary">Late checkout</td>
                            <td class="text-end pe-3 fw-semibold">{{ fmt_num($summary['late_checkout_total'], 2) }}</td>
                        </tr>
                        @endif
                        @if($summary['discount'] > 0)
                        <tr>
                            <td class="ps-4 text-secondary">Discount</td>
                            <td class="text-end pe-3 text-danger">− {{ fmt_num($summary['discount'], 2) }}</td>
                        </tr>
                        @endif
                        @if($summary['tax_amount'] > 0)
                        <tr>
                            <td class="ps-4 text-secondary">Tax</td>
                            <td class="text-end pe-3">{{ fmt_num($summary['tax_amount'], 2) }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td class="ps-4 fw-semibold">Room bill total</td>
                            <td class="text-end pe-3 fw-bold">{{ fmt_num($summary['room_total'], 2) }}</td>
                        </tr>
                        @if($summary['room_paid'] > 0)
                        <tr>
                            <td class="ps-4 text-secondary">Advance / already paid (room)</td>
                            <td class="text-end pe-3 text-success">− {{ fmt_num($summary['room_paid'], 2) }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td class="ps-4 fw-semibold">Room balance due</td>
                            <td class="text-end pe-3 fw-bold {{ $summary['room_balance_due'] > 0 ? 'text-warning' : 'text-success' }}">
                                {{ fmt_num($summary['room_balance_due'], 2) }}
                            </td>
                        </tr>

                        <tr class="table-light">
                            <td colspan="2" class="fw-semibold ps-3 pt-3">Cafe Bills (In-House)</td>
                        </tr>
                        @forelse($allCafeBills as $bill)
                            <tr>
                                <td class="ps-4">
                                    <span class="fw-semibold">{{ $bill->order_no }}</span>
                                    <span class="text-secondary small">· Room {{ $bill->room_no }}</span>
                                    @if($bill->created_at)
                                        <span class="text-secondary small">· {{ $bill->created_at->format('d M H:i') }}</span>
                                    @endif
                                    @if($bill->status === 'draft')
                                        <span class="badge text-bg-danger ms-1">Pending</span>
                                    @else
                                        <span class="badge text-bg-success ms-1">Paid @ POS</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3 fw-semibold">
                                    {{ fmt_num((float) $bill->grand_total, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="ps-4 text-secondary" colspan="2">Koi cafe bill nahi</td>
                            </tr>
                        @endforelse
                        <tr>
                            <td class="ps-4 fw-semibold">Cafe total</td>
                            <td class="text-end pe-3 fw-bold">{{ fmt_num($summary['cafe_total'], 2) }}</td>
                        </tr>
                        @if($summary['cafe_paid_total'] > 0)
                        <tr>
                            <td class="ps-4 text-secondary small">↳ Paid at POS (included above)</td>
                            <td class="text-end pe-3 small text-success">{{ fmt_num($summary['cafe_paid_total'], 2) }}</td>
                        </tr>
                        @endif
                        @if($summary['cafe_pending_total'] > 0)
                        <tr>
                            <td class="ps-4 text-secondary small">↳ Still pending</td>
                            <td class="text-end pe-3 small text-danger fw-semibold">{{ fmt_num($summary['cafe_pending_total'], 2) }}</td>
                        </tr>
                        @endif
                    </tbody>
                    <tfoot class="table-light border-top border-2">
                        <tr>
                            <td class="ps-3 py-3 fw-semibold">Complete bill (Room + Cafe)</td>
                            <td class="text-end pe-3 py-3 fs-5 fw-bold">{{ fmt_num($summary['complete_bill_total'], 2) }}</td>
                        </tr>
                        <tr class="table-warning">
                            <td class="ps-3 py-3 fw-bold">Abhi collect karna hai</td>
                            <td class="text-end pe-3 py-3 fs-4 fw-bold text-danger">{{ fmt_num($summary['total_due_now'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="card-footer bg-white small text-secondary">
                <strong>Collect &amp; Checkout</strong> par room checkout + pending cafe bills ek sath clear ho jati hain.
                POS aur Reception par status update ho jata hai.
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body text-center border-bottom">
                <div class="text-secondary small mb-1">Collect now</div>
                <div class="display-5 fw-bold {{ $summary['total_due_now'] > 0 ? 'text-danger' : 'text-success' }}">
                    {{ fmt_num($summary['total_due_now'], 2) }}
                </div>
                <div class="small text-secondary mt-2">
                    Room {{ fmt_num($summary['room_balance_due'], 2) }}
                    + Cafe {{ fmt_num($summary['cafe_pending_total'], 2) }}
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('guest-rooms.checkout-counter.settle', $booking) }}">
                    @csrf
                    <div class="mb-2">
                        @include('partials.form-date-dmy', [
                            'name' => 'checkout_date',
                            'label' => 'Checkout date',
                            'value' => old('checkout_date', $defaultCheckout),
                            'required' => true,
                            'min' => $checkInMin,
                            'max' => $checkoutMax,
                        ])
                    </div>
                    <div class="border rounded p-2 mb-2 bg-light">
                        <div class="fw-semibold small mb-2">
                            <i class="bi bi-clock-history text-warning me-1"></i>Late checkout charge
                        </div>
                        <div class="mb-2">
                            <label class="form-label small mb-0">Amount</label>
                            <input type="number" step="0.01" min="0" name="late_checkout_amount" class="form-control form-control-sm"
                                value="{{ old('late_checkout_amount', $lateCheckoutAmount > 0 ? $lateCheckoutAmount : '') }}"
                                placeholder="0.00">
                        </div>
                        <div class="mb-0">
                            <label class="form-label small mb-0">Detail (optional)</label>
                            <input type="text" name="late_checkout_notes" class="form-control form-control-sm"
                                value="{{ old('late_checkout_notes', $lateCheckoutNotes) }}"
                                placeholder="e.g. 3 hours late">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Discount</label>
                        <input type="number" step="0.01" min="0" name="discount" class="form-control form-control-sm"
                            value="{{ old('discount', $booking->discount) }}">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Tax %</label>
                        <input type="number" step="0.01" min="0" max="100" name="tax_percent" class="form-control form-control-sm"
                            value="{{ old('tax_percent', $booking->tax_percent) }}">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Payment method @if($summary['total_due_now'] > 0)*@endif</label>
                        <select name="payment_method" class="form-select form-select-sm" @if($summary['total_due_now'] > 0) required @endif>
                            <option value="">Select</option>
                            <option value="cash" @selected(old('payment_method') === 'cash')>Cash</option>
                            <option value="bank" @selected(old('payment_method') === 'bank')>Bank</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Amount received (complete bill)</label>
                        <input type="number" step="0.01" min="0" name="amount_received" class="form-control"
                            value="{{ old('amount_received', $summary['total_due_now']) }}" @if($summary['total_due_now'] > 0) required @endif>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Notes</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2">{{ old('notes', $booking->notes) }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-warning w-100 fw-semibold">
                        <i class="bi bi-cash-coin me-1"></i>Collect &amp; Complete Checkout
                    </button>
                </form>
                <a href="{{ route('guest-rooms.checkout-counter.unpaid-bill', ['booking' => $booking, 'print' => 1]) }}" class="btn btn-outline-primary w-100 mt-2 btn-sm" target="_blank">
                    <i class="bi bi-receipt me-1"></i>Print Unpaid Bill
                </a>
                <p class="small text-secondary mt-2 mb-0">Late checkout charge save karne ke liye pehle neeche &ldquo;Save late checkout&rdquo; dabayein, phir unpaid bill print karein.</p>
                <form method="POST" action="{{ route('guest-rooms.bookings.charges.store', $booking) }}" class="mt-2">
                    @csrf
                    <input type="hidden" name="redirect_to" value="checkout-counter">
                    <input type="hidden" name="charge_type" value="{{ \App\Models\RoomBookingCharge::TYPE_LATE_CHECKOUT }}">
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="number" step="0.01" min="0" name="amount" class="form-control form-control-sm"
                                value="{{ old('amount', $lateCheckoutAmount > 0 ? $lateCheckoutAmount : '') }}"
                                placeholder="Late checkout amount" required>
                        </div>
                        <div class="col-6">
                            <input type="text" name="notes" class="form-control form-control-sm"
                                value="{{ old('notes', $lateCheckoutNotes) }}"
                                placeholder="Detail (optional)">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-outline-warning w-100 mt-2 btn-sm">Save late checkout</button>
                </form>
                <a href="{{ route('guest-rooms.bookings.show', $booking) }}" class="btn btn-outline-secondary w-100 mt-2 btn-sm">Booking detail</a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-12">
        @include('guest-rooms.bookings.partials.damage-charges', ['booking' => $booking, 'redirectTo' => 'show'])
    </div>
</div>
@endsection
