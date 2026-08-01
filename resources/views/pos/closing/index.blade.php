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
                    <div class="text-secondary small">{{ __('Net sales (so far today)') }}</div>
                    <div class="fw-bold fs-4 text-primary">{{ $currency }} {{ fmt_num($stats['net_sales_total'], 2) }}</div>
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
                        <td class="ps-3">{{ __('Service charges') }}</td>
                        <td class="text-end pe-3 text-danger">− {{ $currency }} {{ fmt_num($stats['service_charge_total'], 2) }}</td>
                    </tr>
                    @if((float) $stats['tax_total'] > 0)
                    <tr>
                        <td class="ps-3">{{ __('Tax') }}</td>
                        <td class="text-end pe-3">{{ $currency }} {{ fmt_num($stats['tax_total'], 2) }}</td>
                    </tr>
                    @endif
                    @if($stats['credit_sales_count'] > 0)
                    <tr>
                        <td class="ps-3">
                            {{ __('Credit sales (:count)', ['count' => $stats['credit_sales_count']]) }}
                            <span class="text-secondary small">— {{ __('Credit Book, not in cash drawer') }}</span>
                        </td>
                        <td class="text-end pe-3">{{ $currency }} {{ fmt_num($stats['credit_sales_total'], 2) }}</td>
                    </tr>
                    @endif
                    <tr class="table-light">
                        <td class="ps-3 fw-bold">{{ __('Net sales') }}</td>
                        <td class="text-end pe-3 fw-bold">{{ $currency }} {{ fmt_num($stats['net_sales_total'], 2) }}</td>
                    </tr>
                    <tr><td colspan="2" class="py-1"></td></tr>
                    <tr>
                        <td class="ps-3"><i class="bi bi-cash-coin me-1 text-success"></i> {{ __('Cash') }} <span class="text-secondary small">({{ __('credit sales excluded') }})</span></td>
                        <td class="text-end pe-3 fw-semibold">{{ $currency }} {{ fmt_num($stats['payments_cash'], 2) }}</td>
                    </tr>
                    <tr>
                        <td class="ps-3"><i class="bi bi-bank me-1 text-primary"></i> {{ __('Bank') }}</td>
                        <td class="text-end pe-3 fw-semibold">{{ $currency }} {{ fmt_num($stats['payments_bank'], 2) }}</td>
                    </tr>
                    <tr>
                        <td class="ps-3"><i class="bi bi-credit-card me-1 text-info"></i> {{ __('Card') }}</td>
                        <td class="text-end pe-3 fw-semibold">{{ $currency }} {{ fmt_num($stats['payments_card'], 2) }}</td>
                    </tr>
                    @if((float) $cash['cash_in'] > 0 || (float) $cash['cash_out'] > 0)
                    <tr>
                        <td class="ps-3 text-secondary small">{{ __('Cash in / out') }}</td>
                        <td class="text-end pe-3 small text-secondary">+{{ fmt_num($cash['cash_in'], 2) }} / −{{ fmt_num($cash['cash_out'], 2) }}</td>
                    </tr>
                    @endif
                    <tr class="table-light">
                        <td class="ps-3 fw-bold">{{ __('Total payments') }}</td>
                        <td class="text-end pe-3 fw-bold">{{ $currency }} {{ fmt_num($stats['payments_cash'] + $stats['payments_bank'] + $stats['payments_card'], 2) }}</td>
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
                  @if($canClose) onsubmit="return confirm(@json(__('Do you want to close this POS session?')));" @endif>
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
                </div>
            </form>
        </div>
    </div>
@endif
@endsection
