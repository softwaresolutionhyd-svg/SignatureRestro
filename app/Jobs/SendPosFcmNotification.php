<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Throwable;

class SendPosFcmNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    /**
     * @param  list<int>  $userIds
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $userIds,
        public array $payload,
        public string $app = 'admin',
    ) {}

    public function handle(FcmService $fcm): void
    {
        if ($this->userIds === []) {
            return;
        }

        /** @var Collection<int, User> $users */
        $users = User::query()->whereIn('id', $this->userIds)->get(['id']);
        $fcm->sendToUsers($users, $this->payload, $this->app);
    }

    public function failed(Throwable $e): void
    {
        report($e);
    }
}
