@extends('layouts.admin')

@section('title', 'Deals — Inventory — ' . config('app.name'))

@section('content')
    @include('inventory.partials.subnav')

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <div class="fw-semibold">Menu deals</div>
                <div class="small text-secondary">Mukhtalif menu items se combo banao — limited time ya permanent.</div>
            </div>
            <a href="{{ route('inventory.deals.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> New deal
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Deal</th>
                    <th>Items</th>
                    <th class="text-end">Price</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($deals as $deal)
                    @php $onSale = $deal->isOnSale(); @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $deal->name }}</div>
                            <div class="small text-secondary">{{ $deal->sku ?: $deal->product?->sku }}</div>
                        </td>
                        <td class="small">
                            @forelse($deal->items as $line)
                                <div>{{ fmt_num((float) $line->qty, 3) }} × {{ $line->product?->name ?? '—' }}</div>
                            @empty
                                <span class="text-secondary">—</span>
                            @endforelse
                        </td>
                        <td class="text-end fw-semibold">{{ fmt_num((float) $deal->price, 2) }}</td>
                        <td class="small">{{ $deal->durationLabel() }}</td>
                        <td>
                            @if($onSale)
                                <span class="badge text-bg-success">On POS</span>
                            @elseif(! $deal->active)
                                <span class="badge text-bg-secondary">Off</span>
                            @else
                                <span class="badge text-bg-warning">Not live</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('inventory.deals.edit', $deal) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            <form action="{{ route('inventory.deals.destroy', $deal) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('Deal delete karein? POS se bhi hide ho jayegi.');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-secondary py-4">Abhi koi deal nahi. New deal se banao.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($deals->hasPages())
            <div class="card-footer bg-white">
                {{ $deals->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
@endsection
