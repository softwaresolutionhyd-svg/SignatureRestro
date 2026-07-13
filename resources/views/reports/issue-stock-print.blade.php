<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Issue Stock Report — {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { size: A4 portrait; margin: 14mm; }
        body { margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; font-size: 11pt; color: #000; background: #fff; }
        .noprint { margin-bottom: 12px; }
        .noprint button, .noprint a { margin-right: 6px; padding: 6px 12px; font-size: 12px; border: 1px solid #000; background: #fff; color: #000; cursor: pointer; text-decoration: none; }
        h1 { margin: 0 0 2px; font-size: 16pt; font-weight: bold; text-align: center; }
        h2 { margin: 16px 0 6px; font-size: 11pt; font-weight: bold; }
        .meta { font-size: 10pt; margin-bottom: 14px; line-height: 1.6; }
        .meta p { margin: 0; }
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
        <a href="{{ route('reports.issue-stock', request()->only(['from', 'to', 'department_id'])) }}">Back</a>
    </div>

    @if($rpLogo = company_logo_url(\App\Models\Setting::get('company_logo')))
        <div style="text-align:center;"><img src="{{ $rpLogo }}" alt="" style="max-height:70px;max-width:220px;margin-bottom:4px;"></div>
    @endif
    <h1>{{ \App\Models\Setting::get('company_name', config('app.name')) }}</h1>
    <h2 style="text-align:center;font-weight:normal;margin-top:0;">Issue Stock Report</h2>

    <div class="meta">
        <p>Period: <strong>{{ \Carbon\Carbon::parse($from)->format('d M Y') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</strong>@if($selectedDepartment) &nbsp;|&nbsp; Department: <strong>{{ $selectedDepartment->name }}</strong>@endif</p>
        <p>Generated: {{ now()->format('d M Y, h:i A') }}</p>
    </div>

    <table style="width:60%;">
        <tr><th>Issue Lines</th><td class="num">{{ $issueCount }}</td></tr>
        <tr><th>Total Qty</th><td class="num">{{ fmt_num($totalQty, 3) }}</td></tr>
        <tr><th>Total Value</th><td class="num">{{ $currency }} {{ fmt_num($totalValue, 2) }}</td></tr>
        <tr><th>Departments</th><td class="num">{{ $departmentHit }}</td></tr>
    </table>

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
                    @if($issue->product?->sku)<br>{{ $issue->product->sku }}@endif
                </td>
                <td class="num">{{ fmt_num((float) $issue->qty_uom, 3) }} {{ $issue->uom }}</td>
                <td>{{ $issue->fromDepartment?->name ?? 'Warehouse' }}</td>
                <td>{{ $issue->toDepartment?->name ?? '—' }}</td>
                <td>{{ $issue->user?->name ?? '—' }}</td>
                <td class="num">{{ $currency }} {{ fmt_num((float) ($issue->line_value ?? 0), 2) }}</td>
                <td>{{ $issue->note ?: '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="8" style="text-align:center;padding:16px;">No issues in this period.</td></tr>
        @endforelse
        </tbody>
        @if($issues->isNotEmpty())
        <tfoot>
        <tr class="bold">
            <td colspan="6" class="num">Total</td>
            <td class="num">{{ $currency }} {{ fmt_num($totalValue, 2) }}</td>
            <td></td>
        </tr>
        </tfoot>
        @endif
    </table>

    @if(request()->boolean('print'))
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 200));</script>
    @endif
</body>
</html>
