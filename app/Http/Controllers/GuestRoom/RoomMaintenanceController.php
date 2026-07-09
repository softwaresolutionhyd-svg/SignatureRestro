<?php

namespace App\Http\Controllers\GuestRoom;

use App\Http\Controllers\Controller;
use App\Models\GuestRoom;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class RoomMaintenanceController extends Controller
{
    public function index()
    {
        $rooms = GuestRoom::query()
            ->with('category:id,name')
            ->where('active', true)
            ->where('status', GuestRoom::STATUS_MAINTENANCE)
            ->orderByCategoryThenRoom()
            ->get();

        foreach ($rooms as $room) {
            $room->ensureMaintenanceChecklist();
        }

        return view('guest-rooms.room-maintenance.index', compact('rooms'));
    }

    public function create(Request $request)
    {
        $room = null;
        if ($request->filled('room')) {
            $room = GuestRoom::query()->with('category:id,name')->find($request->integer('room'));
        }

        $selectableRooms = GuestRoom::query()
            ->with('category:id,name')
            ->where('active', true)
            ->whereIn('status', [GuestRoom::STATUS_AVAILABLE, GuestRoom::STATUS_CLEANING])
            ->orderByCategoryThenRoom()
            ->get()
            ->filter(fn (GuestRoom $r) => $r->canEnterMaintenance());

        return view('guest-rooms.room-maintenance.create', compact('room', 'selectableRooms'));
    }

    public function store(Request $request, GuestRoom $room)
    {
        if (! $room->canEnterMaintenance()) {
            return back()->with('error', 'This room cannot be put in maintenance (occupied, reserved, or has an active booking).')->withInput();
        }

        $data = $request->validate([
            'maintenance_reason' => ['required', 'string', Rule::in(array_keys(GuestRoom::maintenanceReasonLabels()))],
            'maintenance_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $room->enterMaintenance($data['maintenance_reason'], $data['maintenance_notes'] ?? null);

        return redirect()->route('guest-rooms.room-maintenance.show', $room)
            ->with('success', 'Room '.$room->room_number.' sent to maintenance.');
    }

    public function show(GuestRoom $room)
    {
        if ($room->status !== GuestRoom::STATUS_MAINTENANCE) {
            return redirect()->route('guest-rooms.room-maintenance.index')
                ->with('error', 'This room is not in maintenance.');
        }

        $room->load('category:id,name');
        $room->ensureMaintenanceChecklist();

        return view('guest-rooms.room-maintenance.show', compact('room'));
    }

    public function update(Request $request, GuestRoom $room)
    {
        if ($room->status !== GuestRoom::STATUS_MAINTENANCE) {
            return back()->with('error', 'This room is not in maintenance.');
        }

        $tasks = array_keys(GuestRoom::maintenanceTaskLabels());
        $checked = [];
        foreach ($tasks as $task) {
            $checked[$task] = $request->boolean('checklist.'.$task);
        }

        $room->updateMaintenanceChecklist($checked);

        $expenseData = $request->validate([
            'maintenance_cost' => ['nullable', 'numeric', 'min:0'],
            'maintenance_bill_reference' => ['nullable', 'string', 'max:120'],
        ]);
        $room->forceFill([
            'maintenance_cost' => $expenseData['maintenance_cost'] ?? $room->maintenance_cost,
            'maintenance_bill_reference' => $expenseData['maintenance_bill_reference'] ?? $room->maintenance_bill_reference,
        ])->save();

        if ($request->boolean('mark_available')) {
            try {
                $room->completeMaintenance();

                return redirect()->route('guest-rooms.room-maintenance.index')
                    ->with('success', 'Room '.$room->room_number.' is now available.');
            } catch (InvalidArgumentException $e) {
                return back()->with('error', $e->getMessage())->withInput();
            }
        }

        return back()->with('success', 'Checklist saved.');
    }
}
