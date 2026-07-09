@extends('layouts.admin')

@section('title', 'Stock Adjustment - Inventory - ' . config('app.name'))
@section('page_title', 'Inventory / Stock Adjustment')

@section('content')
    @include('inventory.partials.subnav')

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">Update stock</div>
        <div class="card-body">
            <form method="POST" action="{{ route('inventory.moves.store') }}">
                @csrf

                <div class="row g-3">
                    <div class="col-12 col-lg-6">
                        <label class="form-label">Product</label>
                        <input
                            type="text"
                            id="productSearchInput"
                            class="form-control @error('product_id') is-invalid @enderror"
                            placeholder="Type SKU or product name..."
                            autocomplete="off"
                            list="productSearchOptions"
                        >
                        <datalist id="productSearchOptions"></datalist>
                        <input type="hidden" id="productIdInput" name="product_id" value="{{ old('product_id') }}" required>
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

                    <div class="col-12 col-lg-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" step="0.001" min="0.001" name="qty_uom" value="{{ old('qty_uom') }}"
                               class="form-control @error('qty_uom') is-invalid @enderror" required>
                        @error('qty_uom')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">For ADJUST, this becomes the new on-hand quantity.</div>
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

                    <div class="col-12 col-lg-8">
                        <label class="form-label" id="moveNoteLabel">Note</label>
                        <input type="text" id="moveNoteInput" name="note" value="{{ old('note') }}"
                               class="form-control @error('note') is-invalid @enderror" maxlength="255">
                        <div class="form-text d-none" id="wastageReasonHint">Wastage type par reason required hai.</div>
                        @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="d-flex gap-2 mt-4">
                    <button class="btn btn-success" type="submit">Apply</button>
                    <a href="{{ route('inventory.moves.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    @php
        $productMap = $products->mapWithKeys(function ($p) {
            return [(string) $p->id => [
                'uoms' => $p->uomsForForms(),
                'qty_on_hand' => (float) $p->qty_on_hand,
                'base_uom' => (string) $p->uom,
                'inner_qty_on_hand' => $p->qtyOnHandAsPackageContents(),
                'inner_uom' => $p->hasPackageContents() ? (string) $p->package_contents_uom : null,
            ]];
        });
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

        const initialProductId = @json(old('product_id'));
        const initialUom = @json(old('uom'));

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

        function setUomStockHint(productId) {
            const product = productMap[productId];
            if (!product) {
                uomStockHint.textContent = 'Select product/UOM to view available stock.';
                return;
            }

            const baseText = `Available: ${formatQty(product.qty_on_hand)} ${product.base_uom}`;
            const innerText = product.inner_uom
                ? ` | Inner stock: ${formatQty(product.inner_qty_on_hand)} ${product.inner_uom}`
                : '';
            uomStockHint.textContent = `${baseText}${innerText}`;
        }

        function setUoms(productId) {
            const product = productMap[productId];
            const list = product?.uoms ?? [];
            uomSelect.innerHTML = '<option value="">Select UOM...</option>' + list.map(u => {
                const stockInThisUom = u.factor > 0 ? (product.qty_on_hand / u.factor) : 0;
                const label = u.factor === 1
                    ? `${u.uom} (base, stock: ${formatQty(stockInThisUom)} ${u.uom})`
                    : `${u.uom} (stock: ${formatQty(stockInThisUom)} ${u.uom})`;
                const selected = (initialUom && initialUom === u.uom) ? 'selected' : '';
                return `<option value="${u.uom}" ${selected}>${label}</option>`;
            }).join('');

            if ((!initialUom || initialUom === '') && list.length > 0) {
                uomSelect.value = list[0].uom;
            }

            setUomStockHint(productId);
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
                setUoms('');
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

        function syncWastageReasonRequirement() {
            if (!moveTypeSelect || !moveNoteInput || !moveNoteLabel) return;
            const isWastage = moveTypeSelect.value === 'wastage';
            moveNoteInput.required = isWastage;
            moveNoteLabel.textContent = isWastage ? 'Reason' : 'Note';
            wastageReasonHint?.classList.toggle('d-none', !isWastage);
        }

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
        }
        else setUomStockHint('');
        syncWastageReasonRequirement();
    </script>
@endsection

