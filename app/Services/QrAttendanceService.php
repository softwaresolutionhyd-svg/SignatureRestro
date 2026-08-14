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
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
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
            'title' => 'Not marked',
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

        $existing = $this->todayRecord($employee->id, $dateKey);
        if ($existing) {
            return $this->alreadyPunchedResult($employee, $existing, $tz, $dateKey);
        }

        $createdNew = false;
        $attendance = null;

        try {
            $attendance = DB::connection('tenant')->transaction(function () use ($employee, $dateKey, $now, &$createdNew) {
                $existing = $this->todayRecord($employee->id, $dateKey);
                if ($existing) {
                    return $existing;
                }

                $createdNew = true;

                return EmployeeAttendance::query()->withoutGlobalScopes()->create([
                    'company_id' => $employee->company_id,
                    'employee_id' => $employee->id,
                    'user_id' => auth()->id(),
                    'attendance_date' => $dateKey,
                    'status' => AttendancePayrollService::STATUS_PRESENT,
                    'source' => 'qr',
                    'clock_in' => $now,
                    'clock_out' => null,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            $attendance = $this->todayRecord($employee->id, $dateKey);
            $createdNew = false;
        } catch (QueryException $e) {
            $duplicate = str_contains(strtolower($e->getMessage()), 'duplicate')
                || (string) $e->getCode() === '23000';
            $attendance = $this->todayRecord($employee->id, $dateKey);
            if ($duplicate || $attendance) {
                $createdNew = false;
            } else {
                return [
                    'ok' => false,
                    'already' => false,
                    'employee' => $employee,
                    'attendance' => null,
                    'title' => 'Not marked',
                    'message' => 'Attendance save nahi ho saki. Dobara scan karein.',
                    'time' => $now->format('h:i A'),
                    'date' => $now->format(app_date_format()),
                ];
            }
        } catch (Throwable) {
            $attendance = $this->todayRecord($employee->id, $dateKey);
            if ($attendance) {
                return $this->alreadyPunchedResult($employee, $attendance, $tz, $dateKey);
            }

            return [
                'ok' => false,
                'already' => false,
                'employee' => $employee,
                'attendance' => null,
                'title' => 'Not marked',
                'message' => 'Attendance save nahi ho saki. Dobara scan karein.',
                'time' => $now->format('h:i A'),
                'date' => $now->format(app_date_format()),
            ];
        }

        if (! $createdNew || ! $attendance) {
            return $this->alreadyPunchedResult(
                $employee,
                $attendance ?: $this->todayRecord($employee->id, $dateKey),
                $tz,
                $dateKey
            );
        }

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

        return [
            'ok' => true,
            'already' => false,
            'employee' => $employee,
            'attendance' => $attendance,
            'title' => 'Present',
            'message' => $employee->name.' Present mark ho gaya.',
            'time' => $now->format('h:i A'),
            'date' => $now->format(app_date_format()),
        ];
    }

    private function todayRecord(int $employeeId, string $dateKey): ?EmployeeAttendance
    {
        return EmployeeAttendance::query()
            ->withoutGlobalScopes()
            ->where('employee_id', $employeeId)
            ->whereRaw('DATE(attendance_date) = ?', [$dateKey])
            ->first();
    }

    /**
     * @return array{
     *     ok: bool,
     *     already: bool,
     *     employee: Employee,
     *     attendance: ?EmployeeAttendance,
     *     title: string,
     *     message: string,
     *     time: string,
     *     date: string
     * }
     */
    private function alreadyPunchedResult(Employee $employee, ?EmployeeAttendance $attendance, string $tz, string $dateKey): array
    {
        $now = Carbon::now($tz);
        $clock = $attendance?->clock_in
            ? Carbon::parse($attendance->clock_in)->timezone($tz)->format('h:i A')
            : $now->format('h:i A');

        return [
            'ok' => true,
            'already' => true,
            'employee' => $employee,
            'attendance' => $attendance,
            'title' => 'Attendance already punched',
            'message' => $employee->name.' ki attendance already punch ho chuki hai ('.$clock.').',
            'time' => $clock,
            'date' => Carbon::parse($dateKey)->format(app_date_format()),
        ];
    }
}
