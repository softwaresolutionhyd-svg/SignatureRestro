@extends('layouts.admin')

@section('title', 'My attendance — ' . config('app.name'))

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card shadow-sm mb-3">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <div class="fw-semibold">{{ $employee->name }}</div>
                <div class="text-secondary small">{{ $employee->employee_no }} · {{ $today }}</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if(!$todayRow || !$todayRow->clock_in)
                    <form method="POST" action="{{ route('my-attendance.clock-in') }}">
                        @csrf
                        <button class="btn btn-success" type="submit"><i class="bi bi-box-arrow-in-right me-1"></i> Clock in</button>
                    </form>
                @else
                    <span class="badge text-bg-success align-self-center">In: {{ $todayRow->clock_in->format('H:i') }}</span>
                @endif

                @if($todayRow && $todayRow->clock_in && !$todayRow->clock_out)
                    <form method="POST" action="{{ route('my-attendance.clock-out') }}">
                        @csrf
                        <button class="btn btn-outline-danger" type="submit"><i class="bi bi-box-arrow-left me-1"></i> Clock out</button>
                    </form>
                @elseif($todayRow && $todayRow->clock_out)
                    <span class="badge text-bg-secondary align-self-center">Out: {{ $todayRow->clock_out->format('H:i') }}</span>
                @endif
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <div class="fw-semibold">History</div>
            <form method="GET" action="{{ route('my-attendance') }}" class="d-flex gap-2 align-items-center">
                <input type="month" name="month" value="{{ $month }}" class="form-control form-control-sm">
                <button class="btn btn-sm btn-outline-primary" type="submit">Go</button>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Clock in</th>
                    <th>Clock out</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row->attendance_date?->format('Y-m-d') }}</td>
                        <td class="text-secondary">{{ $row->clock_in?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td class="text-secondary">{{ $row->clock_out?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td><span class="badge text-bg-light border">{{ $row->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-secondary py-4">No entries this month.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
