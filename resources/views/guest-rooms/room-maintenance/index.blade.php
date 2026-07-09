@extends('layouts.admin')
@section('title', 'Room Maintenance')
@section('content')
@include('guest-rooms.partials.subnav')

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Room Maintenance</h4>
        <div class="text-secondary small">Rooms out of service until repair checklist is complete</div>
    </div>
    <a href="{{ route('guest-rooms.room-maintenance.create') }}" class="btn btn-primary btn-sm">+ Send room to maintenance</a>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="row g-3">
    @forelse($rooms as $room)
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h5 class="fw-bold mb-0">Room {{ $room->room_number }}</h5>
                            <div class="small text-secondary">{{ $room->category?->name ?? '—' }}</div>
                        </div>
                        <span class="badge bg-secondary">Maintenance</span>
                    </div>
                    <p class="small mb-2"><strong>Issue:</strong> {{ $room->maintenanceReasonLabel() ?? '—' }}</p>
                    @if($room->maintenance_notes)
                        <p class="small text-secondary mb-2">{{ Str::limit($room->maintenance_notes, 80) }}</p>
                    @endif
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Repair checklist</span>
                            <span class="fw-semibold">{{ $room->maintenanceProgressPercent() }}%</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-success" style="width: {{ $room->maintenanceProgressPercent() }}%"></div>
                        </div>
                    </div>
                    <a href="{{ route('guest-rooms.room-maintenance.show', $room) }}" class="btn btn-primary btn-sm w-100">Open checklist</a>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5 text-secondary">
                    No rooms in maintenance.
                    <div class="mt-2"><a href="{{ route('guest-rooms.room-maintenance.create') }}">Send a room to maintenance</a></div>
                </div>
            </div>
        </div>
    @endforelse
</div>
@endsection
