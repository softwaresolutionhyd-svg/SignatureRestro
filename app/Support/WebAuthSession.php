<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Single place for web login/logout + session binding (prevents wrong-account sessions). */
final class WebAuthSession
{
    public const BOUND_USER_ID = 'auth_bound_user_id';

    public const BOUND_USERNAME = 'auth_bound_username';

    public static function establish(Request $request, User $user): void
    {
        Auth::logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerate(true);
        }

        Auth::login($user, false);

        if ($user->remember_token) {
            $user->forceFill(['remember_token' => null])->save();
        }

        $request->session()->forget(['active_company_id', 'login_totp_token', 'login_totp_user_id']);
        $request->session()->put(self::BOUND_USER_ID, (int) $user->id);
        $request->session()->put(self::BOUND_USERNAME, $user->loginUsername() ?? $user->email);
    }

    public static function destroy(Request $request): void
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerate(true);
    }

    public static function enforceBoundUser(Request $request): void
    {
        if (! Auth::check()) {
            return;
        }

        $bound = (int) $request->session()->get(self::BOUND_USER_ID, 0);
        $current = (int) Auth::id();

        if ($bound === 0) {
            $user = Auth::user();
            $request->session()->put(self::BOUND_USER_ID, $current);
            $request->session()->put(self::BOUND_USERNAME, $user?->loginUsername() ?? $user?->email);

            return;
        }

        if ($bound !== $current) {
            self::destroy($request);
        }
    }

    public static function boundUsername(Request $request): ?string
    {
        $username = $request->session()->get(self::BOUND_USERNAME);

        return is_string($username) && $username !== '' ? $username : null;
    }
}
