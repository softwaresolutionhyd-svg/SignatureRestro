@extends('layouts.admin')

@section('title', 'Print Report — ' . config('app.name'))

@section('content')
@php
    $template = $report->template;
    $rows = (array) ($template?->rows_json ?? []);
    $values = (array) ($report->values_json ?? []);
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">{{ $template?->name ?? 'Report' }}</h4>
        <div class="small text-secondary">{{ $template?->heading }} — {{ date('M', mktime(0,0,0,$report->month,1)) }} {{ $report->year }}</div>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-dark" onclick="window.print()">Print</button>
        <a class="btn btn-outline-secondary" href="{{ route('custom-forms.fill', ['template' => $template, 'month' => $report->month, 'year' => $report->year]) }}">Edit</a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="fw-bold text-uppercase text-center mb-3">{{ $template?->heading }} — {{ date('M', mktime(0,0,0,$report->month,1)) }} {{ $report->year }}</div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle">
                <thead>
                    <tr>
                        <th style="width:60px;">Ser</th>
                        <th>Detail</th>
                        <th style="width:180px;">Amount</th>
                        @if($template?->show_remarks)
                            <th style="width:220px;">Remarks</th>
                        @endif
                        <th style="width:90px;">Flag</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $idx => $row)
                        @php
                            $type = (string) ($row['type'] ?? 'item');
                            $key = (string) ($row['key'] ?? '');
                            $serial = trim((string) ($row['serial'] ?? ''));
                            $lineValue = (array) data_get($values, $key, []);
                        @endphp
                        <tr class="{{ $type !== 'item' ? 'table-light' : '' }}">
                            <td>{{ $serial !== '' ? $serial : ($idx + 1) }}</td>
                            <td class="{{ $type !== 'item' ? 'fw-semibold text-uppercase' : '' }}">{{ $row['label'] ?? '-' }}</td>
                            <td class="text-end">{{ $type === 'section' ? '-' : ($lineValue['amount'] ?? '') }}</td>
                            @if($template?->show_remarks)
                                <td>{{ $type === 'section' ? '-' : ($lineValue['remarks'] ?? '') }}</td>
                            @endif
                            <td class="text-center">{{ $type === 'section' ? '-' : ($lineValue['flag'] ?? '') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

