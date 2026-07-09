@extends('layouts.admin')

@section('title', $template->name . ' — ' . config('app.name'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-0">{{ $template->name }}</h4>
        <div class="small text-secondary">{{ $template->heading }} — {{ date('F', mktime(0,0,0,$month,1)) }} {{ $year }}</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('custom-forms.templates.edit', $template) }}" class="btn btn-outline-secondary">Design</a>
        <a href="{{ route('custom-forms.index', ['month' => $month, 'year' => $year]) }}" class="btn btn-outline-dark">Back</a>
    </div>
</div>

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('custom-forms.fill.save', $template) }}">
            @csrf
            <div class="row g-2 mb-3">
                <div class="col-6 col-md-2">
                    <label class="form-label small">Month</label>
                    <input type="number" name="month" class="form-control" min="1" max="12" value="{{ $month }}" required>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small">Year</label>
                    <input type="number" name="year" class="form-control" min="2000" max="2100" value="{{ $year }}" required>
                </div>
            </div>

            <div class="table-responsive border rounded">
                <table class="table table-sm table-bordered mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:60px;">Ser</th>
                            <th>Detail</th>
                            <th style="width:180px;">Amount</th>
                            @if($template->show_remarks)
                                <th style="width:220px;">Remarks</th>
                            @endif
                            <th style="width:90px;">Flag</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach((array) $template->rows_json as $idx => $row)
                            @php
                                $type = (string) ($row['type'] ?? 'item');
                                $key = (string) ($row['key'] ?? '');
                                $serial = trim((string) ($row['serial'] ?? ''));
                                $lineValue = (array) data_get($report?->values_json ?? [], $key, []);
                            @endphp
                            <tr class="{{ $type !== 'item' ? 'table-light' : '' }}">
                                <td>{{ $serial !== '' ? $serial : ($idx + 1) }}</td>
                                <td class="{{ $type !== 'item' ? 'fw-semibold text-uppercase' : '' }}">{{ $row['label'] ?? '-' }}</td>
                                <td>
                                    @if($type === 'section')
                                        <span class="text-secondary small">-</span>
                                    @else
                                        <input type="text" name="values[{{ $key }}][amount]" class="form-control form-control-sm text-end" value="{{ $lineValue['amount'] ?? '' }}">
                                    @endif
                                </td>
                                @if($template->show_remarks)
                                    <td>
                                        @if($type === 'section')
                                            <span class="text-secondary small">-</span>
                                        @else
                                            <input type="text" name="values[{{ $key }}][remarks]" class="form-control form-control-sm" value="{{ $lineValue['remarks'] ?? '' }}">
                                        @endif
                                    </td>
                                @endif
                                <td>
                                    @if($type === 'section')
                                        <span class="text-secondary small">-</span>
                                    @else
                                        <input type="text" name="values[{{ $key }}][flag]" class="form-control form-control-sm text-center" value="{{ $lineValue['flag'] ?? '' }}">
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-3 d-flex justify-content-between">
                @if($report)
                    <a class="btn btn-outline-dark" href="{{ route('custom-forms.reports.show', $report) }}">Print View</a>
                @else
                    <span></span>
                @endif
                <button class="btn btn-primary">Save Report</button>
            </div>
        </form>
    </div>
</div>
@endsection

