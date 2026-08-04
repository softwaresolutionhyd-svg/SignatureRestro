<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Who receives which Stair / DB notifications.
 *
 * - Admin + Manager: all (stock, purchase, POS, …)
 * - Cashier / Order Taker / other staff: POS activity only (handled by PosActivityNotifier)
 */
final class StaffNotifier
{
    /**
     * Stock / inventory / purchase style alerts → management only.
     */
    public static function notifyManagement(Notification $notification, ?int $companyId = null): void
    {
        $query = User::query()->orderBy('id');
        if ($companyId !== null) {
            $query->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        $query->chunkById(100, function ($users) use ($notification) {
            $targets = $users->filter(fn (User $u) => $u->receivesManagementNotifications())->values();
            if ($targets->isNotEmpty()) {
                NotificationFacade::send($targets, clone $notification);
            }
        });
    }
}
