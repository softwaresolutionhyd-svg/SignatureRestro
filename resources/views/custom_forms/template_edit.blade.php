@extends('layouts.admin')

@section('title', 'Edit Template — ' . config('app.name'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0">Edit Template: {{ $template->name }}</h4>
    <a href="{{ route('custom-forms.index') }}" class="btn btn-outline-secondary">Back</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('custom-forms.templates.update', $template) }}">
            @csrf
            @method('PUT')
            <div class="row g-2">
                <div class="col-12 col-md-5">
                    <label class="form-label small">Template name</label>
                    <input type="text" name="name" class="form-control" maxlength="120" required value="{{ $template->name }}">
                </div>
                <div class="col-12 col-md-5">
                    <label class="form-label small">Report heading</label>
                    <input type="text" name="heading" class="form-control" maxlength="200" required value="{{ $template->heading }}">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label small">Status</label>
                    <select name="active" class="form-select">
                        <option value="1" @selected($template->active)>Active</option>
                        <option value="0" @selected(!$template->active)>Inactive</option>
                    </select>
                </div>
            </div>
            <div class="form-check mt-2">
                <input type="hidden" name="show_remarks" value="0">
                <input class="form-check-input" type="checkbox" name="show_remarks" value="1" id="showRemarksEditTemplate" @checked((bool) $template->show_remarks)>
                <label class="form-check-label small" for="showRemarksEditTemplate">
                    Remarks column show karna hai
                </label>
            </div>

            <div class="table-responsive border rounded mt-3">
                <table class="table table-sm mb-0 align-middle" id="editRowsTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width:95px;">Ser</th>
                            <th style="width:150px;">Type</th>
                            <th>Label</th>
                            <th style="width:160px;">Key</th>
                            <th style="width:48px;"></th>
                        </tr>
                    </thead>
                    <tbody id="editRowsBody">
                        @foreach((array) $template->rows_json as $i => $row)
                            <tr>
                                <td>
                                    <input type="text" class="form-control form-control-sm" maxlength="20" name="rows[{{ $i }}][serial]" value="{{ $row['serial'] ?? '' }}">
                                </td>
                                <td>
                                    <select class="form-select form-select-sm" name="rows[{{ $i }}][type]">
                                        <option value="section" @selected(($row['type'] ?? 'item') === 'section')>Section</option>
                                        <option value="item" @selected(($row['type'] ?? 'item') === 'item')>Item</option>
                                        <option value="total" @selected(($row['type'] ?? 'item') === 'total')>Total</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" maxlength="200" required name="rows[{{ $i }}][label]" value="{{ $row['label'] ?? '' }}">
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" maxlength="200" required name="rows[{{ $i }}][key]" value="{{ $row['key'] ?? '' }}">
                                </td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="addEditRow">+ Add row</button>

            <div class="mt-3 d-flex justify-content-between">
                <a href="{{ route('custom-forms.fill', ['template' => $template, 'month' => now()->month, 'year' => now()->year]) }}" class="btn btn-outline-dark">Open current month</a>
                <div class="d-flex gap-2">
                    <form method="POST" action="{{ route('custom-forms.templates.destroy', $template) }}" onsubmit="return confirm('Template delete karna hai? Is se is template ki tamam reports bhi delete ho jayengi.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger">Delete Template</button>
                    </form>
                    <button class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    const body = document.getElementById('editRowsBody');
    const addBtn = document.getElementById('addEditRow');
    if (!body || !addBtn) return;

    function rowHtml(index) {
        return `<tr>
            <td><input type="text" class="form-control form-control-sm" maxlength="20" name="rows[${index}][serial]"></td>
            <td>
                <select class="form-select form-select-sm" name="rows[${index}][type]">
                    <option value="section">Section</option>
                    <option value="item" selected>Item</option>
                    <option value="total">Total</option>
                </select>
            </td>
            <td><input type="text" class="form-control form-control-sm" maxlength="200" required name="rows[${index}][label]"></td>
            <td><input type="text" class="form-control form-control-sm" maxlength="200" required name="rows[${index}][key]"></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
        </tr>`;
    }

    addBtn.addEventListener('click', function () {
        const idx = body.querySelectorAll('tr').length;
        body.insertAdjacentHTML('beforeend', rowHtml(idx));
    });

    body.addEventListener('click', function (e) {
        const btn = e.target.closest('.remove-row');
        if (!btn) return;
        btn.closest('tr')?.remove();
    });
})();
</script>
@endsection

