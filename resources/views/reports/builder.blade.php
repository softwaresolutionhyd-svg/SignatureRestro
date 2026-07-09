@extends('layouts.admin')
@section('title', 'Report Builder — ' . config('app.name'))

@section('content')
<div class="mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">Report Builder</h4>
        <div class="text-secondary small">Configure → Preview → Export PDF/CSV → Save as Template</div>
    </div>
    <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm">← Reports Hub</a>
</div>

@php
$reportTypes = [
    'sales'      => ['label'=>'Sales (POS)',        'icon'=>'#f97316', 'svg'=>'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
    'purchases'  => ['label'=>'Purchases',          'icon'=>'#22c55e', 'svg'=>'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z'],
    'inventory'  => ['label'=>'Inventory / Stock',  'icon'=>'#0ea5e9', 'svg'=>'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
    'employees'  => ['label'=>'Employees / HR',     'icon'=>'#ec4899', 'svg'=>'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z'],
    'expenses'   => ['label'=>'Expenses',           'icon'=>'#14b8a6', 'svg'=>'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z'],
    'credit'     => ['label'=>'Credit Book',        'icon'=>'#ef4444', 'svg'=>'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01'],
];

$colDefs = [
    'sales'     => ['order_no'=>'Order #','date'=>'Date','contact'=>'Customer name','cashier'=>'Cashier','subtotal'=>'Subtotal','discount'=>'Discount','tax'=>'Tax','grand_total'=>'Grand Total','is_credit'=>'Payment Type'],
    'purchases' => ['order_no'=>'PO #','date'=>'Date','vendor'=>'Vendor','creator'=>'Created By','subtotal'=>'Subtotal','tax'=>'Tax','grand_total'=>'Grand Total','status'=>'Status'],
    'inventory' => ['sku'=>'SKU','name'=>'Product','category'=>'Category','uom'=>'UOM','qty'=>'Stock Qty','cost'=>'Cost','price'=>'Sale Price','cost_value'=>'Stock Value (Cost)','sale_value'=>'Stock Value (Sale)'],
    'employees' => ['employee_no'=>'Emp #','name'=>'Name','department'=>'Department','designation'=>'Designation','phone'=>'Phone','email'=>'Email','join_date'=>'Join Date','salary'=>'Salary','status'=>'Status'],
    'expenses'  => ['date'=>'Date','employee'=>'Employee','category'=>'Category','description'=>'Description','qty'=>'Qty','unit_amount'=>'Unit Cost','total'=>'Subtotal','tax'=>'Tax','grand_total'=>'Grand Total','status'=>'Status'],
    'credit'    => ['name'=>'Contact','phone'=>'Phone','city'=>'City','credit'=>'Total Credit','paid'=>'Total Paid','balance'=>'Balance Due'],
];
@endphp

{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- Saved Templates Bar (full width, collapsible)              --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<div class="card border-0 shadow-sm mb-4" id="templatesCard">
    <div class="card-header bg-white d-flex align-items-center justify-content-between py-2 px-3"
         style="cursor:pointer;" id="templatesToggle">
        <div class="d-flex align-items-center gap-2">
            <svg width="16" height="16" fill="none" viewBox="0 0 20 20" style="color:#7c3aed;">
                <path d="M5 3h10a2 2 0 012 2v1H3V5a2 2 0 012-2zM3 8h14v9a2 2 0 01-2 2H5a2 2 0 01-2-2V8z" stroke="currentColor" stroke-width="1.5"/>
                <path d="M8 12h4M8 15h2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            <span class="fw-semibold small">Saved Templates</span>
            <span class="badge rounded-pill bg-primary bg-opacity-15 text-primary" id="templateCount" style="font-size:10px;">{{ $templates->count() }}</span>
        </div>
        <svg width="14" height="14" fill="none" viewBox="0 0 20 20" id="templatesChevron" style="transition:.2s;color:#888;">
            <path d="M5 7l5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </div>
    <div id="templatesBody" class="card-body py-2 px-3">
        @if($templates->isEmpty())
        <div class="text-secondary small text-center py-2" id="noTemplatesMsg">
            No saved templates yet. Configure a report and click <strong>"Save as Template"</strong>.
        </div>
        @else
        <div class="d-none text-secondary small py-2" id="noTemplatesMsg">
            No saved templates yet. Configure a report and click <strong>"Save as Template"</strong>.
        </div>
        @endif
        <div class="d-flex flex-wrap gap-2 align-items-start" id="templatesGrid">
            @foreach($templates as $tpl)
            @php
                $tplData = [
                    'id' => $tpl->id,
                    'name' => $tpl->name,
                    'report_type' => $tpl->report_type,
                    'type_label' => $tpl->typeLabel(),
                    'type_color' => $tpl->typeColor(),
                    'preset' => $tpl->preset,
                    'cols' => $tpl->cols,
                    'filters' => $tpl->filters ?? [],
                ];
            @endphp
            <div class="template-chip border rounded-3 px-3 py-2 d-flex align-items-center gap-2"
                 style="cursor:pointer;max-width:260px;background:#fafafa;border-color:#e5e7eb!important;transition:.15s;"
                 data-tpl="{{ json_encode($tplData) }}"
                 onmouseenter="this.style.borderColor='#7c3aed';" onmouseleave="this.style.borderColor='#e5e7eb';">
                <span class="rounded-circle d-inline-block flex-shrink-0"
                      style="width:8px;height:8px;background:{{ $tpl->typeColor() }};"></span>
                <div class="flex-grow-1 overflow-hidden">
                    <div class="fw-semibold small text-truncate" style="max-width:160px;">{{ $tpl->name }}</div>
                    <div class="text-secondary" style="font-size:10px;">{{ $tpl->typeLabel() }} · {{ $tpl->preset }}</div>
                </div>
                <button type="button" class="btn btn-link p-0 text-danger tpl-delete ms-1"
                        data-id="{{ $tpl->id }}" title="Delete template" style="font-size:14px;line-height:1;">
                    <svg width="12" height="12" fill="none" viewBox="0 0 20 20"><path d="M4 7h12M9 11v5M11 11v5M6 7l1-3h6l1 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- Main Builder (left config + right results)                 --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<div class="row g-4">
    {{-- Left panel: configuration --}}
    <div class="col-12 col-xl-3">

        {{-- Report Type --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold py-3 small">1. Report Type</div>
            <div class="card-body py-2 px-3">
                @foreach($reportTypes as $key => $rt)
                <label class="d-flex align-items-center gap-2 mb-2 p-2 rounded type-card" style="cursor:pointer;border:2px solid transparent;"
                    data-type="{{ $key }}" data-color="{{ $rt['icon'] }}">
                    <input type="radio" name="report_type" value="{{ $key }}" class="d-none" {{ $key==='sales'?'checked':'' }}>
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" style="color:{{ $rt['icon'] }};flex-shrink:0;">
                        <path d="{{ $rt['svg'] }}" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span class="small fw-semibold">{{ $rt['label'] }}</span>
                </label>
                @endforeach
            </div>
        </div>

        {{-- Date Period --}}
        <div class="card border-0 shadow-sm mb-3" id="datePeriodCard">
            <div class="card-header bg-white fw-semibold py-3 small">2. Date Period</div>
            <div class="card-body py-2 px-3">
                <div class="d-flex flex-wrap gap-1 mb-3" id="presetBtns">
                    @foreach([
                        'today'=>'Today','yesterday'=>'Yesterday',
                        'this_week'=>'This Week','last_week'=>'Last Week',
                        'this_month'=>'This Month','last_month'=>'Last Month',
                        'this_quarter'=>'This Quarter','this_year'=>'This Year',
                        'last_year'=>'Last Year','custom'=>'Custom',
                    ] as $val => $lbl)
                    <button type="button" class="btn btn-sm preset-btn {{ $val==='this_month'?'btn-primary':'btn-outline-secondary' }}"
                        data-preset="{{ $val }}" style="font-size:11px;padding:2px 8px;">{{ $lbl }}</button>
                    @endforeach
                </div>
                <div id="customDateRow" class="d-none">
                    <div class="mb-2">
                        <label class="form-label small mb-1">From</label>
                        <input type="date" id="dateFrom" class="form-control form-control-sm" value="{{ now()->startOfMonth()->format('Y-m-d') }}">
                    </div>
                    <div>
                        <label class="form-label small mb-1">To</label>
                        <input type="date" id="dateTo" class="form-control form-control-sm" value="{{ now()->format('Y-m-d') }}">
                    </div>
                </div>
                <div id="selectedPeriodLabel" class="small text-secondary mt-2"></div>
            </div>
        </div>

        {{-- Columns --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white d-flex align-items-center justify-content-between py-3 small">
                <span class="fw-semibold">3. Columns</span>
                <div class="d-flex gap-2">
                    <button type="button" id="selectAllCols" class="btn btn-link btn-sm p-0" style="font-size:11px;">All</button>
                    <button type="button" id="selectNoneCols" class="btn btn-link btn-sm p-0 text-secondary" style="font-size:11px;">None</button>
                </div>
            </div>
            <div class="card-body py-2 px-3" id="colCheckboxes"></div>
        </div>

        {{-- Filters --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold py-3 small">4. Filters</div>
            <div class="card-body py-2 px-3" id="filterPanel">
                <div class="text-secondary small">Select a report type to see filters.</div>
            </div>
        </div>

        {{-- Generate --}}
        <button type="button" id="btnGenerate" class="btn btn-primary w-100 py-2 fw-semibold">
            <svg width="16" height="16" fill="none" viewBox="0 0 20 20" class="me-1">
                <path d="M4 4h12v2H4zM4 9h12v2H4zM4 14h8v2H4z" fill="currentColor" opacity=".8"/>
            </svg>
            Generate Report
        </button>
    </div>

    {{-- Right panel: preview + export --}}
    <div class="col-12 col-xl-9">
        {{-- Export toolbar --}}
        <div id="exportToolbar" class="d-none mb-3 align-items-center justify-content-between flex-wrap gap-2">
            <div id="reportMeta" class="small text-secondary"></div>
            <div class="d-flex gap-2 flex-wrap">
                <button id="btnSaveTemplate" class="btn btn-outline-primary btn-sm">
                    <svg width="14" height="14" fill="none" viewBox="0 0 20 20" class="me-1"><path d="M5 3h10a2 2 0 012 2v1H3V5a2 2 0 012-2zM3 8h14v9a2 2 0 01-2 2H5a2 2 0 01-2-2V8z" stroke="currentColor" stroke-width="1.5"/><path d="M8 12h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                    Save as Template
                </button>
                <button id="btnCsv" class="btn btn-outline-success btn-sm">
                    <svg width="14" height="14" fill="none" viewBox="0 0 20 20" class="me-1"><rect x="3" y="3" width="14" height="14" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M7 10h6M10 7v6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                    Export CSV
                </button>
                <button id="btnPdf" class="btn btn-outline-danger btn-sm">
                    <svg width="14" height="14" fill="none" viewBox="0 0 20 20" class="me-1"><path d="M4 2h8l4 4v12a1 1 0 01-1 1H5a1 1 0 01-1-1V3a1 1 0 011-1z" stroke="currentColor" stroke-width="1.5"/><path d="M12 2v4h4" stroke="currentColor" stroke-width="1.5"/></svg>
                    Export PDF
                </button>
                <button id="btnPrint" class="btn btn-outline-secondary btn-sm">
                    <svg width="14" height="14" fill="none" viewBox="0 0 20 20" class="me-1"><path d="M5 4v4h10V4M5 16H3V9h14v7h-2M5 12h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                    Print
                </button>
            </div>
        </div>

        {{-- Loading --}}
        <div id="loadingState" class="d-none text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <div class="mt-2 text-secondary small">Generating report…</div>
        </div>

        {{-- Empty --}}
        <div id="emptyState" class="card border-0 shadow-sm text-center py-5">
            <div class="text-secondary">
                <svg width="48" height="48" fill="none" viewBox="0 0 48 48" class="mb-3 opacity-25">
                    <rect x="8" y="8" width="32" height="32" rx="4" stroke="currentColor" stroke-width="2.5"/>
                    <path d="M16 18h16M16 24h10M16 30h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <div class="fw-semibold mb-1">Configure & Generate</div>
                <div class="small">Or click a saved template above to load instantly</div>
            </div>
        </div>

        {{-- Results --}}
        <div id="reportResults" class="d-none">
            <div id="totalsRow" class="row g-3 mb-3"></div>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
                    <span class="fw-semibold small" id="tableTitle">Report Results</span>
                    <span class="badge bg-primary bg-opacity-15 text-primary" id="rowCountBadge"></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0" id="reportTable">
                            <thead class="table-light" id="reportHead"></thead>
                            <tbody id="reportBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════ --}}
{{-- Save Template Modal                                         --}}
{{-- ═══════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="saveTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:440px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title fw-bold mb-0">Save Report Template</h5>
                    <div class="text-secondary small mt-1">Give this report configuration a name to reuse it later</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-3">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Template Name <span class="text-danger">*</span></label>
                    <input type="text" id="tplNameInput" class="form-control" placeholder="e.g. Monthly Sales Summary" maxlength="120">
                    <div class="form-text text-secondary" style="font-size:11px;">Choose a descriptive name to identify this report</div>
                </div>
                <div class="border rounded-3 px-3 py-2 bg-light mb-1">
                    <div class="row g-1 small text-secondary">
                        <div class="col-5 fw-semibold">Report Type</div>
                        <div class="col-7" id="tplPreviewType">—</div>
                        <div class="col-5 fw-semibold">Date Period</div>
                        <div class="col-7" id="tplPreviewPreset">—</div>
                        <div class="col-5 fw-semibold">Columns</div>
                        <div class="col-7" id="tplPreviewCols">—</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="btnConfirmSave" class="btn btn-primary btn-sm px-4">
                    <svg width="13" height="13" fill="none" viewBox="0 0 20 20" class="me-1"><path d="M5 10l4 4 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Save Template
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<style>
.type-card.active {
    background: var(--active-bg, #7c3aed18);
    border-color: var(--active-color, #7c3aed) !important;
    border-radius: 8px;
}
.template-chip:hover { box-shadow: 0 2px 8px rgba(124,58,237,.15); }
.tpl-delete:hover svg { color: #ef4444; }
@media print {
    body * { visibility: hidden; }
    #printArea, #printArea * { visibility: visible; }
    #printArea { position: fixed; top:0; left:0; width:100%; }
}
</style>
<script>
(function () {
'use strict';

let reportBuilderBootAttempts = 0;
function bootReportBuilder() {
    if (typeof window.bootstrap === 'undefined' || !window.bootstrap.Modal) {
        if (++reportBuilderBootAttempts > 100) {
            console.error('Report Builder: Bootstrap JS failed to load; CSV / Save Template buttons will not work.');
            return;
        }
        setTimeout(bootReportBuilder, 50);
        return;
    }

/* ── Constants ─────────────────────────────────────────────────── */
const colDefs   = @json($colDefs);
const dataUrl   = '{{ route('reports.data') }}';
const saveUrl   = '{{ route('reports.templates.save') }}';
const deleteUrl = (id) => @json(url('/reports/templates')) + '/' + encodeURIComponent(id);
const currency  = '{{ $currency }}';
const company   = '{{ \App\Models\Setting::get('company_name', config('app.name')) }}';
const csrfToken = '{{ csrf_token() }}';
const noDateTypes = ['inventory','employees','credit'];

/* ── State ─────────────────────────────────────────────────────── */
let currentType   = 'sales';
let currentPreset = 'this_month';
let currentRows   = [];
let currentCols   = [];
let currentTotals = {};
let currentMeta   = {};

/* ══════════════════════════════════════════════════════════════
   1. Templates panel
══════════════════════════════════════════════════════════════ */

// Collapse toggle
const toggle  = document.getElementById('templatesToggle');
const body    = document.getElementById('templatesBody');
const chevron = document.getElementById('templatesChevron');
let collapsed = false;

toggle.addEventListener('click', () => {
    collapsed = !collapsed;
    body.style.display      = collapsed ? 'none' : '';
    chevron.style.transform = collapsed ? 'rotate(-90deg)' : '';
});

// Load template on chip click
function attachChipListeners(chip) {
    chip.addEventListener('click', (e) => {
        if (e.target.closest('.tpl-delete')) return;
        loadTemplate(JSON.parse(chip.dataset.tpl));
    });
    const delBtn = chip.querySelector('.tpl-delete');
    if (delBtn) delBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        deleteTemplate(+delBtn.dataset.id, chip);
    });
}
document.querySelectorAll('.template-chip').forEach(attachChipListeners);

function loadTemplate(tpl) {
    // Set type
    const card = document.querySelector(`[data-type="${tpl.report_type}"]`);
    if (card) {
        document.querySelectorAll('.type-card').forEach(c => {
            c.classList.remove('active');
            c.querySelector('input').checked = false;
        });
        card.classList.add('active');
        card.style.setProperty('--active-color', card.dataset.color);
        card.style.setProperty('--active-bg', card.dataset.color + '18');
        card.querySelector('input').checked = true;
        currentType = tpl.report_type;
        renderColCheckboxes();
        renderFilterPanel();
    }

    // Set preset
    currentPreset = tpl.preset;
    document.querySelectorAll('.preset-btn').forEach(b => {
        b.classList.toggle('btn-primary', b.dataset.preset === currentPreset);
        b.classList.toggle('btn-outline-secondary', b.dataset.preset !== currentPreset);
    });
    document.getElementById('customDateRow').classList.toggle('d-none', currentPreset !== 'custom');
    updatePeriodLabel();

    // Set columns
    setTimeout(() => {
        const checks = document.querySelectorAll('.col-check');
        checks.forEach(c => { c.checked = tpl.cols.includes(c.value); });
    }, 50);

    // Set filters
    if (tpl.filters) {
        setTimeout(() => {
            Object.entries(tpl.filters).forEach(([k, v]) => {
                const el = document.querySelector(`#filterPanel [name="${k}"]`);
                if (el) el.value = v;
            });
        }, 60);
    }

    // Flash the chip to confirm
    const activeChip = document.querySelector(`[data-tpl*='"id":${tpl.id}']`);
    if (activeChip) {
        activeChip.style.borderColor = '#7c3aed';
        setTimeout(() => activeChip.style.borderColor = '', 1000);
    }

    // Auto-generate
    setTimeout(generateReport, 80);
}

function deleteTemplate(id, chip) {
    if (!confirm('Delete this template?')) return;
    fetch(deleteUrl(id), {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
    }).then(r => r.json()).then(d => {
        if (d.ok) {
            chip.remove();
            updateTemplateCount();
        }
    }).catch(() => alert('Failed to delete.'));
}

function updateTemplateCount() {
    const chips = document.querySelectorAll('.template-chip');
    document.getElementById('templateCount').textContent = chips.length;
    document.getElementById('noTemplatesMsg').classList.toggle('d-none', chips.length > 0);
}

/* ══════════════════════════════════════════════════════════════
   2. Type card selection
══════════════════════════════════════════════════════════════ */
const typeCards = document.querySelectorAll('.type-card');
typeCards.forEach(card => {
    card.addEventListener('click', () => {
        typeCards.forEach(c => { c.classList.remove('active'); c.querySelector('input').checked = false; });
        card.classList.add('active');
        card.style.setProperty('--active-color', card.dataset.color);
        card.style.setProperty('--active-bg', card.dataset.color + '18');
        card.querySelector('input').checked = true;
        currentType = card.dataset.type;
        renderColCheckboxes();
        renderFilterPanel();
        resetResults();
    });
});
const defaultCard = document.querySelector('[data-type="sales"]');
defaultCard.classList.add('active');
defaultCard.style.setProperty('--active-color', defaultCard.dataset.color);
defaultCard.style.setProperty('--active-bg', defaultCard.dataset.color + '18');

/* ══════════════════════════════════════════════════════════════
   3. Presets
══════════════════════════════════════════════════════════════ */
const presetBtns    = document.querySelectorAll('.preset-btn');
const customDateRow = document.getElementById('customDateRow');
const periodLabel   = document.getElementById('selectedPeriodLabel');
const datePeriodCard= document.getElementById('datePeriodCard');

presetBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        presetBtns.forEach(b => { b.classList.remove('btn-primary'); b.classList.add('btn-outline-secondary'); });
        btn.classList.add('btn-primary'); btn.classList.remove('btn-outline-secondary');
        currentPreset = btn.dataset.preset;
        customDateRow.classList.toggle('d-none', currentPreset !== 'custom');
        updatePeriodLabel();
    });
});

function updatePeriodLabel() {
    const labels = {
        today:'Today', yesterday:'Yesterday', this_week:'This Week', last_week:'Last Week',
        this_month:'This Month', last_month:'Last Month', this_quarter:'This Quarter',
        this_year:'This Year', last_year:'Last Year', custom:'Custom Range'
    };
    periodLabel.textContent = labels[currentPreset] ?? '';
}
updatePeriodLabel();

/* ══════════════════════════════════════════════════════════════
   4. Column checkboxes
══════════════════════════════════════════════════════════════ */
function renderColCheckboxes() {
    const container = document.getElementById('colCheckboxes');
    const cols = colDefs[currentType] || {};
    container.innerHTML = Object.entries(cols).map(([key, label]) =>
        `<label class="d-flex align-items-center gap-2 mb-2 small" style="cursor:pointer;">
            <input type="checkbox" class="col-check form-check-input" value="${key}" checked style="accent-color:#7c3aed;">
            ${label}
        </label>`
    ).join('');
}

document.getElementById('selectAllCols').addEventListener('click', () => {
    document.querySelectorAll('.col-check').forEach(c => c.checked = true);
});
document.getElementById('selectNoneCols').addEventListener('click', () => {
    document.querySelectorAll('.col-check').forEach(c => c.checked = false);
});

/* ══════════════════════════════════════════════════════════════
   5. Filter panels
══════════════════════════════════════════════════════════════ */
const filterPanels = {
    sales: `
        <div class="mb-2">
            <label class="form-label small mb-1">Customer</label>
            <select name="contact_id" class="form-select form-select-sm"><option value="">All Customers</option>
            @foreach(\App\Models\Contact::where('active',true)->orderBy('name')->get(['id','name']) as $c)
            <option value="{{ $c->id }}">{{ $c->name }}</option>
            @endforeach
            </select>
        </div>
        <div>
            <label class="form-label small mb-1">Payment Type</label>
            <select name="is_credit" class="form-select form-select-sm">
                <option value="">All</option><option value="0">Cash</option><option value="1">Credit</option>
            </select>
        </div>`,
    purchases: `
        <div class="mb-2">
            <label class="form-label small mb-1">Vendor</label>
            <select name="vendor_id" class="form-select form-select-sm"><option value="">All Vendors</option>
            @foreach($vendors as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="form-label small mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All</option><option>draft</option><option>confirmed</option><option>received</option>
            </select>
        </div>`,
    inventory: `
        <div class="mb-2">
            <label class="form-label small mb-1">Category</label>
            <select name="category_id" class="form-select form-select-sm"><option value="">All Categories</option>
            @foreach($invCats as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="form-label small mb-1">Stock Status</label>
            <select name="stock" class="form-select form-select-sm">
                <option value="">All</option><option value="in">In Stock</option>
                <option value="low">Low Stock (≤10)</option><option value="zero">Out of Stock</option>
            </select>
        </div>`,
    employees: `
        <div class="mb-2">
            <label class="form-label small mb-1">Department</label>
            <select name="department_id" class="form-select form-select-sm"><option value="">All Departments</option>
            @foreach($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="form-label small mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All</option><option value="active">Active</option><option value="inactive">Inactive</option>
            </select>
        </div>`,
    expenses: `
        <div class="mb-2">
            <label class="form-label small mb-1">Category</label>
            <select name="category_id" class="form-select form-select-sm"><option value="">All Categories</option>
            @foreach($expCats as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
            </select>
        </div>
        <div class="mb-2">
            <label class="form-label small mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All</option><option value="draft">Draft</option>
                <option value="submitted">Submitted</option><option value="approved">Approved</option>
                <option value="paid">Paid</option><option value="refused">Refused</option>
            </select>
        </div>`,
    credit: `
        <div>
            <label class="form-label small mb-1">Search Contact</label>
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Name or phone…">
        </div>`,
};

function renderFilterPanel() {
    const panel = document.getElementById('filterPanel');
    panel.innerHTML = filterPanels[currentType] || '<div class="text-secondary small">No filters.</div>';
    datePeriodCard.classList.toggle('d-none', noDateTypes.includes(currentType));
}

/* ══════════════════════════════════════════════════════════════
   6. Generate report
══════════════════════════════════════════════════════════════ */
document.getElementById('btnGenerate').addEventListener('click', generateReport);

function generateReport() {
    currentCols = [...document.querySelectorAll('.col-check:checked')].map(c => c.value);
    if (!currentCols.length) { alert('Please select at least one column.'); return; }

    const params = new URLSearchParams();
    params.set('type',   currentType);
    params.set('preset', currentPreset);
    if (currentPreset === 'custom') {
        params.set('from', document.getElementById('dateFrom').value);
        params.set('to',   document.getElementById('dateTo').value);
    }
    currentCols.forEach(c => params.append('cols[]', c));
    document.querySelectorAll('#filterPanel [name]').forEach(el => {
        if (el.value) params.set(el.name, el.value);
    });

    document.getElementById('emptyState').classList.add('d-none');
    document.getElementById('reportResults').classList.add('d-none');
    const exportTb = document.getElementById('exportToolbar');
    exportTb.classList.add('d-none');
    exportTb.classList.remove('d-flex');
    document.getElementById('loadingState').classList.remove('d-none');

    fetch(dataUrl + '?' + params.toString(), { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(data => { currentRows = data.rows; currentTotals = data.totals; currentMeta = data; renderResults(data); })
        .catch(() => alert('Error generating report.'))
        .finally(() => document.getElementById('loadingState').classList.add('d-none'));
}

function renderResults(data) {
    const cols      = colDefs[currentType] || {};
    const typeLabels= {sales:'Sales',purchases:'Purchases',inventory:'Inventory',employees:'Employees',expenses:'Expenses',credit:'Credit Book'};
    const colors    = ['#7c3aed','#22c55e','#0ea5e9','#f97316','#ef4444','#ec4899'];

    document.getElementById('totalsRow').innerHTML = Object.entries(data.totals).map(([k,v], i) => `
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid ${colors[i%colors.length]}!important;">
                <div class="card-body py-3">
                    <div class="text-secondary small">${k}</div>
                    <div class="fw-bold fs-5 mt-1" style="color:${colors[i%colors.length]};">${v}</div>
                </div>
            </div>
        </div>`).join('');

    document.getElementById('reportHead').innerHTML = '<tr>' + currentCols.map(c => `<th class="small">${cols[c]||c}</th>`).join('') + '</tr>';

    const body = document.getElementById('reportBody');
    body.innerHTML = data.rows.length
        ? data.rows.map(row => '<tr>' + currentCols.map(c => `<td class="small">${row[c]??''}</td>`).join('') + '</tr>').join('')
        : `<tr><td colspan="${currentCols.length}" class="text-center py-4 text-secondary">No records found.</td></tr>`;

    document.getElementById('tableTitle').textContent    = (typeLabels[currentType]||'Report') + ' Report';
    document.getElementById('rowCountBadge').textContent = data.count + ' rows';
    document.getElementById('reportMeta').innerHTML      =
        `<strong>${typeLabels[currentType]} Report</strong>` +
        (data.from && !noDateTypes.includes(currentType) ? ` &nbsp;|&nbsp; ${data.from} to ${data.to}` : '') +
        ` &nbsp;|&nbsp; ${data.count} records`;

    document.getElementById('reportResults').classList.remove('d-none');
    const exportTb = document.getElementById('exportToolbar');
    exportTb.classList.remove('d-none');
    exportTb.classList.add('d-flex');
}

function resetResults() {
    document.getElementById('reportResults').classList.add('d-none');
    const exportTb = document.getElementById('exportToolbar');
    exportTb.classList.add('d-none');
    exportTb.classList.remove('d-flex');
    document.getElementById('emptyState').classList.remove('d-none');
}

/* ══════════════════════════════════════════════════════════════
   7. Save Template
══════════════════════════════════════════════════════════════ */
const saveModal = new window.bootstrap.Modal(document.getElementById('saveTemplateModal'));
const typeLabels= {sales:'Sales',purchases:'Purchases',inventory:'Inventory',employees:'Employees',expenses:'Expenses',credit:'Credit Book'};
const presetLabels = {today:'Today',yesterday:'Yesterday',this_week:'This Week',last_week:'Last Week',this_month:'This Month',last_month:'Last Month',this_quarter:'This Quarter',this_year:'This Year',last_year:'Last Year',custom:'Custom'};

document.getElementById('btnSaveTemplate').addEventListener('click', () => {
    if (!currentCols.length) { alert('Generate a report first.'); return; }
    document.getElementById('tplNameInput').value = '';
    document.getElementById('tplPreviewType').textContent   = typeLabels[currentType] || currentType;
    document.getElementById('tplPreviewPreset').textContent = presetLabels[currentPreset] || currentPreset;
    const cols = colDefs[currentType] || {};
    document.getElementById('tplPreviewCols').textContent = currentCols.map(c => cols[c]||c).join(', ');
    saveModal.show();
    setTimeout(() => document.getElementById('tplNameInput').focus(), 300);
});

document.getElementById('btnConfirmSave').addEventListener('click', saveTemplate);
document.getElementById('tplNameInput').addEventListener('keydown', e => {
    if (e.key === 'Enter') saveTemplate();
});

function saveTemplate() {
    const name = document.getElementById('tplNameInput').value.trim();
    if (!name) { document.getElementById('tplNameInput').classList.add('is-invalid'); return; }
    document.getElementById('tplNameInput').classList.remove('is-invalid');

    // Collect current filters
    const filters = {};
    document.querySelectorAll('#filterPanel [name]').forEach(el => {
        if (el.value) filters[el.name] = el.value;
    });

    const btn = document.getElementById('btnConfirmSave');
    btn.disabled = true; btn.textContent = 'Saving…';

    fetch(saveUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            name:        name,
            report_type: currentType,
            preset:      currentPreset,
            cols:        currentCols,
            filters:     filters,
        })
    })
    .then(async r => {
        const data = await r.json().catch(() => ({}));
        if (!r.ok || data.ok === false) {
            throw new Error(data.error || data.message || ('Save failed (' + r.status + ')'));
        }
        return data;
    })
    .then(tpl => {
        saveModal.hide();
        addTemplateChip(tpl);
        showToast(`Template "${tpl.name}" saved!`);
    })
    .catch(e => alert(e.message || 'Failed to save template.'))
    .finally(() => { btn.disabled = false; btn.innerHTML = '<svg width="13" height="13" fill="none" viewBox="0 0 20 20" class="me-1"><path d="M5 10l4 4 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> Save Template'; });
}

function addTemplateChip(tpl) {
    const grid = document.getElementById('templatesGrid');
    const chip = document.createElement('div');
    chip.className = 'template-chip border rounded-3 px-3 py-2 d-flex align-items-center gap-2';
    chip.style.cssText = 'cursor:pointer;max-width:260px;background:#fafafa;border-color:#e5e7eb!important;transition:.15s;';
    chip.dataset.tpl  = JSON.stringify(tpl);
    chip.onmouseenter = () => chip.style.borderColor = '#7c3aed';
    chip.onmouseleave = () => chip.style.borderColor = '#e5e7eb';
    chip.innerHTML = `
        <span class="rounded-circle d-inline-block flex-shrink-0"
              style="width:8px;height:8px;background:${tpl.type_color};"></span>
        <div class="flex-grow-1 overflow-hidden">
            <div class="fw-semibold small text-truncate" style="max-width:160px;">${tpl.name}</div>
            <div class="text-secondary" style="font-size:10px;">${tpl.type_label} · ${tpl.preset}</div>
        </div>
        <button type="button" class="btn btn-link p-0 text-danger tpl-delete ms-1"
                data-id="${tpl.id}" title="Delete template" style="font-size:14px;line-height:1;">
            <svg width="12" height="12" fill="none" viewBox="0 0 20 20"><path d="M4 7h12M9 11v5M11 11v5M6 7l1-3h6l1 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>`;
    attachChipListeners(chip);
    grid.appendChild(chip);
    updateTemplateCount();

    // Open panel if collapsed
    if (collapsed) { collapsed = false; body.style.display = ''; chevron.style.transform = ''; }
    chip.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/* ══════════════════════════════════════════════════════════════
   8. Toast notification
══════════════════════════════════════════════════════════════ */
function showToast(msg) {
    const t = document.createElement('div');
    t.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;background:#7c3aed;color:#fff;padding:10px 18px;border-radius:10px;font-size:13px;font-weight:600;box-shadow:0 4px 16px rgba(124,58,237,.4);transition:opacity .4s;';
    t.textContent = '✓ ' + msg;
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 400); }, 2800);
}

/* ══════════════════════════════════════════════════════════════
   9. CSV Export
══════════════════════════════════════════════════════════════ */
document.getElementById('btnCsv').addEventListener('click', () => {
    if (!currentRows.length) return alert('No data. Generate first.');
    const cols = colDefs[currentType] || {};
    const header = currentCols.map(c => cols[c]||c).join(',');
    const rows   = currentRows.map(row =>
        currentCols.map(c => `"${String(row[c]??'').replace(/"/g,'""')}"`).join(',')
    ).join('\n');
    const blob = new Blob(['\ufeff' + header + '\n' + rows], { type: 'text/csv;charset=utf-8;' });
    const a = Object.assign(document.createElement('a'), {
        href: URL.createObjectURL(blob),
        download: `report-${currentType}-${Date.now()}.csv`
    });
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(() => URL.revokeObjectURL(a.href), 500);
});

/* ══════════════════════════════════════════════════════════════
   10. PDF Export
══════════════════════════════════════════════════════════════ */
document.getElementById('btnPdf').addEventListener('click', () => {
    if (!currentRows.length) return alert('No data. Generate first.');
    const cols = colDefs[currentType]||{};
    const typeLabel = {sales:'Sales',purchases:'Purchases',inventory:'Inventory',employees:'Employees',expenses:'Expenses',credit:'Credit Book'}[currentType]||'Report';
    const title = typeLabel + ' Report';
    const totalsHtml = Object.entries(currentTotals).map(([k,v]) => `<span style="margin-right:16px;"><strong>${k}:</strong> ${v}</span>`).join('');
    const headerRow  = '<tr>' + currentCols.map(c => `<th style="background:#7c3aed;color:#fff;padding:6px 8px;text-align:left;font-size:11px;">${cols[c]||c}</th>`).join('') + '</tr>';
    const bodyRows   = currentRows.map((row,i) =>
        `<tr style="background:${i%2===0?'#fff':'#f8f5ff'};">` +
        currentCols.map(c => `<td style="padding:5px 8px;font-size:11px;border-bottom:1px solid #eee;">${row[c]??''}</td>`).join('') +
        '</tr>'
    ).join('');

    const html = `<div style="font-family:Arial,sans-serif;padding:20px;">
        <div style="border-bottom:3px solid #7c3aed;padding-bottom:12px;margin-bottom:16px;">
            <div style="font-size:18px;font-weight:bold;color:#7c3aed;">${company}</div>
            <div style="font-size:14px;font-weight:600;margin-top:4px;">${title}</div>
            ${currentMeta.from && !noDateTypes.includes(currentType) ? `<div style="font-size:11px;color:#666;">Period: ${currentMeta.from} to ${currentMeta.to}</div>` : ''}
            <div style="font-size:11px;color:#666;">Generated: ${new Date().toLocaleString()}</div>
        </div>
        <div style="font-size:11px;margin-bottom:12px;padding:8px 12px;background:#f8f5ff;border-radius:6px;">${totalsHtml}</div>
        <table style="width:100%;border-collapse:collapse;"><thead>${headerRow}</thead><tbody>${bodyRows}</tbody></table>
        <div style="margin-top:16px;font-size:10px;color:#aaa;text-align:center;">${currentRows.length} records | ${company}</div>
    </div>`;

    const el = document.createElement('div');
    el.innerHTML = html;
    document.body.appendChild(el);
    html2pdf().set({
        margin:[10,8,10,8], filename:`${currentType}-report-${new Date().toISOString().slice(0,10)}.pdf`,
        image:{type:'jpeg',quality:.98}, html2canvas:{scale:2,useCORS:true},
        jsPDF:{unit:'mm',format:'a4',orientation:currentCols.length>6?'landscape':'portrait'},
    }).from(el).save().then(() => document.body.removeChild(el));
});

/* ══════════════════════════════════════════════════════════════
   11. Print
══════════════════════════════════════════════════════════════ */
document.getElementById('btnPrint').addEventListener('click', () => {
    if (!currentRows.length) return alert('No data. Generate first.');
    const cols = colDefs[currentType]||{};
    const typeLabel = {sales:'Sales',purchases:'Purchases',inventory:'Inventory',employees:'Employees',expenses:'Expenses',credit:'Credit Book'}[currentType]||'Report';
    const w = window.open('','_blank');
    w.document.write(`<!doctype html><html><head><title>${typeLabel} Report</title>
    <style>body{font-family:Arial,sans-serif;font-size:11px;margin:20px;}
    h2{color:#7c3aed;border-bottom:2px solid #7c3aed;padding-bottom:8px;}
    table{width:100%;border-collapse:collapse;}th{background:#7c3aed;color:#fff;padding:6px 8px;text-align:left;}
    td{padding:5px 8px;border-bottom:1px solid #eee;}tr:nth-child(even) td{background:#f8f5ff;}
    .totals{background:#f8f5ff;padding:8px 12px;border-radius:6px;margin-bottom:12px;}</style></head>
    <body><h2>${company} — ${typeLabel} Report</h2>
    <div class="totals">${Object.entries(currentTotals).map(([k,v])=>`<strong>${k}:</strong> ${v}&nbsp;&nbsp;&nbsp;`).join('')}</div>
    <table><thead><tr>${currentCols.map(c=>`<th>${cols[c]||c}</th>`).join('')}</tr></thead>
    <tbody>${currentRows.map(row=>`<tr>${currentCols.map(c=>`<td>${row[c]??''}</td>`).join('')}</tr>`).join('')}</tbody>
    </table></body></html>`);
    w.document.close(); w.focus(); setTimeout(() => w.print(), 500);
});

/* ══════════════════════════════════════════════════════════════
   12. Init
══════════════════════════════════════════════════════════════ */
renderColCheckboxes();
renderFilterPanel();

} /* end bootReportBuilder */

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootReportBuilder);
} else {
    bootReportBuilder();
}

})();
</script>
@endsection
