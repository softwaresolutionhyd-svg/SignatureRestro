<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>POS Session Report — {{ $session->session_no ?? $session->id }}</title>
    <style>
        * { box-sizing: border-box; }

        @page { size: A4 portrait; margin: 18mm 16mm; }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
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
            text-align: left;
        }

        td.amt {
            text-align: right;
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
    <button type="button" onclick="window.print()">Print / PDF</button>
    <a href="{{ route('restaurant-pos.closing') }}">Back</a>
</div>

<div class="page">
    <h1>{{ $companyName }}</h1>
    <h2>POS Session Report — {{ $bizDate }}</h2>

    <div class="meta">
        <p>Business date: {{ $bizDate }} &nbsp;|&nbsp; Session: {{ $sessionLabel }}</p>
        <p>Opened: {{ $openedAt ?? '—' }}@if($session->status === 'closed' && $closedAt) &nbsp;|&nbsp; Closed: {{ $closedAt }}@endif</p>
        <p>Cashier: {{ $session->user?->name ?? '—' }}@if(!empty($printedBy)) &nbsp;|&nbsp; Printed by: {{ $printedBy }}@endif</p>
        <p>Printed: {{ $printedAt }} &nbsp;|&nbsp; Status: {{ $session->status === 'closed' ? 'Closed' : 'Open' }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="amt">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Gross sales ({{ $stats['sales_count'] }} bills)</td>
                <td class="amt">{{ $currency }} {{ fmt_num($stats['sales_total'], 2) }}</td>
            </tr>
            @if((float) $stats['refunds_total'] > 0)
            <tr>
                <td>Refunds ({{ $stats['refunds_count'] }})</td>
                <td class="amt">- {{ $currency }} {{ fmt_num($stats['refunds_total'], 2) }}</td>
            </tr>
            @endif
            <tr>
                <td>Discount</td>
                <td class="amt">- {{ $currency }} {{ fmt_num($stats['discount_total'], 2) }}</td>
            </tr>
            <tr>
                <td>Service charges</td>
                <td class="amt">{{ $currency }} {{ fmt_num($stats['service_charge_total'], 2) }}</td>
            </tr>
            @if((float) $stats['tax_total'] > 0)
            <tr>
                <td>Tax</td>
                <td class="amt">{{ $currency }} {{ fmt_num($stats['tax_total'], 2) }}</td>
            </tr>
            @endif
            @if($stats['credit_sales_count'] > 0)
            <tr>
                <td>Credit sales ({{ $stats['credit_sales_count'] }})</td>
                <td class="amt">{{ $currency }} {{ fmt_num($stats['credit_sales_total'], 2) }}</td>
            </tr>
            @endif
            <tr class="bold">
                <td>Net sales</td>
                <td class="amt">{{ $currency }} {{ fmt_num($stats['net_sales_total'], 2) }}</td>
            </tr>
            <tr>
                <td>Cash</td>
                <td class="amt">{{ $currency }} {{ fmt_num($stats['payments_cash'], 2) }}</td>
            </tr>
            <tr>
                <td>Bank</td>
                <td class="amt">{{ $currency }} {{ fmt_num($stats['payments_bank'], 2) }}</td>
            </tr>
            <tr>
                <td>Card</td>
                <td class="amt">{{ $currency }} {{ fmt_num($stats['payments_card'], 2) }}</td>
            </tr>
            @if((float) $cash['cash_in'] > 0 || (float) $cash['cash_out'] > 0)
            <tr>
                <td>Cash in / out</td>
                <td class="amt">+{{ fmt_num($cash['cash_in'], 2) }} / -{{ fmt_num($cash['cash_out'], 2) }}</td>
            </tr>
            @endif
            <tr class="bold">
                <td>Total payments</td>
                <td class="amt">{{ $currency }} {{ fmt_num($totalPayments, 2) }}</td>
            </tr>
            <tr class="bold">
                <td>Cash in drawer (expected)</td>
                <td class="amt">{{ $currency }} {{ fmt_num($amountToCollect, 2) }}</td>
            </tr>
            @if($session->status === 'closed' && $session->cash_difference !== null && (float) $session->cash_difference !== 0.0)
            <tr>
                <td>Cash difference</td>
                <td class="amt">{{ $currency }} {{ fmt_num((float) $session->cash_difference, 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    @if(!empty(trim((string) ($session->note ?? ''))))
    <p style="margin-top:14px;font-size:10pt;">Note: {{ $session->note }}</p>
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
