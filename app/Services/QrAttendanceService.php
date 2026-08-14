<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Support\ActivityLogger;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class QrAttendanceService
{
    public function checkInUrl(Employee $employee): string
    {
        $employee->ensureQrToken();

        return route('attendance.qr.checkin', ['token' => $employee->qr_token], true);
    }

    public function svgForUrl(string $url, int $size = 220): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd()
        );

        $svg = (new Writer($renderer))->writeString($url);

        return (string) preg_replace('/^<\?xml[^>]*>\s*/i', '', $svg);
    }

    public function svgForEmployee(Employee $employee, int $size = 220): string
    {
        return $this->svgForUrl($this->checkInUrl($employee), $size);
    }

    /**
     * @return array{
     *     ok: bool,
     *     already: bool,
     *     employee: ?Employee,
     *     attendance: ?EmployeeAttendance,
     *     message: string,
     *     time: string,
     *     date: string
     * }
     */
    public function markPresentByToken(string $token): array
    {
        Employee::ensureQrTokenSchema();

        $token = strtolower(trim($token));
        $empty = [
            'ok' => false,
            'already' => false,
            'employee' => null,
            'attendance' => null,
            'message' => 'QR code invalid hai.',
            'time' => now()->timezone(config('app.timezone'))->format('h:i A'),
            'date' => now()->timezone(config('app.timezone'))->format(app_date_format()),
        ];

        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return $empty;
        }

        if (config('sync.role') === 'cloud' && config('sync.cloud_read_only', true)) {
            return array_merge($empty, [
                'message' => 'Online server view-only hai. QR attendance cafe (local) PC par scan karein.',
            ]);
        }

        $employee = Employee::query()
            ->withoutGlobalScopes()
            ->where('qr_token', $token)
            ->first();

        if (! $employee) {
            return array_merge($empty, ['message' => 'Ye QR kisi employee se match nahi hua.']);
        }

        if ($employee->isAdminAccount()) {
            return array_merge($empty, [
                'employee' => $employee,
                'message' => 'Admin account ki QR attendance nahi lagti.',
            ]);
        }

        if (! $employee->active) {
            return array_merge($empty, [
                'employee' => $employee,
                'message' => $employee->name.' inactive hai — attendance nahi lagi.',
            ]);
        }

        return $this->markPresent($employee);
    }

    /**
     * @return array{
     *     ok: bool,
     *     already: bool,
     *     employee: Employee,
     *     attendance: ?EmployeeAttendance,
     *     message: string,
     *     time: string,
     *     date: string
     * }
     */
    public function markPresent(Employee $employee): array
    {
        $tz = (string) config('app.timezone');
        $now = Carbon::now($tz);
        $dateKey = $now->toDateString();
        $timeLabel = $now->format('h:i A');
        $dateLabel = $now->format(app_date_format());

        $attendance = null;
        $already = false;

        DB::connection('tenant')->transaction(function () use ($employee, $dateKey, $now, &$attendance, &$already) {
            $existing = EmployeeAttendance::query()
                ->withoutGlobalScopes()
                ->where('employee_id', $employee->id)
                ->whereDate('attendance_date', $dateKey)
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->status === AttendancePayrollService::STATUS_PRESENT) {
                $already = true;
                $attendance = $existing;

                return;
            }

            $attendance = EmployeeAttendance::query()->withoutGlobalScopes()->updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'attendance_date' => $dateKey,
                ],
                [
                    'company_id' => $employee->company_id,
                    'user_id' => auth()->id(),
                    'status' => AttendancePayrollService::STATUS_PRESENT,
                    'source' => 'qr',
                    'clock_in' => $existing?->clock_in ?? $now,
                    'clock_out' => null,
                    'notes' => $existing?->notes,
                ]
            );
        });

        if (! $already) {
            try {
                app(PayrollSalaryService::class)->syncPayrollEntryForEmployee(
                    $employee,
                    $now->format('Y-m'),
                    auth()->id()
                );
            } catch (Throwable) {
            }

            if (config('sync.enabled') && config('sync.role') === 'local') {
                try {
                    app(\App\Services\Sync\SyncPayrollQueueService::class)->queuePayrollData($now->format('Y-m'));
                } catch (Throwable) {
                }
            }

            ActivityLogger::log('attendance.qr_present', 'QR attendance present', $employee, [
                'employee_no' => $employee->employee_no,
                'date' => $dateKey,
            ]);
        }

        $clock = $attendance?->clock_in
            ? Carbon::parse($attendance->clock_in)->timezone($tz)->format('h:i A')
            : $timeLabel;

        if ($already) {
            return [
                'ok' => true,
                'already' => true,
                'employee' => $employee,
                'attendance' => $attendance,
                'message' => $employee->name.' aaj pehle se Present hai ('.$clock.').',
                'time' => $clock,
                'date' => $dateLabel,
            ];
        }

        return [
            'ok' => true,
            'already' => false,
            'employee' => $employee,
            'attendance' => $attendance,
            'message' => $employee->name.' Present mark ho gaya.',
            'time' => $clock,
            'date' => $dateLabel,
        ];
    }
}
