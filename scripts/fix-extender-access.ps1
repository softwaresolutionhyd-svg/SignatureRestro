# Extender se phone/tablet access fix — Admin PowerShell se chalao.
#   scripts\fix-extender-access.bat  (double-click, Admin Yes)

$ErrorActionPreference = 'Stop'
$SignaturePort = 8080
$ruleName      = "Signature Laragon HTTP (port $SignaturePort)"

Write-Host ''
Write-Host '=== Signature — Extender / Phone access fix ===' -ForegroundColor Cyan
Write-Host ''

# 1) Apache 8080
$apacheScript = Join-Path $PSScriptRoot 'enable-signature-apache-8080.ps1'
if (Test-Path $apacheScript) {
    & $apacheScript
}

Write-Host ''

# 2) Firewall — ALL profiles (kabhi extender network Public treat hota hai)
$existing = Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue
if ($existing) {
    Set-NetFirewallRule -DisplayName $ruleName -Enabled True -Action Allow -Profile Any | Out-Null
    Write-Host "[OK] Firewall rule updated: port $SignaturePort, ALL profiles." -ForegroundColor Green
} else {
    New-NetFirewallRule `
        -DisplayName $ruleName `
        -Direction Inbound `
        -Protocol TCP `
        -LocalPort $SignaturePort `
        -Action Allow `
        -Profile Any `
        | Out-Null
    Write-Host "[OK] Firewall rule created: port $SignaturePort, ALL profiles." -ForegroundColor Green
}

# Extra: Windows sometimes blocks "File and Printer Sharing" path — direct port rule is enough.

# 3) Server IP
$serverIp = Get-NetIPAddress -AddressFamily IPv4 |
    Where-Object { $_.IPAddress -match '^192\.168\.' } |
    Select-Object -ExpandProperty IPAddress -First 1

if (-not $serverIp) {
    Write-Host '[ERROR] 192.168.x.x IP nahi mili — PC router/Ethernet se connect karein.' -ForegroundColor Red
    exit 1
}

$baseUrl = "http://${serverIp}:$SignaturePort"
Write-Host ''
Write-Host 'Server PC URL (phone/tablet par yehi likho):' -ForegroundColor Green
Write-Host "  $baseUrl/" -ForegroundColor White
Write-Host "  POS: $baseUrl/restaurant-pos"

# 4) Local test
try {
    $code = (Invoke-WebRequest -Uri "$baseUrl/" -UseBasicParsing -TimeoutSec 5).StatusCode
    Write-Host ''
    Write-Host "[OK] Server se test: $baseUrl/ -> HTTP $code" -ForegroundColor Green
} catch {
    Write-Host ''
    Write-Host "[FAIL] Server se bhi open nahi: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host '       Laragon: Apache GREEN karein, phir dubara script chalao.' -ForegroundColor Yellow
}

# 5) .env
$envFile = Join-Path (Split-Path $PSScriptRoot -Parent) '.env'
if (Test-Path $envFile) {
    $content = Get-Content $envFile -Raw
    $content = $content -replace '(?m)^LAN_SERVER_IP=.*$', "LAN_SERVER_IP=$serverIp"
    $content = $content -replace '(?m)^LAN_SERVER_URL=.*$', "LAN_SERVER_URL=$baseUrl"
    Set-Content -Path $envFile -Value $content.TrimEnd() -NoNewline
    Write-Host "[OK] .env updated." -ForegroundColor Green
}

Write-Host ''
Write-Host '--- Phone par check karein ---' -ForegroundColor Yellow
Write-Host "1. WiFi Settings -> IP dekho: 192.168.1.x hona chahiye (server: $serverIp)"
Write-Host '2. Agar IP 192.168.2.x / 192.168.10.x / 10.x hai -> extender ROUTER mode mein hai (galat).'
Write-Host '3. Extender: Access Point / Repeater mode + AP Isolation OFF.'
Write-Host '4. Router: Guest WiFi NA use karein; Wireless Isolation OFF.'
Write-Host "5. Browser: $baseUrl/  (192.168.3.50 mat likho jab tak fixed IP na ho)"
Write-Host ''
Write-Host 'Phone se ping (optional): Settings mein IP same subnet hai ya nahi dekho.' -ForegroundColor Cyan
Write-Host ''
