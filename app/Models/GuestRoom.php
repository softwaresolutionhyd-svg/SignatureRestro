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

class GuestRoom extends Model
{
    use BelongsToCompany;

    protected $connection = 'tenant';

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_OCCUPIED = 'occupied';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_CLEANING = 'cleaning';

    /** Barian Hut rooms available for online bookings only (B-1 … B-6). */
    public const ONLINE_BOOKABLE_ROOM_NUMBERS = ['B-1', 'B-2', 'B-3', 'B-4', 'B-5', 'B-6'];

    /** Barian Hut rooms for manual booking check-in only (B-7, B-8). */
    public const MANUAL_BARIAN_HUT_ROOM_NUMBERS = ['B-7', 'B-8'];

    protected $fillable = [
        'company_id', 'room_number', 'room_type_id', 'room_category_id',
        'floor', 'status', 'cleaning_checklist', 'cleaning_started_at',
        'maintenance_reason', 'maintenance_notes', 'maintenance_cost', 'maintenance_bill_reference',
        'maintenance_started_at', 'maintenance_checklist',
        'notes', 'active',
    ];

    protected $casts = [
        'active' => 'bool',
        'cleaning_checklist' => 'array',
        'cleaning_started_at' => 'datetime',
        'maintenance_checklist' => 'array',
        'maintenance_started_at' => 'datetime',
        'maintenance_cost' => 'decimal:2',
    ];

    /** @return array<string, string> */
    public static function cleaningTaskLabels(): array
    {
        return [
            'bedding' => 'Bedding changed / bed made',
            'flooring' => 'Flooring vacuumed & mopped',
            'toiletries' => 'Toiletries restocked',
            'bathroom' => 'Bathroom cleaned & sanitized',
            'surfaces' => 'Furniture & surfaces wiped',
            'general_cleaning' => 'General room cleaning completed',
        ];
    }

    public function defaultCleaningChecklist(): array
    {
        $list = [];
        foreach (array_keys(self::cleaningTaskLabels()) as $key) {
            $list[$key] = false;
        }

        return $list;
    }

    public function enterCleaning(): void
    {
        $this->forceFill([
            'status' => self::STATUS_CLEANING,
            'cleaning_checklist' => $this->defaultCleaningChecklist(),
            'cleaning_started_at' => now(),
        ])->save();
    }

    public function ensureCleaningChecklist(): void
    {
        if ($this->status !== self::STATUS_CLEANING) {
            return;
        }

        $labels = self::cleaningTaskLabels();
        $current = is_array($this->cleaning_checklist) ? $this->cleaning_checklist : [];
        $merged = [];
        foreach (array_keys($labels) as $key) {
            $merged[$key] = (bool) ($current[$key] ?? false);
        }

        if ($merged !== $current || ! $this->cleaning_started_at) {
            $this->forceFill([
                'cleaning_checklist' => $merged,
                'cleaning_started_at' => $this->cleaning_started_at ?? now(),
            ])->save();
        }
    }

    /** @param array<string, bool> $checked */
    public function updateCleaningChecklist(array $checked): void
    {
        $list = $this->defaultCleaningChecklist();
        foreach ($list as $key => $_) {
            $list[$key] = (bool) ($checked[$key] ?? false);
        }

        $this->forceFill(['cleaning_checklist' => $list])->save();
    }

    public function isCleaningChecklistComplete(): bool
    {
        $list = is_array($this->cleaning_checklist) ? $this->cleaning_checklist : [];
        foreach (array_keys(self::cleaningTaskLabels()) as $key) {
            if (empty($list[$key])) {
                return false;
            }
        }

        return $list !== [];
    }

    public function cleaningProgressPercent(): int
    {
        $labels = self::cleaningTaskLabels();
        if ($labels === []) {
            return 0;
        }

        $list = is_array($this->cleaning_checklist) ? $this->cleaning_checklist : [];
        $done = 0;
        foreach (array_keys($labels) as $key) {
            if (! empty($list[$key])) {
                $done++;
            }
        }

        return (int) round(($done / count($labels)) * 100);
    }

    public function completeCleaning(): void
    {
        if (! $this->isCleaningChecklistComplete()) {
            throw new \InvalidArgumentException('Complete all checklist items before marking the room available.');
        }

        $this->forceFill([
            'status' => self::STATUS_AVAILABLE,
            'cleaning_checklist' => null,
            'cleaning_started_at' => null,
        ])->save();
    }

    /** @param list<int> $roomIds */
    public static function transitionToCleaning(array $roomIds): void
    {
        foreach (array_filter(array_map('intval', $roomIds)) as $id) {
            static::query()->find($id)?->enterCleaning();
        }
    }

    /** @return array<string, string> */
    public static function maintenanceReasonLabels(): array
    {
        return [
            'ac_hvac' => 'AC / HVAC',
            'plumbing' => 'Plumbing',
            'electrical' => 'Electrical',
            'furniture' => 'Furniture / Fixtures',
            'painting' => 'Painting / Walls',
            'other' => 'Other',
        ];
    }

    /** @return array<string, string> */
    public static function maintenanceTaskLabels(): array
    {
        return [
            'repair_done' => 'Repair / issue resolved',
            'safety_check' => 'Safety & utilities checked',
            'cleaned' => 'Room cleaned after repair',
            'inspection' => 'Final inspection completed',
        ];
    }

    public function defaultMaintenanceChecklist(): array
    {
        $list = [];
        foreach (array_keys(self::maintenanceTaskLabels()) as $key) {
            $list[$key] = false;
        }

        return $list;
    }

    public function maintenanceReasonLabel(): ?string
    {
        if (! $this->maintenance_reason) {
            return null;
        }

        return self::maintenanceReasonLabels()[$this->maintenance_reason] ?? $this->maintenance_reason;
    }

    public function maintenanceStartedAtLabel(): ?string
    {
        return $this->formatTimestampLabel($this->maintenance_started_at);
    }

    public function cleaningStartedAtLabel(): ?string
    {
        return $this->formatTimestampLabel($this->cleaning_started_at);
    }

    protected function formatTimestampLabel(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        $at = $value instanceof \DateTimeInterface ? $value : Carbon::parse($value);

        return fmt_datetime($at, '');
    }

    public function hasActiveBooking(): bool
    {
        $onPivot = DB::connection('tenant')->table('room_booking_guest_room')
            ->join('room_bookings', 'room_bookings.id', '=', 'room_booking_guest_room.room_booking_id')
            ->where('room_booking_guest_room.guest_room_id', $this->id)
            ->whereNull('room_booking_guest_room.released_at')
            ->whereIn('room_bookings.status', ['reserved', 'checked_in'])
            ->exists();

        if ($onPivot) {
            return true;
        }

        return $this->bookings()->whereIn('status', ['reserved', 'checked_in'])->exists();
    }

    public function canEnterMaintenance(): bool
    {
        if (in_array($this->status, [self::STATUS_OCCUPIED, self::STATUS_RESERVED], true)) {
            return false;
        }

        return ! $this->hasActiveBooking();
    }

    public function enterMaintenance(string $reason, ?string $notes = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_MAINTENANCE,
            'maintenance_reason' => $reason,
            'maintenance_notes' => $notes,
            'maintenance_started_at' => now(),
            'maintenance_checklist' => $this->defaultMaintenanceChecklist(),
            'cleaning_checklist' => null,
            'cleaning_started_at' => null,
        ])->save();
    }

    public function ensureMaintenanceChecklist(): void
    {
        if ($this->status !== self::STATUS_MAINTENANCE) {
            return;
        }

        $labels = self::maintenanceTaskLabels();
        $current = is_array($this->maintenance_checklist) ? $this->maintenance_checklist : [];
        $merged = [];
        foreach (array_keys($labels) as $key) {
            $merged[$key] = (bool) ($current[$key] ?? false);
        }

        if ($merged !== $current || ! $this->maintenance_started_at) {
            $this->forceFill([
                'maintenance_checklist' => $merged,
                'maintenance_started_at' => $this->maintenance_started_at ?? now(),
            ])->save();
        }
    }

    /** @param array<string, bool> $checked */
    public function updateMaintenanceChecklist(array $checked): void
    {
        $list = $this->defaultMaintenanceChecklist();
        foreach ($list as $key => $_) {
            $list[$key] = (bool) ($checked[$key] ?? false);
        }

        $this->forceFill(['maintenance_checklist' => $list])->save();
    }

    public function isMaintenanceChecklistComplete(): bool
    {
        $list = is_array($this->maintenance_checklist) ? $this->maintenance_checklist : [];
        foreach (array_keys(self::maintenanceTaskLabels()) as $key) {
            if (empty($list[$key])) {
                return false;
            }
        }

        return $list !== [];
    }

    public function maintenanceProgressPercent(): int
    {
        $labels = self::maintenanceTaskLabels();
        if ($labels === []) {
            return 0;
        }

        $list = is_array($this->maintenance_checklist) ? $this->maintenance_checklist : [];
        $done = 0;
        foreach (array_keys($labels) as $key) {
            if (! empty($list[$key])) {
                $done++;
            }
        }

        return (int) round(($done / count($labels)) * 100);
    }

    public function completeMaintenance(): void
    {
        if (! $this->isMaintenanceChecklistComplete()) {
            throw new \InvalidArgumentException('Complete all checklist items before marking the room available.');
        }

        $this->forceFill([
            'status' => self::STATUS_AVAILABLE,
            'maintenance_reason' => null,
            'maintenance_notes' => null,
            'maintenance_started_at' => null,
            'maintenance_checklist' => null,
        ])->save();
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_AVAILABLE => 'Available',
            self::STATUS_OCCUPIED => 'Occupied',
            self::STATUS_RESERVED => 'Reserved',
            self::STATUS_CLEANING => 'Cleaning',
        ];
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_OCCUPIED => 'danger',
            self::STATUS_AVAILABLE => 'success',
            self::STATUS_RESERVED => 'info',
            self::STATUS_CLEANING => 'warning',
            default => 'secondary',
        };
    }

    public function statusTableClass(): string
    {
        return match ($this->status) {
            self::STATUS_OCCUPIED => 'table-danger',
            self::STATUS_AVAILABLE => 'table-success',
            self::STATUS_RESERVED => 'table-info',
            self::STATUS_CLEANING => 'table-warning',
            default => '',
        };
    }

    /** Dashboard: future reserved rooms show as available until stay includes today. */
    public function dashboardDisplayStatus(array $todayReservedRoomIds = []): string
    {
        if (in_array((int) $this->id, $todayReservedRoomIds, true)) {
            return self::STATUS_RESERVED;
        }

        if (in_array($this->status, [self::STATUS_OCCUPIED, self::STATUS_CLEANING], true)) {
            return (string) $this->status;
        }

        return self::STATUS_AVAILABLE;
    }

    public function dashboardStatusLabel(array $todayReservedRoomIds = []): string
    {
        $status = $this->dashboardDisplayStatus($todayReservedRoomIds);

        return self::statusLabels()[$status] ?? $status;
    }

    public function dashboardStatusBadgeClass(array $todayReservedRoomIds = []): string
    {
        return match ($this->dashboardDisplayStatus($todayReservedRoomIds)) {
            self::STATUS_OCCUPIED => 'danger',
            self::STATUS_AVAILABLE => 'success',
            self::STATUS_RESERVED => 'info',
            self::STATUS_CLEANING => 'warning',
            default => 'secondary',
        };
    }

    public function dashboardStatusTableClass(array $todayReservedRoomIds = []): string
    {
        return match ($this->dashboardDisplayStatus($todayReservedRoomIds)) {
            self::STATUS_OCCUPIED => 'table-danger',
            self::STATUS_AVAILABLE => 'table-success',
            self::STATUS_RESERVED => 'table-info',
            self::STATUS_CLEANING => 'table-warning',
            default => '',
        };
    }

    public function statusTileClass(): string
    {
        return 'gr-room-tile--' . ($this->status ?: 'unknown');
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class, 'room_type_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RoomCategory::class, 'room_category_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(RoomBooking::class, 'guest_room_id');
    }

    public function roomBookings(): BelongsToMany
    {
        return $this->belongsToMany(RoomBooking::class, 'room_booking_guest_room', 'guest_room_id', 'room_booking_id')
            ->withTimestamps();
    }

    /** @return list<string> */
    public static function onlineBookableRoomNumbers(): array
    {
        return self::ONLINE_BOOKABLE_ROOM_NUMBERS;
    }

    public static function onlineBookableRoomCount(): int
    {
        return count(self::ONLINE_BOOKABLE_ROOM_NUMBERS);
    }

    /** @return list<string> */
    public static function manualBarianHutRoomNumbers(): array
    {
        return self::MANUAL_BARIAN_HUT_ROOM_NUMBERS;
    }

    public function isOnlineBookable(): bool
    {
        return in_array($this->room_number, self::ONLINE_BOOKABLE_ROOM_NUMBERS, true);
    }

    public function scopeOnlineBookable(Builder $query): Builder
    {
        return $query->whereIn(
            $query->getModel()->getTable().'.room_number',
            self::ONLINE_BOOKABLE_ROOM_NUMBERS
        );
    }

    public function scopeManualCheckInSelectable(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();
        $hutNames = RoomCategory::barianHutCategoryNames();
        $manualHutRooms = self::MANUAL_BARIAN_HUT_ROOM_NUMBERS;

        return $query->where(function ($q) use ($table, $hutNames, $manualHutRooms) {
            $q->whereNotIn($table.'.room_category_id', function ($sub) use ($hutNames) {
                $sub->select('id')->from('room_categories')->whereIn('name', $hutNames);
            })->orWhere(function ($q2) use ($table, $hutNames, $manualHutRooms) {
                $q2->whereIn($table.'.room_category_id', function ($sub) use ($hutNames) {
                    $sub->select('id')->from('room_categories')->whereIn('name', $hutNames);
                })->whereIn($table.'.room_number', $manualHutRooms);
            });
        });
    }

    public function scopeOrderByCategoryThenRoom(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();
        $case = RoomCategory::categoryNameOrderSql('rc_sort.name');

        return $query
            ->orderByRaw(
                'COALESCE((SELECT '.$case.' FROM room_categories rc_sort WHERE rc_sort.id = '.$table.'.room_category_id LIMIT 1), 99)'
            )
            ->orderBy($table.'.room_number');
    }

    /**
     * Rooms blocked by reserved / checked-in bookings overlapping a stay range (checkout day is turnover).
     *
     * @return array<int, array{booking_id: int, booking_no: string, guest: string, check_in: mixed, check_out: mixed, status: string, url: string}>
     */
    public static function bookingBlocksByRoomForDateRange(string $checkIn, string $checkOut): array
    {
        $checkIn = Carbon::parse($checkIn)->toDateString();
        $checkOut = Carbon::parse($checkOut)->toDateString();

        $bookings = RoomBooking::query()
            ->whereIn('status', [RoomBooking::STATUS_RESERVED, RoomBooking::STATUS_CHECKED_IN])
            ->where('check_in_date', '<', $checkOut)
            ->where('check_out_date', '>', $checkIn)
            ->get();

        $map = [];
        foreach ($bookings as $booking) {
            $info = [
                'booking_id' => (int) $booking->id,
                'booking_no' => $booking->booking_no,
                'guest' => $booking->guestDisplayName(),
                'check_in' => $booking->check_in_date,
                'check_out' => $booking->check_out_date,
                'status' => $booking->status,
                'url' => route('guest-rooms.bookings.show', $booking),
            ];
            foreach ($booking->activeAssignedRoomIds() as $roomId) {
                $map[(int) $roomId] ??= $info;
            }
        }

        return $map;
    }

    /**
     * Flight-style availability: free rooms for check-in → check-out (optional category filter).
     *
     * @return array{
     *     check_in: Carbon,
     *     check_out: Carbon,
     *     nights: int,
     *     category_id: int|null,
     *     available: \Illuminate\Support\Collection<int, self>,
     *     unavailable: \Illuminate\Support\Collection<int, array{room: self, reason: string, block: array|null}>
     * }
     */
    public static function searchAvailability(string $checkIn, string $checkOut, ?int $categoryId = null): array
    {
        $checkInDay = Carbon::parse($checkIn)->startOfDay();
        $checkOutDay = Carbon::parse($checkOut)->startOfDay();
        $nights = max(1, (int) $checkInDay->diffInDays($checkOutDay) ?: 1);

        $query = static::query()
            ->with('category:id,name')
            ->where('active', true);

        if ($categoryId) {
            $query->where('room_category_id', $categoryId);
        }

        $rooms = $query->orderByCategoryThenRoom()->get();
        $blocks = self::bookingBlocksByRoomForDateRange($checkInDay->toDateString(), $checkOutDay->toDateString());

        $available = collect();
        $unavailable = collect();

        foreach ($rooms as $room) {
            if ($room->status === self::STATUS_MAINTENANCE) {
                $unavailable->push([
                    'room' => $room,
                    'reason' => 'maintenance',
                    'block' => null,
                ]);

                continue;
            }

            if (isset($blocks[$room->id])) {
                $unavailable->push([
                    'room' => $room,
                    'reason' => 'booked',
                    'block' => $blocks[$room->id],
                ]);

                continue;
            }

            $available->push($room);
        }

        return [
            'check_in' => $checkInDay,
            'check_out' => $checkOutDay,
            'nights' => $nights,
            'category_id' => $categoryId,
            'available' => $available,
            'unavailable' => $unavailable,
        ];
    }
}
