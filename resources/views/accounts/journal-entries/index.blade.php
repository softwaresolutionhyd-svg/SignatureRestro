@extends('layouts.admin')
@section('title', 'Journal Entries — ' . config('app.name'))

@section('content')
<div class="mb-4">
    <h4 class="fw-bold mb-0">Journal Entries</h4>
    <div class="text-secondary small">Manual double-entry bookkeeping</div>
</div>

@include('accounts.partials.subnav')

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach($statusMap as $val => $info)
                        <option value="{{ $val }}" @selected(request('status') === $val)>{{ $info['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="{{ request('from') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="{{ request('to') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">Search</label>
                <input type="text" name="search" class="form-control form-control-sm" value="{{ request('search') }}" placeholder="Number, reference, description">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary btn-sm w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Number</th>
                    <th>Date</th>
                    <th>Reference</th>
                    <th>Description</th>
                    <th>Source</th>
                    <th>Status</th>
                    <th class="text-end">Debit</th>
                    <th class="text-end">Credit</th>
                </tr>
            </thead>
            <tbody>
                @forelse($entries as $entry)
                @php $st = $statusMap[$entry->status] ?? ['label'=>$entry->status,'color'=>'secondary']; @endphp
                <tr style="cursor:pointer" onclick="window.location='{{ route('accounts.journal-entries.show', $entry) }}'">
                    <td class="fw-semibold">{{ $entry->entry_number }}</td>
                    <td>{{ $entry->entry_date->format('d M Y') }}</td>
                    <td>{{ $entry->reference ?: '—' }}</td>
                    <td class="text-truncate" style="max-width:200px">{{ $entry->description ?: '—' }}</td>
                    <td><span class="badge bg-light text-dark border text-uppercase">{{ $entry->source }}</span></td>
                    <td><span class="badge bg-{{ $st['color'] }}">{{ $st['label'] }}</span></td>
                    <td class="text-end">{{ $currency }} {{ number_format($entry->total_debit, 2) }}</td>
                    <td class="text-end">{{ $currency }} {{ number_format($entry->total_credit, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-secondary py-4">No journal entries found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($entries->hasPages())
    <div class="card-footer bg-white">{{ $entries->links() }}</div>
    @endif
</div>
@endsection
