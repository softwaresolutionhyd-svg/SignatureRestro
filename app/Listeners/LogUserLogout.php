<?php

namespace App\Listeners;

use App\Support\ActivityLogger;
use Illuminate\Auth\Events\Logout;

class LogUserLogout
{
    public function handle(Logout $event): void
    {
        $user = $event->user;
        ActivityLogger::log(
            'auth.logout',
            'Signed out',
            null,
            $user ? ['email' => $user->email] : [],
            $user?->id,
        );
    }
}
