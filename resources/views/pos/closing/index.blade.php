@extends('layouts.admin')
@section('title', 'POS Closing — ' . config('app.name'))

@section('content')
<div class="mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">POS Closing</h4>
        <div class="text-secondary small">Aaj ki session summary — cash count karke session end karein</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">← Dashboard</a>
        <a href="{{ route('restaurant-pos.index') }}" class="btn btn-outline-primary btn-sm">Restaurant POS</a>
        <a href="{{ route('reports.pos-sessions') }}" class="btn btn-outline-secondary btn-sm">Session Reports</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if(!empty($noOpenSession))
    <div class="card shadow-sm border-0">
        <div class="card-body text-center py-5">
            <i class="bi bi-cash-stack display-4 text-secondary mb-3 d-block"></i>
            <h5 class="fw-semibold">Koi open POS session nahi hai</h5>
            <p class="text-secondary mb-4">Cashier subah shift shuru karte waqt Restaurant POS par <strong>Open POS Session</strong> dabaye.</p>
            <a href="{{ route('restaurant-pos.index') }}" class="btn btn-primary">Restaurant POS</a>
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
                    <div class="text-secondary small">Business date</div>
                    <div class="fw-bold fs-5">{{ $bizDate }}</div>
                    <div class="text-secondary small mt-2">Session opened: {{ $openedAt }}</div>
                    <div class="text-secondary small">Session #{{ $session->session_no ?? $session->id }}</div>
                    @if($session->user)
                        <div class="text-secondary small mt-1">Cashier: <strong>{{ $session->user->name }}</strong></div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100 border-start border-4 border-primary">
                <div class="card-body">
                    <div class="text-secondary small">Net sales (aaj abhi tak)</div>
                    <div class="fw-bold fs-4 text-primary">{{ $currency }} {{ fmt_num($stats['net_sales_total'], 2) }}</div>
                    <div class="text-secondary small mt-1">{{ $stats['sales_count'] }} bills @if($stats['refunds_count'] > 0) · {{ $stats['refunds_count'] }} refunds @endif</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100 border-start border-4 border-success">
                <div class="card-body">
                    <div class="text-secondary small">Cash in drawer (expected)</div>
                    <div class="fw-bold fs-4 text-success">{{ $currency }} {{ fmt_num($amountToCollect, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white fw-semibold">Session summary</div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Description</th>
                        <th class="text-end pe-3">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="ps-3">Gross sales ({{ $stats['sales_count'] }} bills)</td>
                        <td class="text-end pe-3 fw-semibold">{{ $currency }} {{ fmt_num($stats['sales_total'], 2) }}</td>
                    </tr>
                    @if((float) $stats['refunds_total'] > 0)
                    <tr>
                        <td class="ps-3 text-danger">Refunds</td>
                        <td class="text-end pe-3 text-danger">− {{ $currency }} {{ fmt_num($stats['refunds_total'], 2) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="ps-3">Discount</td>
                        <td class="text-end pe-3 text-danger">− {{ $currency }} {{ fmt_num($stats['discount_total'], 2) }}</td>
                    </tr>
                    <tr>
                        <td class="ps-3">Service charges</td>
                        <td class="text-end pe-3">{{ $currency }} {{ fmt_num($stats['service_charge_total'], 2) }}</td>
                    </tr>
                    @if((float) $stats['tax_total'] > 0)
                    <tr>
                        <td class="ps-3">Tax</td>
                        <td class="text-end pe-3">{{ $currency }} {{ fmt_num($stats['tax_total'], 2) }}</td>
                    </tr>
                    @endif
                    @if($stats['credit_sales_count'] > 0)
                    <tr>
                        <td class="ps-3">Credit sales ({{ $stats['credit_sales_count'] }}) <span class="text-secondary small">— Credit Book, cash drawer mein nahi</span></td>
                        <td class="text-end pe-3">{{ $currency }} {{ fmt_num($stats['credit_sales_total'], 2) }}</td>
                    </tr>
                    @endif
                    <tr class="table-light">
                        <td class="ps-3 fw-bold">Net sales</td>
                        <td class="text-end pe-3 fw-bold">{{ $currency }} {{ fmt_num($stats['net_sales_total'], 2) }}</td>
                    </tr>
                    <tr><td colspan="2" class="py-1"></td></tr>
                    <tr>
                        <td class="ps-3"><i class="bi bi-cash-coin me-1 text-success"></i> Cash <span class="text-secondary small">(credit sales excluded)</span></td>
                        <td class="text-end pe-3 fw-semibold">{{ $currency }} {{ fmt_num($stats['payments_cash'], 2) }}</td>
                    </tr>
                    <tr>
                        <td class="ps-3"><i class="bi bi-bank me-1 text-primary"></i> Bank</td>
                        <td class="text-end pe-3 fw-semibold">{{ $currency }} {{ fmt_num($stats['payments_bank'], 2) }}</td>
                    </tr>
                    <tr>
                        <td class="ps-3"><i class="bi bi-credit-card me-1 text-info"></i> Card</td>
                        <td class="text-end pe-3 fw-semibold">{{ $currency }} {{ fmt_num($stats['payments_card'], 2) }}</td>
                    </tr>
                    @if((float) $cash['cash_in'] > 0 || (float) $cash['cash_out'] > 0)
                    <tr>
                        <td class="ps-3 text-secondary small">Cash in / out</td>
                        <td class="text-end pe-3 small text-secondary">+{{ fmt_num($cash['cash_in'], 2) }} / −{{ fmt_num($cash['cash_out'], 2) }}</td>
                    </tr>
                    @endif
                    <tr class="table-light">
                        <td class="ps-3 fw-bold">Total payments</td>
                        <td class="text-end pe-3 fw-bold">{{ $currency }} {{ fmt_num($stats['payments_cash'] + $stats['payments_bank'] + $stats['payments_card'], 2) }}</td>
                    </tr>
                    <tr>
                        <td class="ps-3 fw-bold">Cash in drawer (expected)</td>
                        <td class="text-end pe-3 fw-bold text-success">{{ $currency }} {{ fmt_num($amountToCollect, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    @if(!$canClose)
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <strong>{{ $stats['held_count'] }} pending bill(s)</strong> abhi bhi open hain. Pehle Restaurant POS par ja kar unhe pay ya discard karein, phir session close karein.
            <a href="{{ route('restaurant-pos.index') }}" class="alert-link">Restaurant POS →</a>
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white fw-semibold">End session</div>
        <div class="card-body">
            <p class="text-secondary small mb-3">
                Pehle <strong>Print Session Summary</strong> nikaalein, cash count karein, phir counted amount likh kar session end karein.
            </p>
            <div class="d-flex flex-wrap gap-2 mb-4">
                <a href="{{ route('restaurant-pos.closing.print') }}" target="_blank" class="btn btn-outline-primary">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Session Report (PDF)
                </a>
                <a href="{{ route('restaurant-pos.closing.print', ['auto' => 1]) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-printer me-1"></i> Print auto
                </a>
            </div>

            <form method="POST" action="{{ route('restaurant-pos.session.close') }}" class="row g-3 align-items-end" @if($canClose) onsubmit="return confirm('Kya aap is POS session ko close karna chahte hain?');" @endif>
                @csrf
                <div class="col-md-3">
                    <label class="form-label" for="counted_cash">Counted cash (drawer)</label>
                    <div class="input-group">
                        <span class="input-group-text">{{ $currency }}</span>
                        <input type="number" step="0.01" min="0" name="counted_cash" id="counted_cash"
                               class="form-control" value="{{ number_format($amountToCollect, 2, '.', '') }}" @disabled(!$canClose)>
                    </div>
                    <div class="form-text">Expected: {{ $currency }} {{ fmt_num($amountToCollect, 2) }}</div>
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="note">Closing note (optional)</label>
                    <input type="text" name="note" id="note" class="form-control" maxlength="255" placeholder="e.g. Shift handover to manager" @disabled(!$canClose)>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-danger w-100" @disabled(!$canClose)>
                        <i class="bi bi-box-arrow-right me-1"></i> End POS Session
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection
