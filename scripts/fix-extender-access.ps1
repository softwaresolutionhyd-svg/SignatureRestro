# Extender se phone/tablet access fix — Admin PowerShell se chalao.
#   scripts\fix-extender-access.bat  (double-click, Admin Yes)

$ErrorActionPreference = 'Stop'
$SignaturePort = 8080
$ruleName8080  = "Signature Laragon HTTP (port $SignaturePort)"
$ruleName80    = 'Signature Laragon HTTP (port 80 LAN)'
$HttpdExe      = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\bin\httpd.exe'

Write-Host ''
Write-Host '=== Signature — Extender / Phone access fix ===' -ForegroundColor Cyan
Write-Host ''

# 1) Apache 8080 + port 80 IP vhost
$apacheScript = Join-Path $PSScriptRoot 'enable-signature-apache-8080.ps1'
if (Test-Path $apacheScript) {
    & $apacheScript
}

$serverIp = Get-NetIPAddress -AddressFamily IPv4 |
    Where-Object { $_.IPAddress -match '^192\.168\.' } |
    Select-Object -ExpandProperty IPAddress -First 1

if (-not $serverIp) {
    Write-Host '[ERROR] 192.168.x.x IP nahi mili — PC router/Ethernet se connect karein.' -ForegroundColor Red
    exit 1
}

$vhostTemplate = Join-Path $PSScriptRoot 'apache\signature-lan-80.conf'
$vhostDest     = 'C:\laragon\etc\apache2\sites-enabled\00-signature-lan-ip.conf'
if (Test-Path $vhostTemplate) {
    $vhostContent = (Get-Content $vhostTemplate -Raw).Replace('SERVER_IP', $serverIp)
    Set-Content -Path $vhostDest -Value $vhostContent.TrimEnd() -NoNewline
    Write-Host "[OK] Apache port 80 vhost for IP $serverIp installed." -ForegroundColor Green
}

# Apache restart (hamesha — taake phone par turant chale)
if (Test-Path $HttpdExe) {
    $conf = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\conf\httpd.conf'
    Stop-Process -Name httpd -Force -ErrorAction SilentlyContinue
    Start-Sleep -Seconds 2
    Start-Process -FilePath $HttpdExe -ArgumentList '-f', $conf -WindowStyle Hidden | Out-Null
    Start-Sleep -Seconds 2
    Write-Host '[OK] Apache restarted.' -ForegroundColor Green
}

Write-Host ''

# 2) Firewall — ALL profiles + httpd.exe
function Ensure-FirewallPort {
    param([string]$Name, [int]$Port)
    $existing = Get-NetFirewallRule -DisplayName $Name -ErrorAction SilentlyContinue
    if ($existing) {
        Set-NetFirewallRule -DisplayName $Name -Enabled True -Action Allow -Profile Any | Out-Null
    } else {
        New-NetFirewallRule -DisplayName $Name -Direction Inbound -Protocol TCP -LocalPort $Port -Action Allow -Profile Any | Out-Null
    }
    Write-Host "[OK] Firewall port $Port allow (all profiles)." -ForegroundColor Green
}

Ensure-FirewallPort -Name $ruleName8080 -Port 8080
Ensure-FirewallPort -Name $ruleName80 -Port 80

$appRule = 'Signature Apache httpd.exe (LAN)'
if (-not (Get-NetFirewallRule -DisplayName $appRule -ErrorAction SilentlyContinue)) {
    New-NetFirewallRule -DisplayName $appRule -Direction Inbound -Program $HttpdExe -Action Allow -Profile Any | Out-Null
    Write-Host '[OK] Firewall: httpd.exe allow (all profiles).' -ForegroundColor Green
} else {
    Set-NetFirewallRule -DisplayName $appRule -Enabled True -Action Allow -Profile Any | Out-Null
    Write-Host '[OK] Firewall httpd.exe rule updated.' -ForegroundColor Green
}

# 3) URLs
$url80  = "http://${serverIp}/"
$url8080 = "http://${serverIp}:8080/"
Write-Host ''
Write-Host '=== PHONE PAR YE URL LIKHO (extender IP NAHI) ===' -ForegroundColor Yellow
Write-Host ''
Write-Host "  Server PC IP: $serverIp" -ForegroundColor Cyan
Write-Host "  Option A: $url80" -ForegroundColor Green
Write-Host "  Option B: $url8080" -ForegroundColor Green
Write-Host "  POS:      ${url80}restaurant-pos" -ForegroundColor White
Write-Host ''
Write-Host '  GALAT (refused aayega):' -ForegroundColor Red
Write-Host '    http://192.168.1.57   <- extender 1'
Write-Host '    http://192.168.1.245  <- extender 2'
Write-Host '    http://192.168.3.50   <- purana IP'

# 4) Local test
foreach ($testUrl in @($url80, $url8080)) {
    try {
        $code = (Invoke-WebRequest -Uri $testUrl -UseBasicParsing -TimeoutSec 5).StatusCode
        Write-Host "[OK] Test $testUrl -> HTTP $code" -ForegroundColor Green
    } catch {
        Write-Host "[FAIL] Test $testUrl -> $($_.Exception.Message)" -ForegroundColor Red
    }
}

# 5) .env
$envFile = Join-Path (Split-Path $PSScriptRoot -Parent) '.env'
if (Test-Path $envFile) {
    $content = Get-Content $envFile -Raw
    $content = $content -replace '(?m)^LAN_SERVER_IP=.*$', "LAN_SERVER_IP=$serverIp"
    $content = $content -replace '(?m)^LAN_SERVER_URL=.*$', "LAN_SERVER_URL=http://${serverIp}:8080"
    Set-Content -Path $envFile -Value $content.TrimEnd() -NoNewline
    Write-Host '[OK] .env updated.' -ForegroundColor Green
}

Write-Host ''
Write-Host 'Phone browser mein copy-paste karo (http zaroor likho, https NAHI):' -ForegroundColor Cyan
Write-Host "  $url80"
Write-Host ''
