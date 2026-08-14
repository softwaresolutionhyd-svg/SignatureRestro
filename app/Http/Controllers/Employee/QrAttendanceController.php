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
        $result = $this->qrAttendance->markPresentByToken($token);

        if ($request->expectsJson() || $request->query('format') === 'json') {
            $employee = $result['employee'];

            return response()->json([
                'ok' => $result['ok'],
                'already' => $result['already'],
                'message' => $result['message'],
                'time' => $result['time'],
                'date' => $result['date'],
                'employee' => $employee ? [
                    'name' => $employee->name,
                    'employee_no' => $employee->employee_no,
                    'photo_url' => $employee->photoUrl(),
                ] : null,
            ], $result['ok'] ? 200 : 422);
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
