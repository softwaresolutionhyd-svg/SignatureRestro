# Signature Admin App — first-time setup (Windows)
# Run from this folder in PowerShell: .\setup.ps1

$ErrorActionPreference = "Stop"

if (-not (Get-Command flutter -ErrorAction SilentlyContinue)) {
    Write-Host "Flutter PATH par nahi mila. Pehle Flutter SDK install karein:" -ForegroundColor Yellow
    Write-Host "https://docs.flutter.dev/get-started/install/windows"
    exit 1
}

if (-not (Test-Path "android")) {
    Write-Host "Platform folders generate ho rahe hain..."
    flutter create . --org com.softwaresolution --project-name admin_app
}

Write-Host "Dependencies install..."
flutter pub get

$manifest = "android\app\src\main\AndroidManifest.xml"
if (Test-Path $manifest) {
    $xml = Get-Content $manifest -Raw
    if ($xml -notmatch 'usesCleartextTraffic') {
        $xml = $xml -replace '<application', '<application android:usesCleartextTraffic="true"'
        Set-Content $manifest $xml -NoNewline
        Write-Host "Android cleartext HTTP enabled (local dev)." -ForegroundColor Green
    }
}

Write-Host ""
Write-Host "Done! Ab run karein: flutter run" -ForegroundColor Green
Write-Host "Login: Admin / Company Admin account"
Write-Host "Server URL: http://192.168.1.105:8080 (apna LAN IP)"
