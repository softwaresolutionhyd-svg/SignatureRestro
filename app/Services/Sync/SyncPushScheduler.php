<?php

namespace App\Services\Sync;

class SyncPushScheduler
{
    private static bool $scheduled = false;

    public function schedule(): void
    {
        if (! config('sync.enabled') || config('sync.role') !== 'local') {
            return;
        }

        if (self::$scheduled) {
            return;
        }

        self::$scheduled = true;

        app()->terminating(function () {
            try {
                // When browser heartbeat auto-pushes, skip request-end push to avoid
                // racing the same queue and keeping "Syncing…" stuck.
                if (config('sync.auto_push_heartbeat', true)) {
                    return;
                }
                app(CloudSyncService::class)->push(false);
            } catch (\Throwable) {
                // Browser heartbeat / scheduler will retry.
            } finally {
                self::$scheduled = false;
            }
        });
    }
}
