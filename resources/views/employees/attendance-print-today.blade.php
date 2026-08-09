<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Attendance — {{ $dateLabel }} — {{ $companyName }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #000; margin: 0; padding: 16px; font-size: 12px; }
        .noprint { margin-bottom: 14px; }
        .noprint button, .noprint a {
            display: inline-block; padding: 8px 14px; margin-right: 8px;
            border: 1px solid #000; border-radius: 4px; background: #fff; cursor: pointer; text-decoration: none; color: #000;
        }
        .head { text-align: center; margin-bottom: 14px; border-bottom: 1px solid #000; padding-bottom: 8px; }
        .head h1 { margin: 0; font-size: 20px; }
        .head h2 { margin: 4px 0 0; font-size: 14px; font-weight: 600; }
        .head .date { margin-top: 4px; font-size: 12px; }
        .cat { margin: 0 0 12px; }
        .cat-title {
            font-size: 12px; font-weight: 700; text-transform: uppercase;
            border-bottom: 1px solid #000; padding: 2px 0 4px; margin-bottom: 4px;
        }
        table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        th, td { border: 1px solid #000; padding: 4px 6px; text-align: left; }
        th { font-size: 11px; background: #eee; }
        td.num, th.num { text-align: center; width: 36px; }
        td.status, th.status { text-align: center; width: 90px; }
        .summary { margin-top: 18px; width: 320px; }
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
        <button type="button" onclick="window.print()">Print</button>
        <a href="{{ route('employees.attendance.index', array_filter([
            'month' => \Carbon\Carbon::createFromFormat('Y-m-d', $dateKey)->format('Y-m'),
            'active_only' => $activeOnly ? 1 : null,
        ])) }}">Back</a>
        <form method="GET" action="{{ route('employees.attendance.print-today') }}" style="display:inline-flex; gap:8px; align-items:center; margin-left:8px;">
            <input type="date" name="date" value="{{ $dateKey }}" style="padding:6px 8px; border:1px solid #000;">
            <label style="display:inline-flex; gap:4px; align-items:center;">
                <input type="checkbox" name="active_only" value="1" @checked($activeOnly)> Sirf active
            </label>
            <button type="submit">Update</button>
        </form>
    </div>

    <div class="head">
        <h1>{{ $companyName }}</h1>
        <h2>Today's Attendance</h2>
        <div class="date">Date: <strong>{{ $dateLabel }}</strong></div>
    </div>

    @forelse($categoryGroups as $group)
        <div class="cat">
            <div class="cat-title">{{ $group['name'] }} ({{ count($group['rows']) }})</div>
            <table>
                <thead>
                    <tr>
                        <th class="num">#</th>
                        <th>ID</th>
                        <th>Name</th>
                        <th class="status">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($group['rows'] as $i => $row)
                        <tr>
                            <td class="num">{{ $i + 1 }}</td>
                            <td>{{ $row['employee_no'] }}</td>
                            <td>{{ $row['name'] }}</td>
                            <td class="status">{{ $row['status'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
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
                <th>Total Present</th>
                <td>{{ $totals['present'] }}</td>
            </tr>
            <tr>
                <th>Total Absent</th>
                <td>{{ $totals['absent'] }}</td>
            </tr>
            <tr>
                <th>Total Holiday</th>
                <td>{{ $totals['holiday'] }}</td>
            </tr>
            <tr>
                <th>Not Marked</th>
                <td>{{ $totals['unmarked'] }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
