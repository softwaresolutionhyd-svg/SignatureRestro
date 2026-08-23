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

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">Update stock</div>
        <div class="card-body">
            <form method="POST" action="{{ route('inventory.moves.store') }}">
                @csrf

                <div class="row g-3">
                    <div class="col-12 col-lg-5">
                        <label class="form-label">Product <span class="text-secondary small">(ingredients only)</span></label>
                        <input
                            type="text"
                            id="productSearchInput"
                            class="form-control @error('product_id') is-invalid @enderror"
                            placeholder="Type SKU or product name..."
                            autocomplete="off"
                            list="productSearchOptions"
                        >
                        <datalist id="productSearchOptions"></datalist>
                        <input type="hidden" id="productIdInput" name="product_id" value="{{ old('product_id', request()->query('product_id')) }}" required>
                        <select id="productSelect" class="d-none">
                            <option value="">Select product...</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}" @selected((string)old('product_id') === (string)$p->id)>
                                    {{ $p->sku }} — {{ $p->name }} (On hand: {{ fmt_num((float)$p->qty_on_hand, 3) }} {{ $p->uom }})
                                </option>
                            @endforeach
                        </select>
                        @error('product_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-lg-3">
                        <label class="form-label">Department</label>
                        @php
                            $defaultDepartmentId = old('department_id', $warehouse->id ?? null);
                        @endphp
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

                    <div class="col-12 col-lg-2">
                        <label class="form-label">Type</label>
                        @php $defaultMoveType = old('type', request()->query('type')); @endphp
                        <select id="moveTypeSelect" name="type" class="form-select @error('type') is-invalid @enderror" required>
                            <option value="in" @selected($defaultMoveType === 'in')>IN (Receive)</option>
                            <option value="out" @selected($defaultMoveType === 'out')>OUT (Deliver)</option>
                            <option value="adjust" @selected($defaultMoveType === 'adjust' || !$defaultMoveType)>ADJUST (Set on hand)</option>
                            <option value="wastage" @selected($defaultMoveType === 'wastage')>WASTAGE (Damaged/Expired)</option>
                        </select>
                        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-lg-2">
                        <label class="form-label">Quantity</label>
                        <input type="number" step="0.001" min="0" name="qty_uom" value="{{ old('qty_uom') }}"
                               class="form-control @error('qty_uom') is-invalid @enderror" required>
                        @error('qty_uom')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text" id="qtyHelpText">For ADJUST, this becomes the new on-hand quantity in the selected department.</div>
                    </div>

                    <div class="col-12 col-lg-4">
                        <label class="form-label">UOM</label>
                        <select id="uomSelect" name="uom" class="form-select @error('uom') is-invalid @enderror" required>
                            <option value="">Select UOM...</option>
                        </select>
                        <div class="form-text" id="uomStockHint">Select product/UOM to view available stock.</div>
                        @error('uom')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-lg-4">
                        <label class="form-label">Reference</label>
                        <input type="text" name="reference" value="{{ old('reference') }}"
                               class="form-control @error('reference') is-invalid @enderror" maxlength="80">
                        @error('reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-lg-4">
                        <label class="form-label">Unit Cost <span class="text-secondary small">(per selected UOM)</span></label>
                        <div class="input-group">
                            <input type="number" step="0.000001" min="0" id="unitCostInput"
                                   class="form-control @error('unit_cost') is-invalid @enderror"
                                   value="{{ old('unit_cost') }}" placeholder="0">
                            <button type="button" class="btn btn-outline-primary" id="updateCostBtn">Update Cost</button>
                        </div>
                        <div class="form-text" id="currentCostHint">Select product to view current cost.</div>
                        @error('unit_cost')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-lg-4">
                        <label class="form-label" id="moveNoteLabel">Note</label>
                        <input type="text" id="moveNoteInput" name="note" value="{{ old('note') }}"
                               class="form-control @error('note') is-invalid @enderror" maxlength="255">
                        <div class="form-text d-none" id="wastageReasonHint">Wastage type par reason required hai.</div>
                        @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 d-none" id="departmentStockPanel">
                        <div class="border rounded bg-light p-3">
                            <div class="fw-semibold small text-secondary mb-2">Stock by department</div>
                            <div id="departmentStockList" class="d-flex flex-wrap gap-2"></div>
                        </div>
                    </div>
                </div>

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

    @php
        $productMap = $products->mapWithKeys(function ($p) {
            return [(string) $p->id => [
                'uoms' => $p->uomsForForms(),
                'qty_on_hand' => (float) $p->qty_on_hand,
                'unit_cost_base' => round((float) $p->cost, 6),
                'base_uom' => (string) $p->uom,
                'inner_qty_on_hand' => $p->qtyOnHandAsPackageContents(),
                'inner_uom' => $p->hasPackageContents() ? (string) $p->package_contents_uom : null,
            ]];
        });
        $productStockUrlTemplate = preg_replace('/\/\d+$/', '/__ID__', route('inventory.moves.product-stock', ['product' => 0]));
        $updateCostUrlTemplate = preg_replace('/\/\d+$/', '/__ID__', route('inventory.moves.update-cost', ['product' => 0]));
    @endphp

    <script>
        const productMap = @json($productMap);

        const productSearchInput = document.getElementById('productSearchInput');
        const productSearchOptions = document.getElementById('productSearchOptions');
        const productIdInput = document.getElementById('productIdInput');
        const productSelect = document.getElementById('productSelect');
        const uomSelect = document.getElementById('uomSelect');
        const uomStockHint = document.getElementById('uomStockHint');
        const moveTypeSelect = document.getElementById('moveTypeSelect');
        const moveNoteInput = document.getElementById('moveNoteInput');
        const moveNoteLabel = document.getElementById('moveNoteLabel');
        const wastageReasonHint = document.getElementById('wastageReasonHint');
        const departmentStockPanel = document.getElementById('departmentStockPanel');
        const departmentStockList = document.getElementById('departmentStockList');
        const departmentSelect = document.getElementById('departmentSelect');
        const qtyHelpText = document.getElementById('qtyHelpText');
        const productStockUrlTemplate = @json($productStockUrlTemplate);
        const updateCostUrlTemplate = @json($updateCostUrlTemplate);
        const unitCostInput = document.getElementById('unitCostInput');
        const currentCostHint = document.getElementById('currentCostHint');
        const updateCostBtn = document.getElementById('updateCostBtn');
        const updateCostForm = document.getElementById('updateCostForm');
        const updateCostValue = document.getElementById('updateCostValue');
        const updateCostUom = document.getElementById('updateCostUom');

        const initialProductId = @json(old('product_id', request()->query('product_id')));
        const initialUom = @json(old('uom', request()->query('uom')));
        let lastDepartmentStock = null;
        let lastUnitCostBase = null;

        const productOptions = Array.from(productSelect.options)
            .filter((opt) => !!opt.value)
            .map((opt) => ({
                id: String(opt.value),
                label: String(opt.text).trim(),
                normalized: String(opt.text).toLowerCase(),
            }));

        function formatQty(qty) {
            return Number(qty || 0).toLocaleString(undefined, {
                minimumFractionDigits: 0,
                maximumFractionDigits: 3,
            });
        }

        function getSelectedDepartmentId() {
            return departmentSelect?.value ? String(departmentSelect.value) : '';
        }

        function getSelectedDepartmentStock() {
            const deptId = getSelectedDepartmentId();
            if (!lastDepartmentStock || !deptId) {
                return null;
            }
            return (lastDepartmentStock.departments || []).find((row) => String(row.id) === deptId) ?? null;
        }

        function selectedDepartmentQtyBase(productId) {
            const deptRow = getSelectedDepartmentStock();
            if (deptRow) {
                return Number(deptRow.qty || 0);
            }
            const product = productMap[productId];
            return product ? Number(product.qty_on_hand || 0) : 0;
        }

        function getUomFactor(productId, uom) {
            const product = productMap[productId];
            if (!product || !uom) {
                return null;
            }
            const row = (product.uoms || []).find((item) => item.uom === uom);
            return row ? Number(row.factor || 0) : null;
        }

        function formatCost(value) {
            if (!Number.isFinite(value)) {
                return '0';
            }
            let text = (Math.round(value * 1000000) / 1000000).toFixed(6);
            text = text.replace(/\.?0+$/, '');
            return text === '' ? '0' : text;
        }

        function costInSelectedUom(productId, uom, unitCostBase) {
            const factor = getUomFactor(productId, uom);
            if (factor === null || factor <= 0) {
                return unitCostBase;
            }
            return unitCostBase * factor;
        }

        function syncCostFields(productId, preferValue = null) {
            const product = productMap[productId];
            const uom = uomSelect?.value || product?.base_uom || '';
            const unitCostBase = lastUnitCostBase ?? product?.unit_cost_base ?? 0;

            if (!productId || !product) {
                currentCostHint.textContent = 'Select product to view current cost.';
                if (preferValue === null && !unitCostInput.value) {
                    unitCostInput.value = '';
                }
                return;
            }

            const currentInUom = costInSelectedUom(productId, uom, unitCostBase);
            currentCostHint.textContent = uom
                ? `Current cost: ${formatCost(currentInUom)} per ${uom}`
                : `Current cost: ${formatCost(unitCostBase)} per ${product.base_uom}`;

            if (preferValue !== null) {
                unitCostInput.value = formatCost(preferValue);
            } else if (!unitCostInput.value && currentInUom > 0) {
                unitCostInput.value = formatCost(currentInUom);
            }
        }

        function setUomStockHint(productId) {
            const product = productMap[productId];
            if (!product) {
                uomStockHint.textContent = 'Select product/UOM to view available stock.';
                return;
            }

            const deptRow = getSelectedDepartmentStock();
            const deptQty = selectedDepartmentQtyBase(productId);
            const deptLabel = deptRow
                ? (deptRow.is_warehouse ? `${deptRow.name} (Warehouse)` : deptRow.name)
                : 'Selected department';

            const baseText = `${deptLabel}: ${formatQty(deptQty)} ${product.base_uom}`;
            const innerText = product.inner_uom && deptRow?.is_warehouse
                ? ` | Inner stock: ${formatQty(product.inner_qty_on_hand)} ${product.inner_uom}`
                : '';
            uomStockHint.textContent = `${baseText}${innerText}`;
        }

        let departmentStockRequestId = 0;

        async function loadDepartmentStock(productId) {
            if (!departmentStockPanel || !departmentStockList) {
                return;
            }

            if (!productId) {
                departmentStockPanel.classList.add('d-none');
                departmentStockList.innerHTML = '';
                return;
            }

            const requestId = ++departmentStockRequestId;
            departmentStockPanel.classList.remove('d-none');
            departmentStockList.innerHTML = '<span class="text-secondary small">Loading department stock...</span>';

            try {
                const url = productStockUrlTemplate.replace('__ID__', encodeURIComponent(productId));
                const response = await fetch(url, {
                    headers: { 'Accept': 'application/json' },
                });

                if (!response.ok) {
                    throw new Error('Failed to load department stock');
                }

                const data = await response.json();
                if (requestId !== departmentStockRequestId) {
                    return;
                }

                lastDepartmentStock = data;
                lastUnitCostBase = Number(data.unit_cost_base ?? 0);
                const departments = Array.isArray(data.departments) ? data.departments : [];
                const baseUom = data.base_uom || '';
                const selectedDeptId = getSelectedDepartmentId();

                if (departments.length === 0) {
                    departmentStockList.innerHTML = '<span class="text-secondary small">No department stock found.</span>';
                    return;
                }

                departmentStockList.innerHTML = departments.map((row) => {
                    const qty = formatQty(row.qty);
                    const label = row.is_warehouse ? `${row.name} (Warehouse)` : row.name;
                    const qtyClass = Number(row.qty) < 0 ? 'text-danger' : (Number(row.qty) > 0 ? 'text-primary' : 'text-secondary');
                    const isSelected = selectedDeptId && String(row.id) === selectedDeptId;
                    return `<span class="badge ${isSelected ? 'bg-primary bg-opacity-10 border-primary' : 'bg-white border'} text-dark px-3 py-2">
                        <span class="fw-semibold">${label}</span>:
                        <span class="${qtyClass}">${qty} ${baseUom}</span>
                    </span>`;
                }).join('');

                const productId = productIdInput.value;
                if (productId) {
                    setUomStockHint(productId);
                    refreshUomOptions(productId);
                    syncCostFields(productId);
                }
            } catch (error) {
                if (requestId !== departmentStockRequestId) {
                    return;
                }
                departmentStockList.innerHTML = '<span class="text-danger small">Could not load department stock.</span>';
            }
        }

        function refreshUomOptions(productId, preferUom = null) {
            const product = productMap[productId];
            const list = product?.uoms ?? [];
            const deptQtyBase = selectedDepartmentQtyBase(productId);
            const currentUom = preferUom || uomSelect.value;

            uomSelect.innerHTML = '<option value="">Select UOM...</option>' + list.map(u => {
                const stockInThisUom = u.factor > 0 ? (deptQtyBase / u.factor) : 0;
                const label = u.factor === 1
                    ? `${u.uom} (base, stock: ${formatQty(stockInThisUom)} ${u.uom})`
                    : `${u.uom} (stock: ${formatQty(stockInThisUom)} ${u.uom})`;
                const selected = currentUom === u.uom ? 'selected' : '';
                return `<option value="${u.uom}" ${selected}>${label}</option>`;
            }).join('');

            if (currentUom) {
                uomSelect.value = currentUom;
            } else if (list.length > 0) {
                uomSelect.value = list[0].uom;
            }

            syncCostFields(productId);
        }

        let preferInitialUom = true;

        function setUoms(productId) {
            const uomPreference = preferInitialUom ? (initialUom || null) : null;
            preferInitialUom = false;
            refreshUomOptions(productId, uomPreference);
            setUomStockHint(productId);
            syncCostFields(productId, uomPreference ? null : undefined);
            loadDepartmentStock(productId);
        }

        function findProductByLabel(label) {
            const value = (label || '').trim().toLowerCase();
            if (!value) {
                return null;
            }
            return productOptions.find((opt) => opt.normalized === value) ?? null;
        }

        function findProductByContains(term) {
            const value = (term || '').trim().toLowerCase();
            if (!value) {
                return null;
            }
            return productOptions.find((opt) => opt.normalized.includes(value)) ?? null;
        }

        function setSelectedProduct(option) {
            if (!option) {
                productIdInput.value = '';
                productSelect.value = '';
                lastUnitCostBase = null;
                setUoms('');
                loadDepartmentStock('');
                return;
            }

            productIdInput.value = option.id;
            productSelect.value = option.id;
            productSearchInput.value = option.label;
            setUoms(option.id);
        }

        function buildProductSearchOptions(term) {
            const query = (term || '').trim().toLowerCase();
            const list = !query
                ? productOptions.slice(0, 50)
                : productOptions.filter((opt) => opt.normalized.includes(query)).slice(0, 100);

            productSearchOptions.innerHTML = list
                .map((opt) => `<option value="${opt.label}"></option>`)
                .join('');
        }

        function setupProductSearch() {
            buildProductSearchOptions('');

            productSearchInput.addEventListener('focus', () => {
                buildProductSearchOptions(productSearchInput.value);
            });

            productSearchInput.addEventListener('input', () => {
                const exact = findProductByLabel(productSearchInput.value);
                if (exact) {
                    setSelectedProduct(exact);
                } else {
                    productIdInput.value = '';
                    setUomStockHint('');
                }
                buildProductSearchOptions(productSearchInput.value);
            });

            productSearchInput.addEventListener('blur', () => {
                const exact = findProductByLabel(productSearchInput.value);
                const contains = findProductByContains(productSearchInput.value);
                if (exact) {
                    setSelectedProduct(exact);
                } else if (contains) {
                    setSelectedProduct(contains);
                } else if (!productSearchInput.value.trim()) {
                    setSelectedProduct(null);
                }
            });

            productSearchInput.addEventListener('keydown', (e) => {
                if (e.key !== 'Enter') {
                    return;
                }
                e.preventDefault();
                const exact = findProductByLabel(productSearchInput.value);
                const contains = findProductByContains(productSearchInput.value);
                if (exact) {
                    setSelectedProduct(exact);
                } else if (contains) {
                    setSelectedProduct(contains);
                }
            });
        }

        function syncQtyHelpText() {
            if (!qtyHelpText || !moveTypeSelect) return;
            const isAdjust = moveTypeSelect.value === 'adjust';
            qtyHelpText.textContent = isAdjust
                ? 'For ADJUST, this becomes the new quantity in the selected department.'
                : 'Enter quantity in the selected UOM for the selected department.';
        }

        function syncWastageReasonRequirement() {
            if (!moveTypeSelect || !moveNoteInput || !moveNoteLabel) return;
            const isWastage = moveTypeSelect.value === 'wastage';
            moveNoteInput.required = isWastage;
            moveNoteLabel.textContent = isWastage ? 'Reason' : 'Note';
            wastageReasonHint?.classList.toggle('d-none', !isWastage);
            syncQtyHelpText();
        }

        departmentSelect?.addEventListener('change', () => {
            const productId = productIdInput.value;
            if (productId) {
                setUomStockHint(productId);
                refreshUomOptions(productId);
                syncCostFields(productId);
                if (lastDepartmentStock) {
                    loadDepartmentStock(productId);
                }
            }
        });

        uomSelect?.addEventListener('change', () => {
            const productId = productIdInput.value;
            if (productId) {
                syncCostFields(productId);
            }
        });

        updateCostBtn?.addEventListener('click', () => {
            const productId = productIdInput.value;
            const uom = uomSelect?.value || '';
            const costValue = unitCostInput?.value ?? '';

            if (!productId) {
                alert('Pehle product select karein.');
                return;
            }
            if (!uom) {
                alert('Pehle UOM select karein.');
                return;
            }
            if (costValue === '' || Number(costValue) < 0) {
                alert('Valid unit cost likhein.');
                return;
            }

            updateCostForm.action = updateCostUrlTemplate.replace('__ID__', encodeURIComponent(productId));
            updateCostValue.value = costValue;
            updateCostUom.value = uom;
            updateCostForm.submit();
        });

        moveTypeSelect?.addEventListener('change', syncWastageReasonRequirement);
        setupProductSearch();

        const pid = initialProductId ?? productIdInput.value ?? productSelect.value;
        if (pid) {
            const selected = productOptions.find((opt) => opt.id === String(pid));
            if (selected) {
                setSelectedProduct(selected);
            } else {
                setUoms(pid);
            }
        } else {
            setUomStockHint('');
        }
        syncWastageReasonRequirement();
        syncQtyHelpText();
    </script>
@endsection

