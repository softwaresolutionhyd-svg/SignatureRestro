<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>All Recipes — {{ $companyName }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { size: A4 portrait; margin: 12mm; }
        body {
            margin: 0;
            padding: 16px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #111;
            background: #f8fafc;
        }
        .noprint { margin-bottom: 14px; text-align: center; }
        .noprint button, .noprint a {
            display: inline-block;
            padding: 8px 14px;
            margin: 0 4px;
            border: 1px solid #666;
            border-radius: 6px;
            background: #fff;
            color: #111;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
        }
        .sheet {
            max-width: 210mm;
            margin: 0 auto;
            background: #fff;
            padding: 10mm 12mm;
            border: 1px solid #ddd;
        }
        .doc-head {
            text-align: center;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 2px solid #111;
        }
        .doc-head h1 { margin: 0 0 4px; font-size: 20px; }
        .doc-head .sub { margin: 0; font-size: 13px; color: #444; }
        .doc-head .meta { margin: 6px 0 0; font-size: 11px; color: #666; }
        .recipe {
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px dashed #bbb;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .recipe:last-child { border-bottom: none; margin-bottom: 0; }
        .dish-name {
            margin: 0 0 8px;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0.2px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        th, td {
            border: 1px solid #333;
            padding: 5px 8px;
            vertical-align: top;
        }
        th { background: #f3f4f6; text-align: left; font-weight: 700; }
        td.qty, th.qty { text-align: right; white-space: nowrap; width: 140px; }
        .empty-ing { color: #666; font-style: italic; padding: 8px 0; }
        @media print {
            body { padding: 0; background: #fff; }
            .noprint { display: none !important; }
            .sheet { max-width: none; border: none; padding: 0; }
            .recipe { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="noprint">
        <button type="button" onclick="window.print()">Print / PDF</button>
        <a href="{{ route('manufacturing.boms.index', request()->only(['q', 'finished_product', 'return'])) }}">Back to BoMs</a>
    </div>

    <div class="sheet">
        <header class="doc-head">
            <h1>{{ $companyName }}</h1>
            <p class="sub">All Recipes</p>
            <p class="meta">
                Printed {{ now()->timezone(config('app.timezone'))->format('d M Y, h:i A') }}
                · {{ $boms->count() }} recipe{{ $boms->count() === 1 ? '' : 's' }}
                @if(($q ?? '') !== '')
                    · Filter: “{{ $q }}”
                @endif
            </p>
        </header>

        @forelse($boms as $bom)
            <section class="recipe">
                <h2 class="dish-name">{{ $bom->finishedProduct?->name ?? '—' }}</h2>

                @if($bom->lines->isEmpty())
                    <div class="empty-ing">No ingredients in this recipe.</div>
                @else
                    <table>
                        <thead>
                            <tr>
                                <th>Ingredient</th>
                                <th class="qty">Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($bom->lines as $line)
                                <tr>
                                    <td>{{ $line->component?->name ?? '—' }}</td>
                                    <td class="qty">{{ fmt_num((float) $line->qty, 3) }} {{ $line->effectiveUom() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>
        @empty
            <p style="text-align:center;color:#666;padding:24px 0;">No recipes found.</p>
        @endforelse
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
