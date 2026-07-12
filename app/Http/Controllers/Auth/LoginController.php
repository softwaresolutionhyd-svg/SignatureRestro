<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoginRateLimitService;
use App\Services\LoginTotpService;
use App\Support\LoginUsername;
use App\Support\WebAuthSession;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    protected $redirectTo = '/dashboard';

    /** Max failed login attempts before lockout. */
    protected $maxAttempts = 5;

    /** Lockout duration in minutes. */
    protected $decayMinutes = 10;

    public function __construct(
        private readonly LoginTotpService $loginTotp,
        private readonly LoginRateLimitService $rateLimit
    ) {
        // Allow login page + POST while already signed in (switch account).
        $this->middleware('guest')->except(['logout', 'showLoginForm', 'login']);
        $this->middleware('auth')->only('logout');
    }

    public function showLoginForm(Request $request)
    {
        $currentUser = Auth::user();

        if ($currentUser === null && $request->hasSession()) {
            // Stale session cookies cause CSRF mismatch on POST.
            $request->session()->invalidate();
            $request->session()->regenerate(true);
        }

        return response()
            ->view('auth.login', [
                'currentUser' => $currentUser,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function login(Request $request)
    {
        $this->validateLogin($request);

        if (method_exists($this, 'hasTooManyLoginAttempts') &&
            $this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return $this->sendLockoutResponse($request);
        }

        $login = trim((string) $request->input('login', ''));
        $user = LoginUsername::resolveUser($login);

        if ($user === null) {
            $this->incrementLoginAttempts($request);

            throw ValidationException::withMessages([
                $this->username() => ['Username maujood nahi. Employee name nahi — login username likhein (masalan: rana_shahid, chef_ad).'],
            ]);
        }

        if (! Hash::check((string) $request->input('password'), $user->password)) {
            $this->incrementLoginAttempts($request);

            return $this->sendFailedLoginResponse($request);
        }

        if ($user->hasTwoFactorEnabled()) {
            WebAuthSession::destroy($request);
            $token = $this->loginTotp->startChallenge($user, false);
            $request->session()->put('login_totp_token', $token);
            $request->session()->put('login_totp_user_id', (int) $user->id);
            $this->clearLoginAttempts($request);

            return redirect()->route('login.verify-totp');
        }

        $this->completeWebLogin($request, $user);
        $this->clearLoginAttempts($request);

        return redirect()->intended($this->redirectPath());
    }

    private function completeWebLogin(Request $request, User $user): void
    {
        WebAuthSession::establish($request, $user);
        $this->authenticated($request, $user);

        $username = $user->loginUsername() ?? $user->email;
        session()->flash('success', "Signed in as {$username} ({$user->name}).");
    }

    public function logout(Request $request)
    {
        WebAuthSession::destroy($request);

        return redirect()->route('login');
    }

    /**
     * @param  \App\Models\User  $user
     */
    protected function authenticated(Request $request, $user): void
    {
        if ($user->must_change_password ?? false) {
            session()->flash('warning', 'Security: pehle naya password set karein.');
        }
    }

    protected function loggedOut(Request $request)
    {
        return redirect()->route('login');
    }

    public function username(): string
    {
        return 'login';
    }

    protected function validateLogin(Request $request): void
    {
        $login = trim((string) $request->input('login', ''));

        $loginRules = str_contains($login, '@')
            ? ['required', 'string', 'email', 'max:120']
            : LoginUsername::loginInputRules();

        $request->validate([
            'login' => $loginRules,
            'password' => ['required', 'string'],
        ], [
            'login.regex' => 'Sirf username likhein (employee name nahi). Masalan: ordertaker, rana_shahid',
            'login.email' => 'Valid email/username likhein.',
        ]);
    }

    protected function credentials(Request $request): array
    {
        $login = trim((string) $request->input('login', ''));
        $password = (string) $request->input('password', '');
        $user = LoginUsername::resolveUser($login);

        if (! $user) {
            return ['email' => '__invalid__', 'password' => $password];
        }

        return ['email' => (string) $user->email, 'password' => $password];
    }

    protected function throttleKey(Request $request): string
    {
        return $this->rateLimit->keyForLogin($request);
    }

    protected function sendFailedLoginResponse(Request $request)
    {
        throw ValidationException::withMessages([
            $this->username() => [$this->rateLimit->failedLoginMessage($this->throttleKey($request))],
        ]);
    }

    protected function sendLockoutResponse(Request $request)
    {
        throw ValidationException::withMessages([
            $this->username() => [$this->rateLimit->lockoutMessage($this->throttleKey($request))],
        ]);
    }
}
