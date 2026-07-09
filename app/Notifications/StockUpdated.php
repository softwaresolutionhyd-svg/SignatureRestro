<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StockUpdated extends Notification
{
    use Queueable;

    public function __construct(
        public readonly array $payload
    ) {}

    /**
     * Create a new notification instance.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }
}
