<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $ok ? 'Printed' : 'Print failed' }} — {{ $order->order_no }}</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, sans-serif;
            background: #f4f6f8;
            color: #111;
        }
        .box {
            width: min(420px, 92vw);
            background: #fff;
            border-radius: 12px;
            padding: 28px 24px;
            box-shadow: 0 8px 28px rgba(0,0,0,.08);
            text-align: center;
        }
        .icon { font-size: 2rem; margin-bottom: 8px; }
        .ok { color: #16a34a; }
        .fail { color: #dc2626; }
        h1 { font-size: 1.15rem; margin: 0 0 8px; }
        p { margin: 0 0 18px; color: #555; font-size: .95rem; line-height: 1.45; }
        .meta { font-size: .85rem; color: #777; margin-bottom: 18px; }
        a {
            display: inline-block;
            text-decoration: none;
            background: #0d6efd;
            color: #fff;
            padding: 10px 16px;
            border-radius: 8px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="box">
        <div class="icon {{ $ok ? 'ok' : 'fail' }}">{{ $ok ? '✓' : '✕' }}</div>
        <h1>{{ $ok ? 'Thermal print OK' : 'Print failed' }}</h1>
        <div class="meta">Bill #{{ $order->order_no }} · {{ $currency }} {{ fmt_num((float) $order->grand_total, 2) }}</div>
        <p>{{ $message }}</p>
        <a href="{{ route('reports.sales') }}">← Sales Report</a>
    </div>
</body>
</html>
