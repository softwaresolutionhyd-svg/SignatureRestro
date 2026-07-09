<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class PosOrder extends Model
{
    protected $connection = 'tenant';

    public const KITCHEN_STATUS_QUEUED = 'queued';

    public const KITCHEN_STATUS_PREPARING = 'preparing';

    public const KITCHEN_STATUS_READY = 'ready';

    public const KITCHEN_STATUS_SERVED = 'served';

    public const MESS_BILL_LABEL = 'Mess Bill/Offices/Conf Room';

    public const SERVICE_DINE_IN = 'dine_in';

    public const SERVICE_TAKEAWAY = 'takeaway';

    public const SERVICE_DELIVERY = 'delivery';

    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'order_no',
        'session_id',
        'table_id',
        'user_id',
        'contact_id',
        'customer_type',
        'service_type',
        'sale_mode',
        'guest_name',
        'room_no',
        'waiter_name',
        'order_notes',
        'serve_time',
        'serve_date',
        'serve_meal',
        'is_credit',
        'refund_of_order_id',
        'type',
        'status',
        'order_source',
        'subtotal',
        'discount_total',
        'tax_total',
        'bill_tax_percent',
        'bill_discount_percent',
        'grand_total',
        'cash_tendered',
        'cash_change',
        'paid_at',
        'ready_for_pos_at',
        'kitchen_completed_at',
        'kitchen_preparing_at',
        'kitchen_ready_at',
        'kitchen_sort',
        'kitchen_pos_x',
        'kitchen_pos_y',
        'kitchen_status',
    ];

    protected $casts = [
        'subtotal'       => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total'      => 'decimal:2',
        'bill_tax_percent' => 'decimal:3',
        'bill_discount_percent' => 'decimal:3',
        'grand_total'    => 'decimal:2',
        'cash_tendered'  => 'decimal:2',
        'cash_change'    => 'decimal:2',
        'paid_at'        => 'datetime',
        'ready_for_pos_at' => 'datetime',
        'kitchen_completed_at' => 'datetime',
        'kitchen_preparing_at' => 'datetime',
        'kitchen_ready_at' => 'datetime',
        'serve_date'     => 'date:Y-m-d',
        'is_credit'      => 'bool',
    ];

    public function serveAt(): ?Carbon
    {
        $time = trim((string) ($this->serve_time ?? ''));
        if ($time === '') {
            return null;
        }

        if (preg_match('/^\d{1,2}:\d{2}$/', $time) === 1) {
            $parts = explode(':', $time);
            $time = sprintf('%02d:%02d', (int) $parts[0], (int) $parts[1]);
        }

        $dateStr = $this->serve_date instanceof Carbon
            ? $this->serve_date->format('Y-m-d')
            : trim((string) ($this->serve_date ?? ''));

        if ($dateStr === '') {
            $ref = $this->ready_for_pos_at ?? $this->created_at ?? now();
            $dateStr = $ref->timezone(config('app.timezone'))->format('Y-m-d');
        }

        try {
            return Carbon::createFromFormat('Y-m-d H:i', $dateStr.' '.$time, config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Explicit serve date (future scheduling). Null = same-day / immediate order.
     */
    public function scheduledServeDay(): ?Carbon
    {
        if (! Schema::hasColumn($this->getTable(), 'serve_date')) {
            return null;
        }

        $dateStr = $this->serve_date instanceof Carbon
            ? $this->serve_date->format('Y-m-d')
            : trim((string) ($this->serve_date ?? ''));

        if ($dateStr === '') {
            return null;
        }

        try {
            return Carbon::parse($dateStr, config('app.timezone'))->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Kitchen / POS pending: show only on serve date (or earlier if still open).
     */
    public function isDueForServeDay(?Carbon $on = null): bool
    {
        $scheduled = $this->scheduledServeDay();
        if ($scheduled === null) {
            return true;
        }

        $on = ($on ?? now())->timezone(config('app.timezone'))->startOfDay();

        return $on->greaterThanOrEqualTo($scheduled);
    }

    public function shouldKitchenBlink(): bool
    {
        if (! $this->isDueForServeDay()) {
            return false;
        }

        $serveAt = $this->serveAt();
        if ($serveAt === null) {
            return false;
        }

        return now()->gte($serveAt->copy()->subHour());
    }

    public function serveScheduleLabel(): ?string
    {
        $mealLabel = \App\Support\ServeMealSchedule::label($this->serve_meal);
        $serveAt = $this->serveAt();

        if ($mealLabel !== null && $serveAt !== null) {
            return $mealLabel.' · '.$serveAt->format('d M Y, H:i');
        }

        if ($mealLabel !== null) {
            return $mealLabel;
        }

        return $serveAt?->format('d M Y, H:i');
    }

    /**
     * @return list<array{key: string, label: string, value: ?string}>
     */
    public function orderTimelineSteps(): array
    {
        $format = fn (?Carbon $dt): ?string => $dt
            ? $dt->timezone(config('app.timezone'))->format('d M Y, H:i')
            : null;

        $requestAt = $this->ready_for_pos_at ?? $this->created_at;
        $serveAt = $this->serveAt();
        $mealLabel = \App\Support\ServeMealSchedule::label($this->serve_meal);
        $serveLabel = null;
        if ($serveAt !== null) {
            $serveLabel = $mealLabel !== null
                ? $mealLabel.' · '.$format($serveAt)
                : $format($serveAt);
        }

        $steps = [
            ['key' => 'taken_at', 'label' => 'Order taking', 'value' => $format($this->created_at)],
            ['key' => 'requested_at', 'label' => 'Request / kitchen note', 'value' => $format($requestAt)],
            ['key' => 'serve_scheduled_at', 'label' => 'Serve scheduled', 'value' => $serveLabel],
            ['key' => 'preparing_at', 'label' => 'Preparing', 'value' => $format($this->kitchen_preparing_at)],
            ['key' => 'completed_at', 'label' => 'Complete order', 'value' => $format($this->kitchen_ready_at)],
            ['key' => 'served_at', 'label' => 'Served', 'value' => $format($this->kitchen_completed_at)],
        ];

        if ($this->status === 'paid' && $this->paid_at !== null) {
            $steps[] = ['key' => 'paid_at', 'label' => 'Paid', 'value' => $format($this->paid_at)];
        }

        return $steps;
    }

    public function isFromOrderTaker(): bool
    {
        if (! Schema::hasColumn($this->getTable(), 'order_source')) {
            return false;
        }

        return ($this->order_source ?? 'pos') === 'order_taker';
    }

    public function isReadyForPosPickup(): bool
    {
        if (! $this->isFromOrderTaker() || ! Schema::hasColumn($this->getTable(), 'ready_for_pos_at')) {
            return false;
        }

        return $this->status === 'draft'
            && $this->ready_for_pos_at !== null;
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(PosTable::class, 'table_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function creditLedger(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CreditLedger::class, 'pos_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosOrderItem::class, 'order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PosPayment::class, 'order_id');
    }

    /**
     * Label "Customer name" on sales report: saved contact, else POS guest/table name, else walk-in.
     */
    public function customerDisplayNameForReport(): string
    {
        $fromContact = trim((string) ($this->contact?->name ?? ''));
        if ($fromContact !== '') {
            return $fromContact;
        }
        $guest = trim((string) ($this->guest_name ?? ''));

        return $guest !== '' ? $guest : 'Walk-in';
    }

    public function customerTypeKey(): string
    {
        $stored = (string) ($this->customer_type ?? '');
        if (in_array($stored, ['mess_use', 'booking', 'ast_offr'], true)) {
            return $stored;
        }

        return $this->room_no ? 'booking' : 'mess_use';
    }

    public function customerTypeLabel(): string
    {
        return match ($this->customerTypeKey()) {
            'booking' => 'In-House',
            'ast_offr' => self::MESS_BILL_LABEL,
            default => 'Walk-In',
        };
    }

    /** @return array<string, string> */
    public static function serviceTypeLabels(): array
    {
        return [
            self::SERVICE_DINE_IN => 'Dine-in',
            self::SERVICE_TAKEAWAY => 'Takeaway',
            self::SERVICE_DELIVERY => 'Delivery',
        ];
    }

    public function serviceTypeKey(): ?string
    {
        $stored = trim((string) ($this->service_type ?? ''));
        if ($stored === '') {
            return null;
        }

        return array_key_exists($stored, self::serviceTypeLabels()) ? $stored : null;
    }

    public function serviceTypeLabel(): ?string
    {
        $key = $this->serviceTypeKey();

        return $key !== null ? self::serviceTypeLabels()[$key] : null;
    }

    public function isWalkInSale(): bool
    {
        return $this->customerTypeKey() === 'mess_use';
    }

    public function isInHouseSale(): bool
    {
        return $this->customerTypeKey() === 'booking';
    }

    public function isMessBillSale(): bool
    {
        return $this->customerTypeKey() === 'ast_offr';
    }

    public function needsKitchenQueue(): bool
    {
        if ($this->kitchen_completed_at !== null) {
            return false;
        }

        if ($this->status === 'draft') {
            return true;
        }

        return $this->status === 'paid'
            && ($this->isWalkInSale() || $this->isMessBillSale());
    }

    public function kitchenStatusKey(): string
    {
        $status = (string) ($this->kitchen_status ?? '');

        if ($status === '') {
            return self::KITCHEN_STATUS_QUEUED;
        }

        return $status;
    }

    public function showsOnCafeStatusScreen(): bool
    {
        if ($this->kitchen_completed_at !== null) {
            return false;
        }

        if ($this->hasPartialKitchenServed()) {
            return true;
        }

        return in_array($this->kitchenStatusKey(), [
            self::KITCHEN_STATUS_PREPARING,
            self::KITCHEN_STATUS_READY,
        ], true);
    }

    public function kitchenServedItemsCount(): int
    {
        if (! Schema::hasColumn('pos_order_items', 'kitchen_served_at')) {
            return 0;
        }

        return $this->items->filter(fn (PosOrderItem $item) => $item->kitchen_served_at !== null)->count();
    }

    public function hasPartialKitchenServed(): bool
    {
        if (! Schema::hasColumn('pos_order_items', 'kitchen_served_at')) {
            return false;
        }

        $total = $this->items->count();
        if ($total === 0) {
            return false;
        }

        $served = $this->kitchenServedItemsCount();

        return $served > 0 && $served < $total;
    }

    public function cafeStatusLabel(): string
    {
        if ($this->hasPartialKitchenServed()) {
            return 'Preparing & Served';
        }

        return match ($this->kitchenStatusKey()) {
            self::KITCHEN_STATUS_PREPARING => 'Preparing',
            self::KITCHEN_STATUS_READY => 'Order Complete',
            default => '',
        };
    }

    public function kitchenStatusBadgeClass(): string
    {
        if ($this->hasPartialKitchenServed()) {
            return 'text-bg-info';
        }

        return match ($this->kitchenStatusKey()) {
            self::KITCHEN_STATUS_PREPARING => 'text-bg-warning',
            self::KITCHEN_STATUS_READY => 'text-bg-success',
            default => 'text-bg-secondary',
        };
    }

    public function pendingKitchenStatusLabel(): string
    {
        if (! Schema::hasColumn($this->getTable(), 'kitchen_status')) {
            return '—';
        }

        if ($this->kitchen_completed_at !== null || $this->kitchenStatusKey() === self::KITCHEN_STATUS_SERVED) {
            return 'Served';
        }

        if ($this->hasPartialKitchenServed()) {
            return 'Preparing';
        }

        return match ($this->kitchenStatusKey()) {
            self::KITCHEN_STATUS_PREPARING => 'Preparing',
            self::KITCHEN_STATUS_READY => 'Complete',
            self::KITCHEN_STATUS_QUEUED => 'Queued',
            default => ucfirst($this->kitchenStatusKey()),
        };
    }

    public function pendingKitchenStatusBadgeClass(): string
    {
        if (! Schema::hasColumn($this->getTable(), 'kitchen_status')) {
            return 'text-bg-secondary';
        }

        if ($this->kitchen_completed_at !== null || $this->kitchenStatusKey() === self::KITCHEN_STATUS_SERVED) {
            return 'text-bg-primary';
        }

        if ($this->hasPartialKitchenServed()) {
            return 'text-bg-warning';
        }

        return match ($this->kitchenStatusKey()) {
            self::KITCHEN_STATUS_PREPARING => 'text-bg-warning',
            self::KITCHEN_STATUS_READY => 'text-bg-success',
            self::KITCHEN_STATUS_SERVED => 'text-bg-primary',
            default => 'text-bg-secondary',
        };
    }

    /**
     * @return list<string>
     */
    public static function parseRoomNumbers(?string $value): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', (string) $value)),
            static fn (string $room) => $room !== ''
        ));
    }

    public static function roomNumbersOverlap(?string $left, ?string $right): bool
    {
        $leftRooms = static::parseRoomNumbers($left);
        $rightRooms = static::parseRoomNumbers($right);

        if ($leftRooms === [] || $rightRooms === []) {
            return false;
        }

        foreach ($leftRooms as $leftRoom) {
            foreach ($rightRooms as $rightRoom) {
                if (strcasecmp($leftRoom, $rightRoom) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $roomNumbers
     * @return list<string>
     */
    public static function normalizeRoomNumberList(array $roomNumbers): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn ($roomNumber) => trim((string) $roomNumber),
            $roomNumbers
        ))));
    }

    /**
     * @param  list<string>  $roomNumbers
     * @return \Illuminate\Support\Collection<int, static>
     */
    public static function inHouseCafeOrdersForRoomNumbers(array $roomNumbers, array $statuses = ['draft', 'paid']): \Illuminate\Support\Collection
    {
        $normalized = static::normalizeRoomNumberList($roomNumbers);

        if ($normalized === []) {
            return collect();
        }

        return static::query()
            ->whereIn('status', $statuses)
            ->whereNotNull('room_no')
            ->where('room_no', '!=', '')
            ->where('type', 'sale')
            ->orderByDesc('id')
            ->get([
                'id', 'order_no', 'customer_type', 'guest_name', 'room_no',
                'grand_total', 'status', 'paid_at', 'created_at', 'is_credit',
            ])
            ->filter(function (self $order) use ($normalized) {
                foreach ($normalized as $roomNumber) {
                    if (static::roomNumbersOverlap($roomNumber, $order->room_no)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    /**
     * @param  list<string>  $roomNumbers
     * @return \Illuminate\Support\Collection<int, static>
     */
    public static function pendingBookingDraftsForRoomNumbers(array $roomNumbers): \Illuminate\Support\Collection
    {
        return static::inHouseCafeOrdersForRoomNumbers($roomNumbers, ['draft']);
    }
}
