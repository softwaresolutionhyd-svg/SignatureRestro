@extends('layouts.admin')
@section('title', $entry->entry_number . ' — ' . config('app.name'))

@section('content')
@php $st = $statusMap[$entry->status] ?? ['label'=>$entry->status,'color'=>'secondary']; @endphp

<div class="mb-4 d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-0">{{ $entry->entry_number }}</h4>
        <div class="text-secondary small">{{ $entry->entry_date->format('d M Y') }} · <span class="badge bg-{{ $st['color'] }}">{{ $st['label'] }}</span></div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @if($entry->isDraft())
            <a href="{{ route('accounts.journal-entries.edit', $entry) }}" class="btn btn-outline-primary btn-sm">Edit</a>
            <form method="POST" action="{{ route('accounts.journal-entries.post', $entry) }}" class="d-inline">
                @csrf
                <button class="btn btn-success btn-sm" onclick="return confirm('Post this entry to the ledger?')">Post Entry</button>
            </form>
            <form method="POST" action="{{ route('accounts.journal-entries.destroy', $entry) }}" class="d-inline" onsubmit="return confirm('Delete this draft entry?')">
                @csrf @method('DELETE')
                <button class="btn btn-outline-danger btn-sm">Delete</button>
            </form>
        @endif
        <a href="{{ route('accounts.journal-entries.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>
</div>

@include('accounts.partials.subnav')

@if($entry->source !== 'manual')
<div class="alert alert-info py-2 small mb-4">
    Auto-posted from <strong>{{ strtoupper($entry->source) }}</strong>
    @if($entry->reference) · Ref: {{ $entry->reference }} @endif
</div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-secondary small">Reference</div>
                <div class="fw-semibold">{{ $entry->reference ?: '—' }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-secondary small">Description</div>
                <div>{{ $entry->description ?: '—' }}</div>
            </div>
        </div>
    </div>
</div>

@if($entry->isPosted())
<div class="alert alert-success py-2 small mb-4">
    Posted on {{ $entry->posted_at?->format('d M Y H:i') }}
    @if($entry->postedByUser) by {{ $entry->postedByUser->name }} @endif
</div>
@endif

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Account</th>
                    <th>Description</th>
                    <th class="text-end">Debit</th>
                    <th class="text-end">Credit</th>
                </tr>
            </thead>
            <tbody>
                @foreach($entry->lines as $line)
                <tr>
                    <td>
                        <span class="fw-semibold">{{ $line->account->code }}</span>
                        — {{ $line->account->name }}
                    </td>
                    <td>{{ $line->description ?: '—' }}</td>
                    <td class="text-end">{{ $line->debit > 0 ? $currency.' '.number_format($line->debit, 2) : '—' }}</td>
                    <td class="text-end">{{ $line->credit > 0 ? $currency.' '.number_format($line->credit, 2) : '—' }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="2" class="text-end">Totals</th>
                    <th class="text-end">{{ $currency }} {{ number_format($entry->total_debit, 2) }}</th>
                    <th class="text-end">{{ $currency }} {{ number_format($entry->total_credit, 2) }}</th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
