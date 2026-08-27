# Install Task Scheduler: keep cafe LAN reachable on boot + every 30 minutes.
# Run as Administrator via install-cafe-lan-keepalive.bat

$ErrorActionPreference = 'Stop'
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host 'ERROR: Right-click install-cafe-lan-keepalive.bat -> Run as administrator' -ForegroundColor Red
    exit 1
}

$script = Join-Path $PSScriptRoot 'keep-cafe-lan-alive.ps1'
$taskName = 'Signature Cafe LAN Keepalive'

# Run once now
& powershell -NoProfile -ExecutionPolicy Bypass -File $script

$action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$script`""
$triggers = @(
    (New-ScheduledTaskTrigger -AtStartup),
    (New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 30) -RepetitionDuration (New-TimeSpan -Days 3650))
)
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -MultipleInstances IgnoreNew
$principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest

Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $triggers -Settings $settings -Principal $principal -Description 'Signature cafe: firewall + Apache LAN keepalive (offline WiFi tablets)' | Out-Null

Write-Host ''
Write-Host '[OK] Task installed:' $taskName -ForegroundColor Green
Write-Host 'Boot + har 30 min firewall/Apache check hoga.' -ForegroundColor Cyan
Write-Host ''
Write-Host 'IMPORTANT: Agar tablet ERR_ADDRESS_UNREACHABLE de:' -ForegroundColor Yellow
Write-Host '  Server Ethernet cable EXTENDER ke LAN port mein lagao (router se hatao).' -ForegroundColor White
Write-Host '  Ya router/extender mein AP Isolation = OFF.' -ForegroundColor White
Write-Host '  Detail: EXTENDER-FIX.txt' -ForegroundColor White
Write-Host ''
