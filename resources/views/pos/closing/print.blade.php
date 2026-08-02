<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if(app()->getLocale() === 'ur') dir="rtl" @endif>
<head>
    <meta charset="utf-8">
    <title>{{ __('POS Session Report') }} — {{ $session->session_no ?? $session->id }}</title>
    <style>
        * { box-sizing: border-box; }

        @page { size: A4 portrait; margin: 18mm 16mm; }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, 'Noto Nastaliq Urdu', 'Jameel Noori Nastaleeq', sans-serif;
            font-size: 11pt;
            color: #000;
            background: #fff;
        }

        .page {
            width: 210mm;
            margin: 0 auto;
            padding: 18mm 16mm;
        }

        .noprint {
            text-align: center;
            padding: 10px;
            border-bottom: 1px solid #000;
        }

        .noprint button,
        .noprint a {
            margin: 0 6px;
            padding: 6px 12px;
            font-size: 12px;
            border: 1px solid #000;
            background: #fff;
            color: #000;
            cursor: pointer;
            text-decoration: none;
        }

        h1 {
            margin: 0 0 2px;
            font-size: 16pt;
            font-weight: bold;
            text-align: center;
        }

        h2 {
            margin: 0 0 14px;
            font-size: 11pt;
            font-weight: normal;
            text-align: center;
        }

        .meta {
            margin-bottom: 16px;
            font-size: 10pt;
            line-height: 1.6;
        }

        .meta p { margin: 0; }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
        }

        th, td {
            border: 1px solid #000;
            padding: 6px 8px;
        }

        th {
            font-weight: bold;
            text-align: start;
        }

        td.amt {
            text-align: end;
            width: 32%;
        }

        tr.bold td {
            font-weight: bold;
        }

        .footer {
            margin-top: 20px;
            font-size: 9pt;
            text-align: center;
        }

        @media print {
            .noprint { display: none; }
            .page { width: auto; padding: 0; }
        }
    </style>
</head>
<body>

@php
    $bizDate = $session->business_date?->format('d M Y') ?? $session->opened_at?->format('d M Y');
    $openedAt = $session->opened_at?->format('d M Y, h:i A');
    $closedAt = $session->closed_at?->format('d M Y, h:i A');
    $printedAt = now()->format('d M Y, h:i A');
    $sessionLabel = $session->session_no ?? ('#'.$session->id);
    $totalPayments = $stats['payments_cash'] + $stats['payments_bank'] + $stats['payments_card'];
@endphp

<div class="noprint">
    <button type="button" onclick="window.print()">{{ __('Print / PDF') }}</button>
    <a href="{{ route('restaurant-pos.closing') }}">{{ __('Back') }}</a>
</div>

<div class="page">
    <h1>{{ $companyName }}</h1>
    <h2>{{ __('POS Session Report') }} — {{ $bizDate }}</h2>

    <div class="meta">
        <p>{{ __('Business date') }}: {{ $bizDate }} &nbsp;|&nbsp; {{ __('Session') }}: {{ $sessionLabel }}</p>
        <p>{{ __('Opened') }}: {{ $openedAt ?? '—' }}@if($session->status === 'closed' && $closedAt) &nbsp;|&nbsp; {{ __('Closed') }}: {{ $closedAt }}@endif</p>
        <p>{{ __('Cashier') }}: {{ $session->user?->name ?? '—' }}@if(!empty($printedBy)) &nbsp;|&nbsp; {{ __('Printed by') }}: {{ $printedBy }}@endif</p>
        <p>{{ __('Printed') }}: {{ $printedAt }} &nbsp;|&nbsp; {{ __('Status') }}: {{ $session->status === 'closed' ? __('Closed') : __('Open') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('Description') }}</th>
                <th class="amt">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ __('Gross sales (:count bills)', ['count' => $stats['sales_count']]) }}</td>
                <td class="amt">{{ $currency }} {{ fmt_num($stats['sales_total'], 2) }}</td>
            </tr>
            @if((float) $stats['refunds_total'] > 0)
            <tr>
                <td>{{ __('Refunds (:count)', ['count' => $stats['refunds_count']]) }}</td>
                <td class="amt">- {{ $currency }} {{ fmt_num($stats['refunds_total'], 2) }}</td>
            </tr>
            @endif
            <tr>
                <td>{{ __('Discount') }}</td>
                <td class="amt">- {{ $currency }} {{ fmt_num($stats['discount_total'], 2) }}</td>
            </tr>
            <tr>
                <td>{{ __('Service charges') }}</td>
                <td class="amt">- {{ $currency }} {{ fmt_num($stats['service_charge_total'], 2) }}</td>
            </tr>
            @if((float) $stats['tax_total'] > 0)
            <tr>
                <td>{{ __('Tax') }}</td>
                <td class="amt">{{ $currency }} {{ fmt_num($stats['tax_total'], 2) }}</td>
            </tr>
            @endif
            @if($stats['credit_sales_count'] > 0)
            <tr>
                <td>{{ __('Credit sales (:count)', ['count' => $stats['credit_sales_count']]) }}</td>
                <td class="amt">{{ $currency }} {{ fmt_num($stats['credit_sales_total'], 2) }}</td>
            </tr>
            @endif
            <tr class="bold">
                <td>{{ __('Net sales') }}</td>
                <td class="amt">{{ $currency }} {{ fmt_num($stats['net_sales_total'], 2) }}</td>
            </tr>
            @php
                $cashMovements = $cashMovements ?? collect();
                $cashOutRows = $cashMovements->where('type', 'out')->values();
                $cashInRows = $cashMovements->where('type', 'in')->values();
                $cashOutTotal = (float) ($cash['cash_out'] ?? 0);
                $displayNetSales = round((float) $stats['net_sales_total'] - $cashOutTotal, 2);
            @endphp
            @foreach($cashOutRows as $mv)
            <tr>
                <td>{{ __('Cash Out') }} — {{ $mv->reason ?: '—' }}</td>
                <td class="amt">− {{ $currency }} {{ fmt_num($mv->amount, 2) }}</td>
            </tr>
            @endforeach
            @if($cashOutTotal > 0)
            <tr class="bold">
                <td>{{ __('Net sales after cash out') }}</td>
                <td class="amt">{{ $currency }} {{ fmt_num($displayNetSales, 2) }}</td>
            </tr>
            @endif
            <tr>
                <td>{{ __('Cash') }}</td>
                <td class="amt">{{ $currency }} {{ fmt_num($stats['payments_cash'], 2) }}</td>
            </tr>
            <tr>
                <td>{{ __('Bank') }}</td>
                <td class="amt">{{ $currency }} {{ fmt_num($stats['payments_bank'], 2) }}</td>
            </tr>
            <tr>
                <td>{{ __('Card') }}</td>
                <td class="amt">{{ $currency }} {{ fmt_num($stats['payments_card'], 2) }}</td>
            </tr>
            @foreach($cashInRows as $mv)
            <tr>
                <td>{{ __('Cash In') }} — {{ $mv->reason ?: '—' }}</td>
                <td class="amt">+ {{ $currency }} {{ fmt_num($mv->amount, 2) }}</td>
            </tr>
            @endforeach
            <tr class="bold">
                <td>{{ __('Total payments') }}</td>
                <td class="amt">{{ $currency }} {{ fmt_num($totalPayments, 2) }}</td>
            </tr>
            <tr class="bold">
                <td>{{ __('Cash in drawer (expected)') }}</td>
                <td class="amt">{{ $currency }} {{ fmt_num($amountToCollect, 2) }}</td>
            </tr>
            @if($session->status === 'closed' && $session->cash_difference !== null && (float) $session->cash_difference !== 0.0)
            <tr>
                <td>{{ __('Cash difference') }}</td>
                <td class="amt">{{ $currency }} {{ fmt_num((float) $session->cash_difference, 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    @if(!empty(trim((string) ($session->note ?? ''))))
    <p style="margin-top:14px;font-size:10pt;">{{ __('Note') }}: {{ $session->note }}</p>
    @endif

    <div class="footer">{{ $companyName }} — {{ $sessionLabel }}</div>
</div>

@if(!empty($autoPrint))
<script>
setTimeout(function () { window.print(); }, 400);
</script>
@endif
</body>
</html>
