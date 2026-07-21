# Cafe server IP permanently lock — sirf is PC par 192.168.1.105, DHCP band.
# Run as Administrator: lock-cafe-server-ip.bat
#
# 1) Static IP + DHCP off on Ethernet
# 2) Startup/logon watchdog (IP wapas set ho jae agar change ho)
# 3) Router reservation guide (MAC -> IP)

$ErrorActionPreference = 'Stop'

$ConfigPath  = Join-Path $PSScriptRoot 'cafe-lan-config.json'
$WatchScript = Join-Path $PSScriptRoot 'watch-cafe-server-ip.ps1'
$EnvFile     = Join-Path (Split-Path $PSScriptRoot -Parent) '.env'
$RouterGuide = Join-Path $PSScriptRoot 'ROUTER-DHCP-RESERVATION.txt'

$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host 'ERROR: Admin chahiye. lock-cafe-server-ip.bat par right-click -> Run as administrator' -ForegroundColor Red
    exit 1
}

if (-not (Test-Path $ConfigPath)) {
    Write-Host "ERROR: Config missing: $ConfigPath" -ForegroundColor Red
    exit 1
}

$config   = Get-Content $ConfigPath -Raw | ConvertFrom-Json
$adapter  = [string]$config.adapter
$staticIp = [string]$config.static_ip
$gateway  = [string]$config.gateway
$mask     = [string]$config.subnet_mask
$dns      = @($config.dns)
$taskName = [string]$config.task_name
$httpPort = [int]$config.http_port

function Resolve-AdapterName {
    param([string]$Preferred)
    $hit = Get-NetAdapter -Name $Preferred -ErrorAction SilentlyContinue
    if ($hit -and $hit.Status -eq 'Up') { return $hit.Name }
    $up = @(Get-NetAdapter | Where-Object Status -eq 'Up' | Sort-Object Name)
    if ($up.Count -eq 1) { return $up[0].Name }
    Write-Host "Adapter '$Preferred' nahi mila." -ForegroundColor Red
    $up | Format-Table Name, MacAddress, Status
    exit 1
}

function Test-IpUsedByOtherDevice {
    param([string]$TargetIp, [string]$AdapterName)

    $myMac = (Get-NetAdapter -Name $AdapterName -ErrorAction SilentlyContinue).MacAddress
    if (-not (Test-Connection -ComputerName $TargetIp -Count 1 -Quiet -ErrorAction SilentlyContinue)) {
        return $false
    }

    $myIp = (Get-NetIPAddress -InterfaceAlias $AdapterName -AddressFamily IPv4 -ErrorAction SilentlyContinue |
        Where-Object { $_.PrefixOrigin -ne 'WellKnown' } | Select-Object -First 1).IPAddress
    if ($myIp -eq $TargetIp) { return $false }

    $null = ping -n 1 $TargetIp 2>$null
    Start-Sleep -Milliseconds 400
    $arpLine = arp -a $TargetIp 2>$null | Select-String '([0-9a-f]{2}-){5}[0-9a-f]{2}' -AllMatches
    if (-not $arpLine) { return $true }

    $remoteMac = ($arpLine.Matches | Select-Object -Last 1).Value -replace '-', ''
    $localMac  = ($myMac -replace '-', '').ToUpper()
    return ($remoteMac.ToUpper() -ne $localMac)
}

function Set-StaticNetwork {
    param([string]$AdapterName)

    Write-Host "Static IP set kar rahe hain: $staticIp (DHCP OFF)..." -ForegroundColor Green

    Set-NetIPInterface -InterfaceAlias $AdapterName -Dhcp Disabled -AddressFamily IPv4 | Out-Null

    Get-NetIPAddress -InterfaceAlias $AdapterName -AddressFamily IPv4 -ErrorAction SilentlyContinue |
        Where-Object { $_.PrefixOrigin -ne 'WellKnown' } |
        ForEach-Object {
            Remove-NetIPAddress -InterfaceAlias $AdapterName -IPAddress $_.IPAddress -Confirm:$false -ErrorAction SilentlyContinue | Out-Null
        }

    New-NetIPAddress -InterfaceAlias $AdapterName -IPAddress $staticIp -PrefixLength 24 -DefaultGateway $gateway -ErrorAction Stop | Out-Null

    Set-DnsClientServerAddress -InterfaceAlias $AdapterName -ServerAddresses $dns -ErrorAction SilentlyContinue | Out-Null
}

function Install-WatchdogTask {
    param([string]$TaskName, [string]$ScriptPath)

    $existing = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
    if ($existing) {
        Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
    }

    $action = New-ScheduledTaskAction `
        -Execute 'powershell.exe' `
        -Argument "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$ScriptPath`""

    $triggers = @(
        (New-ScheduledTaskTrigger -AtStartup),
        (New-ScheduledTaskTrigger -AtLogon),
        (New-ScheduledTaskTrigger -Once -At (Get-Date).Date.AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 10) -RepetitionDuration (New-TimeSpan -Days 9999))
    )

    $settings = New-ScheduledTaskSettingsSet `
        -AllowStartIfOnBatteries `
        -DontStopIfGoingOnBatteries `
        -StartWhenAvailable `
        -ExecutionTimeLimit (New-TimeSpan -Minutes 2)

    $principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest

    Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $triggers -Settings $settings -Principal $principal -Description 'Signature cafe server — IP 192.168.1.105 lock' | Out-Null
    Write-Host "[OK] Watchdog task installed: $TaskName (startup + har 10 min check)" -ForegroundColor Green
}

function Write-RouterGuide {
    param([string]$Path, [string]$Mac, [string]$Ip, [string]$Gw)

    $macRouter = ($Mac -replace '-', ':').ToLower()
    $text = @"
Signature Cafe Server — Router DHCP Reservation (ZAROORI)
========================================================

Is PC ko SIRF yahi IP mile, doosri devices ko router se 105 na mile:

  MAC Address : $Mac  (router format: $macRouter)
  Reserved IP : $Ip
  Gateway     : $Gw

Router steps (192.168.1.1 browser mein):
  1. Login karein (admin password)
  2. DHCP / LAN / Address Reservation dhoondein
  3. Naya reservation add karein:
       Device MAC = $macRouter
       IP Address = $Ip
  4. DHCP pool check karein — agar range 192.168.1.100-200 hai to
     .105 ko exclude karein YA reservation ON rakhein
  5. Save + router reboot (agar option ho)

Is se:
  - Router doosri device ko .105 nahi dega
  - Sirf is cafe PC ko .105 milega (MAC binding)

Note: Agar koi device MANUALLY static .105 set kare to conflict ho sakta hai —
      router reservation + is PC par static IP sab se safe hai.

Mobile/tablet URL:
  http://${Ip}:${httpPort}/pos
  http://${Ip}:${httpPort}/order-taker

"@
    Set-Content -Path $Path -Value $text -Encoding UTF8
}

Write-Host ''
Write-Host '=== Signature Cafe Server IP Lock ===' -ForegroundColor Cyan
Write-Host ''

$adapter = Resolve-AdapterName -Preferred $adapter
$mac     = (Get-NetAdapter -Name $adapter).MacAddress

# Config mein MAC update (future reference)
$config.mac_address = $mac
$config | ConvertTo-Json | Set-Content -Path $ConfigPath -Encoding UTF8

if (Test-IpUsedByOtherDevice -TargetIp $staticIp -AdapterName $adapter) {
    Write-Host "ERROR: $staticIp abhi kisi AUR device par hai." -ForegroundColor Red
    Write-Host 'Pehle router mein DHCP reservation set karein (guide neeche banegi), phir dubara chalao.' -ForegroundColor Yellow
    Write-RouterGuide -Path $RouterGuide -Mac $mac -Ip $staticIp -Gw $gateway
    Write-Host "Guide: $RouterGuide" -ForegroundColor Yellow
    exit 1
}

try {
    Set-StaticNetwork -AdapterName $adapter
} catch {
    Write-Host 'New-NetIPAddress fail — netsh se try...' -ForegroundColor Yellow
    netsh interface ip set address name="$adapter" static $staticIp $mask $gateway | Out-Null
    netsh interface ip set dns name="$adapter" static $dns[0] | Out-Null
    for ($i = 1; $i -lt $dns.Count; $i++) {
        netsh interface ip add dns name="$adapter" $dns[$i] index=($i + 1) | Out-Null
    }
}

Start-Sleep -Seconds 2
$check = (Get-NetIPAddress -InterfaceAlias $adapter -AddressFamily IPv4 -ErrorAction SilentlyContinue |
    Where-Object { $_.IPAddress -eq $staticIp } | Select-Object -First 1)
if (-not $check) {
    Write-Host 'ERROR: Static IP set nahi hui.' -ForegroundColor Red
    exit 1
}

Write-Host "[OK] IP locked: $staticIp (PrefixOrigin: $($check.PrefixOrigin))" -ForegroundColor Green

$dhcpState = (Get-NetIPInterface -InterfaceAlias $adapter -AddressFamily IPv4).Dhcp
Write-Host "[OK] DHCP on Ethernet: $dhcpState" -ForegroundColor $(if ($dhcpState -eq 'Disabled') { 'Green' } else { 'Yellow' })

# Firewall
foreach ($port in @(80, 8080)) {
    $ruleName = "Signature Laragon HTTP (LAN port $port)"
    if (-not (Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue)) {
        New-NetFirewallRule -DisplayName $ruleName -Direction Inbound -Protocol TCP -LocalPort $port -Action Allow -Profile Any | Out-Null
    } else {
        Set-NetFirewallRule -DisplayName $ruleName -Enabled True -Action Allow -Profile Any | Out-Null
    }
}

Get-NetConnectionProfile | ForEach-Object {
    if ($_.NetworkCategory -ne 'Private') {
        Set-NetConnectionProfile -InterfaceIndex $_.InterfaceIndex -NetworkCategory Private | Out-Null
    }
}

Install-WatchdogTask -TaskName $taskName -ScriptPath $WatchScript

if (Test-Path $EnvFile) {
    $lanUrl = "http://${staticIp}:${httpPort}"
    $content = Get-Content $EnvFile -Raw
    $content = $content -replace '(?m)^LAN_SERVER_IP=.*$', "LAN_SERVER_IP=$staticIp"
    $content = $content -replace '(?m)^LAN_SERVER_URL=.*$', "LAN_SERVER_URL=$lanUrl"
    Set-Content -Path $EnvFile -Value $content.TrimEnd() -NoNewline
    Write-Host '[OK] .env updated' -ForegroundColor Green
}

Write-RouterGuide -Path $RouterGuide -Mac $mac -Ip $staticIp -Gw $gateway

Write-Host ''
Write-Host 'Done — is PC par IP permanently locked.' -ForegroundColor Green
Write-Host ''
Write-Host "  Server IP   : $staticIp" -ForegroundColor Cyan
Write-Host "  MAC         : $mac" -ForegroundColor Cyan
Write-Host "  POS URL     : http://${staticIp}:${httpPort}/pos" -ForegroundColor White
Write-Host ''
Write-Host 'IMPORTANT: Router mein bhi reservation set karein (doosri devices ko .105 na mile):' -ForegroundColor Yellow
Write-Host "  Guide file  : $RouterGuide" -ForegroundColor Yellow
Write-Host ''

if (Test-Connection -ComputerName 8.8.8.8 -Count 1 -Quiet) {
    Write-Host '[OK] Internet working.' -ForegroundColor Green
} else {
    Write-Host '[WARN] Internet test fail — gateway/DNS check karein.' -ForegroundColor Yellow
}

Write-Host ''
