@php
    $s = $statusMap[$expense->status] ?? ['label' => $expense->status, 'color' => 'secondary'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Expense #{{ $expense->id }} — {{ $companyName }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { size: A4 portrait; margin: 12mm; }
        body {
            margin: 0;
            padding: 16px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            color: #111;
            background: #fff;
        }
        .noprint { margin-bottom: 14px; }
        .noprint button, .noprint a {
            margin-right: 6px;
            padding: 7px 14px;
            font-size: 12px;
            border: 1px solid #333;
            background: #fff;
            color: #111;
            cursor: pointer;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
        }
        .head {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            border-bottom: 2px solid #111;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .head img { max-height: 64px; max-width: 180px; }
        .head h1 { margin: 0; font-size: 18pt; }
        .head .sub { margin-top: 4px; font-size: 10pt; color: #333; }
        .meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 24px;
            margin-bottom: 16px;
            font-size: 10.5pt;
            line-height: 1.45;
        }
        .meta .lbl { color: #555; font-size: 9.5pt; }
        .meta .val { font-weight: 700; }
        table { width: 100%; border-collapse: collapse; font-size: 10pt; }
        th, td { border: 1px solid #333; padding: 6px 8px; vertical-align: top; }
        th { background: #eee; text-align: left; font-size: 9.5pt; text-transform: uppercase; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        .totals {
            width: 280px;
            margin-left: auto;
            margin-top: 12px;
            border-collapse: collapse;
            font-size: 10.5pt;
        }
        .totals th, .totals td { border: 1px solid #333; padding: 6px 8px; }
        .totals th { text-align: left; background: #eee; }
        .totals td { text-align: right; font-weight: 700; }
        .notes { margin-top: 14px; font-size: 10pt; white-space: pre-line; }
        .foot { margin-top: 28px; font-size: 9pt; color: #555; display: flex; justify-content: space-between; gap: 12px; }
        @media print {
            body { padding: 0; }
            .noprint { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="noprint">
        <button type="button" onclick="window.print()">Print / PDF</button>
        <a href="{{ route('expenses.show', $expense) }}">Open</a>
        <a href="{{ route('expenses.index') }}">Back</a>
    </div>

    <div class="head">
        <div>
            @if(!empty($companyLogo))
                <img src="{{ $companyLogo }}" alt="">
            @endif
            <h1>{{ $companyName }}</h1>
            <div class="sub">Expense Voucher</div>
        </div>
        <div style="text-align:right;">
            <div style="font-size:16pt;font-weight:700;">#{{ $expense->id }}</div>
            <div class="sub">{{ $s['label'] }}</div>
            <div class="sub">{{ $expense->expense_date?->format('d M Y') ?? '—' }}</div>
        </div>
    </div>

    <div class="meta">
        <div>
            <div class="lbl">Category</div>
            <div class="val">{{ $expense->category?->name ?: '—' }}</div>
            <div class="lbl" style="margin-top:8px;">Description</div>
            <div class="val">{{ $expense->description }}</div>
        </div>
        <div>
            @if($expense->employee)
                <div class="lbl">Employee</div>
                <div class="val">{{ $expense->employee->name }} @if($expense->employee->employee_no)({{ $expense->employee->employee_no }})@endif</div>
            @endif
            @if($expense->paid_at)
                <div class="lbl" style="margin-top:8px;">Paid at</div>
                <div class="val">{{ $expense->paid_at->format('d M Y, h:i A') }}</div>
                @if($expense->approvedBy)
                    <div class="lbl" style="margin-top:8px;">Paid by</div>
                    <div class="val">{{ $expense->approvedBy->name }}</div>
                @endif
            @endif
        </div>
    </div>

    <table>
        <thead>
        <tr>
            <th>Description</th>
            <th class="num" style="width:80px;">Qty</th>
            <th class="num" style="width:110px;">Unit cost</th>
            <th class="num" style="width:110px;">Subtotal</th>
            <th class="num" style="width:100px;">Tax</th>
            <th class="num" style="width:110px;">Total</th>
        </tr>
        </thead>
        <tbody>
        @php
            $displayLines = ($expense->lines ?? collect())->isNotEmpty()
                ? $expense->lines
                : collect([(object) [
                    'description' => $expense->description,
                    'qty' => $expense->qty,
                    'unit_amount' => $expense->unit_amount,
                    'tax_percent' => $expense->tax_percent,
                    'total_amount' => $expense->total_amount,
                    'tax_amount' => $expense->tax_amount,
                    'line_total' => $expense->grand_total,
                ]]);
        @endphp
        @foreach($displayLines as $line)
        <tr>
            <td>{{ $line->description }}</td>
            <td class="num">{{ fmt_num((float) $line->qty, 3) }}</td>
            <td class="num">{{ $currency }} {{ fmt_num((float) $line->unit_amount, 2) }}</td>
            <td class="num">{{ $currency }} {{ fmt_num((float) $line->total_amount, 2) }}</td>
            <td class="num">{{ $currency }} {{ fmt_num((float) $line->tax_amount, 2) }}</td>
            <td class="num">{{ $currency }} {{ fmt_num((float) ($line->line_total ?? ((float)$line->total_amount + (float)$line->tax_amount)), 2) }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <th>Subtotal</th>
            <td>{{ $currency }} {{ fmt_num((float) $expense->total_amount, 2) }}</td>
        </tr>
        <tr>
            <th>Tax</th>
            <td>{{ $currency }} {{ fmt_num((float) $expense->tax_amount, 2) }}</td>
        </tr>
        <tr>
            <th>Grand total</th>
            <td>{{ $currency }} {{ fmt_num((float) $expense->grand_total, 2) }}</td>
        </tr>
    </table>

    @if(trim((string) ($expense->notes ?? '')) !== '')
        <div class="notes"><strong>Notes:</strong> {{ $expense->notes }}</div>
    @endif

    @if(trim((string) ($expense->refuse_reason ?? '')) !== '')
        <div class="notes"><strong>Refuse reason:</strong> {{ $expense->refuse_reason }}</div>
    @endif

    <div class="foot">
        <div>Printed: {{ now()->timezone(config('app.timezone'))->format('d M Y, h:i A') }}</div>
        <div>Signature __________________</div>
    </div>
</body>
</html>
