# Background watchdog — cafe server IP hamesha 192.168.1.105 par rahe.
# lock-cafe-server-ip.ps1 isko Scheduled Task mein install karta hai.

$ErrorActionPreference = 'SilentlyContinue'

$ConfigPath = Join-Path $PSScriptRoot 'cafe-lan-config.json'
if (-not (Test-Path $ConfigPath)) { exit 0 }

$config = Get-Content $ConfigPath -Raw | ConvertFrom-Json
$adapter = [string]$config.adapter
$staticIp = [string]$config.static_ip
$gateway = [string]$config.gateway
$mask = [string]$config.subnet_mask
$dns = @($config.dns)

$ad = Get-NetAdapter -Name $adapter -ErrorAction SilentlyContinue
if (-not $ad -or $ad.Status -ne 'Up') { exit 0 }

$current = Get-NetIPAddress -InterfaceAlias $adapter -AddressFamily IPv4 -ErrorAction SilentlyContinue |
    Where-Object { $_.IPAddress -notlike '169.254.*' -and $_.PrefixOrigin -ne 'WellKnown' } |
    Select-Object -First 1

$needsFix = ($null -eq $current) -or ($current.IPAddress -ne $staticIp)

if (-not $needsFix) {
    $dhcp = (Get-NetIPInterface -InterfaceAlias $adapter -AddressFamily IPv4 -ErrorAction SilentlyContinue).Dhcp
    if ($dhcp -eq 'Enabled') { $needsFix = $true }
}

if (-not $needsFix) { exit 0 }

Set-NetIPInterface -InterfaceAlias $adapter -Dhcp Disabled -AddressFamily IPv4 | Out-Null

$others = Get-NetIPAddress -InterfaceAlias $adapter -AddressFamily IPv4 -ErrorAction SilentlyContinue |
    Where-Object { $_.IPAddress -ne $staticIp -and $_.PrefixOrigin -ne 'WellKnown' }
foreach ($addr in $others) {
    Remove-NetIPAddress -InterfaceAlias $adapter -IPAddress $addr.IPAddress -Confirm:$false -ErrorAction SilentlyContinue | Out-Null
}

$existing = Get-NetIPAddress -InterfaceAlias $adapter -AddressFamily IPv4 -ErrorAction SilentlyContinue |
    Where-Object { $_.IPAddress -eq $staticIp } | Select-Object -First 1

if (-not $existing) {
    New-NetIPAddress -InterfaceAlias $adapter -IPAddress $staticIp -PrefixLength 24 -DefaultGateway $gateway -ErrorAction SilentlyContinue | Out-Null
    if ($LASTEXITCODE -ne 0) {
        netsh interface ip set address name="$adapter" static $staticIp $mask $gateway | Out-Null
    }
}

$i = 1
foreach ($server in $dns) {
    $server = [string]$server
    if ($server -eq '') { continue }
    if ($i -eq 1) {
        Set-DnsClientServerAddress -InterfaceAlias $adapter -ServerAddresses $server -ErrorAction SilentlyContinue | Out-Null
    } else {
        $existingDns = (Get-DnsClientServerAddress -InterfaceAlias $adapter -AddressFamily IPv4 -ErrorAction SilentlyContinue).ServerAddresses
        if ($existingDns -notcontains $server) {
            Set-DnsClientServerAddress -InterfaceAlias $adapter -ServerAddresses (@($existingDns) + $server) -ErrorAction SilentlyContinue | Out-Null
        }
    }
    $i++
}

exit 0
