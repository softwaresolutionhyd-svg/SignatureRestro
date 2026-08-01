<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $order->order_no }}</title>
    <style>
        /* Fixed height required — `80mm auto` is ignored by Chrome/Edge → falls back to A4. */
        @page {
            size: 80mm 297mm;
            margin: 3mm 4mm 3mm 3mm;
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            font-family: ui-monospace, 'Cascadia Code', 'Courier New', monospace;
            font-size: 11px;
            line-height: 1.45;
            color: #000;
            background: #fff;
            width: 72mm;
            max-width: 72mm;
            margin-left: auto;
            margin-right: auto;
        }
        .r-wrap { padding: 4px 4px 12px 2px; width: 100%; overflow: hidden; }
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
        .r-bill-title {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 0.06em;
            margin: 6px 0 8px;
            text-transform: none;
            transform: none;
            scale: none;
            line-height: 1.25;
        }
        .r-meta { margin: 2px 0; font-size: 10px; word-break: break-word; }
        .r-meta-label { font-weight: 700; }
        .r-info { margin: 4px 0; }
        .r-info .tot-row { padding: 2px 0; }
        table.items { width: 100%; border-collapse: separate; border-spacing: 0 4px; table-layout: fixed; }
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
            padding-right: 4px;
            width: 46%;
            line-height: 1.25;
            letter-spacing: -0.02em;
        }
        table.items td.item-qty { white-space: nowrap; text-align: center; width: 12%; font-size: 10px; padding: 0 2px; }
        table.items td.item-rate { white-space: nowrap; text-align: right; width: 18%; font-size: 10px; padding-left: 2px; padding-right: 2px; }
        table.items td.amt { text-align: right; white-space: nowrap; width: 24%; padding-left: 2px; padding-right: 2px; }
        table.items td.item-note { font-size: 10px; padding-top: 0; padding-bottom: 2px; color: #333; }
        .tot-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 8px;
            padding: 2px 0;
        }
        .tot-row > span:first-child {
            flex: 1 1 auto;
            min-width: 0;
            padding-right: 4px;
        }
        .tot-row > span:last-child {
            flex: 0 0 auto;
            text-align: right;
            white-space: nowrap;
            max-width: 48%;
        }
        .totals-block { margin-top: 2px; padding-right: 1px; }
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
        /* Simple + slightly larger than body — no giant black pill */
        .table-no {
            text-align: center;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: 0.02em;
            line-height: 1.2;
            text-transform: uppercase;
            margin: 6px 0 4px;
            padding: 0;
            border: 0;
            background: transparent;
            color: #000;
        }
        .r-status-spacer {
            height: 1.6em;
            line-height: 1.1;
            margin: 4px 0 2px;
        }
        .r-logo { max-width: 52mm; max-height: 22mm; object-fit: contain; margin: 0 auto 8px; display: block; }
        .r-powered {
            margin-top: 4px;
            text-align: center;
            font-size: 9px;
            color: #444;
            letter-spacing: 0.02em;
            padding: 0 2px;
            word-break: break-word;
        }
        .r-cut-mark {
            height: 8px;
        }
        .noprint { margin-top: 12px; text-align: center; }
        @media print {
            .noprint { display: none !important; }
            @page {
                size: 80mm 297mm;
                margin: 2mm 3mm;
            }
            html, body {
                width: 72mm !important;
                max-width: 72mm !important;
                margin: 0 auto !important;
                padding: 0 !important;
                background: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .r-wrap {
                width: 72mm !important;
                max-width: 72mm !important;
                padding: 0 1mm 2mm 1mm !important;
            }
            .table-no {
                font-size: 15px !important;
                border: 0 !important;
                background: transparent !important;
                color: #000 !important;
                padding: 0 !important;
            }
            .r-powered {
                page-break-after: always;
            }
            .r-cut-mark {
                display: none;
            }
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
    // Same as kitchen slip: show table whenever order has one.
    $tableLabel = $order->table?->name
        ? trim((string) $order->table->name)
        : null;
    if ($tableLabel === null || $tableLabel === '') {
        $roomNo = trim((string) ($order->room_no ?? ''));
        $guestName = trim((string) ($order->guest_name ?? ''));
        $isPhoneContactService = in_array($order->serviceTypeKey(), [
            \App\Models\PosOrder::SERVICE_DELIVERY,
            \App\Models\PosOrder::SERVICE_TAKEAWAY,
        ], true);
        if ($roomNo !== '') {
            $tableLabel = $isPhoneContactService ? $roomNo : 'Room '.$roomNo;
        } else {
            $tableLabel = $guestName !== '' ? $guestName : null;
        }
    }
    $isPhoneContactService = $isPhoneContactService
        ?? in_array($order->serviceTypeKey(), [
            \App\Models\PosOrder::SERVICE_DELIVERY,
            \App\Models\PosOrder::SERVICE_TAKEAWAY,
        ], true);
    if (
        $tableLabel !== null
        && $tableLabel !== ''
        && ! $isPhoneContactService
        && ! preg_match('/^(table|room)\b/i', $tableLabel)
    ) {
        $tableLabel = 'Table '.$tableLabel;
    }
@endphp
<div class="r-wrap">
    @if(!empty($isQuotation))
        <div class="center r-bill-title">Quotation Bill</div>
    @elseif(!empty($isUnpaid))
        {{-- Unpaid / final bill: no logo or company contact details --}}
        <div class="center r-bill-title">Provisional Bill</div>
    @else
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
    @endif

    @if($tableLabel)
        <div class="table-no">{{ $tableLabel }}</div>
    @endif

    <hr class="line">

    <div class="r-info">
        @if(!empty($isQuotation))
            <div class="tot-row"><span class="muted">Order Type:</span><span class="bold">{{ $orderType }}</span></div>
        @elseif(!empty($isUnpaid))
            <div class="tot-row"><span class="muted">Bill #:</span><span class="bold">{{ $order->order_no }}</span></div>
            <div class="tot-row"><span class="muted">Order Type:</span><span class="bold">{{ $orderType }}</span></div>
            <div class="tot-row"><span class="muted">Date</span><span>{{ ($order->updated_at ?? $order->created_at)?->format('d M Y H:i') }}</span></div>
            <div class="tot-row"><span class="muted">Cashier</span><span>{{ $order->user->name ?? auth()->user()?->name ?? '—' }}</span></div>
        @else
            <div class="tot-row"><span class="muted">Invoice Number:</span><span class="bold">{{ $order->order_no }}</span></div>
            <div class="tot-row"><span class="muted">Order Type:</span><span class="bold">{{ $orderType }}</span></div>
            @if($order->guest_name && ! ($tableLabel && trim((string) $order->guest_name) === $tableLabel))
                <div class="tot-row"><span class="muted">Guest</span><span>{{ $order->guest_name }}</span></div>
            @endif
            @php
                $roomNoRow = trim((string) ($order->room_no ?? ''));
                $showRoomOrPhoneRow = $roomNoRow !== ''
                    && ! ($tableLabel && (
                        $tableLabel === $roomNoRow
                        || str_starts_with($tableLabel, 'Room ')
                    ));
            @endphp
            @if($showRoomOrPhoneRow)
                <div class="tot-row">
                    <span class="muted">{{ !empty($isPhoneContactService) ? 'Phone' : 'Room' }}</span>
                    <span>{{ $order->room_no }}</span>
                </div>
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
            <span>{{ !empty($isQuotation) ? 'Quoted Amount' : (!empty($isUnpaid) ? 'AMOUNT DUE' : 'Grand Total') }}</span>
            <span>{{ $currency }}{{ fmt_num((float) $order->grand_total, 2) }}</span>
        </div>
        @if(empty($isUnpaid) && empty($isQuotation) && !$order->is_credit && $order->payments->isNotEmpty())
            <div class="pay-heading">Payment</div>
            @foreach($order->payments as $pay)
                <div class="tot-row">
                    <span class="muted">{{ ucfirst($pay->method) }}</span>
                    <span>{{ $currency }}{{ fmt_num((float) $pay->amount, 2) }}</span>
                </div>
            @endforeach
        @elseif(empty($isUnpaid) && empty($isQuotation) && $order->is_credit)
            <div class="center bold" style="margin-top:6px;">CREDIT SALE</div>
            <div class="center muted">Amount on account: {{ $currency }}{{ fmt_num((float) $order->grand_total, 2) }}</div>
        @endif
        @if(empty($isUnpaid) && empty($isQuotation) && $order->cash_tendered !== null && (float) $order->cash_tendered >= 0)
            <div class="tot-row" style="margin-top:4px;"><span class="muted">Received</span><span>{{ $currency }}{{ fmt_num((float) $order->cash_tendered, 2) }}</span></div>
            @if($order->cash_change !== null)
                <div class="tot-row bold"><span>Change</span><span>{{ $currency }}{{ fmt_num((float) $order->cash_change, 2) }}</span></div>
            @endif
        @endif
    </div>

    {{-- Delivery address only (not kitchen / item instructions) --}}
    @if(($order->serviceTypeKey() ?? null) === \App\Models\PosOrder::SERVICE_DELIVERY && !empty(trim((string) ($order->order_notes ?? ''))))
        <hr class="line">
        <div class="muted" style="font-size:10px;"><span class="bold">Address:</span> {{ $order->order_notes }}</div>
    @endif

    @if(!empty($isQuotation))
        <div class="center muted" style="font-size:10px;margin-top:8px;">This is a quotation only<br>Not a tax invoice / payment receipt</div>
        <div class="r-bill-status">QUOTATION</div>
    @elseif(!empty($isUnpaid))
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
    <div class="r-cut-mark" aria-hidden="true"></div>
</div>
<div class="noprint" style="max-width:80mm;margin:12px auto 24px;padding:0 8px;">
    <a href="{{ $backUrl ?? route('restaurant-pos.index') }}" style="display:block;text-align:center;text-decoration:none;font-weight:700;padding:14px 16px;border-radius:10px;margin-bottom:10px;background:#0d6efd;color:#fff;font-size:15px;">{{ $backLabel ?? '← Back to Restaurant POS' }}</a>
    @if(!empty($allowBillPrint))
        <button type="button" id="receiptThermalPrintBtn" style="display:block;width:100%;padding:12px;font-size:15px;font-weight:700;cursor:pointer;border:0;border-radius:8px;background:#111;color:#fff;margin-bottom:8px;">
            Print (80mm Thermal)
        </button>
        <button type="button" id="receiptBrowserPrintBtn" style="display:block;width:100%;padding:10px;font-size:13px;cursor:pointer;border:1px solid #999;border-radius:8px;background:#fff;">
            Browser print (fallback)
        </button>
        <p id="receiptPrintStatus" style="font-size:12px;color:#166534;text-align:center;margin:10px 0 0;display:none;"></p>
        <p style="font-size:10px;color:#666;text-align:center;margin:10px 0 0;">
            Thermal = Inventory → Kitchen Agents → <strong>CASHIER</strong> printer. Browser print A4 pe kharab nikalta hai — thermal use karein.
        </p>
    @endif
</div>
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
(function () {
    var orderId = @json((int) ($order->id ?? 0));
    var printUrl = @json($order->id ? route('restaurant-pos.cashier-print', $order) : '');
    var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    var statusEl = document.getElementById('receiptPrintStatus');
    var thermalBtn = document.getElementById('receiptThermalPrintBtn');
    var browserBtn = document.getElementById('receiptBrowserPrintBtn');
    var autoPrint = @json(!empty($autoPrint));

    function setStatus(msg, ok) {
        if (!statusEl) return;
        statusEl.style.display = 'block';
        statusEl.style.color = ok ? '#166534' : '#b91c1c';
        statusEl.textContent = msg;
    }

    function browserPrint() {
        window.print();
    }

    async function thermalPrint() {
        if (!printUrl || !orderId) {
            setStatus('Order print ke liye ready nahi.', false);
            return false;
        }
        if (thermalBtn) thermalBtn.disabled = true;
        try {
            var res = await fetch(printUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf
                }
            });
            var data = await res.json().catch(function () { return {}; });
            if (res.ok && data.ok) {
                setStatus('Thermal printer par print ho gaya (powered by ke baad cut).', true);
                return true;
            }
            if (data.fallback) {
                setStatus((data.message || 'Cashier printer set nahi.') + ' Browser print use karein.', false);
                return false;
            }
            setStatus(data.message || 'Thermal print fail.', false);
            return false;
        } catch (e) {
            setStatus(e.message || 'Thermal print fail.', false);
            return false;
        } finally {
            if (thermalBtn) thermalBtn.disabled = false;
        }
    }

    if (thermalBtn) {
        thermalBtn.addEventListener('click', function () { thermalPrint(); });
    }
    if (browserBtn) {
        browserBtn.addEventListener('click', browserPrint);
    }

    if (autoPrint) {
        var ran = false;
        function runAuto() {
            if (ran) return;
            ran = true;
            thermalPrint().then(function (ok) {
                if (!ok) {
                    // No network printer → last resort browser dialog
                    setTimeout(browserPrint, 200);
                }
            });
        }
        if (document.readyState === 'complete') runAuto();
        else window.addEventListener('load', runAuto);
    }
})();
</script>
</body>
</html>
