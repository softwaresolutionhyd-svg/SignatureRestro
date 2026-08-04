<?php

namespace App\Services;

use App\Models\PosOrder;
use App\Models\User;
use App\Notifications\PosActivity;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Stair-style activity alerts: activity log + in-app / browser notifications.
 */
final class PosActivityNotifier
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public static function push(
        string $action,
        string $title,
        string $message,
        ?Model $subject = null,
        array $properties = [],
        string $level = 'info',
        ?string $url = null,
        bool $log = true,
    ): void {
        try {
            if ($log) {
                ActivityLogger::log($action, $message, $subject, $properties !== [] ? $properties : null);
            }

            $actor = Auth::user();
            $companyId = current_company_id()
                ?? ($actor?->company_id ? (int) $actor->company_id : null);

            $orderId = null;
            $orderNo = null;
            if ($subject instanceof PosOrder) {
                $orderId = (int) $subject->id;
                $orderNo = (string) ($subject->order_no ?? '');
                $url = $url ?? self::orderUrl($subject);
            }

            $payload = [
                'title' => $title,
                'message' => $message,
                'action' => $action,
                'level' => $level,
                'order_id' => $orderId,
                'order_no' => $orderNo,
                'url' => $url,
                'actor' => $actor?->name,
                'icon' => self::iconFor($action, $level),
            ];

            $query = User::query()->orderBy('id');
            if ($companyId !== null) {
                $query->where(function ($q) use ($companyId) {
                    $q->where('company_id', $companyId)
                        ->orWhereNull('company_id');
                });
            }

            $query->chunkById(100, function ($users) use ($payload) {
                Notification::send($users, new PosActivity($payload));
                try {
                    app(WebPushService::class)->sendToUsers($users, $payload);
                } catch (Throwable $e) {
                    report($e);
                }
            });

            // Online-installed PWA: cafe relays so phone gets system tray alert without app open.
            try {
                app(WebPushService::class)->relayToRemote($payload, $companyId);
            } catch (Throwable $e) {
                report($e);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    public static function orderPlaced(PosOrder $order, bool $isUpdate = false): void
    {
        $where = self::orderWhereLabel($order);
        $title = $isUpdate ? 'Order Updated' : 'New Order';
        $action = $isUpdate ? 'pos.order_updated' : 'pos.order_placed';
        $msg = trim(sprintf(
            '%s%s%s',
            $order->order_no,
            $where !== '' ? ' · '.$where : '',
            $order->serviceTypeLabel() ? ' · '.$order->serviceTypeLabel() : ''
        ));

        self::push(
            $action,
            $title,
            $msg,
            $order,
            ['service_type' => $order->serviceTypeKey()],
            $isUpdate ? 'info' : 'success',
        );
    }

    public static function orderPaid(PosOrder $order): void
    {
        $where = self::orderWhereLabel($order);
        self::push(
            'pos.order_paid',
            'Bill Paid',
            trim(sprintf('%s%s · %s', $order->order_no, $where !== '' ? ' · '.$where : '', number_format((float) $order->grand_total, 0))),
            $order,
            ['grand_total' => (float) $order->grand_total],
            'success',
        );
    }

    public static function orderCancelled(PosOrder $order, string $reason = ''): void
    {
        $where = self::orderWhereLabel($order);
        $msg = trim(sprintf(
            '%s%s%s',
            $order->order_no,
            $where !== '' ? ' · '.$where : '',
            $reason !== '' ? ' — '.$reason : ''
        ));

        self::push(
            'pos.order_cancelled',
            'Order Cancelled',
            $msg,
            $order,
            ['reason' => $reason],
            'danger',
            log: false, // caller usually already logged
        );
    }

    /**
     * @param  list<array<string, mixed>>  $voids
     */
    public static function itemsCancelled(PosOrder $order, array $voids): void
    {
        if ($voids === []) {
            return;
        }

        $names = [];
        foreach ($voids as $void) {
            $label = trim((string) ($void['name'] ?? ''));
            if ($label === '') {
                $label = 'Item';
            }
            $qty = (float) ($void['qty'] ?? 0);
            $names[] = $qty > 0 ? sprintf('%s × %s', $label, rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.')) : $label;
        }

        $preview = implode(', ', array_slice($names, 0, 3));
        if (count($names) > 3) {
            $preview .= ' +'.(count($names) - 3).' more';
        }

        self::push(
            'pos.kitchen_void',
            'Item Cancelled',
            sprintf('%s — %s', $order->order_no, $preview),
            $order,
            ['void_count' => count($voids)],
            'warning',
            log: false, // logKitchenVoids already logs each line
        );
    }

    public static function billReopened(PosOrder $order): void
    {
        self::push(
            'pos.bill_reopened',
            'Bill Reopened',
            (string) $order->order_no,
            $order,
            [],
            'warning',
        );
    }

    private static function orderWhereLabel(PosOrder $order): string
    {
        $order->loadMissing(['table:id,name']);
        if ($order->table?->name) {
            return 'Table '.$order->table->name;
        }
        $room = trim((string) ($order->room_no ?? ''));
        if ($room !== '') {
            return $room;
        }
        $guest = trim((string) ($order->guest_name ?? ''));

        return $guest;
    }

    private static function orderUrl(PosOrder $order): string
    {
        if ($order->status === 'draft') {
            return route('restaurant-pos.index', ['resume_order' => $order->id]);
        }

        return route('restaurant-pos.receipt', $order);
    }

    private static function iconFor(string $action, string $level): string
    {
        return match (true) {
            str_contains($action, 'cancel') || str_contains($action, 'void') => 'bi-x-octagon',
            str_contains($action, 'paid') => 'bi-check-circle',
            str_contains($action, 'reopen') => 'bi-arrow-counterclockwise',
            str_contains($action, 'placed') => 'bi-bag-plus',
            $level === 'danger' => 'bi-exclamation-triangle',
            $level === 'success' => 'bi-check2-circle',
            default => 'bi-bell',
        };
    }
}
