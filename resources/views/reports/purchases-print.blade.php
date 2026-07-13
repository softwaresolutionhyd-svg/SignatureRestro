@php $companyName = \App\Models\Setting::get('company_name', config('app.name')); @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Purchase Report — {{ $companyName }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { size: A4 portrait; margin: 14mm; }
        body { margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; font-size: 11pt; color: #000; background: #fff; }
        .noprint { margin-bottom: 12px; }
        .noprint button, .noprint a { margin-right: 6px; padding: 6px 12px; font-size: 12px; border: 1px solid #000; background: #fff; color: #000; cursor: pointer; text-decoration: none; }
        h1 { margin: 0 0 2px; font-size: 16pt; font-weight: bold; text-align: center; }
        h2 { margin: 16px 0 6px; font-size: 11pt; font-weight: bold; }
        .meta { font-size: 10pt; margin-bottom: 14px; line-height: 1.6; }
        table { width: 100%; border-collapse: collapse; font-size: 10pt; margin-bottom: 12px; }
        th, td { border: 1px solid #000; padding: 5px 7px; text-align: left; vertical-align: top; }
        th { font-weight: bold; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        tfoot td, tfoot th, tr.bold td { font-weight: bold; }
        @media print { .noprint { display: none; } }
    </style>
</head>
<body>
    <div class="noprint">
        <button type="button" onclick="window.print()">Print / PDF</button>
        <a href="{{ route('reports.purchases', request()->only(['from', 'to', 'vendor', 'status'])) }}">Back</a>
    </div>

    <h1>{{ $companyName }}</h1>
    <h2 style="text-align:center;font-weight:normal;margin-top:0;">Purchase Report</h2>

    <div class="meta">
        <p style="margin:0;">Period: <strong>{{ \Carbon\Carbon::parse($from)->format('d M Y') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</strong>@if($selectedVendor) &nbsp;|&nbsp; Vendor: <strong>{{ $selectedVendor->name }}</strong>@endif @if($statusLabel) &nbsp;|&nbsp; Status: <strong>{{ $statusLabel }}</strong>@endif</p>
        <p style="margin:0;">Generated: {{ now()->format('d M Y, h:i A') }}</p>
    </div>

    <table style="width:60%;">
        <tr><th>Orders</th><td class="num">{{ $orderCount }}</td></tr>
        <tr><th>Products</th><td class="num">{{ $productCount }}</td></tr>
        <tr><th>Total Spend</th><td class="num">{{ $currency }} {{ fmt_num($totalAmount, 2) }}</td></tr>
        <tr><th>Tax</th><td class="num">{{ $currency }} {{ fmt_num($totalTax, 2) }}</td></tr>
    </table>

    <h2>Purchased Products (Vendor Wise)</h2>
    @forelse($byVendorProducts as $vendorGroup)
        <table style="margin-bottom:4px;">
            <tr>
                <th colspan="5" style="background:#eee;font-size:11pt;">
                    {{ $vendorGroup['vendor'] }}
                    <span style="font-weight:normal;font-size:9pt;">
                        &nbsp;— {{ $vendorGroup['orders'] }} order(s), Total {{ $currency }} {{ fmt_num($vendorGroup['total'], 2) }}
                    </span>
                </th>
            </tr>
        </table>
        <table style="margin-bottom:16px;">
            <thead>
            <tr>
                <th style="width:90px;">Date</th>
                <th>Product</th>
                <th style="width:90px;">SKU</th>
                <th style="width:60px;">UOM</th>
                <th class="num" style="width:80px;">Qty</th>
                <th class="num" style="width:100px;">Amount</th>
            </tr>
            </thead>
            <tbody>
            @foreach($vendorGroup['lines'] as $line)
                <tr>
                    <td>{{ $line['date'] ? \Carbon\Carbon::parse($line['date'])->format('d M Y') : '—' }}</td>
                    <td>{{ $line['product'] }}</td>
                    <td>{{ $line['sku'] }}</td>
                    <td>{{ $line['uom'] }}</td>
                    <td class="num">{{ fmt_num($line['qty'], 3) }}</td>
                    <td class="num">{{ $currency }} {{ fmt_num($line['total'], 2) }}</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
            <tr class="bold">
                <td colspan="4" class="num">Vendor Total</td>
                <td class="num">{{ fmt_num($vendorGroup['qty'], 3) }}</td>
                <td class="num">{{ $currency }} {{ fmt_num($vendorGroup['total'], 2) }}</td>
            </tr>
            </tfoot>
        </table>
    @empty
        <table><tr><td style="text-align:center;">No products</td></tr></table>
    @endforelse

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
        @if($byVendor->isNotEmpty())
        <tfoot>
        <tr class="bold">
            <td class="num">Total</td>
            <td class="num">{{ $orderCount }}</td>
            <td class="num">{{ $currency }} {{ fmt_num($totalAmount, 2) }}</td>
        </tr>
        </tfoot>
        @endif
    </table>

    @if(request()->boolean('print'))
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 200));</script>
    @endif
</body>
</html>
