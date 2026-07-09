<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CalendarController extends Controller
{
    /** Main calendar page */
    public function index()
    {
        $typeLabels = CalendarEvent::$typeLabels;
        $typeColors = CalendarEvent::$typeColors;

        // Upcoming events for sidebar
        $upcoming = CalendarEvent::where('start_datetime', '>=', now())
            ->orderBy('start_datetime')
            ->limit(8)
            ->get();

        return view('calendar.index', compact('typeLabels', 'typeColors', 'upcoming'));
    }

    /** JSON feed for FullCalendar */
    public function feed(Request $request): JsonResponse
    {
        $start = $request->query('start');
        $end   = $request->query('end');

        $query = CalendarEvent::query();

        if ($start) {
            $query->where('end_datetime', '>=', $start);
        }
        if ($end) {
            $query->where('start_datetime', '<=', $end);
        }

        $events = $query->with('creator')->get()->map(fn ($e) => $e->toFcEvent());

        return response()->json($events);
    }

    /** Store new event (AJAX) */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'          => 'required|string|max:200',
            'description'    => 'nullable|string',
            'location'       => 'nullable|string|max:255',
            'start_datetime' => 'required|date',
            'end_datetime'   => 'required|date|after_or_equal:start_datetime',
            'all_day'        => 'nullable|boolean',
            'event_type'     => 'required|in:meeting,task,holiday,reminder,other',
            'color'          => 'nullable|string|max:20',
        ]);

        $data['created_by'] = Auth::id();
        $data['all_day']    = $request->boolean('all_day');

        // Auto-assign colour if not provided or use event_type default
        if (empty($data['color'])) {
            $data['color'] = CalendarEvent::$typeColors[$data['event_type']] ?? '#7c3aed';
        }

        $event = CalendarEvent::create($data);

        return response()->json([
            'success' => true,
            'event'   => $event->load('creator')->toFcEvent(),
        ]);
    }

    /** Return single event for editing */
    public function show(CalendarEvent $calendar): JsonResponse
    {
        return response()->json($calendar->load('creator'));
    }

    /** Update event (AJAX — also handles drag & resize) */
    public function update(Request $request, CalendarEvent $calendar): JsonResponse
    {
        $data = $request->validate([
            'title'          => 'sometimes|required|string|max:200',
            'description'    => 'nullable|string',
            'location'       => 'nullable|string|max:255',
            'start_datetime' => 'sometimes|required|date',
            'end_datetime'   => 'sometimes|required|date|after_or_equal:start_datetime',
            'all_day'        => 'nullable|boolean',
            'event_type'     => 'sometimes|required|in:meeting,task,holiday,reminder,other',
            'color'          => 'nullable|string|max:20',
        ]);

        if (isset($data['all_day'])) {
            $data['all_day'] = $request->boolean('all_day');
        }

        $calendar->update($data);

        return response()->json([
            'success' => true,
            'event'   => $calendar->fresh('creator')->toFcEvent(),
        ]);
    }

    /** Delete event (AJAX) */
    public function destroy(CalendarEvent $calendar): JsonResponse
    {
        $calendar->delete();
        return response()->json(['success' => true]);
    }
}
