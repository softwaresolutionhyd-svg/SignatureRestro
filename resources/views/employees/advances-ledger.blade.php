@extends('layouts.admin')

@section('title', 'Advance Ledger — ' . ($employee->name ?? '') . ' — ' . config('app.name'))

@section('content')
@include('hr.partials.subnav')

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <div>
            <h1 class="h4 mb-1">Advance Ledger</h1>
            <div class="text-secondary small">
                <span class="fw-semibold text-dark">{{ $employee->employee_no }}</span>
                — {{ $employee->name }}
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('employees.advances.create', ['employee_id' => $employee->id]) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle me-1"></i> Add New Entry
            </a>
            <a href="{{ route('employees.advances.index') }}" class="btn btn-outline-secondary btn-sm">Back to Advances</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">All Advance Entries</div>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end">Balance</th>
                    <th>Start Date</th>
                    <th>Status</th>
                    <th>Settled</th>
                    <th>Notes</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($advances as $advance)
                    <tr>
                        <td class="text-secondary">{{ $advance->id }}</td>
                        <td class="text-end">{{ number_format((float) $advance->amount, 2) }}</td>
                        <td class="text-end fw-semibold {{ (float) $advance->balance > 0 ? 'text-danger' : 'text-success' }}">
                            {{ number_format((float) $advance->balance, 2) }}
                        </td>
                        <td>{{ $advance->start_date?->format('Y-m-d') ?? '—' }}</td>
                        <td>
                            @if($advance->status === 'active')
                                <span class="badge text-bg-warning text-dark">Active</span>
                            @elseif($advance->status === 'settled')
                                <span class="badge text-bg-success">Settled</span>
                            @else
                                <span class="badge text-bg-secondary">Cancelled</span>
                            @endif
                        </td>
                        <td class="small">
                            @if($advance->settled_period || $advance->settled_at)
                                {{ $advance->settled_period ?: '—' }}
                                @if($advance->settled_at)
                                    <div class="text-secondary">{{ $advance->settled_at->format('d M Y H:i') }}</div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="small text-secondary">{{ $advance->notes ? \Illuminate\Support\Str::limit($advance->notes, 40) : '—' }}</td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-danger" href="{{ route('employees.advances.print', $advance) }}" target="_blank" rel="noopener">
                                <i class="bi bi-printer"></i> Print
                            </a>
                            @if($advance->settledPayrollEntry)
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('employees.payroll.slip', $advance->settledPayrollEntry) }}" target="_blank" rel="noopener">
                                    Slip
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-secondary py-4">Abhi koi advance entry nahi.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($advances->hasPages())
            <div class="card-footer bg-white">
                {{ $advances->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
@endsection
