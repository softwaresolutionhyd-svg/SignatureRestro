<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>POS Session Summary — {{ $session->session_no ?? $session->id }}</title>
    <style>
        * { box-sizing: border-box; }

        @page {
            size: A4 portrait;
            margin: 14mm 12mm 16mm;
        }

        html, body {
            margin: 0;
            padding: 0;
            background: #e8e8e8;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            font-size: 11pt;
            color: #111;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 12px auto;
            padding: 14mm 12mm 16mm;
            background: #fff;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.12);
        }

        /* ── Header ── */
        .doc-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            padding-bottom: 10px;
            border-bottom: 2.5px solid #111;
            margin-bottom: 14px;
        }

        .doc-head h1 {
            margin: 0 0 4px;
            font-size: 18pt;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.15;
        }

        .doc-head .subtitle {
            font-size: 11pt;
            font-weight: 600;
            color: #333;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .doc-head .meta-block {
            text-align: right;
            font-size: 9.5pt;
            color: #444;
            line-height: 1.55;
            min-width: 42%;
        }

        .doc-head .meta-block strong {
            color: #111;
            font-weight: 700;
        }

        /* ── KPI row ── */
        .kpi-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 16px;
        }

        .kpi {
            border: 1px solid #bbb;
            border-radius: 4px;
            padding: 8px 10px;
            background: #fafafa;
        }

        .kpi .lbl {
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #555;
            margin-bottom: 3px;
        }

        .kpi .val {
            font-size: 13pt;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            line-height: 1.2;
        }

        .kpi.highlight {
            border-color: #111;
            background: #f3f3f3;
        }

        /* ── Section ── */
        .section {
            margin-bottom: 14px;
        }

        .section-title {
            font-size: 9.5pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #333;
            margin: 0 0 6px;
            padding-bottom: 4px;
            border-bottom: 1px solid #ccc;
        }

        /* ── Tables ── */
        table.data {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
        }

        table.data th,
        table.data td {
            border: 1px solid #333;
            padding: 6px 8px;
            vertical-align: middle;
        }

        table.data th {
            background: #ececec;
            font-weight: 700;
            text-align: left;
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        table.data td.num {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
            width: 38%;
        }

        table.data tr.subtotal td {
            background: #f7f7f7;
            font-weight: 600;
        }

        table.data tr.grand td {
            background: #e8e8e8;
            font-weight: 800;
            font-size: 10.5pt;
        }

        table.data tr.danger td.num {
            color: #b91c1c;
        }

        table.data.summary-combined th:nth-child(3),
        table.data.summary-combined td:nth-child(3) {
            border-left: 2px solid #666;
        }

        /* ── Cash count box ── */
        .cash-count {
            border: 2px solid #111;
            border-radius: 4px;
            padding: 12px 14px;
            margin-bottom: 14px;
        }

        .cash-count .section-title {
            border-bottom: none;
            margin-bottom: 10px;
            font-size: 10pt;
        }

        .cash-count-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
        }

        .cash-field label {
            display: block;
            font-size: 8.5pt;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #555;
            margin-bottom: 6px;
        }

        .cash-field .box {
            border: 1px dashed #666;
            border-radius: 3px;
            min-height: 36px;
            padding: 8px 10px;
            font-size: 14pt;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }

        .cash-field .box.prefill {
            border-style: solid;
            border-color: #333;
            background: #fafafa;
        }

        .cash-field .hint {
            font-size: 8pt;
            color: #666;
            margin-top: 4px;
        }

        /* ── Signatures ── */
        .sign-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-top: 28px;
            padding-top: 4px;
        }

        .sign-box {
            text-align: center;
        }

        .sign-line {
            border-top: 1px solid #333;
            margin-bottom: 6px;
            height: 44px;
        }

        .sign-label {
            font-size: 9pt;
            font-weight: 600;
            color: #333;
        }

        .sign-sub {
            font-size: 8pt;
            color: #666;
            margin-top: 2px;
        }

        .note-box {
            border: 1px solid #ccc;
            border-radius: 3px;
            padding: 8px 10px;
            font-size: 9.5pt;
            color: #333;
            margin-bottom: 14px;
            min-height: 36px;
        }

        .footer-print {
            margin-top: 16px;
            padding-top: 8px;
            border-top: 1px solid #ddd;
            font-size: 8pt;
            color: #888;
            display: flex;
            justify-content: space-between;
        }

        /* ── Screen toolbar ── */
        .noprint {
            width: 210mm;
            margin: 12px auto 0;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        .noprint button,
        .noprint a {
            padding: 9px 16px;
            font-size: 13px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid #ccc;
            background: #fff;
            color: #111;
        }

        .noprint .btn-print {
            background: #b45309;
            border-color: #b45309;
            color: #fff;
            font-weight: 600;
        }

        @media print {
            html, body { background: #fff; }
            .sheet {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
            .noprint { display: none !important; }
        }
    </style>
</head>
<body>

<div class="noprint">
    <button type="button" class="btn-print" onclick="window.print()">🖨 Print / PDF</button>
    <a href="{{ route('restaurant-pos.closing') }}">← Back to Closing</a>
</div>

<div class="sheet">

    {{-- Header --}}
    <div class="doc-head">
        <div>
            <h1>{{ $companyName }}</h1>
            <div class="subtitle">POS Session Closing Summary</div>
        </div>
        <div class="meta-block">
            <div>Session: <strong>{{ $session->session_no ?? ('#'.$session->id) }}</strong></div>
            <div>Business date: <strong>{{ $session->business_date?->format('l, d M Y') ?? $session->opened_at?->format('l, d M Y') }}</strong></div>
            <div>Opened: <strong>{{ $session->opened_at?->format('d M Y, h:i A') }}</strong></div>
            @if($session->status === 'closed' && $session->closed_at)
            <div>Closed: <strong>{{ $session->closed_at->format('d M Y, h:i A') }}</strong></div>
            @endif
            <div>Cashier: <strong>{{ $session->user?->name ?? '—' }}</strong></div>
            <div>Printed: <strong>{{ now()->format('d M Y, h:i A') }}</strong></div>
        </div>
    </div>

    {{-- KPI summary --}}
    <div class="kpi-row">
        <div class="kpi highlight">
            <div class="lbl">Net sales</div>
            <div class="val">{{ $currency }} {{ fmt_num($stats['net_sales_total'], 2) }}</div>
        </div>
        <div class="kpi">
            <div class="lbl">Discount</div>
            <div class="val" style="color:#b91c1c;">{{ $currency }} {{ fmt_num($stats['discount_total'], 2) }}</div>
        </div>
        <div class="kpi">
            <div class="lbl">Service charges</div>
            <div class="val">{{ $currency }} {{ fmt_num($stats['service_charge_total'], 2) }}</div>
        </div>
        <div class="kpi highlight">
            <div class="lbl">Cash in drawer</div>
            <div class="val">{{ $currency }} {{ fmt_num($amountToCollect, 2) }}</div>
        </div>
    </div>

  <div class="section">
        <h2 class="section-title">Sales &amp; payments summary</h2>
        <table class="data summary-combined">
            <thead>
                <tr>
                    <th>Sales</th>
                    <th class="num">Amount</th>
                    <th>Payment</th>
                    <th class="num">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Gross sales ({{ $stats['sales_count'] }} bills)</td>
                    <td class="num">{{ $currency }} {{ fmt_num($stats['sales_total'], 2) }}</td>
                    <td><strong>Cash</strong></td>
                    <td class="num"><strong>{{ $currency }} {{ fmt_num($stats['payments_cash'], 2) }}</strong></td>
                </tr>
                @if((float) $stats['refunds_total'] > 0)
                <tr class="danger">
                    <td>Refunds ({{ $stats['refunds_count'] }})</td>
                    <td class="num">− {{ $currency }} {{ fmt_num($stats['refunds_total'], 2) }}</td>
                    <td colspan="2"></td>
                </tr>
                @endif
                <tr class="danger">
                    <td>Discount given</td>
                    <td class="num">− {{ $currency }} {{ fmt_num($stats['discount_total'], 2) }}</td>
                    <td>Bank transfer / deposit</td>
                    <td class="num">{{ $currency }} {{ fmt_num($stats['payments_bank'], 2) }}</td>
                </tr>
                <tr>
                    <td>Service charges</td>
                    <td class="num">{{ $currency }} {{ fmt_num($stats['service_charge_total'], 2) }}</td>
                    <td>Card</td>
                    <td class="num">{{ $currency }} {{ fmt_num($stats['payments_card'], 2) }}</td>
                </tr>
                @if((float) $stats['tax_total'] > 0)
                <tr>
                    <td>Tax collected</td>
                    <td class="num">{{ $currency }} {{ fmt_num($stats['tax_total'], 2) }}</td>
                    <td colspan="2"></td>
                </tr>
                @endif
                @if($stats['credit_sales_count'] > 0)
                <tr>
                    <td>Credit sales ({{ $stats['credit_sales_count'] }} bills)</td>
                    <td class="num">{{ $currency }} {{ fmt_num($stats['credit_sales_total'], 2) }}</td>
                    <td colspan="2"></td>
                </tr>
                @endif
                @if((float) $cash['opening_cash'] > 0)
                <tr>
                    <td colspan="2"></td>
                    <td>Opening float</td>
                    <td class="num">{{ $currency }} {{ fmt_num($cash['opening_cash'], 2) }}</td>
                </tr>
                @endif
                @if((float) $cash['cash_in'] > 0)
                <tr>
                    <td colspan="2"></td>
                    <td>Cash in (manual)</td>
                    <td class="num">+ {{ $currency }} {{ fmt_num($cash['cash_in'], 2) }}</td>
                </tr>
                @endif
                @if((float) $cash['cash_out'] > 0)
                <tr class="danger">
                    <td colspan="2"></td>
                    <td>Cash out (manual)</td>
                    <td class="num">− {{ $currency }} {{ fmt_num($cash['cash_out'], 2) }}</td>
                </tr>
                @endif
                <tr class="grand">
                    <td>Net sales total</td>
                    <td class="num">{{ $currency }} {{ fmt_num($stats['net_sales_total'], 2) }}</td>
                    <td>Total payments</td>
                    <td class="num">{{ $currency }} {{ fmt_num($stats['payments_cash'] + $stats['payments_bank'] + $stats['payments_card'], 2) }}</td>
                </tr>
                <tr class="subtotal">
                    <td colspan="2"></td>
                    <td>Cash in drawer (expected)</td>
                    <td class="num">{{ $currency }} {{ fmt_num($amountToCollect, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Cash count worksheet --}}
    <div class="cash-count">
        <h2 class="section-title">Cash count worksheet</h2>
        <div class="cash-count-grid">
            <div class="cash-field">
                <label>Expected cash (system)</label>
                <div class="box prefill">{{ $currency }} {{ fmt_num($amountToCollect, 2) }}</div>
                <div class="hint">Auto-calculated from cash sales ± movements</div>
            </div>
            <div class="cash-field">
                <label>Counted cash (physical)</label>
                <div class="box">&nbsp;</div>
                <div class="hint">Write amount after counting drawer</div>
            </div>
            <div class="cash-field">
                <label>Difference (counted − expected)</label>
                <div class="box">
                    @if($session->status === 'closed' && (float) $session->cash_difference !== 0.0)
                        {{ $currency }} {{ fmt_num((float) $session->cash_difference, 2) }}
                    @else
                        &nbsp;
                    @endif
                </div>
                <div class="hint">Short (−) or over (+)</div>
            </div>
        </div>
    </div>

    @if(!empty(trim((string) ($session->note ?? ''))))
    <div class="section">
        <h2 class="section-title">Closing note</h2>
        <div class="note-box">{{ $session->note }}</div>
    </div>
    @endif

    {{-- Signatures --}}
    <div class="sign-row">
        <div class="sign-box">
            <div class="sign-line"></div>
            <div class="sign-label">Cashier</div>
            <div class="sign-sub">{{ $session->user?->name ?? '' }}</div>
        </div>
        <div class="sign-box">
            <div class="sign-line"></div>
            <div class="sign-label">Manager / Supervisor</div>
            <div class="sign-sub">Name &amp; signature</div>
        </div>
        <div class="sign-box">
            <div class="sign-line"></div>
            <div class="sign-label">Accounts</div>
            <div class="sign-sub">Received &amp; verified</div>
        </div>
    </div>

    <div class="footer-print">
        <span>{{ $companyName }} — POS Session Closing</span>
        <span>Session {{ $session->session_no ?? $session->id }} · {{ $session->business_date?->format('Y-m-d') ?? now()->format('Y-m-d') }}</span>
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
