<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Session Summary — {{ $session->session_no ?? $session->id }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        * { box-sizing: border-box; }
        body {
            font-family: ui-sans-serif, system-ui, sans-serif;
            font-size: 13px;
            color: #111;
            margin: 0;
            padding: 16px;
            max-width: 720px;
            margin-left: auto;
            margin-right: auto;
        }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .muted { color: #555; }
        .line { border: 0; border-top: 1px dashed #999; margin: 12px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 5px 0; vertical-align: top; }
        td.amt { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        tr.total td { font-weight: 700; border-top: 2px solid #111; padding-top: 8px; }
        .sign { margin-top: 36px; display: flex; gap: 24px; }
        .sign div { flex: 1; border-top: 1px solid #333; padding-top: 6px; font-size: 11px; color: #555; }
        .noprint { margin-top: 16px; text-align: center; }
        @media print { .noprint { display: none; } }
    </style>
</head>
<body>
    <div class="center" style="text-align:center;margin-bottom:12px;">
        <h1>{{ $companyName }}</h1>
        <div class="muted">POS Session Summary</div>
    </div>

    <table>
        <tr><td>Session</td><td class="amt">{{ $session->session_no ?? ('#'.$session->id) }}</td></tr>
        <tr><td>Business date</td><td class="amt">{{ $session->business_date?->format('d M Y') ?? $session->opened_at?->format('d M Y') }}</td></tr>
        <tr><td>Opened</td><td class="amt">{{ $session->opened_at?->format('d M Y H:i') }}</td></tr>
        @if($session->status === 'closed' && $session->closed_at)
        <tr><td>Closed</td><td class="amt">{{ $session->closed_at->format('d M Y H:i') }}</td></tr>
        @endif
        <tr><td>Cashier</td><td class="amt">{{ $session->user?->name ?? '—' }}</td></tr>
    </table>

    <hr class="line">

    <table>
        <tr><td>Sales ({{ $stats['sales_count'] }} bills)</td><td class="amt">{{ $currency }} {{ fmt_num($stats['sales_total'], 2) }}</td></tr>
        @if((float) $stats['refunds_total'] > 0)
        <tr><td>Refunds ({{ $stats['refunds_count'] }})</td><td class="amt">− {{ $currency }} {{ fmt_num($stats['refunds_total'], 2) }}</td></tr>
        @endif
        <tr><td>Discount</td><td class="amt">− {{ $currency }} {{ fmt_num($stats['discount_total'], 2) }}</td></tr>
        <tr><td>Service charges</td><td class="amt">{{ $currency }} {{ fmt_num($stats['service_charge_total'], 2) }}</td></tr>
        @if((float) $stats['tax_total'] > 0)
        <tr><td>Tax</td><td class="amt">{{ $currency }} {{ fmt_num($stats['tax_total'], 2) }}</td></tr>
        @endif
        <tr class="total"><td>Net sales</td><td class="amt">{{ $currency }} {{ fmt_num($stats['net_sales_total'], 2) }}</td></tr>
    </table>

    <hr class="line">

    <table>
        <tr><td><strong>Cash received</strong></td><td class="amt"><strong>{{ $currency }} {{ fmt_num($stats['payments_cash'], 2) }}</strong></td></tr>
        <tr><td>Bank received</td><td class="amt">{{ $currency }} {{ fmt_num($stats['payments_bank'], 2) }}</td></tr>
        <tr><td>Card received</td><td class="amt">{{ $currency }} {{ fmt_num($stats['payments_card'], 2) }}</td></tr>
        @if((float) $cash['cash_in'] > 0)
        <tr><td>Cash in</td><td class="amt">+ {{ $currency }} {{ fmt_num($cash['cash_in'], 2) }}</td></tr>
        @endif
        @if((float) $cash['cash_out'] > 0)
        <tr><td>Cash out</td><td class="amt">− {{ $currency }} {{ fmt_num($cash['cash_out'], 2) }}</td></tr>
        @endif
        <tr class="total"><td>Cash in drawer (expected)</td><td class="amt">{{ $currency }} {{ fmt_num($amountToCollect, 2) }}</td></tr>
    </table>

    @if($session->status === 'closed' && (float) $session->cash_difference !== 0.0)
    <hr class="line">
    <table>
        <tr><td>Cash difference</td><td class="amt">{{ $currency }} {{ fmt_num((float) $session->cash_difference, 2) }}</td></tr>
    </table>
    @endif

    @if(!empty(trim((string) ($session->note ?? ''))))
    <hr class="line">
    <div class="muted">Note: {{ $session->note }}</div>
    @endif

    <div class="sign">
        <div>Cashier signature</div>
        <div>Manager signature</div>
    </div>

    <div class="noprint">
        <button type="button" onclick="window.print()" style="padding:10px 20px;font-size:14px;cursor:pointer;">Print again</button>
        <p class="muted" style="font-size:11px;">{{ now()->format('d M Y H:i') }}</p>
    </div>

@if(!empty($autoPrint))
<script>
(function () {
    function doPrint() { window.print(); }
    if (document.readyState === 'complete') setTimeout(doPrint, 300);
    else window.addEventListener('load', function () { setTimeout(doPrint, 300); });
})();
</script>
@endif
</body>
</html>
