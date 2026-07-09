@php
    $entry = $entry ?? null;
    $lines = old('lines', $entry ? $entry->lines->map(fn ($l) => [
        'account_id' => $l->account_id,
        'description' => $l->description,
        'debit' => $l->debit,
        'credit' => $l->credit,
    ])->toArray() : [
        ['account_id' => '', 'description' => '', 'debit' => '', 'credit' => ''],
        ['account_id' => '', 'description' => '', 'debit' => '', 'credit' => ''],
    ]);
@endphp

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <label class="form-label">Entry Date <span class="text-danger">*</span></label>
        <input type="date" name="entry_date" class="form-control" required
               value="{{ old('entry_date', optional($entry->entry_date ?? now())->format('Y-m-d')) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Reference</label>
        <input type="text" name="reference" class="form-control" maxlength="100"
               value="{{ old('reference', $entry->reference ?? '') }}" placeholder="Invoice #, cheque #">
    </div>
    <div class="col-md-6">
        <label class="form-label">Description</label>
        <input type="text" name="description" class="form-control" maxlength="500"
               value="{{ old('description', $entry->description ?? '') }}">
    </div>
</div>

@if($errors->has('lines'))
<div class="alert alert-danger py-2">{{ $errors->first('lines') }}</div>
@endif

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span>Journal Lines</span>
        <button type="button" class="btn btn-outline-primary btn-sm" id="addJournalLine">
            <i class="bi bi-plus-lg"></i> Add Line
        </button>
    </div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle" id="journalLinesTable">
            <thead class="table-light">
                <tr>
                    <th style="min-width:220px">Account</th>
                    <th>Line Description</th>
                    <th class="text-end" style="width:130px">Debit</th>
                    <th class="text-end" style="width:130px">Credit</th>
                    <th style="width:50px"></th>
                </tr>
            </thead>
            <tbody id="journalLinesBody">
                @foreach($lines as $i => $line)
                <tr class="journal-line-row">
                    <td>
                        <select name="lines[{{ $i }}][account_id]" class="form-select form-select-sm" required>
                            <option value="">Select account</option>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}" @selected((string)($line['account_id'] ?? '') === (string)$acc->id)>
                                    {{ $acc->code }} — {{ $acc->name }}
                                </option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <input type="text" name="lines[{{ $i }}][description]" class="form-control form-control-sm"
                               value="{{ $line['description'] ?? '' }}">
                    </td>
                    <td>
                        <input type="number" step="0.01" min="0" name="lines[{{ $i }}][debit]"
                               class="form-control form-control-sm text-end line-debit"
                               value="{{ $line['debit'] !== '' && $line['debit'] !== null ? $line['debit'] : '' }}">
                    </td>
                    <td>
                        <input type="number" step="0.01" min="0" name="lines[{{ $i }}][credit]"
                               class="form-control form-control-sm text-end line-credit"
                               value="{{ $line['credit'] !== '' && $line['credit'] !== null ? $line['credit'] : '' }}">
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-link btn-sm text-danger remove-line" title="Remove">&times;</button>
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="2" class="text-end">Totals</th>
                    <th class="text-end" id="totalDebit">0.00</th>
                    <th class="text-end" id="totalCredit">0.00</th>
                    <th></th>
                </tr>
                <tr>
                    <th colspan="2" class="text-end">Difference</th>
                    <th colspan="2" class="text-center" id="totalDiff">0.00</th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<template id="journalLineTemplate">
    <tr class="journal-line-row">
        <td>
            <select name="lines[__INDEX__][account_id]" class="form-select form-select-sm" required>
                <option value="">Select account</option>
                @foreach($accounts as $acc)
                    <option value="{{ $acc->id }}">{{ $acc->code }} — {{ $acc->name }}</option>
                @endforeach
            </select>
        </td>
        <td><input type="text" name="lines[__INDEX__][description]" class="form-control form-control-sm"></td>
        <td><input type="number" step="0.01" min="0" name="lines[__INDEX__][debit]" class="form-control form-control-sm text-end line-debit"></td>
        <td><input type="number" step="0.01" min="0" name="lines[__INDEX__][credit]" class="form-control form-control-sm text-end line-credit"></td>
        <td class="text-center"><button type="button" class="btn btn-link btn-sm text-danger remove-line">&times;</button></td>
    </tr>
</template>

@push('scripts')
<script>
(function () {
    const body = document.getElementById('journalLinesBody');
    const tpl = document.getElementById('journalLineTemplate');
    let lineIndex = body.querySelectorAll('.journal-line-row').length;

    function recalc() {
        let debit = 0, credit = 0;
        body.querySelectorAll('.journal-line-row').forEach(row => {
            debit += parseFloat(row.querySelector('.line-debit')?.value || 0) || 0;
            credit += parseFloat(row.querySelector('.line-credit')?.value || 0) || 0;
        });
        const diff = Math.round((debit - credit) * 100) / 100;
        document.getElementById('totalDebit').textContent = debit.toFixed(2);
        document.getElementById('totalCredit').textContent = credit.toFixed(2);
        const diffEl = document.getElementById('totalDiff');
        diffEl.textContent = diff.toFixed(2);
        diffEl.className = diff === 0 && debit > 0 ? 'text-center text-success fw-semibold' : 'text-center text-danger fw-semibold';
    }

    function bindRow(row) {
        row.querySelectorAll('.line-debit, .line-credit').forEach(inp => {
            inp.addEventListener('input', () => {
                if (inp.classList.contains('line-debit') && inp.value) {
                    const credit = row.querySelector('.line-credit');
                    if (credit) credit.value = '';
                }
                if (inp.classList.contains('line-credit') && inp.value) {
                    const debit = row.querySelector('.line-debit');
                    if (debit) debit.value = '';
                }
                recalc();
            });
        });
        row.querySelector('.remove-line')?.addEventListener('click', () => {
            if (body.querySelectorAll('.journal-line-row').length <= 2) return;
            row.remove();
            reindex();
            recalc();
        });
    }

    function reindex() {
        body.querySelectorAll('.journal-line-row').forEach((row, i) => {
            row.querySelectorAll('[name^="lines["]').forEach(el => {
                el.name = el.name.replace(/lines\[\d+\]/, 'lines[' + i + ']');
            });
        });
        lineIndex = body.querySelectorAll('.journal-line-row').length;
    }

    document.getElementById('addJournalLine')?.addEventListener('click', () => {
        const html = tpl.innerHTML.replace(/__INDEX__/g, lineIndex);
        body.insertAdjacentHTML('beforeend', html);
        bindRow(body.lastElementChild);
        lineIndex++;
        recalc();
    });

    body.querySelectorAll('.journal-line-row').forEach(bindRow);
    recalc();
})();
</script>
@endpush
