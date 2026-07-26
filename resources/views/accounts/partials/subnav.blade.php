<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('accounts.index') }}" class="btn btn-outline-primary {{ request()->routeIs('accounts.index') ? 'active' : '' }}">
            <i class="bi bi-grid me-1"></i> Overview
        </a>
        <a href="{{ route('accounts.chart-of-accounts.index') }}" class="btn btn-outline-primary {{ request()->routeIs('accounts.chart-of-accounts.*') ? 'active' : '' }}">
            <i class="bi bi-list-columns me-1"></i> Chart of Accounts
        </a>
        <a href="{{ route('accounts.journal-entries.index') }}" class="btn btn-outline-primary {{ request()->routeIs('accounts.journal-entries.*') ? 'active' : '' }}">
            <i class="bi bi-journal-text me-1"></i> Journal Entries
        </a>
        <a href="{{ route('accounts.reports.trial-balance', array_filter([
                'from' => request('from'),
                'to' => request('to'),
            ])) }}"
           class="btn btn-outline-primary {{ request()->routeIs('accounts.reports.*') ? 'active' : '' }}">
            <i class="bi bi-bar-chart me-1"></i> Trial Balance
        </a>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @if(request()->routeIs('accounts.chart-of-accounts.*'))
            <a href="{{ route('accounts.chart-of-accounts.create') }}" class="btn btn-success btn-sm">
                <i class="bi bi-plus-circle me-1"></i> New Account
            </a>
        @elseif(request()->routeIs('accounts.reports.*'))
            <a href="{{ route('accounts.journal-entries.index', array_filter([
                    'status' => 'posted',
                    'from' => request('from'),
                    'to' => request('to'),
                ])) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-journal-text me-1"></i> Journals
            </a>
            <a href="{{ route('accounts.journal-entries.create') }}" class="btn btn-success btn-sm">
                <i class="bi bi-plus-circle me-1"></i> New Entry
            </a>
        @else
            <a href="{{ route('accounts.journal-entries.create') }}" class="btn btn-success btn-sm">
                <i class="bi bi-plus-circle me-1"></i> New Entry
            </a>
        @endif
    </div>
</div>
