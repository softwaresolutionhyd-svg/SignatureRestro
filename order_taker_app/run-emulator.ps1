# Order Taker — Android emulator par run karein
# Pehli dafa: Android Studio kholo, SDK + Virtual Device setup karo (neeche README steps)

$ErrorActionPreference = 'Stop'
$flutterBin = 'C:\src\flutter\bin'
$gitCmd = 'C:\Program Files\Git\cmd'
$sdk = Join-Path $env:LOCALAPPDATA 'Android\Sdk'

$env:Path = "$flutterBin;$gitCmd;" + [Environment]::GetEnvironmentVariable('Path','User') + ';' + [Environment]::GetEnvironmentVariable('Path','Machine')

if (-not (Test-Path "$sdk\platform-tools\adb.exe")) {
    Write-Host ''
    Write-Host 'Android SDK nahi mila.' -ForegroundColor Yellow
    Write-Host '1. Android Studio kholo (Start menu)'
    Write-Host '2. First-time wizard complete karo — SDK download hone do'
    Write-Host '3. Tools > Device Manager > Create Device > Start emulator'
    Write-Host '4. Phir dubara: .\run-emulator.ps1'
    Write-Host ''
    $studio = 'C:\Program Files\Android\Android Studio\bin\studio64.exe'
    if (Test-Path $studio) {
        $open = Read-Host 'Android Studio ab kholun? (y/n)'
        if ($open -eq 'y') { Start-Process $studio }
    }
    exit 1
}

& "$flutterBin\flutter.bat" config --android-sdk $sdk | Out-Null

Write-Host 'Emulators:'
& "$flutterBin\flutter.bat" emulators

Write-Host ''
Write-Host 'Emulator start ho raha hai (agar band ho)...'
& "$flutterBin\flutter.bat" emulators --launch $((& "$flutterBin\flutter.bat" emulators 2>&1 | Select-String '•' | Select-Object -First 1).ToString().Trim('• ').Trim())

Start-Sleep -Seconds 15

Set-Location $PSScriptRoot
Write-Host 'App run...'
& "$flutterBin\flutter.bat" run
