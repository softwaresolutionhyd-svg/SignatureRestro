<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Salary Slip — {{ $row['name'] }} — {{ $periodLabel }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
            margin: 0;
            padding: 16px;
            font-size: 13px;
            background: #f8fafc;
        }
        .noprint { margin-bottom: 16px; text-align: center; }
        .noprint button, .noprint a {
            display: inline-block;
            padding: 8px 14px;
            margin: 0 4px;
            border: 1px solid #666;
            border-radius: 6px;
            background: #fff;
            cursor: pointer;
            text-decoration: none;
            color: #111;
        }
        .slip-page {
            max-width: 420px;
            margin: 0 auto;
            background: #fff;
        }
        .slip-box {
            border: 2px solid #111;
            padding: 20px 22px;
        }
        .slip-title {
            text-align: center;
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 4px;
            letter-spacing: 0.4px;
        }
        .slip-brand-sub {
            text-align: center;
            font-size: 11px;
            color: #555;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .slip-subtitle {
            text-align: center;
            font-size: 13px;
            margin-bottom: 18px;
            color: #333;
        }
        .slip-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 6px 0;
            border-bottom: 1px dashed #ddd;
        }
        .slip-row:last-child { border-bottom: none; }
        .slip-row.total {
            font-weight: 700;
            font-size: 15px;
            padding-top: 12px;
            margin-top: 4px;
            border-top: 2px solid #111;
            border-bottom: none;
        }
        .slip-label { color: #555; flex-shrink: 0; }
        .slip-value { text-align: right; font-weight: 600; }
        .status-paid { color: #166534; }
        .status-unpaid { color: #b45309; }
        @media print {
            body { padding: 0; background: #fff; }
            .noprint { display: none !important; }
            .slip-page { max-width: none; margin: 0; }
            @page { size: A4 portrait; margin: 15mm; }
        }
    </style>
</head>
<body>
    <div class="noprint">
        <button type="button" onclick="window.print()">Print Salary Slip</button>
        <a href="{{ route('employees.payroll.index', ['period' => $period]) }}">Back to Payroll</a>
    </div>

    <div class="slip-page">
        <div class="slip-box">
            <div class="slip-title">{{ $companyName }}</div>
            <div class="slip-brand-sub">Salary Slip</div>
            <div class="slip-subtitle">{{ $periodLabel }}@if(!empty($row['staff_category'])) · {{ $row['staff_category'] }}@endif</div>

            <div class="slip-row"><span class="slip-label">Employee ID</span><span class="slip-value">{{ $row['employee_no'] }}</span></div>
            <div class="slip-row"><span class="slip-label">Employee Name</span><span class="slip-value">{{ $row['name'] }}</span></div>
            <div class="slip-row"><span class="slip-label">Designation</span><span class="slip-value">{{ $row['designation'] }}</span></div>
            <div class="slip-row"><span class="slip-label">Basic Salary</span><span class="slip-value">{{ number_format($row['basic_salary'], 2) }}</span></div>
            <div class="slip-row"><span class="slip-label">Working Days</span><span class="slip-value">{{ $row['working_days'] }}</span></div>
            <div class="slip-row"><span class="slip-label">Attendance Deduction</span><span class="slip-value">{{ number_format($row['deduction'], 2) }}</span></div>
            <div class="slip-row"><span class="slip-label">Food Bill (Credit)</span><span class="slip-value">{{ number_format($row['food_bill'], 2) }}</span></div>
            <div class="slip-row"><span class="slip-label">Loan</span><span class="slip-value">{{ number_format($row['loan'], 2) }}</span></div>
            @if($row['bonus'] > 0)
            <div class="slip-row"><span class="slip-label">Bonus</span><span class="slip-value">{{ number_format($row['bonus'], 2) }}</span></div>
            @endif
            <div class="slip-row total"><span class="slip-label">Final Salary</span><span class="slip-value">{{ number_format($row['final_salary'], 2) }}</span></div>
            <div class="slip-row">
                <span class="slip-label">Status</span>
                <span class="slip-value {{ $row['status_key'] === 'paid' ? 'status-paid' : 'status-unpaid' }}">{{ $row['status'] }}</span>
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('load', () => {
            if (new URLSearchParams(window.location.search).get('auto') === '1') {
                window.print();
            }
        });
    </script>
</body>
</html>
