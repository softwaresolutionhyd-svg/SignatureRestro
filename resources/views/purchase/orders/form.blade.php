@csrf

@php
    $oldLines = old('lines');
    if (!is_array($oldLines)) $oldLines = null;
    $uomLib = isset($uomLibraryUnits) ? $uomLibraryUnits : collect();

    $productsJs = $products->map(function ($p) {
        $uoms = $p->uomsForForms();

        return [
            'id' => $p->id,
            'label' => $p->name,
            'base_uom' => $p->uom,
            'cost' => (float) ($p->cost ?? 0),
            'package_contents_qty' => $p->package_contents_qty !== null ? (float) $p->package_contents_qty : null,
            'package_contents_uom' => $p->package_contents_uom,
            'sku' => $p->sku,
            'uoms' => $uoms,
            'search_label' => $p->name,
        ];
    })->values()->toArray();

    $initialLinesPhp = $oldLines;
    if (!is_array($initialLinesPhp)) {
        if (isset($order) && $order && isset($order->lines)) {
            $initialLinesPhp = $order->lines->map(function ($l) {
                return [
                    'product_id' => $l->product_id,
                    'uom' => $l->uom,
                    'qty' => (float) $l->qty,
                    'unit_price' => (float) $l->unit_price,
                    'tax_percent' => (float) $l->tax_percent,
                ];
            })->values()->toArray();
        } else {
            $initialLinesPhp = [];
        }
    }
@endphp

<div class="row g-3">
    <div class="col-12 col-lg-4">
        <label class="form-label">Vendor</label>
        <select name="vendor_id" class="form-select @error('vendor_id') is-invalid @enderror" required>
            <option value="">Select vendor...</option>
            @foreach($vendors as $v)
                <option value="{{ $v->id }}" @selected((string)old('vendor_id', $order->vendor_id ?? '') === (string)$v->id)>{{ $v->name }}</option>
            @endforeach
        </select>
        @error('vendor_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-lg-3">
        <label class="form-label">Order date</label>
        <input type="date" name="order_date" value="{{ old('order_date', optional($order->order_date ?? null)?->format('Y-m-d')) }}"
               class="form-control @error('order_date') is-invalid @enderror">
        @error('order_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-lg-3">
        <label class="form-label">Expected date</label>
        <input type="date" name="expected_date" value="{{ old('expected_date', optional($order->expected_date ?? null)?->format('Y-m-d')) }}"
               class="form-control @error('expected_date') is-invalid @enderror">
        @error('expected_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-lg-2">
        <label class="form-label">Status</label>
        <input type="text" class="form-control" value="{{ strtoupper($order->status ?? 'RFQ') }}" disabled>
    </div>

    <div class="col-12 col-lg-3">
        <label class="form-label">Purchase Type</label>
        <select name="purchase_type" class="form-select @error('purchase_type') is-invalid @enderror" required>
            @php $purchaseType = old('purchase_type', $order->purchase_type ?? 'debit'); @endphp
            <option value="debit" {{ $purchaseType === 'debit' ? 'selected' : '' }}>Debit / Cash (already paid)</option>
            <option value="credit" {{ $purchaseType === 'credit' ? 'selected' : '' }}>Credit (pay later)</option>
        </select>
        @error('purchase_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label class="form-label">Note</label>
        <input type="text" name="note" value="{{ old('note', $order->note ?? '') }}"
               class="form-control @error('note') is-invalid @enderror" maxlength="255">
        @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<hr class="my-4">

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
    <div class="fw-semibold">Order lines</div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="quickAddProductBtn">
            <i class="bi bi-box-seam me-1"></i> Quick add product
        </button>
        <button type="button" class="btn btn-sm btn-outline-primary" id="addLineBtn">
            <i class="bi bi-plus-circle me-1"></i> Add line
        </button>
    </div>
</div>

@error('lines')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

<div class="table-responsive border rounded-3">
    <table class="table mb-0 align-middle" id="linesTable">
        <thead class="table-light">
        <tr>
            <th style="min-width: 260px;">Product</th>
            <th style="min-width: 120px;">UOM</th>
            <th class="text-end" style="min-width: 120px;">Qty</th>
            <th class="text-end" style="min-width: 140px;">Unit price <span class="text-secondary fw-normal small">(auto)</span></th>
            <th class="text-end" style="min-width: 140px;">Total <span class="text-secondary fw-normal small">(editable)</span></th>
            <th class="text-end" style="width: 1%;">&nbsp;</th>
        </tr>
        </thead>
        <tbody id="linesBody"></tbody>
    </table>
</div>

<div class="row g-3 mt-2 justify-content-end">
    <div class="col-12 col-md-6 col-lg-4">
        <div class="border rounded-3 p-3 bg-light">
            <div class="d-flex justify-content-between">
                <div class="text-secondary">Subtotal</div>
                <div class="fw-semibold" id="subtotalText">0.00</div>
            </div>
            <hr class="my-2">
            <div class="d-flex justify-content-between">
                <div class="fw-semibold">Total</div>
                <div class="fw-bold" id="grandText">0.00</div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex gap-2 mt-4">
    <button class="btn btn-primary" type="submit">Save</button>
    <a href="{{ route('purchase.orders.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>

<div class="modal fade" id="quickEditProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Quick edit product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none py-2" id="quickEditProductError"></div>
                <input type="hidden" id="quickEditProductId">
                <div class="mb-2 text-secondary small" id="quickEditProductSku"></div>
                <div class="mb-3">
                    <label class="form-label">Product name</label>
                    <input type="text" class="form-control" id="quickEditProductName" maxlength="200" placeholder="e.g. Tender Pops 1kg">
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label">Base UOM</label>
                        <select class="form-select" id="quickEditProductBaseUom">
                            <option value="">Select base UOM...</option>
                            @foreach($uomLib as $unit)
                                <option value="{{ \App\Models\InventoryUnit::normalizeCode((string) $unit->code) }}">
                                    {{ \App\Models\InventoryUnit::normalizeCode((string) $unit->code) }} — {{ $unit->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Cost</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="quickEditProductCost" placeholder="0.00">
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-6">
                        <label class="form-label">Inner qty (optional)</label>
                        <input type="number" step="0.000001" min="0" class="form-control" id="quickEditProductInnerQty" placeholder="e.g. 24">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Inner UOM (optional)</label>
                        <select class="form-select" id="quickEditProductInnerUom">
                            <option value="">Select inner UOM...</option>
                            @foreach($uomLib as $unit)
                                <option value="{{ \App\Models\InventoryUnit::normalizeCode((string) $unit->code) }}">
                                    {{ \App\Models\InventoryUnit::normalizeCode((string) $unit->code) }} — {{ $unit->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="quickEditProductSaveBtn">Save changes</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="quickAddProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Quick add product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none py-2" id="quickAddProductError"></div>
                <div class="mb-3">
                    <label class="form-label">Product name</label>
                    <input type="text" class="form-control" id="quickProductName" maxlength="200" placeholder="e.g. Tender Pops 1kg">
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label">Base UOM</label>
                        <select class="form-select" id="quickProductBaseUom">
                            <option value="">Select base UOM...</option>
                            @foreach($uomLib as $unit)
                                <option value="{{ \App\Models\InventoryUnit::normalizeCode((string) $unit->code) }}">
                                    {{ \App\Models\InventoryUnit::normalizeCode((string) $unit->code) }} — {{ $unit->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Cost (optional)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="quickProductCost" placeholder="0.00">
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-6">
                        <label class="form-label">Inner qty (optional)</label>
                        <input type="number" step="0.000001" min="0" class="form-control" id="quickProductInnerQty" placeholder="e.g. 24">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Inner UOM (optional)</label>
                        <select class="form-select" id="quickProductInnerUom">
                            <option value="">Select inner UOM...</option>
                            @foreach($uomLib as $unit)
                                <option value="{{ \App\Models\InventoryUnit::normalizeCode((string) $unit->code) }}">
                                    {{ \App\Models\InventoryUnit::normalizeCode((string) $unit->code) }} — {{ $unit->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-text mt-2">SKU auto-generate hoga (manual enter ki zarurat nahi).</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="quickAddProductSaveBtn">Create & select</button>
            </div>
        </div>
    </div>
</div>

<script>
    const products = @json($productsJs);

    const initialLines = @json($initialLinesPhp);

    const body = document.getElementById('linesBody');
    const addBtn = document.getElementById('addLineBtn');
    const quickAddProductBtn = document.getElementById('quickAddProductBtn');
    const quickAddProductSaveBtn = document.getElementById('quickAddProductSaveBtn');
    const quickAddProductError = document.getElementById('quickAddProductError');
    const quickEditProductSaveBtn = document.getElementById('quickEditProductSaveBtn');
    const quickEditProductError = document.getElementById('quickEditProductError');
    const quickEditProductId = document.getElementById('quickEditProductId');
    const quickEditProductSku = document.getElementById('quickEditProductSku');
    const quickEditProductName = document.getElementById('quickEditProductName');
    const quickEditProductBaseUom = document.getElementById('quickEditProductBaseUom');
    const quickEditProductCost = document.getElementById('quickEditProductCost');
    const quickEditProductInnerQty = document.getElementById('quickEditProductInnerQty');
    const quickEditProductInnerUom = document.getElementById('quickEditProductInnerUom');
    const quickProductName = document.getElementById('quickProductName');
    const quickProductBaseUom = document.getElementById('quickProductBaseUom');
    const quickProductCost = document.getElementById('quickProductCost');
    const quickProductInnerQty = document.getElementById('quickProductInnerQty');
    const quickProductInnerUom = document.getElementById('quickProductInnerUom');
    const quickAddProductUrl = @json(route('purchase.orders.quick-product'));
    const quickEditProductUrlTemplate = @json(route('purchase.orders.quick-product.update', ['product' => '__ID__']));
    const csrfToken = @json(csrf_token());
    const productDatalistId = 'purchaseProductSearchList';
    let quickModal = null;
    let quickEditModal = null;
    let quickEditRow = null;

    function fmt(n) {
        if (!Number.isFinite(n)) return '0';
        let s = (Math.round(n * 100) / 100).toFixed(2);
        if (s.includes('.')) s = s.replace(/\.?0+$/, '');
        return s === '-0' ? '0' : s;
    }

    function escHtml(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');
    }

    function lineTotalOf(tr) {
        return parseFloat(tr.querySelector('.lineTotalInput')?.value || '0') || 0;
    }

    // Qty × Unit price => Total (normal direction).
    function setLineTotalFromUnit(tr) {
        const qty = parseFloat(tr.querySelector('[name$="[qty]"]').value || '0') || 0;
        const unit = parseFloat(tr.querySelector('[name$="[unit_price]"]').value || '0') || 0;
        const total = qty * unit;
        const input = tr.querySelector('.lineTotalInput');
        if (input) input.value = total ? (Math.round(total * 100) / 100) : '';
    }

    // Total ÷ Qty => Unit price (reverse direction: user Total type kare, unit auto ban jaye).
    function setUnitFromLineTotal(tr) {
        const qty = parseFloat(tr.querySelector('[name$="[qty]"]').value || '0') || 0;
        const total = lineTotalOf(tr);
        const unitInput = tr.querySelector('[name$="[unit_price]"]');
        if (!unitInput) return;
        unitInput.value = qty > 0 ? Number((total / qty).toFixed(4)) : 0;
    }

    function refreshSubtotal() {
        let subtotal = 0;
        [...body.querySelectorAll('tr')].forEach(tr => { subtotal += lineTotalOf(tr); });
        document.getElementById('subtotalText').textContent = fmt(subtotal);
        document.getElementById('grandText').textContent = fmt(subtotal);
    }

    // Sab lines ka total qty×unit se refresh karo (product select / load ke waqt).
    function computeTotals() {
        [...body.querySelectorAll('tr')].forEach(tr => setLineTotalFromUnit(tr));
        refreshSubtotal();
    }

    function uomOptions(productId, selected) {
        const p = products.find(x => String(x.id) === String(productId));
        const list = p ? p.uoms : [];
        return '<option value="">Select...</option>' + list.map(u => {
            const sel = selected && selected === u.uom ? 'selected' : '';
            const label = (u.factor === 1) ? (u.uom + ' (base)') : u.uom;
            return `<option value="${u.uom}" ${sel}>${label}</option>`;
        }).join('');
    }

    function productDatalistOptions() {
        return products.map(p => `<option value="${escHtml(p.search_label)}"></option>`).join('');
    }

    function refreshProductDatalist() {
        const dl = document.getElementById(productDatalistId);
        if (!dl) return;
        dl.innerHTML = productDatalistOptions();
    }

    function normalizeProductPayload(raw) {
        const fallbackName = String(raw?.name || raw?.label || raw?.search_label || '').trim();
        return {
            ...raw,
            name: fallbackName,
            label: fallbackName,
            search_label: fallbackName,
            sku: String(raw?.sku || ''),
            base_uom: String(raw?.base_uom || raw?.uom || ''),
            cost: Number(raw?.cost || 0),
            package_contents_qty: raw?.package_contents_qty ?? null,
            package_contents_uom: raw?.package_contents_uom ?? null,
            uoms: Array.isArray(raw?.uoms) ? raw.uoms : [],
        };
    }

    function findProductBySearchLabel(value) {
        const q = String(value || '').trim().toLowerCase();
        if (!q) return null;
        return products.find(p => String(p.search_label).toLowerCase() === q) || null;
    }

    function findProductByContains(value) {
        const q = String(value || '').trim().toLowerCase();
        if (!q) return null;
        return products.find((p) => {
            const label = String(p.label || p.name || '').toLowerCase();
            const searchLabel = String(p.search_label || p.name || '').toLowerCase();
            return label.includes(q) || searchLabel.includes(q);
        }) || null;
    }

    function searchLabelByProductId(productId) {
        const p = products.find(x => String(x.id) === String(productId));
        if (!p) return '';
        return p.name || p.label || p.search_label || '';
    }

    function addLine(line = {}) {
        const idx = body.querySelectorAll('tr').length;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
              <input class="form-control line-product-search" type="text" list="${productDatalistId}" placeholder="Type to search product..." autocomplete="off" required>
              <input type="hidden" class="line-product-id" name="lines[${idx}][product_id]" value="${line.product_id ?? ''}">
            </td>
            <td>
              <select class="form-select" name="lines[${idx}][uom]" required>
                ${uomOptions(line.product_id, line.uom)}
              </select>
            </td>
            <td><input class="form-control text-end" type="number" step="0.001" min="0.001" name="lines[${idx}][qty]" value="${line.qty ?? ''}" required></td>
            <td>
              <input class="form-control text-end" type="number" step="0.01" min="0" name="lines[${idx}][unit_price]" value="${line.unit_price ?? '0'}" required>
              <input type="hidden" name="lines[${idx}][tax_percent]" value="0">
            </td>
            <td><input class="form-control text-end fw-semibold lineTotalInput" type="number" step="0.01" min="0" placeholder="0.00" value=""></td>
            <td class="text-end">
              <button type="button" class="btn btn-sm btn-outline-secondary quickEditLineProduct">Quick edit</button>
              <button type="button" class="btn btn-sm btn-outline-danger removeLine">Remove</button>
            </td>
        `;

        const productSearch = tr.querySelector('.line-product-search');
        const productIdHidden = tr.querySelector('.line-product-id');
        productSearch.value = searchLabelByProductId(line.product_id);

        function setProductSelection(product) {
            const pid = product ? String(product.id) : '';
            productIdHidden.value = pid;
            if (product) {
                productSearch.value = product.search_label;
            }
            const uomSel = tr.querySelector('[name$="[uom]"]');
            uomSel.innerHTML = uomOptions(pid, null);
            computeTotals();
        }

        function resolveTypedProduct() {
            const exact = findProductBySearchLabel(productSearch.value);
            if (exact) {
                setProductSelection(exact);
                return;
            }
            const contains = findProductByContains(productSearch.value);
            if (contains) {
                setProductSelection(contains);
                return;
            }
            setProductSelection(null);
        }

        productSearch.addEventListener('input', () => {
            const exact = findProductBySearchLabel(productSearch.value);
            if (exact) {
                setProductSelection(exact);
            } else {
                productIdHidden.value = '';
            }
        });
        productSearch.addEventListener('blur', resolveTypedProduct);
        productSearch.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            resolveTypedProduct();
        });

        const unitPriceInput = tr.querySelector('[name$="[unit_price]"]');
        unitPriceInput.addEventListener('keydown', (e) => {
            if (e.key !== 'Tab' || e.shiftKey) return;
            const rows = [...body.querySelectorAll('tr')];
            const isLastRow = rows[rows.length - 1] === tr;
            if (!isLastRow) return;
            e.preventDefault();
            addLine({ unit_price: 0, tax_percent: 0 });
            const nextRow = body.querySelector('tr:last-child .line-product-search');
            if (nextRow) nextRow.focus();
        });

        const qtyInput = tr.querySelector('[name$="[qty]"]');
        const lineTotalInput = tr.querySelector('.lineTotalInput');
        qtyInput.addEventListener('input', () => { setLineTotalFromUnit(tr); refreshSubtotal(); });
        unitPriceInput.addEventListener('input', () => { setLineTotalFromUnit(tr); refreshSubtotal(); });
        lineTotalInput.addEventListener('input', () => { setUnitFromLineTotal(tr); refreshSubtotal(); });
        tr.querySelector('.quickEditLineProduct').addEventListener('click', () => openQuickEditModal(tr));
        tr.querySelector('.removeLine').addEventListener('click', () => {
            tr.remove();
            // re-index names
            [...body.querySelectorAll('tr')].forEach((row, i) => {
                row.querySelectorAll('[name]').forEach(el => {
                    el.name = el.name.replace(/lines\\[\\d+\\]/, `lines[${i}]`);
                });
            });
            computeTotals();
        });

        body.appendChild(tr);
        computeTotals();
    }

    // Shared datalist for all line search inputs.
    if (!document.getElementById(productDatalistId)) {
        const dl = document.createElement('datalist');
        dl.id = productDatalistId;
        dl.innerHTML = productDatalistOptions();
        document.body.appendChild(dl);
    }

    function showQuickAddError(message) {
        if (!quickAddProductError) return;
        quickAddProductError.textContent = message || 'Unable to create product.';
        quickAddProductError.classList.remove('d-none');
    }

    function clearQuickAddError() {
        if (!quickAddProductError) return;
        quickAddProductError.textContent = '';
        quickAddProductError.classList.add('d-none');
    }

    function showQuickEditError(message) {
        if (!quickEditProductError) return;
        quickEditProductError.textContent = message || 'Unable to update product.';
        quickEditProductError.classList.remove('d-none');
    }

    function clearQuickEditError() {
        if (!quickEditProductError) return;
        quickEditProductError.textContent = '';
        quickEditProductError.classList.add('d-none');
    }

    function openQuickAddModal() {
        if (!quickModal && window.bootstrap?.Modal) {
            quickModal = new bootstrap.Modal(document.getElementById('quickAddProductModal'));
        }
        clearQuickAddError();
        quickProductName.value = '';
        quickProductBaseUom.value = '';
        quickProductCost.value = '';
        quickProductInnerQty.value = '';
        quickProductInnerUom.value = '';
        quickModal?.show();
    }

    function openQuickEditModal(row) {
        const pid = row?.querySelector('.line-product-id')?.value;
        if (!pid) {
            alert('Pehle line me product select karein.');
            return;
        }
        const product = products.find(p => String(p.id) === String(pid));
        if (!product) {
            alert('Selected product data not found.');
            return;
        }
        if (!quickEditModal && window.bootstrap?.Modal) {
            quickEditModal = new bootstrap.Modal(document.getElementById('quickEditProductModal'));
        }
        quickEditRow = row;
        clearQuickEditError();
        quickEditProductId.value = String(product.id);
        quickEditProductSku.textContent = product.sku ? `SKU: ${product.sku}` : '';
        quickEditProductName.value = product.name || '';
        quickEditProductBaseUom.value = product.base_uom || '';
        quickEditProductCost.value = Number.isFinite(Number(product.cost)) ? String(product.cost) : '';
        quickEditProductInnerQty.value = product.package_contents_qty ?? '';
        quickEditProductInnerUom.value = product.package_contents_uom || '';
        quickEditModal?.show();
    }

    async function createQuickProduct() {
        clearQuickAddError();

        const payload = {
            name: quickProductName.value,
            uom: quickProductBaseUom.value,
            cost: quickProductCost.value,
            package_contents_qty: quickProductInnerQty.value,
            package_contents_uom: quickProductInnerUom.value,
        };

        quickAddProductSaveBtn.disabled = true;
        try {
            const res = await fetch(quickAddProductUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok || !json.product) {
                const firstError = json?.errors ? Object.values(json.errors)?.[0]?.[0] : null;
                throw new Error(firstError || json?.message || 'Product create failed.');
            }

            products.push(normalizeProductPayload(json.product));
            refreshProductDatalist();
            addLine({ product_id: json.product.id, uom: json.product.base_uom, qty: '', unit_price: payload.cost || 0, tax_percent: 0 });
            quickModal?.hide();
        } catch (e) {
            showQuickAddError(e?.message || 'Product create failed.');
        } finally {
            quickAddProductSaveBtn.disabled = false;
        }
    }

    function replaceProductInCache(nextRaw) {
        const next = normalizeProductPayload(nextRaw);
        const i = products.findIndex(p => String(p.id) === String(next.id));
        if (i >= 0) {
            products[i] = next;
        } else {
            products.push(next);
        }
        refreshProductDatalist();
        return next;
    }

    function refreshRowsUsingProduct(product) {
        [...body.querySelectorAll('tr')].forEach((tr) => {
            const pidInput = tr.querySelector('.line-product-id');
            if (!pidInput || String(pidInput.value) !== String(product.id)) return;

            const searchInput = tr.querySelector('.line-product-search');
            const uomSel = tr.querySelector('[name$="[uom]"]');
            const currentUom = uomSel?.value || '';
            if (searchInput) searchInput.value = product.search_label || product.name || '';
            if (uomSel) {
                uomSel.innerHTML = uomOptions(product.id, currentUom);
                if (currentUom && !Array.from(uomSel.options).some(o => o.value === currentUom)) {
                    uomSel.value = product.base_uom || '';
                }
            }
            if (tr === quickEditRow) {
                const priceInput = tr.querySelector('[name$="[unit_price]"]');
                if (priceInput && (priceInput.value === '' || Number(priceInput.value) === 0)) {
                    priceInput.value = Number(product.cost || 0);
                }
            }
        });
        computeTotals();
    }

    async function saveQuickEditedProduct() {
        clearQuickEditError();
        const pid = quickEditProductId.value;
        if (!pid) {
            showQuickEditError('Product id missing.');
            return;
        }
        const payload = {
            name: quickEditProductName.value,
            uom: quickEditProductBaseUom.value,
            cost: quickEditProductCost.value,
            package_contents_qty: quickEditProductInnerQty.value,
            package_contents_uom: quickEditProductInnerUom.value,
        };
        const url = quickEditProductUrlTemplate.replace('__ID__', encodeURIComponent(String(pid)));

        quickEditProductSaveBtn.disabled = true;
        try {
            const res = await fetch(url, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok || !json.product) {
                const firstError = json?.errors ? Object.values(json.errors)?.[0]?.[0] : null;
                throw new Error(firstError || json?.message || 'Product update failed.');
            }
            const updated = replaceProductInCache(json.product);
            refreshRowsUsingProduct(updated);
            quickEditModal?.hide();
        } catch (e) {
            showQuickEditError(e?.message || 'Product update failed.');
        } finally {
            quickEditProductSaveBtn.disabled = false;
        }
    }

    quickAddProductBtn?.addEventListener('click', openQuickAddModal);
    quickAddProductSaveBtn?.addEventListener('click', createQuickProduct);
    quickEditProductSaveBtn?.addEventListener('click', saveQuickEditedProduct);

    addBtn.addEventListener('click', () => addLine({ unit_price: 0, tax_percent: 0 }));

    if (initialLines && initialLines.length) {
        initialLines.forEach(l => addLine(l));
    } else {
        addLine({ unit_price: 0, tax_percent: 0 });
    }
</script>

