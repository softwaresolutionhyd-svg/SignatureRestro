# Register Laravel sync scheduler to run HIDDEN (no CMD flash).
# Usage:
#   powershell -ExecutionPolicy Bypass -File .\scripts\install-sync-scheduler.ps1
#   powershell -ExecutionPolicy Bypass -File .\scripts\install-sync-scheduler.ps1 -Unregister

param(
    [switch]$Unregister
)

$TaskName = "SignatureCloudSync"
$ProjectRoot = Split-Path -Parent $PSScriptRoot
$Vbs = Join-Path $PSScriptRoot "run-sync-hidden.vbs"
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

if (-not (Test-Path $Vbs)) {
    Write-Error "Missing $Vbs"
    exit 1
}

$Artisan = Join-Path $ProjectRoot "artisan"
# wscript //B = batch (no UI); VBS runs php with window style 0 (hidden)
$Argument = "//B //Nologo `"$Vbs`" `"$Php`" `"$Artisan`""
$Action = New-ScheduledTaskAction -Execute "wscript.exe" -Argument $Argument -WorkingDirectory $ProjectRoot
$Trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1) -RepetitionDuration (New-TimeSpan -Days 3650)
$Settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -MultipleInstances IgnoreNew -Hidden
$Principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel Limited

Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false -ErrorAction SilentlyContinue
Register-ScheduledTask -TaskName $TaskName -Action $Action -Trigger $Trigger -Settings $Settings -Principal $Principal -Force | Out-Null
Write-Host "Installed (hidden): $TaskName"
Write-Host "Runs every 1 minute in background via wscript - no CMD window."
