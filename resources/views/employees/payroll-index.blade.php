@extends('layouts.admin')

@section('title', 'Payroll — ' . config('app.name'))

@section('content')
@include('hr.partials.subnav')

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card shadow-sm mb-3">
        <div class="card-body row g-3 align-items-center">
            <div class="col-md-4">
                <form class="d-flex gap-2 align-items-center flex-wrap" method="GET" action="{{ route('employees.payroll.index') }}">
                    <label class="small mb-0 text-secondary">Period</label>
                    <input type="month" name="period" value="{{ $period }}" class="form-control form-control-sm" style="max-width: 160px;">
                    <button class="btn btn-sm btn-outline-primary" type="submit">View</button>
                </form>
            </div>
            <div class="col-md-4">
                <form method="POST" action="{{ route('employees.payroll.generate') }}" onsubmit="return confirm('Create missing draft rows from employee salaries for {{ $period }}?');">
                    @csrf
                    <input type="hidden" name="period" value="{{ $period }}">
                    <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-magic me-1"></i> Generate missing drafts</button>
                </form>
            </div>
            <div class="col-md-4 text-md-end small text-secondary">
                <div>Total net: <strong>{{ number_format($totalNet, 2) }}</strong></div>
                <div>Paid net: <strong>{{ number_format($paidNet, 2) }}</strong></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">Payroll entries</div>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Employee</th>
                    <th>Base</th>
                    <th>Bonus</th>
                    <th>Deduction</th>
                    <th>Net</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($entries as $entry)
                    @if($entry->status === 'draft')
                        @php $pf = 'payroll-upd-'.$entry->id; @endphp
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $entry->employee?->name }}</span>
                                <div class="small text-secondary">{{ $entry->employee?->employee_no }}</div>
                            </td>
                            <td>{{ number_format((float)$entry->base_salary, 2) }}</td>
                            <td style="min-width: 110px;">
                                <input type="number" step="0.01" min="0" name="bonus" form="{{ $pf }}" value="{{ old('bonus', $entry->bonus) }}" class="form-control form-control-sm">
                            </td>
                            <td style="min-width: 110px;">
                                <input type="number" step="0.01" min="0" name="deduction" form="{{ $pf }}" value="{{ old('deduction', $entry->deduction) }}" class="form-control form-control-sm">
                            </td>
                            <td class="fw-semibold">{{ number_format((float)$entry->net_pay, 2) }}</td>
                            <td><span class="badge text-bg-warning text-dark">Draft</span></td>
                            <td class="text-end" style="min-width: 200px;">
                                <form id="{{ $pf }}" action="{{ route('employees.payroll.update', $entry) }}" method="POST" class="d-none">
                                    @csrf
                                    @method('PUT')
                                </form>
                                <input type="text" name="notes" form="{{ $pf }}" value="{{ $entry->notes }}" class="form-control form-control-sm mb-1" placeholder="Notes">
                                <div class="d-flex gap-1 justify-content-end flex-wrap">
                                    <button class="btn btn-sm btn-outline-primary" type="submit" form="{{ $pf }}">Save</button>
                                    <form method="POST" action="{{ route('employees.payroll.paid', $entry) }}" class="d-inline" onsubmit="return confirm('Mark this row as paid?');">
                                        @csrf
                                        <button class="btn btn-sm btn-success" type="submit">Mark paid</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @else
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $entry->employee?->name }}</span>
                                <div class="small text-secondary">{{ $entry->employee?->employee_no }}</div>
                            </td>
                            <td>{{ number_format((float)$entry->base_salary, 2) }}</td>
                            <td>{{ number_format((float)$entry->bonus, 2) }}</td>
                            <td>{{ number_format((float)$entry->deduction, 2) }}</td>
                            <td class="fw-semibold">{{ number_format((float)$entry->net_pay, 2) }}</td>
                            <td>
                                <span class="badge text-bg-success">Paid</span>
                                <div class="small text-secondary">{{ $entry->paid_at?->format('Y-m-d H:i') }}</div>
                            </td>
                            <td class="text-end small text-secondary">
                                @if($entry->notes){{ $entry->notes }}@else — @endif
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="7" class="text-center text-secondary py-4">No payroll rows. Generate drafts for this period.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">
            {{ $entries->links('pagination::bootstrap-5') }}
        </div>
    </div>

    @if($entries->count() > 0)
        <div class="small text-secondary mt-2">
            Deduction attendance se auto aati hai: <strong>Absent days × (Basic Salary ÷ 30)</strong>.
            Bonus change karne ke baad Save karein — net = base + bonus − deduction.
        </div>
    @endif
@endsection
