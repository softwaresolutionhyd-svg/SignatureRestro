<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeDesignation;
use App\Models\EmployeeStaffCategory;
use App\Models\Setting;
use App\Services\QrAttendanceService;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\EnsuresEmployeePhotoSchema;
use App\Support\EnsuresEmployeeProfileSchema;
use App\Support\LanServerUrl;
use App\Support\WebAuthSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class QrAttendanceController extends Controller
{
    use EnsuresEmployeePhotoSchema;
    use EnsuresEmployeeProfileSchema;

    public function __construct(
        private readonly QrAttendanceService $qrAttendance
    ) {}

    public function checkIn(Request $request, string $token): JsonResponse|View|RedirectResponse
    {
        $authGate = $this->ensureQrAttendanceAuthorized($request);
        if ($authGate !== null) {
            return $authGate;
        }

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

    public function scanKiosk(Request $request): View
    {
        abort_unless($request->user()?->canAuthorizeQrAttendance(), 403);

        Employee::ensureQrTokenSchema();

        return view('employees.qr-scan', [
            'checkInBase' => url('/a'),
        ]);
    }

    /**
     * QR Present only when Admin / Super Admin is logged in on this device.
     * Guests (or other roles) → login; after login, intended URL completes check-in.
     */
    private function ensureQrAttendanceAuthorized(Request $request): JsonResponse|RedirectResponse|null
    {
        $user = $request->user();
        $wantsJson = $request->expectsJson() || $request->query('format') === 'json';
        $loginMessage = 'QR attendance ke liye pehle Admin ya Super Admin se login karein.';

        if ($user instanceof User && $user->canAuthorizeQrAttendance()) {
            return null;
        }

        if ($wantsJson) {
            return response()->json([
                'ok' => false,
                'already' => false,
                'title' => 'Login required',
                'message' => $loginMessage,
                'login_required' => true,
                'login_url' => route('login'),
                'time' => now()->format('h:i A'),
                'date' => now()->format(app_date_format()),
            ], 401);
        }

        if ($user) {
            WebAuthSession::destroy($request);
        }

        return redirect()
            ->guest(route('login'))
            ->with('warning', $loginMessage);
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
        $this->ensureEmployeeProfileSchema();
        $employee->ensureQrToken();
        $employee->loadMissing(['designation:id,name']);

        return view('employees.qr-card', $this->cardPageData(collect([$employee]), true));
    }

    public function cards(Request $request): View
    {
        abort_unless($request->user()?->canAuthorizeQrAttendance(), 403);

        $this->ensureEmployeeProfileSchema();
        $this->ensureEmployeePhotoSchema();
        Employee::ensureQrTokenSchema();

        $activeOnly = $request->boolean('active_only', true);
        $staffCategoryId = $request->integer('staff_category_id') ?: null;
        $designationId = $request->integer('designation_id') ?: null;
        $photoFilter = strtolower(trim((string) $request->query('photo', 'all')));
        if (! in_array($photoFilter, ['all', 'with', 'without'], true)) {
            $photoFilter = 'all';
        }

        $query = Employee::query()
            ->excludeAdminAccounts()
            ->with(['designation:id,name', 'staffCategory:id,name'])
            ->orderBy('employee_no');

        if ($activeOnly) {
            $query->where('active', true);
        }
        if ($staffCategoryId) {
            $query->where('staff_category_id', $staffCategoryId);
        }
        if ($designationId) {
            $query->where('designation_id', $designationId);
        }
        if ($photoFilter === 'with') {
            $query->whereNotNull('photo_path')->where('photo_path', '!=', '');
        } elseif ($photoFilter === 'without') {
            $query->where(function ($q) {
                $q->whereNull('photo_path')->orWhere('photo_path', '');
            });
        }

        $employees = $query->get();

        foreach ($employees as $employee) {
            $employee->ensureQrToken();
        }

        $categories = EmployeeStaffCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);
        $designations = EmployeeDesignation::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('employees.qr-card', array_merge(
            $this->cardPageData($employees, false),
            [
                'activeOnly' => $activeOnly,
                'staffCategoryId' => $staffCategoryId,
                'designationId' => $designationId,
                'photoFilter' => $photoFilter,
                'categories' => $categories,
                'designations' => $designations,
                'printedAt' => now()->timezone(config('app.timezone'))->format('d M Y, h:i A'),
            ]
        ));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Employee>  $employees
     * @return array{companyName: string, companyLogo: ?string, employees: \Illuminate\Support\Collection, qrAttendance: QrAttendanceService, single: bool}
     */
    private function cardPageData($employees, bool $single): array
    {
        $logoPath = (string) Setting::get('company_logo', '');

        return [
            'companyName' => (string) Setting::get('company_name', config('app.name')),
            'companyLogo' => company_logo_data_uri($logoPath) ?: company_logo_url($logoPath),
            'employees' => $employees,
            'qrAttendance' => $this->qrAttendance,
            'single' => $single,
            'qrBaseUrl' => LanServerUrl::baseUrl(),
        ];
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
