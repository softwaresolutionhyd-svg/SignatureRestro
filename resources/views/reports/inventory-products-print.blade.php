<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventory Products — {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 16px 20px 32px;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            font-size: 12px;
            color: #111;
            background: #fff;
        }
        h1 { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .meta { color: #444; font-size: 12px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 5px 7px; vertical-align: top; }
        th { background: #f3f4f6; font-weight: 700; text-align: left; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        tfoot td { font-weight: 700; background: #f9fafb; }
        tr.out td { background: #fee2e2; }
        tr.low td { background: #fef9c3; }
        .noprint { margin-bottom: 16px; display: flex; gap: 8px; flex-wrap: wrap; }
        .noprint button, .noprint a {
            padding: 8px 14px; font-size: 13px; border-radius: 6px; cursor: pointer;
            text-decoration: none; border: 1px solid #ccc; background: #fff; color: #111;
        }
        .noprint .primary { background: #dc3545; border-color: #dc3545; color: #fff; }
        @media print {
            body { padding: 0; }
            .noprint { display: none !important; }
            tr.out td { background: #fee2e2 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            tr.low td { background: #fef9c3 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            @page { size: A4 landscape; margin: 10mm; }
        }
    </style>
</head>
<body>
    <div class="noprint">
        <button type="button" class="primary" onclick="window.print()">Print</button>
        <a href="{{ route('reports.inventory', request()->only(['filter', 'department_id'])) }}">← Back to Inventory Report</a>
    </div>

    <h1>Inventory Products</h1>
    <div class="meta">
        <strong>{{ $filterLabel }}</strong>
        @if($department)
            &nbsp;|&nbsp; Department: <strong>{{ $department->name }}</strong>
        @endif
        &nbsp;|&nbsp; Date: {{ now()->format('d M Y, h:i A') }}
        &nbsp;|&nbsp; Items: {{ $products->count() }}
    </div>

    <table>
        <thead>
        <tr>
            <th style="width:36px;">#</th>
            <th style="width:90px;">SKU</th>
            <th>Name</th>
            <th style="width:100px;">Unit</th>
            <th class="num" style="width:70px;">Qty</th>
            <th class="num" style="width:80px;">Cost</th>
            <th class="num" style="width:80px;">Price</th>
        </tr>
        </thead>
        <tbody>
        @forelse($products as $i => $p)
            @php
                $qty = (float) $p->qty_on_hand;
                $rowClass = $qty <= 0 ? 'out' : ($qty <= 10 ? 'low' : '');
            @endphp
            <tr class="{{ $rowClass }}">
                <td>{{ $i + 1 }}</td>
                <td>{{ $p->sku }}</td>
                <td>{{ $p->name }}</td>
                <td>{{ $p->uom ?: '—' }}</td>
                <td class="num">{{ fmt_num($qty, 2) }}</td>
                <td class="num">{{ $currency }} {{ fmt_num($p->cost, 2) }}</td>
                <td class="num">{{ $currency }} {{ fmt_num($p->price, 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7" style="text-align:center;padding:24px;">No products found.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    @if(request()->boolean('print'))
    <script>window.addEventListener('load', () => window.print());</script>
    @endif
</body>
</html>
