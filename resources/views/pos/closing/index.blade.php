@extends('layouts.admin')
@section('title', __('POS Closing').' — '.config('app.name'))

@section('content')
<div class="mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">{{ __('POS Closing') }}</h4>
        <div class="text-secondary small">{{ __('Session summary — count cash, then only manager/admin can end the session') }}</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">← {{ __('Dashboard') }}</a>
        <a href="{{ route('restaurant-pos.index') }}" class="btn btn-outline-primary btn-sm">{{ __('Restaurant POS') }}</a>
        <a href="{{ route('reports.pos-sessions') }}" class="btn btn-outline-secondary btn-sm">{{ __('Session Reports') }}</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
    </div>
@endif

@if(!empty($noOpenSession))
    <div class="card shadow-sm border-0">
        <div class="card-body text-center py-5">
            <i class="bi bi-cash-stack display-4 text-secondary mb-3 d-block"></i>
            <h5 class="fw-semibold">{{ __('No open POS session') }}</h5>
            <p class="text-secondary mb-4">{!! __('Cashier should tap :action on Restaurant POS when starting the shift.', ['action' => '<strong>'.e(__('Open POS Session')).'</strong>']) !!}</p>
            <a href="{{ route('restaurant-pos.index') }}" class="btn btn-primary">{{ __('Restaurant POS') }}</a>
        </div>
    </div>
@else
    @php
        $bizDate = $session->business_date?->format('d M Y') ?? $session->opened_at?->format('d M Y');
        $openedAt = $session->opened_at?->format('d M Y H:i');
        $cashMovements = $cashMovements ?? collect();
        $cashInTotal = (float) ($cash['cash_in'] ?? 0);
        $cashOutTotal = (float) ($cash['cash_out'] ?? 0);
        $cashInRows = $cashMovements->where('type', 'in')->values();
        $cashOutRows = $cashMovements->where('type', 'out')->values();
        $paymentsCash = (float) $stats['payments_cash'];
        $cashAfterMovements = round($paymentsCash + $cashInTotal - $cashOutTotal, 2);
        $totalPayments = round($cashAfterMovements + (float) $stats['payments_bank'] + (float) $stats['payments_card'], 2);
    @endphp

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="text-secondary small">{{ __('Business date') }}</div>
                    <div class="fw-bold fs-5">{{ $bizDate }}</div>
                    <div class="text-secondary small mt-2">{{ __('Session opened:') }} {{ $openedAt }}</div>
                    <div class="text-secondary small">{{ __('Session') }} #{{ $session->session_no ?? $session->id }}</div>
                    @if($session->user)
                        <div class="text-secondary small mt-1">{{ __('Cashier:') }} <strong>{{ $session->user->name }}</strong></div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100 border-start border-4 border-primary">
                <div class="card-body">
                    <div class="text-secondary small">{{ __('Gross Sale (so far today)') }}</div>
                    <div class="fw-bold fs-4 text-primary">{{ $currency }} {{ fmt_num($stats['gross_sales_total'] ?? ((float) $stats['net_sales_total'] + (float) $stats['service_charge_total']), 2) }}</div>
                    <div class="text-secondary small mt-1">
                        {{ __(':count bills', ['count' => $stats['sales_count']]) }}
                        @if($stats['refunds_count'] > 0)
                            · {{ __(':count refunds', ['count' => $stats['refunds_count']]) }}
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100 border-start border-4 border-success">
                <div class="card-body">
                    <div class="text-secondary small">{{ __('Cash in drawer (expected)') }}</div>
                    <div class="fw-bold fs-4 text-success">{{ $currency }} {{ fmt_num($amountToCollect, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
            <span class="fw-semibold">{{ __('Cash In / Cash Out') }}</span>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-success" id="pcCashInBtn">
                    <i class="bi bi-plus-circle me-1"></i> {{ __('Cash In') }}
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" id="pcCashOutBtn">
                    <i class="bi bi-dash-circle me-1"></i> {{ __('Cash Out') }}
                </button>
            </div>
        </div>
        <div class="card-body d-none" id="pcCashMovementFormWrap">
            <form method="POST" action="{{ route('restaurant-pos.cash-movement') }}" id="pcCashMovementForm">
                @csrf
                <input type="hidden" name="type" id="pcCashMovementType" value="in">
                <div class="mb-3">
                    <div class="small fw-semibold" id="pcCashMovementTitle">{{ __('Cash In') }}</div>
                    <div class="text-secondary small" id="pcCashMovementHint">{{ __('Adds to cash in drawer (expected).') }}</div>
                    <div class="text-secondary small mt-1">{{ __('Tab on Amount adds another line.') }}</div>
                </div>
                <div class="d-flex flex-column gap-2" id="pcCashLines"></div>
                @error('lines')
                    <div class="text-danger small mt-2">{{ $message }}</div>
                @enderror
                @error('lines.0.reason')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
                @error('lines.0.amount')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
                <div class="d-flex justify-content-end mt-3">
                    <button type="submit" class="btn btn-primary px-4" id="pcCashSubmitBtn">{{ __('Save') }}</button>
                </div>
            </form>
        </div>
        @if($cashMovements->isNotEmpty())
            <div class="card-body p-0 border-top">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">{{ __('Description') }}</th>
                            <th class="text-end pe-3" style="width:9rem;">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cashMovements as $mv)
                            <tr>
                                <td class="ps-3">
                                    <span class="badge {{ $mv->type === 'in' ? 'bg-success' : 'bg-danger' }} me-1">
                                        {{ $mv->type === 'in' ? __('Cash In') : __('Cash Out') }}
                                    </span>
                                    {{ $mv->reason ?: '—' }}
                                </td>
                                <td class="text-end pe-3 fw-semibold {{ $mv->type === 'in' ? 'text-success' : 'text-danger' }}">
                                    {{ $mv->type === 'in' ? '+' : '−' }} {{ $currency }} {{ fmt_num($mv->amount, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white fw-semibold">{{ __('Session summary') }}</div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">{{ __('Description') }}</th>
                        <th class="text-end pe-3">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="ps-3">{{ __('Gross sales (:count bills)', ['count' => $stats['sales_count']]) }}</td>
                        <td class="text-end pe-3 fw-semibold">{{ $currency }} {{ fmt_num($stats['sales_total'], 2) }}</td>
                    </tr>
                    @if((float) $stats['refunds_total'] > 0)
                    <tr>
                        <td class="ps-3 text-danger">{{ __('Refunds') }}</td>
                        <td class="text-end pe-3 text-danger">− {{ $currency }} {{ fmt_num($stats['refunds_total'], 2) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="ps-3">{{ __('Discount') }}</td>
                        <td class="text-end pe-3 text-danger">− {{ $currency }} {{ fmt_num($stats['discount_total'], 2) }}</td>
                    </tr>
                    <tr>
                        <td class="ps-3">{{ __('Service charges') }}
                            <span class="text-secondary small">— {{ __('included in Gross Sale') }}</span>
                        </td>
                        <td class="text-end pe-3">{{ $currency }} {{ fmt_num($stats['service_charge_total'], 2) }}</td>
                    </tr>
                    @if((float) $stats['tax_total'] > 0)
                    <tr>
                        <td class="ps-3">{{ __('Tax') }}</td>
                        <td class="text-end pe-3">{{ $currency }} {{ fmt_num($stats['tax_total'], 2) }}</td>
                    </tr>
                    @endif
                    @php
                        $creditOtherCount = (int) ($stats['credit_sales_other_count'] ?? $stats['credit_sales_count']);
                        $creditOtherTotal = (float) ($stats['credit_sales_other_total'] ?? $stats['credit_sales_total']);
                        $creditVisitorCount = (int) ($stats['credit_sales_visitor_expense_count'] ?? 0);
                        $creditVisitorTotal = (float) ($stats['credit_sales_visitor_expense_total'] ?? 0);
                    @endphp
                    @if($creditOtherCount > 0)
                    <tr>
                        <td class="ps-3">
                            {{ __('Credit sales (:count)', ['count' => $creditOtherCount]) }}
                            <span class="text-secondary small">— {{ __('Credit Book, not in cash drawer') }}</span>
                        </td>
                        <td class="text-end pe-3">{{ $currency }} {{ fmt_num($creditOtherTotal, 2) }}</td>
                    </tr>
                    @endif
                    @if($creditVisitorCount > 0)
                    <tr>
                        <td class="ps-3">
                            {{ __('Credit sales (:count)', ['count' => $creditVisitorCount]) }}
                            <span class="fw-semibold">({{ __('Visitor Expense') }})</span>
                            <span class="text-secondary small">— {{ __('Credit Book, not in cash drawer') }}</span>
                        </td>
                        <td class="text-end pe-3">{{ $currency }} {{ fmt_num($creditVisitorTotal, 2) }}</td>
                    </tr>
                    @elseif($stats['credit_sales_count'] > 0 && $creditOtherCount === 0 && $creditVisitorCount === 0)
                    <tr>
                        <td class="ps-3">
                            {{ __('Credit sales (:count)', ['count' => $stats['credit_sales_count']]) }}
                            <span class="text-secondary small">— {{ __('Credit Book, not in cash drawer') }}</span>
                        </td>
                        <td class="text-end pe-3">{{ $currency }} {{ fmt_num($stats['credit_sales_total'], 2) }}</td>
                    </tr>
                    @endif
                    <tr class="table-light">
                        <td class="ps-3 fw-bold">{{ __('Net Sale') }}</td>
                        <td class="text-end pe-3 fw-bold">{{ $currency }} {{ fmt_num($stats['net_sales_total'], 2) }}</td>
                    </tr>
                    @foreach($cashInRows as $mv)
                    <tr>
                        <td class="ps-3 text-success">{{ __('Cash In') }} — {{ $mv->reason ?: '—' }}</td>
                        <td class="text-end pe-3 text-success">+ {{ $currency }} {{ fmt_num($mv->amount, 2) }}</td>
                    </tr>
                    @endforeach
                    @foreach($cashOutRows as $mv)
                    <tr>
                        <td class="ps-3 text-danger">{{ __('Cash Out') }} — {{ $mv->reason ?: '—' }}</td>
                        <td class="text-end pe-3 text-danger">− {{ $currency }} {{ fmt_num($mv->amount, 2) }}</td>
                    </tr>
                    @endforeach
                    <tr><td colspan="2" class="py-1"></td></tr>
                    <tr>
                        <td class="ps-3"><i class="bi bi-cash-coin me-1 text-success"></i> {{ __('Cash') }} <span class="text-secondary small">({{ __('credit sales excluded') }}@if($cashInTotal > 0 || $cashOutTotal > 0), {{ __('after cash in/out') }}@endif)</span></td>
                        <td class="text-end pe-3 fw-semibold">{{ $currency }} {{ fmt_num($cashAfterMovements, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="ps-3"><i class="bi bi-bank me-1 text-primary"></i> {{ __('Bank') }}</td>
                        <td class="text-end pe-3 fw-semibold">{{ $currency }} {{ fmt_num($stats['payments_bank'], 2) }}</td>
                    </tr>
                    <tr>
                        <td class="ps-3"><i class="bi bi-credit-card me-1 text-info"></i> {{ __('Card') }}</td>
                        <td class="text-end pe-3 fw-semibold">{{ $currency }} {{ fmt_num($stats['payments_card'], 2) }}</td>
                    </tr>
                    <tr class="table-light">
                        <td class="ps-3 fw-bold">{{ __('Total payments') }}</td>
                        <td class="text-end pe-3 fw-bold">{{ $currency }} {{ fmt_num($totalPayments, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="ps-3 fw-bold">{{ __('Cash in drawer (expected)') }}</td>
                        <td class="text-end pe-3 fw-bold text-success">{{ $currency }} {{ fmt_num($amountToCollect, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    @if(!$canClose)
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <strong>{{ __(':count pending bill(s)', ['count' => $stats['held_count']]) }}</strong>
            {{ __('are still open. Pay or discard them on Restaurant POS first, then close the session.') }}
            <a href="{{ route('restaurant-pos.index') }}" class="alert-link">{{ __('Restaurant POS') }} →</a>
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white fw-semibold">{{ __('End session') }}</div>
        <div class="card-body">
            <p class="text-secondary small mb-3">
                {!! __('First print the :summary, count cash, then enter the counted amount and end the session.', ['summary' => '<strong>'.e(__('Session Summary')).'</strong>']) !!}
            </p>
            <div class="d-flex flex-wrap gap-2 mb-4">
                <a href="{{ route('restaurant-pos.closing.print') }}" target="_blank" class="btn btn-outline-primary">
                    <i class="bi bi-file-earmark-pdf me-1"></i> {{ __('Session Report (PDF)') }}
                </a>
                <a href="{{ route('restaurant-pos.closing.print', ['auto' => 1]) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-printer me-1"></i> {{ __('Print auto') }}
                </a>
            </div>

            <form method="POST" action="{{ route('restaurant-pos.session.close') }}" class="row g-3 align-items-end"
                  @if($canClose) onsubmit="return confirm(@json(__('Close POS session? Poora software + MySQL offline backup/ folder mein save hoga — 1–3 minute lag sakte hain. PC tab tak on rakhein.')));" @endif>
                @csrf
                <div class="col-md-3">
                    <label class="form-label" for="counted_cash">{{ __('Counted cash (drawer)') }}</label>
                    <div class="input-group">
                        <span class="input-group-text">{{ $currency }}</span>
                        <input type="number" step="0.01" min="0" name="counted_cash" id="counted_cash"
                               class="form-control" value="{{ number_format($amountToCollect, 2, '.', '') }}" @disabled(!$canClose)>
                    </div>
                    <div class="form-text">{{ __('Expected:') }} {{ $currency }} {{ fmt_num($amountToCollect, 2) }}</div>
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="note">{{ __('Closing note (optional)') }}</label>
                    <input type="text" name="note" id="note" class="form-control" maxlength="255"
                           placeholder="{{ __('e.g. Shift handover to manager') }}" @disabled(!$canClose)>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-danger w-100" @disabled(!$canClose)>
                        <i class="bi bi-box-arrow-right me-1"></i> {{ __('End POS Session') }}
                    </button>
                    <div class="form-text mt-1">{{ __('End pe automatic offline backup (code + MySQL) → backup/ folder') }}</div>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection

@section('scripts')
<script>
(function () {
    const wrap = document.getElementById('pcCashMovementFormWrap');
    const typeInput = document.getElementById('pcCashMovementType');
    const title = document.getElementById('pcCashMovementTitle');
    const hint = document.getElementById('pcCashMovementHint');
    const linesEl = document.getElementById('pcCashLines');
    const form = document.getElementById('pcCashMovementForm');
    const inBtn = document.getElementById('pcCashInBtn');
    const outBtn = document.getElementById('pcCashOutBtn');
    if (!wrap || !typeInput || !linesEl || !form) return;

    const labels = {
        inTitle: @json(__('Cash In')),
        outTitle: @json(__('Cash Out')),
        inHint: @json(__('Adds to cash in drawer (expected).')),
        outHint: @json(__('Subtracts from net sales and cash in drawer.')),
        desc: @json(__('Description')),
        amount: @json(__('Amount')),
        currency: @json($currency),
        placeholder: @json(__('e.g. Petty cash / change float')),
        remove: @json(__('Remove')),
    };

    const oldLines = @json(old('lines', [['reason' => '', 'amount' => '']]));

    function reindexLines() {
        Array.from(linesEl.querySelectorAll('.pc-cash-line')).forEach((row, i) => {
            const reason = row.querySelector('.pc-cash-reason');
            const amount = row.querySelector('.pc-cash-amount');
            if (reason) {
                reason.name = `lines[${i}][reason]`;
                reason.id = `pcCashReason${i}`;
            }
            if (amount) {
                amount.name = `lines[${i}][amount]`;
                amount.id = `pcCashAmount${i}`;
            }
            const removeBtn = row.querySelector('.pc-cash-remove');
            if (removeBtn) {
                removeBtn.classList.toggle('d-none', linesEl.querySelectorAll('.pc-cash-line').length <= 1);
            }
        });
    }

    function addLine(prefill = { reason: '', amount: '' }, focus = false) {
        const i = linesEl.querySelectorAll('.pc-cash-line').length;
        const row = document.createElement('div');
        row.className = 'row g-2 align-items-end pc-cash-line';
        row.innerHTML = `
            <div class="col-md-7">
                ${i === 0 ? `<label class="form-label" for="pcCashReason${i}">${labels.desc}</label>` : `<label class="form-label d-md-none" for="pcCashReason${i}">${labels.desc}</label>`}
                <input type="text" class="form-control pc-cash-reason" name="lines[${i}][reason]" id="pcCashReason${i}"
                       maxlength="255" placeholder="${labels.placeholder}" autocomplete="off">
            </div>
            <div class="col-md-3">
                ${i === 0 ? `<label class="form-label" for="pcCashAmount${i}">${labels.amount}</label>` : `<label class="form-label d-md-none" for="pcCashAmount${i}">${labels.amount}</label>`}
                <div class="input-group">
                    <span class="input-group-text">${labels.currency}</span>
                    <input type="number" step="0.01" min="0.01" class="form-control pc-cash-amount" name="lines[${i}][amount]" id="pcCashAmount${i}"
                           inputmode="decimal" autocomplete="off">
                </div>
            </div>
            <div class="col-md-2">
                ${i === 0 ? `<label class="form-label d-none d-md-block">&nbsp;</label>` : ''}
                <button type="button" class="btn btn-outline-secondary w-100 pc-cash-remove" tabindex="-1" title="${labels.remove}" aria-label="${labels.remove}">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        `;
        linesEl.appendChild(row);
        const reasonInput = row.querySelector('.pc-cash-reason');
        const amountInput = row.querySelector('.pc-cash-amount');
        if (reasonInput) reasonInput.value = prefill.reason ?? '';
        if (amountInput) amountInput.value = prefill.amount ?? '';
        reindexLines();
        if (focus) {
            reasonInput?.focus();
        }
        return row;
    }

    function resetLines(seed) {
        linesEl.innerHTML = '';
        const rows = Array.isArray(seed) && seed.length ? seed : [{ reason: '', amount: '' }];
        rows.forEach((line, idx) => addLine(line, false));
        reindexLines();
        linesEl.querySelector('.pc-cash-reason')?.focus();
    }

    function openForm(type) {
        typeInput.value = type;
        title.textContent = type === 'out' ? labels.outTitle : labels.inTitle;
        hint.textContent = type === 'out' ? labels.outHint : labels.inHint;
        wrap.classList.remove('d-none');
        resetLines([{ reason: '', amount: '' }]);
    }

    linesEl.addEventListener('keydown', (e) => {
        if (e.key !== 'Tab' || e.shiftKey) return;
        const amount = e.target.closest?.('.pc-cash-amount');
        if (!amount || !linesEl.contains(amount)) return;

        const rows = Array.from(linesEl.querySelectorAll('.pc-cash-line'));
        const row = amount.closest('.pc-cash-line');
        const isLast = row === rows[rows.length - 1];
        if (!isLast) return;

        const reason = row.querySelector('.pc-cash-reason')?.value?.trim() || '';
        const amt = String(amount.value || '').trim();
        if (!reason && !amt) return;

        e.preventDefault();
        addLine({ reason: '', amount: '' }, true);
    });

    linesEl.addEventListener('click', (e) => {
        const btn = e.target.closest?.('.pc-cash-remove');
        if (!btn) return;
        const row = btn.closest('.pc-cash-line');
        if (!row) return;
        if (linesEl.querySelectorAll('.pc-cash-line').length <= 1) {
            row.querySelector('.pc-cash-reason').value = '';
            row.querySelector('.pc-cash-amount').value = '';
            row.querySelector('.pc-cash-reason')?.focus();
            return;
        }
        row.remove();
        reindexLines();
    });

    form.addEventListener('submit', () => {
        // Drop trailing blank lines before submit.
        Array.from(linesEl.querySelectorAll('.pc-cash-line')).forEach((row) => {
            const reason = row.querySelector('.pc-cash-reason')?.value?.trim() || '';
            const amt = String(row.querySelector('.pc-cash-amount')?.value || '').trim();
            if (!reason && !amt && linesEl.querySelectorAll('.pc-cash-line').length > 1) {
                row.remove();
            }
        });
        reindexLines();
    });

    inBtn?.addEventListener('click', () => openForm('in'));
    outBtn?.addEventListener('click', () => openForm('out'));

    @if($errors->has('lines') || $errors->has('type') || collect($errors->keys())->contains(fn ($k) => str_starts_with($k, 'lines.')))
        typeInput.value = @json(old('type', 'in'));
        title.textContent = typeInput.value === 'out' ? labels.outTitle : labels.inTitle;
        hint.textContent = typeInput.value === 'out' ? labels.outHint : labels.inHint;
        wrap.classList.remove('d-none');
        resetLines(oldLines);
    @endif
})();
</script>
@endsection
