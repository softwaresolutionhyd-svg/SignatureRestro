@extends('layouts.admin')

@section('title', __('Demand') . ' - ' . config('app.name'))
@section('page_title', __('Demand'))

@section('content')
<div class="mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">{{ __('Demand') }}</h4>
        <div class="text-secondary small">
            @if($canCreate)
                {{ __('Ingredients demand create karein aur warehouse se department ko issue karein.') }}
            @else
                {{ __('Aaj ki demands dekhein aur warehouse stock se issue karein.') }}
            @endif
        </div>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if (session('warning'))
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        {{ session('warning') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<ul class="nav nav-tabs mb-4" role="tablist">
    @if($canCreate)
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'create' ? 'active' : '' }}"
               href="{{ route('demand.index', ['tab' => 'create']) }}">
                <i class="bi bi-plus-circle me-1"></i> {{ __('Create Demand') }}
            </a>
        </li>
    @endif
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'today' ? 'active' : '' }}"
           href="{{ route('demand.index', ['tab' => 'today']) }}">
            <i class="bi bi-calendar-day me-1"></i> {{ __("Today's Demand") }}
        </a>
    </li>
</ul>

@if($tab === 'create' && $canCreate)
    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">{{ __('Create Demand') }}</div>
        <div class="card-body">
            <form method="POST" action="{{ route('demand.store') }}" id="demandCreateForm">
                @csrf

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label">{{ __('Department') }} <span class="text-danger">*</span></label>
                        <select name="department_id" class="form-select" required>
                            <option value="">{{ __('Select department...') }}</option>
                            @foreach($departments as $dep)
                                <option value="{{ $dep->id }}" @selected((string) old('department_id') === (string) $dep->id)>
                                    {{ $dep->name }}
                                </option>
                            @endforeach
                        </select>
                        @if($departments->isEmpty())
                            <div class="form-text text-warning">
                                Pehle Inventory → Departments mein Kitchen / department banaein.
                            </div>
                        @endif
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">{{ __('Note') }}</label>
                        <input type="text" name="note" value="{{ old('note') }}" class="form-control" maxlength="255"
                               placeholder="Optional">
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="fw-semibold">{{ __('Ingredients') }}</div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addDemandLine">
                        <i class="bi bi-plus-lg me-1"></i> {{ __('Add item') }}
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle" id="demandLinesTable">
                        <thead>
                            <tr>
                                <th style="min-width:220px;">{{ __('Ingredient') }}</th>
                                <th style="width:120px;">{{ __('Qty') }}</th>
                                <th style="width:100px;">{{ __('UOM') }}</th>
                                <th style="width:120px;">{{ __('Warehouse') }}</th>
                                <th style="width:48px;"></th>
                            </tr>
                        </thead>
                        <tbody id="demandLinesBody"></tbody>
                    </table>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary" @disabled($departments->isEmpty() || $ingredients->isEmpty())>
                        <i class="bi bi-send me-1"></i> {{ __('Submit Demand') }}
                    </button>
                    <a href="{{ route('demand.index', ['tab' => 'today']) }}" class="btn btn-outline-secondary">
                        {{ __("Today's Demand") }}
                    </a>
                </div>
            </form>
        </div>
    </div>

    <template id="demandLineTemplate">
        <tr class="demand-line-row">
            <td>
                <input type="text"
                       class="form-control form-control-sm demand-product-search"
                       placeholder="{{ __('Type name / SKU...') }}"
                       autocomplete="off"
                       list="demandIngredientOptions"
                       required>
                <input type="hidden" name="lines[__INDEX__][product_id]" class="demand-product-id" value="" required>
            </td>
            <td>
                <input type="number" step="0.001" min="0.001" name="lines[__INDEX__][qty_uom]"
                       class="form-control form-control-sm" required value="1">
            </td>
            <td>
                <input type="text" name="lines[__INDEX__][uom]" class="form-control form-control-sm demand-uom" required maxlength="30" readonly>
            </td>
            <td>
                <span class="small text-secondary demand-wh">—</span>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-danger remove-demand-line" title="Remove">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
    </template>

    <datalist id="demandIngredientOptions"></datalist>
    <script type="application/json" id="demandIngredientsJson">@json($ingredients->map(fn ($p) => [
        'id' => (string) $p->id,
        'label' => trim(($p->sku ? $p->sku.' — ' : '').$p->name),
        'uom' => (string) $p->uom,
        'warehouse' => fmt_num((float) ($p->warehouse_qty ?? 0), 3),
    ])->values())</script>
@endif

@if($tab === 'today')
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="fw-semibold">{{ __("Today's Demand") }}</div>
            <span class="badge text-bg-secondary">{{ \Illuminate\Support\Carbon::parse($today)->format('d M Y') }} · {{ $todaysDemands->count() }}</span>
        </div>
        <div class="card-body p-0">
            @if($todaysDemands->isEmpty())
                <div class="p-4 text-secondary text-center">{{ __('Aaj ki koi demand nahi hai.') }}</div>
            @else
                @foreach($todaysDemands as $demand)
                    @php $deptName = $demand->department?->name ?? 'Department'; @endphp
                    <div class="border-bottom p-3">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                            <div>
                                <span class="fw-bold">{{ $demand->demand_no }}</span>
                                <span class="text-secondary mx-1">·</span>
                                <span>{{ $deptName }}</span>
                                @if($demand->creator)
                                    <span class="text-secondary small ms-1">by {{ $demand->creator->name }}</span>
                                @endif
                            </div>
                            <span class="badge
                                @if($demand->status === 'issued') text-bg-success
                                @elseif($demand->status === 'partial') text-bg-warning
                                @else text-bg-primary @endif">
                                {{ ucfirst($demand->status) }}
                            </span>
                        </div>
                        @if($demand->note)
                            <div class="small text-secondary mb-2">{{ $demand->note }}</div>
                        @endif
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('Item') }}</th>
                                        <th class="text-end">{{ __('Demand') }}</th>
                                        <th class="text-end">{{ __('Warehouse') }}</th>
                                        <th class="text-end">{{ __('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($demand->lines as $line)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $line->product?->name ?? '—' }}</div>
                                                <div class="small text-secondary">{{ $line->product?->sku }}</div>
                                            </td>
                                            <td class="text-end">
                                                {{ fmt_num((float) $line->qty_uom, 3) }} {{ $line->uom }}
                                                @if((float) $line->issued_qty_base > 0)
                                                    <div class="small text-success">
                                                        Issued: {{ fmt_num((float) $line->issued_qty_base, 3) }} {{ $line->product?->uom }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                {{ fmt_num((float) ($line->warehouse_qty ?? 0), 3) }} {{ $line->product?->uom }}
                                            </td>
                                            <td class="text-end">
                                                @if($line->isFullyIssued())
                                                    <span class="badge text-bg-success">{{ __('Issued') }}</span>
                                                @elseif($line->can_issue)
                                                    <form method="POST" action="{{ route('demand.lines.issue', $line) }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-success"
                                                                onclick="return confirm('Issue to {{ addslashes($deptName) }}?')">
                                                            <i class="bi bi-box-arrow-right me-1"></i>
                                                            {{ __('Issue To') }} {{ $deptName }}
                                                        </button>
                                                    </form>
                                                @elseif($line->out_of_stock)
                                                    <span class="badge text-bg-danger">{{ __('Out of Stock') }}</span>
                                                @else
                                                    <span class="badge text-bg-secondary">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
@endif
@endsection

@section('scripts')
@if($tab === 'create' && $canCreate)
<script>
(function () {
    var body = document.getElementById('demandLinesBody');
    var tpl = document.getElementById('demandLineTemplate');
    var addBtn = document.getElementById('addDemandLine');
    var form = document.getElementById('demandCreateForm');
    var datalist = document.getElementById('demandIngredientOptions');
    var jsonEl = document.getElementById('demandIngredientsJson');
    if (!body || !tpl || !addBtn || !datalist || !jsonEl) return;

    var ingredients = [];
    try {
        ingredients = JSON.parse(jsonEl.textContent || '[]');
    } catch (e) {
        ingredients = [];
    }

    ingredients = ingredients.map(function (item) {
        return {
            id: String(item.id || ''),
            label: String(item.label || '').trim(),
            normalized: String(item.label || '').toLowerCase(),
            uom: String(item.uom || ''),
            warehouse: String(item.warehouse || '0')
        };
    });

    var index = 0;

    function findExact(term) {
        var value = String(term || '').trim().toLowerCase();
        if (!value) return null;
        return ingredients.find(function (item) { return item.normalized === value; }) || null;
    }

    function findContains(term) {
        var value = String(term || '').trim().toLowerCase();
        if (!value) return null;
        return ingredients.find(function (item) { return item.normalized.indexOf(value) !== -1; }) || null;
    }

    function fillDatalist(term) {
        var query = String(term || '').trim().toLowerCase();
        var list = !query
            ? ingredients.slice(0, 60)
            : ingredients.filter(function (item) {
                return item.normalized.indexOf(query) !== -1;
            }).slice(0, 100);

        datalist.innerHTML = list.map(function (item) {
            return '<option value="' + item.label.replace(/"/g, '&quot;') + '"></option>';
        }).join('');
    }

    function setProduct(row, item) {
        var search = row.querySelector('.demand-product-search');
        var idInput = row.querySelector('.demand-product-id');
        var uom = row.querySelector('.demand-uom');
        var wh = row.querySelector('.demand-wh');

        if (!item) {
            idInput.value = '';
            uom.value = '';
            wh.textContent = '—';
            search.classList.remove('is-invalid');
            return;
        }

        search.value = item.label;
        idInput.value = item.id;
        uom.value = item.uom;
        wh.textContent = item.warehouse + ' ' + item.uom;
        search.classList.remove('is-invalid');
    }

    function bindRow(row) {
        var search = row.querySelector('.demand-product-search');
        var remove = row.querySelector('.remove-demand-line');

        search.addEventListener('focus', function () {
            fillDatalist(search.value);
        });

        search.addEventListener('input', function () {
            var exact = findExact(search.value);
            if (exact) {
                setProduct(row, exact);
            } else {
                setProduct(row, null);
            }
            fillDatalist(search.value);
        });

        search.addEventListener('blur', function () {
            var exact = findExact(search.value);
            var partial = findContains(search.value);
            if (exact) setProduct(row, exact);
            else if (partial) setProduct(row, partial);
            else if (!String(search.value || '').trim()) setProduct(row, null);
        });

        search.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            var exact = findExact(search.value);
            var partial = findContains(search.value);
            if (exact) setProduct(row, exact);
            else if (partial) setProduct(row, partial);
        });

        remove.addEventListener('click', function () {
            if (body.querySelectorAll('.demand-line-row').length <= 1) return;
            row.remove();
        });
    }

    function addRow() {
        var html = tpl.innerHTML.split('__INDEX__').join(String(index++));
        var wrap = document.createElement('tbody');
        wrap.innerHTML = html.trim();
        var row = wrap.firstElementChild;
        body.appendChild(row);
        bindRow(row);
    }

    form.addEventListener('submit', function (e) {
        var rows = body.querySelectorAll('.demand-line-row');
        var ok = true;
        rows.forEach(function (row) {
            var idInput = row.querySelector('.demand-product-id');
            var search = row.querySelector('.demand-product-search');
            if (!idInput.value) {
                ok = false;
                search.classList.add('is-invalid');
            }
        });
        if (!ok) {
            e.preventDefault();
            var firstBad = body.querySelector('.demand-product-search.is-invalid');
            if (firstBad) firstBad.focus();
        }
    });

    fillDatalist('');
    addBtn.addEventListener('click', addRow);
    addRow();
})();
</script>
@endif
@endsection
