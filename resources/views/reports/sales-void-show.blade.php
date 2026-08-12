@extends('layouts.admin')
@section('title', 'Void detail — ' . config('app.name'))

@section('content')
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-0">
            @if(($row['kind'] ?? '') === 'bill')
                Cancelled bill
            @else
                Void item
            @endif
        </h4>
        <div class="text-secondary small">Bill #{{ $row['order_no'] }} — {{ $row['cancelled_at'] }}</div>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <button type="button" class="btn btn-primary btn-sm" id="voidCashierPrintBtn">
            <i class="bi bi-printer me-1"></i> Print (Cashier)
        </button>
        <a href="{{ ($from && $to) ? route('reports.sales.voids', ['from' => $from, 'to' => $to]) : route('reports.sales.voids') }}" class="btn btn-outline-secondary btn-sm">← Void Items</a>
    </div>
</div>
<p id="voidPrintStatus" class="small mb-3" style="display:none;"></p>

<div class="row g-3">
    <div class="col-md-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-semibold">
                @if(($row['kind'] ?? '') === 'bill') Cancelled items @else Voided item @endif
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th class="text-center">Qty</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($row['items'] as $item)
                        <tr>
                            <td class="small text-secondary">{{ $loop->iteration }}</td>
                            <td class="small fw-semibold">{{ $item['name'] }}</td>
                            <td class="text-center small">
                                {{ fmt_num((float) $item['qty'], 3) }}
                                @if(!empty($item['uom'])) {{ $item['uom'] }} @endif
                            </td>
                            <td class="small text-danger">{{ $item['reason'] ?: $row['reason'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-secondary py-4">{{ $row['detail'] }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-semibold">Cancel info</div>
            <div class="card-body small">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Type</span>
                    <span class="fw-semibold">{{ $row['kind_label'] }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Bill #</span>
                    <span class="fw-semibold">{{ $row['order_no'] }}</span>
                </div>
                @if(!empty($row['order_type']))
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Order type</span>
                    <span>{{ $row['order_type'] }}</span>
                </div>
                @endif
                @if(!empty($row['table']))
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Table</span>
                    <span>{{ $row['table'] }}</span>
                </div>
                @endif
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Cashier</span>
                    <span>{{ $row['cashier'] }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Cancelled by</span>
                    <span class="fw-semibold">{{ $row['cancelled_by'] }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Time</span>
                    <span>{{ $row['cancelled_at'] }}</span>
                </div>
                <hr>
                <div class="mb-1 text-secondary">Reason</div>
                <div class="fw-semibold text-danger">{{ $row['reason'] }}</div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(() => {
    const btn = document.getElementById('voidCashierPrintBtn');
    const status = document.getElementById('voidPrintStatus');
    const printUrl = @json(route('reports.sales.voids.cashier-print', $log));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function setStatus(msg, ok) {
        if (!status) return;
        status.style.display = 'block';
        status.className = 'small mb-3 ' + (ok ? 'text-success' : 'text-danger');
        status.textContent = msg;
    }

    btn?.addEventListener('click', async () => {
        btn.disabled = true;
        setStatus('Printing…', true);
        try {
            const res = await fetch(printUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                },
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data.ok === false) {
                setStatus(data.message || 'Print fail — cashier printer check karein.', false);
                return;
            }
            setStatus(data.message || 'Print bhej diya.', true);
        } catch (e) {
            setStatus(e.message || 'Print fail.', false);
        } finally {
            btn.disabled = false;
        }
    });
})();
</script>
@endsection
