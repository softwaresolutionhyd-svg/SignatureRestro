<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>All Recipes — {{ $companyName }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { size: A4 portrait; margin: 7mm 8mm; }
        body {
            margin: 0;
            padding: 10px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9px;
            line-height: 1.25;
            color: #111;
            background: #f8fafc;
        }
        .noprint { margin-bottom: 10px; text-align: center; }
        .noprint button, .noprint a {
            display: inline-block;
            padding: 6px 12px;
            margin: 0 4px;
            border: 1px solid #666;
            border-radius: 4px;
            background: #fff;
            color: #111;
            cursor: pointer;
            text-decoration: none;
            font-size: 12px;
        }
        .sheet {
            max-width: 210mm;
            margin: 0 auto;
            background: #fff;
            padding: 6mm 8mm;
            border: 1px solid #ddd;
        }
        .doc-head {
            text-align: center;
            margin-bottom: 8px;
            padding-bottom: 6px;
            border-bottom: 1px solid #333;
        }
        .doc-head h1 { margin: 0; font-size: 14px; line-height: 1.2; }
        .doc-head .meta { margin: 3px 0 0; font-size: 8px; color: #555; }
        .recipe-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5px;
            table-layout: fixed;
        }
        .recipe-table th,
        .recipe-table td {
            border: 1px solid #666;
            padding: 2px 4px;
            vertical-align: top;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .recipe-table thead th {
            background: #e5e7eb;
            font-weight: 700;
            font-size: 8px;
            padding: 3px 4px;
        }
        .col-dish { width: 22%; }
        .col-ing { width: 30%; }
        .col-qty { width: 16%; text-align: right; white-space: nowrap; }
        .col-rate { width: 16%; text-align: right; white-space: nowrap; }
        .col-amt { width: 16%; text-align: right; white-space: nowrap; }
        tr.dish-row td {
            background: #f3f4f6;
            font-weight: 700;
            font-size: 9px;
            padding: 3px 4px;
            border-top: 1.5px solid #333;
        }
        tr.dish-row:first-child td { border-top: 1px solid #666; }
        tr.total-row td {
            background: #fafafa;
            font-weight: 700;
            font-size: 8px;
            padding: 2px 4px 4px;
            border-bottom: 1.5px solid #999;
        }
        tr.total-row .summary-bar {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 10px 18px;
            text-align: right;
        }
        tr.total-row .summary-item .label {
            font-weight: 600;
            color: #555;
            margin-right: 4px;
        }
        tr.total-row .summary-item .value { font-weight: 700; }
        tr.total-row .summary-item.profit .value { color: #166534; }
        tr.total-row .summary-item.loss .value { color: #b91c1c; }
        tr.total-row .col-amt { font-size: 8.5px; }
        tr.ing-row td.col-dish { color: transparent; font-size: 0; border-top-color: #ddd; }
        tr.empty-row td { font-style: italic; color: #666; font-size: 8px; }
        @media print {
            body { padding: 0; background: #fff; font-size: 8.5px; }
            .noprint { display: none !important; }
            .sheet { max-width: none; border: none; padding: 0; }
            .recipe-table { font-size: 8px; }
            .recipe-table thead { display: table-header-group; }
            tr.dish-row { page-break-after: avoid; break-after: avoid-page; }
            tr.total-row { page-break-after: avoid; break-after: avoid-page; }
        }
    </style>
</head>
<body>
    <div class="noprint">
        <button type="button" onclick="window.print()">Print / PDF</button>
        <a href="{{ route('manufacturing.boms.index', request()->only(['q', 'finished_product', 'return'])) }}">Back to BoMs</a>
    </div>

    <div class="sheet">
        <header class="doc-head">
            <h1>{{ $companyName }} — All Recipes</h1>
            <p class="meta">
                {{ now()->timezone(config('app.timezone'))->format('d M Y, h:i A') }}
                · {{ $boms->count() }} recipes
                @if(($q ?? '') !== '')
                    · “{{ $q }}”
                @endif
            </p>
        </header>

        @if($boms->isEmpty())
            <p style="text-align:center;color:#666;padding:16px 0;">No recipes found.</p>
        @else
            <table class="recipe-table">
                <thead>
                    <tr>
                        <th class="col-dish">Dish</th>
                        <th class="col-ing">Ingredient</th>
                        <th class="col-qty">Qty</th>
                        <th class="col-rate">Rate</th>
                        <th class="col-amt">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($boms as $bom)
                        @php
                            $dishName = $bom->finishedProduct?->name ?? '—';
                            $materialPerBatch = (float) $bom->materialCostPerBatch();
                            $batchQty = (float) $bom->batch_qty;
                            $totalCost = $batchQty > 0 ? ($materialPerBatch / $batchQty) : $materialPerBatch;
                            $salePrice = (float) ($bom->finishedProduct?->price ?? 0);
                            $profit = $salePrice - $totalCost;
                            $finishedUom = (string) ($bom->finishedProduct?->uom ?? '');
                            $uomSuffix = $finishedUom !== '' ? '/'.$finishedUom : '';
                        @endphp

                        @if($bom->lines->isEmpty())
                            <tr class="dish-row empty-row">
                                <td class="col-dish">{{ $dishName }}</td>
                                <td colspan="4">No ingredients</td>
                            </tr>
                            <tr class="total-row">
                                <td class="col-dish"></td>
                                <td class="col-ing" colspan="4">
                                    <div class="summary-bar">
                                        <span class="summary-item">
                                            <span class="label">Total Cost:</span>
                                            <span class="value">{{ fmt_num($totalCost, 2) }}{{ $uomSuffix }}</span>
                                        </span>
                                        <span class="summary-item">
                                            <span class="label">Sale Price:</span>
                                            <span class="value">{{ fmt_num($salePrice, 2) }}</span>
                                        </span>
                                        <span class="summary-item {{ $profit >= 0 ? 'profit' : 'loss' }}">
                                            <span class="label">Profit:</span>
                                            <span class="value">{{ fmt_num($profit, 2) }}</span>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        @else
                            @foreach($bom->lines as $line)
                                @php
                                    $qty = (float) $line->qty;
                                    $uom = $line->effectiveUom();
                                    $lineAmount = (float) $line->lineMaterialCostPerBatch();
                                    $ratePerQtyUom = $qty > 0 ? ($lineAmount / $qty) : (float) ($line->component?->cost ?? 0);
                                @endphp
                                <tr @class(['dish-row' => $loop->first, 'ing-row' => ! $loop->first])>
                                    <td class="col-dish">{{ $loop->first ? $dishName : '·' }}</td>
                                    <td class="col-ing">{{ $line->component?->name ?? '—' }}</td>
                                    <td class="col-qty">{{ fmt_num($qty, 3) }} {{ $uom }}</td>
                                    <td class="col-rate">{{ fmt_num($ratePerQtyUom, 2) }}</td>
                                    <td class="col-amt">{{ fmt_num($lineAmount, 2) }}</td>
                                </tr>
                            @endforeach
                            <tr class="total-row">
                                <td class="col-dish"></td>
                                <td class="col-ing" colspan="4">
                                    <div class="summary-bar">
                                        <span class="summary-item">
                                            <span class="label">Total Cost:</span>
                                            <span class="value">{{ fmt_num($totalCost, 2) }}{{ $uomSuffix }}</span>
                                        </span>
                                        <span class="summary-item">
                                            <span class="label">Sale Price:</span>
                                            <span class="value">{{ fmt_num($salePrice, 2) }}</span>
                                        </span>
                                        <span class="summary-item {{ $profit >= 0 ? 'profit' : 'loss' }}">
                                            <span class="label">Profit:</span>
                                            <span class="value">{{ fmt_num($profit, 2) }}</span>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <script>
        window.addEventListener('load', () => {
            if (new URLSearchParams(window.location.search).get('auto') === '1') {
                window.print();
            }
        });
    </script>
</body>
</html>
