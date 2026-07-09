@extends('layouts.admin')

@section('title', 'Maintenance — ' . config('app.name'))

@section('content')
@php($u = auth()->user())
<div class="mb-3">
    <div class="d-flex justify-content-between align-items-center gap-2">
        <h4 class="fw-bold mb-0">Maintenance</h4>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMaintenanceItemModal">+ Add Item</button>
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addMaintenanceCategoryModal">+ Add Category</button>
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addMaintenanceLocationModal">+ Add Location</button>
            @if($u?->isSuperAdmin())
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#setOpeningStockModal">Set Previous Balance Stock</button>
                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#purgeMaintenanceModal">Delete All Data</button>
            @endif
        </div>
    </div>
    <div class="text-secondary small">Maintenance items are inventory products under category <strong>Maintenance</strong>.</div>
</div>

@include('maintenance.partials.subnav')

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if(session('warning'))
    <div class="alert alert-warning">{{ session('warning') }}</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card kpi-card shadow-sm h-100">
            <div class="card-body">
                <div class="kpi-label">Items</div>
                <div class="kpi-value">{{ fmt_num($kpis['items'], 0) }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card kpi-card shadow-sm h-100">
            <div class="card-body">
                <div class="kpi-label">Total On Hand</div>
                <div class="kpi-value">{{ fmt_num($kpis['on_hand_total'], 3) }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card kpi-card shadow-sm h-100">
            <div class="card-body">
                <div class="kpi-label">Pending Demands</div>
                <div class="kpi-value">{{ fmt_num($kpis['pending_demands'], 0) }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card kpi-card shadow-sm h-100">
            <div class="card-body">
                <div class="kpi-label">Cost Held Items</div>
                <div class="kpi-value">{{ fmt_num($kpis['cost_held_items'], 2) }}</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Create Demand (Multiple Items)</div>
            <div class="card-body">
                <form method="POST" action="{{ route('maintenance.demands.store') }}" class="row g-2" id="maintenanceDemandForm">
                    @csrf
                    <div class="col-12 col-md-4">
                        <label class="form-label small">Demand date</label>
                        <input type="date" name="demand_date" class="form-control" required value="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small">Needed date</label>
                        <input type="date" name="needed_date" class="form-control">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small">Requested by</label>
                        <input type="text" name="requested_by" class="form-control" required maxlength="120">
                    </div>
                    <div class="col-12">
                        <div class="table-responsive border rounded">
                            <table class="table table-sm mb-0 align-middle" id="demandLinesTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Item name</th>
                                        <th style="width:160px;">Location</th>
                                        <th style="width:170px;">Category</th>
                                        <th style="width:110px;">Qty</th>
                                        <th style="width:110px;">UOM</th>
                                        <th style="width:140px;">Rate expected</th>
                                        <th style="width:140px;" class="text-end">Total</th>
                                        <th style="width:48px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="demandLinesBody"></tbody>
                            </table>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="addDemandLine">+ Add item</button>
                    </div>

                    <div class="col-12">
                        <label class="form-label small">Note</label>
                        <input type="text" name="note" class="form-control" maxlength="255">
                    </div>
                    <div class="col-12 d-grid">
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-secondary w-50" type="submit" name="save_mode" value="draft">Save Draft</button>
                            <button class="btn btn-outline-primary w-50" type="submit" name="save_mode" value="final">Create Demand</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Issue Item</div>
            <div class="card-body">
                <form method="POST" action="{{ route('maintenance.issues.store') }}" class="row g-2">
                    @csrf
                    <div class="col-12">
                        <label class="form-label small">Item</label>
                        <select name="product_id" class="form-select" required>
                            <option value="">Select item</option>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}">{{ $item->name }} (On hand: {{ fmt_num((float)$item->qty_on_hand, 3) }} {{ $item->uom }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Issue location</label>
                        <select name="issued_location" class="form-select" required>
                            <option value="">Select location</option>
                            @foreach(($lineLocations ?? []) as $loc)
                                <option value="{{ $loc }}">{{ $loc }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Issue to</label>
                        <input type="text" name="issued_to" class="form-control" required maxlength="120">
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Qty</label>
                        <input type="number" name="qty_uom" class="form-control" min="0.001" step="0.001" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small">UOM</label>
                        <input type="text" name="uom" class="form-control" value="Nos" required maxlength="30">
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Reference</label>
                        <input type="text" name="reference" class="form-control" maxlength="80">
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Note</label>
                        <input type="text" name="note" class="form-control" maxlength="255">
                    </div>
                    <div class="col-12 d-grid">
                        <button class="btn btn-danger" type="submit">Issue & Deduct Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold">Maintenance Items (On Hand)</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th class="text-center" style="width:4rem">Ser</th>
                    <th>Item</th>
                    <th class="text-end">Rate</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    <tr>
                        <td class="text-center text-secondary">{{ preg_match('/MNT-(\d+)/', (string) $item->sku, $m) ? (int) $m[1] : $loop->iteration }}</td>
                        <td>{{ $item->name }}</td>
                        <td class="text-end">{{ fmt_num((float)$item->cost, 2) }}</td>
                        <td class="text-end">{{ fmt_num((float)$item->qty_on_hand, 0) }}</td>
                        <td class="text-end">{{ fmt_num(((float)$item->qty_on_hand) * ((float)$item->cost), 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-secondary py-4">No maintenance items yet.</td></tr>
                @endforelse
            </tbody>
            @if($items->isNotEmpty())
                <tfoot class="table-light">
                    <tr class="fw-semibold">
                        <td colspan="4" class="text-end">Grand Total</td>
                        <td class="text-end">{{ fmt_num($items->sum(fn ($i) => (float)$i->qty_on_hand * (float)$i->cost), 2) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Recent Demands (Expected vs Received)</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Demand</th>
                            <th>Requested by</th>
                            <th>Dates</th>
                            <th class="text-end">Expected</th>
                            <th class="text-end">Received</th>
                            <th>Status</th>
                            <th class="text-end">Update Receive</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($demands as $d)
                            <tr>
                                <td>
                                    @foreach($d->lines as $line)
                                        <div class="small">
                                            {{ $line->displayName() }} · {{ fmt_num((float)$line->qty_uom, 3) }} {{ $line->uom }}
                                            <span class="text-secondary">({{ $line->locationLabel() }} / {{ $line->categoryLabel() }})</span>
                                        </div>
                                    @endforeach
                                </td>
                                <td>{{ $d->requested_by }}</td>
                                <td class="small text-secondary">
                                    Demand: {{ optional($d->demand_date)->format('d M Y') ?: '—' }}<br>
                                    Need: {{ optional($d->needed_date)->format('d M Y') ?: '—' }}
                                </td>
                                <td class="text-end small">
                                    @foreach($d->lines as $line)
                                        <div>{{ fmt_num((float)$line->expected_total, 2) }}</div>
                                    @endforeach
                                </td>
                                <td class="text-end small">
                                    @foreach($d->lines as $line)
                                        <div>{{ fmt_num((float)$line->actual_total, 2) }}</div>
                                    @endforeach
                                </td>
                                <td>
                                    <span class="badge {{ $d->status === 'draft' ? 'text-bg-warning' : 'text-bg-secondary' }}">{{ ucfirst($d->status) }}</span>
                                    @if($d->status === 'draft')
                                        <a href="{{ route('maintenance.demands.edit', $d) }}" class="btn btn-sm btn-outline-dark ms-1">Edit Draft</a>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($d->status === 'draft')
                                        <span class="text-secondary small">Finalize first</span>
                                    @else
                                        <details>
                                            <summary class="btn btn-sm btn-outline-primary">Set rates</summary>
                                            <form method="POST" action="{{ route('maintenance.demands.receive', $d) }}" class="mt-2 p-2 border rounded text-start">
                                                @csrf
                                                @foreach($d->lines as $line)
                                                    <input type="hidden" name="lines[{{ $line->id }}][line_id]" value="{{ $line->id }}">
                                                    <div class="small fw-semibold mb-1">{{ $line->displayName() }}</div>
                                                    <div class="row g-1 mb-2">
                                                        <div class="col-6">
                                                            <input type="number" step="0.001" min="0" max="{{ (float) $line->qty_uom }}" class="form-control form-control-sm"
                                                                   name="lines[{{ $line->id }}][received_qty_uom]" value="{{ fmt_num((float)$line->received_qty_uom, 3) }}" placeholder="Received qty">
                                                        </div>
                                                        <div class="col-6">
                                                            <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                                                   name="lines[{{ $line->id }}][actual_rate]" value="{{ fmt_num((float)$line->actual_rate, 2) }}" placeholder="Actual rate">
                                                        </div>
                                                    </div>
                                                @endforeach
                                                <button type="submit" class="btn btn-sm btn-success w-100">Save receive details</button>
                                            </form>
                                        </details>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-secondary py-3">No demands yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Issue Log</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Item</th>
                            <th>Location</th>
                            <th>Issued to</th>
                            <th class="text-end">Qty</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($issues as $i)
                            <tr>
                                <td>{{ $i->product?->name ?? '—' }}</td>
                                <td>{{ $i->issued_location ?: '—' }}</td>
                                <td>{{ $i->issued_to }}</td>
                                <td class="text-end">{{ fmt_num((float)$i->qty_uom, 3) }} {{ $i->uom }}</td>
                                <td class="small text-secondary">{{ optional($i->created_at)->format('d M Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-secondary py-3">No issues yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="addMaintenanceItemModal" tabindex="-1" aria-labelledby="addMaintenanceItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addMaintenanceItemModalLabel">Add Maintenance Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('maintenance.items.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label small">Item name</label>
                            <input type="text" name="name" class="form-control" required maxlength="200">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Base UOM</label>
                            <input type="text" name="uom" class="form-control" value="Nos" required maxlength="30">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Cost</label>
                            <input type="number" name="cost" class="form-control" min="0" step="0.01" value="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Reorder level</label>
                            <input type="number" name="reorder_level" class="form-control" min="0" step="0.001" value="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary" type="submit">Add Item</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="addMaintenanceCategoryModal" tabindex="-1" aria-labelledby="addMaintenanceCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addMaintenanceCategoryModalLabel">Add Demand Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('maintenance.categories.store') }}">
                @csrf
                <div class="modal-body">
                    <label class="form-label small">Category name</label>
                    <input type="text" name="name" class="form-control" required maxlength="80" placeholder="e.g. Electrical">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary" type="submit">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="addMaintenanceLocationModal" tabindex="-1" aria-labelledby="addMaintenanceLocationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addMaintenanceLocationModalLabel">Add Demand Location</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('maintenance.locations.store') }}">
                @csrf
                <div class="modal-body">
                    <label class="form-label small">Location name</label>
                    <input type="text" name="name" class="form-control" required maxlength="120" placeholder="e.g. Room Block A">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary" type="submit">Add Location</button>
                </div>
            </form>
        </div>
    </div>
</div>
@if($u?->isSuperAdmin())
<div class="modal fade" id="setOpeningStockModal" tabindex="-1" aria-labelledby="setOpeningStockModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="setOpeningStockModalLabel">Set Previous Balance Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('maintenance.opening-stock.set') }}">
                @csrf
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label small">Item</label>
                            <select name="product_id" class="form-select" required>
                                <option value="">Select item</option>
                                @foreach($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }} (Current: {{ fmt_num((float)$item->qty_on_hand, 3) }} {{ $item->uom }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Previous balance qty</label>
                            <input type="number" name="qty_uom" class="form-control" min="0" step="0.001" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">UOM</label>
                            <input type="text" name="uom" class="form-control" value="Nos" required maxlength="30">
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Note</label>
                            <input type="text" name="note" class="form-control" maxlength="255" placeholder="Optional">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-danger" type="submit">Set Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="purgeMaintenanceModal" tabindex="-1" aria-labelledby="purgeMaintenanceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger" id="purgeMaintenanceModalLabel">Delete All Maintenance Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('maintenance.purge') }}">
                @csrf
                @method('DELETE')
                <div class="modal-body">
                    <p class="mb-0">Yeh tamam maintenance <strong>items</strong>, <strong>demands</strong>, <strong>issues</strong>, <strong>locations</strong>, aur <strong>categories</strong> delete kar dega. Yeh action wapas nahi ho sakta.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-danger" type="submit">Delete All</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@section('scripts')
<script>
(function () {
    const items = @json($items->map(fn($i) => ['id' => $i->id, 'name' => $i->name, 'uom' => $i->uom])->values());
    const demandLocations = @json($lineLocations ?? []);
    const demandCategories = @json($lineCategories ?? []);
    const body = document.getElementById('demandLinesBody');
    const addBtn = document.getElementById('addDemandLine');
    if (!body || !addBtn) return;
    let idx = 0;

    function options() {
        return ['<option value="">Select item</option>'].concat(
            items.map(i => `<option value="${i.id}" data-uom="${i.uom}">${i.name}</option>`),
            ['<option value="__custom__">+ Custom item (not in inventory)</option>']
        ).join('');
    }

    function selectOptions(values, placeholder) {
        return ['<option value="">'+placeholder+'</option>'].concat(
            values.map(v => `<option value="${v}">${v}</option>`)
        ).join('');
    }

    function row(i) {
        return `<tr data-line>
            <td>
                <select name="lines[${i}][product_id]" class="form-select form-select-sm line-item">${options()}</select>
                <input type="text" name="lines[${i}][custom_item_name]" class="form-control form-control-sm mt-1 line-custom-name d-none" maxlength="200" placeholder="e.g. Fan repair">
            </td>
            <td><select name="lines[${i}][line_location]" class="form-select form-select-sm" required>${selectOptions(demandLocations, 'Select location')}</select></td>
            <td><select name="lines[${i}][line_category]" class="form-select form-select-sm" required>${selectOptions(demandCategories, 'Select category')}</select></td>
            <td><input type="number" name="lines[${i}][qty_uom]" class="form-control form-control-sm line-qty" min="0.001" step="0.001" required></td>
            <td><input type="text" name="lines[${i}][uom]" class="form-control form-control-sm line-uom" required maxlength="30"></td>
            <td><input type="number" name="lines[${i}][expected_rate]" class="form-control form-control-sm line-rate" min="0" step="0.01" value="0"></td>
            <td class="text-end small fw-semibold line-total">0</td>
            <td><button type="button" class="btn btn-sm btn-outline-danger rm">&times;</button></td>
        </tr>`;
    }

    function recalc(tr) {
        const qty = parseFloat(tr.querySelector('.line-qty')?.value || '0');
        const rate = parseFloat(tr.querySelector('.line-rate')?.value || '0');
        const total = Number.isFinite(qty) && Number.isFinite(rate) ? qty * rate : 0;
        tr.querySelector('.line-total').textContent = total.toFixed(2);
    }

    function wire(tr) {
        tr.querySelector('.line-item').addEventListener('change', (e) => {
            const opt = e.target.selectedOptions[0];
            const customInput = tr.querySelector('.line-custom-name');
            if (e.target.value === '__custom__') {
                customInput.classList.remove('d-none');
                customInput.required = true;
                e.target.value = '';
                tr.querySelector('.line-uom').value = tr.querySelector('.line-uom').value || 'job';
                customInput.focus();
                return;
            }
            customInput.classList.add('d-none');
            customInput.required = false;
            customInput.value = '';
            tr.querySelector('.line-uom').value = opt?.dataset?.uom || '';
        });
        tr.querySelector('.line-qty').addEventListener('input', () => recalc(tr));
        tr.querySelector('.line-rate').addEventListener('input', () => recalc(tr));
        tr.querySelector('.rm').addEventListener('click', () => {
            tr.remove();
            if (!body.querySelector('tr[data-line]')) addLine();
        });
    }

    function addLine() {
        body.insertAdjacentHTML('beforeend', row(idx++));
        wire(body.lastElementChild);
    }

    addBtn.addEventListener('click', addLine);
    addLine();
})();
</script>
@endsection

