<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TotpService
{
    private const RECOVERY_CODE_COUNT = 8;

    /** Allow +/- 2 minutes for server/phone clock drift. */
    private const VERIFY_WINDOW = 4;

    public function __construct(
        private readonly Google2FA $google2fa
    ) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function getQrCodeSvg(User $user, string $secret): string
    {
        $url = $this->google2fa->getQRCodeUrl(
            (string) config('app.name', 'Signature'),
            (string) $user->email,
            $secret
        );

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );

        return (new Writer($renderer))->writeString($url);
    }

    public function verifyKey(string $secret, string $code): bool
    {
        $secret = strtoupper(trim($secret));
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        return (bool) $this->google2fa->verifyKey($secret, $code, self::VERIFY_WINDOW);
    }

    public function verifyForUser(User $user, string $code): bool
    {
        $secret = $user->two_factor_secret;
        if ($secret === null || $secret === '') {
            return false;
        }

        return $this->verifyKey($secret, $code);
    }

    /** @return list<string> */
    public function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODE_COUNT; $i++) {
            $codes[] = Str::upper(Str::random(4).'-'.Str::random(4));
        }

        return $codes;
    }

    public function verifyRecoveryCode(User $user, string $code): bool
    {
        $normalized = Str::upper(preg_replace('/\s+/', '', $code) ?? '');
        $raw = $user->two_factor_recovery_codes ?? [];
        $codes = $raw instanceof \ArrayObject ? $raw->getArrayCopy() : (array) $raw;

        $index = array_search($normalized, array_map(
            fn ($stored) => Str::upper(preg_replace('/\s+/', '', (string) $stored) ?? ''),
            $codes
        ), true);

        if ($index === false) {
            return false;
        }

        unset($codes[$index]);
        $user->forceFill([
            'two_factor_recovery_codes' => array_values($codes),
        ])->save();

        return true;
    }
}
