# Install keep-awake for current Windows user (no admin required).
# Power sleep settings should already be Never via prevent-server-sleep.ps1.

$ErrorActionPreference = 'Stop'
$keep = Join-Path $PSScriptRoot 'keep-server-awake.ps1'
$taskName = 'Signature Keep Server Awake'

$existing = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
if ($existing) {
    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false
}

$action = New-ScheduledTaskAction `
    -Execute 'powershell.exe' `
    -Argument "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$keep`""

$triggers = @(
    (New-ScheduledTaskTrigger -AtLogon -User $env:USERNAME)
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
    -UserId $env:USERNAME `
    -LogonType Interactive `
    -RunLevel Limited

Register-ScheduledTask `
    -TaskName $taskName `
    -Action $action `
    -Trigger $triggers `
    -Settings $settings `
    -Principal $principal `
    -Description 'Keeps PC awake while Laragon is running for Signature POS.' | Out-Null

Start-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue

# Also start now in this session
Start-Process -FilePath 'powershell.exe' -WindowStyle Hidden -ArgumentList @(
    '-NoProfile', '-ExecutionPolicy', 'Bypass', '-WindowStyle', 'Hidden', '-File', $keep
)

Write-Host "OK: task '$taskName' installed for user $env:USERNAME and started."
