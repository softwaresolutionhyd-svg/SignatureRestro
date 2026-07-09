@if(isset($pendingPosBills) && $pendingPosBills->isNotEmpty())
    <div class="alert alert-info mb-3">
        <div class="fw-semibold mb-1">{{ $pendingPosBills->count() }} cafe bill(s) pending — {{ fmt_num((float) $pendingPosBills->sum('grand_total'), 2) }}</div>
        <p class="small mb-2">Yeh bills Checkout Counter par complete bill ke sath collect hongi. Alag se POS par jaane ki zaroorat nahi.</p>
        @if(isset($booking))
            <a href="{{ route('guest-rooms.checkout-counter.show', $booking) }}" class="btn btn-sm btn-primary">Checkout Counter kholein</a>
        @endif
    </div>
@endif
