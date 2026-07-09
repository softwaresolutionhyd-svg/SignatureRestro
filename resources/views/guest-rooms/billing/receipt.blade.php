<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bill {{ $bill->bill_no }}</title>
    <style>
        @@page { size: 80mm auto; margin: 2mm; }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, 'Segoe UI', Tahoma, sans-serif;
            font-size: 12px;
            line-height: 1.45;
            color: #000;
            background: #fff;
            max-width: 80mm;
            margin-left: auto;
            margin-right: auto;
            -webkit-font-smoothing: antialiased;
        }
        .r-wrap { padding: 6px 8px 14px; }
        .center { text-align: center; }
        .bold { font-weight: 700; }
        .muted { color: #000; font-weight: 600; }
        .line { border: 0; border-top: 2px dashed #000; margin: 10px 0; }
        .r-company { font-size: 16px; font-weight: 800; letter-spacing: 0.02em; }
        .r-sub { font-size: 11px; font-weight: 600; }
        .r-bill-title { font-size: 13px; font-weight: 800; margin: 6px 0; letter-spacing: 0.03em; }
        .r-unpaid-banner { font-size: 14px; font-weight: 800; margin-bottom: 4px; text-transform: uppercase; }
        .r-unpaid-note { font-size: 10px; font-weight: 600; margin-bottom: 8px; }
        .section-title {
            font-weight: 800;
            font-size: 12px;
            margin: 8px 0 6px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            border-bottom: 1px solid #000;
            padding-bottom: 3px;
        }
        table.items { width: 100%; border-collapse: collapse; }
        table.items td { padding: 4px 0; vertical-align: top; font-size: 12px; font-weight: 600; }
        table.items td.item-name { font-weight: 700; }
        table.items td.amt { text-align: right; white-space: nowrap; width: 34%; font-weight: 700; }
        table.items td.qty { text-align: right; white-space: nowrap; width: 20%; font-size: 11px; font-weight: 600; }
        .tot-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 3px 0;
            gap: 10px;
            font-size: 12px;
            font-weight: 600;
        }
        .tot-row span:last-child { text-align: right; font-weight: 700; }
        .tot-row.bold span { font-weight: 800; }
        .sub-detail { font-size: 10px; font-weight: 600; display: block; margin-top: 2px; }
        .cafe-meta { font-size: 11px; font-weight: 700; margin: 6px 0 4px; }
        .tag-unpaid { font-weight: 800; }
        .tag-paid { font-weight: 800; }
        .grand { font-size: 16px; font-weight: 800; margin-top: 6px; }
        .grand span { font-weight: 800; }
        .balance-due { font-size: 15px; font-weight: 800; margin-top: 4px; }
        .r-footer { font-size: 11px; font-weight: 600; margin-top: 10px; }
        .noprint { margin-top: 12px; text-align: center; }
        @@media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .noprint { display: none !important; }
            html, body {
                max-width: none;
                width: 80mm;
                font-size: 13px;
                color: #000 !important;
            }
            .r-company { font-size: 17px; }
            .r-bill-title { font-size: 14px; }
            .section-title { font-size: 13px; }
            table.items td { font-size: 13px; padding: 5px 0; }
            table.items td.qty { font-size: 12px; }
            .tot-row { font-size: 13px; padding: 4px 0; }
            .grand { font-size: 18px; }
            .balance-due { font-size: 17px; }
            .sub-detail { font-size: 11px; }
        }
    </style>
</head>
<body>
@php
    $booking = $bill->booking;
    $sym = $settings['currency_symbol'] ?? 'Rs.';
    $b = $breakdown;
    $isUnpaidPreview = $isUnpaidPreview ?? false;
@endphp
@if(session('success'))
    <div class="noprint" style="max-width:80mm;margin:0 auto 8px;padding:8px 10px;background:#d1fae5;border:1px solid #6ee7b7;border-radius:8px;font-size:12px;text-align:center;">
        {{ session('success') }}
    </div>
@endif
<div class="r-wrap">
    <div class="center r-company">{{ $settings['company_name'] ?? config('app.name') }}</div>
    @if(!empty($settings['company_address']))
        <div class="center r-sub">{{ $settings['company_address'] }}</div>
    @endif
    @if(!empty($settings['company_phone']))
        <div class="center r-sub">{{ $settings['company_phone'] }}</div>
    @endif
    <hr class="line">
    @if($isUnpaidPreview)
        <div class="center r-unpaid-banner">UNPAID BILL</div>
        <div class="center r-unpaid-note">Checkout se pehle — provisional</div>
    @endif
    <div class="center r-bill-title">{{ $isUnpaidPreview ? 'COMPLETE BILL (UNPAID)' : 'COMPLETE GUEST BILL' }}</div>
    <div class="tot-row"><span class="muted">Bill</span><span class="bold">{{ $bill->bill_no }}</span></div>
    @if($booking)
        <div class="tot-row"><span class="muted">Booking</span><span>{{ $booking->booking_no }}</span></div>
        <div class="tot-row"><span class="muted">Guest</span><span>{{ $booking->guestDisplayName() }}</span></div>
        @if($booking->guest_phone)
            <div class="tot-row"><span class="muted">Phone</span><span>{{ $booking->guest_phone }}</span></div>
        @endif
        <div class="tot-row"><span class="muted">Room(s)</span><span>{{ $booking->roomNumbersLabel() }}</span></div>
        <div class="tot-row"><span class="muted">Check-in</span><span>{{ $booking->checkInDisplayLabel() }}</span></div>
        <div class="tot-row"><span class="muted">Check-out</span><span>{{ $booking->actual_check_out ? fmt_datetime($booking->actual_check_out) : fmt_date($booking->check_out_date) }}</span></div>
        @if($booking->nights)
            <div class="tot-row"><span class="muted">Nights</span><span>{{ $booking->nights }}</span></div>
        @endif
    @endif
    <div class="tot-row"><span class="muted">Date</span><span>{{ fmt_datetime($bill->billed_at ?? now()) }}</span></div>

    <hr class="line">
    <div class="section-title">Room Charges</div>
    <table class="items">
        @if($b['room_rent'] > 0)
            <tr>
                <td class="item-name">Room rent</td>
                <td class="amt">{{ $sym }}{{ fmt_num($b['room_rent'], 2) }}</td>
            </tr>
        @endif
        @foreach($b['mattress_lines'] as $line)
            <tr>
                <td>
                    Mattress
                    <span class="sub-detail">
                        {{ $line['label'] }}
                        @if(!empty($line['detail']))
                            · {{ $line['detail'] }}
                        @endif
                    </span>
                </td>
                <td class="amt">{{ $sym }}{{ fmt_num($line['amount'], 2) }}</td>
            </tr>
        @endforeach
        @if($b['mattress_total'] > 0 && $b['mattress_lines'] === [])
            <tr>
                <td>Mattress charges</td>
                <td class="amt">{{ $sym }}{{ fmt_num($b['mattress_total'], 2) }}</td>
            </tr>
        @endif
        @foreach($b['laundry_lines'] as $line)
            <tr>
                <td>
                    Laundry
                    <span class="sub-detail">{{ $line['label'] }}</span>
                </td>
                <td class="amt">{{ $sym }}{{ fmt_num($line['amount'], 2) }}</td>
            </tr>
        @endforeach
        @foreach($b['late_checkout_lines'] as $line)
            <tr>
                <td>
                    Late checkout
                    <span class="sub-detail">{{ $line['label'] }}</span>
                </td>
                <td class="amt">{{ $sym }}{{ fmt_num($line['amount'], 2) }}</td>
            </tr>
        @endforeach
        @if($b['late_checkout_total'] > 0 && $b['late_checkout_lines'] === [])
            <tr>
                <td>Late checkout charges</td>
                <td class="amt">{{ $sym }}{{ fmt_num($b['late_checkout_total'], 2) }}</td>
            </tr>
        @endif
        @foreach($b['other_lines'] as $line)
            <tr>
                <td>
                    Breakage / other
                    <span class="sub-detail">{{ $line['label'] }}</span>
                </td>
                <td class="amt">{{ $sym }}{{ fmt_num($line['amount'], 2) }}</td>
            </tr>
        @endforeach
    </table>
    @if($b['room_discount'] > 0)
        <div class="tot-row"><span class="muted">Room discount</span><span>-{{ $sym }}{{ fmt_num($b['room_discount'], 2) }}</span></div>
    @endif
    @if($b['room_tax'] > 0)
        <div class="tot-row">
            <span class="muted">Room tax{{ ($b['room_tax_percent'] ?? 0) > 0 ? ' (' . fmt_num($b['room_tax_percent'], 2) . '%)' : '' }}</span>
            <span>{{ $sym }}{{ fmt_num($b['room_tax'], 2) }}</span>
        </div>
    @endif
    <div class="tot-row bold"><span>Room total</span><span>{{ $sym }}{{ fmt_num($b['room_total'], 2) }}</span></div>

    @if($b['cafe_total'] > 0)
        <hr class="line">
        <div class="section-title">Cafe / In-House</div>
        @foreach($b['cafe_orders'] as $cafeOrder)
            <div class="cafe-meta">
                {{ $cafeOrder['order_no'] }}
                @if(!empty($cafeOrder['room_no']))
                    · Room {{ $cafeOrder['room_no'] }}
                @endif
                @if(($cafeOrder['status'] ?? '') === 'draft')
                    <span class="tag-unpaid"> · [UNPAID]</span>
                @else
                    <span class="tag-paid"> · [PAID]</span>
                @endif
            </div>
            <table class="items">
                @foreach($cafeOrder['items'] as $item)
                    <tr>
                        <td class="item-name">{{ $item['name'] }}</td>
                        <td class="qty">{{ $item['qty'] }}</td>
                        <td class="amt">{{ $sym }}{{ fmt_num($item['amount'], 2) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="2" class="bold">Cafe bill</td>
                    <td class="amt bold">{{ $sym }}{{ fmt_num($cafeOrder['total'], 2) }}</td>
                </tr>
            </table>
        @endforeach
        <div class="tot-row bold"><span>Cafe total</span><span>{{ $sym }}{{ fmt_num($b['cafe_total'], 2) }}</span></div>
        @if($isUnpaidPreview && ($b['cafe_pending_total'] ?? 0) > 0)
            <div class="tot-row"><span class="muted">↳ Cafe unpaid</span><span>{{ $sym }}{{ fmt_num($b['cafe_pending_total'], 2) }}</span></div>
        @endif
    @endif

    <hr class="line">
    <div class="tot-row grand">
        <span>GRAND TOTAL</span>
        <span>{{ $sym }}{{ fmt_num($b['grand_total'], 2) }}</span>
    </div>
    @if($isUnpaidPreview)
        @if(($b['room_paid'] ?? 0) > 0 || ($b['cafe_paid_total'] ?? 0) > 0)
            <div class="tot-row"><span class="muted">Already paid</span><span>{{ $sym }}{{ fmt_num($b['paid_total'], 2) }}</span></div>
        @endif
        <div class="tot-row balance-due">
            <span>BALANCE DUE</span>
            <span>{{ $sym }}{{ fmt_num($b['balance_due'], 2) }}</span>
        </div>
    @else
        <div class="tot-row"><span class="muted">Paid</span><span>{{ $sym }}{{ fmt_num($b['paid_total'], 2) }}</span></div>
        @if($b['balance_due'] > 0)
            <div class="tot-row bold"><span>Balance due</span><span>{{ $sym }}{{ fmt_num($b['balance_due'], 2) }}</span></div>
        @else
            <div class="tot-row bold"><span>Paid in full</span><span>✓</span></div>
        @endif
    @endif
    @if($bill->payment_method && ! $isUnpaidPreview)
        <div class="tot-row"><span class="muted">Payment</span><span>{{ ucfirst($bill->payment_method) }}</span></div>
    @endif

    <hr class="line">
    <div class="center r-footer">Thank you — {{ $settings['company_name'] ?? config('app.name') }}</div>
    @if(!empty(trim((string) ($settings['pos_receipt_footer_note'] ?? ''))))
        <div class="center r-footer" style="white-space:pre-line;">{{ $settings['pos_receipt_footer_note'] }}</div>
    @endif
</div>
<div class="noprint" style="max-width:80mm;margin:12px auto 24px;padding:0 8px;">
    @if($isUnpaidPreview && $booking)
        <a href="{{ route('guest-rooms.checkout-counter.show', $booking) }}" style="display:block;text-align:center;text-decoration:none;font-weight:700;padding:14px 16px;border-radius:10px;margin-bottom:10px;background:#0d6efd;color:#fff;font-size:15px;">← Checkout Counter</a>
    @else
        <a href="{{ route('guest-rooms.billing.show', $bill) }}" style="display:block;text-align:center;text-decoration:none;font-weight:700;padding:14px 16px;border-radius:10px;margin-bottom:10px;background:#0d6efd;color:#fff;font-size:15px;">← Bill details</a>
        <a href="{{ route('guest-rooms.checkout-counter.index') }}" style="display:block;text-align:center;text-decoration:none;padding:10px 16px;border-radius:10px;margin-bottom:10px;border:1px solid #ccc;color:#333;font-size:14px;">Checkout Counter</a>
    @endif
    <button type="button" onclick="window.print()" style="display:block;width:100%;padding:10px;font-size:14px;cursor:pointer;border:1px solid #999;border-radius:8px;background:#fff;">Print again</button>
    <p style="font-size:10px;color:#666;text-align:center;margin:10px 0 0;">80mm thermal — select your receipt printer.</p>
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
