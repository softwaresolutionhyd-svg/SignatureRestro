<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\LoginRateLimitService;
use App\Services\LoginTotpService;
use App\Support\WebAuthSession;
use Illuminate\Http\Request;

class TotpVerificationController extends Controller
{
    public function __construct(
        private readonly LoginTotpService $loginTotp,
        private readonly LoginRateLimitService $rateLimit
    ) {
        $this->middleware('guest');
    }

    public function show(Request $request)
    {
        if (! $request->session()->has('login_totp_token')) {
            return redirect()->route('login')->withErrors([
                'login' => '2FA session expire ho gayi. Dobara login karein.',
            ]);
        }

        return view('auth.verify-totp');
    }

    public function verify(Request $request)
    {
        $rateKey = $this->rateLimit->keyForIp('login-totp', $request);

        if ($this->rateLimit->tooManyAttempts($rateKey)) {
            return back()->withErrors([
                'code' => $this->rateLimit->lockoutMessage($rateKey),
            ]);
        }

        $token = (string) $request->session()->get('login_totp_token', '');
        if ($token === '') {
            return redirect()->route('login')->withErrors([
                'login' => '2FA session expire ho gayi. Dobara login karein.',
            ]);
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
        ]);

        $result = $this->loginTotp->verify($token, $data['code']);
        if ($result === null) {
            $this->rateLimit->hit($rateKey);

            return back()->withErrors([
                'code' => $this->rateLimit->failedCodeMessage($rateKey, 'Authenticator code'),
            ]);
        }

        $expectedUserId = (int) $request->session()->get('login_totp_user_id', 0);
        if ($expectedUserId > 0 && $expectedUserId !== (int) $result['user']->id) {
            $request->session()->forget(['login_totp_token', 'login_totp_user_id']);

            return redirect()->route('login')->withErrors([
                'login' => '2FA session invalid. Dobara login karein.',
            ]);
        }

        $this->rateLimit->clear($rateKey);
        $request->session()->forget(['login_totp_token', 'login_totp_user_id']);

        WebAuthSession::establish($request, $result['user']);

        if ($result['user']->must_change_password ?? false) {
            session()->flash('warning', 'Security: pehle naya password set karein.');
        }

        $username = $result['user']->loginUsername() ?? $result['user']->email;
        session()->flash('success', "Signed in as {$username} ({$result['user']->name}).");

        return redirect()->intended('/dashboard');
    }
}
