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
<style>
.rp-mt-area-tabs-inner{display:flex;flex-wrap:wrap;gap:.35rem;padding:.6rem .5rem .5rem}
.rp-mt-area-tab{padding:.28rem .85rem;border-radius:999px;border:1.5px solid #d1d5db;background:#f9fafb;color:#374151;font-size:.78rem;font-weight:600;cursor:pointer;transition:background .15s,border-color .15s,color .15s;line-height:1.4}
.rp-mt-area-tab:hover{background:#e5e7eb}
.rp-mt-area-tab.is-active{background:#1d4ed8;border-color:#1d4ed8;color:#fff}
.rp-mt-area-title{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;padding:.6rem 0 .3rem}
.rp-mt-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(6.2rem,1fr));gap:.85rem .7rem;padding:.5rem}
.rp-mt-table-btn{display:flex;flex-direction:column;align-items:center;gap:.3rem;padding:.3rem .2rem .45rem;border:none;border-radius:0;background:transparent;cursor:pointer;transition:transform .15s;font-family:inherit}
.rp-mt-table-btn:hover:not(:disabled){transform:translateY(-2px)}
.rp-mt-table-btn:disabled{opacity:.7;cursor:not-allowed}
.rp-mt-shape{position:relative;width:5rem;height:5rem;flex-shrink:0}
.rp-mt-top{position:absolute;inset:18% 18%;border-radius:22%;display:flex;align-items:center;justify-content:center;border:2px solid transparent;background:#fff;z-index:2;transition:border-color .15s,box-shadow .15s}
.rp-mt-chair{position:absolute;border-radius:.25rem;z-index:1;transition:background .15s}
.rp-mt-chair--n,.rp-mt-chair--s{width:38%;height:14%;left:31%}
.rp-mt-chair--e,.rp-mt-chair--w{width:14%;height:38%;top:31%}
.rp-mt-chair--n{top:2%}.rp-mt-chair--s{bottom:2%}.rp-mt-chair--e{right:2%}.rp-mt-chair--w{left:2%}
.rp-mt-name{font-size:.92rem;font-weight:800;line-height:1.1;color:#1c1917}
.rp-mt-label{font-size:.66rem;line-height:1.15;text-align:center}
.rp-mt-table-btn--free .rp-mt-top{border-color:rgba(61,214,140,.65);background:linear-gradient(160deg,rgba(34,160,107,.15) 0%,#fff 65%);box-shadow:0 0 12px rgba(34,160,107,.18)}
.rp-mt-table-btn--free .rp-mt-chair{background:#3dd68c;box-shadow:0 0 6px rgba(61,214,140,.3)}
.rp-mt-table-btn--free:hover .rp-mt-top{border-color:rgba(61,214,140,.95);box-shadow:0 0 18px rgba(34,160,107,.3)}
.rp-mt-table-btn--free .rp-mt-label{color:#16a34a;font-weight:600}
.rp-mt-table-btn--occupied .rp-mt-top{border-color:rgba(255,107,107,.65);background:linear-gradient(160deg,rgba(201,42,42,.15) 0%,#fff 65%);box-shadow:0 0 12px rgba(201,42,42,.18)}
.rp-mt-table-btn--occupied .rp-mt-chair{background:#ff6b6b;box-shadow:0 0 6px rgba(255,107,107,.3)}
.rp-mt-table-btn--occupied .rp-mt-label{color:#dc2626;font-weight:600}
</style>
<div class="modal fade" id="otMoveTableModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="otMoveTableTitle">Select New Table</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div id="otMoveTableAreaTabs"></div>
            <div class="modal-body" id="otMoveTableBody" style="max-height:55vh;overflow-y:auto;"></div>
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
    // Full tableBoard with sitting_area info
    const tableBoard = @json(
        \App\Services\OrderTakerService::class === \App\Services\OrderTakerService::class
            ? app(\App\Services\OrderTakerService::class)->tableBoard()
            : []
    );

    const modalEl = document.getElementById('otMoveTableModal');
    if (!modalEl) return;
    const modal = new bootstrap.Modal(modalEl, { backdrop: 'static' });
    const tabsEl = document.getElementById('otMoveTableAreaTabs');
    const body = document.getElementById('otMoveTableBody');
    let currentOrderId = null;

    function escH(s) { const d = document.createElement('div'); d.textContent = String(s); return d.innerHTML; }

    function tableIcon(t, isCurrent) {
        const isFree = !isCurrent && t.status === 'free';
        const cls = isCurrent ? 'rp-mt-table-btn--occupied' : (isFree ? 'rp-mt-table-btn--free' : 'rp-mt-table-btn--occupied');
        const pick = isFree ? ' js-ot-pick-table' : '';
        const dis = (!isFree || isCurrent) ? ' disabled' : '';
        const label = isCurrent ? 'Current' : (isFree ? 'Free' : (t.order_no || 'Occupied'));
        return '<button type="button" class="rp-mt-table-btn ' + cls + pick + '" data-table-id="' + t.id + '"' + dis + '>' +
            '<span class="rp-mt-shape">' +
                '<span class="rp-mt-chair rp-mt-chair--n"></span>' +
                '<span class="rp-mt-chair rp-mt-chair--e"></span>' +
                '<span class="rp-mt-chair rp-mt-chair--s"></span>' +
                '<span class="rp-mt-chair rp-mt-chair--w"></span>' +
                '<span class="rp-mt-top"><span class="rp-mt-name">' + escH(t.name) + '</span></span>' +
            '</span>' +
            '<span class="rp-mt-label">' + escH(label) + '</span>' +
        '</button>';
    }

    function buildModal(currentTableId) {
        // Group by area
        const areaMap = {};
        tableBoard.forEach(t => {
            const key = t.sitting_area_id != null ? String(t.sitting_area_id) : 'none';
            const name = t.sitting_area_name || 'Other';
            if (!areaMap[key]) areaMap[key] = { name, tables: [] };
            areaMap[key].tables.push(t);
        });
        const areas = Object.entries(areaMap);
        const multi = areas.length > 1;

        // Tabs
        if (multi) {
            tabsEl.innerHTML = '<div class="rp-mt-area-tabs-inner">' +
                '<button type="button" class="rp-mt-area-tab is-active" data-area-key="all">All</button>' +
                areas.map(([key, a]) => '<button type="button" class="rp-mt-area-tab" data-area-key="' + escH(key) + '">' + escH(a.name) + '</button>').join('') +
            '</div>';
        } else {
            tabsEl.innerHTML = '';
        }

        // Body
        body.innerHTML = areas.map(([key, a]) =>
            '<div class="rp-mt-area-section" data-area-key="' + escH(key) + '">' +
            (multi ? '<div class="rp-mt-area-title px-2">' + escH(a.name) + '</div>' : '') +
            '<div class="rp-mt-grid">' +
                a.tables.map(t => tableIcon(t, Number(t.id) === Number(currentTableId))).join('') +
            '</div></div>'
        ).join('');
    }

    // Tab switching
    tabsEl.addEventListener('click', function(e) {
        const tab = e.target.closest('.rp-mt-area-tab');
        if (!tab) return;
        tabsEl.querySelectorAll('.rp-mt-area-tab').forEach(b => b.classList.remove('is-active'));
        tab.classList.add('is-active');
        const key = tab.dataset.areaKey;
        body.querySelectorAll('.rp-mt-area-section').forEach(sec => {
            sec.classList.toggle('d-none', key !== 'all' && sec.dataset.areaKey !== key);
        });
    });

    // Open modal on click
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.js-ot-move-table');
        if (!btn) return;
        currentOrderId = btn.dataset.orderId;
        const currentTable = Number(btn.dataset.currentTable);
        const orderNo = btn.dataset.orderNo || '';
        document.getElementById('otMoveTableTitle').textContent = 'Select New Table — ' + orderNo;
        buildModal(currentTable);
        modal.show();
    });

    // Pick table
    body.addEventListener('click', async function(e) {
        const btn = e.target.closest('.js-ot-pick-table');
        if (!btn || btn.disabled || !currentOrderId) return;
        const tableId = Number(btn.dataset.tableId);
        body.querySelectorAll('.js-ot-pick-table').forEach(b => { b.disabled = true; });

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
                body.querySelectorAll('.js-ot-pick-table').forEach(b => { b.disabled = false; });
                return;
            }
            modal.hide();
            location.reload();
        } catch (err) {
            alert('Table move request fail.');
            body.querySelectorAll('.js-ot-pick-table').forEach(b => { b.disabled = false; });
        }
    });
})();
</script>
@endsection
