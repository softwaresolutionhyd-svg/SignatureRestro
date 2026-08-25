<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ ($single ?? false) ? 'Employee ID Card' : 'Employee ID Cards' }} — {{ $companyName }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=playfair-display:600,700|dm-sans:400,500,600,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --wine: #6b2d3c;
            --wine-deep: #4a1f2a;
            --gold: #c9a84c;
            --gold-light: #e8c872;
            --cream: #faf6ef;
            --ink: #1c1410;
            --muted: #7a6f63;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 14px;
            font-family: 'DM Sans', ui-sans-serif, system-ui, sans-serif;
            background: #d6d3d1;
            color: var(--ink);
        }
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
            padding: 12px 14px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 1px 4px rgba(0,0,0,.08);
        }
        .toolbar-actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .toolbar button, .toolbar a.btn-link {
            padding: 8px 16px;
            border: 0;
            border-radius: 6px;
            background: var(--wine);
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
            display: inline-block;
        }
        .toolbar a.btn-muted {
            background: #fff;
            color: var(--ink);
            border: 1px solid #ccc;
        }
        .toolbar form {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        .toolbar select {
            padding: 6px 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 12px;
            max-width: 180px;
        }
        .toolbar label.chk {
            display: inline-flex;
            gap: 4px;
            align-items: center;
            font-size: 12px;
            white-space: nowrap;
        }
        .report-meta {
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 10px;
        }
        .sheet {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180mm, 1fr));
            gap: 12px 16px;
            justify-content: center;
        }
        .id-pack {
            display: flex;
            flex-wrap: wrap;
            gap: 6mm;
            page-break-inside: avoid;
            break-inside: avoid;
            justify-content: flex-start;
            padding: 4px;
            position: relative;
        }
        .id-pack-select {
            position: absolute;
            top: 2px;
            left: 2px;
            z-index: 2;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 7px;
            background: rgba(255,255,255,.95);
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            color: var(--ink);
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
            cursor: pointer;
            user-select: none;
        }
        .id-pack-select input {
            width: 15px;
            height: 15px;
            margin: 0;
            cursor: pointer;
        }
        .id-pack.is-checked {
            outline: 2px solid var(--wine);
            outline-offset: 2px;
            border-radius: 6px;
        }
        .toolbar button.btn-print-only {
            background: #fff;
            color: var(--wine);
            border: 1px solid var(--wine);
        }
        .toolbar label.chk-select-all {
            display: inline-flex;
            gap: 4px;
            align-items: center;
            font-size: 12px;
            white-space: nowrap;
            font-weight: 600;
        }
        #printOnlyCount {
            font-size: 12px;
            color: var(--muted);
            min-width: 4.5em;
        }
        .id-face {
            width: 85.6mm;
            height: 54mm;
            background: var(--cream);
            border: 0.35mm solid var(--gold);
            border-radius: 3.2mm;
            overflow: hidden;
            position: relative;
            display: flex;
            flex-direction: column;
            box-shadow: 0 8px 22px rgba(74, 31, 42, .12);
        }
        .id-face::before {
            content: "";
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 1.8mm;
            background: linear-gradient(180deg, var(--gold-light), var(--gold), #a8863a);
        }
        .id-head {
            background: linear-gradient(105deg, var(--wine-deep) 0%, var(--wine) 55%, #8d4454 100%);
            color: #faf6ef;
            display: flex;
            align-items: center;
            gap: 2.6mm;
            padding: 2.2mm 4mm 2.2mm 5mm;
            min-height: 10mm;
            flex-shrink: 0;
        }
        .id-head img {
            height: 6.8mm;
            width: auto;
            max-width: 12mm;
            object-fit: contain;
            background: #fff;
            border-radius: 50%;
            padding: 0.4mm;
            border: 0.25mm solid var(--gold);
        }
        .id-head .hotel {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 10pt;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            line-height: 1.1;
        }
        .gold-line {
            height: 0.7mm;
            background: linear-gradient(90deg, var(--gold), var(--gold-light), var(--gold));
            flex-shrink: 0;
        }
        .id-front-body {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 3.2mm;
            padding: 2.6mm 4mm 2mm 5.2mm;
            min-height: 0;
        }
        .id-photo {
            width: 21mm;
            height: 27mm;
            border: 0.4mm solid var(--gold);
            border-radius: 1.4mm;
            overflow: hidden;
            background: #fff;
            flex-shrink: 0;
            box-shadow: 0 1mm 2mm rgba(74, 31, 42, .12);
        }
        .id-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .id-photo .ph {
            width: 100%; height: 100%;
            display: flex; align-items: center; justify-content: center;
            font-size: 14pt; font-weight: 700; color: var(--wine);
            background: #f0e8da;
        }
        .id-meta { min-width: 0; flex: 1; }
        .id-meta .name {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 10.5pt;
            font-weight: 700;
            line-height: 1.12;
            color: var(--wine-deep);
            margin-bottom: 1.2mm;
        }
        .id-meta .row {
            font-size: 6.7pt;
            line-height: 1.38;
            display: grid;
            grid-template-columns: 16mm 1fr;
            gap: 1mm;
            align-items: baseline;
        }
        .id-meta .lbl {
            color: var(--muted);
            font-weight: 600;
            letter-spacing: .02em;
        }
        .id-meta .val {
            font-weight: 700;
            color: var(--ink);
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .id-foot {
            padding: 0 4mm 2mm 5.2mm;
            font-size: 6.2pt;
            color: var(--muted);
            letter-spacing: .08em;
            text-transform: uppercase;
            flex-shrink: 0;
        }
        .id-back-body {
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.2mm 4mm 1.8mm 5mm;
            gap: 0.9mm;
        }
        .qr-plate {
            width: 20.5mm;
            height: 20.5mm;
            flex-shrink: 0;
            background: #fff;
            border: 0.35mm solid var(--gold);
            border-radius: 1.4mm;
            padding: 1mm;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 1mm 2mm rgba(74, 31, 42, .08);
        }
        .qr-plate svg {
            width: 18mm !important;
            height: 18mm !important;
            display: block;
            max-width: 100%;
            max-height: 100%;
        }
        .id-back-body .bname {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 9pt;
            font-weight: 700;
            text-align: center;
            line-height: 1.15;
            color: var(--wine-deep);
            max-width: 72mm;
        }
        .id-back-body .baddr {
            font-size: 6.4pt;
            line-height: 1.3;
            text-align: center;
            color: var(--ink);
            max-width: 72mm;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .id-back-body .hint {
            font-size: 5.8pt;
            color: var(--muted);
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        @media print {
            @page { size: A4 portrait; margin: 8mm; }
            body { background: #fff; padding: 0; }
            .toolbar, .report-meta, .id-pack-select { display: none !important; }
            .sheet {
                display: block;
            }
            .id-pack {
                display: flex;
                gap: 5mm;
                margin-bottom: 6mm;
                page-break-inside: avoid;
                break-inside: avoid;
                outline: none !important;
            }
            body.print-selection .id-pack:not(.is-checked) {
                display: none !important;
            }
            .id-face, .id-head, .gold-line, .id-photo, .qr-plate {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .id-face { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <strong>{{ ($single ?? false) ? 'Print ID card (front + back)' : 'All employees ID cards (PDF)' }}</strong>
            @unless($single ?? false)
                <div class="report-meta" style="margin:4px 0 0;">
                    {{ $employees->count() }} card{{ $employees->count() === 1 ? '' : 's' }}
                    @isset($printedAt) · {{ $printedAt }}@endisset
                    · Print / Save as PDF se ek saath cards nikal lo
                </div>
            @endunless
            @if(!empty($qrBaseUrl))
                @php
                    $qrHost = strtolower((string) (parse_url($qrBaseUrl, PHP_URL_HOST) ?: ''));
                    $qrHostIsLoopback = in_array($qrHost, ['127.0.0.1', 'localhost', '::1'], true);
                @endphp
                <div class="report-meta" style="margin:4px 0 0;">
                    QR opens: <code>{{ $qrBaseUrl }}/a/…</code>
                    @if($qrHostIsLoopback)
                        — Settings → LAN Server IP set karein, phir cards dubara print karein
                    @endif
                </div>
            @endif
        </div>
        <div class="toolbar-actions">
            @unless($single ?? false)
                <form method="GET" action="{{ route('employees.qr-cards') }}">
                    <select name="staff_category_id" onchange="this.form.submit()" title="Staff category">
                        <option value="">All categories</option>
                        @foreach(($categories ?? []) as $cat)
                            <option value="{{ $cat->id }}" @selected(($staffCategoryId ?? null) == $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    <select name="designation_id" onchange="this.form.submit()" title="Designation">
                        <option value="">All designations</option>
                        @foreach(($designations ?? []) as $des)
                            <option value="{{ $des->id }}" @selected(($designationId ?? null) == $des->id)>{{ $des->name }}</option>
                        @endforeach
                    </select>
                    <select name="photo" onchange="this.form.submit()" title="Photo filter">
                        <option value="all" @selected(($photoFilter ?? 'all') === 'all')>All</option>
                        <option value="with" @selected(($photoFilter ?? 'all') === 'with')>With pictures</option>
                        <option value="without" @selected(($photoFilter ?? 'all') === 'without')>Without pictures</option>
                    </select>
                    <label class="chk">
                        <input type="hidden" name="active_only" value="0">
                        <input type="checkbox" name="active_only" value="1" @checked($activeOnly ?? true) onchange="this.form.submit()">
                        Active only
                    </label>
                </form>
            @endunless
            @unless($single ?? false)
                <label class="chk-select-all">
                    <input type="checkbox" id="selectAllCards">
                    Select all
                </label>
                <span id="printOnlyCount">0 selected</span>
                <button type="button" class="btn-print-only" id="printOnlyBtn">Print only</button>
            @endunless
            <button type="button" id="printAllBtn">Print / PDF</button>
            <a class="btn-link btn-muted" href="{{ route('employees.index') }}">Back</a>
        </div>
    </div>
    <div class="sheet">
        @forelse($employees as $emp)
            @php
                $photo = $emp->photoUrl();
                $initials = mb_strtoupper(mb_substr(trim((string) $emp->name), 0, 1));
                $addressLine = implode(', ', array_filter([
                    trim((string) ($emp->address ?? '')),
                    trim((string) ($emp->city ?? '')),
                    trim((string) ($emp->district ?? '')),
                ], fn ($v) => $v !== ''));
            @endphp
            <section class="id-pack" data-employee-id="{{ $emp->id }}">
                @unless($single ?? false)
                    <label class="id-pack-select">
                        <input type="checkbox" class="card-select" value="{{ $emp->id }}">
                        Print
                    </label>
                @endunless
                <article class="id-face">
                    <header class="id-head">
                        @if(!empty($companyLogo))
                            <img src="{{ $companyLogo }}" alt="">
                        @endif
                        <div class="hotel">{{ $companyName }}</div>
                    </header>
                    <div class="gold-line"></div>
                    <div class="id-front-body">
                        <div class="id-photo">
                            @if($photo)
                                <img src="{{ $photo }}" alt="">
                            @else
                                <div class="ph">{{ $initials }}</div>
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
                            <div class="row">
                                <span class="lbl">Mobile</span>
                                <span class="val">{{ $emp->phone ?: '—' }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="id-foot">{{ $emp->employee_no }} · Staff identity card</div>
                </article>

                <article class="id-face">
                    <header class="id-head">
                        @if(!empty($companyLogo))
                            <img src="{{ $companyLogo }}" alt="">
                        @endif
                        <div class="hotel">{{ $companyName }}</div>
                    </header>
                    <div class="gold-line"></div>
                    <div class="id-back-body">
                        <div class="qr-plate">
                            {!! $qrAttendance->svgForEmployee($emp, 140) !!}
                        </div>
                        <div class="bname">{{ $emp->name }}</div>
                        @if($addressLine !== '')
                            <div class="baddr">{{ $addressLine }}</div>
                        @endif
                        <div class="hint">Scan for attendance</div>
                    </div>
                </article>
            </section>
        @empty
            <p>No employees match these filters.</p>
        @endforelse
    </div>
    @unless($single ?? false)
        <script>
        (function () {
            const packs = () => [...document.querySelectorAll('.id-pack')];
            const boxes = () => [...document.querySelectorAll('.card-select')];
            const selectAll = document.getElementById('selectAllCards');
            const countEl = document.getElementById('printOnlyCount');
            const printOnlyBtn = document.getElementById('printOnlyBtn');
            const printAllBtn = document.getElementById('printAllBtn');

            function syncPack(box) {
                const pack = box.closest('.id-pack');
                if (pack) pack.classList.toggle('is-checked', box.checked);
            }

            function updateCount() {
                const selected = boxes().filter((b) => b.checked).length;
                const total = boxes().length;
                if (countEl) countEl.textContent = `${selected} selected`;
                if (selectAll) {
                    selectAll.checked = total > 0 && selected === total;
                    selectAll.indeterminate = selected > 0 && selected < total;
                }
            }

            boxes().forEach((box) => {
                box.addEventListener('change', () => {
                    syncPack(box);
                    updateCount();
                });
            });

            selectAll?.addEventListener('change', () => {
                boxes().forEach((box) => {
                    box.checked = selectAll.checked;
                    syncPack(box);
                });
                updateCount();
            });

            printAllBtn?.addEventListener('click', () => {
                document.body.classList.remove('print-selection');
                window.print();
            });

            printOnlyBtn?.addEventListener('click', () => {
                const selected = boxes().filter((b) => b.checked);
                if (selected.length === 0) {
                    alert('Pehle employees select karein jinke cards print karne hain.');
                    return;
                }
                document.body.classList.add('print-selection');
                window.print();
            });

            window.addEventListener('afterprint', () => {
                document.body.classList.remove('print-selection');
            });

            updateCount();
        })();
        </script>
    @endunless
    @if($single ?? false)
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 400));</script>
    @endif
</body>
</html>
