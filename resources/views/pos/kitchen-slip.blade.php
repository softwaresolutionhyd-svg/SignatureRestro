<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kitchen {{ $order->order_no }}</title>
    <style>
        @page { size: 80mm auto; margin: 3mm; }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            font-family: ui-monospace, 'Cascadia Code', 'Courier New', monospace;
            font-size: 12px;
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
        .k-title { font-size: 16px; font-weight: 800; letter-spacing: 0.06em; margin: 4px 0; }
        .tot-row { display: flex; justify-content: space-between; padding: 2px 0; }
        .tot-row.k-table-row span:last-child {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0.02em;
        }
        table.items { width: 100%; border-collapse: collapse; }
        table.items td { padding: 5px 0; vertical-align: top; }
        table.items td.item-name {
            word-break: break-word;
            font-weight: 800;
            font-size: 15px;
            line-height: 1.25;
            padding-right: 6px;
        }
        table.items td.item-qty {
            white-space: nowrap;
            text-align: center;
            width: 20%;
            font-weight: 800;
            font-size: 17px;
            line-height: 1.25;
        }
        table.items td.item-note { font-size: 10px; padding-top: 0; padding-bottom: 4px; color: #333; }
        .noprint { margin-top: 12px; text-align: center; }
        @media print {
            .noprint { display: none !important; }
            html, body { max-width: none; }
            .tot-row.k-table-row span:last-child { font-size: 19px; }
            table.items td.item-name { font-size: 16px; }
            table.items td.item-qty { font-size: 18px; }
        }
    </style>
</head>
<body>
<div class="r-wrap">
    <div class="center k-title">KITCHEN ORDER</div>
    <div class="center bold" style="font-size:14px;">{{ $settings['company_name'] ?? config('app.name') }}</div>
    <hr class="line">
    <div class="tot-row"><span class="muted">Order</span><span class="bold">{{ $order->order_no }}</span></div>
    @if(!empty($settings['pos_enable_tables']) && $settings['pos_enable_tables'] === '1' && $order->table)
        <div class="tot-row k-table-row"><span class="muted">Table</span><span>{{ $order->table->name }}</span></div>
    @endif
    @if($order->serviceTypeLabel())
        <div class="tot-row"><span class="muted">Service</span><span>{{ $order->serviceTypeLabel() }}</span></div>
    @endif
    <div class="tot-row"><span class="muted">Time</span><span>{{ now()->format('d M Y H:i') }}</span></div>
    <div class="tot-row"><span class="muted">Cashier</span><span>{{ $order->user->name ?? '—' }}</span></div>
    <hr class="line">
    <table class="items">
        @foreach($kitchenItems as $line)
            <tr>
                <td class="item-name">{{ $line->product->name ?? 'Item' }}</td>
                <td class="item-qty">{{ fmt_num((float) $line->qty, 3) }}</td>
            </tr>
            @if(trim((string) ($line->notes ?? '')) !== '')
            <tr>
                <td colspan="2" class="item-note muted">Note: {{ $line->notes }}</td>
            </tr>
            @endif
        @endforeach
    </table>
    <hr class="line">
    <div class="center bold" style="font-size:13px;margin-top:6px;">{{ $kitchenItems->count() }} item(s)</div>
</div>
<div class="noprint" style="max-width:80mm;margin:12px auto 24px;padding:0 8px;">
    <a href="{{ $backUrl ?? route('restaurant-pos.index') }}" style="display:block;text-align:center;text-decoration:none;font-weight:700;padding:14px 16px;border-radius:10px;margin-bottom:10px;background:#0d6efd;color:#fff;font-size:15px;">{{ $backLabel ?? '← Back to Restaurant POS' }}</a>
    <button type="button" onclick="window.print()" style="display:block;width:100%;padding:10px;font-size:14px;cursor:pointer;border:1px solid #999;border-radius:8px;background:#fff;">Print again</button>
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
