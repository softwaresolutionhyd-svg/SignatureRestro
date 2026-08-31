@extends('layouts.admin')
@section('title', 'Consumption Report — ' . config('app.name'))

@section('content')
@php
    $consumptionPrintBase = request()->only(['from', 'to', 'department_id']);
    $sectionPrintUrl = fn (string $section) => route('reports.consumption.print', array_merge($consumptionPrintBase, [
        'section' => $section,
        'print' => 1,
    ]));
@endphp
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-0">Consumption Report</h4>
        <div class="text-secondary small">Department-wise recipe sales (day wise) + remaining stock with amount</div>
    </div>
    <div class="d-flex gap-2 no-print">
        <a href="{{ route('reports.consumption.print', array_merge($consumptionPrintBase, ['print' => 1])) }}"
           target="_blank" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-printer me-1"></i> Print All
        </a>
        <a href="{{ route('reports.issue-stock') }}" class="btn btn-outline-primary btn-sm">Issue Stock</a>
        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm">← All Reports</a>
    </div>
</div>

<form method="GET" class="card shadow-sm border-0 mb-4 no-print">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-end gap-3">
            <div>
                <label class="form-label small fw-semibold mb-1">From</label>
                <input type="date" name="from" value="{{ $from }}" class="form-control">
            </div>
            <div>
                <label class="form-label small fw-semibold mb-1">To</label>
                <input type="date" name="to" value="{{ $to }}" class="form-control">
            </div>
            <div>
                <label class="form-label small fw-semibold mb-1">Department</label>
                <select name="department_id" class="form-select" style="min-width:180px;">
                    <option value="">All Departments</option>
                    @foreach($departments as $dep)
                        <option value="{{ $dep->id }}" @selected($departmentId === (int) $dep->id)>{{ $dep->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Apply</button>
            <a href="{{ route('reports.consumption') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-3">
            @php
                $presets = [
                    'This Month' => [now()->startOfMonth()->format('Y-m-d'), now()->format('Y-m-d')],
                    'Last Month' => [now()->subMonth()->startOfMonth()->format('Y-m-d'), now()->subMonth()->endOfMonth()->format('Y-m-d')],
                    'Last 7 Days' => [now()->subDays(6)->format('Y-m-d'), now()->format('Y-m-d')],
                    'Today' => [now()->format('Y-m-d'), now()->format('Y-m-d')],
                ];
            @endphp
            @foreach($presets as $label => [$pFrom, $pTo])
                <a href="{{ route('reports.consumption', array_filter(['from' => $pFrom, 'to' => $pTo, 'department_id' => $departmentId])) }}"
                   class="btn btn-sm btn-outline-secondary">{{ $label }}</a>
            @endforeach
        </div>
    </div>
</form>

<div class="alert alert-light border small mb-4">
    <strong>Period:</strong> {{ \Carbon\Carbon::parse($from)->format('d M Y') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }}
    @if($selectedDepartment)
        &nbsp;|&nbsp; <strong>Department:</strong> {{ $selectedDepartment->name }}
    @endif
    <div class="text-secondary mt-1">Sale = paid POS recipes. Ingredients = recipe/BoM se actual stock use. Stock = current on-hand. Har section ke Print se sirf wohi part print hota hai.</div>
</div>

<div class="row g-3 mb-4">
    @foreach([
        ['Sale Qty', fmt_num($totalSaleQty, 3), 'bi-basket', '#0d9488'],
        ['Sale Amount', $currency.' '.fmt_num($totalSaleAmount, 2), 'bi-currency-dollar', '#7c3aed'],
        ['Ingredients Used', $ingredientHit, 'bi-egg-fried', '#0ea5e9'],
        ['Ingredient Cost', $currency.' '.fmt_num($totalIngredientAmount, 2), 'bi-cash-stack', '#f97316'],
    ] as [$label,$val,$icon,$color])
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex gap-2 align-items-center mb-2">
                    <i class="bi {{ $icon }}" style="color:{{ $color }};font-size:1.2rem;"></i>
                    <span class="text-secondary small">{{ $label }}</span>
                </div>
                <div class="fw-bold fs-5">{{ $val }}</div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="fw-semibold">Sales by Day</span>
                <a href="{{ $sectionPrintUrl('by_day') }}" target="_blank" class="btn btn-outline-danger btn-sm no-print">
                    <i class="bi bi-printer me-1"></i> Print
                </a>
            </div>
            <div class="table-responsive" style="max-height:280px;overflow-y:auto;">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light sticky-top">
                    <tr>
                        <th>Date</th>
                        <th class="text-end">Recipes</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Sale</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($byDay as $row)
                        <tr>
                            <td class="small fw-semibold">{{ $row['label'] }}</td>
                            <td class="text-end small">{{ $row['recipes'] }}</td>
                            <td class="text-end small">{{ fmt_num($row['qty'], 3) }}</td>
                            <td class="text-end small">{{ $currency }} {{ fmt_num($row['sale_amount'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-4 text-secondary">No sales in this period</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="fw-semibold">Sales by Department</span>
                <a href="{{ $sectionPrintUrl('by_department') }}" target="_blank" class="btn btn-outline-danger btn-sm no-print">
                    <i class="bi bi-printer me-1"></i> Print
                </a>
            </div>
            <div class="table-responsive" style="max-height:280px;overflow-y:auto;">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light sticky-top">
                    <tr>
                        <th>Department</th>
                        <th class="text-end">Recipes</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Sale</th>
                        <th class="text-end">Stock Value</th>
                    </tr>
                    </thead>
                    <tbody>
                    @php
                        $stockAmountByDept = $stockByDepartment->keyBy('name');
                    @endphp
                    @forelse($byDepartment as $row)
                        <tr>
                            <td class="small fw-semibold">{{ $row['name'] }}</td>
                            <td class="text-end small">{{ $row['recipes'] }}</td>
                            <td class="text-end small">{{ fmt_num($row['qty'], 3) }}</td>
                            <td class="text-end small">{{ $currency }} {{ fmt_num($row['sale_amount'], 2) }}</td>
                            <td class="text-end small">
                                {{ $currency }} {{ fmt_num((float) ($stockAmountByDept[$row['name']]['amount'] ?? 0), 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center py-4 text-secondary">No department sales</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold">Ingredients Consumption (Total)</span>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="small text-secondary">
                Qty {{ fmt_num($totalIngredientQty, 3) }} · Cost {{ $currency }} {{ fmt_num($totalIngredientAmount, 2) }}
            </span>
            <a href="{{ $sectionPrintUrl('ingredients') }}" target="_blank" class="btn btn-outline-danger btn-sm no-print">
                <i class="bi bi-printer me-1"></i> Print
            </a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Ingredient</th>
                <th class="text-end">Qty Used</th>
                <th>UOM</th>
                <th class="text-end">Cost Amount</th>
                <th>Departments</th>
            </tr>
            </thead>
            <tbody>
            @forelse($ingredientSummary as $i => $row)
                <tr>
                    <td class="small text-secondary">{{ $i + 1 }}</td>
                    <td>
                        <div class="fw-semibold small">{{ $row['ingredient'] }}</div>
                        @if(($row['sku'] ?? '') !== '')
                            <div class="text-secondary" style="font-size:11px;">{{ $row['sku'] }}</div>
                        @endif
                    </td>
                    <td class="text-end small fw-semibold">{{ fmt_num($row['qty'], 3) }}</td>
                    <td class="small text-secondary">{{ $row['uom'] ?: '—' }}</td>
                    <td class="text-end small">{{ $currency }} {{ fmt_num($row['amount'], 2) }}</td>
                    <td class="small text-secondary">{{ implode(', ', $row['departments'] ?? []) ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center py-4 text-secondary">Is period mein BoM/recipe se koi ingredient consume nahi hua.</td></tr>
            @endforelse
            </tbody>
            @if($ingredientSummary->isNotEmpty())
                <tfoot class="table-light">
                <tr>
                    <th colspan="2" class="text-end">Total</th>
                    <th class="text-end">{{ fmt_num($totalIngredientQty, 3) }}</th>
                    <th></th>
                    <th class="text-end">{{ $currency }} {{ fmt_num($totalIngredientAmount, 2) }}</th>
                    <th></th>
                </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold">Ingredients by Day / Department</span>
        <a href="{{ $sectionPrintUrl('ingredients_day') }}" target="_blank" class="btn btn-outline-danger btn-sm no-print">
            <i class="bi bi-printer me-1"></i> Print
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
            <tr>
                <th>Date</th>
                <th>Department</th>
                <th>Ingredient</th>
                <th class="text-end">Qty Used</th>
                <th>UOM</th>
                <th class="text-end">Cost Amount</th>
            </tr>
            </thead>
            <tbody>
            @forelse($ingredientRows as $row)
                <tr>
                    <td class="small">{{ $row['date_label'] }}</td>
                    <td class="small fw-semibold">{{ $row['department'] }}</td>
                    <td>
                        <div class="fw-semibold small">{{ $row['ingredient'] }}</div>
                        @if(($row['sku'] ?? '') !== '')
                            <div class="text-secondary" style="font-size:11px;">{{ $row['sku'] }}</div>
                        @endif
                    </td>
                    <td class="text-end small">{{ fmt_num($row['qty'], 3) }}</td>
                    <td class="small text-secondary">{{ $row['uom'] ?: '—' }}</td>
                    <td class="text-end small">{{ $currency }} {{ fmt_num($row['amount'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center py-4 text-secondary">Day-wise ingredient data nahi mili.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold">Recipe-wise Consumption / Sales</span>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="small text-secondary">Period total · date filter ke hisaab se</span>
            <a href="{{ $sectionPrintUrl('recipes') }}" target="_blank" class="btn btn-outline-danger btn-sm no-print">
                <i class="bi bi-printer me-1"></i> Print
            </a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Department</th>
                <th>Recipe</th>
                <th class="text-end">Qty</th>
                <th>UOM</th>
                <th class="text-end">Sale Amount</th>
            </tr>
            </thead>
            <tbody>
            @forelse($recipeRows as $i => $row)
                <tr>
                    <td class="small text-secondary">{{ $i + 1 }}</td>
                    <td class="small fw-semibold">{{ $row['department'] }}</td>
                    <td>
                        <div class="fw-semibold small">{{ $row['recipe'] }}</div>
                        @if($row['sku'] !== '')
                            <div class="text-secondary" style="font-size:11px;">{{ $row['sku'] }}</div>
                        @endif
                    </td>
                    <td class="text-end small">{{ fmt_num($row['qty'], 3) }}</td>
                    <td class="small text-secondary">{{ $row['uom'] ?: '—' }}</td>
                    <td class="text-end small">{{ $currency }} {{ fmt_num($row['sale_amount'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center py-4 text-secondary">Is period mein koi department recipe sale nahi mili.</td></tr>
            @endforelse
            </tbody>
            @if($recipeRows->isNotEmpty())
                <tfoot class="table-light">
                <tr>
                    <th colspan="3" class="text-end">Total</th>
                    <th class="text-end">{{ fmt_num($totalSaleQty, 3) }}</th>
                    <th></th>
                    <th class="text-end">{{ $currency }} {{ fmt_num($totalSaleAmount, 2) }}</th>
                </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold">Remaining Stock (Department)</span>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="small text-secondary">Current on-hand · Total {{ $currency }} {{ fmt_num($totalStockAmount, 2) }}</span>
            <a href="{{ $sectionPrintUrl('stock') }}" target="_blank" class="btn btn-outline-danger btn-sm no-print">
                <i class="bi bi-printer me-1"></i> Print
            </a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
            <tr>
                <th>Department</th>
                <th>Product / Ingredient</th>
                <th class="text-end">Qty On Hand</th>
                <th>UOM</th>
                <th class="text-end">Unit Cost</th>
                <th class="text-end">Stock Amount</th>
            </tr>
            </thead>
            <tbody>
            @forelse($stockRows as $row)
                <tr>
                    <td class="small fw-semibold">{{ $row['department'] }}</td>
                    <td>
                        <div class="fw-semibold small">{{ $row['product'] }}</div>
                        @if($row['sku'] !== '')
                            <div class="text-secondary" style="font-size:11px;">{{ $row['sku'] }}</div>
                        @endif
                    </td>
                    <td class="text-end small {{ $row['qty'] < 0 ? 'text-danger' : '' }}">{{ fmt_num($row['qty'], 3) }}</td>
                    <td class="small text-secondary">{{ $row['uom'] ?: '—' }}</td>
                    <td class="text-end small">{{ fmt_num($row['unit_cost'], 4) }}</td>
                    <td class="text-end small fw-semibold">{{ $currency }} {{ fmt_num($row['amount'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center py-4 text-secondary">Department mein remaining stock nahi mila.</td></tr>
            @endforelse
            </tbody>
            @if($stockRows->isNotEmpty())
                <tfoot class="table-light">
                <tr>
                    <th colspan="5" class="text-end">Total Stock Amount</th>
                    <th class="text-end">{{ $currency }} {{ fmt_num($totalStockAmount, 2) }}</th>
                </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
