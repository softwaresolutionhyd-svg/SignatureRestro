@php
    $vendor = $order->vendor;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $order->number }} — {{ $companyName }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { size: A4 portrait; margin: 12mm; }
        body {
            margin: 0;
            padding: 16px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            color: #111;
            background: #fff;
        }
        .noprint { margin-bottom: 14px; }
        .noprint button, .noprint a {
            margin-right: 6px;
            padding: 7px 14px;
            font-size: 12px;
            border: 1px solid #333;
            background: #fff;
            color: #111;
            cursor: pointer;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
        }
        .head {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            border-bottom: 2px solid #111;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .head img { max-height: 64px; max-width: 180px; }
        .head h1 { margin: 0; font-size: 18pt; }
        .head .sub { margin-top: 4px; font-size: 10pt; color: #333; }
        .meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 24px;
            margin-bottom: 16px;
            font-size: 10.5pt;
            line-height: 1.45;
        }
        .meta .lbl { color: #555; font-size: 9.5pt; }
        .meta .val { font-weight: 700; }
        table { width: 100%; border-collapse: collapse; font-size: 10pt; margin-top: 4px; }
        th, td { border: 1px solid #333; padding: 6px 8px; vertical-align: top; }
        th { background: #eee; text-align: left; font-size: 9.5pt; text-transform: uppercase; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        td.center, th.center { text-align: center; }
        .totals {
            width: 280px;
            margin-left: auto;
            margin-top: 12px;
            border-collapse: collapse;
            font-size: 10.5pt;
        }
        .totals th, .totals td { border: 1px solid #333; padding: 6px 8px; }
        .totals th { text-align: left; background: #eee; }
        .totals td { text-align: right; font-weight: 700; }
        .note { margin-top: 14px; font-size: 10pt; }
        .foot { margin-top: 28px; font-size: 9pt; color: #555; display: flex; justify-content: space-between; gap: 12px; }
        @media print {
            body { padding: 0; }
            .noprint { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="noprint">
        <button type="button" onclick="window.print()">Print / PDF</button>
        <a href="{{ route('purchase.orders.edit', $order) }}">Open</a>
        <a href="{{ route('purchase.orders.index') }}">Back</a>
    </div>

    <div class="head">
        <div>
            @if(!empty($companyLogo))
                <img src="{{ $companyLogo }}" alt="">
            @endif
            <h1>{{ $companyName }}</h1>
            <div class="sub">Purchase Order</div>
        </div>
        <div style="text-align:right;">
            <div style="font-size:16pt;font-weight:700;">{{ $order->number }}</div>
            <div class="sub">Status: {{ strtoupper($order->status) }}</div>
            <div class="sub">{{ strtoupper($order->purchase_type ?? 'debit') }} · {{ strtoupper($order->payment_status ?? 'paid') }}</div>
        </div>
    </div>

    <div class="meta">
        <div>
            <div class="lbl">Vendor</div>
            <div class="val">{{ $vendor?->name ?: '—' }}</div>
            @if($vendor?->phone)<div>{{ $vendor->phone }}</div>@endif
            @if($vendor?->email)<div>{{ $vendor->email }}</div>@endif
            @if($vendor?->address)<div>{{ $vendor->address }}</div>@endif
            @if($vendor?->tax_id)<div>Tax ID: {{ $vendor->tax_id }}</div>@endif
        </div>
        <div>
            <div class="lbl">Order date</div>
            <div class="val">{{ $order->order_date?->format('d M Y') ?? '—' }}</div>
            <div class="lbl" style="margin-top:8px;">Expected date</div>
            <div class="val">{{ $order->expected_date?->format('d M Y') ?? '—' }}</div>
            <div class="lbl" style="margin-top:8px;">Created by</div>
            <div class="val">{{ $order->creator?->name ?: '—' }}</div>
        </div>
    </div>

    <table>
        <thead>
        <tr>
            <th class="center" style="width:36px;">#</th>
            <th>Product</th>
            <th style="width:90px;">SKU</th>
            <th class="num" style="width:90px;">Qty</th>
            <th class="num" style="width:100px;">Unit price</th>
            <th class="num" style="width:70px;">Tax %</th>
            <th class="num" style="width:110px;">Total</th>
        </tr>
        </thead>
        <tbody>
        @forelse($order->lines as $i => $line)
            <tr>
                <td class="center">{{ $i + 1 }}</td>
                <td>
                    {{ $line->product?->name ?: ($line->description ?: '—') }}
                </td>
                <td>{{ $line->product?->sku ?: '—' }}</td>
                <td class="num">{{ fmt_num((float) $line->qty, 3) }} {{ $line->uom }}</td>
                <td class="num">{{ $currency }} {{ fmt_num((float) $line->unit_price, 2) }}</td>
                <td class="num">{{ fmt_num((float) $line->tax_percent, 3) }}</td>
                <td class="num">{{ $currency }} {{ fmt_num((float) $line->total, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="7" style="text-align:center;">No lines.</td></tr>
        @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <th>Subtotal</th>
            <td>{{ $currency }} {{ fmt_num((float) $order->subtotal, 2) }}</td>
        </tr>
        <tr>
            <th>Tax</th>
            <td>{{ $currency }} {{ fmt_num((float) $order->tax_total, 2) }}</td>
        </tr>
        <tr>
            <th>Grand total</th>
            <td>{{ $currency }} {{ fmt_num((float) $order->grand_total, 2) }}</td>
        </tr>
    </table>

    @if(trim((string) ($order->note ?? '')) !== '')
        <div class="note">
            <strong>Note:</strong> {{ $order->note }}
        </div>
    @endif

    <div class="foot">
        <div>Printed: {{ now()->timezone(config('app.timezone'))->format('d M Y, h:i A') }}</div>
        <div>Signature __________________</div>
    </div>
</body>
</html>
