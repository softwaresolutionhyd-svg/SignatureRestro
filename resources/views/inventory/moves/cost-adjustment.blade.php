@extends('layouts.admin')

@section('title', 'Stock Cost Adjustment - Inventory - ' . config('app.name'))
@section('page_title', 'Inventory / Stock Cost Adjustment')

@section('content')
    @include('inventory.partials.subnav')

    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $productRows = $products->map(function ($p) {
            return [
                'id' => (string) $p->id,
                'sku' => (string) $p->sku,
                'name' => (string) $p->name,
                'base_uom' => (string) $p->uom,
                'unit_cost_base' => round((float) $p->cost, 6),
                'uoms' => $p->uomsForForms(),
            ];
        })->values();
    @endphp

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div>
                <div class="fw-semibold">Stock Cost Adjustment</div>
                <div class="text-secondary small">Sab ingredients ki unit cost yahan se update karein. Sirf changed rows save hongi.</div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <input type="search" id="costSearch" class="form-control form-control-sm" style="min-width:220px;"
                       placeholder="Search SKU or name…" autocomplete="off">
                <button type="submit" form="costAdjustmentForm" class="btn btn-success btn-sm">
                    <i class="bi bi-check2-circle me-1"></i> Save costs
                </button>
                <a href="{{ route('inventory.moves.index') }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
            </div>
        </div>

        <form method="POST" action="{{ route('inventory.moves.cost-adjustment.update') }}" id="costAdjustmentForm">
            @csrf
            <div id="costLinesPayload"></div>

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th style="min-width:260px;">Product</th>
                        <th style="width:130px;">UOM</th>
                        <th style="width:160px;">Unit Cost</th>
                        <th style="width:140px;">Current</th>
                    </tr>
                    </thead>
                    <tbody id="costRowsBody"></tbody>
                </table>
            </div>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                <div class="text-secondary small" id="costRowsHint">{{ $products->count() }} ingredients</div>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check2-circle me-1"></i> Save costs
                </button>
            </div>
        </form>
    </div>

    <template id="costRowTpl">
        <tr class="cost-row" data-product-id="" data-search="">
            <td>
                <div class="fw-semibold cost-name"></div>
                <div class="text-secondary small cost-sku"></div>
            </td>
            <td>
                <select class="form-select form-select-sm cost-uom"></select>
            </td>
            <td>
                <input type="number" step="0.000001" min="0" class="form-control form-control-sm cost-input" placeholder="0">
            </td>
            <td>
                <div class="small text-secondary cost-current">—</div>
            </td>
        </tr>
    </template>

    @push('scripts')
    <script>
    (function () {
        const products = @json($productRows);
        const body = document.getElementById('costRowsBody');
        const tpl = document.getElementById('costRowTpl');
        const searchInput = document.getElementById('costSearch');
        const form = document.getElementById('costAdjustmentForm');
        const payload = document.getElementById('costLinesPayload');
        const hint = document.getElementById('costRowsHint');

        function formatCost(value) {
            if (!Number.isFinite(value)) return '0';
            let text = (Math.round(value * 1000000) / 1000000).toFixed(6);
            text = text.replace(/\.?0+$/, '');
            return text === '' ? '0' : text;
        }

        function getFactor(product, uom) {
            const meta = (product.uoms || []).find((u) => u.uom === uom);
            return meta ? Number(meta.factor || 0) : 0;
        }

        function costInUom(unitCostBase, factor) {
            return factor > 0 ? unitCostBase * factor : unitCostBase;
        }

        function toBase(unitCost, factor) {
            return factor > 0 ? unitCost / factor : unitCost;
        }

        function syncRow(row) {
            const product = products.find((p) => p.id === row.dataset.productId);
            if (!product) return;

            const uom = row.querySelector('.cost-uom').value;
            const factor = getFactor(product, uom) || 1;
            const current = costInUom(Number(product.unit_cost_base || 0), factor);
            row.querySelector('.cost-current').textContent = `${formatCost(current)} / ${uom || product.base_uom}`;

            const costInput = row.querySelector('.cost-input');
            const entered = Number(costInput.value || 0);
            const enteredBase = toBase(entered, factor);
            const dirty = Math.abs(enteredBase - Number(product.unit_cost_base || 0)) >= 0.0000001;
            row.classList.toggle('table-warning', dirty);
            row.dataset.dirty = dirty ? '1' : '0';
        }

        function fillRow(row, product) {
            row.dataset.productId = product.id;
            row.dataset.search = `${product.sku} ${product.name}`.toLowerCase();
            row.querySelector('.cost-name').textContent = product.name;
            row.querySelector('.cost-sku').textContent = product.sku;

            const uomSelect = row.querySelector('.cost-uom');
            const list = product.uoms || [];
            uomSelect.innerHTML = list.map((u) => `<option value="${u.uom}">${u.uom}</option>`).join('');
            if (list.length > 0) uomSelect.value = list[0].uom;

            const factor = getFactor(product, uomSelect.value) || 1;
            const costInput = row.querySelector('.cost-input');
            costInput.value = formatCost(costInUom(Number(product.unit_cost_base || 0), factor));

            uomSelect.addEventListener('change', () => {
                const nextFactor = getFactor(product, uomSelect.value) || 1;
                costInput.value = formatCost(costInUom(Number(product.unit_cost_base || 0), nextFactor));
                syncRow(row);
            });
            costInput.addEventListener('input', () => syncRow(row));
            syncRow(row);
        }

        function filterRows(term) {
            const query = (term || '').trim().toLowerCase();
            let visible = 0;
            body.querySelectorAll('.cost-row').forEach((row) => {
                const match = !query || (row.dataset.search || '').includes(query);
                row.classList.toggle('d-none', !match);
                if (match) visible++;
            });
            hint.textContent = `${visible} of ${products.length} ingredients`;
        }

        products.forEach((product) => {
            body.insertAdjacentHTML('beforeend', tpl.innerHTML);
            fillRow(body.lastElementChild, product);
        });
        filterRows('');

        searchInput?.addEventListener('input', () => filterRows(searchInput.value));

        form.addEventListener('submit', (e) => {
            payload.innerHTML = '';
            const changed = [];

            body.querySelectorAll('.cost-row').forEach((row) => {
                if (row.dataset.dirty !== '1') return;
                const productId = row.dataset.productId;
                const uom = row.querySelector('.cost-uom')?.value || '';
                const unitCost = row.querySelector('.cost-input')?.value ?? '';
                if (!productId || !uom || unitCost === '') return;
                changed.push({ product_id: productId, uom, unit_cost: unitCost });
            });

            if (changed.length === 0) {
                e.preventDefault();
                alert('Koi cost change nahi hui. Pehle unit cost update karein.');
                return;
            }

            changed.forEach((line, i) => {
                ['product_id', 'uom', 'unit_cost'].forEach((key) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `lines[${i}][${key}]`;
                    input.value = line[key];
                    payload.appendChild(input);
                });
            });
        });
    })();
    </script>
    @endpush
@endsection
