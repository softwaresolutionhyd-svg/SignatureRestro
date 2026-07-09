<?php

namespace App\Http\Controllers;

use App\Services\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;

class TwoFactorController extends Controller
{
    public function __construct(
        private readonly TotpService $totp
    ) {}

    public function setup(Request $request)
    {
        if (! $this->twoFactorColumnsReady()) {
            return redirect()->route('profile.edit')->withErrors([
                'two_factor' => '2FA database columns missing hain. Server par migrate chalayein: php artisan migrate --force',
            ]);
        }

        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return redirect()->route('profile.edit')->with('status', 'Google Authenticator pehle se enabled hai.');
        }

        try {
            $existingSecret = (string) $request->session()->get('two_factor_pending_secret', '');
            $shouldReuse = $existingSecret !== '' && ! $request->boolean('reset');
            $secret = $shouldReuse ? $existingSecret : $this->totp->generateSecret();
            $request->session()->put('two_factor_pending_secret', $secret);
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('profile.edit')->withErrors([
                'two_factor' => '2FA setup start nahi ho saka. Server par composer packages check karein (google2fa).',
            ]);
        }

        return view('profile.two-factor-setup', [
            'secret' => $secret,
            'qrCodeSvg' => $this->totp->getQrCodeSvg($user, $secret),
        ]);
    }

    public function resetSetup(Request $request)
    {
        $request->session()->forget('two_factor_pending_secret');

        return redirect()->route('profile.two-factor.setup', ['reset' => 1]);
    }

    public function confirm(Request $request)
    {
        if (! $this->twoFactorColumnsReady()) {
            return redirect()->route('profile.edit')->withErrors([
                'two_factor' => '2FA database columns missing hain. Server par migrate chalayein: php artisan migrate --force',
            ]);
        }

        $secret = (string) $request->session()->get('two_factor_pending_secret', '');
        if ($secret === '') {
            return redirect()->route('profile.two-factor.setup')->withErrors([
                'two_factor' => 'Setup session expire ho gayi. QR code dubara scan karein.',
            ]);
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        if (! $this->totp->verifyKey($secret, $data['code'])) {
            return back()->withErrors([
                'code' => 'Galat code. Google Authenticator se sahi 6-digit code enter karein.',
            ])->withInput();
        }

        $user = $request->user();
        $recoveryCodes = $this->totp->generateRecoveryCodes();

        try {
            $user->forceFill([
                'two_factor_secret' => $secret,
                'two_factor_recovery_codes' => $recoveryCodes,
                'two_factor_confirmed_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors([
                'code' => '2FA save nahi ho saka. Server par migrate aur cache clear karein, phir dubara try karein.',
            ])->withInput();
        }

        $request->session()->forget('two_factor_pending_secret');
        $request->session()->put('two_factor_recovery_codes_flash', $recoveryCodes);

        return redirect()->route('profile.two-factor.recovery');
    }

    public function recovery(Request $request)
    {
        $recoveryCodes = $request->session()->pull('two_factor_recovery_codes_flash');

        if (! is_array($recoveryCodes) || $recoveryCodes === []) {
            return redirect()->route('profile.edit');
        }

        return view('profile.two-factor-recovery', [
            'recoveryCodes' => $recoveryCodes,
        ]);
    }

    public function disable(Request $request)
    {
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('profile.edit');
        }

        $data = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'code' => ['required', 'string', 'max:20'],
        ]);

        $codeValid = $this->totp->verifyForUser($user, $data['code'])
            || $this->totp->verifyRecoveryCode($user, $data['code']);

        if (! $codeValid) {
            return back()->withErrors([
                'code' => 'Password ya Authenticator code galat hai.',
            ]);
        }

        try {
            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors([
                'code' => '2FA disable nahi ho saka. Admin se rabta karein.',
            ]);
        }

        return redirect()->route('profile.edit')->with('status', 'Google Authenticator 2FA disable ho gaya.');
    }

    private function twoFactorColumnsReady(): bool
    {
        return Schema::connection('mysql')->hasColumn('users', 'two_factor_secret')
            && Schema::connection('mysql')->hasColumn('users', 'two_factor_recovery_codes')
            && Schema::connection('mysql')->hasColumn('users', 'two_factor_confirmed_at');
    }
}
