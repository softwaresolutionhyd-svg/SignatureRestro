@extends('layouts.admin')
@section('title', 'Void Items — ' . config('app.name'))

@section('content')
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-0">Void Items</h4>
        <div class="text-secondary small">
            {{ \Carbon\Carbon::parse($from)->format('d M Y') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }}
            · {{ $voidRows->count() }} records
        </div>
    </div>
    <a href="{{ route('reports.sales', ['from' => $from, 'to' => $to]) }}" class="btn btn-outline-secondary btn-sm">← Sales Report</a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span>Void Items / Cancelled bills</span>
        <span class="badge bg-danger">{{ $voidRows->count() }}</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Type</th>
                        <th>Bill #</th>
                        <th>Detail</th>
                        <th>Reason</th>
                        <th>Cancelled By</th>
                        <th>Cashier</th>
                        <th>Time</th>
                        <th class="text-center no-print" style="width:96px;"></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($voidRows as $void)
                    <tr>
                        <td class="small">
                            @if(($void['kind'] ?? '') === 'bill')
                                <span class="badge text-bg-danger">Bill Cancelled</span>
                            @else
                                <span class="badge text-bg-warning text-dark">Item Void</span>
                            @endif
                        </td>
                        <td class="small fw-semibold">{{ $void['order_no'] }}</td>
                        <td class="small">{{ $void['detail'] }}</td>
                        <td class="small text-danger">{{ $void['reason'] }}</td>
                        <td class="small">{{ $void['cancelled_by'] }}</td>
                        <td class="small">{{ $void['cashier'] }}</td>
                        <td class="small text-secondary">{{ $void['cancelled_at'] }}</td>
                        <td class="text-center no-print">
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('reports.sales.voids.show', ['activityLog' => $void['id'], 'from' => $from, 'to' => $to]) }}"
                                   class="btn btn-sm btn-outline-primary px-2 py-0"
                                   title="View void detail">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary px-2 py-0 js-void-cashier-print"
                                        data-print-url="{{ route('reports.sales.voids.cashier-print', $void['id']) }}"
                                        title="Print on cashier printer">
                                    <i class="bi bi-printer"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-secondary py-3">
                            Is period mein koi void item / cancelled bill nahi.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    document.querySelectorAll('.js-void-cashier-print').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const url = btn.getAttribute('data-print-url');
            if (!url) return;
            btn.disabled = true;
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                    },
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || data.ok === false) {
                    alert(data.message || 'Print fail — cashier printer check karein.');
                    return;
                }
                alert(data.message || 'Print bhej diya.');
            } catch (e) {
                alert(e.message || 'Print fail.');
            } finally {
                btn.disabled = false;
            }
        });
    });
})();
</script>
@endsection
