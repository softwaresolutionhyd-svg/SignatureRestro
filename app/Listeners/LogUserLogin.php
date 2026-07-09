<?php

namespace App\Listeners;

use App\Support\ActivityLogger;
use Illuminate\Auth\Events\Login;

class LogUserLogin
{
    public function handle(Login $event): void
    {
        ActivityLogger::log(
            'auth.login',
            'Signed in',
            null,
            ['email' => $event->user->email],
            $event->user->id,
        );
    }
}
