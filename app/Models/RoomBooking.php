<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RoomBooking extends Model
{
    use BelongsToCompany;

    protected $connection = 'tenant';

    public const STATUS_RESERVED = 'reserved';
    public const STATUS_CHECKED_IN = 'checked_in';
    public const STATUS_CHECKED_OUT = 'checked_out';
    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_MANUAL = 'manual';

    public const TYPE_ONLINE = 'online';

    public const GUEST_CATEGORY_A = 'A';

    public const GUEST_CATEGORY_B = 'B';

    public const GUEST_CATEGORY_C = 'C';

    public const GUEST_CATEGORY_D = 'D';

    protected $fillable = [
        'company_id', 'booking_no', 'booking_type', 'voucher_no', 'guest_room_id', 'room_category_id', 'person_type', 'guest_category', 'room_type_id', 'room_rate_id',
        'pa_no', 'guest_rank', 'care_of', 'guest_name', 'guest_phone', 'guest_email', 'guest_cnic', 'primary_guest_staying',
        'adults', 'children', 'vehicles_count', 'rooms_count', 'check_in_date', 'check_out_date',
        'actual_check_in', 'actual_check_out', 'nights', 'status',
        'rate_per_night', 'room_rent', 'electric_charges', 'gas_charges', 'media_charges',
        'room_charges', 'extra_charges', 'discount',
        'tax_percent', 'tax_amount', 'total_amount', 'paid_amount', 'balance',
        'notes', 'created_by',
    ];

    protected $casts = [
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'actual_check_in' => 'datetime',
        'actual_check_out' => 'datetime',
        'rate_per_night' => 'float',
        'room_rent' => 'float',
        'electric_charges' => 'float',
        'gas_charges' => 'float',
        'media_charges' => 'float',
        'room_charges' => 'float',
        'extra_charges' => 'float',
        'discount' => 'float',
        'tax_percent' => 'float',
        'tax_amount' => 'float',
        'total_amount' => 'float',
        'paid_amount' => 'float',
        'balance' => 'float',
        'nights' => 'int',
        'adults' => 'int',
        'children' => 'int',
        'vehicles_count' => 'int',
        'rooms_count' => 'int',
        'primary_guest_staying' => 'boolean',
    ];

    public function primaryGuestIsStaying(): bool
    {
        return (bool) ($this->primary_guest_staying ?? true);
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_RESERVED => 'Reserved',
            self::STATUS_CHECKED_IN => 'Checked In',
            self::STATUS_CHECKED_OUT => 'Checked Out',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    /** Reserved bookings whose booked stay includes today. */
    public function scopeReservedStayingToday(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query
            ->where('status', self::STATUS_RESERVED)
            ->whereDate('check_in_date', '<=', $today)
            ->whereDate('check_out_date', '>=', $today);
    }

    /** Whether this booking's stay includes the given calendar day (matches dashboard "staying today" logic). */
    public function coversStayDate(mixed $date): bool
    {
        $day = Carbon::parse($date)->toDateString();
        $checkIn = Carbon::parse($this->check_in_date)->toDateString();
        $checkOut = Carbon::parse($this->check_out_date)->toDateString();

        return $day >= $checkIn && $checkOut >= $day;
    }

    /** Bookings whose stay overlaps the given check-in / check-out range (checkout day is turnover). */
    public function scopeStayOverlaps(Builder $query, mixed $checkIn, mixed $checkOut): Builder
    {
        $in = Carbon::parse($checkIn)->toDateString();
        $out = Carbon::parse($checkOut)->toDateString();

        return $query
            ->where('check_in_date', '<', $out)
            ->where('check_out_date', '>', $in);
    }

    /** @return array<string, string> */
    public static function bookingTypeLabels(): array
    {
        return [
            self::TYPE_MANUAL => 'Manual',
            self::TYPE_ONLINE => 'Online',
        ];
    }

    /** @return array<string, string> */
    public static function guestCategoryLabels(): array
    {
        return [
            self::GUEST_CATEGORY_A => 'Category A',
            self::GUEST_CATEGORY_B => 'Category B',
            self::GUEST_CATEGORY_C => 'Category C',
            self::GUEST_CATEGORY_D => 'Category D',
        ];
    }

    /** @return array<string, string> */
    public static function guestCategoryRentPolicies(): array
    {
        return [
            self::GUEST_CATEGORY_A => 'No charges — total / night is 0',
            self::GUEST_CATEGORY_B => 'No charges — total / night is 0',
            self::GUEST_CATEGORY_C => '',
            self::GUEST_CATEGORY_D => '',
        ];
    }

    public static function guestCategoryIsComplimentary(?string $guestCategory): bool
    {
        return in_array($guestCategory, [self::GUEST_CATEGORY_A, self::GUEST_CATEGORY_B], true);
    }

    /**
     * Split manual per-night total into room rent + fixed utilities (from config).
     *
     * @return array{room_rent: float, electric_charges: float, gas_charges: float, media_charges: float}
     */
    public static function splitManualPerNightTotal(float $perNightTotal): array
    {
        $split = config('app.manual_rate_split', [
            'electric' => 300,
            'gas' => 400,
            'media' => 100,
        ]);
        $electric = (float) ($split['electric'] ?? 0);
        $gas = (float) ($split['gas'] ?? 0);
        $media = (float) ($split['media'] ?? 0);
        $utilities = $electric + $gas + $media;

        return [
            'room_rent' => round(max(0, $perNightTotal - $utilities), 2),
            'electric_charges' => $electric,
            'gas_charges' => $gas,
            'media_charges' => $media,
        ];
    }

    public static function applyGuestCategoryAmount(?string $guestCategory, float $baseAmount): float
    {
        if (self::guestCategoryIsComplimentary($guestCategory)) {
            return 0.0;
        }

        return round($baseAmount, 2);
    }

    /** @return list<string> */
    public static function guestCategoryValues(): array
    {
        return array_keys(self::guestCategoryLabels());
    }

    public function guestCategoryLabel(): ?string
    {
        if (! $this->guest_category) {
            return null;
        }

        return self::guestCategoryLabels()[$this->guest_category] ?? 'Category '.$this->guest_category;
    }

    public function guestCategoryRentPolicy(): ?string
    {
        if (! $this->guest_category) {
            return null;
        }

        return self::guestCategoryRentPolicies()[$this->guest_category] ?? null;
    }

    /** @deprecated Use applyGuestCategoryAmount() — kept for compatibility. */
    public static function applyGuestCategoryRoomRent(?string $guestCategory, float $baseRoomRent): float
    {
        return self::applyGuestCategoryAmount($guestCategory, $baseRoomRent);
    }

    /**
     * @return array{room_rent: float, electric_charges: float, gas_charges: float, media_charges: float, rate_per_night: float}
     */
    public static function adjustRatesForGuestCategory(
        ?string $guestCategory,
        float $baseRoomRent,
        float $electric,
        float $gas,
        float $media
    ): array {
        $roomRent = self::applyGuestCategoryAmount($guestCategory, $baseRoomRent);
        $electricCharges = self::applyGuestCategoryAmount($guestCategory, $electric);
        $gasCharges = self::applyGuestCategoryAmount($guestCategory, $gas);
        $mediaCharges = self::applyGuestCategoryAmount($guestCategory, $media);

        return [
            'room_rent' => $roomRent,
            'electric_charges' => $electricCharges,
            'gas_charges' => $gasCharges,
            'media_charges' => $mediaCharges,
            'rate_per_night' => round($roomRent + $electricCharges + $gasCharges + $mediaCharges, 2),
        ];
    }

    public function isCivilianPersonType(): bool
    {
        return strcasecmp((string) $this->person_type, 'Civilian') === 0;
    }

    public function guestDisplayName(): string
    {
        if ($this->isCivilianPersonType()) {
            $parts = array_filter([
                $this->care_of ? 'C/O '.$this->care_of : null,
                $this->guest_name,
            ]);
        } else {
            $parts = array_filter([
                $this->pa_no ? 'PA '.$this->pa_no : null,
                $this->guest_rank,
                $this->guest_name,
            ]);
        }

        return $parts ? implode(' · ', $parts) : '—';
    }

    public static function generateBookingNo(): string
    {
        $prefix = 'BK-'.now()->format('Ymd').'-';
        $last = static::query()
            ->where('booking_no', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('booking_no');
        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function guestRoom(): BelongsTo
    {
        return $this->belongsTo(GuestRoom::class, 'guest_room_id');
    }

    public function assignedRooms(): BelongsToMany
    {
        return $this->belongsToMany(GuestRoom::class, 'room_booking_guest_room', 'room_booking_id', 'guest_room_id')
            ->withPivot('released_at')
            ->withTimestamps()
            ->orderByCategoryThenRoom();
    }

    public function activeAssignedRooms(): BelongsToMany
    {
        return $this->assignedRooms()->wherePivotNull('released_at');
    }

    /** @return list<int> */
    public function activeAssignedRoomIds(): array
    {
        if ($this->relationLoaded('assignedRooms')) {
            $ids = $this->assignedRooms
                ->filter(fn ($room) => empty($room->pivot->released_at))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        } else {
            $ids = $this->activeAssignedRooms()
                ->pluck('guest_rooms.id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        if ($ids === [] && $this->guest_room_id) {
            return [(int) $this->guest_room_id];
        }

        return $ids;
    }

    /** @return list<int> */
    public function assignedRoomIds(): array
    {
        return $this->activeAssignedRoomIds();
    }

    public function hasAssignedRooms(): bool
    {
        if ($this->activeAssignedRoomIds() !== []) {
            return true;
        }

        return (bool) $this->guest_room_id;
    }

    public function billableRoomCount(): int
    {
        $active = count($this->activeAssignedRoomIds());
        if ($active > 0) {
            return $active;
        }

        return max(1, (int) ($this->rooms_count ?? 1));
    }

    public function roomsCountLabel(): string
    {
        $n = max(1, (int) ($this->rooms_count ?? 1));

        return $n.' room'.($n === 1 ? '' : 's').' booked';
    }

    /**
     * @return list<string>
     */
    public function activeRoomNumbers(): array
    {
        if ($this->relationLoaded('assignedRooms')) {
            $numbers = $this->assignedRooms
                ->filter(fn ($room) => empty($room->pivot->released_at))
                ->pluck('room_number')
                ->filter()
                ->map(fn ($number) => (string) $number)
                ->values()
                ->all();
        } elseif ($this->relationLoaded('activeAssignedRooms')) {
            $numbers = $this->activeAssignedRooms
                ->pluck('room_number')
                ->filter()
                ->map(fn ($number) => (string) $number)
                ->values()
                ->all();
        } else {
            $numbers = $this->activeAssignedRooms()
                ->pluck('room_number')
                ->map(fn ($number) => (string) $number)
                ->values()
                ->all();
        }

        if ($numbers === [] && $this->guestRoom?->room_number) {
            return [(string) $this->guestRoom->room_number];
        }

        return $numbers;
    }

    public function roomNumbersLabel(): string
    {
        $rooms = $this->relationLoaded('assignedRooms')
            ? $this->assignedRooms
            : $this->assignedRooms()->get();

        if ($rooms->isEmpty()) {
            return $this->guestRoom?->room_number ?? '—';
        }

        $active = $rooms->filter(fn ($r) => empty($r->pivot->released_at))->pluck('room_number');
        $released = $rooms->filter(fn ($r) => ! empty($r->pivot->released_at));

        $parts = [];
        if ($active->isNotEmpty()) {
            $parts[] = $active->join(', ');
        }
        if ($released->isNotEmpty()) {
            $releasedLabels = $released->map(function ($r) {
                $date = $r->pivot->released_at
                    ? fmt_date(Carbon::parse($r->pivot->released_at), '')
                    : '';

                return $r->room_number.($date ? ' (released '.$date.')' : ' (released)');
            });
            $parts[] = $releasedLabels->join(', ');
        }

        return $parts ? implode(' · ', $parts) : '—';
    }

    public function syncAssignedRooms(array $roomIds): void
    {
        $roomIds = array_values(array_unique(array_filter(array_map('intval', $roomIds))));
        $sync = [];
        foreach ($roomIds as $id) {
            $sync[$id] = ['released_at' => null];
        }
        $this->assignedRooms()->sync($sync);
        $this->forceFill(['guest_room_id' => $roomIds[0] ?? null])->save();
    }

    /** @return list<int> */
    public function allAssignedRoomIds(): array
    {
        return $this->activeAssignedRoomIds() ?: ($this->guest_room_id ? [(int) $this->guest_room_id] : []);
    }

    public function setAssignedRoomsStatus(string $status): void
    {
        $ids = $this->activeAssignedRoomIds();
        if ($ids === [] && $this->guest_room_id) {
            $ids = [(int) $this->guest_room_id];
        }
        if ($ids === []) {
            return;
        }

        if ($status === GuestRoom::STATUS_CLEANING) {
            GuestRoom::transitionToCleaning($ids);

            return;
        }

        GuestRoom::query()->whereIn('id', $ids)->update(['status' => $status]);
    }

    public function releaseRemovedRooms(array $previousRoomIds, array $newRoomIds, string $status = GuestRoom::STATUS_AVAILABLE): void
    {
        $released = array_diff(array_map('intval', $previousRoomIds), array_map('intval', $newRoomIds));
        if ($released !== []) {
            GuestRoom::query()->whereIn('id', $released)->update(['status' => $status]);
        }
    }

    public function markRoomReleased(int $guestRoomId): void
    {
        DB::connection('tenant')
            ->table('room_booking_guest_room')
            ->where('room_booking_id', $this->id)
            ->where('guest_room_id', $guestRoomId)
            ->whereNull('released_at')
            ->update(['released_at' => now(), 'updated_at' => now()]);

        GuestRoom::query()->find($guestRoomId)?->enterCleaning();
    }

    public function calculateRoomCharges(): float
    {
        $perNight = $this->nightlyChargeTotal() > 0 ? $this->nightlyChargeTotal() : (float) $this->rate_per_night;

        if (in_array($this->status, [self::STATUS_CHECKED_IN, self::STATUS_CHECKED_OUT], true)) {
            return $this->calculateProratedRoomCharges($perNight);
        }

        $nights = max(1, (int) $this->nights);
        $assigned = count($this->activeAssignedRoomIds());
        $roomCount = $assigned > 0 ? $assigned : max(1, (int) ($this->rooms_count ?? 1));

        return round($perNight * $nights * $roomCount, 2);
    }

    /** Planned check-in from booked stay (check-out minus nights). */
    public function bookedCheckInDate(): ?Carbon
    {
        if ($this->check_out_date) {
            $nights = max(1, (int) $this->nights);

            return Carbon::parse($this->check_out_date)->subDays($nights)->startOfDay();
        }

        return $this->check_in_date
            ? Carbon::parse($this->check_in_date)->startOfDay()
            : null;
    }

    public function hasPartialRoomRelease(): bool
    {
        return DB::connection('tenant')->table('room_booking_guest_room')
            ->where('room_booking_id', $this->id)
            ->whereNotNull('released_at')
            ->exists();
    }

    public function canUndoCheckIn(): bool
    {
        if ($this->status !== self::STATUS_CHECKED_IN) {
            return false;
        }

        if ($this->charges()->exists()) {
            return false;
        }

        if ($this->hasPartialRoomRelease()) {
            return false;
        }

        if ($this->bill && (float) $this->bill->paid_amount > 0) {
            return false;
        }

        return true;
    }

    /** Stay start for billing and calendars — uses booked check-in date when set. */
    public function stayCheckInAt(): Carbon
    {
        if ($this->check_in_date) {
            return Carbon::parse($this->check_in_date)->startOfDay();
        }

        if ($this->actual_check_in) {
            return Carbon::parse($this->actual_check_in)->startOfDay();
        }

        return now()->startOfDay();
    }

    /** Last date to count daily mattress charges (inclusive). */
    public function mattressChargeThroughDate(): Carbon
    {
        if ($this->status === self::STATUS_CHECKED_OUT) {
            if ($this->actual_check_out) {
                return Carbon::parse($this->actual_check_out)->startOfDay();
            }
            if ($this->check_out_date) {
                return Carbon::parse($this->check_out_date)->startOfDay();
            }
        }

        if ($this->check_out_date) {
            $planned = Carbon::parse($this->check_out_date)->startOfDay();
            $today = now()->startOfDay();
            if ($planned->gte($today)) {
                return $planned;
            }
        }

        return now()->startOfDay();
    }

    public function sumExtraCharges(): float
    {
        $total = 0.0;
        foreach ($this->charges as $charge) {
            $charge->syncCalculatedAmount($this);
            $total += $charge->calculatedAmount($this);
        }

        return round($total, 2);
    }

    public function checkInDisplayLabel(): string
    {
        $date = fmt_date($this->stayCheckInAt(), '');
        if ($this->actual_check_in
            && $this->actual_check_in->format('Y-m-d') === $this->stayCheckInAt()->format('Y-m-d')) {
            return $date.' '.$this->actual_check_in->format('h:i A');
        }

        return $date;
    }

    public function calculateProratedRoomCharges(?float $perNight = null): float
    {
        $perNight ??= $this->nightlyChargeTotal() > 0 ? $this->nightlyChargeTotal() : (float) $this->rate_per_night;
        $checkIn = $this->stayCheckInAt();
        $checkOut = Carbon::parse($this->check_out_date)->startOfDay();
        $plannedNights = max(1, (int) $this->nights);

        $rows = DB::connection('tenant')
            ->table('room_booking_guest_room')
            ->where('room_booking_id', $this->id)
            ->get();

        if ($rows->isEmpty()) {
            return round($perNight * $plannedNights * ($this->guest_room_id ? 1 : 1), 2);
        }

        $totalRoomNights = 0;
        foreach ($rows as $row) {
            if ($row->released_at) {
                $until = Carbon::parse($row->released_at)->startOfDay();
                $totalRoomNights += max(1, (int) $checkIn->diffInDays($until));
            } else {
                $totalRoomNights += max(1, (int) $checkIn->diffInDays($checkOut));
            }
        }

        return round($perNight * $totalRoomNights, 2);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RoomCategory::class, 'room_category_id');
    }

    public function roomRate(): BelongsTo
    {
        return $this->belongsTo(RoomRate::class, 'room_rate_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(RoomBookingMember::class)->orderBy('member_type')->orderBy('sort_order');
    }

    /** @return list<array{name: string, cnic: string, relation: string}> */
    public function membersFormAdults(): array
    {
        if ($this->relationLoaded('members') && $this->members->isNotEmpty()) {
            $rows = $this->members->where('member_type', RoomBookingMember::TYPE_ADULT)->sortBy('sort_order')->values();

            return $rows->map(fn (RoomBookingMember $m) => [
                'name' => $m->name,
                'cnic' => $m->cnic ?? '',
                'relation' => $m->relation ?? '',
            ])->all();
        }

        $adults = max(1, (int) ($this->adults ?? 1));

        if (! $this->primaryGuestIsStaying()) {
            return array_fill(0, $adults, ['name' => '', 'cnic' => '', 'relation' => '']);
        }

        $result = [['name' => (string) ($this->guest_name ?? ''), 'cnic' => (string) ($this->guest_cnic ?? ''), 'relation' => 'Self']];
        for ($i = 1; $i < $adults; $i++) {
            $result[] = ['name' => '', 'cnic' => '', 'relation' => ''];
        }

        return $result;
    }

    /** @return list<array{name: string, relation: string}> */
    public function membersFormChildren(): array
    {
        if ($this->relationLoaded('members') && $this->members->isNotEmpty()) {
            $rows = $this->members->where('member_type', RoomBookingMember::TYPE_CHILD)->sortBy('sort_order')->values();

            return $rows->map(fn (RoomBookingMember $m) => [
                'name' => $m->name,
                'relation' => $m->relation ?? '',
            ])->all();
        }

        $children = max(0, (int) ($this->children ?? 0));

        return array_fill(0, $children, ['name' => '', 'relation' => '']);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(RoomBookingVehicle::class)->orderBy('sort_order');
    }

    /** @return list<array{vehicle_no: string, driver_with: bool, driver_name: string, driver_cnic: string, driver_phone: string}> */
    public function vehiclesFormData(): array
    {
        if ($this->relationLoaded('vehicles') && $this->vehicles->isNotEmpty()) {
            return $this->vehicles->sortBy('sort_order')->values()->map(fn (RoomBookingVehicle $v) => [
                'vehicle_no' => $v->vehicle_no,
                'driver_with' => (bool) $v->driver_accompanying,
                'driver_name' => $v->driver_name ?? '',
                'driver_cnic' => $v->driver_cnic ?? '',
                'driver_phone' => $v->driver_phone ?? '',
            ])->all();
        }

        $count = max(0, (int) ($this->vehicles_count ?? 0));

        return array_fill(0, $count, [
            'vehicle_no' => '',
            'driver_with' => false,
            'driver_name' => '',
            'driver_cnic' => '',
            'driver_phone' => '',
        ]);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(RoomBookingCharge::class, 'room_booking_id');
    }

    public function guestDetailChanges(): HasMany
    {
        return $this->hasMany(RoomBookingGuestChange::class, 'room_booking_id')->orderByDesc('changed_at');
    }

    /** @return array<string, string> */
    public static function guestDetailFieldLabels(): array
    {
        return [
            'person_type' => 'Guest type',
            'pa_no' => 'PA No',
            'guest_rank' => 'Rank',
            'care_of' => 'C/O',
            'guest_name' => 'Name',
            'guest_phone' => 'Phone',
            'guest_cnic' => 'CNIC',
            'primary_guest_staying' => 'Primary guest staying',
            'adults' => 'Adults',
            'children' => 'Children',
            'guest_category' => 'Guest category',
        ];
    }

    /** @return list<string> */
    public static function guestDetailTrackableFields(): array
    {
        return array_keys(self::guestDetailFieldLabels());
    }

    public function logGuestDetailChanges(array $before, array $after, ?int $userId = null): int
    {
        $userId ??= auth()->id();
        $count = 0;
        $now = now();

        foreach (self::guestDetailTrackableFields() as $field) {
            $old = self::normalizeGuestDetailValue($field, $before[$field] ?? null);
            $new = self::normalizeGuestDetailValue($field, $after[$field] ?? null);

            if ($old === $new) {
                continue;
            }

            $this->guestDetailChanges()->create([
                'room_booking_id' => $this->id,
                'field' => $field,
                'field_label' => self::guestDetailFieldLabels()[$field] ?? $field,
                'old_value' => $old,
                'new_value' => $new,
                'changed_by' => $userId,
                'changed_at' => $now,
            ]);
            $count++;
        }

        return $count;
    }

    public static function normalizeGuestDetailValue(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (in_array($field, ['adults', 'children'], true)) {
            return (string) (int) $value;
        }

        if ($field === 'primary_guest_staying') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Yes' : 'No';
        }

        return trim((string) $value);
    }

    public function guestDetailSnapshot(): array
    {
        return [
            'person_type' => $this->person_type,
            'pa_no' => $this->pa_no,
            'guest_rank' => $this->guest_rank,
            'care_of' => $this->care_of,
            'guest_name' => $this->guest_name,
            'guest_phone' => $this->guest_phone,
            'guest_cnic' => $this->guest_cnic,
            'primary_guest_staying' => $this->primary_guest_staying,
            'adults' => $this->adults,
            'children' => $this->children,
            'guest_category' => $this->guest_category,
        ];
    }

    public function bill(): HasOne
    {
        return $this->hasOne(RoomBill::class, 'room_booking_id');
    }

    public function nightlyChargeTotal(): float
    {
        return (float) $this->room_rent
            + (float) $this->electric_charges
            + (float) $this->gas_charges
            + (float) $this->media_charges;
    }

    public function isOnlineBooking(): bool
    {
        return ($this->booking_type ?? self::TYPE_MANUAL) === self::TYPE_ONLINE;
    }

    /** Room stay + tax on room (prepaid for online bookings). */
    public function onlineRoomPrepaidAmount(): float
    {
        $roomSubtotal = max(0, (float) $this->room_charges);
        $taxOnRoom = round($roomSubtotal * ((float) $this->tax_percent / 100), 2);

        return round($roomSubtotal + $taxOnRoom, 2);
    }

    /** Mattress / laundry / other — due at checkout for online bookings. */
    public function onlineExtrasDueAmount(): float
    {
        $extraSubtotal = max(0, (float) $this->extra_charges);
        $extraAfterDiscount = max(0, $extraSubtotal - (float) $this->discount);
        $taxOnExtra = round($extraAfterDiscount * ((float) $this->tax_percent / 100), 2);

        return round($extraAfterDiscount + $taxOnExtra, 2);
    }

    public function recalculateTotals(): void
    {
        $this->loadMissing('charges');
        $extra = $this->sumExtraCharges();
        $perNight = $this->nightlyChargeTotal() > 0 ? $this->nightlyChargeTotal() : (float) $this->rate_per_night;
        $this->room_charges = $this->calculateRoomCharges();
        $this->rate_per_night = $perNight;
        $this->extra_charges = $extra;
        $discount = (float) $this->discount;
        $taxPercent = (float) $this->tax_percent;

        if ($this->isOnlineBooking()) {
            $roomSubtotal = max(0, (float) $this->room_charges);
            $extraSubtotal = max(0, $extra);
            $taxOnRoom = round($roomSubtotal * ($taxPercent / 100), 2);
            $extraAfterDiscount = max(0, $extraSubtotal - $discount);
            $taxOnExtra = round($extraAfterDiscount * ($taxPercent / 100), 2);
            $roomPrepaid = round($roomSubtotal + $taxOnRoom, 2);
            $extrasDue = round($extraAfterDiscount + $taxOnExtra, 2);

            $this->tax_amount = round($taxOnRoom + $taxOnExtra, 2);
            $this->total_amount = round($roomPrepaid + $extrasDue, 2);

            if ($this->status === self::STATUS_CHECKED_OUT) {
                $paid = max($roomPrepaid, (float) $this->paid_amount);
                $this->paid_amount = $paid;
                $this->balance = max(0, round($this->total_amount - $paid, 2));
            } else {
                $this->paid_amount = $roomPrepaid;
                $this->balance = max(0, $extrasDue);
            }
        } else {
            $subtotal = max(0, (float) $this->room_charges + $extra - $discount);
            $taxAmount = round($subtotal * ($taxPercent / 100), 2);
            $total = $subtotal + $taxAmount;
            $paid = (float) $this->paid_amount;

            $this->tax_amount = $taxAmount;
            $this->total_amount = $total;
            $this->balance = max(0, $total - $paid);
        }

        $this->save();

        if ($this->status === self::STATUS_CHECKED_IN) {
            $this->syncRunningBill();
        }
    }

    /** Preview checkout totals for a date without saving (checkout form live recalc). */
    public function estimateCheckoutTotals(Carbon $checkoutDay, ?float $discount = null, ?float $taxPercent = null): array
    {
        $this->loadMissing('charges');
        $checkIn = $this->stayCheckInAt();
        $checkoutDay = $checkoutDay->copy()->startOfDay();
        $nights = max(1, (int) $checkIn->diffInDays($checkoutDay) ?: 1);
        $discount = $discount ?? (float) $this->discount;
        $taxPercent = $taxPercent ?? (float) $this->tax_percent;

        $savedCheckOut = $this->check_out_date;
        $this->check_out_date = $checkoutDay;
        $perNight = $this->nightlyChargeTotal() > 0 ? $this->nightlyChargeTotal() : (float) $this->rate_per_night;
        $roomCharges = $this->calculateProratedRoomCharges($perNight);
        $this->check_out_date = $savedCheckOut;

        $savedCheckOutForExtra = $this->check_out_date;
        $this->check_out_date = $checkoutDay;
        $extra = $this->sumExtraCharges();
        $this->check_out_date = $savedCheckOutForExtra;

        if ($this->isOnlineBooking()) {
            $roomSubtotal = max(0, $roomCharges);
            $extraSubtotal = max(0, $extra);
            $taxOnRoom = round($roomSubtotal * ($taxPercent / 100), 2);
            $extraAfterDiscount = max(0, $extraSubtotal - $discount);
            $taxOnExtra = round($extraAfterDiscount * ($taxPercent / 100), 2);
            $roomPrepaid = round($roomSubtotal + $taxOnRoom, 2);
            $extrasDue = round($extraAfterDiscount + $taxOnExtra, 2);
            $total = round($roomPrepaid + $extrasDue, 2);
            $advance = $roomPrepaid;
            $balance = max(0, $extrasDue);

            return [
                'nights' => $nights,
                'room_charges' => round($roomCharges, 2),
                'extra_charges' => round($extra, 2),
                'tax_amount' => round($taxOnRoom + $taxOnExtra, 2),
                'total' => $total,
                'advance' => $advance,
                'balance' => $balance,
                'is_online' => true,
            ];
        }

        $subtotal = max(0, $roomCharges + $extra - $discount);
        $taxAmount = round($subtotal * ($taxPercent / 100), 2);
        $total = round($subtotal + $taxAmount, 2);
        $advance = (float) $this->paid_amount;
        $balance = max(0, round($total - $advance, 2));

        return [
            'nights' => $nights,
            'room_charges' => round($roomCharges, 2),
            'extra_charges' => round($extra, 2),
            'tax_amount' => $taxAmount,
            'total' => $total,
            'advance' => $advance,
            'balance' => $balance,
            'is_online' => false,
        ];
    }

    /** Provisional bill while guest is still checked in (print / unpaid tracking). */
    public function syncRunningBill(): ?RoomBill
    {
        if ($this->status !== self::STATUS_CHECKED_IN) {
            return $this->bill;
        }

        $bill = RoomBill::query()->firstOrNew(['room_booking_id' => $this->id]);
        if (! $bill->exists) {
            $bill->bill_no = RoomBill::generateBillNo();
            $bill->created_by = auth()->id();
        }

        $total = (float) $this->total_amount;
        $paid = (float) $this->paid_amount;
        $balance = max(0, $total - $paid);
        $paymentStatus = $balance <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');

        $bill->fill([
            'room_charges' => (float) $this->room_charges,
            'extra_charges' => (float) $this->extra_charges,
            'discount' => (float) $this->discount,
            'tax_amount' => (float) $this->tax_amount,
            'total' => $total,
            'paid_amount' => $paid,
            'balance' => $balance,
            'payment_status' => $paymentStatus,
        ]);
        $bill->save();

        return $bill;
    }
}
