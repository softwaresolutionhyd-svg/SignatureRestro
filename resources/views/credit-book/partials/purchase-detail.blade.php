<div class="p-3 p-lg-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h6 class="mb-1 fw-bold">Purchase {{ $order->number }}</h6>
            <div class="small text-secondary">
                {{ $order->order_date?->format('d M Y') ?? $order->created_at->format('d M Y H:i') }}
            </div>
        </div>
        <div class="text-end">
            @if($order->payment_status === 'paid')
                <div class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-25">Credit — Paid</div>
            @else
                <div class="badge bg-danger bg-opacity-15 text-danger border border-danger border-opacity-25">Credit — Unpaid</div>
            @endif
            <div class="small text-secondary mt-1">{{ ucfirst($order->status) }}</div>
        </div>
    </div>

    <div class="row g-2 small mb-3">
        <div class="col-md-4"><span class="text-secondary">Supplier / Vendor:</span> <span class="fw-semibold">{{ $order->vendor?->name ?? '—' }}</span></div>
        <div class="col-md-4"><span class="text-secondary">Phone:</span> <span class="fw-semibold">{{ $order->vendor?->phone ?: '—' }}</span></div>
        <div class="col-md-4"><span class="text-secondary">Created by:</span> <span class="fw-semibold">{{ $order->creator?->name ?? '—' }}</span></div>
        <div class="col-md-4"><span class="text-secondary">Order date:</span> <span class="fw-semibold">{{ $order->order_date?->format('d M Y') ?? '—' }}</span></div>
        <div class="col-md-4"><span class="text-secondary">Paid on:</span> <span class="fw-semibold">{{ $order->paid_at?->format('d M Y') ?? '—' }}</span></div>
        @if($order->note)
            <div class="col-12"><span class="text-secondary">Note:</span> <span class="fw-semibold">{{ $order->note }}</span></div>
        @endif
    </div>

    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Item</th>
                    <th class="text-end">Qty</th>
                    <th>UOM</th>
                    <th class="text-end">Unit Price</th>
                    <th class="text-end">Tax</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($order->lines as $line)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $line->product->name ?? $line->description ?? 'Item' }}</div>
                            @if(!empty($line->product?->sku))
                                <div class="small text-secondary">SKU: {{ $line->product->sku }}</div>
                            @endif
                        </td>
                        <td class="text-end">{{ fmt_num((float) $line->qty, 3) }}</td>
                        <td>{{ $line->uom }}</td>
                        <td class="text-end">{{ $settings['currency_symbol'] ?? 'Rs.' }}{{ fmt_num((float) $line->unit_price, 2) }}</td>
                        <td class="text-end">{{ fmt_num((float) $line->tax_amount, 2) }}</td>
                        <td class="text-end fw-semibold">{{ $settings['currency_symbol'] ?? 'Rs.' }}{{ fmt_num((float) $line->total, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-secondary py-4">No items found.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="4" class="text-end">Subtotal</th>
                    <th colspan="2" class="text-end">{{ $settings['currency_symbol'] ?? 'Rs.' }}{{ fmt_num((float) $order->subtotal, 2) }}</th>
                </tr>
                @if((float) $order->tax_total > 0)
                <tr>
                    <th colspan="4" class="text-end">Tax</th>
                    <th colspan="2" class="text-end">{{ $settings['currency_symbol'] ?? 'Rs.' }}{{ fmt_num((float) $order->tax_total, 2) }}</th>
                </tr>
                @endif
                <tr>
                    <th colspan="4" class="text-end">Bill Total</th>
                    <th colspan="2" class="text-end fs-6">{{ $settings['currency_symbol'] ?? 'Rs.' }}{{ fmt_num((float) $order->grand_total, 2) }}</th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
