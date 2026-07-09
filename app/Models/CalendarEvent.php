<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEvent extends Model
{
    protected $connection = 'tenant';

    use BelongsToCompany;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'title', 'description', 'location',
        'start_datetime', 'end_datetime', 'all_day',
        'event_type', 'color', 'created_by',
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime'   => 'datetime',
        'all_day'        => 'bool',
    ];

    // Default colours per event type
    public static array $typeColors = [
        'meeting'  => '#7c3aed',
        'task'     => '#0ea5e9',
        'holiday'  => '#22c55e',
        'reminder' => '#f97316',
        'other'    => '#64748b',
    ];

    public static array $typeLabels = [
        'meeting'  => 'Meeting',
        'task'     => 'Task',
        'holiday'  => 'Holiday',
        'reminder' => 'Reminder',
        'other'    => 'Other',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Convert to FullCalendar event object */
    public function toFcEvent(): array
    {
        return [
            'id'              => $this->id,
            'title'           => $this->title,
            'start'           => $this->all_day
                                    ? $this->start_datetime->toDateString()
                                    : $this->start_datetime->toIso8601String(),
            'end'             => $this->all_day
                                    ? $this->end_datetime->copy()->addDay()->toDateString()
                                    : $this->end_datetime->toIso8601String(),
            'allDay'          => $this->all_day,
            'color'           => $this->color,
            'extendedProps'   => [
                'description' => $this->description,
                'location'    => $this->location,
                'event_type'  => $this->event_type,
                'created_by'  => $this->creator?->name,
            ],
        ];
    }
}
