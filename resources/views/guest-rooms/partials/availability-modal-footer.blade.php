@if($availability && $availability['available']->isNotEmpty())
    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
    <a href="{{ route('guest-rooms.bookings.create', array_filter([
        'check_in_date' => fmt_date($availability['check_in']),
        'check_out_date' => fmt_date($availability['check_out']),
        'room_category_id' => $availability['category_id'],
    ])) }}" class="btn btn-success btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Create booking
    </a>
@else
    <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">Close</button>
@endif
