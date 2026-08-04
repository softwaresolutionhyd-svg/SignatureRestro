<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PosActivity extends Notification
{
    use Queueable;

    /**
     * @param  array{
     *   title: string,
     *   message: string,
     *   action?: string,
     *   level?: string,
     *   order_id?: int|null,
     *   order_no?: string|null,
     *   url?: string|null,
     *   actor?: string|null,
     *   icon?: string|null
     * }  $payload
     */
    public function __construct(
        public readonly array $payload
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => (string) ($this->payload['title'] ?? 'Activity'),
            'message' => (string) ($this->payload['message'] ?? ''),
            'action' => (string) ($this->payload['action'] ?? 'pos.activity'),
            'level' => (string) ($this->payload['level'] ?? 'info'),
            'order_id' => isset($this->payload['order_id']) ? (int) $this->payload['order_id'] : null,
            'order_no' => $this->payload['order_no'] ?? null,
            'url' => $this->payload['url'] ?? null,
            'actor' => $this->payload['actor'] ?? null,
            'icon' => $this->payload['icon'] ?? 'bi-bell',
        ];
    }
}
