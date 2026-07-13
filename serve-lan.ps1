# ============================================================
#  Signature POS - LAN Server Launcher
#  Doosre devices (same Wi-Fi/router) se software chalane ke liye.
#  Chalane ka tareeqa: serve-lan.bat ko double-click karein.
# ============================================================

$ErrorActionPreference = 'SilentlyContinue'
$port = 8080
Set-Location -Path $PSScriptRoot

# 1) Current LAN IPv4 auto-detect (192.168.x / 10.x / 172.16-31.x)
$ip = (Get-NetIPAddress -AddressFamily IPv4 |
    Where-Object {
        $_.IPAddress -match '^(192\.168\.|10\.|172\.(1[6-9]|2\d|3[01])\.)' -and
        $_.InterfaceAlias -notmatch 'Loopback|vEthernet|VirtualBox|VMware'
    } |
    Sort-Object InterfaceMetric |
    Select-Object -First 1).IPAddress

if (-not $ip) { $ip = '127.0.0.1' }

# 2) PHP dhoondo (Laragon) agar PATH me na ho
$php = 'php'
if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    $cand = Get-ChildItem 'C:\laragon\bin\php' -Directory -ErrorAction SilentlyContinue |
        Sort-Object Name -Descending | Select-Object -First 1
    if ($cand) { $php = Join-Path $cand.FullName 'php.exe' }
}

# 3) Windows Firewall me port allow karo (best-effort; admin ho to chalega)
netsh advfirewall firewall add rule name="Signature POS $port" dir=in action=allow protocol=TCP localport=$port 2>$null | Out-Null

Write-Host ""
Write-Host "=====================================================" -ForegroundColor Green
Write-Host "  SIGNATURE POS  -  LAN par chal raha hai" -ForegroundColor Green
Write-Host "=====================================================" -ForegroundColor Green
Write-Host "  Is PC par:        http://localhost:$port"
Write-Host "  Doosre devices:   http://$ip`:$port" -ForegroundColor Yellow
Write-Host "  (Sab devices same Wi-Fi / router par hone chahiyein)"
Write-Host "-----------------------------------------------------"
Write-Host "  Band karne ke liye: yeh window close karein (Ctrl+C)"
Write-Host "=====================================================" -ForegroundColor Green
Write-Host ""

& $php artisan serve --host=0.0.0.0 --port=$port
