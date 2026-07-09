<?php



namespace App\Http\Controllers\GuestRoom;



use App\Http\Controllers\Controller;

use App\Models\GuestRoom;

use App\Models\PosOrder;

use App\Models\RoomBill;

use App\Models\RoomBooking;

use App\Models\RoomBookingCharge;

use App\Models\RoomBookingMember;

use App\Models\RoomBookingVehicle;

use App\Models\RoomCategory;

use App\Models\RoomPersonType;

use App\Models\RoomRate;

use Carbon\Carbon;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

use Illuminate\Validation\Rule;

use Illuminate\Validation\ValidationException;



class BookingController extends Controller

{

    public function index(Request $request)

    {

        $q = RoomBooking::query()->with(['guestRoom:id,room_number', 'assignedRooms:id,room_number', 'category:id,name']);

        if (current_company_id() === null && auth()->check() && (auth()->user()->role ?? '') === 'super_admin') {
            $q->withoutGlobalScope('company');
        }



        if ($request->filled('status')) {

            $q->where('status', $request->string('status'));

        }

        if ($request->filled('booking_type')) {

            $q->where('booking_type', $request->string('booking_type'));

        }

        if ($request->filled('guest_category')) {

            $q->where('guest_category', $request->string('guest_category'));

        }

        if ($request->filled('q')) {

            $term = '%'.$request->string('q').'%';

            $q->where(function ($w) use ($term) {

                $w->where('guest_name', 'like', $term)

                    ->orWhere('pa_no', 'like', $term)

                    ->orWhere('guest_rank', 'like', $term)

                    ->orWhere('care_of', 'like', $term)

                    ->orWhere('booking_no', 'like', $term)

                    ->orWhere('voucher_no', 'like', $term)

                    ->orWhere('guest_phone', 'like', $term);

            });

        }

        $reservedToday = $request->boolean('reserved_today');

        if ($reservedToday) {
            $q->reservedStayingToday();
        }



        $bookings = $q->latest('id')->paginate(25)->withQueryString();



        return view('guest-rooms.bookings.index', compact('bookings', 'reservedToday'));

    }



    public function create()

    {

        $categories = RoomCategory::query()->where('active', true)->orderedForRoomList()->get();

        $personTypes = RoomPersonType::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->pluck('name');

        $defaultOnlineCategory = RoomCategory::defaultOnlineCategory();



        return view('guest-rooms.bookings.create', compact('categories', 'personTypes', 'defaultOnlineCategory'));

    }



    public function store(Request $request)

    {
        normalize_request_dates($request, ['check_in_date', 'check_out_date']);
        $this->normalizeBookingRoomCategory($request);

        $validated = $this->validatedBooking($request);
        $data = $this->prepareBookingData($validated, []);

        $companyId = current_company_id() ?? auth()->user()?->company_id ?? 1;



        $booking = RoomBooking::query()->create([

            ...$data,

            'company_id' => $companyId,

            'booking_no' => RoomBooking::generateBookingNo(),

            'status' => RoomBooking::STATUS_RESERVED,

            'created_by' => auth()->id(),

            'guest_room_id' => null,

        ]);

        $this->syncBookingMembers(
            $booking,
            $request,
            (int) ($data['adults'] ?? 1),
            (int) ($data['children'] ?? 0)
        );

        $this->syncBookingVehicles($booking, $request);

        $booking->recalculateTotals();



        return redirect()->route('guest-rooms.bookings.show', $booking)->with('success', 'Booking created.');

    }



    public function show(RoomBooking $booking)

    {

        $booking->load([
            'guestRoom',
            'assignedRooms',
            'category',
            'roomRate',
            'charges',
            'bill',
            'members',
            'vehicles',
            'guestDetailChanges.changedBy:id,name',
        ]);

        if ($booking->status === RoomBooking::STATUS_CHECKED_IN
            && $booking->charges->contains(fn ($c) => $c->isDailyMattress())) {
            $booking->recalculateTotals();
            $booking->load('charges', 'bill');
        }

        $personTypes = RoomPersonType::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->pluck('name');

        $assignableRooms = $booking->status === RoomBooking::STATUS_RESERVED
            ? $this->selectableRoomsForBooking($booking)
            : collect();

        $pendingPosBills = $booking->status === RoomBooking::STATUS_CHECKED_IN
            ? PosOrder::pendingBookingDraftsForRoomNumbers($booking->activeRoomNumbers())
            : collect();

        return view('guest-rooms.bookings.show', compact('booking', 'personTypes', 'assignableRooms', 'pendingPosBills'));

    }



    public function edit(RoomBooking $booking)

    {

        if (! in_array($booking->status, [RoomBooking::STATUS_RESERVED], true)) {

            return redirect()->route('guest-rooms.bookings.show', $booking)->with('error', 'Only reserved bookings can be edited.');

        }



        $booking->load('members', 'vehicles');

        $categories = RoomCategory::query()->where('active', true)->orderedForRoomList()->get();

        $personTypes = RoomPersonType::query()->where('active', true)->orderBy('sort_order')->orderBy('name')->pluck('name');

        $defaultOnlineCategory = RoomCategory::defaultOnlineCategory();



        return view('guest-rooms.bookings.edit', compact('booking', 'categories', 'personTypes', 'defaultOnlineCategory'));

    }



    public function update(Request $request, RoomBooking $booking)

    {
        normalize_request_dates($request, ['check_in_date', 'check_out_date']);
        $this->normalizeBookingRoomCategory($request);

        if ($booking->status !== RoomBooking::STATUS_RESERVED) {

            return back()->with('error', 'Only reserved bookings can be updated.');

        }



        $data = $this->prepareBookingData($this->validatedBooking($request), []);

        if (($data['booking_type'] ?? $booking->booking_type) === RoomBooking::TYPE_ONLINE) {
            $defaultCat = RoomCategory::defaultOnlineCategory();
            if ($defaultCat) {
                $data['room_category_id'] = $defaultCat->id;
            }
        }

        $booking->update($data);

        $this->syncBookingMembers(
            $booking,
            $request,
            (int) ($data['adults'] ?? $booking->adults ?? 1),
            (int) ($data['children'] ?? $booking->children ?? 0)
        );

        $this->syncBookingVehicles($booking, $request);

        $booking->recalculateTotals();



        return redirect()->route('guest-rooms.bookings.show', $booking)->with('success', 'Booking updated.');

    }

    public function updateGuestDetails(Request $request, RoomBooking $booking)
    {
        if ($booking->status !== RoomBooking::STATUS_CHECKED_IN) {
            return back()->with('error', 'Guest details can only be corrected while the guest is checked in.');
        }

        $data = $this->validatedGuestDetails($request, $booking);
        $this->normalizeGuestIdentity($data);

        $before = $booking->guestDetailSnapshot();

        $booking->fill([
            'person_type' => $data['person_type'],
            'pa_no' => $data['pa_no'] ?? null,
            'guest_rank' => $data['guest_rank'] ?? null,
            'care_of' => $data['care_of'] ?? null,
            'guest_name' => $data['guest_name'],
            'guest_phone' => $data['guest_phone'] ?? null,
            'guest_cnic' => $data['guest_cnic'] ?? null,
            'primary_guest_staying' => $request->boolean('primary_guest_staying', true),
            'adults' => (int) $data['adults'],
            'children' => (int) ($data['children'] ?? 0),
            'vehicles_count' => max(0, min(10, (int) $request->input('vehicles_count', 0))),
            'guest_category' => $booking->booking_type === RoomBooking::TYPE_MANUAL
                ? ($data['guest_category'] ?? null)
                : $booking->guest_category,
        ]);

        $booking->save();

        $this->syncBookingMembers(
            $booking,
            $request,
            (int) $data['adults'],
            (int) ($data['children'] ?? 0)
        );

        $this->syncBookingVehicles($booking, $request);

        $logged = $booking->logGuestDetailChanges($before, $booking->guestDetailSnapshot());

        if ($logged === 0) {
            return back()->with('info', 'No changes were made.');
        }

        return back()->with('success', 'Guest details updated. '.$logged.' change(s) recorded in the log.');
    }

    public function updateGuestType(Request $request, RoomBooking $booking)
    {
        if (! in_array($booking->status, [RoomBooking::STATUS_RESERVED, RoomBooking::STATUS_CHECKED_IN], true)) {
            return back()->with('error', 'Guest type can only be changed for reserved or checked-in bookings.');
        }

        $before = $booking->guestDetailSnapshot();

        $data = $request->validate([
            'person_type' => ['required', 'string', 'max:60', Rule::exists('tenant.room_person_types', 'name')->where(function ($q) {
                $cid = current_company_id();
                if ($cid !== null) {
                    $q->where('company_id', $cid);
                }
            })],
            'guest_category' => [
                Rule::requiredIf(fn () => $booking->booking_type === RoomBooking::TYPE_MANUAL),
                'nullable',
                'string',
                Rule::in(RoomBooking::guestCategoryValues()),
            ],
            'room_category_id' => ['nullable', 'exists:tenant.room_categories,id'],
            'room_rate_id' => ['nullable', 'exists:tenant.room_rates,id'],
            'room_rent' => ['nullable', 'numeric', 'min:0'],
            'electric_charges' => ['nullable', 'numeric', 'min:0'],
            'gas_charges' => ['nullable', 'numeric', 'min:0'],
            'media_charges' => ['nullable', 'numeric', 'min:0'],
            'rate_per_night' => ['nullable', 'numeric', 'min:0'],
            'pa_no' => ['nullable', 'string', 'max:40'],
            'guest_rank' => ['nullable', 'string', 'max:60'],
            'care_of' => ['nullable', 'string', 'max:200'],
            'guest_name' => ['nullable', 'string', 'max:200'],
        ]);

        $categoryId = (int) ($data['room_category_id'] ?? $booking->room_category_id ?? 0);
        if (! $categoryId && $booking->guest_room_id) {
            $categoryId = (int) (GuestRoom::query()->find($booking->guest_room_id)?->room_category_id ?? 0);
        }
        if (! $categoryId) {
            return back()->with('error', 'No room category on this booking. Assign a room first.')->withInput();
        }

        $this->normalizeGuestIdentity($data);

        $guestCategory = $booking->booking_type === RoomBooking::TYPE_MANUAL
            ? ($data['guest_category'] ?? null)
            : null;

        $common = [
            'person_type' => $data['person_type'],
            'guest_category' => $guestCategory,
            'room_category_id' => $categoryId,
            'pa_no' => $data['pa_no'] ?? null,
            'guest_rank' => $data['guest_rank'] ?? null,
            'care_of' => $data['care_of'] ?? null,
        ];

        if ($booking->booking_type === RoomBooking::TYPE_MANUAL) {
            $roomRent = (float) ($data['room_rent'] ?? 0);
            $electric = (float) ($data['electric_charges'] ?? 0);
            $gas = (float) ($data['gas_charges'] ?? 0);
            $media = (float) ($data['media_charges'] ?? 0);
            $adjusted = RoomBooking::adjustRatesForGuestCategory(
                $guestCategory,
                $roomRent,
                $electric,
                $gas,
                $media
            );
            $booking->fill(array_merge($common, [
                'room_rate_id' => null,
                'room_rent' => $adjusted['room_rent'],
                'electric_charges' => $adjusted['electric_charges'],
                'gas_charges' => $adjusted['gas_charges'],
                'media_charges' => $adjusted['media_charges'],
                'rate_per_night' => $adjusted['rate_per_night'],
            ]));
        } else {
            $rate = $this->resolveBookingRate($data, $categoryId);
            if (! $rate) {
                return back()->with('error', 'No active rate found for this guest type and room category.')->withInput();
            }

            $adjusted = RoomBooking::adjustRatesForGuestCategory(
                $guestCategory,
                (float) $rate->room_rent,
                (float) $rate->electric_charges,
                (float) $rate->gas_charges,
                (float) $rate->media_charges
            );

            $booking->fill(array_merge(
                $this->rateFieldsFromRate($rate),
                $adjusted,
                $common
            ));
        }

        if (! empty($data['guest_name'])) {
            $booking->guest_name = $data['guest_name'];
        }

        $booking->save();
        $booking->logGuestDetailChanges($before, $booking->guestDetailSnapshot());
        $booking->recalculateTotals();

        return back()->with('success', 'Guest type and rates updated. Bill recalculated.');
    }

    public function checkIn(Request $request, RoomBooking $booking)

    {
        normalize_request_dates($request, ['check_in_date']);

        if ($booking->status !== RoomBooking::STATUS_RESERVED) {

            return back()->with('error', 'Only reserved bookings can be checked in.');

        }



        $pastLimit = now()->subYears(3)->startOfDay()->toDateString();
        $maxRooms = $this->maxAssignableRooms($booking);
        $data = $request->validate([
            'check_in_date' => [
                'required',
                'date',
                'after_or_equal:'.$pastLimit,
                'before_or_equal:'.now()->toDateString(),
            ],
            'guest_room_ids' => ['required', 'array', 'size:'.$maxRooms],
            'guest_room_ids.*' => ['integer', 'exists:tenant.guest_rooms,id'],
        ], [
            'guest_room_ids.max' => 'This booking allows at most '.$maxRooms.' room(s).',
            'guest_room_ids.required' => 'Check-in ke liye room(s) select karein.',
        ]);

        $roomIds = $this->extractRoomIds($request);
        if ($roomIds === []) {
            return back()->with('error', 'Check-in ke liye room(s) select karein.')->withInput();
        }

        if (count($roomIds) !== $maxRooms) {
            return back()->with('error', 'Is booking ke liye '.$maxRooms.' room(s) select karein.')->withInput();
        }

        if ($message = $this->roomsCountLimitMessage($booking, $roomIds)) {
            return back()->with('error', $message)->withInput();
        }

        if ($message = $this->roomAssignmentConflictMessage($booking, $roomIds)) {
            return back()->with('error', $message)->withInput();
        }

        if ($message = $this->onlineRoomRestrictionMessage($booking, $roomIds)) {
            return back()->with('error', $message)->withInput();
        }

        if ($message = $this->manualRoomRestrictionMessage($booking, $roomIds)) {
            return back()->with('error', $message)->withInput();
        }

        $checkInDay = Carbon::parse($data['check_in_date'])->startOfDay();

        if ($checkInDay->greaterThan(now()->startOfDay())) {
            return back()->with('error', 'Check-in date cannot be in the future.')->withInput();
        }

        $actualCheckIn = $checkInDay->isToday() ? now() : $checkInDay->copy()->setTime(14, 0);

        DB::connection('tenant')->transaction(function () use ($booking, $actualCheckIn, $checkInDay, $roomIds) {

            $this->syncBookingRooms($booking, $roomIds, GuestRoom::STATUS_OCCUPIED);

            $booking->update([

                'status' => RoomBooking::STATUS_CHECKED_IN,

                'check_in_date' => $checkInDay->toDateString(),

                'actual_check_in' => $actualCheckIn,

            ]);

            $booking->setAssignedRoomsStatus(GuestRoom::STATUS_OCCUPIED);

            $booking->recalculateTotals();
        });



        return back()->with('success', 'Guest checked in successfully. Running bill is ready to print.');

    }

    public function undoCheckIn(RoomBooking $booking)
    {
        if ($booking->status !== RoomBooking::STATUS_CHECKED_IN) {
            return back()->with('error', 'Only checked-in bookings can be reverted.');
        }

        if (! $booking->canUndoCheckIn()) {
            $message = 'This check-in cannot be undone.';
            if ($booking->charges()->exists()) {
                $message = 'Remove extra charges before undoing check-in.';
            } elseif ($booking->hasPartialRoomRelease()) {
                $message = 'Cannot undo after a room was released during the stay.';
            } elseif ($booking->bill && (float) $booking->bill->paid_amount > 0) {
                $message = 'Cannot undo after payments were recorded on the bill.';
            }

            return back()->with('error', $message);
        }

        DB::connection('tenant')->transaction(function () use ($booking) {
            $plannedCheckIn = $booking->bookedCheckInDate();

            $booking->bill?->delete();

            $booking->update([
                'status' => RoomBooking::STATUS_RESERVED,
                'actual_check_in' => null,
                'check_in_date' => $plannedCheckIn?->toDateString(),
            ]);

            $booking->setAssignedRoomsStatus(GuestRoom::STATUS_RESERVED);
            $booking->recalculateTotals();
        });

        return redirect()
            ->route('guest-rooms.bookings.show', $booking)
            ->with('success', 'Check-in reversed. Booking is reserved again; assigned rooms show as Reserved.');
    }

    public function changeRoomsForm(RoomBooking $booking)
    {
        if ($booking->status !== RoomBooking::STATUS_CHECKED_IN) {
            return redirect()->route('guest-rooms.bookings.show', $booking)
                ->with('error', 'Rooms can only be changed while the guest is checked in.');
        }

        $booking->load('assignedRooms');
        $rooms = $this->selectableRoomsForBooking($booking);

        return view('guest-rooms.bookings.change-rooms', compact('booking', 'rooms'));
    }

    public function updateRooms(Request $request, RoomBooking $booking)
    {
        if ($booking->status !== RoomBooking::STATUS_CHECKED_IN) {
            return back()->with('error', 'Rooms can only be changed while the guest is checked in.');
        }

        $request->validate([
            'guest_room_ids' => ['required', 'array', 'min:1'],
            'guest_room_ids.*' => ['integer', 'exists:tenant.guest_rooms,id'],
        ]);

        $roomIds = $this->extractRoomIds($request);
        if ($roomIds === []) {
            return back()->with('error', 'Select at least one room.');
        }

        $previousRoomIds = $booking->activeAssignedRoomIds();

        if ($message = $this->roomAssignmentConflictMessage($booking, $roomIds)) {
            return back()->with('error', $message);
        }

        if ($message = $this->onlineRoomRestrictionMessage($booking, $roomIds)) {
            return back()->with('error', $message);
        }

        if ($message = $this->manualRoomRestrictionMessage($booking, $roomIds)) {
            return back()->with('error', $message);
        }

        DB::connection('tenant')->transaction(function () use ($booking, $roomIds, $previousRoomIds) {
            $this->syncActiveRoomsDuringStay($booking, $roomIds, $previousRoomIds);
            $booking->recalculateTotals();
        });

        return redirect()->route('guest-rooms.bookings.show', $booking)
            ->with('success', 'Rooms updated. Released rooms billed only for nights used.');
    }

    public function releaseRoom(RoomBooking $booking, GuestRoom $guestRoom)
    {
        if ($booking->status !== RoomBooking::STATUS_CHECKED_IN) {
            return back()->with('error', 'Rooms can only be released while the guest is checked in.');
        }

        $isActive = $booking->activeAssignedRooms()
            ->where('guest_rooms.id', $guestRoom->id)
            ->exists();

        if (! $isActive) {
            return back()->with('error', 'This room is not currently active on the booking.');
        }

        if (count($booking->activeAssignedRoomIds()) <= 1) {
            return back()->with('error', 'Cannot release the only remaining room. Use checkout to end the stay.');
        }

        DB::connection('tenant')->transaction(function () use ($booking, $guestRoom) {
            $booking->markRoomReleased((int) $guestRoom->id);
            $booking->recalculateTotals();
        });

        return back()->with('success', 'Room '.$guestRoom->room_number.' released. Bill updated for partial stay.');
    }

    public function runningBillReceipt(Request $request, RoomBooking $booking)
    {
        if ($booking->status !== RoomBooking::STATUS_CHECKED_IN) {
            return redirect()->route('guest-rooms.bookings.show', $booking)
                ->with('error', 'Running bill is only available while the guest is checked in.');
        }

        $booking->recalculateTotals();
        $bill = $booking->fresh()->bill;

        if (! $bill) {
            return back()->with('error', 'Could not generate running bill.');
        }

        return redirect()->route('guest-rooms.billing.receipt', [
            'bill' => $bill,
            'print' => $request->boolean('print', true),
        ]);
    }

    public function checkoutForm(RoomBooking $booking)

    {

        if ($booking->status !== RoomBooking::STATUS_CHECKED_IN) {

            return redirect()->route('guest-rooms.bookings.show', $booking)->with('error', 'Only checked-in guests can checkout.');

        }

        return redirect()
            ->route('guest-rooms.checkout-counter.show', $booking)
            ->with('info', 'Complete bill (room + cafe) Checkout Counter se collect karein.');

    }

    public function checkoutPreview(Request $request, RoomBooking $booking)
    {
        if ($booking->status !== RoomBooking::STATUS_CHECKED_IN) {
            return response()->json(['message' => 'Invalid booking state.'], 422);
        }

        normalize_request_dates($request, ['checkout_date']);
        [$checkInMin, $checkoutMax] = $this->checkoutDateBounds($booking);

        $data = $request->validate([
            'checkout_date' => [
                'required',
                'date',
                'after_or_equal:'.$checkInMin->toDateString(),
                'before_or_equal:'.$checkoutMax->toDateString(),
            ],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $booking->load('charges');

        return response()->json(
            $booking->estimateCheckoutTotals(
                Carbon::parse($data['checkout_date'])->startOfDay(),
                isset($data['discount']) ? (float) $data['discount'] : null,
                isset($data['tax_percent']) ? (float) $data['tax_percent'] : null,
            )
        );
    }



    public function checkout(Request $request, RoomBooking $booking)

    {
        return redirect()
            ->route('guest-rooms.checkout-counter.show', $booking)
            ->with('info', 'Complete bill Checkout Counter se collect karein.');

    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function performCheckout(RoomBooking $booking, array $data): RoomBill
    {
        if ($booking->status !== RoomBooking::STATUS_CHECKED_IN) {
            throw ValidationException::withMessages([
                'booking' => 'Only checked-in guests can checkout.',
            ]);
        }

        [$checkInMin, $checkoutMax] = $this->checkoutDateBounds($booking);

        $stayCheckIn = $booking->stayCheckInAt();
        $checkoutDay = Carbon::parse($data['checkout_date'])->startOfDay();
        if ($checkoutDay->lessThan($stayCheckIn)) {
            throw ValidationException::withMessages([
                'checkout_date' => 'Checkout date cannot be before check-in.',
            ]);
        }

        $nights = max(1, (int) $stayCheckIn->diffInDays($checkoutDay) ?: 1);
        $actualCheckOut = $checkoutDay->isToday() ? now() : $checkoutDay->copy()->endOfDay();
        $actualCheckIn = $stayCheckIn->isToday()
            ? ($booking->actual_check_in ? Carbon::parse($booking->actual_check_in) : now())
            : $stayCheckIn->copy()->setTime(14, 0);

        $received = (float) ($data['amount_received'] ?? 0);

        $booking->check_out_date = $data['checkout_date'];
        $booking->nights = $nights;
        $booking->discount = (float) ($data['discount'] ?? 0);
        $booking->tax_percent = (float) ($data['tax_percent'] ?? 0);

        if (! $booking->isOnlineBooking()) {
            $booking->paid_amount = (float) ($data['advance_amount'] ?? $booking->paid_amount);
        }

        $booking->recalculateTotals();

        $balanceDue = (float) $booking->balance;
        if ($received > $balanceDue + 0.009) {
            throw ValidationException::withMessages([
                'amount_received' => 'Received amount cannot exceed room balance.',
            ]);
        }
        if ($received > 0 && empty($data['payment_method'])) {
            throw ValidationException::withMessages([
                'payment_method' => 'Select Cash or Bank for payment received.',
            ]);
        }

        return DB::connection('tenant')->transaction(function () use ($booking, $data, $received, $actualCheckOut, $actualCheckIn, $nights) {
            $paidTotal = $booking->isOnlineBooking()
                ? (float) $booking->paid_amount + $received
                : (float) ($data['advance_amount'] ?? $booking->paid_amount) + $received;

            $booking->update([
                'discount' => (float) ($data['discount'] ?? 0),
                'tax_percent' => (float) ($data['tax_percent'] ?? 0),
                'notes' => $data['notes'] ?? $booking->notes,
                'status' => RoomBooking::STATUS_CHECKED_OUT,
                'check_out_date' => $data['checkout_date'],
                'nights' => $nights,
                'actual_check_in' => $actualCheckIn,
                'actual_check_out' => $actualCheckOut,
                'paid_amount' => $paidTotal,
            ]);

            $booking->recalculateTotals();

            $booking->setAssignedRoomsStatus(GuestRoom::STATUS_CLEANING);

            $bill = RoomBill::query()->firstOrNew(['room_booking_id' => $booking->id]);

            if (! $bill->exists) {
                $bill->bill_no = RoomBill::generateBillNo();
            }

            $bill->fill([
                'room_charges' => $booking->room_charges,
                'extra_charges' => $booking->extra_charges,
                'discount' => $booking->discount,
                'tax_amount' => $booking->tax_amount,
                'total' => $booking->total_amount,
                'paid_amount' => $booking->paid_amount,
                'balance' => $booking->balance,
                'payment_method' => $data['payment_method'] ?? null,
                'payment_status' => $booking->balance <= 0 ? 'paid' : ($booking->paid_amount > 0 ? 'partial' : 'unpaid'),
                'billed_at' => $actualCheckOut,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $bill->save();

            return $bill->fresh();
        });
    }



    public function cancel(RoomBooking $booking)

    {

        if ($booking->status !== RoomBooking::STATUS_RESERVED) {

            return back()->with('error', 'Checked-in bookings cannot be cancelled. Use checkout instead.');

        }



        $booking->update(['status' => RoomBooking::STATUS_CANCELLED]);

        $allRoomIds = DB::connection('tenant')->table('room_booking_guest_room')
            ->where('room_booking_id', $booking->id)
            ->pluck('guest_room_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if ($allRoomIds !== []) {
            GuestRoom::query()->whereIn('id', $allRoomIds)->update(['status' => GuestRoom::STATUS_AVAILABLE]);
        } else {
            $booking->setAssignedRoomsStatus(GuestRoom::STATUS_AVAILABLE);
        }

        return back()->with('success', 'Booking cancelled.');

    }



    public function addCharge(Request $request, RoomBooking $booking)

    {

        if ($booking->status !== RoomBooking::STATUS_CHECKED_IN) {

            return back()->with('error', 'Charges can only be added after check-in and before checkout.');

        }



        $types = array_keys(RoomBookingCharge::allChargeTypes());

        normalize_request_dates($request, ['charge_date']);

        $data = $request->validate([

            'charge_type' => ['required', 'string', Rule::in($types)],

            'description' => ['nullable', 'string', 'max:200', 'required_if:charge_type,other'],

            'notes' => ['nullable', 'string', 'max:255'],

            'amount' => ['required', 'numeric', 'min:0.01'],

            'charge_date' => [
                'nullable',
                'date',
                Rule::requiredIf(fn () => $request->input('charge_type') === RoomBookingCharge::TYPE_MATTRESS),
            ],

        ]);

        $issueDate = $data['charge_date'] ?? now()->toDateString();
        $issueDay = Carbon::parse($issueDate)->startOfDay();
        if ($issueDay->lt($booking->stayCheckInAt())) {
            return back()->with('error', 'Mattress issue date cannot be before check-in.')->withInput();
        }
        if ($issueDay->gt($booking->mattressChargeThroughDate())) {
            return back()->with('error', 'Mattress issue date cannot be after today or checkout.')->withInput();
        }

        if ($data['charge_type'] === RoomBookingCharge::TYPE_LATE_CHECKOUT) {
            RoomBookingCharge::syncLateCheckout(
                $booking,
                (float) $data['amount'],
                $data['notes'] ?? null,
            );
            $booking->recalculateTotals();

            return $this->chargeRedirect($request, $booking)
                ->with('success', RoomBookingCharge::checkoutChargeTypes()[RoomBookingCharge::TYPE_LATE_CHECKOUT].' saved.');
        }



        $label = RoomBookingCharge::allChargeTypes()[$data['charge_type']];

        $description = $data['charge_type'] === RoomBookingCharge::TYPE_OTHER

            ? trim($data['description'] ?? '')

            : $label;

        if (! empty($data['notes'])) {

            $description .= ' — '.$data['notes'];

        }

        $unitAmount = (float) $data['amount'];
        $charge = new RoomBookingCharge([
            'room_booking_id' => $booking->id,
            'charge_type' => $data['charge_type'],
            'description' => $description,
            'unit_amount' => $data['charge_type'] === RoomBookingCharge::TYPE_MATTRESS ? $unitAmount : null,
            'amount' => $unitAmount,
            'charge_date' => $data['charge_type'] === RoomBookingCharge::TYPE_MATTRESS ? $issueDate : now()->toDateString(),
        ]);
        if ($charge->isDailyMattress()) {
            $charge->amount = $charge->calculatedAmount($booking);
        }
        $charge->save();



        $booking->recalculateTotals();



        $message = $data['charge_type'] === RoomBookingCharge::TYPE_MATTRESS
            ? $label.' added (per day from issue date until checkout).'
            : $label.' added.';

        return $this->chargeRedirect($request, $booking)->with('success', $message);

    }



    public function destroyCharge(Request $request, RoomBooking $booking, RoomBookingCharge $charge)

    {

        if ($booking->status !== RoomBooking::STATUS_CHECKED_IN) {

            return back()->with('error', 'Charges can only be removed before checkout.');

        }

        if ($charge->room_booking_id !== $booking->id) {

            abort(404);

        }



        $charge->delete();

        $booking->recalculateTotals();



        return $this->chargeRedirect($request, $booking)->with('success', 'Charge removed.');

    }



    private function chargeRedirect(Request $request, RoomBooking $booking)

    {

        $to = $request->input('redirect_to');

        if ($to === 'checkout') {

            return redirect()->route('guest-rooms.bookings.checkout', $booking);

        }

        if ($to === 'checkout-counter') {

            return redirect()->route('guest-rooms.checkout-counter.show', $booking);

        }



        return redirect()->route('guest-rooms.bookings.show', $booking);

    }



    /** @return list<int> */

    private function extractRoomIds(Request $request): array

    {

        $ids = $request->input('guest_room_ids', []);

        if (! is_array($ids)) {

            $ids = [];

        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if ($ids === [] && $request->filled('guest_room_id')) {

            $ids = [(int) $request->input('guest_room_id')];

        }



        return $ids;

    }



    private function syncBookingRooms(RoomBooking $booking, array $roomIds, string $statusForAssigned, ?array $previousRoomIds = null): void
    {
        $previousRoomIds = $previousRoomIds ?? $booking->activeAssignedRoomIds();
        $booking->syncAssignedRooms($roomIds);
        $booking->releaseRemovedRooms($previousRoomIds, $roomIds, GuestRoom::STATUS_AVAILABLE);

        if ($roomIds !== []) {
            GuestRoom::query()->whereIn('id', $roomIds)->update(['status' => $statusForAssigned]);
        }
    }

    /** @param list<int> $activeRoomIds @param list<int> $previousActiveIds */
    private function syncActiveRoomsDuringStay(RoomBooking $booking, array $activeRoomIds, array $previousActiveIds): void
    {
        $activeRoomIds = array_values(array_unique(array_filter(array_map('intval', $activeRoomIds))));
        $toRelease = array_values(array_diff($previousActiveIds, $activeRoomIds));

        foreach ($toRelease as $roomId) {
            $booking->markRoomReleased($roomId);
        }

        foreach ($activeRoomIds as $roomId) {
            $pivot = DB::connection('tenant')->table('room_booking_guest_room')
                ->where('room_booking_id', $booking->id)
                ->where('guest_room_id', $roomId)
                ->first();

            if ($pivot) {
                DB::connection('tenant')->table('room_booking_guest_room')
                    ->where('id', $pivot->id)
                    ->update(['released_at' => null, 'updated_at' => now()]);
            } else {
                $booking->assignedRooms()->attach($roomId, ['released_at' => null]);
            }

            GuestRoom::query()->whereKey($roomId)->update(['status' => GuestRoom::STATUS_OCCUPIED]);
        }

        $booking->forceFill(['guest_room_id' => $activeRoomIds[0] ?? null])->save();
    }



    private function selectableRoomsForBooking(RoomBooking $booking)
    {
        $includeIds = array_map('intval', $booking->assignedRoomIds());
        $blockedIds = $this->roomIdsBlockedDuringStay($booking);

        $query = GuestRoom::query()
            ->where('active', true)
            ->whereNotIn('status', [
                GuestRoom::STATUS_MAINTENANCE,
                GuestRoom::STATUS_CLEANING,
                GuestRoom::STATUS_OCCUPIED,
            ])
            ->where(function ($q) use ($includeIds, $blockedIds) {
                $q->whereNotIn('id', $blockedIds);
                if ($includeIds !== []) {
                    $q->orWhereIn('id', $includeIds);
                }
            });

        if ($booking->isOnlineBooking()) {
            $query->onlineBookable();
        } else {
            $query->manualCheckInSelectable();
        }

        return $query
            ->with('category')
            ->orderByCategoryThenRoom()
            ->get();
    }

    /**
     * Room IDs held by other bookings whose stay overlaps this booking's check-in / check-out.
     *
     * @param  list<int>|null  $onlyRoomIds
     * @return list<int>
     */
    private function roomIdsBlockedDuringStay(RoomBooking $booking, ?array $onlyRoomIds = null): array
    {
        if (! $booking->check_in_date || ! $booking->check_out_date) {
            return [];
        }

        $checkIn = Carbon::parse($booking->check_in_date)->toDateString();
        $checkOut = Carbon::parse($booking->check_out_date)->toDateString();

        $applyOverlap = function ($query) use ($booking, $checkIn, $checkOut) {
            $query
                ->where('b.id', '!=', $booking->id)
                ->whereIn('b.status', [RoomBooking::STATUS_RESERVED, RoomBooking::STATUS_CHECKED_IN])
                ->where('b.check_in_date', '<', $checkOut)
                ->where('b.check_out_date', '>', $checkIn);
        };

        $pivotQuery = DB::connection('tenant')
            ->table('room_booking_guest_room as p')
            ->join('room_bookings as b', 'b.id', '=', 'p.room_booking_id')
            ->whereNull('p.released_at');
        $applyOverlap($pivotQuery);

        if ($onlyRoomIds !== null && $onlyRoomIds !== []) {
            $pivotQuery->whereIn('p.guest_room_id', $onlyRoomIds);
        }

        $fromPivot = $pivotQuery->pluck('p.guest_room_id');

        $primaryQuery = DB::connection('tenant')
            ->table('room_bookings as b')
            ->whereNotNull('b.guest_room_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('room_booking_guest_room as p')
                    ->whereColumn('p.room_booking_id', 'b.id')
                    ->whereNull('p.released_at');
            });
        $applyOverlap($primaryQuery);

        if ($onlyRoomIds !== null && $onlyRoomIds !== []) {
            $primaryQuery->whereIn('b.guest_room_id', $onlyRoomIds);
        }

        $fromPrimary = $primaryQuery->pluck('b.guest_room_id');

        return $fromPivot->merge($fromPrimary)
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }



    private function validatedBooking(Request $request): array

    {
        $this->mergePrimaryGuestFromMembers($request);
        $adults = max(1, min(20, (int) $request->input('adults', 1)));
        $children = max(0, min(20, (int) $request->input('children', 0)));
        $vehiclesCount = max(0, min(10, (int) $request->input('vehicles_count', 0)));

        return $request->validate(array_merge([

            'guest_room_ids' => ['nullable', 'array'],

            'guest_room_ids.*' => ['integer', 'exists:tenant.guest_rooms,id'],

            'guest_room_id' => ['nullable', 'exists:tenant.guest_rooms,id'],

            'room_category_id' => [
                Rule::requiredIf(fn () => $request->input('booking_type', RoomBooking::TYPE_MANUAL) === RoomBooking::TYPE_MANUAL),
                'nullable',
                'exists:tenant.room_categories,id',
            ],

            'room_rate_id' => ['nullable', 'exists:tenant.room_rates,id'],

            'person_type' => ['required', 'string', 'max:60', Rule::exists('tenant.room_person_types', 'name')->where(function ($q) {

                $cid = current_company_id();

                if ($cid !== null) {

                    $q->where('company_id', $cid);

                }

            })],

            'rooms_count' => [
                'required',
                'integer',
                'min:1',
                'max:'.($request->input('booking_type', RoomBooking::TYPE_MANUAL) === RoomBooking::TYPE_ONLINE
                    ? GuestRoom::onlineBookableRoomCount()
                    : 20),
            ],

            'guest_category' => [
                Rule::requiredIf(fn () => $request->input('booking_type', RoomBooking::TYPE_MANUAL) === RoomBooking::TYPE_MANUAL),
                'nullable',
                'string',
                Rule::in(RoomBooking::guestCategoryValues()),
            ],

            'room_rent' => ['nullable', 'numeric', 'min:0'],

            'electric_charges' => ['nullable', 'numeric', 'min:0'],

            'gas_charges' => ['nullable', 'numeric', 'min:0'],

            'media_charges' => ['nullable', 'numeric', 'min:0'],

            'category_rates' => ['nullable', 'array'],

            'category_rates.*' => ['nullable', 'array'],

            'category_rates.*.room_rent' => ['nullable', 'numeric', 'min:0'],

            'category_rates.*.electric_charges' => ['nullable', 'numeric', 'min:0'],

            'category_rates.*.gas_charges' => ['nullable', 'numeric', 'min:0'],

            'category_rates.*.media_charges' => ['nullable', 'numeric', 'min:0'],

            'booking_type' => ['required', 'string', Rule::in([RoomBooking::TYPE_MANUAL, RoomBooking::TYPE_ONLINE])],

            'voucher_no' => [
                Rule::requiredIf(fn () => $request->input('booking_type', RoomBooking::TYPE_MANUAL) === RoomBooking::TYPE_ONLINE),
                'nullable',
                'string',
                'max:80',
            ],

            'pa_no' => ['nullable', 'string', 'max:40'],

            'guest_rank' => ['nullable', 'string', 'max:60'],

            'care_of' => ['nullable', 'string', 'max:200'],

            'guest_name' => ['required', 'string', 'max:200'],

            'guest_phone' => ['nullable', 'string', 'max:40'],

            'guest_email' => ['nullable', 'email', 'max:120'],

            'guest_cnic' => ['nullable', 'string', 'max:30'],

            'primary_guest_staying' => ['nullable', 'boolean'],

            'adults' => ['required', 'integer', 'min:1', 'max:20'],

            'children' => ['nullable', 'integer', 'min:0', 'max:20'],

            'vehicles_count' => ['nullable', 'integer', 'min:0', 'max:10'],

            'check_in_date' => ['required', 'date'],

            'check_out_date' => ['required', 'date', 'after:check_in_date'],

            'rate_per_night' => ['nullable', 'numeric', 'min:0'],

            'paid_amount' => ['nullable', 'numeric', 'min:0'],

            'notes' => ['nullable', 'string', 'max:500'],

        ], $this->membersValidationRules($adults, $children), $this->vehiclesValidationRules($vehiclesCount)));

    }



    /** @param list<int> $roomIds */

    private function prepareBookingData(array $data, array $roomIds = []): array

    {
        if (($data['booking_type'] ?? RoomBooking::TYPE_MANUAL) === RoomBooking::TYPE_ONLINE) {
            $data['guest_category'] = null;
            $defaultCat = RoomCategory::defaultOnlineCategory();
            if ($defaultCat) {
                $data['room_category_id'] = $defaultCat->id;
            }
        } else {
            $data['voucher_no'] = null;
        }

        $roomsMax = ($data['booking_type'] ?? RoomBooking::TYPE_MANUAL) === RoomBooking::TYPE_ONLINE
            ? GuestRoom::onlineBookableRoomCount()
            : 20;
        $data['rooms_count'] = max(1, min($roomsMax, (int) ($data['rooms_count'] ?? 1)));

        $data['primary_guest_staying'] = filter_var($data['primary_guest_staying'] ?? true, FILTER_VALIDATE_BOOLEAN);

        $data['vehicles_count'] = max(0, min(10, (int) ($data['vehicles_count'] ?? 0)));

        unset($data['guest_room_ids'], $data['vehicles']);



        if (strcasecmp((string) ($data['person_type'] ?? ''), 'Civilian') === 0) {

            $data['pa_no'] = null;

            $data['guest_rank'] = null;

        } else {

            $data['care_of'] = null;

        }



        $nights = max(1, Carbon::parse($data['check_in_date'])->diffInDays(Carbon::parse($data['check_out_date'])) ?: 1);



        $primaryRoomId = $roomIds[0] ?? ($data['guest_room_id'] ?? null);

        $data['guest_room_id'] = $primaryRoomId;



        if ($primaryRoomId) {

            $room = GuestRoom::query()->find($primaryRoomId);

            $data['room_category_id'] = $data['room_category_id'] ?? $room?->room_category_id;

        }



        $data['nights'] = $nights;

        if ($this->applyCategoryRatesFromRequest($data, $roomIds, $nights)) {
            unset($data['category_rates']);

            if (($data['booking_type'] ?? RoomBooking::TYPE_MANUAL) === RoomBooking::TYPE_ONLINE) {
                $data['paid_amount'] = $data['room_charges'];
            } elseif (RoomBooking::guestCategoryIsComplimentary($data['guest_category'] ?? null)) {
                $data['paid_amount'] = 0;
            }

            return $data;
        }

        $isOnline = ($data['booking_type'] ?? RoomBooking::TYPE_MANUAL) === RoomBooking::TYPE_ONLINE;

        $categoryId = (int) ($data['room_category_id'] ?? 0);
        $rate = $this->resolveBookingRate($data, $categoryId);

        if ($rate) {
            $adjusted = RoomBooking::adjustRatesForGuestCategory(
                $data['guest_category'] ?? null,
                (float) $rate->room_rent,
                (float) $rate->electric_charges,
                (float) $rate->gas_charges,
                (float) $rate->media_charges
            );
            $data = array_merge($data, $this->rateFieldsFromRate($rate), $adjusted);
            if (! $isOnline) {
                $data['room_rate_id'] = null;
            }
        }

        $adjusted = RoomBooking::adjustRatesForGuestCategory(
            $data['guest_category'] ?? null,
            (float) ($data['room_rent'] ?? 0),
            (float) ($data['electric_charges'] ?? 0),
            (float) ($data['gas_charges'] ?? 0),
            (float) ($data['media_charges'] ?? 0)
        );
        $data = array_merge($data, $adjusted);

        $perNight = (float) $data['rate_per_night'];
        $roomCount = max(1, count($roomIds) ?: (int) ($data['rooms_count'] ?? 1));
        $data['room_charges'] = round($perNight * $nights * $roomCount, 2);

        if ($isOnline) {
            $data['paid_amount'] = $data['room_charges'];
        } elseif (RoomBooking::guestCategoryIsComplimentary($data['guest_category'] ?? null)) {
            $data['paid_amount'] = 0;
        }

        return $data;

    }

    /** @param array<string, mixed> $data */
    private function applyCategoryRatesFromRequest(array &$data, array $roomIds, int $nights): bool
    {
        $categoryRates = $data['category_rates'] ?? null;
        if (! is_array($categoryRates) || $categoryRates === []) {
            return false;
        }

        $perNightTotal = 0.0;
        $rentSum = 0.0;
        $electricSum = 0.0;
        $gasSum = 0.0;
        $mediaSum = 0.0;
        $isOnline = ($data['booking_type'] ?? RoomBooking::TYPE_MANUAL) === RoomBooking::TYPE_ONLINE;

        $accumulateCharges = function (float $roomRent, float $electric, float $gas, float $media) use (
            &$perNightTotal,
            &$rentSum,
            &$electricSum,
            &$gasSum,
            &$mediaSum
        ): void {
            $perNightTotal += $roomRent + $electric + $gas + $media;
            $rentSum += $roomRent;
            $electricSum += $electric;
            $gasSum += $gas;
            $mediaSum += $media;
        };

        if ($roomIds !== []) {
            $rooms = GuestRoom::query()->whereIn('id', $roomIds)->get();
            foreach ($rooms as $room) {
                $cid = (string) ($room->room_category_id ?? '');
                $cr = $categoryRates[$cid] ?? $categoryRates[(int) $cid] ?? null;
                if (! is_array($cr)) {
                    continue;
                }
                if ($isOnline) {
                    $tariff = RoomRate::findForBooking((int) $cid, $data['person_type'] ?? null);
                    $adjusted = RoomBooking::adjustRatesForGuestCategory(
                        $data['guest_category'] ?? null,
                        (float) ($tariff?->room_rent ?? $cr['room_rent'] ?? 0),
                        (float) ($tariff?->electric_charges ?? $cr['electric_charges'] ?? 0),
                        (float) ($tariff?->gas_charges ?? $cr['gas_charges'] ?? 0),
                        (float) ($tariff?->media_charges ?? $cr['media_charges'] ?? 0)
                    );
                    $accumulateCharges(
                        $adjusted['room_rent'],
                        $adjusted['electric_charges'],
                        $adjusted['gas_charges'],
                        $adjusted['media_charges']
                    );
                } else {
                    $adjusted = RoomBooking::adjustRatesForGuestCategory(
                        $data['guest_category'] ?? null,
                        (float) ($cr['room_rent'] ?? 0),
                        (float) ($cr['electric_charges'] ?? 0),
                        (float) ($cr['gas_charges'] ?? 0),
                        (float) ($cr['media_charges'] ?? 0)
                    );
                    $accumulateCharges(
                        $adjusted['room_rent'],
                        $adjusted['electric_charges'],
                        $adjusted['gas_charges'],
                        $adjusted['media_charges']
                    );
                }
            }
        } else {
            foreach ($categoryRates as $roomCatId => $cr) {
                if (! is_array($cr)) {
                    continue;
                }
                if ($isOnline) {
                    $tariff = RoomRate::findForBooking((int) $roomCatId, $data['person_type'] ?? null);
                    $adjusted = RoomBooking::adjustRatesForGuestCategory(
                        $data['guest_category'] ?? null,
                        (float) ($tariff?->room_rent ?? $cr['room_rent'] ?? 0),
                        (float) ($tariff?->electric_charges ?? $cr['electric_charges'] ?? 0),
                        (float) ($tariff?->gas_charges ?? $cr['gas_charges'] ?? 0),
                        (float) ($tariff?->media_charges ?? $cr['media_charges'] ?? 0)
                    );
                    $accumulateCharges(
                        $adjusted['room_rent'],
                        $adjusted['electric_charges'],
                        $adjusted['gas_charges'],
                        $adjusted['media_charges']
                    );
                } else {
                    $adjusted = RoomBooking::adjustRatesForGuestCategory(
                        $data['guest_category'] ?? null,
                        (float) ($cr['room_rent'] ?? 0),
                        (float) ($cr['electric_charges'] ?? 0),
                        (float) ($cr['gas_charges'] ?? 0),
                        (float) ($cr['media_charges'] ?? 0)
                    );
                    $accumulateCharges(
                        $adjusted['room_rent'],
                        $adjusted['electric_charges'],
                        $adjusted['gas_charges'],
                        $adjusted['media_charges']
                    );
                }
            }
        }
        $data['room_rate_id'] = $isOnline ? ($data['room_rate_id'] ?? null) : null;
        $data['room_rent'] = round($rentSum, 2);
        $data['electric_charges'] = round($electricSum, 2);
        $data['gas_charges'] = round($gasSum, 2);
        $data['media_charges'] = round($mediaSum, 2);
        $roomsCount = max(1, (int) ($data['rooms_count'] ?? 1));
        if ($roomIds !== []) {
            $roomsCount = max(1, count($roomIds));
        }
        $data['rate_per_night'] = round($perNightTotal, 2);
        $data['room_charges'] = round($perNightTotal * $nights * $roomsCount, 2);

        return true;
    }

    private function resolveBookingRate(array $data, int $categoryId): ?RoomRate
    {
        if (! empty($data['room_rate_id'])) {
            $rate = RoomRate::query()->find($data['room_rate_id']);
            if ($rate) {
                return $rate;
            }
        }

        if ($categoryId && ! empty($data['person_type'])) {
            return RoomRate::findForBooking($categoryId, $data['person_type']);
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function rateFieldsFromRate(RoomRate $rate): array
    {
        return [
            'room_rate_id' => $rate->id,
            'person_type' => $rate->person_type,
            'room_rent' => $rate->room_rent,
            'electric_charges' => $rate->electric_charges,
            'gas_charges' => $rate->gas_charges,
            'media_charges' => $rate->media_charges,
            'rate_per_night' => $rate->total,
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public function checkoutDateBounds(RoomBooking $booking): array
    {
        $checkInMin = $booking->stayCheckInAt();
        $checkoutMax = now()->startOfDay();
        if ($booking->check_out_date) {
            $planned = Carbon::parse($booking->check_out_date)->startOfDay();
            if ($planned->greaterThan($checkoutMax)) {
                $checkoutMax = $planned;
            }
        }

        if ($checkoutMax->lessThan($checkInMin)) {
            $checkoutMax = $checkInMin->copy();
        }

        return [$checkInMin, $checkoutMax];
    }

    /** @return array<string, mixed> */
    private function validatedGuestDetails(Request $request, RoomBooking $booking): array
    {
        $this->mergePrimaryGuestFromMembers($request);
        $adults = max(1, min(20, (int) $request->input('adults', $booking->adults ?? 1)));
        $children = max(0, min(20, (int) $request->input('children', $booking->children ?? 0)));
        $vehiclesCount = max(0, min(10, (int) $request->input('vehicles_count', $booking->vehicles_count ?? 0)));

        return $request->validate(array_merge([
            'person_type' => ['required', 'string', 'max:60', Rule::exists('tenant.room_person_types', 'name')->where(function ($q) {
                $cid = current_company_id();
                if ($cid !== null) {
                    $q->where('company_id', $cid);
                }
            })],
            'guest_category' => [
                Rule::requiredIf(fn () => $booking->booking_type === RoomBooking::TYPE_MANUAL),
                'nullable',
                'string',
                Rule::in(RoomBooking::guestCategoryValues()),
            ],
            'pa_no' => ['nullable', 'string', 'max:40'],
            'guest_rank' => ['nullable', 'string', 'max:60'],
            'care_of' => ['nullable', 'string', 'max:200'],
            'guest_name' => ['required', 'string', 'max:200'],
            'guest_phone' => ['nullable', 'string', 'max:40'],
            'guest_cnic' => ['nullable', 'string', 'max:30'],
            'primary_guest_staying' => ['nullable', 'boolean'],
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['nullable', 'integer', 'min:0', 'max:20'],
            'vehicles_count' => ['nullable', 'integer', 'min:0', 'max:10'],
        ], $this->membersValidationRules($adults, $children), $this->vehiclesValidationRules($vehiclesCount)));
    }

    private function mergePrimaryGuestFromMembers(Request $request): void
    {
        if (! $request->boolean('primary_guest_staying', true)) {
            return;
        }

        $adults = $request->input('members.adults', []);
        $name = trim((string) ($request->input('guest_name') ?? ''));
        $cnic = trim((string) ($request->input('guest_cnic') ?? ''));

        if (is_array($adults) && isset($adults[0])) {
            if ($name === '') {
                $name = trim((string) ($adults[0]['name'] ?? ''));
            }
            if ($cnic === '') {
                $cnic = trim((string) ($adults[0]['cnic'] ?? ''));
            }
        }

        if ($name !== '') {
            $request->merge(['guest_name' => $name]);
        }
        if ($cnic !== '') {
            $request->merge(['guest_cnic' => $cnic]);
        }
    }

    /** @return array<string, mixed> */
    private function membersValidationRules(int $adults, int $children): array
    {
        $rules = [];
        for ($i = 0; $i < $adults; $i++) {
            $rules["members.adults.{$i}.name"] = ['nullable', 'string', 'max:200'];
            $rules["members.adults.{$i}.cnic"] = ['nullable', 'string', 'max:30'];
            $rules["members.adults.{$i}.relation"] = ['nullable', 'string', 'max:100'];
        }
        for ($i = 0; $i < $children; $i++) {
            $rules["members.children.{$i}.name"] = ['nullable', 'string', 'max:200'];
            $rules["members.children.{$i}.relation"] = ['nullable', 'string', 'max:100'];
        }

        return $rules;
    }

    private function syncBookingMembers(RoomBooking $booking, Request $request, int $adults, int $children): void
    {
        $members = $request->input('members', []);
        if (! is_array($members)) {
            $members = [];
        }

        $staying = $request->boolean('primary_guest_staying', true);

        $booking->members()->delete();

        $adultRows = array_values(is_array($members['adults'] ?? null) ? $members['adults'] : []);
        for ($i = 0; $i < $adults; $i++) {
            $row = $adultRows[$i] ?? [];
            $name = trim((string) ($row['name'] ?? ''));
            $cnic = trim((string) ($row['cnic'] ?? ''));
            if ($staying && $i === 0) {
                if ($name === '') {
                    $name = trim((string) $request->input('guest_name', ''));
                }
                if ($cnic === '') {
                    $cnic = trim((string) $request->input('guest_cnic', ''));
                }
            }
            RoomBookingMember::query()->create([
                'room_booking_id' => $booking->id,
                'member_type' => RoomBookingMember::TYPE_ADULT,
                'sort_order' => $i,
                'name' => $name,
                'cnic' => $cnic !== '' ? $cnic : null,
                'relation' => ($r = trim((string) ($row['relation'] ?? ''))) !== '' ? $r : null,
            ]);
        }

        $childRows = array_values(is_array($members['children'] ?? null) ? $members['children'] : []);
        for ($i = 0; $i < $children; $i++) {
            $row = $childRows[$i] ?? [];
            RoomBookingMember::query()->create([
                'room_booking_id' => $booking->id,
                'member_type' => RoomBookingMember::TYPE_CHILD,
                'sort_order' => $i,
                'name' => trim((string) ($row['name'] ?? '')),
                'cnic' => null,
                'relation' => ($r = trim((string) ($row['relation'] ?? ''))) !== '' ? $r : null,
            ]);
        }

        $firstAdult = $booking->members()
            ->where('member_type', RoomBookingMember::TYPE_ADULT)
            ->orderBy('sort_order')
            ->first();

        if ($staying && $firstAdult && $firstAdult->name !== '') {
            $booking->update([
                'guest_name' => $firstAdult->name,
                'guest_cnic' => $firstAdult->cnic,
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function vehiclesValidationRules(int $vehiclesCount): array
    {
        $rules = [];
        for ($i = 0; $i < $vehiclesCount; $i++) {
            $rules["vehicles.{$i}.vehicle_no"] = ['required', 'string', 'max:40'];
            $rules["vehicles.{$i}.driver_with"] = ['nullable', 'boolean'];
            $rules["vehicles.{$i}.driver_name"] = ['nullable', 'string', 'max:200'];
            $rules["vehicles.{$i}.driver_cnic"] = ['nullable', 'string', 'max:30'];
            $rules["vehicles.{$i}.driver_phone"] = ['nullable', 'string', 'max:40'];
        }

        return $rules;
    }

    private function syncBookingVehicles(RoomBooking $booking, Request $request): void
    {
        $count = max(0, min(10, (int) $request->input('vehicles_count', $booking->vehicles_count ?? 0)));
        $vehicles = $request->input('vehicles', []);
        if (! is_array($vehicles)) {
            $vehicles = [];
        }

        $vehicleRows = array_values($vehicles);

        $booking->vehicles()->delete();

        for ($i = 0; $i < $count; $i++) {
            $row = $vehicleRows[$i] ?? [];
            $vehicleNo = trim((string) ($row['vehicle_no'] ?? ''));
            $driverWith = filter_var($row['driver_with'] ?? false, FILTER_VALIDATE_BOOLEAN);

            RoomBookingVehicle::query()->create([
                'room_booking_id' => $booking->id,
                'sort_order' => $i,
                'vehicle_no' => $vehicleNo,
                'driver_accompanying' => $driverWith,
                'driver_name' => $driverWith ? (($n = trim((string) ($row['driver_name'] ?? ''))) !== '' ? $n : null) : null,
                'driver_cnic' => $driverWith ? (($c = trim((string) ($row['driver_cnic'] ?? ''))) !== '' ? $c : null) : null,
                'driver_phone' => $driverWith ? (($p = trim((string) ($row['driver_phone'] ?? ''))) !== '' ? $p : null) : null,
            ]);
        }

        if ((int) $booking->vehicles_count !== $count) {
            $booking->update(['vehicles_count' => $count]);
        }
    }

    /** @param array<string, mixed> $data */
    private function normalizeGuestIdentity(array &$data): void
    {
        if (strcasecmp((string) ($data['person_type'] ?? ''), 'Civilian') === 0) {
            $data['pa_no'] = null;
            $data['guest_rank'] = null;
        } else {
            $data['care_of'] = null;
        }
    }

    private function maxAssignableRooms(RoomBooking $booking): int
    {
        $count = max(1, (int) ($booking->rooms_count ?? 1));

        if ($booking->isOnlineBooking()) {
            $count = min($count, GuestRoom::onlineBookableRoomCount());
        }

        return $count;
    }

    /** @param list<int> $roomIds */
    private function onlineRoomRestrictionMessage(RoomBooking $booking, array $roomIds): ?string
    {
        if (! $booking->isOnlineBooking() || $roomIds === []) {
            return null;
        }

        $invalid = GuestRoom::query()
            ->whereIn('id', $roomIds)
            ->whereNotIn('room_number', GuestRoom::onlineBookableRoomNumbers())
            ->orderBy('room_number')
            ->pluck('room_number')
            ->all();

        if ($invalid === []) {
            return null;
        }

        return 'Online bookings only allow Barian Hut rooms B-1 to B-6. Not allowed: '.implode(', ', $invalid).'.';
    }

    /** @param list<int> $roomIds */
    private function manualRoomRestrictionMessage(RoomBooking $booking, array $roomIds): ?string
    {
        if ($booking->isOnlineBooking() || $roomIds === []) {
            return null;
        }

        $invalid = GuestRoom::query()
            ->whereIn('id', $roomIds)
            ->whereHas('category', fn ($q) => $q->whereIn('name', RoomCategory::barianHutCategoryNames()))
            ->whereNotIn('room_number', GuestRoom::manualBarianHutRoomNumbers())
            ->orderBy('room_number')
            ->pluck('room_number')
            ->all();

        if ($invalid === []) {
            return null;
        }

        return 'Manual check-in for Barian Hut only allows B-7 and B-8. Not allowed: '.implode(', ', $invalid).'.';
    }

    /** @param list<int> $roomIds */
    private function roomsCountLimitMessage(RoomBooking $booking, array $roomIds): ?string
    {
        $max = $this->maxAssignableRooms($booking);
        $count = count($roomIds);
        if ($count > $max) {
            return 'This booking allows at most '.$max.' room(s); you selected '.$count.'.';
        }

        return null;
    }

    /** @param list<int> $roomIds */
    private function roomAssignmentConflictMessage(RoomBooking $booking, array $roomIds): ?string
    {
        $blockedDuringStay = $this->roomIdsBlockedDuringStay($booking, $roomIds);
        if ($blockedDuringStay !== []) {
            $labels = GuestRoom::query()
                ->whereIn('id', $blockedDuringStay)
                ->orderBy('room_number')
                ->pluck('room_number')
                ->all();

            return 'Already booked for overlapping dates: '.implode(', ', $labels).'.';
        }

        $ours = $booking->activeAssignedRoomIds();
        $occupied = GuestRoom::query()
            ->whereIn('id', $roomIds)
            ->where('status', GuestRoom::STATUS_OCCUPIED)
            ->when($ours !== [], fn ($q) => $q->whereNotIn('id', $ours))
            ->orderBy('room_number')
            ->pluck('room_number')
            ->all();

        if ($occupied !== []) {
            return 'Already occupied: '.implode(', ', $occupied).'.';
        }

        return null;
    }

    private function normalizeBookingRoomCategory(Request $request): void
    {
        if ($request->input('booking_type') === RoomBooking::TYPE_ONLINE) {
            $cat = RoomCategory::defaultOnlineCategory();
            if ($cat) {
                $request->merge(['room_category_id' => $cat->id]);

                return;
            }
        }

        $fromSelect = $request->input('room_category_id');
        if ($fromSelect !== null && $fromSelect !== '') {
            return;
        }

        $ui = $request->input('room_category_select');
        if ($ui !== null && $ui !== '') {
            $request->merge(['room_category_id' => $ui]);
        }
    }

}

