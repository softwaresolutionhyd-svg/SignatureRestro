@extends('layouts.admin')

@section('title', 'Custom Forms — ' . config('app.name'))

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-0">Custom Forms Reports</h4>
        <div class="text-secondary small">Month/year select karo, template design karo, aur us month ka report save/print karo.</div>
    </div>
    <form method="GET" class="d-flex gap-2">
        <select name="month" class="form-select">
            @for($m = 1; $m <= 12; $m++)
                <option value="{{ $m }}" @selected($m === (int) $month)>{{ date('F', mktime(0,0,0,$m,1)) }}</option>
            @endfor
        </select>
        <input type="number" name="year" class="form-control" value="{{ $year }}" min="2000" max="2100">
        <button class="btn btn-outline-secondary">Load</button>
    </form>
</div>

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="row g-3">
    <div class="col-12 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">New Template Design</div>
            <div class="card-body">
                <form method="POST" action="{{ route('custom-forms.templates.store') }}" id="templateBuilderForm">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label small">Template name</label>
                        <input type="text" name="name" class="form-control" maxlength="120" required placeholder="Expenditure Summary">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Report heading</label>
                        <input type="text" name="heading" class="form-control" maxlength="200" required placeholder="EXPENDITURE SUMMARY HOTEL">
                    </div>
                    <div class="form-check mb-2">
                        <input type="hidden" name="show_remarks" value="0">
                        <input class="form-check-input" type="checkbox" name="show_remarks" value="1" id="showRemarksNewTemplate" checked>
                        <label class="form-check-label small" for="showRemarksNewTemplate">
                            Remarks column show karna hai
                        </label>
                    </div>
                    <div class="table-responsive border rounded">
                        <table class="table table-sm mb-0 align-middle" id="templateRowsTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:95px;">Ser</th>
                                    <th style="width:150px;">Row type</th>
                                    <th>Label / Detail</th>
                                    <th style="width:48px;"></th>
                                </tr>
                            </thead>
                            <tbody id="templateRowsBody"></tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="addTemplateRow">+ Add row</button>
                    <div class="d-grid mt-3">
                        <button class="btn btn-primary" type="submit">Save Template</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-7">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">Templates</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Template</th>
                                <th>Rows</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($templates as $t)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $t->name }}</div>
                                        <div class="small text-secondary">{{ $t->heading }}</div>
                                    </td>
                                    <td>{{ count((array) $t->rows_json) }}</td>
                                    <td><span class="badge {{ $t->active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $t->active ? 'Active' : 'Inactive' }}</span></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('custom-forms.fill', ['template' => $t->id, 'month' => $month, 'year' => $year]) }}">Open {{ date('M', mktime(0,0,0,$month,1)) }} {{ $year }}</a>
                                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('custom-forms.templates.edit', $t) }}">Design</a>
                                        <form method="POST" action="{{ route('custom-forms.templates.destroy', $t) }}" class="d-inline" onsubmit="return confirm('Template delete karna hai? Is se is template ki tamam reports bhi delete ho jayengi.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center py-4 text-secondary">No template yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">Saved Reports ({{ date('F', mktime(0,0,0,$month,1)) }} {{ $year }})</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Template</th>
                                <th>Saved at</th>
                                <th class="text-end">View</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reports as $r)
                                <tr>
                                    <td>{{ $r->template?->name ?? 'Template deleted' }}</td>
                                    <td>{{ optional($r->updated_at)->format('d M Y h:i A') }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('custom-forms.reports.show', $r) }}" class="btn btn-sm btn-dark">Print View</a>
                                        <form method="POST" action="{{ route('custom-forms.reports.destroy', $r) }}" class="d-inline" onsubmit="return confirm('Ye saved report delete karni hai?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center py-4 text-secondary">No report saved for selected period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const body = document.getElementById('templateRowsBody');
    const btnAdd = document.getElementById('addTemplateRow');
    if (!body || !btnAdd) return;

    function rowHtml(index, type = 'item', label = '', serial = '') {
        return `<tr>
            <td><input name="rows[${index}][serial]" class="form-control form-control-sm" maxlength="20" value="${serial}" placeholder="${index + 1}."></td>
            <td>
                <select name="rows[${index}][type]" class="form-select form-select-sm">
                    <option value="section" ${type === 'section' ? 'selected' : ''}>Section heading</option>
                    <option value="item" ${type === 'item' ? 'selected' : ''}>Normal line</option>
                    <option value="total" ${type === 'total' ? 'selected' : ''}>Total line</option>
                </select>
            </td>
            <td><input name="rows[${index}][label]" class="form-control form-control-sm" maxlength="200" required value="${label}"></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
        </tr>`;
    }

    function addRow(type = 'item', label = '', serial = '') {
        const idx = body.querySelectorAll('tr').length;
        body.insertAdjacentHTML('beforeend', rowHtml(idx, type, label, serial));
    }

    btnAdd.addEventListener('click', function () { addRow(); });
    body.addEventListener('click', function (e) {
        const btn = e.target.closest('.remove-row');
        if (!btn) return;
        btn.closest('tr')?.remove();
    });

    addRow('section', 'GR INCOME', '');
    addRow('item', 'Room Rent', '1.');
    addRow('item', 'Electricity Charges', '2.');
    addRow('total', 'TOTAL', '');
})();
</script>
@endsection

