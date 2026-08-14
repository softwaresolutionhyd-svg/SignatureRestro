<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ ($single ?? false) ? 'Employee QR Card' : 'Employee QR Cards' }} — {{ $companyName }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 12px;
            font-family: ui-sans-serif, system-ui, sans-serif;
            background: #f3f4f6;
            color: #111827;
        }
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }
        .sheet { display: flex; flex-wrap: wrap; gap: 12px; }
        .qr-card {
            width: 85.6mm;
            height: 54mm;
            background: #fff;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 6mm 5mm 5mm;
            display: flex;
            gap: 4mm;
            page-break-inside: avoid;
            overflow: hidden;
        }
        .qr-card .meta { flex: 1; min-width: 0; display: flex; flex-direction: column; }
        .qr-card .co {
            font-size: 8.5pt;
            font-weight: 700;
            letter-spacing: .02em;
            color: #6b2d3c;
            text-transform: uppercase;
        }
        .qr-card .name {
            font-size: 12.5pt;
            font-weight: 700;
            margin-top: 3mm;
            line-height: 1.2;
        }
        .qr-card .no { font-size: 9pt; color: #4b5563; margin-top: 1mm; }
        .qr-card .role { font-size: 8.5pt; color: #6b7280; margin-top: auto; }
        .qr-card .code {
            width: 28mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .qr-card .code svg { width: 26mm; height: 26mm; }
        .qr-card .scan-hint { font-size: 6.5pt; color: #9ca3af; margin-top: 1mm; text-align: center; }
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none !important; }
            .qr-card { border-color: #9ca3af; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <strong>{{ ($single ?? false) ? 'Print this card' : 'Print all QR cards' }}</strong>
        <button type="button" onclick="window.print()">Print</button>
    </div>
    <div class="sheet">
        @forelse($employees as $emp)
            <article class="qr-card">
                <div class="meta">
                    <div class="co">{{ $companyName }}</div>
                    <div class="name">{{ $emp->name }}</div>
                    <div class="no">{{ $emp->employee_no }}</div>
                    <div class="role">{{ $emp->designation?->name ?: 'Staff' }} · Attendance card</div>
                </div>
                <div class="code">
                    {!! $qrAttendance->svgForEmployee($emp, 180) !!}
                    <div class="scan-hint">Scan = Present</div>
                </div>
            </article>
        @empty
            <p>No active employees.</p>
        @endforelse
    </div>
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 250));</script>
</body>
</html>
