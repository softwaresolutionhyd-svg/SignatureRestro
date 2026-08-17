<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Issue Stock — {{ \Carbon\Carbon::parse($date)->format('d M Y') }} — {{ $companyName }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 16px 20px 32px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #111;
            background: #fff;
        }
        h1, h2 { margin: 0 0 6px; }
        .report-head { text-align: center; border-bottom: 2px solid #111; padding-bottom: 10px; margin-bottom: 16px; }
        .report-head h1 { font-size: 20px; }
        .report-head h2 { font-size: 14px; font-weight: 600; }
        .meta { color: #444; font-size: 11px; }
        .dept-block { margin-bottom: 18px; page-break-inside: avoid; }
        .dept-heading {
            background: #111;
            color: #fff;
            padding: 7px 10px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            margin: 0 0 0;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 5px 7px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-size: 11px; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        td.center, th.center { text-align: center; }
        .item-name { font-weight: 600; }
        .item-sku { font-size: 10px; color: #555; }
        tfoot td { font-weight: 700; background: #f9fafb; }
        .noprint { margin-bottom: 14px; display: flex; gap: 8px; flex-wrap: wrap; }
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
        .noprint .primary { background: #4f46e5; border-color: #4f46e5; color: #fff; }
        @media print {
            body { padding: 0; }
            .noprint { display: none !important; }
            @page { size: A4 portrait; margin: 12mm; }
            .dept-block { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="noprint">
        <button type="button" class="primary" onclick="window.print()">Print</button>
        <a href="{{ route('inventory.issues.index', ['date' => $date]) }}">← Back to Issue Stock</a>
    </div>

    <div class="report-head">
        @if($companyLogo)
            <div><img src="{{ $companyLogo }}" alt="" style="max-height:64px;max-width:200px;margin-bottom:4px;"></div>
        @endif
        <h1>{{ $companyName }}</h1>
        <h2>Issue Stock</h2>
        <div class="meta">
            Date: <strong>{{ \Carbon\Carbon::parse($date)->format('d M Y') }}</strong>
            &nbsp;|&nbsp;
            {{ $grouped->count() }} {{ $grouped->count() === 1 ? 'department' : 'departments' }}
            &nbsp;|&nbsp;
            {{ $totalLines }} {{ $totalLines === 1 ? 'item' : 'items' }}
            &nbsp;|&nbsp;
            Printed {{ now()->format('d M Y, h:i A') }}
        </div>
    </div>

    @forelse($grouped as $dept)
        <div class="dept-block">
            <div class="dept-heading">{{ $dept['name'] }} — {{ $dept['count'] }} {{ $dept['count'] === 1 ? 'item' : 'items' }}</div>
            <table>
                <thead>
                <tr>
                    <th class="center" style="width:36px;">#</th>
                    <th>Product</th>
                    <th class="num" style="width:90px;">Qty</th>
                    <th style="width:110px;">From</th>
                    <th style="width:70px;">Time</th>
                    <th>Note</th>
                </tr>
                </thead>
                <tbody>
                @foreach($dept['items'] as $i => $issue)
                    <tr>
                        <td class="center">{{ $i + 1 }}</td>
                        <td>
                            <div class="item-name">{{ $issue->product?->name ?? '—' }}</div>
                            @if($issue->product?->sku)
                                <div class="item-sku">{{ $issue->product->sku }}</div>
                            @endif
                        </td>
                        <td class="num">{{ fmt_num((float) $issue->qty_uom, 3) }} {{ $issue->uom }}</td>
                        <td>{{ $issue->fromDepartment?->name ?? 'Warehouse' }}</td>
                        <td>{{ $issue->created_at?->format('H:i') }}</td>
                        <td>{{ $issue->note ?: '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                <tr>
                    <td colspan="2" class="num">{{ $dept['name'] }} total</td>
                    <td class="num">{{ $dept['count'] }}</td>
                    <td colspan="3"></td>
                </tr>
                </tfoot>
            </table>
        </div>
    @empty
        <p style="text-align:center;padding:24px;color:#666;">Is date pe koi stock issue nahi hua.</p>
    @endforelse

    @if(request()->boolean('print'))
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 200));</script>
    @endif
</body>
</html>
