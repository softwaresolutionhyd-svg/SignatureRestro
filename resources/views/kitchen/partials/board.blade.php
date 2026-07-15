@php
    use Illuminate\Support\Facades\Schema;
    $kitchen = app(\App\Services\KitchenService::class);
    $totalOrders = $orders->count();
    $hasKitchenPos = Schema::hasColumn('pos_orders', 'kitchen_pos_x') && Schema::hasColumn('pos_orders', 'kitchen_pos_y');
@endphp

@if($totalOrders === 0)
    <div class="kitchen-empty text-center py-5">
        <div class="display-6 text-secondary mb-2"><i class="bi bi-check2-circle"></i></div>
        <h5 class="fw-semibold mb-1">Koi active order nahi</h5>
        <p class="text-secondary mb-0">Naye orders yahan dikhenge — card ko kahin bhi drag karein.</p>
    </div>
@else
    <div class="kitchen-free-board" id="kitchenFreeBoard">
        @foreach($orders as $index => $order)
            @php
                $sentAt = $order->ready_for_pos_at ?? $order->created_at;
                $serveAt = $order->serveAt();
                $shouldBlink = $order->shouldKitchenBlink();
                $tableMeta = $kitchen->tableLabelFor($order);
                $defaultCol = $index % 4;
                $defaultRow = intdiv($index, 4);
                $defaultX = $defaultCol * 256 + 12;
                $defaultY = $defaultRow * 170 + 12;
                if ($hasKitchenPos && $order->kitchen_pos_x !== null && $order->kitchen_pos_y !== null) {
                    $rawX = (int) $order->kitchen_pos_x;
                    $rawY = (int) $order->kitchen_pos_y;
                    // Purani percent saves (0–100) ko pixels mein convert
                    if ($rawX <= 100 && $rawY <= 100) {
                        $posX = (int) round($rawX / 100 * 880);
                        $posY = (int) round($rawY / 100 * 480);
                    } else {
                        $posX = $rawX;
                        $posY = $rawY;
                    }
                } else {
                    $posX = $defaultX;
                    $posY = $defaultY;
                }
            @endphp
            <article
                class="kitchen-order-card kitchen-free-card{{ $shouldBlink ? ' is-serve-soon' : '' }}"
                data-order-id="{{ $order->id }}"
                data-pos-url="{{ $hasKitchenPos ? route('kitchen.position', $order) : '' }}"
                @if($serveAt) data-serve-at="{{ $serveAt->toIso8601String() }}" @endif
                style="left: {{ $posX }}px; top: {{ $posY }}px;"
            >
                <div class="kitchen-drag-bar" title="Drag card">
                    <i class="bi bi-arrows-move"></i>
                    <span>Drag</span>
                </div>
                <div class="kitchen-order-top">
                    <div class="kitchen-order-head-main">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <span class="kitchen-table-pill">{{ in_array($order->customerTypeKey(), ['mess_use', 'ast_offr'], true) ? $order->customerTypeLabel() : $tableMeta['label'] }}</span>
                            <span class="kitchen-order-no">{{ $order->order_no }}</span>
                            @if($order->kitchenStatusKey() !== \App\Models\PosOrder::KITCHEN_STATUS_QUEUED)
                                <span class="badge {{ $order->kitchenStatusBadgeClass() }} kitchen-status-badge">
                                    {{ $order->cafeStatusLabel() ?: ucfirst($order->kitchenStatusKey()) }}
                                </span>
                            @endif
                            @if($order->isFromOrderTaker())
                                <span class="badge text-bg-info">OT</span>
                            @endif
                        </div>
                        @php
                            $kitchenGuestName = trim((string) ($order->guest_name ?? ''));
                        @endphp
                        @if($kitchenGuestName !== '')
                            <div class="kitchen-order-guest">{{ $kitchenGuestName }}</div>
                        @endif
                        <div class="kitchen-order-meta">
                            @if($order->waiter_name)
                                <span><i class="bi bi-person"></i> {{ $order->waiter_name }}</span>
                            @endif
                            @if($order->table && $order->customerTypeKey() === 'mess_use')
                                <span><i class="bi bi-table"></i> {{ $order->table->name }}</span>
                            @endif
                            @if($order->room_no)
                                <span><i class="bi bi-door-open"></i> {{ $order->room_no }}</span>
                            @endif
                            @if($order->isFromOrderTaker())
                                @php $orderAt = $order->ready_for_pos_at ?? $order->created_at; @endphp
                                @if($orderAt)
                                    <span><i class="bi bi-calendar-event"></i> Order {{ $orderAt->format('H:i') }}</span>
                                @endif
                            @endif
                            @if($serveAt)
                                @php $mealLabel = \App\Support\ServeMealSchedule::label($order->serve_meal); @endphp
                                <span class="kitchen-serve-at"><i class="bi bi-alarm"></i>
                                    @if($mealLabel)
                                        {{ $mealLabel }} · {{ $serveAt->format('d M, H:i') }}
                                    @else
                                        Serve {{ $serveAt->format('d M, H:i') }}
                                    @endif
                                </span>
                            @elseif(trim((string) ($order->serve_time ?? '')) !== '')
                                <span><i class="bi bi-alarm"></i> Serve {{ $order->serve_time }}</span>
                            @endif
                            <span><i class="bi bi-clock"></i> {{ $sentAt?->diffForHumans(short: true) }}</span>
                        </div>
                    </div>
                </div>
                <ul class="kitchen-item-list list-unstyled mb-2">
                    @foreach($kitchen->itemsForKitchenDisplay($order) as $line)
                        <li class="kitchen-item-row" data-item-id="{{ $line->id }}">
                            <div class="kitchen-item-main">
                                <div class="kitchen-item-name">{{ $line->product->name ?? 'Item' }}</div>
                                <div class="kitchen-item-qty">{{ fmt_num((float) $line->qty, 3) }} {{ $line->uom }}</div>
                                @if(trim((string) ($line->notes ?? '')) !== '')
                                    <div class="kitchen-item-note">{{ $line->notes }}</div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
                @if(trim((string) ($order->kitchen_notes ?? '')) !== '')
                    <div class="kitchen-item-note mb-2"><strong>Bill:</strong> {{ $order->kitchen_notes }}</div>
                @endif
                <div class="kitchen-status-actions">
                    @if($order->kitchenStatusKey() === \App\Models\PosOrder::KITCHEN_STATUS_QUEUED)
                        <form method="POST" action="{{ route('kitchen.status', [$order, 'preparing']) }}" class="kitchen-status-form" data-remove="0">
                            @csrf
                            <button type="submit" class="btn btn-warning w-100 btn-sm">
                                <i class="bi bi-fire me-1"></i> Preparing
                            </button>
                        </form>
                    @elseif($order->kitchenStatusKey() === \App\Models\PosOrder::KITCHEN_STATUS_PREPARING)
                        <form method="POST" action="{{ route('kitchen.status', [$order, 'complete']) }}" class="kitchen-status-form kitchen-complete-form" data-remove="0">
                            @csrf
                            <button type="submit" class="btn btn-success w-100 btn-sm kitchen-complete-btn">
                                <i class="bi bi-check2-circle me-1"></i> Complete Order
                            </button>
                        </form>
                    @elseif($order->kitchenStatusKey() === \App\Models\PosOrder::KITCHEN_STATUS_READY)
                        <form method="POST" action="{{ route('kitchen.status', [$order, 'served']) }}" class="kitchen-status-form" data-remove="1">
                            @csrf
                            <button type="submit" class="btn btn-primary w-100 btn-sm">
                                <i class="bi bi-bag-check me-1"></i> Order Served
                            </button>
                        </form>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
@endif
