@if($booking->status === 'checked_in')
<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-white fw-semibold">Correct guest details</div>
    <div class="card-body">
        <p class="small text-secondary mb-3">
            If PA No, name, members, or other guest info was typed wrong, update it here.
            Each change is saved in the log below (previous value, new value, date, and user).
        </p>
        <form method="POST" action="{{ route('guest-rooms.bookings.guest-details.update', $booking) }}" id="guest-details-correction-form">
            @csrf
            @method('PATCH')
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Guest type *</label>
                    <select name="person_type" class="form-select" required>
                        <option value="">Select</option>
                        @foreach($personTypes as $pt)
                            <option value="{{ $pt }}" @selected(old('person_type', $booking->person_type) === $pt)>{{ $pt }}</option>
                        @endforeach
                    </select>
                </div>
                @if(($booking->booking_type ?? \App\Models\RoomBooking::TYPE_MANUAL) === \App\Models\RoomBooking::TYPE_MANUAL)
                    @include('guest-rooms.bookings.partials.guest-category-field', ['booking' => $booking])
                @endif
                @include('guest-rooms.bookings.partials.guest-fields', ['booking' => $booking, 'includePhone' => true])
                <div class="col-md-2">
                    <label class="form-label">Adults *</label>
                    <input type="number" name="adults" id="booking_adults" class="form-control" value="{{ old('adults', $booking->adults ?? 1) }}" min="1" max="20" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Children</label>
                    <input type="number" name="children" id="booking_children" class="form-control" value="{{ old('children', $booking->children ?? 0) }}" min="0" max="20">
                </div>
                @include('guest-rooms.bookings.partials.guest-members-fields', ['booking' => $booking])
                @include('guest-rooms.bookings.partials.guest-vehicles-fields', ['booking' => $booking])
            </div>
            <button type="submit" class="btn btn-primary btn-sm mt-3">Save corrections</button>
        </form>

        @if($booking->guestDetailChanges->isNotEmpty())
        <hr class="my-4">
        <h6 class="fw-semibold mb-2">Change history</h6>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Date &amp; time</th>
                        <th>Changed by</th>
                        <th>Field</th>
                        <th>Previous</th>
                        <th>New</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($booking->guestDetailChanges as $change)
                    <tr>
                        <td class="small text-nowrap">{{ fmt_datetime($change->changed_at) }}</td>
                        <td class="small">{{ $change->changedBy?->name ?? '—' }}</td>
                        <td class="small">{{ $change->field_label ?? $change->field }}</td>
                        <td class="small">{{ $change->displayOldValue() }}</td>
                        <td class="small">{{ $change->displayNewValue() }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
@push('scripts')
@include('guest-rooms.bookings.partials.guest-members-script')
@include('guest-rooms.bookings.partials.guest-vehicles-script')
@endpush
@endif
