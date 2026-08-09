<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Today's Attendance — {{ $dateLabel }} — {{ $companyName }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #111; margin: 0; padding: 16px; font-size: 12px; }
        h1, h2, h3 { margin: 0 0 8px; }
        .noprint { margin-bottom: 16px; }
        .noprint button, .noprint a {
            display: inline-block; padding: 8px 14px; margin-right: 8px;
            border: 1px solid #666; border-radius: 6px; background: #fff; cursor: pointer; text-decoration: none; color: #111;
        }
        .report-head { margin-bottom: 14px; border-bottom: 2px solid #111; padding-bottom: 10px; text-align: center; }
        .report-head h1 { font-size: 22px; letter-spacing: 0.5px; }
        .meta { color: #444; font-size: 11px; }
        .summary {
            display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 16px;
        }
        .summary .chip {
            border: 1px solid #ccc; border-radius: 6px; padding: 6px 10px; min-width: 110px;
            background: #f9fafb;
        }
        .summary .chip strong { display: block; font-size: 18px; line-height: 1.1; }
        .summary .chip span { font-size: 10px; text-transform: uppercase; color: #555; letter-spacing: 0.4px; }
        .chip-p { border-color: #86efac; background: #f0fdf4; }
        .chip-a { border-color: #fca5a5; background: #fef2f2; }
        .chip-h { border-color: #93c5fd; background: #eff6ff; }
        .chip-u { border-color: #d1d5db; background: #f3f4f6; }
        .section { margin-bottom: 16px; page-break-inside: avoid; break-inside: avoid; }
        .section-head {
            padding: 6px 10px; font-size: 12px; font-weight: 700; letter-spacing: 0.5px;
            text-transform: uppercase; color: #fff; margin: 0 0 6px;
        }
        .section-head.p { background: #166534; }
        .section-head.a { background: #991b1b; }
        .section-head.h { background: #1e40af; }
        .section-head.u { background: #4b5563; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 5px 8px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-size: 11px; text-transform: uppercase; }
        td.num, th.num { text-align: center; width: 48px; }
        td.code { text-align: center; font-weight: 700; width: 56px; }
        .empty { color: #6b7280; font-style: italic; padding: 6px 2px; }
        tr { page-break-inside: avoid; break-inside: avoid; }
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
        ])) }}">Back to Attendance</a>
        <form method="GET" action="{{ route('employees.attendance.print-today') }}" style="display:inline-flex; gap:8px; align-items:center; margin-left:8px;">
            <input type="date" name="date" value="{{ $dateKey }}" style="padding:6px 8px; border:1px solid #666; border-radius:6px;">
            <label style="display:inline-flex; gap:4px; align-items:center; font-size:12px;">
                <input type="checkbox" name="active_only" value="1" @checked($activeOnly)> Sirf active
            </label>
            <button type="submit">Update</button>
        </form>
    </div>

    <div class="report-head">
        <h1>{{ $companyName }}</h1>
        <h2 style="font-size: 16px; font-weight: 600; margin-top: 4px;">Today's Attendance</h2>
        <div class="meta">
            Date: <strong>{{ $dateLabel }}</strong>
            · Printed {{ now()->timezone(config('app.timezone'))->format('d M Y, H:i') }}
            @if($activeOnly) · Active staff only @endif
        </div>
    </div>

    <div class="summary">
        <div class="chip chip-p"><strong>{{ $totals['present'] }}</strong><span>Present</span></div>
        <div class="chip chip-a"><strong>{{ $totals['absent'] }}</strong><span>Absent</span></div>
        <div class="chip chip-h"><strong>{{ $totals['holiday'] }}</strong><span>Holiday</span></div>
        <div class="chip chip-u"><strong>{{ $totals['unmarked'] }}</strong><span>Not marked</span></div>
        <div class="chip"><strong>{{ $totals['all'] }}</strong><span>Total staff</span></div>
    </div>

    @php
        $sections = [
            ['key' => 'p', 'title' => 'Present (P)', 'rows' => $present],
            ['key' => 'a', 'title' => 'Absent (A)', 'rows' => $absent],
            ['key' => 'h', 'title' => 'Holiday (H)', 'rows' => $holiday],
            ['key' => 'u', 'title' => 'Not marked (—)', 'rows' => $unmarked],
        ];
    @endphp

    @foreach($sections as $section)
        <div class="section">
            <div class="section-head {{ $section['key'] }}">
                {{ $section['title'] }} — {{ $section['rows']->count() }}
            </div>
            @if($section['rows']->isEmpty())
                <div class="empty">Koi employee nahi.</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th class="num">#</th>
                            <th>Employee ID</th>
                            <th>Name</th>
                            <th class="code">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($section['rows'] as $i => $row)
                            <tr>
                                <td class="num">{{ $i + 1 }}</td>
                                <td>{{ $row['employee_no'] }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td class="code">{{ $row['code'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach
</body>
</html>
