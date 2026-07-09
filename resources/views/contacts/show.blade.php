@extends('layouts.admin')
@section('title', $contact->name . ' — Contacts')

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><button class="btn-close" data-bs-dismiss="alert"></button>{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show"><button class="btn-close" data-bs-dismiss="alert"></button>{{ session('error') }}</div>
@endif

{{-- Header --}}
<div class="mb-4 d-flex align-items-start justify-content-between flex-wrap gap-3">
    <div class="d-flex align-items-center gap-3">
        <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white fs-5"
            style="width:52px;height:52px;background:linear-gradient(135deg,#7c3aed,#a78bfa);flex-shrink:0;">
            {{ strtoupper(substr($contact->name,0,1)) }}
        </div>
        <div>
            <h4 class="fw-bold mb-0">{{ $contact->name }}</h4>
            <div class="text-secondary small">
                @if($contact->phone) <span class="me-3">📞 {{ $contact->phone }}</span> @endif
                @if($contact->email) <span class="me-3">✉ {{ $contact->email }}</span> @endif
                @if($contact->city)  <span>📍 {{ $contact->city }}</span> @endif
            </div>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('contacts.index') }}" class="btn btn-outline-secondary btn-sm">← Back</a>
        <a href="{{ route('contacts.edit', $contact) }}" class="btn btn-outline-primary btn-sm">Edit</a>
    </div>
</div>

{{-- KPI cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #7c3aed!important;">
            <div class="card-body py-3">
                <div class="text-secondary small">Total Credit</div>
                <div class="fw-bold fs-5 mt-1" style="color:#7c3aed;">{{ fmt_num($totalCredit, 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #22c55e!important;">
            <div class="card-body py-3">
                <div class="text-secondary small">Total Paid</div>
                <div class="fw-bold fs-5 mt-1" style="color:#22c55e;">{{ fmt_num($totalPaid, 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid {{ $balance > 0 ? '#ef4444' : '#22c55e' }}!important;">
            <div class="card-body py-3">
                <div class="text-secondary small">Balance</div>
                <div class="fw-bold fs-5 mt-1" style="color:{{ $balance > 0 ? '#ef4444' : '#22c55e' }};">
                    {{ fmt_num(abs($balance), 2) }}
                    @if($balance > 0) <small class="fw-normal text-danger">(owes)</small>
                    @elseif($balance < 0) <small class="fw-normal text-success">(overpaid)</small>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #0ea5e9!important;">
            <div class="card-body py-3">
                <div class="text-secondary small">POS Orders</div>
                <div class="fw-bold fs-5 mt-1" style="color:#0ea5e9;">{{ $contact->posOrders->count() }}</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- Ledger --}}
    <div class="col-12 col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
                <span class="fw-semibold">Credit Ledger</span>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addEntryModal">
                    + Add Entry
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Date</th>
                                <th>Description</th>
                                <th>Type</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end">Balance</th>
                                <th class="text-end pe-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($ledger as $entry)
                            <tr>
                                <td class="ps-3 small text-secondary">{{ $entry->entry_date->format('d M Y') }}</td>
                                <td>
                                    <div class="small fw-semibold">{{ $entry->description }}</div>
                                    @if($entry->posOrder)
                                        <div style="font-size:11px;" class="text-secondary">
                                            POS: {{ $entry->posOrder->order_no }}
                                        </div>
                                    @endif
                                    @if($entry->notes)
                                        <div style="font-size:11px;" class="text-secondary fst-italic">{{ $entry->notes }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if($entry->type === 'credit')
                                        <span class="badge bg-danger bg-opacity-15 text-danger border border-danger border-opacity-25">Credit</span>
                                    @else
                                        <span class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-25">Payment</span>
                                    @endif
                                </td>
                                <td class="text-end fw-semibold small {{ $entry->type === 'credit' ? 'text-danger' : 'text-success' }}">
                                    {{ $entry->type === 'credit' ? '+' : '-' }} {{ fmt_num($entry->amount, 2) }}
                                </td>
                                <td class="text-end small text-secondary">{{ fmt_num($entry->balance_after, 2) }}</td>
                                <td class="text-end pe-3 text-nowrap">
                                    @if($entry->pos_order_id && $entry->posOrder)
                                        <button type="button"
                                           class="btn btn-sm btn-outline-primary py-0 px-2 js-pos-sale-view"
                                           style="font-size:11px;"
                                           data-pos-sale-url="{{ route('credit-book.pos-sale', $entry->posOrder) }}">View</button>
                                    @elseif(!$entry->pos_order_id)
                                    <form method="POST" action="{{ route('credit-book.destroy', $entry) }}"
                                        onsubmit="return confirm('Delete this entry?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger py-0 px-1" style="font-size:11px;">×</button>
                                    </form>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center py-4 text-secondary small">No ledger entries yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($ledger->hasPages())
                <div class="px-3 py-2 border-top">{{ $ledger->links() }}</div>
                @endif
            </div>
        </div>
    </div>

    {{-- Sidebar info --}}
    <div class="col-12 col-lg-4">
        {{-- Contact details card --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold py-3 small">Contact Info</div>
            <div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-5 text-secondary fw-normal">Category</dt>
                    <dd class="col-7">{{ $contact->categoryLabel() }}</dd>
                    <dt class="col-5 text-secondary fw-normal">Phone</dt>
                    <dd class="col-7">{{ $contact->phone ?? '—' }}</dd>
                    <dt class="col-5 text-secondary fw-normal">Email</dt>
                    <dd class="col-7">{{ $contact->email ?? '—' }}</dd>
                    <dt class="col-5 text-secondary fw-normal">Address</dt>
                    <dd class="col-7">{{ $contact->address ?? '—' }}</dd>
                    <dt class="col-5 text-secondary fw-normal">City</dt>
                    <dd class="col-7">{{ $contact->city ?? '—' }}</dd>
                    @if($contact->notes)
                    <dt class="col-5 text-secondary fw-normal">Notes</dt>
                    <dd class="col-7">{{ $contact->notes }}</dd>
                    @endif
                </dl>
            </div>
        </div>

        {{-- Recent POS orders --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold py-3 small">Recent POS Orders</div>
            <div class="card-body p-0">
                @forelse($contact->posOrders->sortByDesc('id')->take(6) as $order)
                <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom small">
                    <div>
                        <div class="fw-semibold">{{ $order->order_no }}</div>
                        <div class="text-secondary" style="font-size:11px;">{{ $order->created_at->format('d M Y') }}</div>
                    </div>
                    <div class="text-end d-flex align-items-center gap-2">
                        <div>
                            <div class="fw-semibold">{{ fmt_num($order->grand_total, 2) }}</div>
                            @if($order->is_credit)
                                <span class="badge bg-danger bg-opacity-15 text-danger" style="font-size:10px;">Credit</span>
                            @else
                                <span class="badge bg-success bg-opacity-15 text-success" style="font-size:10px;">Paid</span>
                            @endif
                        </div>
                        @if($order->is_credit && $order->creditLedger)
                            <button type="button"
                               class="btn btn-sm btn-outline-primary py-0 px-2 js-pos-sale-view"
                               style="font-size:11px;"
                               data-pos-sale-url="{{ route('credit-book.pos-sale', $order) }}">View</button>
                        @endif
                    </div>
                </div>
                @empty
                <div class="px-3 py-3 text-secondary small">No POS orders yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- POS Sale View Modal --}}
<div class="modal fade" id="posSaleViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">POS Sale Detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="posSaleViewBody" style="min-height:70vh;">
                <div class="d-flex align-items-center justify-content-center h-100 text-secondary small p-4">
                    Select a POS entry to view details.
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Add Credit/Payment Modal --}}
<div class="modal fade" id="addEntryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Credit / Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('credit-book.store') }}">
                @csrf
                <input type="hidden" name="contact_id" value="{{ $contact->id }}">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Type <span class="text-danger">*</span></label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="type" id="typeCredit" value="credit" checked>
                                <label class="form-check-label text-danger fw-semibold" for="typeCredit">Credit (Owes)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="type" id="typePayment" value="payment">
                                <label class="form-check-label text-success fw-semibold" for="typePayment">Payment (Received)</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description <span class="text-danger">*</span></label>
                        <input type="text" name="description" class="form-control" required
                            placeholder="e.g. Manual credit, Cash received…">
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                            <input type="number" name="amount" class="form-control" min="0.01" step="0.01" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" name="entry_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2"
                            placeholder="Optional notes…"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('posSaleViewModal');
    const body = document.getElementById('posSaleViewBody');
    if (!modalEl || !body || typeof bootstrap === 'undefined') return;

    const modal = new bootstrap.Modal(modalEl);
    const loadingHtml = `
        <div class="d-flex align-items-center justify-content-center h-100 text-secondary small p-4">
            Loading...
        </div>`;
    const errorHtml = `
        <div class="d-flex align-items-center justify-content-center h-100 text-danger small p-4">
            Detail load nahi ho saki.
        </div>`;

    document.querySelectorAll('.js-pos-sale-view').forEach((button) => {
        button.addEventListener('click', async () => {
            const url = button.getAttribute('data-pos-sale-url');
            if (!url) return;
            body.innerHTML = loadingHtml;
            modal.show();
            try {
                const res = await fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!res.ok) throw new Error('Request failed');
                body.innerHTML = await res.text();
            } catch (e) {
                body.innerHTML = errorHtml;
            }
        });
    });

    modalEl.addEventListener('hidden.bs.modal', () => {
        body.innerHTML = `
            <div class="d-flex align-items-center justify-content-center h-100 text-secondary small p-4">
                Select a POS entry to view details.
            </div>`;
    });
});
</script>
@endsection
