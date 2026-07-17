# Register / run Laravel scheduler so full DB sync runs every minute (local cafe PC).
# Usage:
#   powershell -ExecutionPolicy Bypass -File .\scripts\install-sync-scheduler.ps1
#   powershell -ExecutionPolicy Bypass -File .\scripts\install-sync-scheduler.ps1 -Unregister

param(
    [switch]$Unregister
)

$TaskName = "SignatureCloudSync"
$ProjectRoot = Split-Path -Parent $PSScriptRoot
$PhpCandidates = @(
    "C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe",
    "C:\laragon\bin\php\php-8.2.0-Win32-vs16-x64\php.exe",
    "C:\laragon\bin\php\php-8.3.0-Win32-vs16-x64\php.exe"
)
$Php = $PhpCandidates | Where-Object { Test-Path $_ } | Select-Object -First 1
if (-not $Php) {
    $cmd = Get-Command php -ErrorAction SilentlyContinue
    if ($cmd) { $Php = $cmd.Source }
}
if (-not $Php) {
    Write-Error "PHP not found. Install Laragon PHP or add php to PATH."
    exit 1
}

if ($Unregister) {
    Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false -ErrorAction SilentlyContinue
    Write-Host "Removed scheduled task: $TaskName"
    exit 0
}

$Argument = "`"$ProjectRoot\artisan`" schedule:run"
$Action = New-ScheduledTaskAction -Execute $Php -Argument $Argument -WorkingDirectory $ProjectRoot
# Windows limits repetition duration; use ~10 years then re-register if needed
$Trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1) -RepetitionDuration (New-TimeSpan -Days 3650)
$Settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -MultipleInstances IgnoreNew
$Principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel Limited

Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false -ErrorAction SilentlyContinue
Register-ScheduledTask -TaskName $TaskName -Action $Action -Trigger $Trigger -Settings $Settings -Principal $Principal -Force | Out-Null
Write-Host "Installed: $TaskName"
Write-Host "Runs every 1 minute: $Php $Argument"
Write-Host "This pushes local changes and pulls full hosting DB when SYNC_AUTO_PULL=true."

