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
            line-height: 1.35;
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
        table.items { width: 100%; border-collapse: collapse; }
        table.items td { padding: 3px 0; vertical-align: top; }
        table.items td.item-name { word-break: break-word; padding-right: 4px; }
        table.items td.item-qty { white-space: nowrap; text-align: center; width: 22%; font-size: 10px; color: #333; padding: 3px 2px; }
        table.items td.amt { text-align: right; white-space: nowrap; width: 28%; }
        table.items td.item-note { font-size: 10px; padding-top: 0; padding-bottom: 4px; color: #333; }
        .tot-row { display: flex; justify-content: space-between; padding: 2px 0; }
        .r-logo { max-width: 56mm; max-height: 18mm; object-fit: contain; margin: 0 auto 6px; display: block; }
        .r-unpaid-banner { font-size: 13px; font-weight: 800; letter-spacing: 0.04em; }
        .r-unpaid-note { font-size: 10px; font-weight: 600; margin-top: 2px; }
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
<div class="r-wrap">
    @if(!empty($settings['company_logo_url']))
        <img src="{{ $settings['company_logo_url'] }}" alt="" class="r-logo">
    @endif
    <div class="center bold" style="font-size:14px;">{{ $settings['company_name'] ?? config('app.name') }}</div>
    @if(!empty($settings['company_address']))
        <div class="center muted" style="font-size:10px;">{{ $settings['company_address'] }}</div>
    @endif
    @if(!empty($settings['company_phone']))
        <div class="center muted" style="font-size:10px;">{{ $settings['company_phone'] }}</div>
    @endif
    <hr class="line">
    @if(!empty($isUnpaid))
        <div class="center bold r-unpaid-banner">UNPAID BILL</div>
        <div class="center muted r-unpaid-note">Payment pending — provisional bill</div>
    @else
        <div class="center bold">{{ $order->type === 'refund' ? 'REFUND' : 'SALES RECEIPT' }}</div>
    @endif
    <div class="tot-row"><span class="muted">Order</span><span class="bold">{{ $order->order_no }}</span></div>
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
    @if($order->serviceTypeLabel())
        <div class="tot-row"><span class="muted">Service</span><span>{{ $order->serviceTypeLabel() }}</span></div>
    @endif
    <div class="tot-row"><span class="muted">Type</span><span>{{ $order->customerTypeLabel() }}</span></div>
    <div class="tot-row"><span class="muted">Date</span><span>{{ ($order->paid_at ?? $order->updated_at ?? $order->created_at)?->format('d M Y H:i') }}</span></div>
    <div class="tot-row"><span class="muted">Cashier</span><span>{{ $order->user->name ?? '—' }}</span></div>
    @if($order->is_credit && $order->contact)
        <div class="tot-row"><span class="muted">Customer</span><span>{{ $order->contact->name }}</span></div>
        @if($order->contact->phone)
            <div class="tot-row"><span class="muted">Phone</span><span>{{ $order->contact->phone }}</span></div>
        @endif
    @endif
    <hr class="line">
    <table class="items">
        @foreach($order->items as $line)
            <tr>
                <td class="item-name bold">{{ $line->product->name ?? 'Item' }}</td>
                <td class="item-qty">{{ fmt_num((float) $line->qty, 3) }}</td>
                <td class="amt bold">{{ $settings['currency_symbol'] ?? 'Rs.' }}{{ fmt_num((float) $line->total, 2) }}</td>
            </tr>
            @if(trim((string) ($line->notes ?? '')) !== '')
            <tr>
                <td colspan="3" class="item-note muted">Note: {{ $line->notes }}</td>
            </tr>
            @endif
        @endforeach
    </table>
    <hr class="line">
    <div class="tot-row"><span class="muted">Subtotal</span><span>{{ $settings['currency_symbol'] ?? 'Rs.' }}{{ fmt_num((float) $order->subtotal, 2) }}</span></div>
    @if((float) $order->discount_total > 0)
        <div class="tot-row"><span class="muted">Discount <span style="font-size:9px;">(on profit)</span></span><span>-{{ $settings['currency_symbol'] ?? 'Rs.' }}{{ fmt_num((float) $order->discount_total, 2) }}</span></div>
    @endif
    @if((float) $order->tax_total > 0)
        <div class="tot-row"><span class="muted">Tax</span><span>{{ $settings['currency_symbol'] ?? 'Rs.' }}{{ fmt_num((float) $order->tax_total, 2) }}</span></div>
    @endif
    <div class="tot-row bold" style="font-size:13px;margin-top:4px;">
        <span>{{ !empty($isUnpaid) ? 'AMOUNT DUE' : 'TOTAL' }}</span>
        <span>{{ $settings['currency_symbol'] ?? 'Rs.' }}{{ fmt_num((float) $order->grand_total, 2) }}</span>
    </div>
    @if(empty($isUnpaid) && !$order->is_credit && $order->payments->isNotEmpty())
        <hr class="line">
        <div class="bold" style="margin-bottom:4px;">Payment</div>
        @foreach($order->payments as $pay)
            <div class="tot-row">
                <span class="muted">{{ ucfirst($pay->method) }}</span>
                <span>{{ $settings['currency_symbol'] ?? 'Rs.' }}{{ fmt_num((float) $pay->amount, 2) }}</span>
            </div>
        @endforeach
    @elseif(empty($isUnpaid) && $order->is_credit)
        <hr class="line">
        <div class="center bold">CREDIT SALE</div>
        <div class="center muted">Amount on account: {{ $settings['currency_symbol'] ?? 'Rs.' }}{{ fmt_num((float) $order->grand_total, 2) }}</div>
    @endif
    @if(empty($isUnpaid) && $order->cash_tendered !== null && (float) $order->cash_tendered >= 0)
        <hr class="line">
        <div class="tot-row"><span class="muted">Received</span><span>{{ $settings['currency_symbol'] ?? 'Rs.' }}{{ fmt_num((float) $order->cash_tendered, 2) }}</span></div>
        @if($order->cash_change !== null)
            <div class="tot-row bold"><span>Change</span><span>{{ $settings['currency_symbol'] ?? 'Rs.' }}{{ fmt_num((float) $order->cash_change, 2) }}</span></div>
        @endif
    @endif
    <hr class="line">
    <div class="center muted" style="font-size:10px;margin-top:8px;">Thank you — {{ $settings['company_name'] ?? config('app.name') }}</div>
    @if(!empty(trim((string) ($settings['pos_receipt_footer_note'] ?? ''))))
        <div class="center muted" style="font-size:10px;margin-top:6px;white-space:pre-line;">{{ $settings['pos_receipt_footer_note'] }}</div>
    @endif
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
    function doPrint() {
        window.print();
    }
    if (document.readyState === 'complete') {
        setTimeout(doPrint, 300);
    } else {
        window.addEventListener('load', function () { setTimeout(doPrint, 300); });
    }
})();
</script>
@endif
</body>
</html>
