@extends('layouts.admin')
@section('title', 'Order Taker — ' . config('app.name'))
@section('page-title', 'Order Taker')

@section('content')
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0">Order Taker</h4>
        <div class="text-secondary small">Naya order POS pending bill ban jata hai aur kitchen screen par seedha dikhai deta hai.</div>
    </div>
    <a href="{{ route('order-taker.create') }}" class="btn btn-success">
        <i class="bi bi-plus-circle me-1"></i> Naya Order
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card shadow-sm border-0">
    <div class="card-header bg-white fw-semibold d-flex align-items-center justify-content-between">
        <span>POS Pending Bills</span>
        <span class="badge text-bg-warning">{{ $pendingBills->count() }}</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Order</th>
                    <th>Type</th>
                    <th>Guest</th>
                    <th>Table / Room</th>
                    <th>Waiter</th>
                    <th>Serve At</th>
                    <th>Status</th>
                    <th>Items</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($pendingBills as $o)
                @php
                    $tableRoom = [];
                    if ($o->table) {
                        $tableRoom[] = $o->table->name;
                    }
                    if ($o->room_no) {
                        $tableRoom[] = $o->room_no;
                    }
                    $isBooking = $o->customerTypeKey() === 'booking';
                    $isAstOffr = $o->customerTypeKey() === 'ast_offr';
                @endphp
                <tr>
                    <td class="small">
                        {{ $o->order_no }}
                        @if($o->isFromOrderTaker())
                            <span class="badge text-bg-info ms-1">Order Taker</span>
                            @php $orderAt = $o->ready_for_pos_at ?? $o->created_at; @endphp
                            @if($orderAt)
                                <div class="text-secondary">Order {{ $orderAt->format('H:i') }}</div>
                            @endif
                        @else
                            <span class="badge text-bg-secondary ms-1">POS</span>
                        @endif
                        @if($o->kitchen_completed_at)
                            <div class="text-success">Served {{ $o->kitchen_completed_at->format('H:i') }}</div>
                        @endif
                    </td>
                    <td>
                        @if($isAstOffr)
                            <span class="badge text-bg-info">{{ \App\Models\PosOrder::MESS_BILL_LABEL }}</span>
                        @elseif($isBooking)
                            <span class="badge text-bg-primary">In-House</span>
                        @else
                            <span class="badge text-bg-secondary">Walk-In</span>
                        @endif
                    </td>
                    <td class="small">{{ $o->guest_name ?: '—' }}</td>
                    <td class="small">{{ $tableRoom !== [] ? implode(' / ', $tableRoom) : '—' }}</td>
                    <td class="small">{{ $o->waiter_name ?: '—' }}</td>
                    <td class="small">{{ $o->serveScheduleLabel() ?? '—' }}</td>
                    <td>
                        <span class="badge {{ $o->pendingKitchenStatusBadgeClass() }}">{{ $o->pendingKitchenStatusLabel() }}</span>
                    </td>
                    <td>{{ $o->items_count }}</td>
                    <td class="text-end">{{ fmt_num($o->grand_total, 2) }}</td>
                    <td class="text-end text-nowrap">
                        @if($o->serviceTypeKey() === 'dine_in' && $o->table_id)
                            <button type="button" class="btn btn-sm btn-outline-info me-1 js-ot-move-table"
                                    data-order-id="{{ $o->id }}"
                                    data-order-no="{{ $o->order_no }}"
                                    data-current-table="{{ $o->table_id }}">
                                <i class="bi bi-arrow-left-right me-1"></i> Move Table
                            </button>
                        @endif
                        <a href="{{ route('order-taker.edit', $o) }}" class="btn btn-sm btn-primary">
                            <i class="bi bi-plus-circle me-1"></i> Add items
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-secondary text-center py-4">POS par koi pending bill nahi — naya order banayein.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Move Table Modal --}}
<div class="modal fade" id="otMoveTableModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="otMoveTableTitle">Select New Table</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="otMoveTableBody" style="max-height:60vh;overflow-y:auto;"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function() {
    const csrf = '{{ csrf_token() }}';
    const moveUrl = '{{ route("order-taker.move-table", ["order" => 999999999]) }}'.replace('999999999', '__ID__');
    const tables = @json(
        \App\Models\PosTable::query()->where('active', true)->orderBy('name')
            ->get(['id', 'name'])->map(fn ($t) => ['id' => (int) $t->id, 'name' => (string) $t->name])
    );

    const modalEl = document.getElementById('otMoveTableModal');
    if (!modalEl) return;
    const modal = new bootstrap.Modal(modalEl, { backdrop: 'static' });
    let currentOrderId = null;

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.js-ot-move-table');
        if (!btn) return;
        currentOrderId = btn.dataset.orderId;
        const currentTable = Number(btn.dataset.currentTable);
        const orderNo = btn.dataset.orderNo || '';

        document.getElementById('otMoveTableTitle').textContent = 'Select New Table — ' + orderNo;

        const body = document.getElementById('otMoveTableBody');
        const free = tables.filter(t => t.id !== currentTable);
        if (!free.length) {
            body.innerHTML = '<div class="text-center text-secondary py-4">Koi free table nahi.</div>';
        } else {
            body.innerHTML = '<div class="d-flex flex-wrap gap-2 justify-content-center">' +
                free.map(t => '<button type="button" class="btn btn-outline-success px-3 py-3 js-ot-pick-table" data-table-id="' + t.id + '" style="min-width:80px;"><div class="fw-bold" style="font-size:1.1rem;">' + t.name + '</div></button>').join('') +
            '</div>';
        }
        modal.show();
    });

    modalEl.querySelector('#otMoveTableBody').addEventListener('click', async function(e) {
        const btn = e.target.closest('.js-ot-pick-table');
        if (!btn || btn.disabled || !currentOrderId) return;
        const tableId = Number(btn.dataset.tableId);
        modalEl.querySelectorAll('.js-ot-pick-table').forEach(b => { b.disabled = true; });

        try {
            const url = moveUrl.replace('__ID__', String(currentOrderId));
            const res = await fetch(url, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' },
                body: JSON.stringify({ table_id: tableId }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.ok) {
                alert(data.message || 'Table move fail.');
                modalEl.querySelectorAll('.js-ot-pick-table').forEach(b => { b.disabled = false; });
                return;
            }
            modal.hide();
            location.reload();
        } catch (err) {
            alert('Table move request fail.');
            modalEl.querySelectorAll('.js-ot-pick-table').forEach(b => { b.disabled = false; });
        }
    });
})();
</script>
@endsection
