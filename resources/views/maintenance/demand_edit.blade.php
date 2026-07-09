@extends('layouts.admin')

@section('title', 'Edit Draft Demand — ' . config('app.name'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">Edit Draft Demand #{{ $demand->id }}</h4>
        <div class="small text-secondary">Draft save karte raho, final hone par editable nahi rahega.</div>
    </div>
    <a href="{{ route('maintenance.index') }}" class="btn btn-outline-secondary">Back</a>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold">Draft Demand</div>
    <div class="card-body">
        <form method="POST" action="{{ route('maintenance.demands.update', $demand) }}" class="row g-2" id="editDemandForm">
            @csrf
            @method('PUT')
            <div class="col-12 col-md-4">
                <label class="form-label small">Demand date</label>
                <input type="date" name="demand_date" class="form-control" required value="{{ optional($demand->demand_date)->toDateString() }}">
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label small">Needed date</label>
                <input type="date" name="needed_date" class="form-control" value="{{ optional($demand->needed_date)->toDateString() }}">
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label small">Requested by</label>
                <input type="text" name="requested_by" class="form-control" required maxlength="120" value="{{ $demand->requested_by }}">
            </div>

            <div class="col-12">
                <div class="table-responsive border rounded">
                    <table class="table table-sm mb-0 align-middle">
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
                <input type="text" name="note" class="form-control" maxlength="255" value="{{ $demand->note }}">
            </div>
            <div class="col-12 d-grid">
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary w-50" type="submit" name="save_mode" value="draft">Save Draft</button>
                    <button class="btn btn-outline-primary w-50" type="submit" name="save_mode" value="final">Finalize Demand</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const items = @json($items->map(fn($i) => ['id' => $i->id, 'name' => $i->name, 'uom' => $i->uom])->values());
    const demandLocations = @json($lineLocations ?? []);
    const demandCategories = @json($lineCategories ?? []);
    const existingLines = @json($demand->lines->map(function($line){
        return [
            'product_id' => $line->is_custom ? '' : $line->product_id,
            'custom_item_name' => $line->is_custom ? $line->item_name : '',
            'line_location' => $line->line_location,
            'line_category' => $line->line_category,
            'qty_uom' => (float) $line->qty_uom,
            'uom' => $line->uom,
            'expected_rate' => (float) $line->expected_rate,
        ];
    })->values());

    const body = document.getElementById('demandLinesBody');
    const addBtn = document.getElementById('addDemandLine');
    if (!body || !addBtn) return;
    let idx = 0;

    function options(selectedValue = '') {
        const opts = ['<option value="">Select item</option>']
            .concat(items.map(i => `<option value="${i.id}" data-uom="${i.uom}" ${String(i.id) === String(selectedValue) ? 'selected' : ''}>${i.name}</option>`))
            .concat(['<option value="__custom__">+ Custom item (not in inventory)</option>']);
        return opts.join('');
    }
    function selectOptions(values, placeholder, selectedValue = '') {
        return ['<option value="">'+placeholder+'</option>']
            .concat(values.map(v => `<option value="${v}" ${String(v) === String(selectedValue) ? 'selected' : ''}>${v}</option>`))
            .join('');
    }

    function row(i, line = null) {
        const l = line || {};
        const customClass = l.custom_item_name ? '' : 'd-none';
        const customRequired = l.custom_item_name ? 'required' : '';
        const total = (parseFloat(l.qty_uom || 0) * parseFloat(l.expected_rate || 0)).toFixed(2);
        return `<tr data-line>
            <td>
                <select name="lines[${i}][product_id]" class="form-select form-select-sm line-item">${options(l.product_id || '')}</select>
                <input type="text" name="lines[${i}][custom_item_name]" class="form-control form-control-sm mt-1 line-custom-name ${customClass}" maxlength="200" placeholder="e.g. Fan repair" value="${l.custom_item_name || ''}" ${customRequired}>
            </td>
            <td><select name="lines[${i}][line_location]" class="form-select form-select-sm" required>${selectOptions(demandLocations, 'Select location', l.line_location || '')}</select></td>
            <td><select name="lines[${i}][line_category]" class="form-select form-select-sm" required>${selectOptions(demandCategories, 'Select category', l.line_category || '')}</select></td>
            <td><input type="number" name="lines[${i}][qty_uom]" class="form-control form-control-sm line-qty" min="0.001" step="0.001" required value="${l.qty_uom || ''}"></td>
            <td><input type="text" name="lines[${i}][uom]" class="form-control form-control-sm line-uom" required maxlength="30" value="${l.uom || ''}"></td>
            <td><input type="number" name="lines[${i}][expected_rate]" class="form-control form-control-sm line-rate" min="0" step="0.01" value="${l.expected_rate || 0}"></td>
            <td class="text-end small fw-semibold line-total">${total}</td>
            <td><button type="button" class="btn btn-sm btn-outline-danger rm">&times;</button></td>
        </tr>`;
    }

    function recalc(tr) {
        const qty = parseFloat(tr.querySelector('.line-qty')?.value || '0');
        const rate = parseFloat(tr.querySelector('.line-rate')?.value || '0');
        tr.querySelector('.line-total').textContent = (Number.isFinite(qty) && Number.isFinite(rate) ? qty * rate : 0).toFixed(2);
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
    function addLine(line = null) {
        body.insertAdjacentHTML('beforeend', row(idx++, line));
        wire(body.lastElementChild);
    }

    addBtn.addEventListener('click', function(){ addLine(); });
    if (existingLines.length) {
        existingLines.forEach((line) => addLine(line));
    } else {
        addLine();
    }
})();
</script>
@endsection

