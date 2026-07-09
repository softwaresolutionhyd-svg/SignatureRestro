@php
    $kitchen = app(\App\Services\KitchenService::class);
    $totalOrders = $orders->count();
@endphp

@if($totalOrders === 0)
    <div class="order-status-empty text-center py-5">
        <div class="display-6 text-secondary mb-2"><i class="bi bi-display"></i></div>
        <h5 class="fw-semibold mb-1">Abhi koi order nahi</h5>
        <p class="text-secondary mb-0">Kitchen mein Preparing dabane par yahan dikhega.</p>
    </div>
@else
    <div class="order-status-grid">
        @foreach($orders as $order)
            @php
                $tableMeta = $kitchen->tableLabelFor($order);
                $placeLabel = $order->customerTypeKey() === 'mess_use'
                    ? $order->customerTypeLabel()
                    : preg_replace('/^Room\s+/i', '', (string) $tableMeta['label']);
                $isReady = $order->kitchenStatusKey() === \App\Models\PosOrder::KITCHEN_STATUS_READY;
                $isPartial = $order->hasPartialKitchenServed();
                $cardClass = $isReady ? 'is-ready' : ($isPartial ? 'is-partial' : 'is-preparing');
                $guestName = trim((string) ($order->guest_name ?: $order->waiter_name ?: ''));
            @endphp
            <article class="order-status-card {{ $cardClass }}">
                <div class="order-status-card-head">
                    <span class="order-status-table">{{ $placeLabel }}</span>
                    <span class="order-status-no">{{ $order->order_no }}</span>
                </div>
                <div class="order-status-label">
                    {{ $order->cafeStatusLabel() }}
                </div>
                @if($guestName !== '')
                    <div class="order-status-guest">{{ $guestName }}</div>
                @endif
                @if(trim((string) ($order->serve_time ?? '')) !== '')
                    <div class="order-status-serve small text-secondary">Serve {{ $order->serve_time }}</div>
                @endif
                @if($order->isFromOrderTaker())
                    @php $orderAt = $order->ready_for_pos_at ?? $order->created_at; @endphp
                    @if($orderAt)
                        <div class="order-status-order-time small text-secondary">Order {{ $orderAt->format('H:i') }}</div>
                    @endif
                @endif
            </article>
        @endforeach
    </div>
@endif
