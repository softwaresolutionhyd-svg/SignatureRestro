<?php

declare(strict_types=1);

use Carbon\Carbon;

/** Display: day-month-year (e.g. 24-06-2026). */
function app_date_format(): string
{
    return (string) config('app.date_display_format', 'd-m-Y');
}

/** Display: day-month-year with time. */
function app_datetime_format(): string
{
    return (string) config('app.datetime_display_format', 'd-m-Y h:i A');
}

/** HTML date input value (ISO); browser requires Y-m-d. */
function app_date_input_format(): string
{
    return 'Y-m-d';
}

/**
 * @param  \DateTimeInterface|string|null  $value
 */
function fmt_date(mixed $value, string $default = '—'): string
{
    if ($value === null || $value === '') {
        return $default;
    }

    try {
        $d = $value instanceof \DateTimeInterface ? Carbon::instance($value) : Carbon::parse($value);

        return $d->format(app_date_format());
    } catch (\Throwable) {
        return $default;
    }
}

/**
 * @param  \DateTimeInterface|string|null  $value
 */
function fmt_datetime(mixed $value, string $default = '—'): string
{
    if ($value === null || $value === '') {
        return $default;
    }

    try {
        $d = $value instanceof \DateTimeInterface ? Carbon::instance($value) : Carbon::parse($value);

        return $d->format(app_datetime_format());
    } catch (\Throwable) {
        return $default;
    }
}

/**
 * @param  \DateTimeInterface|string|null  $value
 */
function fmt_date_input(mixed $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    try {
        $d = $value instanceof \DateTimeInterface ? Carbon::instance($value) : Carbon::parse($value);

        return $d->format(app_date_input_format());
    } catch (\Throwable) {
        return '';
    }
}

/**
 * Parse user-entered date (DD-MM-YYYY, DD/MM/YYYY, or ISO) for saving.
 *
 * @param  \DateTimeInterface|string|null  $value
 */
function parse_display_date(mixed $value): ?Carbon
{
    if ($value === null || $value === '') {
        return null;
    }

    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    foreach ([app_date_format(), 'd/m/Y', 'd.m.Y', 'Y-m-d'] as $fmt) {
        try {
            $d = Carbon::createFromFormat($fmt, $value);

            return $d->startOfDay();
        } catch (\Throwable) {
            continue;
        }
    }

    try {
        return Carbon::parse($value)->startOfDay();
    } catch (\Throwable) {
        return null;
    }
}

/** Value for text date fields (DD-MM-YYYY), including repopulating after validation errors. */
function form_date_value(string $name, mixed $default = null): string
{
    $old = old($name);
    if ($old !== null && $old !== '') {
        $parsed = parse_display_date($old);

        return $parsed ? fmt_date($parsed) : fmt_date($old, (string) $old);
    }

    if ($default === null || $default === '') {
        return '';
    }

    if ($default instanceof \DateTimeInterface) {
        return fmt_date($default);
    }

    $parsedDefault = parse_display_date($default);

    return $parsedDefault ? fmt_date($parsedDefault) : (string) $default;
}

/**
 * @param  list<string>  $fields
 */
function normalize_request_dates(\Illuminate\Http\Request $request, array $fields): void
{
    $merge = [];
    foreach ($fields as $field) {
        if (! $request->has($field)) {
            continue;
        }
        $raw = $request->input($field);
        if ($raw === null || $raw === '') {
            continue;
        }
        $parsed = parse_display_date($raw);
        if ($parsed) {
            $merge[$field] = $parsed->format('Y-m-d');
        }
    }
    if ($merge !== []) {
        $request->merge($merge);
    }
}

/**
 * Format a number for display: no unnecessary decimals (e.g. 0 not 0.0000).
 * Trims trailing zeros after the decimal; keeps thousands separators.
 */
function fmt_num(mixed $value, int $maxDecimals = 4): string
{
    if ($value === null || $value === '') {
        return '0';
    }
    if (is_string($value)) {
        $value = str_replace(',', '', $value);
    }
    if (! is_numeric($value)) {
        return '0';
    }
    $n = (float) $value;
    if (! is_finite($n)) {
        return '0';
    }
    $maxDecimals = max(0, min(14, $maxDecimals));
    $s = number_format($n, $maxDecimals, '.', ',');
    if (str_contains($s, '.')) {
        $s = rtrim(rtrim($s, '0'), '.');
    }
    if ($s === '' || $s === '-0') {
        return '0';
    }

    return $s;
}

/**
 * Active tenant company id: super admin uses session; others use users.company_id.
 */
function current_company_id(): ?int
{
    if (! auth()->check()) {
        return null;
    }

    $request = request();
    if ($request && $request->attributes->has('api_company_id')) {
        return (int) $request->attributes->get('api_company_id');
    }

    $user = auth()->user();
    if (($user->role ?? '') === 'super_admin') {
        $v = session('active_company_id');

        return $v !== null && $v !== '' ? (int) $v : null;
    }

    return $user->company_id ? (int) $user->company_id : null;
}

/**
 * Branding for the guest login page: first active company's settings (name, phone, logo path).
 *
 * @return array{company_name: string, company_phone: string, company_logo: string}
 */
function login_page_branding(): array
{
    $defaults = [
        'company_name' => (string) config('app.name', 'Stair'),
        'company_phone' => '',
        'company_logo' => '',
    ];

    try {
        $cid = \App\Models\Company::query()->where('active', true)->orderBy('id')->value('id');
        if ($cid === null) {
            return $defaults;
        }

        $keys = ['company_name', 'company_phone', 'company_logo'];
        $rows = \App\Models\Setting::query()
            ->where('company_id', $cid)
            ->whereIn('key', $keys)
            ->pluck('value', 'key')
            ->all();

        return array_merge($defaults, $rows);
    } catch (\Throwable) {
        return $defaults;
    }
}

/**
 * Public URL for a company logo stored on the public disk.
 */
function company_logo_url(?string $path): ?string
{
    $path = trim((string) $path);
    if ($path === '') {
        return null;
    }

    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }

    return asset('storage/'.$path);
}

/**
 * Absolute filesystem path for a company logo on the public disk.
 */
function company_logo_path(?string $path): ?string
{
    $path = trim((string) $path);
    if ($path === '' || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return null;
    }

    $candidates = [
        storage_path('app/public/'.$path),
        public_path('storage/'.$path),
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

/**
 * Data-URI for reliable browser / thermal print embedding.
 */
function company_logo_data_uri(?string $path): ?string
{
    $absolute = company_logo_path($path);
    if ($absolute === null) {
        return null;
    }

    $mime = @mime_content_type($absolute) ?: null;
    if ($mime === null || ! str_starts_with($mime, 'image/')) {
        $ext = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };
    }

    $bytes = @file_get_contents($absolute);
    if ($bytes === false || $bytes === '') {
        return null;
    }

    return 'data:'.$mime.';base64,'.base64_encode($bytes);
}

/**
 * Food images for the login page hero collage (from active POS products).
 *
 * @return list<string>
 */
function login_page_food_collage(int $limit = 6): array
{
    try {
        $cid = \App\Models\Company::query()->where('active', true)->orderBy('id')->value('id');
        if ($cid === null) {
            return [];
        }

        return \App\Models\InventoryProduct::query()
            ->where('company_id', $cid)
            ->where('active', true)
            ->whereNotNull('image_path')
            ->where('image_path', '!=', '')
            ->inRandomOrder()
            ->limit($limit)
            ->pluck('image_path')
            ->map(fn (string $path) => asset('storage/'.$path))
            ->values()
            ->all();
    } catch (\Throwable) {
        return [];
    }
}

/** @return list<string> All 192.168.x.x on this PC. */
function local_lan_ips(): array
{
    $ips = [];

    if (PHP_OS_FAMILY === 'Windows') {
        $output = (string) @shell_exec('ipconfig');
        if (preg_match_all('/IPv4 Address[^:\r\n]*:\s*(192\.168\.\d+\.\d+)/', $output, $m)) {
            $ips = array_values(array_unique($m[1]));
        }
    }

    return $ips;
}

/** First 192.168.x.x on this PC (WiFi). */
function local_lan_ip(): ?string
{
    $ips = local_lan_ips();

    return $ips[0] ?? null;
}

/** @return list<string> http://IP/ for each LAN adapter. */
function mobile_app_urls(): array
{
    return array_map(fn (string $ip) => 'http://'.$ip.'/', local_lan_ips());
}

/** Short URL for phones on the same WiFi (Apache port 80). */
function mobile_app_url(): ?string
{
    $urls = mobile_app_urls();

    return $urls[0] ?? null;
}
