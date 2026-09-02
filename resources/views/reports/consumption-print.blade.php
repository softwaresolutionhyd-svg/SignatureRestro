<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Consumption Report — {{ $printSectionLabel ?? 'Full Report' }} — {{ config('app.name') }}</title>
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
        /* Prevent Total row from repeating at the bottom of every printed page */
        tfoot { display: table-row-group; }
        tr.total-row td { font-weight: bold; }
        @media print { .noprint { display: none; } }
    </style>
</head>
<body>
@php
    $printSection = $printSection ?? 'all';
    $show = fn (string $key) => $printSection === 'all' || $printSection === $key;
@endphp
    <div class="noprint">
        <button type="button" onclick="window.print()">Print / PDF</button>
        <a href="{{ route('reports.consumption', request()->only(['from', 'to', 'department_id'])) }}">Back</a>
    </div>

    @if($rpLogo = company_logo_url(\App\Models\Setting::get('company_logo')))
        <div style="text-align:center;"><img src="{{ $rpLogo }}" alt="" style="max-height:70px;max-width:220px;margin-bottom:4px;"></div>
    @endif
    <h1>{{ \App\Models\Setting::get('company_name', config('app.name')) }}</h1>
    <h2 style="text-align:center;font-weight:normal;margin-top:0;">
        Consumption Report
        @if($printSection !== 'all')
            — {{ $printSectionLabel }}
        @endif
    </h2>

    <div class="meta">
        <p>Period: <strong>{{ \Carbon\Carbon::parse($from)->format('d M Y') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</strong>@if($selectedDepartment) &nbsp;|&nbsp; Department: <strong>{{ $selectedDepartment->name }}</strong>@endif</p>
        <p>Generated: {{ now()->format('d M Y, h:i A') }}</p>
    </div>

    @if($printSection === 'all')
    <table style="width:70%;">
        <tr><th>Sale Qty</th><td class="num">{{ fmt_num($totalSaleQty, 3) }}</td></tr>
        <tr><th>Sale Amount</th><td class="num">{{ $currency }} {{ fmt_num($totalSaleAmount, 2) }}</td></tr>
        <tr><th>Ingredients Used</th><td class="num">{{ $ingredientHit }}</td></tr>
        <tr><th>Ingredient Cost</th><td class="num">{{ $currency }} {{ fmt_num($totalIngredientAmount, 2) }}</td></tr>
        <tr><th>Stock Value (now)</th><td class="num">{{ $currency }} {{ fmt_num($totalStockAmount, 2) }}</td></tr>
    </table>
    @endif

    @if($show('by_day'))
    <h2>Sales by Day</h2>
    <table>
        <thead>
        <tr>
            <th>Date</th>
            <th class="num">Recipes</th>
            <th class="num">Qty</th>
            <th class="num">Sale</th>
        </tr>
        </thead>
        <tbody>
        @forelse($byDay as $row)
            <tr>
                <td>{{ $row['label'] }}</td>
                <td class="num">{{ $row['recipes'] }}</td>
                <td class="num">{{ fmt_num($row['qty'], 3) }}</td>
                <td class="num">{{ $currency }} {{ fmt_num($row['sale_amount'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="4" style="text-align:center;">No sales in this period</td></tr>
        @endforelse
        </tbody>
    </table>
    @endif

    @if($show('by_department'))
    <h2>Sales by Department</h2>
    @php $stockAmountByDept = $stockByDepartment->keyBy('name'); @endphp
    <table>
        <thead>
        <tr>
            <th>Department</th>
            <th class="num">Recipes</th>
            <th class="num">Qty</th>
            <th class="num">Sale</th>
            <th class="num">Stock Value</th>
        </tr>
        </thead>
        <tbody>
        @forelse($byDepartment as $row)
            <tr>
                <td>{{ $row['name'] }}</td>
                <td class="num">{{ $row['recipes'] }}</td>
                <td class="num">{{ fmt_num($row['qty'], 3) }}</td>
                <td class="num">{{ $currency }} {{ fmt_num($row['sale_amount'], 2) }}</td>
                <td class="num">{{ $currency }} {{ fmt_num((float) ($stockAmountByDept[$row['name']]['amount'] ?? 0), 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" style="text-align:center;">No department sales</td></tr>
        @endforelse
        </tbody>
    </table>
    @endif

    @if($show('ingredients'))
    <h2>Ingredients Consumption (Total)</h2>
    <table>
        <thead>
        <tr>
            <th>#</th>
            <th>Ingredient</th>
            <th class="num">Qty Used</th>
            <th>UOM</th>
            <th class="num">Cost Amount</th>
            <th>Departments</th>
        </tr>
        </thead>
        <tbody>
        @forelse($ingredientSummary as $i => $row)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $row['ingredient'] }}@if(($row['sku'] ?? '') !== '') <span style="color:#555;">({{ $row['sku'] }})</span>@endif</td>
                <td class="num">{{ fmt_num($row['qty'], 3) }}</td>
                <td>{{ $row['uom'] ?: '—' }}</td>
                <td class="num">{{ $currency }} {{ fmt_num($row['amount'], 2) }}</td>
                <td>{{ implode(', ', $row['departments'] ?? []) ?: '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align:center;">No ingredient consumption</td></tr>
        @endforelse
        @if($ingredientSummary->isNotEmpty())
            <tr class="total-row">
                <td colspan="2" class="num">Total</td>
                <td class="num">{{ fmt_num($totalIngredientQty, 3) }}</td>
                <td></td>
                <td class="num">{{ $currency }} {{ fmt_num($totalIngredientAmount, 2) }}</td>
                <td></td>
            </tr>
        @endif
        </tbody>
    </table>
    @endif

    @if($show('ingredients_day'))
    <h2>Ingredients by Day / Department</h2>
    <table>
        <thead>
        <tr>
            <th>Date</th>
            <th>Department</th>
            <th>Ingredient</th>
            <th class="num">Qty</th>
            <th>UOM</th>
            <th class="num">Cost</th>
        </tr>
        </thead>
        <tbody>
        @forelse($ingredientRows as $row)
            <tr>
                <td>{{ $row['date_label'] }}</td>
                <td>{{ $row['department'] }}</td>
                <td>{{ $row['ingredient'] }}</td>
                <td class="num">{{ fmt_num($row['qty'], 3) }}</td>
                <td>{{ $row['uom'] ?: '—' }}</td>
                <td class="num">{{ $currency }} {{ fmt_num($row['amount'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align:center;">No data</td></tr>
        @endforelse
        </tbody>
    </table>
    @endif

    @if($show('recipes'))
    <h2>Recipe-wise Sales (Period Total)</h2>
    <table>
        <thead>
        <tr>
            <th>#</th>
            <th>Department</th>
            <th>Recipe</th>
            <th class="num">Qty</th>
            <th>UOM</th>
            <th class="num">Sale Amount</th>
            <th class="num">Profit</th>
        </tr>
        </thead>
        <tbody>
        @forelse($recipeRows as $i => $row)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $row['department'] }}</td>
                <td>{{ $row['recipe'] }}@if($row['sku'] !== '') <span style="color:#555;">({{ $row['sku'] }})</span>@endif</td>
                <td class="num">{{ fmt_num($row['qty'], 3) }}</td>
                <td>{{ $row['uom'] ?: '—' }}</td>
                <td class="num">{{ $currency }} {{ fmt_num($row['sale_amount'], 2) }}</td>
                <td class="num" style="color: {{ ($row['profit'] ?? 0) < 0 ? '#b91c1c' : '#166534' }};">
                    {{ $currency }} {{ fmt_num($row['profit'] ?? 0, 2) }}
                </td>
            </tr>
        @empty
            <tr><td colspan="7" style="text-align:center;">No data</td></tr>
        @endforelse
        @if($recipeRows->isNotEmpty())
            <tr class="total-row">
                <td colspan="3" class="num">Total</td>
                <td class="num">{{ fmt_num($totalSaleQty, 3) }}</td>
                <td></td>
                <td class="num">{{ $currency }} {{ fmt_num($totalSaleAmount, 2) }}</td>
                <td class="num" style="color: {{ ($totalProfit ?? 0) < 0 ? '#b91c1c' : '#166534' }};">
                    {{ $currency }} {{ fmt_num($totalProfit ?? 0, 2) }}
                </td>
            </tr>
        @endif
        </tbody>
    </table>
    @endif

    @if($show('stock'))
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
        @if($stockRows->isNotEmpty())
            <tr class="total-row">
                <td colspan="5" class="num">Total Stock Amount</td>
                <td class="num">{{ $currency }} {{ fmt_num($totalStockAmount, 2) }}</td>
            </tr>
        @endif
        </tbody>
    </table>
    @endif

    <script>
        window.addEventListener('load', () => {
            if (new URLSearchParams(window.location.search).get('print') === '1') {
                window.print();
            }
        });
    </script>
</body>
</html>
