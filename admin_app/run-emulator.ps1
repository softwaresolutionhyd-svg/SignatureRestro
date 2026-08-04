# Signature Admin — Android emulator / device par run
$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot

if (-not (Get-Command flutter -ErrorAction SilentlyContinue)) {
    Write-Host "Flutter install karein aur PATH me add karein." -ForegroundColor Yellow
    exit 1
}

flutter pub get
flutter run
