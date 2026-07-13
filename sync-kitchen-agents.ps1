# Force re-queue Kitchen Agents (department printers + cashier) and push to cloud.
$ErrorActionPreference = 'Continue'
Set-Location -Path $PSScriptRoot

$php = 'php'
if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    $cand = Get-ChildItem 'C:\laragon\bin\php' -Directory -ErrorAction SilentlyContinue |
        Sort-Object Name -Descending | Select-Object -First 1
    if ($cand) { $php = Join-Path $cand.FullName 'php.exe' }
}

Write-Host "=== Kitchen Agents -> Cloud sync ===" -ForegroundColor Cyan
Write-Host "1) Status pehle..." -ForegroundColor Yellow
& $php artisan sync:cloud --status

Write-Host ""
Write-Host "2) Re-queue department printers + cashier settings, phir push..." -ForegroundColor Yellow
& $php artisan sync:repair --queue-kitchen-agents --push

Write-Host ""
Write-Host "3) Status baad mein..." -ForegroundColor Yellow
& $php artisan sync:cloud --status

Write-Host ""
Write-Host "Done. Cloud page refresh karein: Inventory -> Kitchen Agents" -ForegroundColor Green
Write-Host "Agar ab bhi empty ho to pehle latest code FTP se hosting par deploy karein," -ForegroundColor Yellow
Write-Host "phir yeh bat dobara chalaein (cloud pe printer columns banani padti hain)."
