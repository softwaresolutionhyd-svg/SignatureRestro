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
                app(CloudSyncService::class)->push();
            } catch (\Throwable) {
                // Browser heartbeat / scheduler will retry.
            } finally {
                self::$scheduled = false;
            }
        });
    }
}
