# Local speed — Laravel cache + clear stale views
# Run from project root: scripts\optimize-local.bat

$ErrorActionPreference = 'Stop'
$Root = Split-Path $PSScriptRoot -Parent

Push-Location $Root
try {
    Write-Host '=== Signature local optimize ===' -ForegroundColor Cyan
    php artisan config:clear
    php artisan view:clear
    php artisan cache:clear
    php artisan config:cache
    php artisan view:cache
    php artisan route:cache
    Write-Host '[OK] Config, views, routes cached.' -ForegroundColor Green
    Write-Host 'Tip: Laragon Stop All -> Start All after this.' -ForegroundColor Yellow
} finally {
    Pop-Location
}
