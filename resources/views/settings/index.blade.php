@extends('layouts.admin')
@section('title', 'Settings — ' . config('app.name'))

@section('content')
<div class="mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Settings</h4>
        <div class="text-secondary small">Company, finance, module behaviour, POS & system preferences</div>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>{{ session('status') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if ($errors->has('backup'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i>{{ $errors->first('backup') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<form id="settingsMainForm" method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data">
@csrf
@method('PUT')

@if ($errors->any())
    <div class="alert alert-danger">
        <div class="fw-semibold mb-1">Settings save failed:</div>
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Tab nav --}}
<ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-company" type="button"><i class="bi bi-building me-1"></i> Company</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-finance" type="button"><i class="bi bi-cash-coin me-1"></i> Finance & Tax</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-modules" type="button"><i class="bi bi-grid-3x3-gap me-1"></i> Modules</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-pos" type="button"><i class="bi bi-shop-window me-1"></i> POS</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-system" type="button"><i class="bi bi-gear me-1"></i> System</button></li>
</ul>

<div class="tab-content">

    {{-- ── Company Tab ── --}}
    <div class="tab-pane fade show active" id="tab-company">
        <div class="row g-4">

            {{-- Logo card --}}
            <div class="col-12 col-lg-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            @if (!empty($settings['company_logo']))
                                <img src="{{ Storage::url($settings['company_logo']) }}"
                                     id="logoPreview"
                                     class="img-fluid rounded-3 border"
                                     style="max-height:120px; object-fit:contain;"
                                     alt="Logo">
                            @else
                                <div id="logoPreview" class="d-inline-flex align-items-center justify-content-center bg-light rounded-3 border"
                                     style="width:120px;height:120px;">
                                    <i class="bi bi-building fs-1 text-secondary"></i>
                                </div>
                            @endif
                        </div>
                        <label class="btn btn-outline-primary btn-sm" for="company_logo_input">
                            <i class="bi bi-upload me-1"></i> Upload Logo
                        </label>
                        <input type="file" id="company_logo_input" name="company_logo"
                               class="d-none" accept="image/*"
                               onchange="previewLogo(this)">
                        <div class="text-secondary small mt-2">PNG, JPG, SVG — max 1 MB</div>
                    </div>
                </div>
            </div>

            {{-- Company details card --}}
            <div class="col-12 col-lg-9">
                <div class="card shadow-sm">
                    <div class="card-header bg-white fw-semibold">Company Information</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Company Name <span class="text-danger">*</span></label>
                                <input type="text" name="company_name" class="form-control @error('company_name') is-invalid @enderror"
                                       value="{{ old('company_name', $settings['company_name']) }}" required>
                                @error('company_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tagline / Slogan</label>
                                <input type="text" name="company_tagline" class="form-control"
                                       value="{{ old('company_tagline', $settings['company_tagline']) }}"
                                       placeholder="e.g. Your trusted partner">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <textarea name="company_address" class="form-control" rows="2"
                                          placeholder="Street, City, Country">{{ old('company_address', $settings['company_address']) }}</textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Phone</label>
                                <input type="text" name="company_phone" class="form-control"
                                       value="{{ old('company_phone', $settings['company_phone']) }}"
                                       placeholder="+92 300 0000000">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Email</label>
                                <input type="email" name="company_email" class="form-control"
                                       value="{{ old('company_email', $settings['company_email']) }}"
                                       placeholder="info@company.com">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Website</label>
                                <input type="text" name="company_website" class="form-control"
                                       value="{{ old('company_website', $settings['company_website']) }}"
                                       placeholder="https://company.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">NTN / Tax ID</label>
                                <input type="text" name="company_ntn" class="form-control"
                                       value="{{ old('company_ntn', $settings['company_ntn']) }}"
                                       placeholder="e.g. 1234567-8">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">STRN / Sales Tax Reg. No.</label>
                                <input type="text" name="company_strn" class="form-control"
                                       value="{{ old('company_strn', $settings['company_strn']) }}"
                                       placeholder="e.g. 03-99-9999-999-17">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- ── Finance & Tax Tab ── --}}
    <div class="tab-pane fade" id="tab-finance">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">Currency & Tax</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Currency Symbol <span class="text-danger">*</span></label>
                        <input type="text" name="currency_symbol" class="form-control"
                               value="{{ old('currency_symbol', $settings['currency_symbol']) }}"
                               placeholder="Rs.">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Currency Code</label>
                        <input type="text" name="currency_code" class="form-control"
                               value="{{ old('currency_code', $settings['currency_code']) }}"
                               placeholder="PKR">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Symbol Position</label>
                        <select name="currency_position" class="form-select">
                            <option value="before" {{ $settings['currency_position']==='before' ? 'selected' : '' }}>Before amount (Rs. 100)</option>
                            <option value="after"  {{ $settings['currency_position']==='after'  ? 'selected' : '' }}>After amount (100 Rs.)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tax Label</label>
                        <input type="text" name="tax_label" class="form-control"
                               value="{{ old('tax_label', $settings['tax_label']) }}"
                               placeholder="GST">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Default Tax Rate (%)</label>
                        <div class="input-group">
                            <input type="number" name="tax_rate" class="form-control" step="0.01" min="0" max="100"
                                   value="{{ old('tax_rate', $settings['tax_rate']) }}">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fiscal Year Start (MM-DD)</label>
                        <input type="text" name="fiscal_year_start" class="form-control"
                               value="{{ old('fiscal_year_start', $settings['fiscal_year_start']) }}"
                               placeholder="01-01" maxlength="5">
                        <div class="form-text">e.g. 07-01 for July 1</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-4">
            <div class="card-header bg-white fw-semibold">Document Numbering</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Invoice Prefix</label>
                        <input type="text" name="invoice_prefix" class="form-control"
                               value="{{ old('invoice_prefix', $settings['invoice_prefix']) }}"
                               placeholder="INV-">
                        <div class="form-text">e.g. INV-0001</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Purchase Order Prefix</label>
                        <input type="text" name="po_prefix" class="form-control"
                               value="{{ old('po_prefix', $settings['po_prefix']) }}"
                               placeholder="PO-">
                        <div class="form-text">e.g. PO-0001</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Modules Tab (Inventory, Purchase, Manufacturing, Expenses, Employees) ── --}}
    <div class="tab-pane fade" id="tab-modules">
        @php
            $modChecked = fn (string $key) => (string) old($key, $settings[$key] ?? '0') === '1';
        @endphp
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-box-seam me-1"></i> Inventory</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="inventory_products_per_page">Products — rows per page</label>
                                <input type="number" class="form-control" id="inventory_products_per_page" name="inventory_products_per_page" min="5" max="100" required
                                       value="{{ old('inventory_products_per_page', $settings['inventory_products_per_page'] ?? 20) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="inventory_moves_per_page">Stock moves — rows per page</label>
                                <input type="number" class="form-control" id="inventory_moves_per_page" name="inventory_moves_per_page" min="5" max="100" required
                                       value="{{ old('inventory_moves_per_page', $settings['inventory_moves_per_page'] ?? 25) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="inventory_categories_per_page">Categories — rows per page</label>
                                <input type="number" class="form-control" id="inventory_categories_per_page" name="inventory_categories_per_page" min="5" max="100" required
                                       value="{{ old('inventory_categories_per_page', $settings['inventory_categories_per_page'] ?? 20) }}">
                            </div>
                        </div>
                        <div class="form-check mt-3">
                            <input type="hidden" name="inventory_show_low_stock_banner" value="0">
                            <input class="form-check-input" type="checkbox" name="inventory_show_low_stock_banner" value="1" id="inventory_show_low_stock_banner" @checked($modChecked('inventory_show_low_stock_banner'))>
                            <label class="form-check-label" for="inventory_show_low_stock_banner">Show low stock alert banner on Products</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-cart-dash me-1"></i> Purchase</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="purchase_orders_per_page">Purchase orders — rows per page</label>
                                <input type="number" class="form-control" id="purchase_orders_per_page" name="purchase_orders_per_page" min="5" max="100" required
                                       value="{{ old('purchase_orders_per_page', $settings['purchase_orders_per_page'] ?? 20) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="purchase_vendors_per_page">Vendors — rows per page</label>
                                <input type="number" class="form-control" id="purchase_vendors_per_page" name="purchase_vendors_per_page" min="5" max="100" required
                                       value="{{ old('purchase_vendors_per_page', $settings['purchase_vendors_per_page'] ?? 20) }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-gear-wide-connected me-1"></i> Manufacturing</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="manufacturing_boms_per_page">BoMs — rows per page</label>
                                <input type="number" class="form-control" id="manufacturing_boms_per_page" name="manufacturing_boms_per_page" min="5" max="100" required
                                       value="{{ old('manufacturing_boms_per_page', $settings['manufacturing_boms_per_page'] ?? 20) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="manufacturing_orders_per_page">Production orders — rows per page</label>
                                <input type="number" class="form-control" id="manufacturing_orders_per_page" name="manufacturing_orders_per_page" min="5" max="100" required
                                       value="{{ old('manufacturing_orders_per_page', $settings['manufacturing_orders_per_page'] ?? 25) }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-receipt me-1"></i> Expenses</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="expenses_per_page">Expense list — rows per page</label>
                                <input type="number" class="form-control" id="expenses_per_page" name="expenses_per_page" min="5" max="100" required
                                       value="{{ old('expenses_per_page', $settings['expenses_per_page'] ?? 25) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="expenses_categories_per_page">Categories (admin) — rows per page</label>
                                <input type="number" class="form-control" id="expenses_categories_per_page" name="expenses_categories_per_page" min="5" max="100" required
                                       value="{{ old('expenses_categories_per_page', $settings['expenses_categories_per_page'] ?? 20) }}">
                            </div>
                        </div>
                        <div class="form-check mt-3">
                            <input type="hidden" name="expenses_require_receipt_on_submit" value="0">
                            <input class="form-check-input" type="checkbox" name="expenses_require_receipt_on_submit" value="1" id="expenses_require_receipt_on_submit" @checked($modChecked('expenses_require_receipt_on_submit'))>
                            <label class="form-check-label" for="expenses_require_receipt_on_submit">Require receipt attachment before submitting for approval</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-journal-bookmark me-1"></i> Accounts</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="accounts_per_page">Chart of accounts — rows per page</label>
                                <input type="number" class="form-control" id="accounts_per_page" name="accounts_per_page" min="5" max="100" required
                                       value="{{ old('accounts_per_page', $settings['accounts_per_page'] ?? 25) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="accounts_journal_per_page">Journal entries — rows per page</label>
                                <input type="number" class="form-control" id="accounts_journal_per_page" name="accounts_journal_per_page" min="5" max="100" required
                                       value="{{ old('accounts_journal_per_page', $settings['accounts_journal_per_page'] ?? 25) }}">
                            </div>
                        </div>
                        <div class="form-check mt-3">
                            <input type="hidden" name="accounts_auto_journal" value="0">
                            <input class="form-check-input" type="checkbox" name="accounts_auto_journal" value="1" id="accounts_auto_journal" @checked($modChecked('accounts_auto_journal'))>
                            <label class="form-check-label" for="accounts_auto_journal">Auto-post journal entries from POS, Expenses, Purchase &amp; Payroll</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-people me-1"></i> HR</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="employees_per_page">Employees — rows per page</label>
                                <input type="number" class="form-control" id="employees_per_page" name="employees_per_page" min="5" max="100" required
                                       value="{{ old('employees_per_page', $settings['employees_per_page'] ?? 20) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="employees_ref_per_page">Departments &amp; designations — rows per page</label>
                                <input type="number" class="form-control" id="employees_ref_per_page" name="employees_ref_per_page" min="5" max="100" required
                                       value="{{ old('employees_ref_per_page', $settings['employees_ref_per_page'] ?? 20) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="hr_leave_per_page">Leave requests — rows per page</label>
                                <input type="number" class="form-control" id="hr_leave_per_page" name="hr_leave_per_page" min="5" max="100" required
                                       value="{{ old('hr_leave_per_page', $settings['hr_leave_per_page'] ?? 20) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="hr_annual_leave_days">Annual leave days per employee (0 = no limit)</label>
                                <input type="number" class="form-control" id="hr_annual_leave_days" name="hr_annual_leave_days" min="0" max="365" required
                                       value="{{ old('hr_annual_leave_days', $settings['hr_annual_leave_days'] ?? 14) }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-white fw-semibold"><i class="bi bi-calculator me-1"></i> Product Cost Fields</div>
                    <div class="card-body">
                        <p class="text-secondary small mb-3">
                            Har line = <strong>ik nayi cost field</strong>. Pehle <strong>Base</strong> chunein (Cost, Effective Cost, Sale Price ya upar wali koi field), phir
                            <strong>Add % / Subtract % / Multiply / Divide</strong>. Add/Subtract mein rate <strong>% of base</strong> hai;
                            Multiply/Divide mein rate <strong>seedha number</strong> hai (e.g. base × 1.5, ya base ÷ 2).
                        </p>
                        @php
                            $productExtraCostFields = old('product_extra_cost_fields');
                            if (!is_array($productExtraCostFields)) {
                                $decodedProductFields = json_decode((string) ($settings['product_extra_cost_fields'] ?? '[]'), true);
                                $productExtraCostFields = is_array($decodedProductFields) ? $decodedProductFields : [];
                                foreach ($productExtraCostFields as $i => &$__pecRow) {
                                    $b = $__pecRow['base'] ?? 'cost';
                                    $__bi = -1;
                                    if ($b === 'effective_cost') {
                                        $__bi = -2;
                                    } elseif ($b === 'price') {
                                        $__bi = -3;
                                    } elseif ($b !== 'cost') {
                                        for ($j = 0; $j < $i; $j++) {
                                            if (($productExtraCostFields[$j]['key'] ?? '') === $b) {
                                                $__bi = $j;
                                                break;
                                            }
                                        }
                                    }
                                    $__pecRow['base_index'] = $__bi;
                                }
                                unset($__pecRow);
                            }
                            if (empty($productExtraCostFields)) {
                                $productExtraCostFields = [['label' => '', 'operator' => 'plus', 'rate' => '', 'calculate_to' => 'effective_cost', 'base_index' => -1]];
                            }
                        @endphp

                        @if ($errors->has('product_extra_cost_fields') || $errors->has('product_extra_cost_fields.*.label') || $errors->has('product_extra_cost_fields.*.rate') || $errors->has('product_extra_cost_fields.*.operator') || $errors->has('product_extra_cost_fields.*.calculate_to') || $errors->has('product_extra_cost_fields.*.base_index'))
                            <div class="alert alert-danger py-2 small">
                                Product cost fields check karein (calculate from, effect, calculate to, rate).
                            </div>
                        @endif

                        <div id="productCostFieldsRows">
                            @foreach ($productExtraCostFields as $idx => $row)
                                @php
                                    $operatorVal = (string) ($row['operator'] ?? 'plus');
                                    if (! in_array($operatorVal, ['plus', 'minus', 'multiply', 'divide'], true)) {
                                        $operatorVal = 'plus';
                                    }
                                    $baseIndexVal = (int) ($row['base_index'] ?? -1);
                                    if ($baseIndexVal < -3) {
                                        $baseIndexVal = -1;
                                    }
                                    if ($baseIndexVal >= $idx) {
                                        $baseIndexVal = -1;
                                    }
                                    $targetVal = (string) ($row['calculate_to'] ?? ($row['target'] ?? 'effective_cost'));
                                    if (! in_array($targetVal, ['effective_cost', 'price'], true)) {
                                        $targetVal = 'effective_cost';
                                    }
                                @endphp
                                <div class="row g-2 align-items-end mb-2 product-cost-field-row">
                                    <div class="col-md-2">
                                        <label class="form-label">Field Label</label>
                                        <input type="text"
                                               class="form-control"
                                               data-name="label"
                                               name="product_extra_cost_fields[{{ $idx }}][label]"
                                               value="{{ $row['label'] ?? '' }}"
                                               placeholder="e.g. Gas Charges">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Calculate from</label>
                                        <select class="form-select"
                                                data-name="base_index"
                                                name="product_extra_cost_fields[{{ $idx }}][base_index]">
                                            <option value="-1" {{ $baseIndexVal === -1 ? 'selected' : '' }}>Base Cost</option>
                                            <option value="-2" {{ $baseIndexVal === -2 ? 'selected' : '' }}>Effective Cost (Auto)</option>
                                            <option value="-3" {{ $baseIndexVal === -3 ? 'selected' : '' }}>Sale Price</option>
                                            @for ($j = 0; $j < $idx; $j++)
                                                @php
                                                    $prevLab = trim((string) ($productExtraCostFields[$j]['label'] ?? ''));
                                                    if ($prevLab === '') {
                                                        $prevLab = 'Field '.($j + 1);
                                                    }
                                                @endphp
                                                <option value="{{ $j }}" {{ $baseIndexVal === $j ? 'selected' : '' }}>{{ $prevLab }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Effect</label>
                                        <select class="form-select"
                                                data-name="operator"
                                                name="product_extra_cost_fields[{{ $idx }}][operator]">
                                            <option value="plus" {{ $operatorVal === 'plus' ? 'selected' : '' }}>+ Add % of base</option>
                                            <option value="minus" {{ $operatorVal === 'minus' ? 'selected' : '' }}>− Subtract % of base</option>
                                            <option value="multiply" {{ $operatorVal === 'multiply' ? 'selected' : '' }}>× Multiply base</option>
                                            <option value="divide" {{ $operatorVal === 'divide' ? 'selected' : '' }}>÷ Divide base</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Calculate To</label>
                                        <select class="form-select"
                                                data-name="calculate_to"
                                                name="product_extra_cost_fields[{{ $idx }}][calculate_to]">
                                            <option value="effective_cost" {{ $targetVal === 'effective_cost' ? 'selected' : '' }}>Effective Cost</option>
                                            <option value="price" {{ $targetVal === 'price' ? 'selected' : '' }}>Sale Price</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">% of base (optional)</label>
                                        <div class="input-group">
                                            <input type="number"
                                                   step="0.0001"
                                                   min="0"
                                                   class="form-control"
                                                   data-name="rate"
                                                   name="product_extra_cost_fields[{{ $idx }}][rate]"
                                                   value="{{ $row['rate'] ?? '' }}"
                                                   placeholder="e.g. 20">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-outline-danger w-100" data-remove-product-cost-field>
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <button type="button" class="btn btn-sm btn-outline-primary mt-1" id="addProductCostFieldBtn">
                            <i class="bi bi-plus-circle me-1"></i> Add Cost Field
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── POS Tab ── --}}
    <div class="tab-pane fade" id="tab-pos">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">Point of Sale</div>
            <div class="card-body">
                <p class="text-secondary small mb-4">These options apply to the POS screen and receipt behaviour for all users.</p>
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="fw-semibold mb-3">Receipt</div>
                        @php
                            $posChecked = fn (string $key) => (string) old($key, $settings[$key] ?? '0') === '1';
                        @endphp
                        <div class="form-check mb-2">
                            <input type="hidden" name="pos_open_receipt_after_sale" value="0">
                            <input class="form-check-input" type="checkbox" name="pos_open_receipt_after_sale" value="1" id="pos_open_receipt_after_sale" @checked($posChecked('pos_open_receipt_after_sale'))>
                            <label class="form-check-label" for="pos_open_receipt_after_sale">Open receipt page after successful sale</label>
                        </div>
                        <div class="form-text ms-4 mb-3">When off, POS returns to the register with a success message; you can still open the last receipt from the alert link.</div>
                        <div class="form-check mb-2">
                            <input type="hidden" name="pos_auto_print_receipt" value="0">
                            <input class="form-check-input" type="checkbox" name="pos_auto_print_receipt" value="1" id="pos_auto_print_receipt" @checked($posChecked('pos_auto_print_receipt'))>
                            <label class="form-check-label" for="pos_auto_print_receipt">Auto-trigger print dialog on receipt page</label>
                        </div>
                        <div class="form-text ms-4 mb-3">Uses the browser print dialog (thermal printer if configured as default).</div>
                        <div class="form-check mb-2">
                            <input type="hidden" name="pos_allow_bill_print" value="0">
                            <input class="form-check-input" type="checkbox" name="pos_allow_bill_print" value="1" id="pos_allow_bill_print" @checked($posChecked('pos_allow_bill_print'))>
                            <label class="form-check-label" for="pos_allow_bill_print">Allow bill print before payment and re-print after payment</label>
                        </div>
                        <div class="form-text ms-4 mb-3">When off, POS hides print options before checkout and the manual print button on paid receipt.</div>
                        <div class="form-check mb-2">
                            <input type="hidden" name="pos_enable_tables" value="0">
                            <input class="form-check-input" type="checkbox" name="pos_enable_tables" value="1" id="pos_enable_tables" @checked($posChecked('pos_enable_tables'))>
                            <label class="form-check-label" for="pos_enable_tables">Enable restaurant tables (Table No on POS)</label>
                        </div>
                        <div class="form-text ms-4 mb-3">When off, table selection is hidden from POS. Pehle sitting area banayein, phir us ke under table numbers add karein.</div>
                        <div class="border rounded-3 p-3 mb-3 bg-light" id="posTablesManageBox">
                            <div class="fw-semibold mb-2">Sitting areas &amp; tables</div>

                            <div class="d-flex flex-wrap gap-2 mb-3 align-items-end">
                                <div>
                                    <label class="form-label small mb-1" for="posSittingAreaName">1. Sitting area name</label>
                                    <input form="posSittingAreaAddForm" type="text" name="name" id="posSittingAreaName" class="form-control form-control-sm" style="max-width:16rem;" placeholder="e.g. VIP Hall, Garden, Family" maxlength="80" @disabled((string) ($settings['pos_enable_tables'] ?? '0') !== '1')>
                                </div>
                                <button form="posSittingAreaAddForm" type="submit" class="btn btn-sm btn-primary" @disabled((string) ($settings['pos_enable_tables'] ?? '0') !== '1')>Add sitting area</button>
                            </div>
                            @error('pos_sitting_area')
                                <div class="text-danger small mb-2">{{ $message }}</div>
                            @enderror
                            @error('name')
                                @if(request()->routeIs('settings.pos-sitting-areas.*') || old('_form') === 'sitting_area')
                                    <div class="text-danger small mb-2">{{ $message }}</div>
                                @endif
                            @enderror

                            @forelse(($posSittingAreas ?? []) as $area)
                                <div class="border rounded-3 bg-white p-3 mb-2">
                                    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                        <div class="fw-semibold">{{ $area->name }}</div>
                                        <button type="submit" form="posSittingAreaDeleteForm-{{ $area->id }}" class="btn btn-sm btn-outline-danger" @disabled((string) ($settings['pos_enable_tables'] ?? '0') !== '1') onclick="return confirm('Delete sitting area {{ $area->name }}?');">Delete area</button>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2 mb-2 align-items-end">
                                        <div>
                                            <label class="form-label small mb-1">2. Table No. for {{ $area->name }}</label>
                                            <input form="posTableAddForm-{{ $area->id }}" type="text" name="name" class="form-control form-control-sm" style="max-width:12rem;" placeholder="e.g. T1" maxlength="60" @disabled((string) ($settings['pos_enable_tables'] ?? '0') !== '1')>
                                            <input form="posTableAddForm-{{ $area->id }}" type="hidden" name="sitting_area_id" value="{{ $area->id }}">
                                        </div>
                                        <button form="posTableAddForm-{{ $area->id }}" type="submit" class="btn btn-sm btn-outline-primary" @disabled((string) ($settings['pos_enable_tables'] ?? '0') !== '1')>Add table</button>
                                    </div>
                                    <div class="d-flex flex-wrap gap-1">
                                        @forelse($area->tables as $t)
                                            <span class="badge rounded-pill bg-white border text-dark fw-semibold d-inline-flex align-items-center gap-1 py-1 px-2">
                                                {{ $t->name }}
                                                <button type="submit" form="posTableDeleteForm-{{ $t->id }}" class="btn btn-sm btn-link text-danger p-0 lh-1" title="Delete table" @disabled((string) ($settings['pos_enable_tables'] ?? '0') !== '1') onclick="return confirm('Delete table {{ $t->name }}?');">×</button>
                                            </span>
                                        @empty
                                            <span class="text-secondary small">Is area mein abhi koi table nahi.</span>
                                        @endforelse
                                    </div>
                                </div>
                            @empty
                                <span class="text-secondary small">Pehle sitting area add karein (e.g. Hall A, Outdoor), phir us ke under tables.</span>
                            @endforelse
                            @error('pos_table')
                                <div class="text-danger small mt-2">{{ $message }}</div>
                            @enderror
                        </div>
                        <label class="form-label" for="pos_receipt_footer_note">Extra line on receipt (optional)</label>
                        <textarea name="pos_receipt_footer_note" id="pos_receipt_footer_note" class="form-control" rows="2" maxlength="240" placeholder="e.g. Returns within 7 days with receipt">{{ old('pos_receipt_footer_note', $settings['pos_receipt_footer_note'] ?? '') }}</textarea>
                    </div>
                    <div class="col-lg-6">
                        <div class="fw-semibold mb-3">POS screen</div>
                        <div class="form-check mb-2">
                            <input type="hidden" name="pos_show_cash_movements" value="0">
                            <input class="form-check-input" type="checkbox" name="pos_show_cash_movements" value="1" id="pos_show_cash_movements" @checked($posChecked('pos_show_cash_movements'))>
                            <label class="form-check-label" for="pos_show_cash_movements">Show Cash In / Out</label>
                        </div>
                        <div class="form-check mb-2">
                            <input type="hidden" name="pos_show_held_orders" value="0">
                            <input class="form-check-input" type="checkbox" name="pos_show_held_orders" value="1" id="pos_show_held_orders" @checked($posChecked('pos_show_held_orders'))>
                            <label class="form-check-label" for="pos_show_held_orders">Show Held Orders list</label>
                        </div>
                        <div class="form-check mb-2">
                            <input type="hidden" name="pos_show_customer_section" value="0">
                            <input class="form-check-input" type="checkbox" name="pos_show_customer_section" value="1" id="pos_show_customer_section" @checked($posChecked('pos_show_customer_section'))>
                            <label class="form-check-label" for="pos_show_customer_section">Show Customer / Credit sale section</label>
                        </div>
                        <div class="form-check mb-2">
                            <input type="hidden" name="pos_hold_only" value="0">
                            <input class="form-check-input" type="checkbox" name="pos_hold_only" value="1" id="pos_hold_only" @checked($posChecked('pos_hold_only'))>
                            <label class="form-check-label" for="pos_hold_only">Legacy: hide Pay Now on POS (not recommended — use Hold + Pay Now together)</label>
                        </div>
                        <div class="form-text ms-4 mb-2">Jab ON ho: POS par sirf Hold Order; Pay Now band.</div>
                        <div class="form-check mb-2">
                            <input type="hidden" name="pos_show_hold_button" value="0">
                            <input class="form-check-input" type="checkbox" name="pos_show_hold_button" value="1" id="pos_show_hold_button" @checked($posChecked('pos_show_hold_button'))>
                            <label class="form-check-label" for="pos_show_hold_button">Show Hold Order button</label>
                        </div>
                        <div class="form-check mb-2">
                            <input type="hidden" name="pos_show_refund_toggle" value="0">
                            <input class="form-check-input" type="checkbox" name="pos_show_refund_toggle" value="1" id="pos_show_refund_toggle" @checked($posChecked('pos_show_refund_toggle'))>
                            <label class="form-check-label" for="pos_show_refund_toggle">Show Refund mode toggle</label>
                        </div>
                        <div class="form-check mb-2">
                            <input type="hidden" name="pos_show_discount" value="0">
                            <input class="form-check-input" type="checkbox" name="pos_show_discount" value="1" id="pos_show_discount" @checked($posChecked('pos_show_discount'))>
                            <label class="form-check-label" for="pos_show_discount">Show discount (profit %) column on cart</label>
                        </div>
                        <div class="form-text ms-4 mb-3">When off, the POS hides discount controls and no line discount is applied.</div>
                        <div class="mb-2">
                            <label class="form-label" for="pos_tax_mode">Tax on POS</label>
                            @php
                                $posTaxMode = old('pos_tax_mode', $settings['pos_tax_mode'] ?? 'line');
                            @endphp
                            <select name="pos_tax_mode" id="pos_tax_mode" class="form-select">
                                <option value="off" {{ $posTaxMode === 'off' ? 'selected' : '' }}>Off — no tax fields or tax on totals</option>
                                <option value="line" {{ $posTaxMode === 'line' ? 'selected' : '' }}>Per line — tax % on each cart line (default % from Finance &amp; Tax → Default Tax Rate)</option>
                                <option value="bill" {{ $posTaxMode === 'bill' ? 'selected' : '' }}>Whole bill — one tax % on net (after line discounts)</option>
                            </select>
                            <div class="form-text">Line mode is per product/line. Bill mode applies one rate to subtotal − discounts. Default percentages use <strong>Finance &amp; Tax</strong> → Default Tax Rate unless changed on the register.</div>
                        </div>
                        <div class="form-check mb-2">
                            <input type="hidden" name="pos_service_charge_enabled" value="0">
                            <input class="form-check-input" type="checkbox" name="pos_service_charge_enabled" value="1" id="pos_service_charge_enabled" @checked($posChecked('pos_service_charge_enabled'))>
                            <label class="form-check-label" for="pos_service_charge_enabled">Service Charges (sirf Dine-in)</label>
                        </div>
                        <div class="ms-4 mb-3" id="posServiceChargePercentWrap" style="{{ $posChecked('pos_service_charge_enabled') ? '' : 'display:none;' }}">
                            <label class="form-label" for="pos_service_charge_percent">Service charge %</label>
                            <div class="input-group" style="max-width: 10rem;">
                                <input type="number"
                                       name="pos_service_charge_percent"
                                       id="pos_service_charge_percent"
                                       class="form-control"
                                       min="0"
                                       max="100"
                                       step="0.01"
                                       value="{{ old('pos_service_charge_percent', $settings['pos_service_charge_percent'] ?? '0') }}">
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="form-text">Sirf <strong>Dine-in</strong> bills par apply hoga. Takeaway aur Delivery par nahi.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── System Tab ── --}}
    <div class="tab-pane fade" id="tab-system">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">Display & Format</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Date Format</label>
                        <select name="date_format" class="form-select">
                            @php
                                $formats = ['d M Y' => '01 Jan 2025', 'd/m/Y' => '01/01/2025', 'm/d/Y' => '01/01/2025', 'Y-m-d' => '2025-01-01', 'd-m-Y' => '01-01-2025'];
                            @endphp
                            @foreach ($formats as $fmt => $example)
                                <option value="{{ $fmt }}" {{ old('date_format', $settings['date_format']) === $fmt ? 'selected' : '' }}>
                                    {{ $example }} ({{ $fmt }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 mt-3">
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="fw-semibold mb-1 small text-secondary">LIVE PREVIEW</div>
                            <div class="d-flex flex-wrap gap-3 small">
                                <span>Company: <strong id="prev_name">{{ $settings['company_name'] }}</strong></span>
                                <span>Currency: <strong id="prev_currency">{{ $settings['currency_symbol'] }}</strong></span>
                                <span>Tax: <strong id="prev_tax">{{ $settings['tax_label'] }} {{ $settings['tax_rate'] }}%</strong></span>
                                <span>Invoice: <strong id="prev_inv">{{ $settings['invoice_prefix'] }}0001</strong></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-4 border-success border-opacity-25">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-wifi me-1"></i> Mobile & Tablet — LAN Server IP
            </div>
            <div class="card-body">
                <p class="text-secondary small mb-3">
                    Cafe / restaurant PC ka jo <strong>fixed LAN IP</strong> hai woh yahan likhein.
                    Same WiFi par mobile, tablet aur Order Taker app isi address se connect karenge.
                    (PC par pehle static IP set karein — <code>scripts/set-cafe-lan-ip.ps1</code>)
                </p>
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">LAN Server IP</label>
                        <input type="text" name="lan_server_ip" id="lan_server_ip" class="form-control font-monospace"
                               value="{{ old('lan_server_ip', $settings['lan_server_ip']) }}"
                               placeholder="192.168.3.50"
                               autocomplete="off">
                        <div class="form-text">IPv4 address — router / PC ka local IP</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Port <span class="text-secondary fw-normal">(optional)</span></label>
                        <input type="number" name="lan_server_port" id="lan_server_port" class="form-control font-monospace"
                               value="{{ old('lan_server_port', $settings['lan_server_port']) }}"
                               placeholder="8080" min="1" max="65535">
                        <div class="form-text">Khali = port 80</div>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" name="lan_server_https" id="lan_server_https" value="1"
                                {{ old('lan_server_https', $settings['lan_server_https']) === '1' ? 'checked' : '' }}>
                            <label class="form-check-label" for="lan_server_https">HTTPS use karein (secure lock)</label>
                        </div>
                    </div>
                </div>

                <div class="mt-4 p-3 bg-light rounded-3 border">
                    <div class="fw-semibold small text-secondary mb-2">DEVICE URLs (same network)</div>
                    <div class="vstack gap-2 small">
                        @php
                            $lanRows = [
                                ['key' => 'order_taker_app', 'label' => 'Order Taker App (API)', 'url' => $lanLinks['order_taker_app'] ?? ''],
                                ['key' => 'order_taker_web', 'label' => 'Order Taker (Browser)', 'url' => $lanLinks['order_taker_web'] ?? ''],
                                ['key' => 'pos', 'label' => 'Cashier POS', 'url' => $lanLinks['pos'] ?? ''],
                                ['key' => 'kitchen', 'label' => 'Kitchen Display', 'url' => $lanLinks['kitchen'] ?? ''],
                                ['key' => 'order_status', 'label' => 'Order Status', 'url' => $lanLinks['order_status'] ?? ''],
                            ];
                        @endphp
                        @foreach ($lanRows as $row)
                            <div class="d-flex flex-wrap align-items-center gap-2 lan-url-row" data-lan-path="{{ parse_url($row['url'], PHP_URL_PATH) ?: '/' }}">
                                <span class="text-secondary" style="min-width:11rem;">{{ $row['label'] }}:</span>
                                <code class="flex-grow-1 lan-url-text user-select-all" id="lanUrl-{{ $row['key'] }}">{{ $row['url'] }}</code>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-copy-lan-url="{{ $row['key'] }}">
                                    <i class="bi bi-clipboard"></i> Copy
                                </button>
                            </div>
                        @endforeach
                    </div>
                    <div class="form-text mt-2 mb-0">
                        Mobile app login par <strong>Server URL</strong> mein upar wala API address use karein.
                        App pehli dafa <code>/api/server-config</code> se bhi yeh IP le sakti hai.
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-4">
            <div class="card-header bg-white fw-semibold">Database Information</div>
            <div class="card-body">
                <div class="row g-2 small text-secondary">
                    <div class="col-md-3"><span class="fw-semibold text-dark">DB Name:</span> {{ config('database.connections.mysql.database') }}</div>
                    <div class="col-md-3"><span class="fw-semibold text-dark">App Env:</span> {{ config('app.env') }}</div>
                    <div class="col-md-3"><span class="fw-semibold text-dark">Laravel:</span> {{ app()->version() }}</div>
                    <div class="col-md-3"><span class="fw-semibold text-dark">PHP:</span> {{ PHP_VERSION }}</div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-4 border-primary border-opacity-25">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-database-down me-1"></i> Database backup (.sql)
            </div>
            <div class="card-body">
                @if(auth()->user()->isPlatformSuperAdmin())
                    <p class="text-secondary small mb-3 mb-md-0">
                        <strong>Super admin:</strong> poora database export (pehle <code>mysqldump</code> try hota hai; warna PHP se).
                        Optional: <code>.env</code> mein <code>MYSQLDUMP_PATH</code> = full path to <code>mysqldump.exe</code>.
                    </p>
                @else
                    <p class="text-secondary small mb-3 mb-md-0">
                        <strong>Company admin:</strong> sirf is company ki rows (<code>company_id</code> wali tables + <code>companies</code> ki apni entry).
                        Dusri companies ka data is file mein nahi aata.
                    </p>
                @endif
                <button type="submit" form="dbBackupForm" class="btn btn-outline-primary" onclick="return confirm('SQL backup download shuru karein?');">
                    <i class="bi bi-download me-1"></i> Download SQL backup
                </button>
            </div>
        </div>
    </div>

</div>{{-- /tab-content --}}

<div class="mt-4 d-flex gap-2">
    <button type="submit" form="settingsMainForm" class="btn btn-primary px-4">
        <i class="bi bi-check-lg me-1"></i> Save Settings
    </button>
    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Cancel</a>
</div>

</form>

<form id="posSittingAreaAddForm" method="POST" action="{{ route('settings.pos-sitting-areas.store') }}" class="d-none">
    @csrf
</form>
@foreach(($posSittingAreas ?? []) as $area)
    <form id="posSittingAreaDeleteForm-{{ $area->id }}" method="POST" action="{{ route('settings.pos-sitting-areas.destroy', $area) }}" class="d-none">
        @csrf
        @method('DELETE')
    </form>
    <form id="posTableAddForm-{{ $area->id }}" method="POST" action="{{ route('settings.pos-tables.store') }}" class="d-none">
        @csrf
    </form>
    @foreach($area->tables as $t)
        <form id="posTableDeleteForm-{{ $t->id }}" method="POST" action="{{ route('settings.pos-tables.destroy', $t) }}" class="d-none">
            @csrf
            @method('DELETE')
        </form>
    @endforeach
@endforeach

<form id="dbBackupForm" method="POST" action="{{ route('settings.database-backup') }}" class="d-none">
    @csrf
</form>
@endsection

@section('scripts')
<script>
function previewLogo(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            const prev = document.getElementById('logoPreview');
            if (prev.tagName === 'IMG') {
                prev.src = e.target.result;
            } else {
                const img = document.createElement('img');
                img.id = 'logoPreview';
                img.src = e.target.result;
                img.className = 'img-fluid rounded-3 border';
                img.style.maxHeight = '120px';
                img.style.objectFit = 'contain';
                prev.replaceWith(img);
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Live preview updates
document.querySelector('[name="company_name"]')?.addEventListener('input', e =>
    document.getElementById('prev_name').textContent = e.target.value);
document.querySelector('[name="currency_symbol"]')?.addEventListener('input', e =>
    document.getElementById('prev_currency').textContent = e.target.value);
document.querySelector('[name="tax_label"]')?.addEventListener('input', e => {
    const rate = document.querySelector('[name="tax_rate"]').value;
    document.getElementById('prev_tax').textContent = e.target.value + ' ' + rate + '%';
});
document.querySelector('[name="tax_rate"]')?.addEventListener('input', e => {
    const lbl = document.querySelector('[name="tax_label"]').value;
    document.getElementById('prev_tax').textContent = lbl + ' ' + e.target.value + '%';
});
document.querySelector('[name="invoice_prefix"]')?.addEventListener('input', e =>
    document.getElementById('prev_inv').textContent = e.target.value + '0001');

(function () {
    const rowsWrap = document.getElementById('productCostFieldsRows');
    const addBtn = document.getElementById('addProductCostFieldBtn');
    if (!rowsWrap || !addBtn) return;

    function syncIndexes() {
        const rows = rowsWrap.querySelectorAll('.product-cost-field-row');
        rows.forEach((row, index) => {
            const label = row.querySelector('[data-name="label"]');
            const baseIndex = row.querySelector('[data-name="base_index"]');
            const operator = row.querySelector('[data-name="operator"]');
            const calcTo = row.querySelector('[data-name="calculate_to"]');
            const rate = row.querySelector('[data-name="rate"]');
            if (label) label.name = `product_extra_cost_fields[${index}][label]`;
            if (baseIndex) baseIndex.name = `product_extra_cost_fields[${index}][base_index]`;
            if (operator) operator.name = `product_extra_cost_fields[${index}][operator]`;
            if (calcTo) calcTo.name = `product_extra_cost_fields[${index}][calculate_to]`;
            if (rate) rate.name = `product_extra_cost_fields[${index}][rate]`;
        });
        refreshBaseDropdowns();
    }

    function refreshBaseDropdowns() {
        const rows = Array.from(rowsWrap.querySelectorAll('.product-cost-field-row'));
        rows.forEach((row, index) => {
            const sel = row.querySelector('[data-name="base_index"]');
            if (!sel) return;
            const prevVal = sel.value;
            let html = '<option value="-1">Base Cost</option>';
            html += '<option value="-2">Effective Cost (Auto)</option>';
            html += '<option value="-3">Sale Price</option>';
            for (let j = 0; j < index; j++) {
                const prevRow = rows[j];
                const labInp = prevRow?.querySelector('[data-name="label"]');
                const t = (labInp?.value || '').trim() || ('Field ' + (j + 1));
                html += '<option value="' + j + '">' + String(t).replace(/&/g,'&amp;').replace(/</g,'&lt;') + '</option>';
            }
            sel.innerHTML = html;
            const allowed = new Set(['-1', '-2', '-3', ...rows.slice(0, index).map((_, j) => String(j))]);
            if (allowed.has(prevVal)) {
                sel.value = prevVal;
            } else {
                sel.value = '-1';
            }
        });
    }

    function makeRow(labelVal = '', rateVal = '') {
        const row = document.createElement('div');
        row.className = 'row g-2 align-items-end mb-2 product-cost-field-row';
        row.innerHTML = `
            <div class="col-md-2">
                <label class="form-label">Field Label</label>
                <input type="text" class="form-control" data-name="label" placeholder="e.g. Gas Charges">
            </div>
            <div class="col-md-2">
                <label class="form-label">Calculate from</label>
                <select class="form-select" data-name="base_index">
                    <option value="-1">Base Cost</option>
                    <option value="-2">Effective Cost (Auto)</option>
                    <option value="-3">Sale Price</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Effect</label>
                <select class="form-select" data-name="operator">
                    <option value="plus">+ Add % of base</option>
                    <option value="minus">− Subtract % of base</option>
                    <option value="multiply">× Multiply base</option>
                    <option value="divide">÷ Divide base</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Calculate To</label>
                <select class="form-select" data-name="calculate_to">
                    <option value="effective_cost">Effective Cost</option>
                    <option value="price">Sale Price</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">% of base (optional)</label>
                <div class="input-group">
                    <input type="number" step="0.0001" min="0" class="form-control" data-name="rate" placeholder="e.g. 20">
                    <span class="input-group-text">%</span>
                </div>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-outline-danger w-100" data-remove-product-cost-field>Remove</button>
            </div>
        `;

        const label = row.querySelector('[data-name="label"]');
        const rate = row.querySelector('[data-name="rate"]');
        if (label) label.value = labelVal;
        if (rate) rate.value = rateVal;

        return row;
    }

    addBtn.addEventListener('click', function () {
        rowsWrap.appendChild(makeRow());
        syncIndexes();
    });

    rowsWrap.addEventListener('click', function (event) {
        const btn = event.target.closest('[data-remove-product-cost-field]');
        if (!btn) return;
        const row = btn.closest('.product-cost-field-row');
        if (!row) return;
        row.remove();
        if (!rowsWrap.querySelector('.product-cost-field-row')) {
            rowsWrap.appendChild(makeRow());
        }
        syncIndexes();
    });

    rowsWrap.addEventListener('input', function (e) {
        if (e.target?.matches?.('[data-name="label"]')) {
            refreshBaseDropdowns();
        }
    });
    syncIndexes();
})();

(function () {
    const tab = new URLSearchParams(window.location.search).get('tab');
    const tabMap = { pos: '#tab-pos', system: '#tab-system' };
    const target = tabMap[tab];
    if (target) {
        const btn = document.querySelector('[data-bs-target="' + target + '"]');
        if (btn && typeof bootstrap !== 'undefined') {
            bootstrap.Tab.getOrCreateInstance(btn).show();
        }
    }
})();

(function () {
    const ipInput = document.getElementById('lan_server_ip');
    const portInput = document.getElementById('lan_server_port');
    if (!ipInput) return;

    function buildBaseUrl() {
        let ip = (ipInput.value || '').trim().replace(/^https?:\/\//i, '');
        ip = ip.split('/')[0];
        let port = (portInput?.value || '').trim();
        const https = document.getElementById('lan_server_https')?.checked;
        if (ip.includes(':')) {
            const parts = ip.split(':');
            ip = parts[0];
            if (!port && parts[1]) port = parts[1];
        }
        if (!ip) return '';
        const scheme = https ? 'https' : 'http';
        const p = parseInt(port, 10);
        const defaultPort = https ? 443 : 80;
        if (p > 0 && p !== defaultPort) return scheme + '://' + ip + ':' + p;
        return scheme + '://' + ip;
    }

    function refreshLanUrls() {
        const base = buildBaseUrl();
        document.querySelectorAll('.lan-url-row').forEach(function (row) {
            const path = row.getAttribute('data-lan-path') || '/';
            const code = row.querySelector('.lan-url-text');
            if (!code) return;
            if (!base) {
                code.textContent = '— IP save karein —';
                return;
            }
            const suffix = path === '/' ? '' : path;
            code.textContent = base + suffix;
        });
    }

    document.querySelectorAll('[data-copy-lan-url]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const key = btn.getAttribute('data-copy-lan-url');
            const el = document.getElementById('lanUrl-' + key);
            const text = el?.textContent?.trim() || '';
            if (!text || text.startsWith('—')) return;
            navigator.clipboard.writeText(text).then(function () {
                const icon = btn.querySelector('i');
                if (icon) {
                    icon.className = 'bi bi-check2';
                    setTimeout(function () { icon.className = 'bi bi-clipboard'; }, 1500);
                }
            });
        });
    });

    ipInput.addEventListener('input', refreshLanUrls);
    portInput?.addEventListener('input', refreshLanUrls);
    document.getElementById('lan_server_https')?.addEventListener('change', refreshLanUrls);
    refreshLanUrls();
})();

(function () {
    const enabled = document.getElementById('pos_service_charge_enabled');
    const wrap = document.getElementById('posServiceChargePercentWrap');
    if (!enabled || !wrap) return;

    function syncServiceChargeField() {
        wrap.style.display = enabled.checked ? '' : 'none';
    }

    enabled.addEventListener('change', syncServiceChargeField);
    syncServiceChargeField();
})();

</script>
@endsection
