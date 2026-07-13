# ============================================================
#  Signature POS - Clear caches (development / troubleshooting)
#  Jab .env, config, ya routes change karein to yeh chalayein,
#  ya agar optimize ke baad koi purani value stuck lage.
# ============================================================

$ErrorActionPreference = 'Continue'
Set-Location -Path $PSScriptRoot

$php = 'php'
if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    $cand = Get-ChildItem 'C:\laragon\bin\php' -Directory -ErrorAction SilentlyContinue |
        Sort-Object Name -Descending | Select-Object -First 1
    if ($cand) { $php = Join-Path $cand.FullName 'php.exe' }
}

Write-Host "=== Signature POS: Saari cache clear ho rahi hai ===" -ForegroundColor Cyan
& $php artisan optimize:clear
& $php artisan config:clear
& $php artisan cache:clear
& $php artisan view:clear
& $php artisan route:clear
& $php artisan event:clear
Write-Host "=== Ho gaya. ===" -ForegroundColor Green
