<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Purchase Report — {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 16px 20px 32px; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; font-size: 12px; color: #111; background: #fff; }
        h1 { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        h2 { font-size: 14px; margin: 20px 0 8px; }
        .meta { color: #444; font-size: 12px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { border: 1px solid #333; padding: 5px 7px; vertical-align: top; }
        th { background: #f3f4f6; font-weight: 700; text-align: left; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        tfoot td, tfoot th { font-weight: 700; background: #f9fafb; }
        .kpi { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; }
        .kpi div { border: 1px solid #ccc; padding: 8px 12px; border-radius: 6px; min-width: 130px; }
        .kpi strong { display: block; font-size: 16px; margin-top: 2px; }
        .noprint { margin-bottom: 16px; display: flex; gap: 8px; flex-wrap: wrap; }
        .noprint button, .noprint a { padding: 8px 14px; font-size: 13px; border-radius: 6px; cursor: pointer; text-decoration: none; border: 1px solid #ccc; background: #fff; color: #111; }
        .noprint .primary { background: #dc3545; border-color: #dc3545; color: #fff; }
        @media print { body { padding: 0; } .noprint { display: none !important; } @page { size: A4 landscape; margin: 10mm; } }
    </style>
</head>
<body>
    <div class="noprint">
        <button type="button" class="primary" onclick="window.print()">Print</button>
        <a href="{{ route('reports.purchases', request()->only(['from', 'to', 'vendor', 'status'])) }}">← Back to Report</a>
    </div>

    <h1>Purchase Report</h1>
    <div class="meta">
        Period: <strong>{{ \Carbon\Carbon::parse($from)->format('d M Y') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</strong>
        @if($selectedVendor)
            &nbsp;|&nbsp; Vendor: <strong>{{ $selectedVendor->name }}</strong>
        @endif
        @if($statusLabel)
            &nbsp;|&nbsp; Status: <strong>{{ $statusLabel }}</strong>
        @endif
        &nbsp;|&nbsp; Generated: {{ now()->format('d M Y, h:i A') }}
    </div>

    <div class="kpi">
        <div>Orders<strong>{{ $orderCount }}</strong></div>
        <div>Products<strong>{{ $productCount }}</strong></div>
        <div>Total Spend<strong>{{ $currency }} {{ fmt_num($totalAmount, 2) }}</strong></div>
        <div>Tax<strong>{{ $currency }} {{ fmt_num($totalTax, 2) }}</strong></div>
    </div>

    <h2>Vendor Breakdown</h2>
    <table>
        <thead><tr><th>Vendor</th><th class="num">Orders</th><th class="num">Total</th></tr></thead>
        <tbody>
        @forelse($byVendor as $row)
            <tr>
                <td>{{ $row['name'] }}</td>
                <td class="num">{{ $row['count'] }}</td>
                <td class="num">{{ $currency }} {{ fmt_num($row['total'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="3" style="text-align:center;">No data</td></tr>
        @endforelse
        </tbody>
    </table>

    <h2>Purchased Products (Summary)</h2>
    <table>
        <thead>
        <tr>
            <th>Product</th>
            <th>SKU</th>
            <th>UOM</th>
            <th class="num">Total Qty</th>
            <th class="num">Total Amount</th>
            <th class="num">Lines</th>
        </tr>
        </thead>
        <tbody>
        @forelse($byProduct as $row)
            <tr>
                <td>{{ $row['name'] }}</td>
                <td>{{ $row['sku'] }}</td>
                <td>{{ $row['uom'] }}</td>
                <td class="num">{{ fmt_num($row['qty'], 3) }}</td>
                <td class="num">{{ $currency }} {{ fmt_num($row['total'], 2) }}</td>
                <td class="num">{{ $row['lines'] }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align:center;">No products</td></tr>
        @endforelse
        </tbody>
        @if($byProduct->isNotEmpty())
        <tfoot>
        <tr>
            <th colspan="4" class="num">Total</th>
            <th class="num">{{ $currency }} {{ fmt_num($purchaseLines->sum('total'), 2) }}</th>
            <th class="num">{{ $lineCount }}</th>
        </tr>
        </tfoot>
        @endif
    </table>

    <h2>Purchase Details — Product wise</h2>
    <table>
        <thead>
        <tr>
            <th>PO #</th>
            <th>Date</th>
            <th>Vendor</th>
            <th>Product</th>
            <th>UOM</th>
            <th class="num">Qty</th>
            <th class="num">Unit Price</th>
            <th class="num">Amount</th>
        </tr>
        </thead>
        <tbody>
        @forelse($purchaseLines as $line)
            <tr>
                <td>{{ $line->order?->number ?? '—' }}</td>
                <td>{{ optional($line->order?->order_date)->format('d M Y') }}</td>
                <td>{{ $line->order?->vendor?->name ?? '—' }}</td>
                <td>
                    <strong>{{ $line->product?->name ?? $line->description ?? '—' }}</strong>
                    @if($line->product?->sku)<br><span style="color:#555;">{{ $line->product->sku }}</span>@endif
                </td>
                <td>{{ $line->uom ?: ($line->product?->uom ?? '—') }}</td>
                <td class="num">{{ fmt_num((float) $line->qty, 3) }}</td>
                <td class="num">{{ $currency }} {{ fmt_num((float) $line->unit_price, 2) }}</td>
                <td class="num">{{ $currency }} {{ fmt_num((float) $line->total, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="8" style="text-align:center;padding:24px;">No line items</td></tr>
        @endforelse
        </tbody>
        @if($purchaseLines->isNotEmpty())
        <tfoot>
        <tr>
            <th colspan="7" class="num">Total</th>
            <th class="num">{{ $currency }} {{ fmt_num($purchaseLines->sum('total'), 2) }}</th>
        </tr>
        </tfoot>
        @endif
    </table>

    <h2>Purchase Orders</h2>
    <table>
        <thead>
        <tr>
            <th>PO #</th>
            <th>Date</th>
            <th>Vendor</th>
            <th>Status</th>
            <th class="num">Subtotal</th>
            <th class="num">Tax</th>
            <th class="num">Total</th>
        </tr>
        </thead>
        <tbody>
        @forelse($orders as $o)
            <tr>
                <td>{{ $o->number }}</td>
                <td>{{ optional($o->order_date)->format('d M Y') }}</td>
                <td>{{ optional($o->vendor)->name }}</td>
                <td>{{ ucfirst($o->status) }}</td>
                <td class="num">{{ $currency }} {{ fmt_num($o->subtotal, 2) }}</td>
                <td class="num">{{ $currency }} {{ fmt_num($o->tax_total, 2) }}</td>
                <td class="num">{{ $currency }} {{ fmt_num($o->grand_total, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="7" style="text-align:center;">No orders</td></tr>
        @endforelse
        </tbody>
        @if($orders->isNotEmpty())
        <tfoot>
        <tr>
            <th colspan="6" class="num">Grand Total</th>
            <th class="num">{{ $currency }} {{ fmt_num($totalAmount, 2) }}</th>
        </tr>
        </tfoot>
        @endif
    </table>

    @if(request()->boolean('print'))
    <script>window.addEventListener('load', () => window.print());</script>
    @endif
</body>
</html>
