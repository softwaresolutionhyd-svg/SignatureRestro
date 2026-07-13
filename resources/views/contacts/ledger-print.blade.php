<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Ledger — {{ $contact->name }}</title>
    <style>
        * { box-sizing: border-box; }

        @page { size: A4 portrait; margin: 16mm 14mm; }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            color: #000;
            background: #fff;
        }

        .page {
            width: 210mm;
            margin: 0 auto;
            padding: 16mm 14mm;
        }

        .noprint {
            text-align: center;
            padding: 10px;
            border-bottom: 1px solid #000;
        }

        .noprint button,
        .noprint a {
            margin: 0 6px;
            padding: 6px 12px;
            font-size: 12px;
            border: 1px solid #000;
            background: #fff;
            color: #000;
            cursor: pointer;
            text-decoration: none;
        }

        h1 { margin: 0 0 2px; font-size: 16pt; font-weight: bold; text-align: center; }
        h2 { margin: 0 0 14px; font-size: 11pt; font-weight: normal; text-align: center; }

        .meta { margin-bottom: 14px; font-size: 10pt; line-height: 1.6; }
        .meta p { margin: 0; }

        table { width: 100%; border-collapse: collapse; font-size: 10pt; }
        th, td { border: 1px solid #000; padding: 5px 7px; }
        th { font-weight: bold; text-align: left; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        tr.bold td { font-weight: bold; }

        .summary { margin-top: 16px; width: 60%; margin-left: auto; }

        .footer { margin-top: 20px; font-size: 9pt; text-align: center; }

        @media print {
            .noprint { display: none; }
            .page { width: auto; padding: 0; }
        }
    </style>
</head>
<body>

@php
    $printedAt = now()->format('d M Y, h:i A');
    $running = 0.0;
@endphp

<div class="noprint">
    <button type="button" onclick="window.print()">Print / PDF</button>
    <a href="{{ route('contacts.show', $contact) }}">Back</a>
</div>

<div class="page">
    <h1>{{ $companyName }}</h1>
    <h2>Credit Ledger Report</h2>

    <div class="meta">
        <p>Name: <strong>{{ $contact->name }}</strong>@if($contact->phone) &nbsp;|&nbsp; Phone: {{ $contact->phone }}@endif</p>
        <p>Category: {{ $contact->categoryLabel() }}@if($contact->city) &nbsp;|&nbsp; City: {{ $contact->city }}@endif</p>
        <p>Printed: {{ $printedAt }} &nbsp;|&nbsp; Entries: {{ $ledger->count() }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:14%;">Date</th>
                <th>Description</th>
                <th style="width:12%;">Type</th>
                <th class="num" style="width:16%;">Credit</th>
                <th class="num" style="width:16%;">Payment</th>
                <th class="num" style="width:16%;">Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse($ledger as $entry)
                @php
                    $isCredit = $entry->type === 'credit';
                    $running += $isCredit ? (float) $entry->amount : -(float) $entry->amount;
                @endphp
                <tr>
                    <td>{{ $entry->entry_date->format('d M Y') }}</td>
                    <td>{{ $entry->description }}</td>
                    <td>{{ $isCredit ? 'Credit' : 'Payment' }}</td>
                    <td class="num">{{ $isCredit ? $currency.' '.fmt_num((float) $entry->amount, 2) : '—' }}</td>
                    <td class="num">{{ ! $isCredit ? $currency.' '.fmt_num((float) $entry->amount, 2) : '—' }}</td>
                    <td class="num">{{ $currency }} {{ fmt_num($running, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;">No ledger entries.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="summary">
        <tr>
            <th>Total Credit</th>
            <td class="num">{{ $currency }} {{ fmt_num($totalCredit, 2) }}</td>
        </tr>
        <tr>
            <th>Total Paid</th>
            <td class="num">{{ $currency }} {{ fmt_num($totalPaid, 2) }}</td>
        </tr>
        <tr class="bold">
            <th>Balance Outstanding</th>
            <td class="num">{{ $currency }} {{ fmt_num($balance, 2) }}</td>
        </tr>
    </table>

    <div class="footer">
        Computer generated ledger report — {{ $companyName }}
    </div>
</div>

@if(request()->boolean('auto'))
<script>
    window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });
</script>
@endif
</body>
</html>
