<div class="p-3 p-lg-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h6 class="mb-1 fw-bold">{{ $order->order_no }}</h6>
            <div class="small text-secondary">
                {{ $order->paid_at?->format('d M Y H:i') ?? $order->created_at->format('d M Y H:i') }}
            </div>
        </div>
        <div class="text-end">
            <div class="badge bg-danger bg-opacity-15 text-danger border border-danger border-opacity-25">Credit Sale</div>
            <div class="small text-secondary mt-1">{{ $order->customerTypeLabel() }}</div>
        </div>
    </div>

    <div class="row g-2 small mb-3">
        <div class="col-md-4"><span class="text-secondary">Officer / Customer:</span> <span class="fw-semibold">{{ $order->contact?->name ?? '—' }}</span></div>
        <div class="col-md-4"><span class="text-secondary">Phone:</span> <span class="fw-semibold">{{ $order->contact?->phone ?: '—' }}</span></div>
        <div class="col-md-4"><span class="text-secondary">Cashier:</span> <span class="fw-semibold">{{ $order->user?->name ?? '—' }}</span></div>
        @if(($settings['pos_enable_tables'] ?? '1') === '1')
            <div class="col-md-4"><span class="text-secondary">Table:</span> <span class="fw-semibold">{{ $order->table?->name ?? '—' }}</span></div>
        @endif
        <div class="col-md-4"><span class="text-secondary">Guest:</span> <span class="fw-semibold">{{ $order->guest_name ?: '—' }}</span></div>
        <div class="col-md-4"><span class="text-secondary">Room:</span> <span class="fw-semibold">{{ $order->room_no ?: '—' }}</span></div>
    </div>

    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Item</th>
                    <th class="text-end">Qty</th>
                    <th>UOM</th>
                    <th class="text-end">Price</th>
                    <th class="text-end">Discount</th>
                    <th class="text-end">Tax</th>
                    <th class="text-end">Total</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($order->items as $line)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $line->displayName() }}</div>
                            @if(!empty($line->product?->sku))
                                <div class="small text-secondary">SKU: {{ $line->product->sku }}</div>
                            @endif
                        </td>
                        <td class="text-end">{{ fmt_num((float) $line->qty, 3) }}</td>
                        <td>{{ $line->uom }}</td>
                        <td class="text-end">{{ $settings['currency_symbol'] ?? 'Rs.' }}{{ fmt_num((float) $line->unit_price, 2) }}</td>
                        <td class="text-end">{{ fmt_num((float) $line->discount_amount, 2) }}</td>
                        <td class="text-end">{{ fmt_num((float) $line->tax_amount, 2) }}</td>
                        <td class="text-end fw-semibold">{{ $settings['currency_symbol'] ?? 'Rs.' }}{{ fmt_num((float) $line->total, 2) }}</td>
                        <td class="small text-secondary">{{ trim((string) $line->notes) !== '' ? $line->notes : '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-secondary py-4">No items found.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th colspan="6" class="text-end">Bill Total</th>
                    <th class="text-end fs-6">{{ $settings['currency_symbol'] ?? 'Rs.' }}{{ fmt_num((float) $order->grand_total, 2) }}</th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
