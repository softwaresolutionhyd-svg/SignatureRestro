@extends('layouts.admin')
@section('title', 'Housekeeping')
@section('content')
@include('guest-rooms.partials.subnav')

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0">Housekeeping — Room Cleaning</h4>
        <div class="text-secondary small">Checkout ke baad rooms yahan aati hain — clean karke available mark karein</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('guest-rooms.room-maintenance.index') }}" class="btn btn-outline-warning btn-sm">Maintenance queue</a>
        <a href="{{ route('guest-rooms.room-maintenance.create') }}" class="btn btn-outline-secondary btn-sm">Send to maintenance</a>
        <a href="{{ route('guest-rooms.rooms.index', ['status' => 'cleaning']) }}" class="btn btn-outline-secondary btn-sm">All cleaning rooms</a>
    </div>
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
                            <div class="small text-secondary">{{ $room->category?->name ?? '—' }} @if($room->floor)· Floor {{ $room->floor }}@endif</div>
                        </div>
                        <span class="badge bg-warning text-dark">Cleaning</span>
                    </div>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Checklist progress</span>
                            <span class="fw-semibold">{{ $room->cleaningProgressPercent() }}%</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-success" style="width: {{ $room->cleaningProgressPercent() }}%"></div>
                        </div>
                    </div>
                    @if($room->cleaning_started_at)
                        <p class="small text-secondary mb-3">Since {{ $room->cleaningStartedAtLabel() }}</p>
                    @endif
                    <a href="{{ route('guest-rooms.cleaning.show', $room) }}" class="btn btn-primary btn-sm w-100">
                        Open checklist
                    </a>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5 text-secondary">
                    No rooms pending cleaning.
                </div>
            </div>
        </div>
    @endforelse
</div>
@endsection
