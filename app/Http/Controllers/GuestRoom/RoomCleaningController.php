<?php

namespace App\Http\Controllers\GuestRoom;

use App\Http\Controllers\Controller;
use App\Models\GuestRoom;
use Illuminate\Http\Request;
use InvalidArgumentException;

class RoomCleaningController extends Controller
{
    public function index()
    {
        $rooms = GuestRoom::query()
            ->with('category:id,name')
            ->where('active', true)
            ->where('status', GuestRoom::STATUS_CLEANING)
            ->orderByCategoryThenRoom()
            ->get();

        foreach ($rooms as $room) {
            $room->ensureCleaningChecklist();
        }

        return view('guest-rooms.cleaning.index', compact('rooms'));
    }

    public function show(Request $request, GuestRoom $room)
    {
        if ($room->status !== GuestRoom::STATUS_CLEANING) {
            return redirect()->route('guest-rooms.cleaning.index')
                ->with('error', 'This room is not awaiting cleaning.');
        }

        $room->load('category:id,name');
        $room->ensureCleaningChecklist();

        $fromDashboard = $request->query('from') === 'dashboard';

        return view('guest-rooms.cleaning.show', compact('room', 'fromDashboard'));
    }

    public function update(Request $request, GuestRoom $room)
    {
        if ($room->status !== GuestRoom::STATUS_CLEANING) {
            return back()->with('error', 'This room is not awaiting cleaning.');
        }

        $tasks = array_keys(GuestRoom::cleaningTaskLabels());
        $checked = [];
        foreach ($tasks as $task) {
            $checked[$task] = $request->boolean('checklist.'.$task);
        }

        $room->updateCleaningChecklist($checked);

        if ($request->boolean('mark_available')) {
            try {
                $room->completeCleaning();

                $success = 'Room '.$room->room_number.' is now available.';

                if ($request->input('return_to') === 'dashboard') {
                    return redirect()->route('guest-rooms.index')->with('success', $success);
                }

                return redirect()->route('guest-rooms.cleaning.index')
                    ->with('success', $success);
            } catch (InvalidArgumentException $e) {
                return back()->with('error', $e->getMessage())->withInput();
            }
        }

        return back()->with('success', 'Checklist saved.');
    }
}
