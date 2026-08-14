<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Employees Report — {{ $companyName }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
            margin: 0;
            padding: 16px;
            font-size: 11.5px;
            background: #fff;
        }
        .noprint { margin-bottom: 14px; }
        .noprint button, .noprint a, .noprint label, .noprint select {
            display: inline-block;
            vertical-align: middle;
        }
        .noprint button, .noprint a {
            padding: 8px 14px;
            margin-right: 8px;
            border: 1px solid #333;
            border-radius: 4px;
            background: #fff;
            cursor: pointer;
            text-decoration: none;
            color: #111;
            font-size: 12px;
        }
        .noprint form {
            display: inline-flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            margin-left: 4px;
        }
        .noprint select, .noprint input[type="checkbox"] {
            padding: 6px 8px;
            border: 1px solid #333;
            background: #fff;
        }
        .head {
            text-align: center;
            margin-bottom: 14px;
            border-bottom: 2px solid #111;
            padding-bottom: 10px;
        }
        .head img { max-height: 64px; max-width: 200px; margin-bottom: 4px; }
        .head h1 { margin: 0; font-size: 20px; letter-spacing: 0.3px; }
        .head h2 { margin: 4px 0 0; font-size: 13px; font-weight: 600; }
        .head .meta { margin-top: 4px; font-size: 11px; color: #333; }
        .group { margin: 0 0 16px; page-break-inside: avoid; }
        .group-title {
            background: #111;
            color: #fff;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 6px;
        }
        .sub-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            border-bottom: 1px solid #111;
            padding: 2px 0 4px;
            margin: 10px 0 4px;
        }
        table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        th, td {
            border: 1px solid #333;
            padding: 4px 6px;
            text-align: left;
            vertical-align: top;
        }
        th { background: #eee; font-size: 10px; text-transform: uppercase; }
        td.num, th.num { text-align: center; width: 36px; }
        td.status, th.status { text-align: center; width: 70px; }
        .summary {
            margin-top: 18px;
            width: 280px;
            page-break-inside: avoid;
        }
        .summary th { text-align: left; }
        .summary td { text-align: right; font-weight: 700; width: 70px; }
        tr { page-break-inside: avoid; }
        @media print {
            body { padding: 0; }
            .noprint { display: none !important; }
            @page { size: A4 portrait; margin: 10mm; }
        }
    </style>
</head>
<body>
    <div class="noprint">
        <button type="button" onclick="window.print()">Print / PDF</button>
        <a href="{{ route('employees.index') }}">Back</a>
        <form method="GET" action="{{ route('employees.print-report') }}">
            <label>
                Group by
                <select name="group" onchange="this.form.submit()">
                    <option value="category" @selected(($groupBy ?? '') === 'category')>Staff Category</option>
                    <option value="designation" @selected(($groupBy ?? '') === 'designation')>Designation</option>
                </select>
            </label>
            <label style="display:inline-flex; gap:4px; align-items:center;">
                <input type="checkbox" name="active_only" value="1" @checked($activeOnly) onchange="this.form.submit()">
                Active only
            </label>
            <button type="submit">Update</button>
        </form>
    </div>

    <div class="head">
        @if($logo = company_logo_url(\App\Models\Setting::get('company_logo')))
            <img src="{{ $logo }}" alt="">
        @endif
        <h1>{{ $companyName }}</h1>
        <h2>Employees Report</h2>
        <div class="meta">
            {{ ($groupBy ?? '') === 'designation' ? 'Grouped by Designation' : 'Grouped by Staff Category → Designation' }}
            &nbsp;|&nbsp; Printed: <strong>{{ $printedAt }}</strong>
            &nbsp;|&nbsp; Total: <strong>{{ $totalCount }}</strong>
            (Active {{ $activeCount }})
        </div>
    </div>

    @php $serial = 0; @endphp
    @forelse($groups as $group)
        <div class="group">
            <div class="group-title">
                {{ $group['name'] }}
                ({{ collect($group['subgroups'])->sum(fn ($s) => $s['employees']->count()) }})
            </div>

            @foreach($group['subgroups'] as $subgroup)
                @if(trim((string) ($subgroup['name'] ?? '')) !== '')
                    <div class="sub-title">{{ $subgroup['name'] }} ({{ $subgroup['employees']->count() }})</div>
                @endif
                <table>
                    <thead>
                        <tr>
                            <th class="num">#</th>
                            <th style="width:90px;">Employee #</th>
                            <th>Name</th>
                            @if(($groupBy ?? '') === 'category')
                                <th style="width:120px;">Designation</th>
                            @else
                                <th style="width:120px;">Category</th>
                            @endif
                            <th style="width:110px;">Phone</th>
                            <th style="width:90px;">Join date</th>
                            <th class="status">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($subgroup['employees'] as $employee)
                            @php $serial++; @endphp
                            <tr>
                                <td class="num">{{ $serial }}</td>
                                <td>{{ $employee->employee_no }}</td>
                                <td>{{ $employee->name }}</td>
                                @if(($groupBy ?? '') === 'category')
                                    <td>{{ $employee->designation?->name ?: '—' }}</td>
                                @else
                                    <td>{{ $employee->staffCategory?->name ?: '—' }}</td>
                                @endif
                                <td>{{ $employee->phone ?: '—' }}</td>
                                <td>{{ optional($employee->join_date)->format('Y-m-d') ?: '—' }}</td>
                                <td class="status">{{ $employee->active ? 'Active' : 'Inactive' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach
        </div>
    @empty
        <p>Koi employee nahi.</p>
    @endforelse

    <table class="summary">
        <thead>
            <tr>
                <th colspan="2">Summary</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <th>Total employees</th>
                <td>{{ $totalCount }}</td>
            </tr>
            <tr>
                <th>Active</th>
                <td>{{ $activeCount }}</td>
            </tr>
            <tr>
                <th>Inactive</th>
                <td>{{ max(0, $totalCount - $activeCount) }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
