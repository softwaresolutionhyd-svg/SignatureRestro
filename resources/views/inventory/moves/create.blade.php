@extends('layouts.admin')

@section('title', 'Stock Adjustment - Inventory - ' . config('app.name'))
@section('page_title', 'Inventory / Stock Adjustment')

@section('content')
    @include('inventory.partials.subnav')

    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            {{ session('status') }}
        </div>
    @endif

    @php
        $defaultDepartmentId = old('department_id', $warehouse->id ?? null);
        $defaultMoveType = old('type', request()->query('type', 'adjust'));
        $oldLines = old('lines');
        if (! is_array($oldLines) || $oldLines === []) {
            $legacyProductId = old('product_id', request()->query('product_id'));
            $oldLines = [[
                'product_id' => $legacyProductId,
                'uom' => old('uom', request()->query('uom')),
                'qty_uom' => old('qty_uom'),
            ]];
        }
        $productCatalog = $products->map(fn ($p) => [
            'id' => (string) $p->id,
            'sku' => (string) $p->sku,
            'name' => (string) $p->name,
            'base_uom' => (string) $p->uom,
        ])->values();
        $productMap = $products->mapWithKeys(function ($p) {
            return [(string) $p->id => [
                'uoms' => $p->uomsForForms(),
                'unit_cost_base' => round((float) $p->cost, 6),
                'base_uom' => (string) $p->uom,
            ]];
        });
        $productStockUrlTemplate = preg_replace('/\/\d+$/', '/__ID__', route('inventory.moves.product-stock', ['product' => 0]));
        $updateCostUrlTemplate = preg_replace('/\/\d+$/', '/__ID__', route('inventory.moves.update-cost', ['product' => 0]));
    @endphp

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">Update stock</div>
        <div class="card-body">
            <form method="POST" action="{{ route('inventory.moves.store') }}" id="moveForm">
                @csrf

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label">Department</label>
                        <select id="departmentSelect" name="department_id" class="form-select @error('department_id') is-invalid @enderror" required>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}" @selected((string) $defaultDepartmentId === (string) $dept->id)>
                                    {{ $dept->name }}@if($dept->is_warehouse) (Warehouse) @endif
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Stock is adjusted in the selected department.</div>
                        @error('department_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-3">
                        <label class="form-label">Type</label>
                        <select id="moveTypeSelect" name="type" class="form-select @error('type') is-invalid @enderror" required>
                            <option value="in" @selected($defaultMoveType === 'in')>IN (Receive)</option>
                            <option value="out" @selected($defaultMoveType === 'out')>OUT (Deliver)</option>
                            <option value="adjust" @selected($defaultMoveType === 'adjust')>ADJUST (Set on hand)</option>
                            <option value="wastage" @selected($defaultMoveType === 'wastage')>WASTAGE (Damaged/Expired)</option>
                        </select>
                        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-2">
                        <label class="form-label">Reference</label>
                        <input type="text" name="reference" value="{{ old('reference') }}"
                               class="form-control @error('reference') is-invalid @enderror" maxlength="80">
                        @error('reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-3">
                        <label class="form-label" id="moveNoteLabel">Note</label>
                        <input type="text" id="moveNoteInput" name="note" value="{{ old('note') }}"
                               class="form-control @error('note') is-invalid @enderror" maxlength="255">
                        <div class="form-text d-none" id="wastageReasonHint">Wastage type par reason required hai.</div>
                        @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="fw-semibold">Products <span class="text-secondary small">(ingredients only)</span></div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addLineBtn">
                        <i class="bi bi-plus-lg me-1"></i> Add line
                    </button>
                </div>
                @error('lines')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

                <div class="table-responsive border rounded-3">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th style="min-width:240px;">Product</th>
                            <th style="width:150px;">UOM</th>
                            <th style="width:110px;">Qty</th>
                            <th style="width:130px;">On hand</th>
                            <th style="width:170px;">Unit Cost</th>
                            <th style="width:44px;"></th>
                        </tr>
                        </thead>
                        <tbody id="moveLinesBody"></tbody>
                    </table>
                </div>
                <div class="form-text mt-1" id="qtyHelpText">For ADJUST, quantity becomes the new on-hand in the selected department.</div>

                <div class="d-flex gap-2 mt-4">
                    <button class="btn btn-success" type="submit">Apply</button>
                    <a href="{{ route('inventory.moves.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>

            <form method="POST" action="" id="updateCostForm" class="d-none">
                @csrf
                <input type="hidden" name="unit_cost" id="updateCostValue">
                <input type="hidden" name="uom" id="updateCostUom">
            </form>
        </div>
    </div>

    <datalist id="productSearchOptions"></datalist>

    <template id="moveLineRowTpl">
        <tr class="move-line-row">
            <td>
                <input type="text" class="form-control form-control-sm line-product-search" placeholder="SKU or name..." autocomplete="off" list="productSearchOptions">
                <input type="hidden" class="line-product-id" name="lines[__i__][product_id]" required>
            </td>
            <td>
                <select class="form-select form-select-sm line-uom" name="lines[__i__][uom]" required>
                    <option value="">UOM...</option>
                </select>
            </td>
            <td>
                <input type="number" step="0.001" min="0" class="form-control form-control-sm line-qty" name="lines[__i__][qty_uom]" required>
            </td>
            <td>
                <div class="small text-secondary line-stock-hint">—</div>
            </td>
            <td>
                <div class="input-group input-group-sm">
                    <input type="number" step="0.000001" min="0" class="form-control line-unit-cost" placeholder="0">
                    <button type="button" class="btn btn-outline-primary line-update-cost" title="Update cost">Cost</button>
                </div>
                <div class="small text-secondary line-cost-hint mt-1">—</div>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 line-remove" title="Remove line">&times;</button>
            </td>
        </tr>
    </template>

    @push('scripts')
    <script>
    (function () {
        const productCatalog = @json($productCatalog);
        const departmentStockMap = @json($departmentStockMap ?? []);
        const productMap = @json($productMap);
        const productStockUrlTemplate = @json($productStockUrlTemplate);
        const updateCostUrlTemplate = @json($updateCostUrlTemplate);
        const initialLines = @json(array_values($oldLines));

        const linesBody = document.getElementById('moveLinesBody');
        const lineTpl = document.getElementById('moveLineRowTpl');
        const addLineBtn = document.getElementById('addLineBtn');
        const departmentSelect = document.getElementById('departmentSelect');
        const moveTypeSelect = document.getElementById('moveTypeSelect');
        const moveNoteInput = document.getElementById('moveNoteInput');
        const moveNoteLabel = document.getElementById('moveNoteLabel');
        const wastageReasonHint = document.getElementById('wastageReasonHint');
        const qtyHelpText = document.getElementById('qtyHelpText');
        const productSearchOptions = document.getElementById('productSearchOptions');
        const updateCostForm = document.getElementById('updateCostForm');
        const updateCostValue = document.getElementById('updateCostValue');
        const updateCostUom = document.getElementById('updateCostUom');

        let lineIdx = 0;
        let productOptions = [];

        function formatQty(qty) {
            return Number(qty || 0).toLocaleString(undefined, {
                minimumFractionDigits: 0,
                maximumFractionDigits: 3,
            });
        }

        function formatCost(value) {
            if (!Number.isFinite(value)) return '0';
            let text = (Math.round(value * 1000000) / 1000000).toFixed(6);
            text = text.replace(/\.?0+$/, '');
            return text === '' ? '0' : text;
        }

        function getSelectedDepartmentId() {
            return departmentSelect?.value ? String(departmentSelect.value) : '';
        }

        function departmentQty(productId) {
            const deptId = getSelectedDepartmentId();
            if (!deptId || !productId) return 0;
            return Number(departmentStockMap[deptId]?.[String(productId)] ?? 0);
        }

        function buildProductLabel(item) {
            const qty = departmentQty(item.id);
            return `${item.sku} — ${item.name} (On hand: ${formatQty(qty)} ${item.base_uom})`;
        }

        function rebuildProductOptions() {
            productOptions = productCatalog.map((item) => {
                const label = buildProductLabel(item);
                return {
                    id: String(item.id),
                    label,
                    normalized: label.toLowerCase(),
                    searchKey: `${item.sku} ${item.name}`.toLowerCase(),
                };
            });
        }

        function buildProductSearchOptions(term) {
            const query = (term || '').trim().toLowerCase();
            const list = !query
                ? productOptions.slice(0, 80)
                : productOptions.filter((opt) => opt.searchKey.includes(query)).slice(0, 100);
            productSearchOptions.innerHTML = list.map((opt) => `<option value="${opt.label}"></option>`).join('');
        }

        function findProductByLabel(label) {
            const value = (label || '').trim().toLowerCase();
            return value ? (productOptions.find((opt) => opt.normalized === value) ?? null) : null;
        }

        function findProductByContains(term) {
            const value = (term || '').trim().toLowerCase();
            return value ? (productOptions.find((opt) => opt.searchKey.includes(value)) ?? null) : null;
        }

        function getUomFactor(productId, uom) {
            const product = productMap[productId];
            if (!product || !uom) return null;
            const row = (product.uoms || []).find((item) => item.uom === uom);
            return row ? Number(row.factor || 0) : null;
        }

        function costInSelectedUom(productId, uom, unitCostBase) {
            const factor = getUomFactor(productId, uom);
            if (factor === null || factor <= 0) return unitCostBase;
            return unitCostBase * factor;
        }

        function refreshRowUomOptions(row, preferUom = null) {
            const productId = row.querySelector('.line-product-id')?.value || '';
            const uomSelect = row.querySelector('.line-uom');
            const product = productMap[productId];
            const list = product?.uoms ?? [];
            const deptQtyBase = departmentQty(productId);
            const currentUom = preferUom || uomSelect.value;

            uomSelect.innerHTML = '<option value="">UOM...</option>' + list.map((u) => {
                const stockInThisUom = u.factor > 0 ? (deptQtyBase / u.factor) : 0;
                const label = `${u.uom} (${formatQty(stockInThisUom)})`;
                const selected = currentUom === u.uom ? 'selected' : '';
                return `<option value="${u.uom}" ${selected}>${label}</option>`;
            }).join('');

            if (currentUom && list.some((u) => u.uom === currentUom)) {
                uomSelect.value = currentUom;
            } else if (list.length > 0) {
                uomSelect.value = list[0].uom;
            }
        }

        function syncRowStockHint(row) {
            const productId = row.querySelector('.line-product-id')?.value || '';
            const hint = row.querySelector('.line-stock-hint');
            const product = productMap[productId];
            if (!product || !hint) {
                if (hint) hint.textContent = '—';
                return;
            }
            const qty = departmentQty(productId);
            hint.textContent = `${formatQty(qty)} ${product.base_uom}`;
            hint.className = 'small ' + (qty > 0 ? 'text-primary fw-semibold' : 'text-secondary') + ' line-stock-hint';
        }

        function syncRowCostFields(row, preferValue = null) {
            const productId = row.querySelector('.line-product-id')?.value || '';
            const uom = row.querySelector('.line-uom')?.value || '';
            const costInput = row.querySelector('.line-unit-cost');
            const costHint = row.querySelector('.line-cost-hint');
            const product = productMap[productId];
            const unitCostBase = row._unitCostBase ?? product?.unit_cost_base ?? 0;

            if (!productId || !product) {
                if (costHint) costHint.textContent = '—';
                return;
            }

            const currentInUom = costInSelectedUom(productId, uom, unitCostBase);
            if (costHint) {
                costHint.textContent = uom
                    ? `Current: ${formatCost(currentInUom)}/${uom}`
                    : `Current: ${formatCost(unitCostBase)}/${product.base_uom}`;
            }
            if (preferValue !== null) {
                costInput.value = formatCost(preferValue);
            } else if (!costInput.value && currentInUom > 0) {
                costInput.value = formatCost(currentInUom);
            }
        }

        async function patchDepartmentStock(productId) {
            if (!productId) return;
            try {
                const url = productStockUrlTemplate.replace('__ID__', encodeURIComponent(productId));
                const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!response.ok) return;
                const data = await response.json();
                (data.departments || []).forEach((row) => {
                    const deptKey = String(row.id);
                    if (!departmentStockMap[deptKey]) departmentStockMap[deptKey] = {};
                    departmentStockMap[deptKey][String(productId)] = Number(row.qty || 0);
                });
                if (data.unit_cost_base != null) {
                    productMap[String(productId)] = productMap[String(productId)] || {};
                    productMap[String(productId)].unit_cost_base = Number(data.unit_cost_base);
                }
            } catch (e) {}
        }

        function setRowProduct(row, option) {
            const searchInput = row.querySelector('.line-product-search');
            const idInput = row.querySelector('.line-product-id');

            if (!option) {
                idInput.value = '';
                searchInput.value = '';
                row._unitCostBase = null;
                refreshRowUomOptions(row);
                syncRowStockHint(row);
                syncRowCostFields(row);
                return;
            }

            idInput.value = option.id;
            searchInput.value = option.label;
            row._unitCostBase = productMap[option.id]?.unit_cost_base ?? 0;

            patchDepartmentStock(option.id).then(() => {
                rebuildProductOptions();
                buildProductSearchOptions(searchInput.value);
                refreshRowUomOptions(row);
                syncRowStockHint(row);
                syncRowCostFields(row);
            });
        }

        function clearRowProductSelection(row) {
            row.querySelector('.line-product-id').value = '';
            row._unitCostBase = null;
            row.querySelector('.line-uom').innerHTML = '<option value="">UOM...</option>';
            row.querySelector('.line-unit-cost').value = '';
            syncRowStockHint(row);
            row.querySelector('.line-cost-hint').textContent = '—';
        }

        function resolveProductFromSearchInput(row, { allowPartial = false } = {}) {
            const searchInput = row.querySelector('.line-product-search');
            const value = searchInput.value.trim();
            const exact = findProductByLabel(searchInput.value);

            if (exact) {
                setRowProduct(row, exact);
                return;
            }

            if (allowPartial) {
                const contains = findProductByContains(searchInput.value);
                if (contains) {
                    setRowProduct(row, contains);
                    return;
                }
            }

            if (!value) {
                setRowProduct(row, null);
            } else {
                clearRowProductSelection(row);
            }
        }

        function bindLineRow(row) {
            const searchInput = row.querySelector('.line-product-search');
            const uomSelect = row.querySelector('.line-uom');
            const removeBtn = row.querySelector('.line-remove');
            const updateCostBtn = row.querySelector('.line-update-cost');

            searchInput.addEventListener('focus', () => buildProductSearchOptions(searchInput.value));
            searchInput.addEventListener('input', () => {
                resolveProductFromSearchInput(row, { allowPartial: false });
                buildProductSearchOptions(searchInput.value);
            });
            searchInput.addEventListener('blur', () => resolveProductFromSearchInput(row, { allowPartial: true }));
            searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    resolveProductFromSearchInput(row, { allowPartial: true });
                }
            });

            uomSelect.addEventListener('change', () => syncRowCostFields(row));

            removeBtn.addEventListener('click', () => {
                if (linesBody.querySelectorAll('.move-line-row').length <= 1) return;
                row.remove();
            });

            updateCostBtn.addEventListener('click', () => {
                const productId = row.querySelector('.line-product-id')?.value || '';
                const uom = row.querySelector('.line-uom')?.value || '';
                const costValue = row.querySelector('.line-unit-cost')?.value ?? '';
                if (!productId) { alert('Pehle product select karein.'); return; }
                if (!uom) { alert('Pehle UOM select karein.'); return; }
                if (costValue === '' || Number(costValue) < 0) { alert('Valid unit cost likhein.'); return; }
                updateCostForm.action = updateCostUrlTemplate.replace('__ID__', encodeURIComponent(productId));
                updateCostValue.value = costValue;
                updateCostUom.value = uom;
                updateCostForm.submit();
            });
        }

        function addLine(data) {
            const html = lineTpl.innerHTML.replaceAll('__i__', String(lineIdx++));
            linesBody.insertAdjacentHTML('beforeend', html);
            const row = linesBody.lastElementChild;
            bindLineRow(row);

            if (data?.qty_uom != null && data.qty_uom !== '') {
                row.querySelector('.line-qty').value = data.qty_uom;
            }

            if (data?.product_id) {
                const option = productOptions.find((opt) => opt.id === String(data.product_id));
                if (option) {
                    setRowProduct(row, option);
                    if (data.uom) {
                        refreshRowUomOptions(row, data.uom);
                        syncRowCostFields(row);
                    }
                } else {
                    row.querySelector('.line-product-id').value = String(data.product_id);
                    row._unitCostBase = productMap[String(data.product_id)]?.unit_cost_base ?? 0;
                    patchDepartmentStock(String(data.product_id)).then(() => {
                        refreshRowUomOptions(row, data.uom || null);
                        syncRowStockHint(row);
                        syncRowCostFields(row);
                    });
                }
            }

            return row;
        }

        function refreshAllRows() {
            rebuildProductOptions();
            buildProductSearchOptions('');
            linesBody.querySelectorAll('.move-line-row').forEach((row) => {
                const productId = row.querySelector('.line-product-id')?.value || '';
                if (productId) {
                    const option = productOptions.find((opt) => opt.id === String(productId));
                    if (option) {
                        row.querySelector('.line-product-search').value = option.label;
                    }
                }
                refreshRowUomOptions(row);
                syncRowStockHint(row);
                syncRowCostFields(row);
            });
        }

        function syncQtyHelpText() {
            if (!qtyHelpText || !moveTypeSelect) return;
            const isAdjust = moveTypeSelect.value === 'adjust';
            qtyHelpText.textContent = isAdjust
                ? 'For ADJUST, quantity becomes the new on-hand in the selected department.'
                : 'Enter quantity in the selected UOM for each line.';
            linesBody.querySelectorAll('.line-qty').forEach((input) => {
                input.min = isAdjust ? '0' : '0.001';
            });
        }

        function syncWastageReasonRequirement() {
            if (!moveTypeSelect || !moveNoteInput || !moveNoteLabel) return;
            const isWastage = moveTypeSelect.value === 'wastage';
            moveNoteInput.required = isWastage;
            moveNoteLabel.textContent = isWastage ? 'Reason' : 'Note';
            wastageReasonHint?.classList.toggle('d-none', !isWastage);
            syncQtyHelpText();
        }

        addLineBtn?.addEventListener('click', () => addLine({}));
        departmentSelect?.addEventListener('change', refreshAllRows);
        moveTypeSelect?.addEventListener('change', syncWastageReasonRequirement);

        rebuildProductOptions();
        if (initialLines.length > 0) {
            initialLines.forEach((line) => addLine(line));
        } else {
            addLine({});
        }
        syncWastageReasonRequirement();
    })();
    </script>
    @endpush
@endsection
