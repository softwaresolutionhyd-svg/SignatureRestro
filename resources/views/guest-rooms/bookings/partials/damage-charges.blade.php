@php
    $damageTypes = \App\Models\RoomBookingCharge::damageChargeTypes();
    $redirectTo = $redirectTo ?? null;
@endphp
<div class="card border-0 shadow-sm {{ $class ?? '' }}">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-exclamation-triangle text-warning me-1"></i> Room damage / extra charges
        <span class="text-secondary small fw-normal">(check-in ke baad, checkout se pehle)</span>
    </div>
    <div class="card-body">
        <p class="small text-secondary mb-3">Agar room mein koi cheez tooti ya kharab hui ho to neeche se charge add karein.</p>

        <div class="row g-3 mb-4">
            @foreach([\App\Models\RoomBookingCharge::TYPE_MATTRESS, \App\Models\RoomBookingCharge::TYPE_LAUNDRY] as $typeKey)
            <div class="col-md-6">
                <div class="border rounded p-3 h-100 bg-light">
                    <div class="fw-semibold mb-2">{{ $damageTypes[$typeKey] }}</div>
                    @if($typeKey === \App\Models\RoomBookingCharge::TYPE_MATTRESS)
                        <p class="small text-secondary mb-2">Issue date se checkout (ya aaj) tak har din per-day rate multiply hoti rahegi.</p>
                    @endif
                    <form method="POST" action="{{ route('guest-rooms.bookings.charges.store', $booking) }}">
                        @csrf
                        @if($redirectTo)<input type="hidden" name="redirect_to" value="{{ $redirectTo }}">@endif
                        <input type="hidden" name="charge_type" value="{{ $typeKey }}">
                        @if($typeKey === \App\Models\RoomBookingCharge::TYPE_MATTRESS)
                        <div class="mb-2">
                            @include('partials.form-date-dmy', ['name' => 'charge_date', 'label' => 'Issue date', 'value' => now(), 'required' => true, 'class' => 'form-control form-control-sm'])
                        </div>
                        <div class="mb-2">
                            <label class="form-label small mb-0">Per day rate *</label>
                            <input type="number" step="0.01" name="amount" class="form-control form-control-sm" placeholder="0.00" required min="0.01">
                        </div>
                        @else
                        <div class="mb-2">
                            <label class="form-label small mb-0">Amount *</label>
                            <input type="number" step="0.01" name="amount" class="form-control form-control-sm" placeholder="0.00" required min="0.01">
                        </div>
                        @endif
                        <div class="mb-2">
                            <label class="form-label small mb-0">Detail (optional)</label>
                            <input name="notes" class="form-control form-control-sm" placeholder="e.g. stained, torn">
                        </div>
                        <button class="btn btn-sm btn-primary w-100">Add {{ $damageTypes[$typeKey] }}</button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>

        <div class="border rounded p-3 mb-3">
            <div class="fw-semibold small mb-2">Other charge</div>
            <form method="POST" action="{{ route('guest-rooms.bookings.charges.store', $booking) }}" class="row g-2 align-items-end">
                @csrf
                @if($redirectTo)<input type="hidden" name="redirect_to" value="{{ $redirectTo }}">@endif
                <input type="hidden" name="charge_type" value="other">
                <div class="col-md-4">
                    <label class="form-label small mb-0">Description *</label>
                    <input name="description" class="form-control form-control-sm" placeholder="e.g. Broken chair" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0">Amount *</label>
                    <input type="number" step="0.01" name="amount" class="form-control form-control-sm" required min="0.01">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0">Detail (optional)</label>
                    <input name="notes" class="form-control form-control-sm" placeholder="Notes">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-sm btn-outline-primary w-100">Add</button>
                </div>
            </form>
        </div>

        @if($booking->charges->isNotEmpty())
        <h6 class="fw-semibold small text-secondary mb-2">Added charges</h6>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Description</th>
                        <th>Date</th>
                        <th class="text-end">Amount</th>
                        @if($booking->status === 'checked_in')<th class="text-end">Action</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($booking->charges as $c)
                    @php
                        $breakdown = $c->amountBreakdownLabel($booking);
                        $lineAmount = $c->calculatedAmount($booking);
                    @endphp
                    <tr>
                        <td>
                            {{ $c->description }}
                            @if($breakdown)<div class="small text-secondary">{{ $breakdown }}</div>@endif
                        </td>
                        <td class="small text-secondary">{{ fmt_date($c->charge_date) }}</td>
                        <td class="text-end fw-semibold">{{ number_format($lineAmount, 2) }}</td>
                        @if($booking->status === 'checked_in')
                        <td class="text-end">
                            <form method="POST" action="{{ route('guest-rooms.bookings.charges.destroy', [$booking, $c]) }}" class="d-inline" onsubmit="return confirm('Remove this charge?')">
                                @csrf @method('DELETE')
                                @if($redirectTo)<input type="hidden" name="redirect_to" value="{{ $redirectTo }}">@endif
                                <button type="submit" class="btn btn-sm btn-outline-danger py-0">Remove</button>
                            </form>
                        </td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="table-light">
                        <th colspan="2" class="text-end">Extra charges total</th>
                        <th class="text-end">{{ number_format($booking->extra_charges, 2) }}</th>
                        @if($booking->status === 'checked_in')<th></th>@endif
                    </tr>
                </tfoot>
            </table>
        </div>
        @else
        <p class="small text-secondary mb-0">Abhi koi extra charge add nahi hui.</p>
        @endif
    </div>
</div>

