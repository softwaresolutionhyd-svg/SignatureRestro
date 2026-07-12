<?php

namespace App\Listeners;

use App\Support\ActivityLogger;
use Illuminate\Auth\Events\Login;

class LogUserLogin
{
    public function handle(Login $event): void
    {
        $typedLogin = trim((string) request()->input('login', ''));

        ActivityLogger::log(
            'auth.login',
            'Signed in',
            null,
            [
                'email' => $event->user->email,
                'username' => $event->user->loginUsername(),
                'typed_login' => $typedLogin !== '' ? $typedLogin : null,
            ],
            $event->user->id,
        );
    }
}
