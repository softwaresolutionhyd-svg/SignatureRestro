<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Advance Receipt — {{ $advance->employee?->name }} — {{ $companyName }}</title>
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
            min-height: 180mm;
            margin: 0 auto;
            background: #fff;
            border: 2px solid #111;
            padding: 14mm 16mm 16mm;
        }
        .slip-head {
            text-align: center;
            margin-bottom: 10mm;
            padding-bottom: 6mm;
            border-bottom: 2px solid #111;
        }
        .slip-title { font-size: 26px; font-weight: 700; margin: 0 0 6px; }
        .slip-brand-sub {
            font-size: 14px;
            color: #333;
            margin: 0 0 6px;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 700;
        }
        .slip-subtitle { font-size: 15px; margin: 0; color: #444; }
        .slip-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 16px;
            padding: 10px 0;
            border-bottom: 1px dashed #ccc;
            font-size: 15px;
        }
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
        .slip-signatures {
            margin-top: 24mm;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20mm;
        }
        .sig-line {
            border-bottom: 1.5px solid #111;
            height: 24mm;
            margin-bottom: 8px;
        }
        .sig-label {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #333;
            text-align: center;
        }
        @media print {
            body { padding: 0; background: #fff; }
            .noprint { display: none !important; }
            .slip-sheet {
                max-width: none;
                width: auto;
                min-height: auto;
                margin: 0;
                border: none;
                padding: 0;
            }
            @page { size: A4 portrait; margin: 12mm; }
        }
    </style>
</head>
<body>
    <div class="noprint">
        @if (session('status'))
            <div class="alert alert-success text-start mb-3">{{ session('status') }}</div>
        @endif
        <button type="button" onclick="window.print()">Print Advance Receipt</button>
        <a href="{{ route('employees.advances.index') }}">Back to Advances</a>
    </div>

    <div class="slip-sheet">
        <header class="slip-head">
            <h1 class="slip-title">{{ $companyName }}</h1>
            <p class="slip-brand-sub">Employee Advance Receipt</p>
            <p class="slip-subtitle">Advance #{{ $advance->id }} · {{ $advance->start_date?->format('d M Y') ?? $advance->created_at?->format('d M Y') }}</p>
        </header>

        <div class="slip-row"><span class="slip-label">Employee ID</span><span class="slip-value">{{ $advance->employee?->employee_no }}</span></div>
        <div class="slip-row"><span class="slip-label">Employee Name</span><span class="slip-value">{{ $advance->employee?->name }}</span></div>
        <div class="slip-row"><span class="slip-label">Advance Date</span><span class="slip-value">{{ $advance->start_date?->format('d M Y') ?? '—' }}</span></div>
        <div class="slip-row"><span class="slip-label">Status</span><span class="slip-value">{{ ucfirst($advance->status) }}</span></div>
        @if(filled($advance->notes))
        <div class="slip-row"><span class="slip-label">Notes</span><span class="slip-value">{{ $advance->notes }}</span></div>
        @endif
        <div class="slip-row total"><span class="slip-label">Advance Amount</span><span class="slip-value">{{ number_format((float) $advance->amount, 2) }}</span></div>
        <div class="slip-row"><span class="slip-label">Outstanding Balance</span><span class="slip-value">{{ number_format((float) $advance->balance, 2) }}</span></div>

        <footer class="slip-signatures" aria-label="Signatures">
            <div>
                <div class="sig-line" aria-hidden="true"></div>
                <div class="sig-label">Manager Signature</div>
            </div>
            <div>
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
