<?php

namespace App\Http\Controllers\GuestRoom;

use App\Http\Controllers\Controller;
use App\Models\GuestRoom;
use App\Models\RoomCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $q = GuestRoom::query()->with(['category:id,name']);

        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }
        if ($request->filled('room_category_id')) {
            $q->where('room_category_id', $request->integer('room_category_id'));
        }

        $rooms = $q->orderByCategoryThenRoom()->paginate(30)->withQueryString();
        $categories = RoomCategory::query()->where('active', true)->orderedForRoomList()->get(['id', 'name']);

        return view('guest-rooms.rooms.index', compact('rooms', 'categories'));
    }

    public function create()
    {
        $categories = RoomCategory::query()->where('active', true)->orderedForRoomList()->get(['id', 'name']);

        return view('guest-rooms.rooms.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'room_number' => ['required', 'string', 'max:30', $this->uniqueRoomNumberRule()],
            'room_category_id' => ['nullable', 'exists:tenant.room_categories,id'],
            'floor' => ['nullable', 'string', 'max:20'],
            'status' => ['required', 'in:available,occupied,reserved,cleaning'],
            'notes' => ['nullable', 'string', 'max:500'],
            'active' => ['nullable', 'boolean'],
        ], ['room_number.unique' => 'This room number already exists.']);
        $data['active'] = $request->boolean('active', true);

        GuestRoom::query()->create($data);

        return redirect()->route('guest-rooms.rooms.index')->with('success', 'Room added.');
    }

    public function edit(GuestRoom $room)
    {
        $categories = RoomCategory::query()->where('active', true)->orderedForRoomList()->get(['id', 'name']);

        return view('guest-rooms.rooms.edit', compact('room', 'categories'));
    }

    public function update(Request $request, GuestRoom $room)
    {
        $data = $request->validate([
            'room_number' => ['required', 'string', 'max:30', $this->uniqueRoomNumberRule($room->id)],
            'room_category_id' => ['nullable', 'exists:tenant.room_categories,id'],
            'floor' => ['nullable', 'string', 'max:20'],
            'status' => ['required', 'in:available,occupied,reserved,cleaning'],
            'notes' => ['nullable', 'string', 'max:500'],
            'active' => ['nullable', 'boolean'],
        ], ['room_number.unique' => 'This room number already exists.']);
        $data['active'] = $request->boolean('active', true);

        $wasCleaning = $room->status === GuestRoom::STATUS_CLEANING;
        $room->update($data);

        if ($room->status === GuestRoom::STATUS_CLEANING && ! $wasCleaning) {
            $room->enterCleaning();
        } elseif ($room->status !== GuestRoom::STATUS_CLEANING && $wasCleaning) {
            $room->forceFill(['cleaning_checklist' => null, 'cleaning_started_at' => null])->save();
        }

        return redirect()->route('guest-rooms.rooms.index')->with('success', 'Room updated.');
    }

    public function destroy(GuestRoom $room)
    {
        if ($room->bookings()->whereIn('status', ['reserved', 'checked_in'])->exists()) {
            return back()->with('error', 'Cannot delete room with active bookings.');
        }
        $room->delete();

        return redirect()->route('guest-rooms.rooms.index')->with('success', 'Room deleted.');
    }

    private function uniqueRoomNumberRule(?int $ignoreId = null)
    {
        $rule = Rule::unique('tenant.guest_rooms', 'room_number');
        $companyId = current_company_id();
        if ($companyId !== null) {
            $rule = $rule->where(fn ($query) => $query->where('company_id', $companyId));
        }
        if ($ignoreId !== null) {
            $rule = $rule->ignore($ignoreId);
        }

        return $rule;
    }
}
