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
        .dept {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0.04em;
            margin: 2px 0 4px;
            text-transform: uppercase;
        }
        .company {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .meta-row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            font-weight: 700;
            padding: 2px 0 6px;
        }
        .table-no {
            text-align: center;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 0.02em;
            margin: 8px 0 2px;
            line-height: 1.15;
        }
        .service-tag {
            text-align: center;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.06em;
            margin: 0 0 6px;
            text-transform: uppercase;
        }
        .by-line { margin: 2px 0 6px; }
        .bill-notes {
            margin: 4px 0 8px;
            white-space: pre-wrap;
            font-size: 11px;
        }
        .bill-notes-label { font-weight: 700; font-size: 12px; margin-bottom: 2px; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items td { padding: 5px 0; vertical-align: top; }
        table.items thead td {
            font-weight: 800;
            font-size: 12px;
            border-bottom: 1px dashed #000;
            padding-bottom: 4px;
        }
        table.items td.item-name {
            word-break: break-word;
            font-weight: 800;
            font-size: 16px;
            line-height: 1.2;
            padding-right: 6px;
            padding-top: 8px;
        }
        table.items td.item-qty {
            white-space: nowrap;
            text-align: center;
            width: 18%;
            font-weight: 800;
            font-size: 16px;
            line-height: 1.2;
            padding-top: 8px;
            padding-right: 8px;
        }
        table.items td.item-note {
            font-size: 11px;
            padding-top: 0;
            padding-bottom: 4px;
            color: #222;
        }
        .end-mark {
            text-align: center;
            font-weight: 800;
            font-size: 14px;
            margin-top: 28px;
            letter-spacing: 0.08em;
        }
        .noprint { margin-top: 12px; text-align: center; }
        @media print {
            .noprint { display: none !important; }
            html, body { max-width: none; }
            .table-no { font-size: 20px; }
            table.items td.item-name { font-size: 16px; }
            table.items td.item-qty { font-size: 16px; }
        }
    </style>
</head>
<body>
@php
    $company = strtoupper(trim((string) ($settings['company_name'] ?? config('app.name'))));
    $company = preg_replace('/\bRESRO\b/u', 'RESTRO', $company) ?? $company;
    if ($company === '') {
        $company = 'SIGNATURE RESTRO';
    }
    $departmentName = trim((string) ($departmentName ?? ''));
    if ($departmentName === '') {
        $departmentName = 'KITCHEN';
    }
    $tableLabel = $order->table?->name
        ?? (trim((string) ($order->room_no ?? '')) !== '' ? 'Room ' . trim((string) $order->room_no) : null)
        ?? (trim((string) ($order->guest_name ?? '')) !== '' ? trim((string) $order->guest_name) : null);
    $serviceTag = match ($order->serviceTypeKey()) {
        \App\Models\PosOrder::SERVICE_DINE_IN => 'DINE-IN',
        \App\Models\PosOrder::SERVICE_TAKEAWAY => 'TAKEAWAY',
        \App\Models\PosOrder::SERVICE_DELIVERY => 'DELIVERY',
        default => null,
    };
    $billKitchenNotes = trim((string) ($order->kitchen_notes ?? ''));
@endphp
<div class="r-wrap">
    <div class="center dept">{{ $departmentName }}</div>
    <div class="center company">{{ $company }}</div>

    <div class="meta-row">
        <span>Bill#: {{ $order->order_no }}</span>
        <span>{{ now()->format('d-M-Y h:i A') }}</span>
    </div>

    @if($tableLabel)
        <div class="table-no">{{ $tableLabel }}</div>
    @endif
    @if($serviceTag)
        <div class="service-tag">{{ $serviceTag }}</div>
    @endif

    <div class="by-line">by: {{ $order->user->name ?? '—' }}</div>

    @if($billKitchenNotes !== '')
        <div class="bill-notes">
            <div class="bill-notes-label">Complete bill Notes:</div>
            <div>{{ $billKitchenNotes }}</div>
        </div>
    @endif

    <hr class="line">

    @if(!empty($isAddonPrint))
        <div class="center bold" style="font-size:15px;margin:6px 0 8px;letter-spacing:0.04em;">+ NEW ITEMS</div>
    @endif

    <table class="items">
        <thead>
            <tr>
                <td>Items</td>
                <td class="item-qty">QTY</td>
            </tr>
        </thead>
        <tbody>
            @foreach($kitchenItems as $line)
                <tr>
                    <td class="item-name">{{ $line->product->name ?? 'Item' }}</td>
                    <td class="item-qty">{{ fmt_num((float) $line->qty, 3) }}</td>
                </tr>
                @if(trim((string) ($line->notes ?? '')) !== '')
                <tr>
                    <td colspan="2" class="item-note">*{{ $line->notes }}</td>
                </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    <div class="end-mark">END</div>
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
