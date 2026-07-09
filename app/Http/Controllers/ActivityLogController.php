<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $action = trim((string) $request->query('action', ''));
        $userId = $request->query('user_id');
        $from = $request->query('from');
        $to = $request->query('to');

        $logs = ActivityLog::query()
            ->with(['user:id,name,email'])
            ->when($action !== '', fn ($q) => $q->where('action', 'like', '%'.$action.'%'))
            ->when($userId !== null && $userId !== '', fn ($q) => $q->where('user_id', (int) $userId))
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->orderByDesc('id')
            ->paginate(Setting::pageSize('employees_per_page', 20))
            ->withQueryString();

        $users = User::query()->orderBy('name')->get(['id', 'name', 'email']);

        return view('activity-logs.index', compact('logs', 'users', 'action', 'userId', 'from', 'to'));
    }
}
