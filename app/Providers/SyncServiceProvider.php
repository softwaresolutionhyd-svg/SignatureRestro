<?php

namespace App\Providers;

use App\Services\Sync\SyncOutboxRecorder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class SyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SyncOutboxRecorder::class);
        $this->app->singleton(SyncPushScheduler::class);
    }

    public function boot(): void
    {
        if (! config('sync.enabled') || config('sync.role') !== 'local') {
            return;
        }

        $recorder = $this->app->make(SyncOutboxRecorder::class);

        Event::listen('eloquent.created: *', function (string $event, array $payload) use ($recorder) {
            $model = $payload[0] ?? null;
            if ($model instanceof Model) {
                $recorder->recordModel($model, 'upsert');
            }
        });

        Event::listen('eloquent.updated: *', function (string $event, array $payload) use ($recorder) {
            $model = $payload[0] ?? null;
            if ($model instanceof Model) {
                $recorder->recordModel($model, 'upsert');
            }
        });

        Event::listen('eloquent.deleted: *', function (string $event, array $payload) use ($recorder) {
            $model = $payload[0] ?? null;
            if ($model instanceof Model) {
                $recorder->recordModel($model, 'delete');
            }
        });
    }
}
