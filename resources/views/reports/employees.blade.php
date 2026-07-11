@extends('layouts.admin')
@section('title', 'Employee Report — ' . config('app.name'))

@section('content')
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-0">Employee Report</h4>
        <div class="text-secondary small">Staff directory & salary breakdown</div>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-danger btn-sm">Print / PDF</button>
        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm">← All Reports</a>
    </div>
</div>

{{-- Filters --}}
<form method="GET" class="card shadow-sm border-0 mb-4">
    <div class="card-body d-flex flex-wrap align-items-end gap-3">
        <div>
            <label class="form-label small fw-semibold mb-1">Department</label>
            <select name="dept" class="form-select">
                <option value="">All Departments</option>
                @foreach($departments as $d)
                <option value="{{ $d->id }}" {{ $dept == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label small fw-semibold mb-1">Status</label>
            <select name="status" class="form-select">
                <option value="all"      {{ $status==='all'      ? 'selected' : '' }}>All</option>
                <option value="active"   {{ $status==='active'   ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ $status==='inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>
        <button class="btn btn-primary">Apply</button>
        <a href="{{ route('reports.employees') }}" class="btn btn-outline-secondary">Reset</a>
    </div>
</form>

{{-- KPI --}}
<div class="row g-3 mb-4">
    @foreach([
        ['Total Shown', $employees->count(), 'bi-people', '#ec4899'],
        ['Active', $activeCount, 'bi-person-check', '#22c55e'],
        ['Inactive', $inactiveCount, 'bi-person-x', '#ef4444'],
        ['Monthly Payroll', $currency.' '.fmt_num($totalSalary,2), 'bi-wallet2', '#7c3aed'],
    ] as [$label,$val,$icon,$color])
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex gap-2 align-items-center mb-2">
                    <i class="bi {{ $icon }}" style="color:{{ $color }};font-size:1.2rem;"></i>
                    <span class="text-secondary small">{{ $label }}</span>
                </div>
                <div class="fw-bold fs-5">{{ $val }}</div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-semibold">Salary by Department</div>
            <div class="card-body"><canvas id="deptChart" height="180"></canvas></div>
        </div>
    </div>
    <div class="col-12 col-md-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between">
                Employees <span class="badge bg-pink" style="background:#ec4899;">{{ $employees->count() }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr><th>#</th><th>Name</th><th>Department</th><th>Designation</th><th>Join Date</th><th class="text-end">Salary</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    @forelse($employees as $e)
                    <tr>
                        <td class="small text-secondary">{{ $e->employee_no }}</td>
                        <td class="fw-semibold small">{{ $e->name }}</td>
                        <td class="small">{{ optional($e->department)->name ?? '—' }}</td>
                        <td class="small">{{ optional($e->designation)->name ?? '—' }}</td>
                        <td class="small">{{ optional($e->join_date)->format('d M Y') ?? '—' }}</td>
                        <td class="text-end small fw-semibold">{{ $currency }} {{ fmt_num($e->salary,2) }}</td>
                        <td>
                            <span class="badge {{ $e->active ? 'bg-success' : 'bg-secondary' }}">
                                {{ $e->active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center py-4 text-secondary">No employees found</td></tr>
                    @endforelse
                    </tbody>
                    @if($employees->count())
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="5">Total Monthly Payroll</td>
                            <td class="text-end">{{ $currency }} {{ fmt_num($totalSalary,2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('deptChart'), {
    type: 'bar',
    data: {
        labels: @json($chartLabels),
        datasets: [{
            label: 'Salary',
            data: @json($chartData),
            backgroundColor: ['#ec4899','#7c3aed','#0ea5e9','#22c55e','#f97316','#eab308'],
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        indexAxis: 'y',
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { callback: v => '{{ $currency }}' + v.toLocaleString() } },
            y: { grid: { display: false } }
        }
    }
});
</script>
@include('reports.partials.print-portrait')
@endsection
