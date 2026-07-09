<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetRequest;
use App\Models\Setting;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\AdminPasswordReset;
use Illuminate\Http\Request;

class PasswordResetRequestController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:company_admin,super_admin');
    }

    public function index()
    {
        if (! PasswordResetRequest::tableExists()) {
            return redirect()
                ->route('dashboard')
                ->with(
                    'warning',
                    'Password reset requests ke liye pehle `php artisan migrate` chalayein (table `password_reset_requests`).'
                );
        }

        $query = PasswordResetRequest::query()
            ->with(['user'])
            ->where('status', 'pending')
            ->orderByDesc('created_at');

        if (! auth()->user()?->isPlatformSuperAdmin()) {
            $cid = auth()->user()?->company_id;
            $query->whereHas('user', fn ($q) => $q->where('company_id', $cid));
        }

        $requests = $query
            ->paginate(Setting::pageSize('admin_password_reset_requests_per_page', 20))
            ->withQueryString();

        return view('admin.password-reset-requests.index', compact('requests'));
    }

    public function reset(Request $request, PasswordResetRequest $passwordResetRequest)
    {
        abort_unless($passwordResetRequest->isPending(), 404);

        $target = $passwordResetRequest->user;
        abort_unless($this->canProcess($request->user(), $target), 403);

        $target->password = AdminPasswordReset::TEMP_PASSWORD;
        $target->must_change_password = true;
        $target->save();

        $passwordResetRequest->update([
            'status' => 'completed',
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
        ]);

        ActivityLogger::log('user.password_reset_by_admin', 'Password reset to default for '.$target->email);

        return redirect()
            ->route('admin.password-reset-requests.index')
            ->with('status', 'Password set to '.AdminPasswordReset::TEMP_PASSWORD.' — user ko bata dein.');
    }

    private function canProcess(User $admin, User $target): bool
    {
        if ($admin->isPlatformSuperAdmin()) {
            return true;
        }

        return $target->company_id !== null
            && (int) $target->company_id === (int) $admin->company_id;
    }
}
