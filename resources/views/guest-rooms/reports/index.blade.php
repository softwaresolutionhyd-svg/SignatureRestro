@extends('layouts.admin')
@section('title', 'Guest Rooms — Reports')
@section('content')
@include('guest-rooms.partials.subnav')

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h4 class="fw-bold mb-0">Reports</h4>
        <div class="text-secondary small">Bookings, guests, revenue & occupancy summary</div>
    </div>
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
</div>

<form method="GET" class="card border-0 shadow-sm mb-4 no-print">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                @include('partials.form-date-dmy', ['name' => 'from', 'label' => 'From date', 'value' => $from, 'class' => 'form-control form-control-sm', 'id' => 'report-from'])
            </div>
            <div class="col-md-3">
                @include('partials.form-date-dmy', ['name' => 'to', 'label' => 'To date', 'value' => $to, 'class' => 'form-control form-control-sm', 'id' => 'report-to'])
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary btn-sm">Apply</button>
                <a href="{{ route('guest-rooms.reports.index') }}" class="btn btn-outline-secondary btn-sm">This month</a>
            </div>
            <div class="col-md-3 text-md-end small text-secondary">
                Period: <strong>{{ fmt_date($from) }}</strong> — <strong>{{ fmt_date($to) }}</strong>
            </div>
        </div>
    </div>
</form>

<div class="row g-3 mb-4">
    @foreach([
        ['Bookings', $bookings->count(), 'primary'],
        ['Checked out', $statusCounts['checked_out'] ?? 0, 'success'],
        ['Checked in', $statusCounts['checked_in'] ?? 0, 'warning'],
        ['Reserved', $statusCounts['reserved'] ?? 0, 'info'],
        ['Cancelled', $statusCounts['cancelled'] ?? 0, 'secondary'],
        ['Online', $typeCounts['online'] ?? 0, 'info'],
        ['Manual', $typeCounts['manual'] ?? 0, 'dark'],
        ['Bills', $revenue['bills'], 'secondary'],
    ] as [$label, $val, $color])
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="fs-4 fw-bold text-{{ $color }}">{{ $val }}</div>
                <div class="small text-secondary">{{ $label }}</div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-primary">
            <div class="card-body text-center py-3">
                <div class="fs-4 fw-bold text-primary">{{ number_format($guestCounts['adults']) }}</div>
                <div class="small text-secondary">Adults in period</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-info">
            <div class="card-body text-center py-3">
                <div class="fs-4 fw-bold text-info">{{ number_format($guestCounts['children']) }}</div>
                <div class="small text-secondary">Children in period</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-success">
            <div class="card-body text-center py-3">
                <div class="fs-4 fw-bold text-success">{{ number_format($guestCounts['total']) }}</div>
                <div class="small text-secondary">Total guests (adults + children)</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Revenue summary</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><td>Total billed</td><td class="text-end fw-semibold">{{ number_format($revenue['total'], 2) }}</td></tr>
                    <tr><td>Collected</td><td class="text-end text-success">{{ number_format($revenue['collected'], 2) }}</td></tr>
                    <tr><td>Outstanding</td><td class="text-end text-danger">{{ number_format($revenue['balance'], 2) }}</td></tr>
                    <tr><td>Room charges</td><td class="text-end">{{ number_format($revenue['room_charges'], 2) }}</td></tr>
                    <tr><td>Extra / damage charges</td><td class="text-end">{{ number_format($revenue['extra_charges'], 2) }}</td></tr>
                    <tr><td>Extra charges (bookings)</td><td class="text-end">{{ number_format($extraChargesTotal, 2) }}</td></tr>
                    <tr><td>Discount</td><td class="text-end">-{{ number_format($revenue['discount'], 2) }}</td></tr>
                    <tr><td>Tax</td><td class="text-end">{{ number_format($revenue['tax'], 2) }}</td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Current room status</div>
            <div class="card-body">
                @forelse($roomStatus as $status => $count)
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span>{{ \App\Models\GuestRoom::statusLabels()[$status] ?? $status }}</span>
                    <span class="fw-semibold">{{ $count }}</span>
                </div>
                @empty
                <p class="text-secondary small mb-0">No rooms.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold">Revenue by category</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Category</th>
                        <th class="text-center">Bills</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end pe-3">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($revenueByCategory as $row)
                    <tr>
                        <td class="ps-3 fw-semibold">{{ $row->category_name ?? 'Uncategorized' }}</td>
                        <td class="text-center">{{ $row->bill_count }}</td>
                        <td class="text-end">{{ number_format($row->total_amount, 2) }}</td>
                        <td class="text-end text-success">{{ number_format($row->paid_amount, 2) }}</td>
                        <td class="text-end pe-3">{{ number_format($row->balance_amount, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center py-4 text-secondary">No bills in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>Bookings (check-in / check-out in period)</span>
        <span class="small fw-normal text-secondary">
            Guests: <strong>{{ number_format($guestCounts['adults']) }}</strong> adults,
            <strong>{{ number_format($guestCounts['children']) }}</strong> children
            (cancelled excluded)
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Booking #</th>
                        <th>PA No / C/O</th>
                        <th>Rank</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Room(s)</th>
                        <th>Category</th>
                        <th>Guest category</th>
                        <th>Guest type</th>
                        <th class="text-center">Adults</th>
                        <th class="text-center">Children</th>
                        <th>Check-in</th>
                        <th>Check-out</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bookings as $b)
                    <tr>
                        <td class="ps-3"><a href="{{ route('guest-rooms.bookings.show', $b) }}">{{ $b->booking_no }}</a></td>
                        <td>@if($b->isCivilianPersonType()){{ $b->care_of ?? '—' }}@else{{ $b->pa_no ?? '—' }}@endif</td>
                        <td>@if($b->isCivilianPersonType())—@else{{ $b->guest_rank ?? '—' }}@endif</td>
                        <td class="fw-semibold">{{ $b->guest_name }}</td>
                        <td>{{ \App\Models\RoomBooking::bookingTypeLabels()[$b->booking_type ?? 'manual'] ?? 'Manual' }}</td>
                        <td>{{ $b->roomNumbersLabel() }}</td>
                        <td>{{ $b->category?->name ?? '—' }}</td>
                        <td>{{ $b->guestCategoryLabel() ?? '—' }}</td>
                        <td>{{ $b->person_type ?? '—' }}</td>
                        <td class="text-center">{{ $b->adults }}</td>
                        <td class="text-center">{{ $b->children }}</td>
                        <td>{{ fmt_date($b->check_in_date) }}</td>
                        <td>{{ fmt_date($b->check_out_date) }}</td>
                        <td><span class="badge bg-secondary">{{ \App\Models\RoomBooking::statusLabels()[$b->status] ?? $b->status }}</span></td>
                        <td class="text-end pe-3 fw-semibold">{{ number_format($b->total_amount, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="15" class="text-center py-4 text-secondary">No bookings in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">Bills (billed in period)</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Bill #</th>
                        <th>Guest</th>
                        <th>Room(s)</th>
                        <th>Date</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Balance</th>
                        <th>Status</th>
                        <th class="text-end pe-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bills as $bill)
                    <tr>
                        <td class="ps-3 fw-semibold">{{ $bill->bill_no }}</td>
                        <td>{{ $bill->booking?->guestDisplayName() }}</td>
                        <td>{{ $bill->booking?->roomNumbersLabel() }}</td>
                        <td>{{ fmt_date($bill->billed_at) }}</td>
                        <td class="text-end">{{ number_format($bill->total, 2) }}</td>
                        <td class="text-end text-success">{{ number_format($bill->paid_amount, 2) }}</td>
                        <td class="text-end">{{ number_format($bill->balance, 2) }}</td>
                        <td><span class="badge bg-{{ $bill->payment_status === 'paid' ? 'success' : ($bill->payment_status === 'partial' ? 'warning' : 'danger') }}">{{ ucfirst($bill->payment_status) }}</span></td>
                        <td class="text-end pe-3"><a href="{{ route('guest-rooms.billing.show', $bill) }}" class="btn btn-sm btn-outline-primary py-0">View</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center py-4 text-secondary">No bills in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
@media print {
    .no-print, .admin-topbar, .admin-action-btns, nav[aria-label="breadcrumb"] { display: none !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; }
}
</style>
@endsection

