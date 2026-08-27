<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Consumption Report — {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { size: A4 portrait; margin: 12mm; }
        body { margin: 0; padding: 0; font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #000; background: #fff; }
        .noprint { margin-bottom: 12px; }
        .noprint button, .noprint a { margin-right: 6px; padding: 6px 12px; font-size: 12px; border: 1px solid #000; background: #fff; color: #000; cursor: pointer; text-decoration: none; }
        h1 { margin: 0 0 2px; font-size: 15pt; font-weight: bold; text-align: center; }
        h2 { margin: 14px 0 6px; font-size: 11pt; font-weight: bold; }
        .meta { font-size: 9.5pt; margin-bottom: 12px; line-height: 1.5; }
        .meta p { margin: 0; }
        table { width: 100%; border-collapse: collapse; font-size: 9pt; margin-bottom: 10px; }
        th, td { border: 1px solid #000; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { font-weight: bold; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        tfoot td, tfoot th { font-weight: bold; }
        @media print { .noprint { display: none; } }
    </style>
</head>
<body>
    <div class="noprint">
        <button type="button" onclick="window.print()">Print / PDF</button>
        <a href="{{ route('reports.consumption', request()->only(['from', 'to', 'department_id'])) }}">Back</a>
    </div>

    @if($rpLogo = company_logo_url(\App\Models\Setting::get('company_logo')))
        <div style="text-align:center;"><img src="{{ $rpLogo }}" alt="" style="max-height:70px;max-width:220px;margin-bottom:4px;"></div>
    @endif
    <h1>{{ \App\Models\Setting::get('company_name', config('app.name')) }}</h1>
    <h2 style="text-align:center;font-weight:normal;margin-top:0;">Consumption Report</h2>

    <div class="meta">
        <p>Period: <strong>{{ \Carbon\Carbon::parse($from)->format('d M Y') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</strong>@if($selectedDepartment) &nbsp;|&nbsp; Department: <strong>{{ $selectedDepartment->name }}</strong>@endif</p>
        <p>Generated: {{ now()->format('d M Y, h:i A') }}</p>
    </div>

    <table style="width:70%;">
        <tr><th>Sale Qty</th><td class="num">{{ fmt_num($totalSaleQty, 3) }}</td></tr>
        <tr><th>Sale Amount</th><td class="num">{{ $currency }} {{ fmt_num($totalSaleAmount, 2) }}</td></tr>
        <tr><th>Recipes</th><td class="num">{{ $recipeHit }}</td></tr>
        <tr><th>Departments</th><td class="num">{{ $departmentHit }}</td></tr>
        <tr><th>Stock Value (now)</th><td class="num">{{ $currency }} {{ fmt_num($totalStockAmount, 2) }}</td></tr>
    </table>

    <h2>Recipe-wise Sales</h2>
    <table>
        <thead>
        <tr>
            <th>Date</th>
            <th>Department</th>
            <th>Recipe</th>
            <th class="num">Qty</th>
            <th>UOM</th>
            <th class="num">Sale Amount</th>
        </tr>
        </thead>
        <tbody>
        @forelse($recipeRows as $row)
            <tr>
                <td>{{ $row['date_label'] }}</td>
                <td>{{ $row['department'] }}</td>
                <td>{{ $row['recipe'] }}@if($row['sku'] !== '') <span style="color:#555;">({{ $row['sku'] }})</span>@endif</td>
                <td class="num">{{ fmt_num($row['qty'], 3) }}</td>
                <td>{{ $row['uom'] ?: '—' }}</td>
                <td class="num">{{ $currency }} {{ fmt_num($row['sale_amount'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align:center;">No data</td></tr>
        @endforelse
        </tbody>
        @if($recipeRows->isNotEmpty())
            <tfoot>
            <tr>
                <td colspan="3" class="num">Total</td>
                <td class="num">{{ fmt_num($totalSaleQty, 3) }}</td>
                <td></td>
                <td class="num">{{ $currency }} {{ fmt_num($totalSaleAmount, 2) }}</td>
            </tr>
            </tfoot>
        @endif
    </table>

    <h2>Remaining Stock (Department)</h2>
    <table>
        <thead>
        <tr>
            <th>Department</th>
            <th>Product</th>
            <th class="num">Qty</th>
            <th>UOM</th>
            <th class="num">Unit Cost</th>
            <th class="num">Amount</th>
        </tr>
        </thead>
        <tbody>
        @forelse($stockRows as $row)
            <tr>
                <td>{{ $row['department'] }}</td>
                <td>{{ $row['product'] }}@if($row['sku'] !== '') <span style="color:#555;">({{ $row['sku'] }})</span>@endif</td>
                <td class="num">{{ fmt_num($row['qty'], 3) }}</td>
                <td>{{ $row['uom'] ?: '—' }}</td>
                <td class="num">{{ fmt_num($row['unit_cost'], 4) }}</td>
                <td class="num">{{ $currency }} {{ fmt_num($row['amount'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align:center;">No stock</td></tr>
        @endforelse
        </tbody>
        @if($stockRows->isNotEmpty())
            <tfoot>
            <tr>
                <td colspan="5" class="num">Total Stock Amount</td>
                <td class="num">{{ $currency }} {{ fmt_num($totalStockAmount, 2) }}</td>
            </tr>
            </tfoot>
        @endif
    </table>

    <script>
        window.addEventListener('load', () => {
            if (new URLSearchParams(window.location.search).get('print') === '1') {
                window.print();
            }
        });
    </script>
</body>
</html>
