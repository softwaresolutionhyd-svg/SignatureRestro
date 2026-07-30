<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $locale = (string) $request->input('locale', 'en');
        if (! in_array($locale, ['en', 'ur'], true)) {
            $locale = 'en';
        }

        $request->session()->put('app_locale', $locale);

        $redirectTo = $this->safeRedirectPath($request->input('redirect_to'));

        return redirect()->to($redirectTo ?? url()->previous() ?? route('dashboard'));
    }

    private function safeRedirectPath(mixed $value): ?string
    {
        if (! is_string($value) || $value === '' || preg_match('/[\r\n\0]/', $value)) {
            return null;
        }

        if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
            return $value;
        }

        $base = rtrim((string) config('app.url'), '/');
        if ($base !== '' && str_starts_with($value, $base.'/')) {
            return $value;
        }

        return null;
    }
}
