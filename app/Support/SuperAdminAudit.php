<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Reusable Super Admin reason gate + audit logger.
 * Persists via activity_logs with action prefix "super_admin.*".
 */
final class SuperAdminAudit
{
    public const PREFIX = 'super_admin.';

    public const TYPE_ADD = 'add';

    public const TYPE_EDIT = 'edit';

    public const TYPE_DELETE = 'delete';

    public const TYPE_PASSWORD_CHANGE = 'password_change';

    public const TYPE_OTHER = 'other';

    public static function userIsSuperAdmin(?User $user): bool
    {
        return (bool) $user?->isSuperAdmin();
    }

    public static function abortUnlessSuperAdmin(?User $user): void
    {
        abort_unless(self::userIsSuperAdmin($user), 403);
    }

    /**
     * When the actor is Super Admin, require a non-empty reason.
     * Non–Super Admin callers get null (no reason required).
     */
    public static function requireReasonIfSuperAdmin(Request $request, ?User $user = null, string $field = 'admin_reason'): ?string
    {
        $user ??= $request->user();
        if (! self::userIsSuperAdmin($user)) {
            return null;
        }

        return self::requireReason($request, $field);
    }

    public static function requireReason(Request $request, string $field = 'admin_reason'): string
    {
        $reason = trim((string) $request->input($field, ''));
        if ($reason === '') {
            throw ValidationException::withMessages([
                $field => ['Reason is required before this Super Admin action.'],
            ]);
        }
        if (mb_strlen($reason) > 500) {
            throw ValidationException::withMessages([
                $field => ['Reason must be at most 500 characters.'],
            ]);
        }

        return $reason;
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public static function log(
        string $actionType,
        string $reason,
        ?Model $subject = null,
        ?array $metadata = null,
        ?string $description = null,
    ): void {
        $actionType = self::normalizeType($actionType);
        $meta = array_merge($metadata ?? [], [
            'reason' => $reason,
            'action_type' => $actionType,
        ]);

        ActivityLogger::log(
            self::PREFIX.$actionType,
            $description ?? ('Super Admin '.$actionType),
            $subject,
            $meta
        );
    }

    public static function normalizeType(string $actionType): string
    {
        $allowed = [
            self::TYPE_ADD,
            self::TYPE_EDIT,
            self::TYPE_DELETE,
            self::TYPE_PASSWORD_CHANGE,
            self::TYPE_OTHER,
        ];

        return in_array($actionType, $allowed, true) ? $actionType : self::TYPE_OTHER;
    }

    public static function queryLogs(): Builder
    {
        return ActivityLog::query()->where('action', 'like', self::PREFIX.'%');
    }

    public static function typeLabel(string $actionOrType): string
    {
        $type = str_starts_with($actionOrType, self::PREFIX)
            ? substr($actionOrType, strlen(self::PREFIX))
            : $actionOrType;

        return match ($type) {
            self::TYPE_ADD => 'Add',
            self::TYPE_EDIT => 'Edit',
            self::TYPE_DELETE => 'Delete',
            self::TYPE_PASSWORD_CHANGE => 'Password change',
            default => 'Other',
        };
    }
}
