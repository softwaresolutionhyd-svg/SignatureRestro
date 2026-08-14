<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ ($single ?? false) ? 'Employee ID Card' : 'Employee ID Cards' }} — {{ $companyName }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 12px;
            font-family: ui-sans-serif, system-ui, sans-serif;
            background: #e5e7eb;
            color: #111827;
        }
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }
        .sheet { display: flex; flex-direction: column; gap: 14px; }
        .id-pack {
            display: flex;
            flex-wrap: wrap;
            gap: 8mm;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .id-face {
            width: 85.6mm;
            height: 54mm;
            background: #fff;
            border: 1px solid #c4b5a5;
            border-radius: 5px;
            overflow: hidden;
            position: relative;
            display: flex;
            flex-direction: column;
        }
        .id-face .side-tag {
            position: absolute;
            top: 1.5mm;
            right: 2.5mm;
            font-size: 6pt;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #a16207;
            font-weight: 700;
        }
        .id-head {
            background: linear-gradient(90deg, #6b2d3c, #8b3d4e);
            color: #faf6ef;
            display: flex;
            align-items: center;
            gap: 3mm;
            padding: 2.6mm 4mm;
            min-height: 11mm;
        }
        .id-head img {
            height: 7.5mm;
            width: auto;
            max-width: 14mm;
            object-fit: contain;
            background: #fff;
            border-radius: 2px;
            padding: 0.4mm;
        }
        .id-head .hotel {
            font-size: 9.5pt;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
            line-height: 1.15;
        }
        .id-front-body {
            flex: 1;
            display: flex;
            gap: 3.5mm;
            padding: 3mm 4mm 2.5mm;
        }
        .id-photo {
            width: 22mm;
            height: 28mm;
            border: 1px solid #d6c7b4;
            border-radius: 2px;
            overflow: hidden;
            background: #f3f4f6;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .id-photo img { width: 100%; height: 100%; object-fit: cover; }
        .id-photo .ph {
            font-size: 11pt;
            font-weight: 800;
            color: #9ca3af;
        }
        .id-meta { min-width: 0; flex: 1; display: flex; flex-direction: column; justify-content: center; }
        .id-meta .name {
            font-size: 11pt;
            font-weight: 800;
            line-height: 1.15;
            color: #1f2937;
            margin-bottom: 1.4mm;
        }
        .id-meta .row {
            font-size: 7.2pt;
            line-height: 1.35;
            color: #374151;
            display: flex;
            gap: 1.5mm;
        }
        .id-meta .lbl {
            width: 18mm;
            flex-shrink: 0;
            color: #6b7280;
            font-weight: 600;
        }
        .id-meta .val { font-weight: 700; min-width: 0; word-break: break-word; }
        .id-foot {
            padding: 0 4mm 2mm;
            font-size: 6.5pt;
            color: #6b7280;
            letter-spacing: .04em;
        }
        .id-back-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2mm 4mm 3mm;
        }
        .id-back-body .code svg { width: 28mm; height: 28mm; display: block; }
        .id-back-body .bname {
            margin-top: 1.5mm;
            font-size: 9pt;
            font-weight: 800;
            text-align: center;
            line-height: 1.2;
        }
        .id-back-body .hint {
            margin-top: 0.6mm;
            font-size: 6.5pt;
            color: #6b7280;
        }
        @media print {
            body { background: #fff; padding: 6mm; }
            .toolbar { display: none !important; }
            .id-face { border-color: #9ca3af; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .id-head { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <strong>{{ ($single ?? false) ? 'Print ID card (front + back)' : 'Print all ID cards (front + back)' }}</strong>
        <button type="button" onclick="window.print()">Print</button>
    </div>
    <div class="sheet">
        @forelse($employees as $emp)
            @php
                $photo = $emp->photoUrl();
                $initials = mb_strtoupper(mb_substr(trim((string) $emp->name), 0, 1));
            @endphp
            <section class="id-pack">
                <article class="id-face">
                    <span class="side-tag">Front</span>
                    <header class="id-head">
                        @if(!empty($companyLogo))
                            <img src="{{ $companyLogo }}" alt="">
                        @endif
                        <div class="hotel">{{ $companyName }}</div>
                    </header>
                    <div class="id-front-body">
                        <div class="id-photo">
                            @if($photo)
                                <img src="{{ $photo }}" alt="">
                            @else
                                <span class="ph">{{ $initials }}</span>
                            @endif
                        </div>
                        <div class="id-meta">
                            <div class="name">{{ $emp->name }}</div>
                            <div class="row">
                                <span class="lbl">Father</span>
                                <span class="val">{{ $emp->father_name ?: '—' }}</span>
                            </div>
                            <div class="row">
                                <span class="lbl">Designation</span>
                                <span class="val">{{ $emp->designation?->name ?: 'Staff' }}</span>
                            </div>
                            <div class="row">
                                <span class="lbl">CNIC</span>
                                <span class="val">{{ $emp->cnic ?: '—' }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="id-foot">{{ $emp->employee_no }} · Staff ID card</div>
                </article>

                <article class="id-face">
                    <span class="side-tag">Back</span>
                    <header class="id-head">
                        @if(!empty($companyLogo))
                            <img src="{{ $companyLogo }}" alt="">
                        @endif
                        <div class="hotel">{{ $companyName }}</div>
                    </header>
                    <div class="id-back-body">
                        {!! $qrAttendance->svgForEmployee($emp, 200) !!}
                        <div class="bname">{{ $emp->name }}</div>
                        <div class="hint">Scan QR = Attendance Present</div>
                    </div>
                </article>
            </section>
        @empty
            <p>No active employees.</p>
        @endforelse
    </div>
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 350));</script>
</body>
</html>
