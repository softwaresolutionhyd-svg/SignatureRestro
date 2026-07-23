@extends('layouts.admin')
@section('title', 'Trial Balance — ' . config('app.name'))

@section('content')
<div class="mb-4">
    <h4 class="fw-bold mb-0">Trial Balance</h4>
    <div class="text-secondary small">Posted journal balances as of selected date</div>
</div>

@include('accounts.partials.subnav')

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">As of date</label>
                <input type="date" name="as_of" class="form-control form-control-sm" value="{{ $asOf }}">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary btn-sm w-100">Update</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Code</th>
                    <th>Account</th>
                    <th>Type</th>
                    <th class="text-end">Debit</th>
                    <th class="text-end">Credit</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                @php $acc = $row['account']; @endphp
                <tr>
                    <td class="fw-semibold">
                        <a href="{{ route('accounts.journal-entries.index', ['account_id' => $acc->id, 'status' => 'posted']) }}" class="text-decoration-none">
                            {{ $acc->code }}
                        </a>
                    </td>
                    <td>
                        <a href="{{ route('accounts.journal-entries.index', ['account_id' => $acc->id, 'status' => 'posted']) }}" class="text-decoration-none text-dark">
                            {{ $acc->name }}
                        </a>
                    </td>
                    <td>{{ $typeLabels[$acc->type] ?? $acc->type }}</td>
                    <td class="text-end">{{ $row['debit'] > 0 ? $currency.' '.number_format($row['debit'], 2) : '—' }}</td>
                    <td class="text-end">{{ $row['credit'] > 0 ? $currency.' '.number_format($row['credit'], 2) : '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center text-secondary py-4">No posted balances for this date.</td></tr>
                @endforelse
            </tbody>
            @if($rows->isNotEmpty())
            <tfoot class="table-light">
                <tr>
                    <th colspan="3" class="text-end">Totals</th>
                    <th class="text-end">{{ $currency }} {{ number_format($totalDebit, 2) }}</th>
                    <th class="text-end">{{ $currency }} {{ number_format($totalCredit, 2) }}</th>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
