<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>POS Session Report — {{ $session->session_no ?? $session->id }}</title>
    <style>
        * { box-sizing: border-box; }

        @page {
            size: A4 portrait;
            margin: 15mm 14mm;
        }

        html, body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            color: #111;
            background: #f0f0f0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 10px auto;
            padding: 15mm 14mm;
            background: #fff;
            box-shadow: 0 1px 8px rgba(0, 0, 0, 0.12);
        }

        .toolbar {
            width: 210mm;
            margin: 10px auto 0;
            display: flex;
            gap: 8px;
        }

        .toolbar button,
        .toolbar a {
            padding: 8px 14px;
            font-size: 13px;
            border-radius: 4px;
            border: 1px solid #ccc;
            background: #fff;
            cursor: pointer;
            text-decoration: none;
            color: #111;
        }

        .toolbar .btn-print {
            background: #1d4ed8;
            border-color: #1d4ed8;
            color: #fff;
            font-weight: 600;
        }

        h1 {
            margin: 0 0 4px;
            font-size: 20pt;
            font-weight: 700;
        }

        .subtitle {
            font-size: 11pt;
            color: #444;
            margin-bottom: 16px;
        }

        .meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px 24px;
            margin-bottom: 20px;
            padding: 12px 14px;
            border: 1px solid #ccc;
            background: #fafafa;
            font-size: 10pt;
        }

        .meta div span {
            color: #555;
            display: inline-block;
            min-width: 110px;
        }

        .meta div strong {
            color: #111;
        }

        table.report {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.5pt;
        }

        table.report th,
        table.report td {
            border: 1px solid #333;
            padding: 8px 10px;
        }

        table.report th {
            background: #eee;
            text-align: left;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 9pt;
            letter-spacing: 0.04em;
        }

        table.report td.amount {
            text-align: right;
            width: 35%;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        table.report tr.total td {
            background: #f3f4f6;
            font-weight: 700;
        }

        table.report tr.grand td {
            background: #e5e7eb;
            font-weight: 800;
            font-size: 11pt;
        }

        table.report tr.danger td.amount {
            color: #b91c1c;
        }

        .footer {
            margin-top: 24px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 9pt;
            color: #666;
            display: flex;
            justify-content: space-between;
        }

        @media print {
            html, body { background: #fff; }
            .page {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
            .toolbar { display: none !important; }
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

<div class="toolbar noprint">
    <button type="button" class="btn-print" onclick="window.print()">Print / Save as PDF</button>
    <a href="{{ route('restaurant-pos.closing') }}">← Back to Closing</a>
</div>

<div class="page">
    <h1>{{ $companyName }}</h1>
    <div class="subtitle">POS Session Report — {{ $bizDate }}</div>

    <div class="meta">
        <div><span>Business date</span> <strong>{{ $bizDate }}</strong></div>
        <div><span>Session #</span> <strong>{{ $sessionLabel }}</strong></div>
        <div><span>Session opened</span> <strong>{{ $openedAt ?? '—' }}</strong></div>
        <div><span>Status</span> <strong>{{ $session->status === 'closed' ? 'Closed' : 'Open' }}</strong></div>
        @if($session->status === 'closed' && $closedAt)
        <div><span>Session closed</span> <strong>{{ $closedAt }}</strong></div>
        @endif
        <div><span>Cashier</span> <strong>{{ $session->user?->name ?? '—' }}</strong></div>
        <div><span>Report printed</span> <strong>{{ $printedAt }}</strong></div>
        @if(!empty($printedBy))
        <div><span>Printed by</span> <strong>{{ $printedBy }}</strong></div>
        @endif
    </div>

    <table class="report">
        <thead>
            <tr>
                <th>Description</th>
                <th class="amount">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Gross sales ({{ $stats['sales_count'] }} bills)</td>
                <td class="amount">{{ $currency }} {{ fmt_num($stats['sales_total'], 2) }}</td>
            </tr>
            @if((float) $stats['refunds_total'] > 0)
            <tr class="danger">
                <td>Refunds ({{ $stats['refunds_count'] }})</td>
                <td class="amount">− {{ $currency }} {{ fmt_num($stats['refunds_total'], 2) }}</td>
            </tr>
            @endif
            <tr class="danger">
                <td>Discount</td>
                <td class="amount">− {{ $currency }} {{ fmt_num($stats['discount_total'], 2) }}</td>
            </tr>
            <tr>
                <td>Service charges</td>
                <td class="amount">{{ $currency }} {{ fmt_num($stats['service_charge_total'], 2) }}</td>
            </tr>
            @if((float) $stats['tax_total'] > 0)
            <tr>
                <td>Tax</td>
                <td class="amount">{{ $currency }} {{ fmt_num($stats['tax_total'], 2) }}</td>
            </tr>
            @endif
            @if($stats['credit_sales_count'] > 0)
            <tr>
                <td>Credit sales ({{ $stats['credit_sales_count'] }}) — Credit Book, not in cash drawer</td>
                <td class="amount">{{ $currency }} {{ fmt_num($stats['credit_sales_total'], 2) }}</td>
            </tr>
            @endif
            <tr class="total">
                <td>Net sales</td>
                <td class="amount">{{ $currency }} {{ fmt_num($stats['net_sales_total'], 2) }}</td>
            </tr>
            <tr>
                <td colspan="2" style="border:none;padding:4px 0;"></td>
            </tr>
            <tr>
                <td>Cash (credit sales excluded)</td>
                <td class="amount">{{ $currency }} {{ fmt_num($stats['payments_cash'], 2) }}</td>
            </tr>
            <tr>
                <td>Bank</td>
                <td class="amount">{{ $currency }} {{ fmt_num($stats['payments_bank'], 2) }}</td>
            </tr>
            <tr>
                <td>Card</td>
                <td class="amount">{{ $currency }} {{ fmt_num($stats['payments_card'], 2) }}</td>
            </tr>
            @if((float) $cash['cash_in'] > 0 || (float) $cash['cash_out'] > 0)
            <tr>
                <td>Cash in / out (manual)</td>
                <td class="amount">+{{ fmt_num($cash['cash_in'], 2) }} / −{{ fmt_num($cash['cash_out'], 2) }}</td>
            </tr>
            @endif
            <tr class="total">
                <td>Total payments</td>
                <td class="amount">{{ $currency }} {{ fmt_num($totalPayments, 2) }}</td>
            </tr>
            <tr class="grand">
                <td>Cash in drawer (expected)</td>
                <td class="amount">{{ $currency }} {{ fmt_num($amountToCollect, 2) }}</td>
            </tr>
            @if($session->status === 'closed' && $session->cash_difference !== null && (float) $session->cash_difference !== 0.0)
            <tr>
                <td>Cash difference (counted − expected)</td>
                <td class="amount">{{ $currency }} {{ fmt_num((float) $session->cash_difference, 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    @if(!empty(trim((string) ($session->note ?? ''))))
    <p style="margin-top:16px;font-size:10pt;"><strong>Note:</strong> {{ $session->note }}</p>
    @endif

    <div class="footer">
        <span>{{ $companyName }}</span>
        <span>{{ $sessionLabel }} · {{ $bizDate }}</span>
    </div>
</div>

@if(!empty($autoPrint))
<script>
(function () {
    function doPrint() { window.print(); }
    if (document.readyState === 'complete') setTimeout(doPrint, 400);
    else window.addEventListener('load', function () { setTimeout(doPrint, 400); });
})();
</script>
@endif
</body>
</html>
