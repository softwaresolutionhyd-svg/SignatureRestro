@extends('layouts.admin')
@section('title', 'Accounts — ' . config('app.name'))

@section('content')
<div class="mb-4">
    <h4 class="fw-bold mb-0">Accounts</h4>
    <div class="text-secondary small">Chart of accounts, journal entries & financial ledger</div>
</div>

@include('accounts.partials.subnav')

<div class="row g-3 mb-4">
    @php
        $ribbon = [
            ['key' => 'accounts', 'label' => 'Active Accounts', 'color' => '#6366f1'],
            ['key' => 'draft', 'label' => 'Draft Entries', 'color' => '#64748b'],
            ['key' => 'posted', 'label' => 'Posted Entries', 'color' => '#22c55e'],
        ];
    @endphp
    @foreach($ribbon as $ri)
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid {{ $ri['color'] }} !important;">
            <div class="card-body py-3">
                <div class="text-secondary small">{{ $ri['label'] }}</div>
                <div class="fw-bold fs-4 mt-1" style="color:{{ $ri['color'] }}">{{ $kpis[$ri['key']] }}</div>
            </div>
        </div>
    </div>
    @endforeach
    <div class="col-12 col-md-4">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #0ea5e9 !important;">
            <div class="card-body py-3">
                <div class="text-secondary small">Posted Volume</div>
                <div class="fw-bold fs-4 mt-1" style="color:#0ea5e9">{{ $currency }} {{ number_format($kpis['posted_total'], 2) }}</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Accounts by Type</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        @foreach($typeLabels as $type => $label)
                        <tr>
                            <td>{{ $label }}</td>
                            <td class="text-end fw-semibold">{{ $accountCounts[$type] ?? 0 }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span>Recent Journal Entries</span>
                <a href="{{ route('accounts.journal-entries.index') }}" class="small">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Number</th>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentEntries as $entry)
                        @php $st = \App\Models\JournalEntry::statusLabel()[$entry->status] ?? ['label'=>$entry->status,'color'=>'secondary']; @endphp
                        <tr>
                            <td><a href="{{ route('accounts.journal-entries.show', $entry) }}" class="text-decoration-none fw-semibold">{{ $entry->entry_number }}</a></td>
                            <td>{{ $entry->entry_date->format('d M Y') }}</td>
                            <td class="text-truncate" style="max-width:180px">{{ $entry->description ?: '—' }}</td>
                            <td><span class="badge bg-{{ $st['color'] }}">{{ $st['label'] }}</span></td>
                            <td class="text-end">{{ $currency }} {{ number_format($entry->total_debit, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-secondary py-4">No journal entries yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
