<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $order->order_no }}</title>
    <style>
        @page { size: 80mm auto; margin: 3mm; }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            font-family: ui-monospace, 'Cascadia Code', 'Courier New', monospace;
            font-size: 11px;
            line-height: 1.45;
            color: #000;
            background: #fff;
            max-width: 80mm;
            margin-left: auto;
            margin-right: auto;
        }
        .r-wrap { padding: 4px 6px 12px; }
        .center { text-align: center; }
        .bold { font-weight: 700; }
        .muted { color: #333; }
        .line { border: 0; border-top: 1px dashed #000; margin: 8px 0; }
        .r-brand {
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.04em;
            margin: 4px 0 6px;
            text-transform: uppercase;
        }
        .r-meta { margin: 2px 0; font-size: 10px; }
        .r-meta-label { font-weight: 700; }
        .r-info { margin: 4px 0; }
        .r-info .tot-row { padding: 2px 0; }
        table.items { width: 100%; border-collapse: separate; border-spacing: 0 4px; }
        table.items thead td {
            font-weight: 800;
            font-size: 10px;
            text-transform: uppercase;
            padding: 0 0 4px;
            border-bottom: 1px dashed #000;
            vertical-align: bottom;
        }
        table.items tbody td {
            padding: 3px 0;
            vertical-align: top;
        }
        table.items td.item-name {
            word-break: break-word;
            padding-right: 2px;
            width: 50%;
            line-height: 1.25;
            letter-spacing: -0.02em;
        }
        table.items td.item-qty { white-space: nowrap; text-align: center; width: 10%; font-size: 10px; padding-left: 0; }
        table.items td.item-rate { white-space: nowrap; text-align: right; width: 18%; font-size: 10px; padding-left: 0; }
        table.items td.amt { text-align: right; white-space: nowrap; width: 22%; padding-left: 0; }
        table.items td.item-note { font-size: 10px; padding-top: 0; padding-bottom: 2px; color: #333; }
        .tot-row { display: flex; justify-content: space-between; padding: 2px 0; }
        .totals-block { margin-top: 2px; }
        .totals-block .tot-row + .tot-row { padding-top: 3px; }
        .totals-block .pay-heading { font-weight: 700; margin: 6px 0 2px; font-size: 11px; }
        .r-grand-total {
            margin-top: 6px;
            padding-top: 4px;
            font-size: 12px;
            font-weight: 800;
        }
        .r-bill-status {
            margin-top: 14px;
            padding: 10px 4px 6px;
            text-align: center;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            border-top: 2px solid #000;
        }
        .r-bill-status--unpaid { font-size: 18px; }
        .r-status-spacer {
            height: 1.6em;
            line-height: 1.1;
            margin: 4px 0 2px;
        }
        .r-logo { max-width: 56mm; max-height: 22mm; object-fit: contain; margin: 0 auto 8px; display: block; }
        .r-powered {
            margin-top: 4px;
            text-align: center;
            font-size: 9px;
            color: #444;
            letter-spacing: 0.02em;
        }
        .noprint { margin-top: 12px; text-align: center; }
        @media print {
            .noprint { display: none !important; }
            html, body { max-width: none; }
        }
    </style>
</head>
<body>
@if(session('success'))
    <div class="noprint" style="max-width:80mm;margin:0 auto 8px;padding:8px 10px;background:#d1fae5;border:1px solid #6ee7b7;border-radius:8px;font-size:12px;text-align:center;">
        {{ session('success') }}
    </div>
@endif
@php
    $companyName = trim((string) ($settings['company_name'] ?? config('app.name')));
    $companyAddress = trim((string) ($settings['company_address'] ?? ''));
    $companyEmail = trim((string) ($settings['company_email'] ?? ''));
    $companyPhone = trim((string) ($settings['company_phone'] ?? ''));
    $currency = $settings['currency_symbol'] ?? 'Rs.';
    $orderType = $order->serviceTypeLabel() ?: '—';
    $logoSrc = trim((string) ($settings['company_logo_data_uri'] ?? ''))
        ?: trim((string) ($settings['company_logo_url'] ?? ''));
    if ($logoSrc === '' && ! empty($settings['company_logo'])) {
        $logoSrc = company_logo_data_uri((string) $settings['company_logo'])
            ?: (company_logo_url((string) $settings['company_logo']) ?? '');
    }
@endphp
<div class="r-wrap">
    @if($logoSrc !== '')
        <img src="{{ $logoSrc }}" alt="{{ $companyName }}" class="r-logo">
    @endif

    <div class="center r-brand">{{ $companyName }}</div>

    @if($companyAddress !== '')
        <div class="center r-meta"><span class="r-meta-label">Address:</span> {{ $companyAddress }}</div>
    @endif
    @if($companyEmail !== '')
        <div class="center r-meta"><span class="r-meta-label">Email:</span> {{ $companyEmail }}</div>
    @endif
    @if($companyPhone !== '')
        <div class="center r-meta"><span class="r-meta-label">Phone:</span> {{ $companyPhone }}</div>
    @endif

    <hr class="line">

    <div class="r-info">
        <div class="tot-row"><span class="muted">Invoice Number:</span><span class="bold">{{ $order->order_no }}</span></div>
        <div class="tot-row"><span class="muted">Order Type:</span><span class="bold">{{ $orderType }}</span></div>
        @if(!empty($settings['pos_enable_tables']) && $settings['pos_enable_tables'] === '1' && $order->table)
            <div class="tot-row"><span class="muted">Table</span><span class="bold">{{ $order->table->name }}</span></div>
        @endif
        @if($order->guest_name)
            <div class="tot-row"><span class="muted">Guest</span><span>{{ $order->guest_name }}</span></div>
        @endif
        @if($order->room_no)
            <div class="tot-row"><span class="muted">Room</span><span>{{ $order->room_no }}</span></div>
        @endif
        @if($order->waiter_name)
            <div class="tot-row"><span class="muted">Waiter</span><span>{{ $order->waiter_name }}</span></div>
        @endif
        <div class="tot-row"><span class="muted">Date</span><span>{{ ($order->paid_at ?? $order->updated_at ?? $order->created_at)?->format('d M Y H:i') }}</span></div>
        <div class="tot-row"><span class="muted">Cashier</span><span>{{ $order->user->name ?? '—' }}</span></div>
        @if($order->is_credit && $order->contact)
            <div class="tot-row"><span class="muted">Customer</span><span>{{ $order->contact->name }}</span></div>
            @if($order->contact->phone)
                <div class="tot-row"><span class="muted">Phone</span><span>{{ $order->contact->phone }}</span></div>
            @endif
        @endif
    </div>

    <hr class="line">

    <table class="items">
        <thead>
            <tr>
                <td class="item-name">Items</td>
                <td class="item-qty">Qty</td>
                <td class="item-rate">Rate</td>
                <td class="amt">Amount</td>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $line)
                <tr>
                    <td class="item-name bold">{{ $line->displayName() }}</td>
                    <td class="item-qty">{{ fmt_num((float) $line->qty, 3) }}</td>
                    <td class="item-rate">{{ fmt_num((float) $line->unit_price, 2) }}</td>
                    <td class="amt bold">{{ fmt_num((float) $line->total, 2) }}</td>
                </tr>
                @if(trim((string) ($line->notes ?? '')) !== '')
                <tr>
                    <td colspan="4" class="item-note muted">Note: {{ $line->notes }}</td>
                </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    <hr class="line">

    <div class="totals-block">
        <div class="tot-row"><span class="muted">Subtotal</span><span>{{ $currency }}{{ fmt_num((float) $order->subtotal, 2) }}</span></div>
        @if((float) ($order->service_charge_total ?? 0) > 0)
            <div class="tot-row">
                <span class="muted">Service Charges</span>
                <span>{{ $currency }}{{ fmt_num((float) $order->service_charge_total, 2) }}</span>
            </div>
        @endif
        @if((float) $order->discount_total > 0)
            <div class="tot-row"><span class="muted">Discount</span><span>-{{ $currency }}{{ fmt_num((float) $order->discount_total, 2) }}</span></div>
        @endif
        @if((float) $order->tax_total > 0)
            <div class="tot-row"><span class="muted">Tax</span><span>{{ $currency }}{{ fmt_num((float) $order->tax_total, 2) }}</span></div>
        @endif
        <div class="tot-row r-grand-total">
            <span>{{ !empty($isUnpaid) ? 'AMOUNT DUE' : 'Grand Total' }}</span>
            <span>{{ $currency }}{{ fmt_num((float) $order->grand_total, 2) }}</span>
        </div>
        @if(empty($isUnpaid) && !$order->is_credit && $order->payments->isNotEmpty())
            <div class="pay-heading">Payment</div>
            @foreach($order->payments as $pay)
                <div class="tot-row">
                    <span class="muted">{{ ucfirst($pay->method) }}</span>
                    <span>{{ $currency }}{{ fmt_num((float) $pay->amount, 2) }}</span>
                </div>
            @endforeach
        @elseif(empty($isUnpaid) && $order->is_credit)
            <div class="center bold" style="margin-top:6px;">CREDIT SALE</div>
            <div class="center muted">Amount on account: {{ $currency }}{{ fmt_num((float) $order->grand_total, 2) }}</div>
        @endif
        @if(empty($isUnpaid) && $order->cash_tendered !== null && (float) $order->cash_tendered >= 0)
            <div class="tot-row" style="margin-top:4px;"><span class="muted">Received</span><span>{{ $currency }}{{ fmt_num((float) $order->cash_tendered, 2) }}</span></div>
            @if($order->cash_change !== null)
                <div class="tot-row bold"><span>Change</span><span>{{ $currency }}{{ fmt_num((float) $order->cash_change, 2) }}</span></div>
            @endif
        @endif
    </div>

    @if(!empty(trim((string) ($order->order_notes ?? ''))))
        <hr class="line">
        <div class="muted" style="font-size:10px;"><span class="bold">Note:</span> {{ $order->order_notes }}</div>
    @endif

    @if(!empty($isUnpaid))
        <div class="r-bill-status r-bill-status--unpaid">UNPAID</div>
    @elseif($order->type === 'refund')
        <div class="r-bill-status">REFUND</div>
    @else
        <div class="r-bill-status">PAID</div>
    @endif

    <div class="r-status-spacer" aria-hidden="true">&nbsp;<br>&nbsp;</div>

    @if(!empty(trim((string) ($settings['pos_receipt_footer_note'] ?? ''))))
        <div class="center muted" style="font-size:10px;margin-top:0;white-space:pre-line;">{{ $settings['pos_receipt_footer_note'] }}</div>
    @endif

    <div class="r-powered">Powered by softwaresolutions.pk</div>
</div>
<div class="noprint" style="max-width:80mm;margin:12px auto 24px;padding:0 8px;">
    <a href="{{ $backUrl ?? route('restaurant-pos.index') }}" style="display:block;text-align:center;text-decoration:none;font-weight:700;padding:14px 16px;border-radius:10px;margin-bottom:10px;background:#0d6efd;color:#fff;font-size:15px;">{{ $backLabel ?? '← Back to Restaurant POS' }}</a>
    @if(!empty($allowBillPrint))
        <button type="button" onclick="window.print()" style="display:block;width:100%;padding:10px;font-size:14px;cursor:pointer;border:1px solid #999;border-radius:8px;background:#fff;">Print again</button>
        <p style="font-size:10px;color:#666;text-align:center;margin:10px 0 0;">Same-tab receipt — no pop-up blocker.</p>
    @endif
</div>
@if(!empty($autoPrint))
<script>
(function () {
    var printed = false;
    function waitForImages(done) {
        var imgs = Array.prototype.slice.call(document.images || []);
        var finished = false;
        var complete = function () {
            if (finished) return;
            finished = true;
            done();
        };
        if (!imgs.length) {
            complete();
            return;
        }
        var left = imgs.length;
        var onOne = function () {
            left -= 1;
            if (left <= 0) complete();
        };
        imgs.forEach(function (img) {
            if (img.complete) {
                onOne();
                return;
            }
            img.addEventListener('load', onOne, { once: true });
            img.addEventListener('error', onOne, { once: true });
        });
        setTimeout(complete, 2500);
    }
    function doPrint() {
        waitForImages(function () {
            if (printed) return;
            printed = true;
            setTimeout(function () { window.print(); }, 150);
        });
    }
    if (document.readyState === 'complete') {
        doPrint();
    } else {
        window.addEventListener('load', doPrint);
    }
})();
</script>
@endif
</body>
</html>
