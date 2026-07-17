# Cafe server PC ko fixed LAN IP deta hai taake mobile/tablet hamesha same address use karein.
# Run as Administrator: double-click set-cafe-lan-ip.bat
#   ya: cd C:\laragon\www\signature\scripts ; .\set-cafe-lan-ip.ps1
#
# Signature LAN URL = http://IP  (Laragon port 80) ya http://IP:8080

$ErrorActionPreference = 'Stop'

# --- Apni zaroorat par change karein (warna auto-detect bhi chalega) ---
$AdapterName = 'Ethernet'       # aapke PC par: Ethernet
$StaticIp    = '192.168.1.100'    # free IP — 192.168.1.50 printer ki hai, mat use karein
$Gateway     = '192.168.1.1'
$Dns1        = '192.168.1.1'
$Dns2        = '8.8.8.8'
$HttpPort    = 80               # Laragon 8080 par ho to 8080 likhein
# -----------------------------------------------------------------------

$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host 'ERROR: Admin PowerShell chahiye. set-cafe-lan-ip.bat par right-click -> Run as administrator' -ForegroundColor Red
    exit 1
}

function Resolve-AdapterName {
    param([string]$Preferred)

    $preferred = $Preferred.Trim()
    if ($preferred -ne '') {
        $hit = Get-NetAdapter -Name $preferred -ErrorAction SilentlyContinue
        if ($hit -and $hit.Status -eq 'Up') {
            return $hit.Name
        }
    }

    $up = @(Get-NetAdapter | Where-Object Status -eq 'Up' | Sort-Object Name)
    if ($up.Count -eq 1) {
        Write-Host "Adapter auto-detect: '$($up[0].Name)'" -ForegroundColor DarkGray
        return $up[0].Name
    }

    Write-Host "Adapter '$Preferred' nahi mila. Available (Up):" -ForegroundColor Yellow
    $up | Format-Table Name, InterfaceDescription, Status
    Write-Host "Script ke top par `$AdapterName sahi naam set karein." -ForegroundColor Yellow
    exit 1
}

$AdapterName = Resolve-AdapterName -Preferred $AdapterName

if ($Gateway -eq '192.168.1.1') {
    try {
        $routeGw = (Get-NetRoute -InterfaceAlias $AdapterName -DestinationPrefix '0.0.0.0/0' -ErrorAction Stop | Select-Object -First 1).NextHop
        if ($routeGw) {
            $Gateway = $routeGw
            if ($Dns1 -eq '192.168.1.1') { $Dns1 = $Gateway }
        }
    } catch {
        # keep configured gateway
    }
}

$BaseUrl = if ($HttpPort -eq 80) { "http://${StaticIp}" } else { "http://${StaticIp}:$HttpPort" }

Write-Host ''
Write-Host "Cafe LAN setup - fixed IP: $StaticIp on '$AdapterName'" -ForegroundColor Cyan
Write-Host "Gateway: $Gateway" -ForegroundColor DarkGray

$ping = Test-Connection -ComputerName $StaticIp -Count 1 -Quiet -ErrorAction SilentlyContinue
if ($ping) {
    Write-Host "WARNING: $StaticIp par koi device reply kar raha hai — IP conflict ho sakta hai." -ForegroundColor Yellow
    $cont = Read-Host 'Phir bhi set karein? (y/N)'
    if ($cont -notmatch '^[yY]') { exit 0 }
} else {
    Write-Host "OK: $StaticIp par koi reply nahi — IP free lag rahi hai." -ForegroundColor Green
}

Write-Host "Setting static IP on '$AdapterName'..." -ForegroundColor Green
netsh interface ip set address name="$AdapterName" static $StaticIp 255.255.255.0 $Gateway
netsh interface ip set dns name="$AdapterName" static $Dns1
netsh interface ip add dns name="$AdapterName" $Dns2 index=2 2>$null

$ports = @($HttpPort) + @(8080, 80) | Select-Object -Unique
foreach ($port in $ports) {
    $ruleName = "Signature Laragon HTTP (LAN port $port)"
    if (-not (Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue)) {
        New-NetFirewallRule -DisplayName $ruleName -Direction Inbound -Protocol TCP -LocalPort $port -Action Allow -Profile Private,Domain | Out-Null
        Write-Host "Firewall: port $port allow." -ForegroundColor Green
    }
}

Write-Host ''
Write-Host 'Done. Mobile / tablet URLs (same WiFi / LAN):' -ForegroundColor Cyan
Write-Host "  Order Taker app : $BaseUrl"
Write-Host "  Order Taker web : ${BaseUrl}/order-taker"
Write-Host "  POS             : ${BaseUrl}/pos"
Write-Host "  Kitchen         : ${BaseUrl}/kitchen"
Write-Host ''
Write-Host "Settings -> System -> LAN Server IP mein bhi yahi IP save karein." -ForegroundColor Yellow
Write-Host "Router mein DHCP reservation: is PC ka MAC -> $StaticIp" -ForegroundColor Yellow
