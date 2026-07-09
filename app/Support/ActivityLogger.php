<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Throwable;

final class ActivityLogger
{
    public static function log(
        string $action,
        ?string $description = null,
        ?Model $subject = null,
        ?array $properties = null,
        ?int $userId = null,
    ): void {
        $userId = $userId ?? Auth::id();
        $companyId = current_company_id();

        if ($companyId === null && $userId) {
            $companyId = User::query()->whereKey($userId)->value('company_id');
            $companyId = $companyId !== null ? (int) $companyId : null;
        }

        if ($companyId === null && $subject && $subject->getAttribute('company_id') !== null) {
            $companyId = (int) $subject->getAttribute('company_id');
        }

        if ($companyId === null) {
            $companyId = Company::query()->orderBy('id')->value('id');
            $companyId = $companyId !== null ? (int) $companyId : null;
        }

        if ($companyId === null) {
            return;
        }

        $req = request();

        try {
            ActivityLog::query()->create([
                'company_id' => $companyId,
                'user_id' => $userId,
                'action' => $action,
                'description' => $description,
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'properties' => $properties,
                'ip_address' => $req?->ip(),
                'user_agent' => $req ? mb_substr((string) $req->userAgent(), 0, 512) : null,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // Never block login / requests because of activity logging.
        }
    }
}
