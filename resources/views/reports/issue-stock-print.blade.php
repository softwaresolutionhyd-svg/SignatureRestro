<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Issue Stock Report — {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 16px 20px 32px; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; font-size: 12px; color: #111; background: #fff; }
        h1 { margin: 0 0 4px; font-size: 20px; font-weight: 700; }
        .meta { color: #444; font-size: 12px; margin-bottom: 16px; }
        h2 { font-size: 14px; margin: 20px 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { border: 1px solid #333; padding: 5px 7px; vertical-align: top; }
        th { background: #f3f4f6; font-weight: 700; text-align: left; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        tfoot td { font-weight: 700; background: #f9fafb; }
        .kpi { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; }
        .kpi div { border: 1px solid #ccc; padding: 8px 12px; border-radius: 6px; min-width: 140px; }
        .kpi strong { display: block; font-size: 16px; margin-top: 2px; }
        .noprint { margin-bottom: 16px; display: flex; gap: 8px; flex-wrap: wrap; }
        .noprint button, .noprint a { padding: 8px 14px; font-size: 13px; border-radius: 6px; cursor: pointer; text-decoration: none; border: 1px solid #ccc; background: #fff; color: #111; }
        .noprint .primary { background: #dc3545; border-color: #dc3545; color: #fff; }
        @media print { body { padding: 0; } .noprint { display: none !important; } @page { size: A4 portrait; margin: 12mm; } }
    </style>
</head>
<body>
    <div class="noprint">
        <button type="button" class="primary" onclick="window.print()">Print</button>
        <a href="{{ route('reports.issue-stock', request()->only(['from', 'to', 'department_id'])) }}">← Back to Report</a>
    </div>

    <h1>Issue Stock Report</h1>
    <div class="meta">
        Period: <strong>{{ \Carbon\Carbon::parse($from)->format('d M Y') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</strong>
        @if($selectedDepartment)
            &nbsp;|&nbsp; Department: <strong>{{ $selectedDepartment->name }}</strong>
        @endif
        &nbsp;|&nbsp; Generated: {{ now()->format('d M Y, h:i A') }}
    </div>

    <div class="kpi">
        <div>Issue Lines<strong>{{ $issueCount }}</strong></div>
        <div>Total Qty<strong>{{ fmt_num($totalQty, 3) }}</strong></div>
        <div>Total Value<strong>{{ $currency }} {{ fmt_num($totalValue, 2) }}</strong></div>
        <div>Departments<strong>{{ $departmentHit }}</strong></div>
    </div>

    <div style="display:flex;gap:16px;flex-wrap:wrap;">
        <div style="flex:1;min-width:280px;">
            <h2>By Day</h2>
            <table>
                <thead><tr><th>Date</th><th class="num">Lines</th><th class="num">Qty</th><th class="num">Value</th></tr></thead>
                <tbody>
                @forelse($byDay as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td class="num">{{ $row['lines'] }}</td>
                        <td class="num">{{ fmt_num($row['qty'], 3) }}</td>
                        <td class="num">{{ $currency }} {{ fmt_num($row['value'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="text-align:center;">No data</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="flex:1;min-width:280px;">
            <h2>By Department</h2>
            <table>
                <thead><tr><th>Department</th><th class="num">Lines</th><th class="num">Qty</th><th class="num">Value</th></tr></thead>
                <tbody>
                @forelse($byDepartment as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td class="num">{{ $row['lines'] }}</td>
                        <td class="num">{{ fmt_num($row['qty'], 3) }}</td>
                        <td class="num">{{ $currency }} {{ fmt_num($row['value'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="text-align:center;">No data</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <h2>Issue Details</h2>
    <table>
        <thead>
        <tr>
            <th style="width:110px;">Date</th>
            <th>Product</th>
            <th class="num">Qty</th>
            <th>From</th>
            <th>To</th>
            <th>By</th>
            <th class="num">Value</th>
            <th>Note</th>
        </tr>
        </thead>
        <tbody>
        @forelse($issues as $issue)
            <tr>
                <td>{{ $issue->created_at?->format('d M Y H:i') }}</td>
                <td>
                    <strong>{{ $issue->product?->name ?? '—' }}</strong>
                    @if($issue->product?->sku)<br><span style="color:#555;">{{ $issue->product->sku }}</span>@endif
                </td>
                <td class="num">{{ fmt_num((float) $issue->qty_uom, 3) }} {{ $issue->uom }}</td>
                <td>{{ $issue->fromDepartment?->name ?? 'Warehouse' }}</td>
                <td>{{ $issue->toDepartment?->name ?? '—' }}</td>
                <td>{{ $issue->user?->name ?? '—' }}</td>
                <td class="num">{{ $currency }} {{ fmt_num((float) ($issue->line_value ?? 0), 2) }}</td>
                <td>{{ $issue->note ?: '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="8" style="text-align:center;padding:24px;">No issues in this period.</td></tr>
        @endforelse
        </tbody>
        @if($issues->isNotEmpty())
        <tfoot>
        <tr>
            <td colspan="6" class="num">Total</td>
            <td class="num">{{ $currency }} {{ fmt_num($totalValue, 2) }}</td>
            <td></td>
        </tr>
        </tfoot>
        @endif
    </table>

    @if(request()->boolean('print'))
    <script>window.addEventListener('load', () => window.print());</script>
    @endif
</body>
</html>
