@extends('layouts.admin')

@section('title', $stockCheck->number . ' — Stock check — ' . config('app.name'))

@section('content')
    @include('inventory.partials.subnav')

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->has('approve'))
        <div class="alert alert-danger">{{ $errors->first('approve') }}</div>
    @endif

    @php
        use App\Models\User;
        $creator = $stockCheck->created_by ? User::find($stockCheck->created_by) : null;
        $reviewer = $stockCheck->reviewed_by ? User::find($stockCheck->reviewed_by) : null;
    @endphp

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <span class="fw-bold">{{ $stockCheck->number }}</span>
                @php
                    $badgeClass = match ($stockCheck->status) {
                        'draft' => 'text-bg-secondary',
                        'pending_approval' => 'text-bg-warning',
                        'approved' => 'text-bg-success',
                        'rejected' => 'text-bg-danger',
                        default => 'text-bg-secondary',
                    };
                @endphp
                <span class="badge {{ $badgeClass }} ms-2">{{ str_replace('_', ' ', strtoupper($stockCheck->status)) }}</span>
            </div>
            <div class="d-flex flex-wrap gap-1">
                <a href="{{ route('inventory.stock-check.index') }}" class="btn btn-sm btn-outline-secondary">List</a>
                @if ($stockCheck->isDraft())
                    <a href="{{ route('inventory.stock-check.edit', $stockCheck) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                    <form method="POST" action="{{ route('inventory.stock-check.destroy', $stockCheck) }}" class="d-inline"
                          onsubmit="return confirm('Delete this draft?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                    <form method="POST" action="{{ route('inventory.stock-check.submit', $stockCheck) }}" class="d-inline"
                          onsubmit="return confirm('Submit for admin approval? Har line par counted qty zaroori hai.');">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-primary">Submit for approval</button>
                    </form>
                @endif
            </div>
        </div>
        <div class="card-body">
            <div class="row g-2 small">
                <div class="col-md-4"><span class="text-secondary">Title</span><div>{{ $stockCheck->title ?: '—' }}</div></div>
                <div class="col-md-4"><span class="text-secondary">Created by</span><div>{{ $creator?->name ?? '—' }}</div></div>
                <div class="col-md-4"><span class="text-secondary">Submitted</span><div>{{ $stockCheck->submitted_at?->format('Y-m-d H:i') ?? '—' }}</div></div>
                @if ($reviewer)
                    <div class="col-md-4"><span class="text-secondary">Reviewed by</span><div>{{ $reviewer->name }}</div></div>
                    <div class="col-md-4"><span class="text-secondary">Reviewed at</span><div>{{ $stockCheck->reviewed_at?->format('Y-m-d H:i') }}</div></div>
                @endif
            </div>
            @if ($stockCheck->status === 'rejected' && $stockCheck->reject_reason)
                <div class="alert alert-danger mt-3 mb-0 py-2 small"><strong>Reason:</strong> {{ $stockCheck->reject_reason }}</div>
            @endif
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">Lines (base UOM)</div>
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Product</th>
                    <th class="text-end">Expected (at submit)</th>
                    <th class="text-end">Counted</th>
                    <th class="text-end">Variance</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($stockCheck->lines as $l)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $l->product->name }}</div>
                            <div class="text-secondary small">{{ $l->product->sku }} · {{ $l->product->uom }}</div>
                        </td>
                        <td class="text-end">{{ fmt_num((float) $l->expected_qty, 6) }}</td>
                        <td class="text-end">{{ $l->counted_qty !== null ? fmt_num((float) $l->counted_qty, 6) : '—' }}</td>
                        <td class="text-end fw-semibold">
                            @if ($l->counted_qty !== null)
                                @php $v = (float) $l->counted_qty - (float) $l->expected_qty; @endphp
                                <span class="{{ $v > 0 ? 'text-success' : ($v < 0 ? 'text-danger' : 'text-secondary') }}">
                                    {{ $v >= 0 ? '+' : '' }}{{ fmt_num($v, 6) }}
                                </span>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($stockCheck->isPendingApproval() && (auth()->user()->isPlatformSuperAdmin() || auth()->user()->isCompanyAdmin() || (auth()->user()->role ?? '') === 'admin'))
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold">Admin</div>
            <div class="card-body d-flex flex-wrap gap-2 align-items-start">
                <form method="POST" action="{{ route('inventory.stock-check.approve', $stockCheck) }}" class="d-inline"
                      onsubmit="return confirm('Approve & apply counted qty to stock (FIFO)?');">
                    @csrf
                    <button type="submit" class="btn btn-success">Approve &amp; update stock</button>
                </form>
                <form method="POST" action="{{ route('inventory.stock-check.reject', $stockCheck) }}" class="flex-grow-1" style="min-width: 240px;">
                    @csrf
                    <div class="input-group">
                        <input type="text" name="reject_reason" class="form-control" placeholder="Reject reason" required maxlength="2000" value="{{ old('reject_reason') }}">
                        <button type="submit" class="btn btn-outline-danger">Reject</button>
                    </div>
                    @error('reject_reason')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </form>
            </div>
        </div>
    @endif
@endsection
