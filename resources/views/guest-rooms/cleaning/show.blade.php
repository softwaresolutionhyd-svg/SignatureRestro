@extends('layouts.admin')
@section('title', 'Cleaning — Room ' . $room->room_number)
@section('content')
@include('guest-rooms.partials.subnav')

<div class="mb-3">
    <a href="{{ ($fromDashboard ?? false) ? route('guest-rooms.index') : route('guest-rooms.cleaning.index') }}" class="text-secondary small">&larr; {{ ($fromDashboard ?? false) ? 'Back to dashboard' : 'Back to cleaning queue' }}</a>
    <h4 class="fw-bold mb-0 mt-1">Room {{ $room->room_number }} — Cleaning checklist</h4>
    <div class="text-secondary small">{{ $room->category?->name ?? '—' }} @if($room->cleaningStartedAtLabel())· Started {{ $room->cleaningStartedAtLabel() }}@endif</div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Check all items before marking available</div>
            <div class="card-body">
                <form method="POST" action="{{ route('guest-rooms.cleaning.update', $room) }}" id="cleaning-form">
                    @csrf
                    @method('PUT')
                    @if($fromDashboard ?? false)
                        <input type="hidden" name="return_to" value="dashboard">
                    @endif
                    @php $checklist = $room->cleaning_checklist ?? []; @endphp
                    <div class="list-group list-group-flush mb-4">
                        @foreach(\App\Models\GuestRoom::cleaningTaskLabels() as $key => $label)
                            <label class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3">
                                <input type="checkbox" class="form-check-input flex-shrink-0 mt-0 cleaning-task" name="checklist[{{ $key }}]" value="1" @checked(old('checklist.'.$key, !empty($checklist[$key])))>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-outline-primary">Save progress</button>
                        <button type="submit" name="mark_available" value="1" class="btn btn-success" id="btn-mark-available" @disabled(!$room->isCleaningChecklistComplete())>
                            Mark as Available
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-semibold">Progress</h6>
                <div class="display-6 fw-bold text-success mb-2" id="progress-pct">{{ $room->cleaningProgressPercent() }}%</div>
                <div class="progress mb-3" style="height: 10px;">
                    <div class="progress-bar bg-success" id="progress-bar" style="width: {{ $room->cleaningProgressPercent() }}%"></div>
                </div>
                <p class="small text-secondary mb-0">When every item is checked, use <strong>Mark as Available</strong> to release the room for new bookings.</p>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const tasks = document.querySelectorAll('.cleaning-task');
    const total = tasks.length;
    const btn = document.getElementById('btn-mark-available');
    const pctEl = document.getElementById('progress-pct');
    const barEl = document.getElementById('progress-bar');

    function refresh() {
        const done = [...tasks].filter(t => t.checked).length;
        const pct = total ? Math.round((done / total) * 100) : 0;
        if (pctEl) pctEl.textContent = pct + '%';
        if (barEl) barEl.style.width = pct + '%';
        if (btn) btn.disabled = done < total;
    }

    tasks.forEach(t => t.addEventListener('change', refresh));
    refresh();
})();
</script>
@endsection
