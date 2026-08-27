# Signature Cafe — keep LAN software reachable (no internet needed)
# Run as Admin once: scripts\install-cafe-lan-keepalive.bat
# Also runs every boot + every 30 min via Task Scheduler.

$ErrorActionPreference = 'Continue'
$Httpd = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\bin\httpd.exe'
$LogDir = Join-Path $PSScriptRoot '..\storage\logs'
$LogFile = Join-Path $LogDir 'cafe-lan-keepalive.log'

function Write-Log([string]$msg) {
    $line = "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')  $msg"
    try {
        if (-not (Test-Path $LogDir)) { New-Item -ItemType Directory -Path $LogDir -Force | Out-Null }
        Add-Content -Path $LogFile -Value $line -ErrorAction SilentlyContinue
    } catch {}
    Write-Host $line
}

# 1) Network must be Private (Public blocks many inbound LAN cases)
Get-NetConnectionProfile -ErrorAction SilentlyContinue | ForEach-Object {
    if ($_.NetworkCategory -ne 'Private') {
        Set-NetConnectionProfile -InterfaceIndex $_.InterfaceIndex -NetworkCategory Private -ErrorAction SilentlyContinue
        Write-Log "Network '$($_.Name)' -> Private"
    }
}

# 2) Firewall ports always open
function Ensure-PortRule([string]$Name, [int]$Port) {
    $rule = Get-NetFirewallRule -DisplayName $Name -ErrorAction SilentlyContinue
    if ($rule) {
        Set-NetFirewallRule -DisplayName $Name -Enabled True -Action Allow -Profile Any -ErrorAction SilentlyContinue | Out-Null
    } else {
        New-NetFirewallRule -DisplayName $Name -Direction Inbound -Protocol TCP -LocalPort $Port -Action Allow -Profile Any -ErrorAction SilentlyContinue | Out-Null
    }
}

foreach ($p in @(80, 8080, 443, 9100)) {
    Ensure-PortRule -Name "Signature Cafe LAN (port $p)" -Port $p
}

if (Test-Path $Httpd) {
    $appRule = 'Signature Apache httpd.exe (LAN)'
    if (-not (Get-NetFirewallRule -DisplayName $appRule -ErrorAction SilentlyContinue)) {
        New-NetFirewallRule -DisplayName $appRule -Direction Inbound -Program $Httpd -Action Allow -Profile Any -ErrorAction SilentlyContinue | Out-Null
    } else {
        Set-NetFirewallRule -DisplayName $appRule -Enabled True -Action Allow -Profile Any -ErrorAction SilentlyContinue | Out-Null
    }
}

Enable-NetFirewallRule -DisplayGroup 'Network Discovery' -ErrorAction SilentlyContinue | Out-Null
Enable-NetFirewallRule -DisplayGroup 'File and Printer Sharing' -ErrorAction SilentlyContinue | Out-Null

# 3) Ensure Apache listening on :80
$listening = Get-NetTCPConnection -LocalPort 80 -State Listen -ErrorAction SilentlyContinue
if (-not $listening) {
    Write-Log 'Apache port 80 NOT listening — restarting httpd'
    Stop-Process -Name httpd -Force -ErrorAction SilentlyContinue
    Start-Sleep -Seconds 1
    if (Test-Path $Httpd) {
        $conf = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\conf\httpd.conf'
        Start-Process -FilePath $Httpd -ArgumentList '-f', $conf -WindowStyle Hidden | Out-Null
        Start-Sleep -Seconds 2
    }
} else {
    Write-Log 'Apache :80 OK'
}

$ip = (Get-NetIPAddress -AddressFamily IPv4 |
    Where-Object { $_.IPAddress -match '^192\.168\.' } |
    Select-Object -ExpandProperty IPAddress -First 1)

Write-Log ("LAN IP=" + ($(if ($ip) { $ip } else { 'NONE' })) + "  URL=http://$ip/order-taker")
