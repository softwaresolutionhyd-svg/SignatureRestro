@extends('layouts.admin')
@section('title', 'Send to Maintenance')
@section('content')
@include('guest-rooms.partials.subnav')

<div class="mb-3">
    <a href="{{ route('guest-rooms.room-maintenance.index') }}" class="text-secondary small">&larr; Back</a>
    <h4 class="fw-bold mb-0 mt-1">Send room to maintenance</h4>
</div>

@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ $room ? route('guest-rooms.room-maintenance.store', $room) : '#' }}" id="maintenance-form">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Room *</label>
                        @if($room)
                            <input type="hidden" name="room_id" value="{{ $room->id }}">
                            <p class="form-control-plaintext fw-semibold mb-0">Room {{ $room->room_number }} ({{ $room->category?->name ?? '—' }})</p>
                        @else
                            <select name="room_id" id="room_id" class="form-select" required onchange="if(this.value) window.location='{{ route('guest-rooms.room-maintenance.create') }}?room='+this.value">
                                <option value="">Select room</option>
                                @foreach($selectableRooms as $r)
                                    <option value="{{ $r->id }}">{{ $r->room_number }} — {{ \App\Models\GuestRoom::statusLabels()[$r->status] ?? $r->status }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Only available or cleaning rooms without active bookings are listed.</div>
                        @endif
                    </div>
                    @if($room)
                        <div class="mb-3">
                            <label class="form-label">Issue type *</label>
                            <select name="maintenance_reason" class="form-select" required>
                                <option value="">Select</option>
                                @foreach(\App\Models\GuestRoom::maintenanceReasonLabels() as $key => $label)
                                    <option value="{{ $key }}" @selected(old('maintenance_reason') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="maintenance_notes" class="form-control" rows="3" placeholder="Describe the problem…">{{ old('maintenance_notes') }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-secondary">Send to maintenance</button>
                        <a href="{{ route('guest-rooms.rooms.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
