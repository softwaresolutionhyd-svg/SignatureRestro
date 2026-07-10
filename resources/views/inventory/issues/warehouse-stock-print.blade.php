<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Warehouse Stock — {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 16px 20px 32px;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            font-size: 13px;
            color: #111;
            background: #fff;
        }
        h1 {
            margin: 0 0 4px;
            font-size: 20px;
            font-weight: 700;
        }
        .meta {
            color: #444;
            font-size: 12px;
            margin-bottom: 16px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #333;
            padding: 6px 8px;
            vertical-align: top;
        }
        th {
            background: #f3f4f6;
            font-weight: 700;
            text-align: left;
        }
        td.num, th.num { text-align: right; white-space: nowrap; }
        td.center { text-align: center; }
        tfoot td {
            font-weight: 700;
            background: #f9fafb;
        }
        .item-name { font-weight: 600; }
        .item-sku { font-size: 11px; color: #555; }
        .noprint {
            margin-bottom: 16px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .noprint button, .noprint a {
            padding: 8px 14px;
            font-size: 13px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid #ccc;
            background: #fff;
            color: #111;
        }
        .noprint .primary {
            background: #4f46e5;
            border-color: #4f46e5;
            color: #fff;
        }
        @media print {
            body { padding: 0; }
            .noprint { display: none !important; }
            @page { size: A4 portrait; margin: 12mm; }
        }
    </style>
</head>
<body>
    <div class="noprint">
        <button type="button" class="primary" onclick="window.print()">Print</button>
        <a href="{{ route('inventory.issues.index') }}">← Back to Issue Stock</a>
    </div>

    <h1>Warehouse Stock Report</h1>
    <div class="meta">
        <strong>{{ $warehouse->name }}</strong>
        &nbsp;|&nbsp;
        Date: {{ now()->format('d M Y, h:i A') }}
        &nbsp;|&nbsp;
        Items: {{ $lines->count() }}
    </div>

    <table>
        <thead>
        <tr>
            <th style="width:36px;" class="center">#</th>
            <th>Item</th>
            <th style="width:52px;" class="center">UOM</th>
            <th class="num" style="width:90px;">Quantity</th>
            <th class="num" style="width:100px;">Per Price</th>
            <th class="num" style="width:110px;">Amount</th>
        </tr>
        </thead>
        <tbody>
        @forelse($lines as $i => $line)
            <tr>
                <td class="center">{{ $i + 1 }}</td>
                <td>
                    <div class="item-name">{{ $line['name'] }}</div>
                    @if($line['sku'])
                        <div class="item-sku">{{ $line['sku'] }}</div>
                    @endif
                </td>
                <td class="center">{{ $line['uom'] }}</td>
                <td class="num">{{ fmt_num($line['qty'], 3) }}</td>
                <td class="num">{{ $currency }} {{ fmt_num($line['unit_price'], 2) }}</td>
                <td class="num">{{ $currency }} {{ fmt_num($line['amount'], 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="center" style="padding:24px;">Warehouse me abhi stock nahi hai.</td>
            </tr>
        @endforelse
        </tbody>
        @if($lines->isNotEmpty())
        <tfoot>
        <tr>
            <td colspan="5" class="num">Grand Total</td>
            <td class="num">{{ $currency }} {{ fmt_num($grandTotal, 2) }}</td>
        </tr>
        </tfoot>
        @endif
    </table>

    <p style="margin-top:12px;font-size:11px;color:#666;">
        Per Price = product cost (base UOM). Amount = Quantity × Per Price.
    </p>

    @if(request()->boolean('print'))
    <script>window.addEventListener('load', () => window.print());</script>
    @endif
</body>
</html>
