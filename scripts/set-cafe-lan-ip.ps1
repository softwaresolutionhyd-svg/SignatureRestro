# Cafe server PC ko fixed LAN IP deta hai taake mobile/tablet hamesha same address use karein.
# Run as Administrator: Right-click PowerShell -> Run as administrator
#   cd C:\laragon\www\Signature\scripts
#   .\set-cafe-lan-ip.ps1
#
# Signature LAN URL = http://IP:8080  (Softwaresolution alag port 80 par rehta hai)

$ErrorActionPreference = 'Stop'

$AdapterName = 'Wi-Fi 2'
$StaticIp    = '192.168.3.50'
$Gateway     = '192.168.3.1'
$Dns1        = '192.168.3.1'
$Dns2        = '8.8.8.8'
$BaseUrl     = "http://${StaticIp}:8080"

Write-Host "Cafe LAN setup - fixed IP: $StaticIp" -ForegroundColor Cyan

$adapter = Get-NetAdapter -Name $AdapterName -ErrorAction SilentlyContinue
if (-not $adapter) {
    Write-Host "Adapter '$AdapterName' nahi mila. Available adapters:" -ForegroundColor Yellow
    Get-NetAdapter | Where-Object Status -eq 'Up' | Format-Table Name, InterfaceDescription, Status
    Write-Host "Script ke top par `$AdapterName change karein (e.g. Wi-Fi)." -ForegroundColor Yellow
    exit 1
}

Write-Host "Setting static IP on '$AdapterName'..." -ForegroundColor Green
netsh interface ip set address name="$AdapterName" static $StaticIp 255.255.255.0 $Gateway
netsh interface ip set dns name="$AdapterName" static $Dns1
netsh interface ip add dns name="$AdapterName" $Dns2 index=2

$ruleName = 'Cafe Laragon HTTP (LAN)'
$existing = Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue
if (-not $existing) {
    New-NetFirewallRule -DisplayName $ruleName -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow | Out-Null
    Write-Host 'Firewall: port 80 allow (LAN).' -ForegroundColor Green
} else {
    Write-Host 'Firewall rule already exists.' -ForegroundColor DarkGray
}

Write-Host ''
Write-Host 'Done. Mobile / tablet URLs:' -ForegroundColor Cyan
Write-Host "  Order Taker app : $BaseUrl"
Write-Host "  Kitchen         : ${BaseUrl}/kitchen"
Write-Host "  Order Status    : ${BaseUrl}/order-status"
Write-Host "  POS             : ${BaseUrl}/pos"
Write-Host ''
Write-Host "Router mein DHCP reservation bhi set karein: MAC -> $StaticIp" -ForegroundColor Yellow
