# ============================================================
#  Signature POS - Speed Optimize
#  Config/view/event cache + optimized autoloader.
#  (route:cache skip kiya gaya hai kyunke kuch closure routes hain.)
#  Chalane ka tareeqa: optimize.bat double-click karein.
#  NOTE: .env ya config change karne ke baad dobara chalayein.
# ============================================================

$ErrorActionPreference = 'Continue'
Set-Location -Path $PSScriptRoot

# PHP + Composer dhoondo (Laragon)
$php = 'php'
if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    $cand = Get-ChildItem 'C:\laragon\bin\php' -Directory -ErrorAction SilentlyContinue |
        Sort-Object Name -Descending | Select-Object -First 1
    if ($cand) { $php = Join-Path $cand.FullName 'php.exe' }
}

$composer = $null
if (Get-Command composer -ErrorAction SilentlyContinue) { $composer = 'composer' }
elseif (Test-Path 'C:\laragon\bin\composer\composer.phar') { $composer = "$php C:\laragon\bin\composer\composer.phar" }

Write-Host "=== Signature POS: Optimize shuru ===" -ForegroundColor Cyan

Write-Host "-> Migrations (naye performance indexes apply)..." -ForegroundColor Yellow
& $php artisan migrate --force

Write-Host "-> Purani cache clear..." -ForegroundColor Yellow
& $php artisan optimize:clear

if ($composer) {
    Write-Host "-> Optimized autoloader (composer dump-autoload -o)..." -ForegroundColor Yellow
    Invoke-Expression "$composer dump-autoload -o --no-interaction"
} else {
    Write-Host "!! composer nahi mila - autoloader optimize skip. (Laragon Terminal se: composer dump-autoload -o)" -ForegroundColor Red
}

Write-Host "-> config:cache" -ForegroundColor Yellow
& $php artisan config:cache
Write-Host "-> view:cache" -ForegroundColor Yellow
& $php artisan view:cache
Write-Host "-> event:cache" -ForegroundColor Yellow
& $php artisan event:cache

# route:cache jaan-boojh kar skip (closure routes fail kar dete hain)

Write-Host ""
Write-Host "=== Ho gaya. Software ab tez chalega. ===" -ForegroundColor Green
Write-Host ""
Write-Host "OPcache (aur bhi tez ke liye) - Laragon php.ini me yeh set karein:" -ForegroundColor Cyan
Write-Host "   opcache.enable=1"
Write-Host "   opcache.enable_cli=1"
Write-Host "   opcache.memory_consumption=256"
Write-Host "   opcache.max_accelerated_files=30000"
Write-Host "   opcache.validate_timestamps=0   (production/stable ke liye)"
Write-Host "   (php.ini: Laragon Menu -> PHP -> php.ini; save ke baad Laragon/serve restart)"
Write-Host ""
