<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoginRateLimitService;
use App\Services\LoginTotpService;
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
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    public function showLoginForm(Request $request)
    {
        // Stale session cookies (old host, SW cache, expired tab) cause CSRF mismatch on POST.
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerate(true);
        }

        return response()
            ->view('auth.login')
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

        $credentials = $this->credentials($request);
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            $this->incrementLoginAttempts($request);

            return $this->sendFailedLoginResponse($request);
        }

        if ($user->hasTwoFactorEnabled()) {
            $token = $this->loginTotp->startChallenge($user, false);
            $request->session()->put('login_totp_token', $token);
            $this->clearLoginAttempts($request);

            return redirect()->route('login.verify-totp');
        }

        Auth::login($user, false);
        if ($user->remember_token) {
            $user->forceFill(['remember_token' => null])->save();
        }
        $request->session()->regenerate();
        $this->clearLoginAttempts($request);
        $this->authenticated($request, $user);

        return redirect()->intended($this->redirectPath());
    }

    /**
     * @param  \App\Models\User  $user
     */
    protected function authenticated(Request $request, $user): void
    {
        if ($user->isPlatformSuperAdmin()) {
            $request->session()->forget('active_company_id');
        }

        if ($user->must_change_password ?? false) {
            session()->flash('warning', 'Security: pehle naya password set karein.');
        }
    }

    /**
     * After logout go straight to login (no marketing / welcome page).
     */
    protected function loggedOut(Request $request)
    {
        return redirect()->route('login');
    }

    /**
     * Login field name used by throttling / trait.
     */
    public function username(): string
    {
        return 'login';
    }

    /**
     * Validate simple username + password.
     */
    protected function validateLogin(Request $request): void
    {
        $request->validate([
            'login' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string'],
        ]);
    }

    /**
     * Resolve username to stored email (supports plain username).
     *
     * Users can enter:
     * - full email, or
     * - username part before @ (e.g. superadmin)
     */
    protected function credentials(Request $request): array
    {
        $login = trim((string) $request->input('login', ''));
        $password = (string) $request->input('password', '');

        if ($login === '') {
            return ['email' => '', 'password' => $password];
        }

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return ['email' => $login, 'password' => $password];
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($login)])
            ->orWhereRaw("LOWER(SUBSTRING_INDEX(email, '@', 1)) = ?", [mb_strtolower($login)])
            ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($login)])
            ->first();

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
