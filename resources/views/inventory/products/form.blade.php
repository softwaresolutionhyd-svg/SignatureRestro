@csrf

@php
    $uomLib = isset($uomLibraryUnits) ? $uomLibraryUnits : collect();
    $baseUomVal = \App\Models\InventoryUnit::normalizeCode((string) old('uom', isset($product) && $product ? $product->uom : ''));
    $pkgUomVal = \App\Models\InventoryUnit::normalizeCode((string) old('package_contents_uom', isset($product) && $product ? ($product->package_contents_uom ?? '') : ''));
    $bomStandardCost = isset($bomStandardCost) ? (float) $bomStandardCost : null;
    $productCostDefault = isset($product) && $product
        ? ($bomStandardCost !== null && $bomStandardCost > 0 ? round($bomStandardCost, 2) : (float) ($product->cost ?? 0))
        : 0;
    $productCostValue = old('cost', $productCostDefault);
    $extraCostDefinitions = \App\Models\Setting::productExtraCostFieldDefinitions();
    $keyToLabel = [];
    foreach ($extraCostDefinitions as $i => &$extraCostDefRow) {
        $b = $extraCostDefRow['base'];
        $extraCostDefRow['base_label'] = match ($b) {
            'effective_cost' => 'Effective Cost (Auto)',
            'price' => 'Sale Price',
            'cost' => 'Cost',
            default => $keyToLabel[$b] ?? $b,
        };
        $keyToLabel[$extraCostDefRow['key']] = $extraCostDefRow['label'];
    }
    unset($extraCostDefRow);

    $extraCostDefinitionsForJs = array_map(static fn (array $d) => [
        'key' => $d['key'],
        'rate' => (float) ($d['rate'] ?? 0),
        'operator' => $d['operator'] ?? 'plus',
        'base' => $d['base'] ?? 'cost',
        'target' => $d['target'] ?? 'effective_cost',
    ], $extraCostDefinitions);

    $parentCategories = $parentCategories ?? collect();
    $subcategoriesByParent = $subcategoriesByParent ?? [];
    $categorySelection = $categorySelection ?? ['parent_id' => null, 'sub_category_id' => null];
    $selectedParentCategoryId = old('parent_category_id', $categorySelection['parent_id'] ?? '');
    $selectedSubCategoryId = old('sub_category_id', $categorySelection['sub_category_id'] ?? '');
    $departments = $departments ?? collect();
    $selectedDepartmentIds = $selectedDepartmentIds ?? [];
    $defaultWarehouseId = isset($defaultWarehouseId) ? (string) $defaultWarehouseId : null;
    $hasOldDepartmentInput = session()->hasOldInput('department_ids');
@endphp

<div class="row g-3">
    <div class="col-12 col-md-4">
        <label class="form-label">SKU</label>
        <input type="text" name="sku" value="{{ old('sku', $product->sku ?? '') }}"
               class="form-control @error('sku') is-invalid @enderror" maxlength="80" placeholder="Auto-generate if blank">
        @error('sku')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-4">
        <label class="form-label">Barcode</label>
        <input type="text" name="barcode" value="{{ old('barcode', $product->barcode ?? '') }}"
               class="form-control @error('barcode') is-invalid @enderror" maxlength="120">
        @error('barcode')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-4">
        <label class="form-label">Name</label>
        <input type="text" name="name" value="{{ old('name', $product->name ?? '') }}"
               class="form-control @error('name') is-invalid @enderror" required maxlength="200">
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-4">
        <label class="form-label">Product Picture <span class="text-secondary small">(1:1 square)</span></label>
        <input type="file" name="image" id="productImageInput" accept="image/jpeg,image/png,image/webp"
               class="form-control @error('image') is-invalid @enderror">
        @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text small text-secondary">POS menu box mein dikhegi. Auto crop center se square ban jati hai.</div>
        <div class="mt-2 d-flex align-items-start gap-3 flex-wrap">
            <div class="product-image-preview-wrap border rounded bg-light overflow-hidden" style="width:96px;height:96px;">
                @php $existingImage = isset($product) && $product ? $product->imageUrl() : null; @endphp
                <img src="{{ $existingImage ?: '' }}" alt="" id="productImagePreview"
                     class="w-100 h-100 {{ $existingImage ? '' : 'd-none' }}" style="object-fit:cover;">
                <div id="productImagePlaceholder" class="w-100 h-100 d-flex align-items-center justify-content-center text-secondary small {{ $existingImage ? 'd-none' : '' }}">
                    <i class="bi bi-image fs-4"></i>
                </div>
            </div>
            @if(isset($product) && $product && $product->image_path)
                <div class="form-check mt-1">
                    <input type="hidden" name="remove_image" value="0">
                    <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="removeProductImage">
                    <label class="form-check-label small" for="removeProductImage">Picture hata dein</label>
                </div>
            @endif
        </div>
    </div>

    <div class="col-12 col-md-3">
        <label class="form-label">Departments</label>
        <div class="border rounded p-2 bg-white @error('department_ids') border-danger @enderror @error('department_ids.*') border-danger @enderror"
             style="max-height:140px;overflow-y:auto;">
            @forelse($departments as $dep)
                @php
                    $depId = (string) $dep->id;
                    $isWarehouseDefault = $defaultWarehouseId !== null
                        && $depId === $defaultWarehouseId
                        && ($dep->is_warehouse ?? false);
                    $isDepartmentChecked = in_array($depId, $selectedDepartmentIds, true)
                        || ($isWarehouseDefault && ! $hasOldDepartmentInput);
                @endphp
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="department_ids[]" value="{{ $dep->id }}"
                           id="productDept{{ $dep->id }}"
                           @checked($isDepartmentChecked)>
                    <label class="form-check-label small" for="productDept{{ $dep->id }}">
                        {{ $dep->name }}
                        @if($dep->is_warehouse ?? false)
                            <span class="text-secondary">(default)</span>
                        @endif
                    </label>
                </div>
            @empty
                <div class="small text-secondary">Koi department nahi — pehle add karein.</div>
            @endforelse
        </div>
        @error('department_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        @error('department_ids.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <div class="form-text text-secondary small">
            Ek se zyada select ho sakte hain. <a href="{{ route('inventory.departments.index') }}">Departments manage karein</a>
        </div>
    </div>

    <div class="col-12 col-md-3">
        <label class="form-label">Category</label>
        <select name="parent_category_id" id="productParentCategory" class="form-select @error('parent_category_id') is-invalid @enderror">
            <option value="">—</option>
            @foreach($parentCategories as $c)
                <option value="{{ $c->id }}" @selected((string) $selectedParentCategoryId === (string) $c->id)>
                    {{ $c->name }}
                </option>
            @endforeach
        </select>
        @error('parent_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-3">
        <label class="form-label">Sub-category</label>
        <select name="sub_category_id" id="productSubCategory" class="form-select @error('sub_category_id') is-invalid @enderror">
            <option value="">—</option>
        </select>
        <div class="form-text text-secondary small">Pehle category choose karein.</div>
        @error('sub_category_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-6">
        <label class="form-label">Base UOM (stock unit)</label>
        @if($uomLib->isEmpty())
            <input type="text" name="uom" value="{{ old('uom', $product->uom ?? '') }}"
                   class="form-control @error('uom') is-invalid @enderror" required maxlength="30"
                   placeholder="e.g. pkt">
            <div class="form-text text-secondary small">Pehle <a href="{{ route('inventory.uom-library.index') }}">Inventory → Units</a> par units banaein taake yahan dropdown se sirf wohi codes choose hon (ek jaisa pooray system mein).</div>
        @else
            <select name="uom" id="productBaseUom" class="form-select @error('uom') is-invalid @enderror" required>
                <option value="">— Unit choose karein —</option>
                @foreach($uomLib as $unit)
                    @php $unitCode = \App\Models\InventoryUnit::normalizeCode((string) $unit->code); @endphp
                    <option value="{{ $unitCode }}" @selected($baseUomVal !== '' && $baseUomVal === $unitCode)>{{ $unitCode }} — {{ $unit->name }}</option>
                @endforeach
                @if(isset($product) && $product && $baseUomVal !== '' && !$uomLib->contains(fn ($u) => \App\Models\InventoryUnit::normalizeCode((string) $u->code) === $baseUomVal))
                    <option value="{{ $baseUomVal }}" selected class="text-danger">Legacy: {{ $baseUomVal }} (library mein yeh code add karein)</option>
                @endif
            </select>
            <div class="form-text text-secondary small">Sirf <a href="{{ route('inventory.uom-library.index') }}">Units library</a> wale codes — POS, purchase, BoM sab par yahi dikhengi.</div>
        @endif
        @error('uom')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <div class="border rounded-3 p-3" style="background:#f8fafc;">
            <div class="fw-semibold mb-3">1 packet / base unit = kitna andar? (optional)</div>
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label">Qty per 1 <span id="pkgPerBaseLabel">{{ $baseUomVal !== '' ? $baseUomVal : (old('uom', $product?->uom ?? 'base')) }}</span></label>
                    <input type="number" step="0.000001" min="0" name="package_contents_qty"
                           value="{{ old('package_contents_qty', isset($product) && $product ? $product->package_contents_qty : '') }}"
                           class="form-control @error('package_contents_qty') is-invalid @enderror"
                           placeholder="e.g. 25">
                    @error('package_contents_qty')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label">Andar wali UOM</label>
                    @if($uomLib->isEmpty())
                        <input type="text" name="package_contents_uom" maxlength="30"
                               value="{{ old('package_contents_uom', isset($product) && $product ? $product->package_contents_uom : '') }}"
                               class="form-control @error('package_contents_uom') is-invalid @enderror"
                               placeholder="e.g. g">
                    @else
                        <select name="package_contents_uom" class="form-select @error('package_contents_uom') is-invalid @enderror">
                            <option value="">—</option>
                            @foreach($uomLib as $unit)
                                @php $pkgCode = \App\Models\InventoryUnit::normalizeCode((string) $unit->code); @endphp
                                <option value="{{ $pkgCode }}" @selected($pkgUomVal !== '' && $pkgUomVal === $pkgCode)>{{ $pkgCode }} — {{ $unit->name }}</option>
                            @endforeach
                            @if(isset($product) && $product && $pkgUomVal !== '' && !$uomLib->contains(fn ($u) => \App\Models\InventoryUnit::normalizeCode((string) $u->code) === $pkgUomVal))
                                <option value="{{ $pkgUomVal }}" selected class="text-danger">Legacy: {{ $pkgUomVal }}</option>
                            @endif
                        </select>
                    @endif
                    @error('package_contents_uom')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    @php
        $rows = old('conversions');
        if (!is_array($rows)) $rows = [];
        if (empty($rows) && isset($product) && $product) {
            $rows = $product->uomConversions->map(fn($c) => [
                'uom' => $c->uom,
                'factor_to_base' => $c->factor_to_base,
            ])->toArray();
        }
        if (empty($rows)) {
            $rows = [['uom' => '', 'factor_to_base' => '']];
        }
    @endphp

    <div class="col-12 d-none" id="productOtherUnitsSection" aria-hidden="true">
        <div class="border rounded-3 p-3 bg-light">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <div class="fw-semibold">Other Units (conversion to base)</div>
                    <div class="text-secondary small">Factor = how many <strong>base</strong> units equal <strong>1</strong> of this unit. Example: base <span class="fw-semibold">kg</span>, add <span class="fw-semibold">g</span> with factor <span class="fw-semibold">0.001</span> (1 g = 0.001 kg). Base <span class="fw-semibold">pkt</span> aur 1 pkt = 25 g ho to <span class="fw-semibold">g</span> ka factor <span class="fw-semibold">1÷25 = 0.04</span> (1 g = 0.04 pkt) — yeh sahi hai; BoM/POS mein <strong>25 g</strong> = <strong>1 pkt</strong> ban’ta hai (<code>25 × 0.04</code>).</div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addConversionRow">
                    <i class="bi bi-plus-circle me-1"></i> Add unit
                </button>
            </div>

            <div id="conversionRows">
                @foreach($rows as $row)
                    @php
                        $rowUom = \App\Models\InventoryUnit::normalizeCode((string) ($row['uom'] ?? ''));
                    @endphp
                    <div class="row g-2 align-items-end mb-2" data-conv-row>
                        <div class="col-12 col-md-6">
                            <label class="form-label">UOM</label>
                            @if($uomLib->isEmpty())
                                <input type="text" name="conversions[][uom]" value="{{ $row['uom'] ?? '' }}"
                                       class="form-control @error('conversions.*.uom') is-invalid @enderror" maxlength="30">
                            @else
                                <select name="conversions[][uom]" class="form-select @error('conversions.*.uom') is-invalid @enderror">
                                    <option value="">—</option>
                                    @foreach($uomLib as $unit)
                                        @php $convCode = \App\Models\InventoryUnit::normalizeCode((string) $unit->code); @endphp
                                        <option value="{{ $convCode }}" @selected($rowUom !== '' && $rowUom === $convCode)>{{ $convCode }} — {{ $unit->name }}</option>
                                    @endforeach
                                    @if($rowUom !== '' && !$uomLib->contains(fn ($u) => \App\Models\InventoryUnit::normalizeCode((string) $u->code) === $rowUom))
                                        <option value="{{ $rowUom }}" selected class="text-danger">Legacy: {{ $rowUom }}</option>
                                    @endif
                                </select>
                            @endif
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">Factor to base</label>
                            <input type="number" step="0.001" min="0" name="conversions[][factor_to_base]"
                                   value="{{ $row['factor_to_base'] ?? '' }}"
                                   class="form-control @error('conversions.*.factor_to_base') is-invalid @enderror"
                                   title="1 is unit mein kitne base (e.g. pkt) — 25 g = 1 pkt ke liye g ka factor 1÷25 = 0.04">
                        </div>
                    </div>
                @endforeach
            </div>

            @if(!empty($uomLibraryRules))
                <div class="mt-3 pt-3 border-top">
                    <div class="fw-semibold mb-1">Quick add from unit library</div>
                    <div class="text-secondary small mb-2">Pehle <strong>Base UOM</strong> set karo (jaise <code>kg</code>). Neeche woh rules dikhengi jahan conversion <strong>is base</strong> par khatam hoti hai — click se nayi conversion line add ho jati hai.</div>
                    <div id="uomLibraryQuickAdd" class="d-flex flex-wrap gap-2"></div>
                </div>
            @endif
        </div>
    </div>

    <div class="col-12 col-md-4">
        <label class="form-label">Cost</label>
        <input type="number" step="0.01" min="0" name="cost" id="productCostInput" value="{{ $productCostValue }}"
               class="form-control @error('cost') is-invalid @enderror"
               @if($bomStandardCost !== null && $bomStandardCost > 0) data-bom-driven="1" @endif>
        @if($bomStandardCost !== null && $bomStandardCost > 0)
            <div class="form-text small text-secondary">
                Recipe se auto: <strong>{{ fmt_num($bomStandardCost, 4) }}</strong> — cost recipe se update hoti hai; sale price aap jo likhen wahi save hoti hai
            </div>
        @else
            <div class="form-text small text-secondary">Manual entry — active recipe ho to cost auto set hoti hai.</div>
        @endif
        @error('cost')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    @foreach($extraCostDefinitions as $idx => $extraDef)
        @php
            $extraKey = (string) $extraDef['key'];
            $extraLabel = (string) $extraDef['label'];
            $extraRate = (float) ($extraDef['rate'] ?? 0);
            $extraOperator = (string) ($extraDef['operator'] ?? 'plus');
            $baseLabel = (string) ($extraDef['base_label'] ?? 'Cost');
            $extraValue = old('extra_costs.'.$extraKey, data_get($product->extra_costs ?? [], $extraKey, 0));
            $opBadge = match ($extraOperator) {
                'minus' => '− %',
                'multiply' => '×',
                'divide' => '÷',
                default => '+ %',
            };
            $formulaLine = match ($extraOperator) {
                'minus' => $extraLabel.' = − ('.$baseLabel.' × '.fmt_num($extraRate, 4).'%)',
                'multiply' => $extraLabel.' = '.$baseLabel.' × '.fmt_num($extraRate, 4),
                'divide' => $extraLabel.' = '.$baseLabel.' ÷ '.fmt_num($extraRate, 4),
                default => $extraLabel.' = + ('.$baseLabel.' × '.fmt_num($extraRate, 4).'%)',
            };
        @endphp
        <div class="col-12 col-md-4" data-extra-cost-row>
            <label class="form-label">{{ $extraLabel }} <span class="text-secondary small">({{ $opBadge }})</span></label>
            <input type="number"
                   step="0.01"
                   name="extra_costs[{{ $extraKey }}]"
                   data-extra-cost-input
                   data-extra-cost-key="{{ $extraKey }}"
                   data-extra-cost-rate="{{ $extraRate }}"
                   data-extra-cost-operator="{{ $extraOperator }}"
                   value="{{ $extraValue }}"
                   class="form-control"
                   readonly>
            <div class="form-text small text-secondary">Auto: <strong>{{ $formulaLine }}</strong></div>
        </div>
    @endforeach

    <div class="col-12 col-md-4">
        <label class="form-label">Effective Cost (Auto)</label>
        <input type="number"
               step="0.01"
               id="productEffectiveCostInput"
               data-previous-effective="{{ old('total', isset($product) && $product ? ($product->total ?? 0) : (old('cost', 0))) }}"
               value="{{ old('total', isset($product) && $product ? ($product->total ?? 0) : (old('cost', 0))) }}"
               class="form-control"
               readonly>
        <div class="form-text small text-secondary">Auto formula: <strong>Cost + Settings Cost Fields</strong></div>
    </div>

    <div class="col-12 col-md-4">
        <label class="form-label">Profit (Auto)</label>
        <input type="number"
               step="0.01"
               id="productProfitInput"
               value="{{ old('profit', isset($product) && $product ? ($product->profit ?? 0) : 0) }}"
               class="form-control"
               readonly>
        <div class="form-text small text-secondary">Auto formula: <strong>Sale Price − Effective Cost</strong></div>
    </div>

    <div class="col-12 col-md-4">
        <label class="form-label">Sale Price</label>
        <input type="number" step="0.01" min="0" name="price" id="productSalePriceInput" value="{{ old('price', $product->price ?? 0) }}"
               class="form-control @error('price') is-invalid @enderror">
        @error('price')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    @php
        $showReorderLevel = (bool) old('for_purchase', isset($product) && $product ? $product->for_purchase : true);
    @endphp
    <div class="col-12 col-md-4 @if(!$showReorderLevel) d-none @endif" id="reorderLevelBlock">
        <label class="form-label fw-semibold d-flex align-items-center gap-1">
            Reorder Level
            <span class="badge rounded-pill bg-warning text-dark ms-1" style="font-size:10px;">Low Stock Alert</span>
        </label>
        <div class="input-group">
            <input type="number" step="0.001" min="0" name="reorder_level" id="reorderLevelInput"
                   value="{{ old('reorder_level', $product->reorder_level ?? 0) }}"
                   class="form-control @error('reorder_level') is-invalid @enderror"
                   placeholder="0">
            <span class="input-group-text text-secondary small" id="reorderUomLabel">{{ $baseUomVal !== '' ? $baseUomVal : ($product->uom ?? 'units') }}</span>
        </div>
        <div class="form-text text-secondary" style="font-size:11px;">Purchase / stock items ke liye — stock is qty se neeche aaye to alert. 0 = off.</div>
        @error('reorder_level')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-6">
        <div class="border rounded-3 p-3 bg-white">
            <div class="fw-semibold mb-2">Use in modules</div>
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="forPosSwitch" name="for_pos" value="1"
                       @checked(old('for_pos', isset($product) && $product ? $product->for_pos : true))>
                <label class="form-check-label" for="forPosSwitch">POS par sell karein</label>
                <div class="form-text small text-secondary">Off = product POS search / cart mein nahi dikhega (held bill resume ab bhi chal sakta hai).</div>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="forPurchaseSwitch" name="for_purchase" value="1"
                       @checked(old('for_purchase', isset($product) && $product ? $product->for_purchase : true))>
                <label class="form-check-label" for="forPurchaseSwitch">Purchase / stock alert (reorder)</label>
                <div class="form-text small text-secondary">Off = low-stock report &amp; banner is SKU ko ignore karega; POS par stock check bhi nahi (services / non-stock items).</div>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-4 d-flex align-items-end">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="activeSwitch" name="active" value="1"
                   @checked(old('active', ($product->active ?? true)) ? true : false)>
            <label class="form-check-label" for="activeSwitch">Active</label>
        </div>
    </div>
</div>

<div class="d-flex gap-2 mt-4">
    <button class="btn btn-primary" type="submit">Save</button>
    <a href="{{ $productReturnCancelHref ?? route('inventory.products.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>

<script>
    (function () {
        const container = document.getElementById('conversionRows');
        const unitsJson = @json($uomLib->map(fn ($u) => ['code' => $u->code, 'name' => $u->name])->values());
        const pkgQtyInput = document.querySelector('input[name="package_contents_qty"]');
        const pkgUomInput = document.querySelector('[name="package_contents_uom"]');

        function esc(s) {
            return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
        }

        function norm(s) {
            return String(s || '').trim().toLowerCase();
        }

        function uomSelectHtml(selected) {
            if (!unitsJson.length) {
                return '<input type="text" name="conversions[][uom]" class="form-control" maxlength="30" value="' + esc(selected) + '">';
            }
            let opts = '<option value="">—</option>';
            unitsJson.forEach(u => {
                const code = norm(u.code);
                const sel = selected && norm(selected) === code ? ' selected' : '';
                opts += '<option value="' + esc(code) + '"' + sel + '>' + esc(code) + ' — ' + esc(u.name) + '</option>';
            });
            return '<select name="conversions[][uom]" class="form-select">' + opts + '</select>';
        }

        function appendConversionRow(uom, factor, autoPackage = false) {
            if (!container) return;
            const row = document.createElement('div');
            row.className = 'row g-2 align-items-end mb-2';
            row.setAttribute('data-conv-row', '');
            if (autoPackage) {
                row.setAttribute('data-auto-package', '1');
            }
            row.innerHTML = `
                <div class="col-12 col-md-6">
                    <label class="form-label">UOM</label>
                    ${uomSelectHtml(uom)}
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label">Factor to base</label>
                    <input type="number" step="0.001" min="0" name="conversions[][factor_to_base]" class="form-control">
                </div>
            `;
            container.appendChild(row);
            const facInp = row.querySelector('input[name="conversions[][factor_to_base]"]');
            if (factor != null && factor !== '' && facInp) facInp.value = factor;
            enforceUniqueConversionRows();
            return row;
        }

        function getRowUomInput(row) {
            return row?.querySelector('[name="conversions[][uom]"]') ?? null;
        }

        function getRowFactorInput(row) {
            return row?.querySelector('input[name="conversions[][factor_to_base]"]') ?? null;
        }

        function getNormalizedRowUom(row) {
            const uomInp = getRowUomInput(row);
            return norm(uomInp?.value);
        }

        function enforceUniqueConversionRows() {
            if (!container) return;
            const rows = Array.from(container.querySelectorAll('[data-conv-row]'));
            const seen = new Set();

            rows.forEach((row) => {
                const uom = getNormalizedRowUom(row);
                if (!uom) return;

                if (seen.has(uom)) {
                    if (row.getAttribute('data-auto-package') === '1') {
                        const uomInp = getRowUomInput(row);
                        const facInp = getRowFactorInput(row);
                        if (uomInp) uomInp.value = '';
                        if (facInp) facInp.value = '';
                    } else {
                        row.remove();
                    }
                    return;
                }

                seen.add(uom);
            });
        }

        function findAutoPackageRow() {
            return container?.querySelector('[data-conv-row][data-auto-package="1"]') ?? null;
        }

        function syncPackageConversionRow() {
            if (!container || !baseInput || !pkgQtyInput || !pkgUomInput) return;

            const base = norm(baseInput.value);
            const inner = norm(pkgUomInput.value);
            const qty = parseFloat(pkgQtyInput.value || '0');
            const factor = qty > 0 ? (1 / qty) : 0;

            const existingAuto = findAutoPackageRow();
            const invalid = !base || !inner || inner === base || !Number.isFinite(factor) || factor <= 0;
            if (invalid) {
                existingAuto?.remove();
                return;
            }

            const targetRow = existingAuto || appendConversionRow(inner, factor.toFixed(6), true);
            if (!targetRow) return;

            const uomInp = getRowUomInput(targetRow);
            const facInp = getRowFactorInput(targetRow);
            if (uomInp) {
                uomInp.value = inner;
                if (!unitsJson.length) {
                    uomInp.setAttribute('readonly', 'readonly');
                }
            }
            if (facInp) {
                facInp.value = factor.toFixed(6);
                facInp.setAttribute('readonly', 'readonly');
            }

            // Keep auto row at top for visibility.
            if (container.firstElementChild !== targetRow) {
                container.prepend(targetRow);
            }
            enforceUniqueConversionRows();
        }

        const btn = document.getElementById('addConversionRow');
        if (btn) {
            btn.addEventListener('click', function () { appendConversionRow('', '', false); });
        }
        if (container) {
            container.addEventListener('change', enforceUniqueConversionRows);
            container.addEventListener('input', enforceUniqueConversionRows);
        }

        const rules = @json($uomLibraryRules ?? []);
        const uomLibraryUrl = @json(route('inventory.uom-library.index'));
        const baseInput = document.querySelector('#productBaseUom') || document.querySelector('input[name="uom"]');
        const quickBox = document.getElementById('uomLibraryQuickAdd');
        const reorderLabel = document.getElementById('reorderUomLabel');
        const reorderBlock = document.getElementById('reorderLevelBlock');
        const reorderInput = document.getElementById('reorderLevelInput');
        const forPurchaseSwitch = document.getElementById('forPurchaseSwitch');
        const pkgPerLabel = document.getElementById('pkgPerBaseLabel');

        function syncReorderLevelVisibility() {
            const show = !!forPurchaseSwitch?.checked;
            if (reorderBlock) {
                reorderBlock.classList.toggle('d-none', !show);
            }
            if (reorderInput) {
                reorderInput.disabled = !show;
            }
        }

        if (forPurchaseSwitch) {
            forPurchaseSwitch.addEventListener('change', syncReorderLevelVisibility);
            syncReorderLevelVisibility();
        }

        function syncBaseUomLabels() {
            if (!baseInput) return;
            const v = String(baseInput.value || '').trim();
            if (reorderLabel) reorderLabel.textContent = v || 'units';
            if (pkgPerLabel) pkgPerLabel.textContent = v || 'base';
        }
        if (baseInput) {
            baseInput.addEventListener('change', syncBaseUomLabels);
            baseInput.addEventListener('input', syncBaseUomLabels);
            syncBaseUomLabels();
        }

        if (quickBox && baseInput && Array.isArray(rules) && rules.length) {
            function renderQuick() {
                const base = norm(baseInput.value);
                quickBox.innerHTML = '';
                if (!base) {
                    quickBox.innerHTML = '<span class="text-secondary small">Base UOM choose karo taake library se rules dikhen.</span>';
                    return;
                }
                const hits = rules.filter(r => norm(r.to) === base);
                if (!hits.length) {
                    quickBox.innerHTML = '<span class="text-secondary small">Is base ke liye koi rule nahi. <a href="' + uomLibraryUrl + '">Inventory → Units</a> par rule add karo.</span>';
                    return;
                }
                hits.forEach(r => {
                    const b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'btn btn-sm btn-outline-primary';
                    b.textContent = '+ ' + r.from + ' → ' + r.to + ' (×' + String(r.factor) + ')';
                    b.addEventListener('click', () => appendConversionRow(norm(r.from), r.factor, false));
                    quickBox.appendChild(b);
                });
            }
            baseInput.addEventListener('input', renderQuick);
            baseInput.addEventListener('change', renderQuick);
            renderQuick();
        }

        if (pkgQtyInput) {
            pkgQtyInput.addEventListener('input', syncPackageConversionRow);
            pkgQtyInput.addEventListener('change', syncPackageConversionRow);
        }
        if (pkgUomInput) {
            pkgUomInput.addEventListener('input', syncPackageConversionRow);
            pkgUomInput.addEventListener('change', syncPackageConversionRow);
        }
        if (baseInput) {
            baseInput.addEventListener('input', syncPackageConversionRow);
            baseInput.addEventListener('change', syncPackageConversionRow);
        }
        syncPackageConversionRow();
        enforceUniqueConversionRows();

        const costInput = document.getElementById('productCostInput');
        const effectiveCostInput = document.getElementById('productEffectiveCostInput');
        const profitInput = document.getElementById('productProfitInput');
        const priceInput = document.getElementById('productSalePriceInput');
        const costFieldDefs = @json($extraCostDefinitionsForJs);
        const bomStandardCost = @json($bomStandardCost);
        const bomDrivenCost = costInput?.dataset.bomDriven === '1';
        const hasPriceTargetRule = (costFieldDefs || []).some((def) => def.target === 'price');
        if (priceInput && hasPriceTargetRule && !priceInput.dataset.basePrice) {
            priceInput.dataset.basePrice = priceInput.value || '0';
        }

        function syncCostingFields(recalcSalePrice = false) {
            if (!costInput) return;
            const cost = parseFloat(costInput.value || '0');
            const safeCost = Number.isFinite(cost) && cost > 0 ? cost : 0;
            const sourcePriceRaw = hasPriceTargetRule
                ? (priceInput?.dataset.basePrice ?? priceInput?.value ?? '0')
                : (priceInput?.value ?? '0');
            const price = parseFloat(sourcePriceRaw);
            const safePrice = Number.isFinite(price) && price > 0 ? price : 0;
            const amounts = {};
            let runningEffective = safeCost;
            let runningPrice = safePrice;
            let extraTotal = 0;
            (costFieldDefs || []).forEach((def) => {
                let baseVal = safeCost;
                if (def.base === 'effective_cost') {
                    baseVal = runningEffective;
                } else if (def.base === 'price') {
                    baseVal = runningPrice;
                } else if (def.base !== 'cost') {
                    baseVal = amounts[def.base] || 0;
                }
                const rate = parseFloat(def.rate) || 0;
                let value = 0;
                switch (def.operator) {
                    case 'minus':
                        value = -baseVal * (rate / 100);
                        break;
                    case 'multiply':
                        value = baseVal * rate;
                        break;
                    case 'divide':
                        value = rate > 0 ? baseVal / rate : 0;
                        break;
                    default:
                        value = baseVal * (rate / 100);
                }
                value = Math.round(value * 100) / 100;
                amounts[def.key] = value;
                if (def.target === 'price') {
                    runningPrice += value;
                } else {
                    extraTotal += value;
                    runningEffective += value;
                }
                const extraInput = document.querySelector(`input[name="extra_costs[${def.key}]"]`);
                if (extraInput) {
                    extraInput.value = value.toFixed(2);
                }
            });
            if (effectiveCostInput) {
                effectiveCostInput.value = (safeCost + extraTotal).toFixed(2);
            }
            if (priceInput && hasPriceTargetRule && recalcSalePrice) {
                priceInput.value = runningPrice.toFixed(2);
            }
            const finalEffective = safeCost + extraTotal;
            const finalPrice = priceInput ? (parseFloat(priceInput.value || '0') || 0) : runningPrice;
            if (profitInput) {
                profitInput.value = (finalPrice - finalEffective).toFixed(2);
            }
        }

        function applyRecipeMarkupToPrice() {
            if (!bomDrivenCost || !(bomStandardCost > 0) || !priceInput || hasPriceTargetRule || !effectiveCostInput) {
                return;
            }
            const eff = parseFloat(effectiveCostInput.value || '0') || 0;
            const oldEff = parseFloat(effectiveCostInput.dataset.previousEffective || '0') || 0;
            const oldPrice = parseFloat(priceInput.dataset.basePrice || priceInput.value || '0') || 0;
            const markup = Math.max(oldPrice - oldEff, 0);
            priceInput.value = (eff + markup).toFixed(2);
            if (profitInput) {
                const sale = parseFloat(priceInput.value || '0') || 0;
                profitInput.value = (sale - eff).toFixed(2);
            }
        }

        if (costInput) {
            if (bomDrivenCost && bomStandardCost > 0) {
                costInput.value = Number(bomStandardCost).toFixed(2);
            }
            if (priceInput) {
                priceInput.dataset.basePrice = priceInput.value || '0';
            }
            costInput.addEventListener('input', () => {
                syncCostingFields(true);
                applyRecipeMarkupToPrice();
            });
            costInput.addEventListener('change', () => {
                syncCostingFields(true);
                applyRecipeMarkupToPrice();
            });
            syncCostingFields(false);
        }
        if (priceInput) {
            const updatePriceBase = () => {
                if (!hasPriceTargetRule) return;
                priceInput.dataset.basePrice = priceInput.value || '0';
            };
            priceInput.addEventListener('focus', updatePriceBase);
            priceInput.addEventListener('input', updatePriceBase);
            priceInput.addEventListener('change', updatePriceBase);
            priceInput.addEventListener('input', () => syncCostingFields(false));
            priceInput.addEventListener('change', () => syncCostingFields(false));
        }
    })();

    (function initProductCategorySelects() {
        const parentSelect = document.getElementById('productParentCategory');
        const subSelect = document.getElementById('productSubCategory');
        if (!parentSelect || !subSelect) return;

        const subcategoriesByParent = @json($subcategoriesByParent);
        const initialParent = @json($selectedParentCategoryId !== '' && $selectedParentCategoryId !== null ? (string) $selectedParentCategoryId : '');
        const initialSub = @json($selectedSubCategoryId !== '' && $selectedSubCategoryId !== null ? (string) $selectedSubCategoryId : '');

        function fillSubcategories(parentId, selectedSubId) {
            subSelect.innerHTML = '<option value="">—</option>';
            const list = parentId ? (subcategoriesByParent[parentId] || subcategoriesByParent[String(parentId)] || []) : [];
            if (!parentId || list.length === 0) {
                subSelect.disabled = true;
                return;
            }
            subSelect.disabled = false;
            list.forEach((row) => {
                const opt = document.createElement('option');
                opt.value = String(row.id);
                opt.textContent = row.name;
                if (selectedSubId && String(row.id) === String(selectedSubId)) {
                    opt.selected = true;
                }
                subSelect.appendChild(opt);
            });
        }

        parentSelect.addEventListener('change', () => {
            fillSubcategories(parentSelect.value, '');
        });

        fillSubcategories(initialParent, initialSub);
    })();

    (function initProductImagePreview() {
        const input = document.getElementById('productImageInput');
        const preview = document.getElementById('productImagePreview');
        const placeholder = document.getElementById('productImagePlaceholder');
        const removeCb = document.getElementById('removeProductImage');
        if (!input || !preview) return;

        input.addEventListener('change', () => {
            const file = input.files && input.files[0];
            if (!file) return;
            const url = URL.createObjectURL(file);
            preview.src = url;
            preview.classList.remove('d-none');
            placeholder?.classList.add('d-none');
            if (removeCb) removeCb.checked = false;
        });

        removeCb?.addEventListener('change', () => {
            if (!removeCb.checked) return;
            preview.src = '';
            preview.classList.add('d-none');
            placeholder?.classList.remove('d-none');
            input.value = '';
        });
    })();
</script>

