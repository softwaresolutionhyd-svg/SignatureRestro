<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Setting;
use App\Services\QrAttendanceService;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class QrAttendanceController extends Controller
{
    public function __construct(
        private readonly QrAttendanceService $qrAttendance
    ) {}

    public function checkIn(Request $request, string $token): JsonResponse|View
    {
        try {
            $result = $this->qrAttendance->markPresentByToken($token);
        } catch (\Throwable) {
            $result = [
                'ok' => false,
                'already' => false,
                'employee' => null,
                'attendance' => null,
                'title' => 'Not marked',
                'message' => 'Attendance save nahi ho saki. Dobara scan karein.',
                'time' => now()->format('h:i A'),
                'date' => now()->format(app_date_format()),
            ];
        }

        if ($request->expectsJson() || $request->query('format') === 'json') {
            $employee = $result['employee'] ?? null;

            return response()->json([
                'ok' => (bool) ($result['ok'] ?? false),
                'already' => (bool) ($result['already'] ?? false),
                'title' => $result['title'] ?? (($result['already'] ?? false) ? 'Attendance already punched' : 'Present'),
                'message' => $result['message'] ?? '',
                'time' => $result['time'] ?? '',
                'date' => $result['date'] ?? '',
                'employee' => $employee ? [
                    'name' => $employee->name,
                    'employee_no' => $employee->employee_no,
                    'photo_url' => $employee->photoUrl(),
                ] : null,
            ], ($result['ok'] ?? false) || ($result['already'] ?? false) ? 200 : 422);
        }

        return view('employees.qr-checkin-result', $result);
    }

    public function scanKiosk(): View
    {
        Employee::ensureQrTokenSchema();

        return view('employees.qr-scan', [
            'checkInBase' => url('/a'),
        ]);
    }

    public function svg(Employee $employee): Response
    {
        $svg = $this->qrAttendance->svgForEmployee($employee, 280);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function card(Employee $employee): View
    {
        $employee->ensureQrToken();
        $employee->loadMissing(['designation:id,name']);

        return view('employees.qr-card', [
            'companyName' => Setting::get('company_name', config('app.name')),
            'employees' => collect([$employee]),
            'qrAttendance' => $this->qrAttendance,
            'single' => true,
        ]);
    }

    public function cards(): View
    {
        Employee::ensureQrTokenSchema();

        $employees = Employee::query()
            ->excludeAdminAccounts()
            ->where('active', true)
            ->with(['designation:id,name'])
            ->orderBy('employee_no')
            ->get();

        foreach ($employees as $employee) {
            $employee->ensureQrToken();
        }

        return view('employees.qr-card', [
            'companyName' => Setting::get('company_name', config('app.name')),
            'employees' => $employees,
            'qrAttendance' => $this->qrAttendance,
            'single' => false,
        ]);
    }

    public function regenerate(Employee $employee)
    {
        $employee->regenerateQrToken();
        ActivityLogger::log('employee.qr_regenerated', 'Employee QR regenerated', $employee);

        return redirect()
            ->route('employees.edit', $employee)
            ->with('status', 'Naya QR generate ho gaya. Purana card kaam nahi karega — naya print karein.');
    }
}
