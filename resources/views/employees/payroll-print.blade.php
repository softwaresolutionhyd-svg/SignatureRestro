<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Salary Record — {{ $periodLabel }} — {{ $companyName }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #111; margin: 0; padding: 16px; font-size: 12px; }
        h1, h2, h3 { margin: 0 0 8px; }
        .noprint { margin-bottom: 16px; }
        .noprint button, .noprint a {
            display: inline-block; padding: 8px 14px; margin-right: 8px;
            border: 1px solid #666; border-radius: 6px; background: #fff; cursor: pointer; text-decoration: none; color: #111;
        }
        .report-head { margin-bottom: 16px; border-bottom: 2px solid #111; padding-bottom: 10px; }
        .meta { color: #444; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-size: 11px; text-transform: uppercase; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        .status-paid { color: #166534; font-weight: 700; }
        .status-unpaid { color: #b45309; font-weight: 700; }
        .slip-section { page-break-before: always; margin-top: 24px; }
        .slip-section:first-of-type { page-break-before: auto; }
        .slip-box {
            border: 2px solid #111; padding: 16px; max-width: 520px; margin: 0 auto 20px;
        }
        .slip-title { text-align: center; font-size: 16px; font-weight: 700; margin-bottom: 12px; }
        .slip-row { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px dashed #ddd; }
        .slip-row:last-child { border-bottom: none; font-weight: 700; font-size: 14px; padding-top: 10px; }
        .slip-label { color: #555; }
        @media print {
            body { padding: 0; }
            .noprint { display: none !important; }
            @page { size: A4 portrait; margin: 12mm; }
        }
    </style>
</head>
<body>
    <div class="noprint">
        <button type="button" onclick="window.print()">Print Salary Record</button>
        <a href="{{ route('employees.payroll.index', ['period' => $period]) }}">Back to Payroll</a>
    </div>

    <div class="report-head">
        <h1>{{ $companyName }} — Salary Record</h1>
        <div class="meta">Period: <strong>{{ $periodLabel }}</strong> ({{ $period }}) · Printed {{ now()->timezone(config('app.timezone'))->format('d M Y, H:i') }}</div>
    </div>

    <table>
        <thead>
        <tr>
            <th>Employee ID</th>
            <th>Employee Name</th>
            <th>Designation</th>
            <th class="num">Basic Salary</th>
            <th class="num">Working Days</th>
            <th class="num">Deduction</th>
            <th class="num">Food Bill</th>
            <th class="num">Loan</th>
            <th class="num">Final Salary</th>
            <th>Status</th>
        </tr>
        </thead>
        <tbody>
        @foreach($rows as $row)
            <tr>
                <td>{{ $row['employee_no'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td>{{ $row['designation'] }}</td>
                <td class="num">{{ number_format($row['basic_salary'], 2) }}</td>
                <td class="num">{{ $row['working_days'] }}</td>
                <td class="num">{{ number_format($row['deduction'], 2) }}</td>
                <td class="num">{{ number_format($row['food_bill'], 2) }}</td>
                <td class="num">{{ number_format($row['loan'], 2) }}</td>
                <td class="num">{{ number_format($row['final_salary'], 2) }}</td>
                <td class="{{ $row['status_key'] === 'paid' ? 'status-paid' : 'status-unpaid' }}">{{ $row['status'] }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
        <tr>
            <th colspan="8" class="num">Grand Total (Final Salary)</th>
            <th class="num">{{ number_format(collect($rows)->sum('final_salary'), 2) }}</th>
            <th></th>
        </tr>
        </tfoot>
    </table>

    <h2 style="margin-top: 28px; margin-bottom: 12px;">Individual Salary Slips</h2>

    @foreach($rows as $row)
        <div class="slip-section">
            <div class="slip-box">
                <div class="slip-title">{{ $companyName }}</div>
                <div class="slip-title" style="font-size: 13px; margin-bottom: 16px;">Salary Slip — {{ $periodLabel }}</div>

                <div class="slip-row"><span class="slip-label">Employee ID</span><span>{{ $row['employee_no'] }}</span></div>
                <div class="slip-row"><span class="slip-label">Employee Name</span><span>{{ $row['name'] }}</span></div>
                <div class="slip-row"><span class="slip-label">Designation</span><span>{{ $row['designation'] }}</span></div>
                <div class="slip-row"><span class="slip-label">Basic Salary</span><span>{{ number_format($row['basic_salary'], 2) }}</span></div>
                <div class="slip-row"><span class="slip-label">Working Days</span><span>{{ $row['working_days'] }}</span></div>
                <div class="slip-row"><span class="slip-label">Attendance Deduction</span><span>{{ number_format($row['deduction'], 2) }}</span></div>
                <div class="slip-row"><span class="slip-label">Food Bill (Credit)</span><span>{{ number_format($row['food_bill'], 2) }}</span></div>
                <div class="slip-row"><span class="slip-label">Loan</span><span>{{ number_format($row['loan'], 2) }}</span></div>
                @if($row['bonus'] > 0)
                <div class="slip-row"><span class="slip-label">Bonus</span><span>{{ number_format($row['bonus'], 2) }}</span></div>
                @endif
                <div class="slip-row"><span class="slip-label">Final Salary</span><span>{{ number_format($row['final_salary'], 2) }}</span></div>
                <div class="slip-row"><span class="slip-label">Status</span><span class="{{ $row['status_key'] === 'paid' ? 'status-paid' : 'status-unpaid' }}">{{ $row['status'] }}</span></div>
            </div>
        </div>
    @endforeach

    <script>
        window.addEventListener('load', () => {
            if (new URLSearchParams(window.location.search).get('auto') === '1') {
                window.print();
            }
        });
    </script>
</body>
</html>
