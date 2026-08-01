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
            font-size: 14px;
            background: #f1f5f9;
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
        .slip-sheet {
            width: 100%;
            max-width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: #fff;
            border: 2px solid #111;
            padding: 14mm 16mm 16mm;
            display: flex;
            flex-direction: column;
        }
        .slip-head {
            text-align: center;
            margin-bottom: 10mm;
            padding-bottom: 6mm;
            border-bottom: 2px solid #111;
        }
        .slip-title {
            font-size: 26px;
            font-weight: 700;
            margin: 0 0 6px;
            letter-spacing: 0.4px;
        }
        .slip-brand-sub {
            font-size: 14px;
            color: #333;
            margin: 0 0 6px;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 700;
        }
        .slip-subtitle {
            font-size: 15px;
            margin: 0;
            color: #444;
        }
        .slip-body {
            flex: 1 1 auto;
        }
        .slip-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 16px;
            padding: 10px 0;
            border-bottom: 1px dashed #ccc;
            font-size: 15px;
        }
        .slip-row:last-child { border-bottom: none; }
        .slip-row.total {
            font-weight: 700;
            font-size: 18px;
            padding-top: 14px;
            margin-top: 8px;
            border-top: 2px solid #111;
            border-bottom: none;
        }
        .slip-label { color: #444; flex-shrink: 0; }
        .slip-value { text-align: right; font-weight: 600; }
        .status-paid { color: #166534; }
        .status-unpaid { color: #b45309; }
        .slip-signatures {
            margin-top: auto;
            padding-top: 18mm;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20mm;
        }
        .sig-block {
            text-align: center;
        }
        .sig-line {
            border-bottom: 1.5px solid #111;
            height: 28mm;
            margin-bottom: 8px;
        }
        .sig-label {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #333;
        }
        @media print {
            body {
                padding: 0;
                background: #fff;
            }
            .noprint { display: none !important; }
            .slip-sheet {
                max-width: none;
                width: auto;
                min-height: calc(297mm - 24mm);
                margin: 0;
                border: none;
                padding: 0;
            }
            @page {
                size: A4 portrait;
                margin: 12mm;
            }
            .slip-title { font-size: 28px; }
            .slip-row { font-size: 16px; }
            .slip-row.total { font-size: 20px; }
        }
        @media screen and (max-width: 640px) {
            .slip-sheet {
                min-height: auto;
                padding: 16px;
            }
            .slip-signatures {
                grid-template-columns: 1fr;
                gap: 24px;
                padding-top: 32px;
            }
            .sig-line { height: 48px; }
        }
    </style>
</head>
<body>
    <div class="noprint">
        <button type="button" onclick="window.print()">Print Salary Slip</button>
        <a href="{{ route('employees.payroll.index', ['period' => $period]) }}">Back to Payroll</a>
    </div>

    <div class="slip-sheet">
        <header class="slip-head">
            <h1 class="slip-title">{{ $companyName }}</h1>
            <p class="slip-brand-sub">Salary Slip</p>
            <p class="slip-subtitle">{{ $periodLabel }}@if(!empty($row['staff_category'])) · {{ $row['staff_category'] }}@endif</p>
        </header>

        <div class="slip-body">
            <div class="slip-row"><span class="slip-label">Employee ID</span><span class="slip-value">{{ $row['employee_no'] }}</span></div>
            <div class="slip-row"><span class="slip-label">Employee Name</span><span class="slip-value">{{ $row['name'] }}</span></div>
            <div class="slip-row"><span class="slip-label">Designation</span><span class="slip-value">{{ $row['designation'] }}</span></div>
            <div class="slip-row"><span class="slip-label">Basic Salary</span><span class="slip-value">{{ number_format($row['basic_salary'], 2) }}</span></div>
            <div class="slip-row"><span class="slip-label">Working Days (P+H)</span><span class="slip-value">{{ $row['working_days'] ?? 0 }}</span></div>
            <div class="slip-row"><span class="slip-label">Present Days</span><span class="slip-value">{{ $row['present_days'] ?? 0 }}</span></div>
            <div class="slip-row"><span class="slip-label">Holidays</span><span class="slip-value">{{ $row['holiday_days'] ?? 0 }}</span></div>
            <div class="slip-row"><span class="slip-label">Absent Days</span><span class="slip-value">{{ $row['absent_days'] ?? 0 }}</span></div>
            <div class="slip-row"><span class="slip-label">Days Deduction (30 − working)</span><span class="slip-value">{{ number_format($row['deduction'], 2) }}</span></div>
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

        <footer class="slip-signatures" aria-label="Signatures">
            <div class="sig-block">
                <div class="sig-line" aria-hidden="true"></div>
                <div class="sig-label">Manager Signature</div>
            </div>
            <div class="sig-block">
                <div class="sig-line" aria-hidden="true"></div>
                <div class="sig-label">Employee Signature</div>
            </div>
        </footer>
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
