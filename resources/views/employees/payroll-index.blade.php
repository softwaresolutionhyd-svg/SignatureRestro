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
            <div class="col-lg-5">
                <form class="d-flex gap-2 align-items-center flex-wrap" method="GET" action="{{ route('employees.payroll.index') }}">
                    <label class="small mb-0 text-secondary">Period</label>
                    <input type="month" name="period" value="{{ $period }}" class="form-control form-control-sm" style="max-width: 160px;">
                    <input type="text" name="employee_no" value="{{ $employeeNo ?? '' }}" class="form-control form-control-sm" placeholder="ID ya naam" style="max-width: 170px;">
                    <button class="btn btn-sm btn-outline-primary" type="submit">View</button>
                    <a class="btn btn-sm btn-outline-danger" href="{{ route('employees.payroll.print', array_filter(['period' => $period, 'employee_no' => ($employeeNo ?? '') ?: null])) }}" target="_blank" rel="noopener">
                        <i class="bi bi-printer me-1"></i> Print Salary Record
                    </a>
                </form>
            </div>
            <div class="col-lg-3">
                <form method="POST" action="{{ route('employees.payroll.generate') }}" onsubmit="return confirm('Sync payroll rows for {{ $period }}?');">
                    @csrf
                    <input type="hidden" name="period" value="{{ $period }}">
                    <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-magic me-1"></i> Generate / Refresh</button>
                </form>
            </div>
            <div class="col-lg-4 text-lg-end small text-secondary">
                <div>Total net: <strong>{{ number_format($totalNet, 2) }}</strong></div>
                <div>Paid net: <strong>{{ number_format($paidNet, 2) }}</strong></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">Payroll entries — {{ $period }}</div>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Employee</th>
                    <th>Base</th>
                    <th>Days</th>
                    <th>Deduction</th>
                    <th>Food Bill</th>
                    <th>Loan</th>
                    <th>Bonus</th>
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
                                <div class="small text-secondary">{{ $entry->employee?->employee_no }} · {{ $entry->employee?->designation?->name ?? '—' }}</div>
                            </td>
                            <td>{{ number_format((float)$entry->base_salary, 2) }}</td>
                            <td>{{ $workingDays[$entry->employee_id] ?? 0 }}</td>
                            <td style="min-width: 95px;">
                                <input type="number" step="0.01" min="0" name="deduction" form="{{ $pf }}" value="{{ old('deduction', $entry->deduction) }}" class="form-control form-control-sm">
                            </td>
                            <td style="min-width: 95px;">
                                <input type="number" step="0.01" min="0" name="food_bill" form="{{ $pf }}" value="{{ old('food_bill', $entry->food_bill ?? 0) }}" class="form-control form-control-sm" title="Credit sales — auto from contact">
                            </td>
                            <td style="min-width: 95px;">
                                <input type="text" readonly class="form-control form-control-sm bg-light"
                                       value="{{ number_format((float)($entry->loan ?? 0), 2) }}"
                                       title="Auto from Employee Loan — monthly instalment">
                            </td>
                            <td style="min-width: 95px;">
                                <input type="number" step="0.01" min="0" name="bonus" form="{{ $pf }}" value="{{ old('bonus', $entry->bonus) }}" class="form-control form-control-sm">
                            </td>
                            <td class="fw-semibold">{{ number_format((float)$entry->net_pay, 2) }}</td>
                            <td><span class="badge text-bg-warning text-dark">Unpaid</span></td>
                            <td class="text-end" style="min-width: 180px;">
                                <form id="{{ $pf }}" action="{{ route('employees.payroll.update', $entry) }}" method="POST" class="d-none">
                                    @csrf
                                    @method('PUT')
                                </form>
                                <input type="text" name="notes" form="{{ $pf }}" value="{{ $entry->notes }}" class="form-control form-control-sm mb-1" placeholder="Notes">
                                <div class="d-flex gap-1 justify-content-end flex-wrap">
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('employees.payroll.slip', $entry) }}" target="_blank" rel="noopener" title="Print individual salary slip">
                                        <i class="bi bi-receipt"></i> Slip
                                    </a>
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
                                <div class="small text-secondary">{{ $entry->employee?->employee_no }} · {{ $entry->employee?->designation?->name ?? '—' }}</div>
                            </td>
                            <td>{{ number_format((float)$entry->base_salary, 2) }}</td>
                            <td>{{ $workingDays[$entry->employee_id] ?? 0 }}</td>
                            <td>{{ number_format((float)$entry->deduction, 2) }}</td>
                            <td>{{ number_format((float)($entry->food_bill ?? 0), 2) }}</td>
                            <td>{{ number_format((float)($entry->loan ?? 0), 2) }}</td>
                            <td>{{ number_format((float)$entry->bonus, 2) }}</td>
                            <td class="fw-semibold">{{ number_format((float)$entry->net_pay, 2) }}</td>
                            <td>
                                <span class="badge text-bg-success">Paid</span>
                                <div class="small text-secondary">{{ $entry->paid_at?->format('Y-m-d H:i') }}</div>
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('employees.payroll.slip', $entry) }}" target="_blank" rel="noopener" title="Print individual salary slip">
                                    <i class="bi bi-receipt"></i> Slip
                                </a>
                                @if($entry->notes)
                                    <div class="small text-secondary mt-1">{{ $entry->notes }}</div>
                                @endif
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="10" class="text-center text-secondary py-4">No payroll rows. Generate / Refresh for this period.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">
            {{ $entries->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection
