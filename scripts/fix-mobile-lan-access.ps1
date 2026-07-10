# Mobile se access na ho to ye script Admin se chalao
# scripts\fix-mobile-lan-access.bat

$ErrorActionPreference = 'Stop'

Write-Host ''
Write-Host '=== Mobile LAN access fix ===' -ForegroundColor Cyan
Write-Host ''

# 1) Network Private (Public profile mobile block karta hai)
Get-NetConnectionProfile | ForEach-Object {
    if ($_.NetworkCategory -ne 'Private') {
        Set-NetConnectionProfile -InterfaceIndex $_.InterfaceIndex -NetworkCategory Private
        Write-Host "[OK] Network '$($_..Name)' -> Private" -ForegroundColor Green
    } else {
        Write-Host "[OK] Network '$($_.Name)' already Private" -ForegroundColor DarkGray
    }
}

# 2) Firewall ports
foreach ($port in @(80, 8080)) {
    $name = "Signature Laragon HTTP (port $port LAN)"
    $rule = Get-NetFirewallRule -DisplayName $name -ErrorAction SilentlyContinue
    if ($rule) {
        Set-NetFirewallRule -DisplayName $name -Enabled True -Action Allow -Profile Any | Out-Null
    } else {
        New-NetFirewallRule -DisplayName $name -Direction Inbound -Protocol TCP -LocalPort $port -Action Allow -Profile Any | Out-Null
    }
    Write-Host "[OK] Firewall port $port allow" -ForegroundColor Green
}

# 3) httpd.exe
$httpd = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\bin\httpd.exe'
$appRule = 'Signature Apache httpd.exe (LAN)'
if (Test-Path $httpd) {
    if (-not (Get-NetFirewallRule -DisplayName $appRule -ErrorAction SilentlyContinue)) {
        New-NetFirewallRule -DisplayName $appRule -Direction Inbound -Program $httpd -Action Allow -Profile Any | Out-Null
    } else {
        Set-NetFirewallRule -DisplayName $appRule -Enabled True -Action Allow -Profile Any | Out-Null
    }
    Write-Host '[OK] Firewall httpd.exe allow' -ForegroundColor Green
}

# 4) File and Printer Sharing (kabhi mobile ke liye zaroori)
$fps = 'File and Printer Sharing (Echo Request - ICMPv4-In)'
if (Get-NetFirewallRule -DisplayName $fps -ErrorAction SilentlyContinue) {
    Enable-NetFirewallRule -DisplayName $fps -ErrorAction SilentlyContinue | Out-Null
    Write-Host '[OK] Ping (ICMP) allow for LAN test' -ForegroundColor Green
}

$serverIp = (Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.IPAddress -match '^192\.168\.' } | Select-Object -First 1).IPAddress

Write-Host ''
Write-Host "Server IP: $serverIp" -ForegroundColor Cyan
Write-Host "Mobile par ye URL:" -ForegroundColor Yellow
Write-Host "  http://${serverIp}/lan-test.html"
Write-Host ''
Write-Host 'Agar phir bhi na chale -> modem WiFi wired PC ko block kar raha hai.' -ForegroundColor Yellow
Write-Host 'Fix: Server cable extender ke LAN port mein lagao.' -ForegroundColor Yellow
Write-Host ''
