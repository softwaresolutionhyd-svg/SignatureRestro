@extends('layouts.admin')
@section('title', 'Maintenance — Room ' . $room->room_number)
@section('content')
@include('guest-rooms.partials.subnav')

<div class="mb-3">
    <a href="{{ route('guest-rooms.room-maintenance.index') }}" class="text-secondary small">&larr; Back to maintenance queue</a>
    <h4 class="fw-bold mb-0 mt-1">Room {{ $room->room_number }} — Maintenance</h4>
    <div class="text-secondary small">
        {{ $room->maintenanceReasonLabel() ?? 'Maintenance' }}
        @if($room->maintenanceStartedAtLabel())· Since {{ $room->maintenanceStartedAtLabel() }}@endif
    </div>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

@if($room->maintenance_notes)
<div class="alert alert-light border mb-3"><strong>Notes:</strong> {{ $room->maintenance_notes }}</div>
@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Complete repair checklist to mark available</div>
            <div class="card-body">
                <form method="POST" action="{{ route('guest-rooms.room-maintenance.update', $room) }}">
                    @csrf
                    @method('PUT')
                    @php $checklist = $room->maintenance_checklist ?? []; @endphp
                    <div class="list-group list-group-flush mb-4">
                        @foreach(\App\Models\GuestRoom::maintenanceTaskLabels() as $key => $label)
                            <label class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3">
                                <input type="checkbox" class="form-check-input flex-shrink-0 mt-0 maintenance-task" name="checklist[{{ $key }}]" value="1" @checked(old('checklist.'.$key, !empty($checklist[$key])))>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-outline-primary">Save progress</button>
                        <button type="submit" name="mark_available" value="1" class="btn btn-success" id="btn-mark-available" @disabled(!$room->isMaintenanceChecklistComplete())>
                            Mark as Available
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">Repair expense record</div>
            <div class="card-body">
                <form method="POST" action="{{ route('guest-rooms.room-maintenance.update', $room) }}">
                    @csrf
                    @method('PUT')
                    <div class="mb-2">
                        <label class="form-label small">Muramat ka kharcha (Rs.)</label>
                        <input type="number" step="0.01" min="0" name="maintenance_cost" class="form-control" value="{{ old('maintenance_cost', $room->maintenance_cost) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Bill / voucher reference</label>
                        <input type="text" name="maintenance_bill_reference" class="form-control" maxlength="120" value="{{ old('maintenance_bill_reference', $room->maintenance_bill_reference) }}" placeholder="Bill no, vendor, etc.">
                    </div>
                    <button type="submit" class="btn btn-outline-secondary btn-sm">Save expense record</button>
                </form>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-semibold">Progress</h6>
                <div class="display-6 fw-bold text-success mb-2" id="progress-pct">{{ $room->maintenanceProgressPercent() }}%</div>
                <div class="progress mb-3" style="height: 10px;">
                    <div class="progress-bar bg-success" id="progress-bar" style="width: {{ $room->maintenanceProgressPercent() }}%"></div>
                </div>
                <p class="small text-secondary mb-0">Room will not accept bookings until marked available.</p>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const tasks = document.querySelectorAll('.maintenance-task');
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
