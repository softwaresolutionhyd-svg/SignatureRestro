@extends('layouts.admin')
@section('title', __('Accounts') . ' — ' . config('app.name'))

@section('content')
<div class="mb-4">
    <h4 class="fw-bold mb-0">{{ __('Accounts') }}</h4>
    <div class="text-secondary small">{{ __('Chart of accounts, journal entries & financial ledger') }}</div>
</div>

@include('accounts.partials.subnav')

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="{{ route('accounts.chart-of-accounts.index') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #6366f1 !important;">
                <div class="card-body py-3">
                    <div class="text-secondary small">{{ __('Active Accounts') }}</div>
                    <div class="fw-bold fs-4 mt-1" style="color:#6366f1">{{ $kpis['accounts'] }}</div>
                    <div class="text-secondary" style="font-size:11px;">{{ __('Open chart of accounts') }} →</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="{{ route('accounts.journal-entries.index', ['status' => 'draft']) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #64748b !important;">
                <div class="card-body py-3">
                    <div class="text-secondary small">{{ __('Draft Entries') }}</div>
                    <div class="fw-bold fs-4 mt-1" style="color:#64748b">{{ $kpis['draft'] }}</div>
                    <div class="text-secondary" style="font-size:11px;">{{ __('View draft journals') }} →</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="{{ route('accounts.journal-entries.index', ['status' => 'posted']) }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #22c55e !important;">
                <div class="card-body py-3">
                    <div class="text-secondary small">{{ __('Posted Entries') }}</div>
                    <div class="fw-bold fs-4 mt-1" style="color:#22c55e">{{ $kpis['posted'] }}</div>
                    <div class="text-secondary" style="font-size:11px;">{{ __('View posted journals') }} →</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="{{ route('accounts.reports.trial-balance') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #0ea5e9 !important;">
                <div class="card-body py-3">
                    <div class="text-secondary small">{{ __('Posted Volume') }}</div>
                    <div class="fw-bold fs-4 mt-1" style="color:#0ea5e9">{{ $currency }} {{ number_format($kpis['posted_total'], 2) }}</div>
                    <div class="text-secondary" style="font-size:11px;">{{ __('Open trial balance') }} →</div>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                <span>{{ __('Accounts by Type') }}</span>
                <a href="{{ route('accounts.chart-of-accounts.index') }}" class="small">{{ __('All accounts') }}</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        @foreach($typeLabels as $type => $label)
                        <tr>
                            <td>
                                <a href="{{ route('accounts.chart-of-accounts.index', ['type' => $type]) }}" class="text-decoration-none text-dark">
                                    {{ $label }}
                                </a>
                            </td>
                            <td class="text-end fw-semibold">
                                <a href="{{ route('accounts.chart-of-accounts.index', ['type' => $type]) }}" class="text-decoration-none text-dark">
                                    {{ $accountCounts[$type] ?? 0 }}
                                </a>
                            </td>
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
                <span>{{ __('Recent Journal Entries') }}</span>
                <a href="{{ route('accounts.journal-entries.index') }}" class="small">{{ __('View all') }}</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Number') }}</th>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Description') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-end">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentEntries as $entry)
                        @php $st = \App\Models\JournalEntry::statusLabel()[$entry->status] ?? ['label'=>$entry->status,'color'=>'secondary']; @endphp
                        <tr style="cursor:pointer" onclick="window.location='{{ route('accounts.journal-entries.show', $entry) }}'">
                            <td><a href="{{ route('accounts.journal-entries.show', $entry) }}" class="text-decoration-none fw-semibold" onclick="event.stopPropagation()">{{ $entry->entry_number }}</a></td>
                            <td>{{ $entry->entry_date->format('d M Y') }}</td>
                            <td class="text-truncate" style="max-width:180px">{{ $entry->description ?: '—' }}</td>
                            <td><span class="badge bg-{{ $st['color'] }}">{{ $st['label'] }}</span></td>
                            <td class="text-end">{{ $currency }} {{ number_format($entry->total_debit, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-secondary py-4">{{ __('No journal entries yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
