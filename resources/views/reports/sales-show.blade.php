@extends('layouts.admin')
@section('title', 'Bill '.$order->order_no.' — ' . config('app.name'))

@section('content')
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-0">Order detail</h4>
        <div class="text-secondary small">Bill #{{ $order->order_no }} — items & amounts</div>
    </div>
    <a href="{{ route('reports.sales') }}" class="btn btn-outline-secondary btn-sm">← Sales Report</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-semibold">Items in this order</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Item</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Rate</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($order->items as $line)
                        <tr>
                            <td class="small text-secondary">{{ $loop->iteration }}</td>
                            <td>
                                <div class="fw-semibold small">{{ $line->displayName() }}</div>
                                @if(trim((string) ($line->notes ?? '')) !== '')
                                    <div class="text-secondary" style="font-size:0.75rem;">{{ $line->notes }}</div>
                                @endif
                            </td>
                            <td class="text-center small">{{ fmt_num((float) $line->qty, 3) }} {{ $line->uom }}</td>
                            <td class="text-end small">{{ $currency }} {{ fmt_num((float) $line->unit_price, 2) }}</td>
                            <td class="text-end small fw-semibold">{{ $currency }} {{ fmt_num((float) $line->total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-secondary py-4">No items</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white fw-semibold">Bill info</div>
            <div class="card-body small">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Bill #</span>
                    <span class="fw-semibold">{{ $order->order_no }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Date</span>
                    <span>{{ optional($order->paid_at ?? $order->created_at)->format('d M Y h:i A') }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Customer</span>
                    <span>{{ $order->customerDisplayNameForReport() }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Order type</span>
                    <span>{{ $order->serviceTypeLabel() ?: '—' }}</span>
                </div>
                @if($order->table?->name)
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Table</span>
                    <span>{{ $order->table->name }}</span>
                </div>
                @endif
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Cashier</span>
                    <span>{{ $order->user?->name ?: '—' }}</span>
                </div>
                @if($order->is_credit)
                <div class="d-flex justify-content-between">
                    <span class="text-secondary">Payment</span>
                    <span class="badge text-bg-warning text-dark">Credit</span>
                </div>
                @endif
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-semibold">Totals</div>
            <div class="card-body small">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Subtotal</span>
                    <span>{{ $currency }} {{ fmt_num((float) $order->subtotal, 2) }}</span>
                </div>
                @if((float) ($order->service_charge_total ?? 0) > 0)
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Service charges</span>
                    <span>{{ $currency }} {{ fmt_num((float) $order->service_charge_total, 2) }}</span>
                </div>
                @endif
                @if((float) $order->discount_total > 0)
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Discount</span>
                    <span class="text-danger">− {{ $currency }} {{ fmt_num((float) $order->discount_total, 2) }}</span>
                </div>
                @endif
                @if((float) $order->tax_total > 0)
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Tax</span>
                    <span>{{ $currency }} {{ fmt_num((float) $order->tax_total, 2) }}</span>
                </div>
                @endif
                <hr class="my-2">
                <div class="d-flex justify-content-between fw-bold">
                    <span>Total bill</span>
                    <span>{{ $currency }} {{ fmt_num((float) $order->grand_total, 2) }}</span>
                </div>

                @if(!$order->is_credit && $order->payments->isNotEmpty())
                    <hr class="my-2">
                    <div class="text-secondary mb-1">Payments</div>
                    @foreach($order->payments as $pay)
                        <div class="d-flex justify-content-between mb-1">
                            <span>{{ ucfirst($pay->method) }}</span>
                            <span>{{ $currency }} {{ fmt_num((float) $pay->amount, 2) }}</span>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
