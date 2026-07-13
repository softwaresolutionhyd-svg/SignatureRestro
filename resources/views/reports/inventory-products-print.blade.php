<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Inventory Products — {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { size: A4 portrait; margin: 14mm; }
        body { margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; font-size: 11pt; color: #000; background: #fff; }
        .noprint { margin-bottom: 12px; }
        .noprint button, .noprint a { margin-right: 6px; padding: 6px 12px; font-size: 12px; border: 1px solid #000; background: #fff; color: #000; cursor: pointer; text-decoration: none; }
        h1 { margin: 0 0 2px; font-size: 16pt; font-weight: bold; text-align: center; }
        h2 { margin: 0 0 14px; font-size: 11pt; font-weight: normal; text-align: center; }
        .meta { font-size: 10pt; margin-bottom: 14px; line-height: 1.6; }
        .meta p { margin: 0; }
        table { width: 100%; border-collapse: collapse; font-size: 10pt; }
        th, td { border: 1px solid #000; padding: 5px 7px; text-align: left; vertical-align: top; }
        th { font-weight: bold; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        @media print { .noprint { display: none; } }
    </style>
</head>
<body>
    <div class="noprint">
        <button type="button" onclick="window.print()">Print / PDF</button>
        <a href="{{ route('reports.inventory', request()->only(['filter', 'department_id'])) }}">Back</a>
    </div>

    <h1>{{ $companyName ?? config('app.name') }}</h1>
    <h2>Inventory Products</h2>

    <div class="meta">
        <p><strong>{{ $filterLabel }}</strong>@if($department) &nbsp;|&nbsp; Department: <strong>{{ $department->name }}</strong>@endif</p>
        <p>Date: {{ now()->format('d M Y, h:i A') }} &nbsp;|&nbsp; Items: {{ $products->count() }}</p>
    </div>

    <table>
        <thead>
        <tr>
            <th style="width:36px;">#</th>
            <th style="width:90px;">SKU</th>
            <th>Name</th>
            <th style="width:90px;">Unit</th>
            <th class="num" style="width:64px;">Qty</th>
            <th class="num" style="width:80px;">Cost</th>
            <th class="num" style="width:80px;">Price</th>
            <th style="width:60px;">Status</th>
        </tr>
        </thead>
        <tbody>
        @forelse($products as $i => $p)
            @php
                $qty = (float) $p->qty_on_hand;
                $status = $qty <= 0 ? 'Out' : ($qty <= 10 ? 'Low' : 'OK');
            @endphp
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $p->sku }}</td>
                <td>{{ $p->name }}</td>
                <td>{{ $p->uom ?: '—' }}</td>
                <td class="num">{{ fmt_num($qty, 2) }}</td>
                <td class="num">{{ $currency }} {{ fmt_num($p->cost, 2) }}</td>
                <td class="num">{{ $currency }} {{ fmt_num($p->price, 2) }}</td>
                <td>{{ $status }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="8" style="text-align:center;padding:16px;">No products found.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    @if(request()->boolean('print'))
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 200));</script>
    @endif
</body>
</html>
