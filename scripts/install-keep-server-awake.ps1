# One-time: disable sleep on AC + install background keep-awake task.
# Run as Administrator.

$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
$prevent = Join-Path $root 'prevent-server-sleep.ps1'
$keep = Join-Path $root 'keep-server-awake.ps1'
$taskName = 'Signature Keep Server Awake'

$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole(
    [Security.Principal.WindowsBuiltInRole]::Administrator
)
if (-not $isAdmin) {
    throw 'Administrator rights required. Right-click install-keep-server-awake.bat and choose Run as administrator.'
}

& $prevent

$existing = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
if ($existing) {
    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false
}

$action = New-ScheduledTaskAction `
    -Execute 'powershell.exe' `
    -Argument "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$keep`""

$triggers = @(
    (New-ScheduledTaskTrigger -AtStartup),
    (New-ScheduledTaskTrigger -AtLogon)
)

$settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -RestartCount 3 `
    -RestartInterval (New-TimeSpan -Minutes 1) `
    -ExecutionTimeLimit ([TimeSpan]::Zero) `
    -MultipleInstances IgnoreNew

$principal = New-ScheduledTaskPrincipal `
    -UserId 'SYSTEM' `
    -LogonType ServiceAccount `
    -RunLevel Highest

Register-ScheduledTask `
    -TaskName $taskName `
    -Action $action `
    -Trigger $triggers `
    -Settings $settings `
    -Principal $principal `
    -Description 'Keeps PC awake while Laragon/Apache/MySQL is running for Signature POS.' | Out-Null

Start-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue

Write-Host ''
Write-Host "OK: Sleep disabled + task '$taskName' installed and started."
Write-Host 'Laragon on = PC will not sleep. Screen may still turn off.'
