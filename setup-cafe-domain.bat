@echo off
setlocal enabledelayedexpansion
title Setup softwaresolution.cafe

:: --- Run as Administrator ---
net session >nul 2>&1
if errorlevel 1 (
    echo.
    echo Admin permission chahiye. Ya setup-cafe-domain.vbs double-click karo.
    echo.
    pause
    exit /b 1
)

set "HOSTS=%SystemRoot%\System32\drivers\etc\hosts"
set "DOMAIN=softwaresolution.cafe"
set "PHP=C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe"

echo.
echo === softwaresolution.cafe setup ===
echo.

:: Detect LAN IP
set "LAN_IP="
for /f "usebackq tokens=2 delims=:" %%a in (`ipconfig ^| findstr /c:"IPv4"`) do (
    set "ip=%%a"
    set "ip=!ip:~1!"
    echo !ip! | findstr /r "^192\.168\." >nul && set "LAN_IP=!ip!"
)

if not defined LAN_IP (
    echo Warning: No 192.168.x.x IP found. Connect WiFi first.
    set "LAN_IP=127.0.0.1"
)

echo PC LAN IP: %LAN_IP%
echo.

:: Windows hosts (this PC)
findstr /c:"%DOMAIN%" "%HOSTS%" >nul 2>&1
if errorlevel 1 (
    echo 127.0.0.1 %DOMAIN%>> "%HOSTS%"
    echo %LAN_IP% %DOMAIN%>> "%HOSTS%"
    echo ::1 %DOMAIN%>> "%HOSTS%"
    echo [OK] Added %DOMAIN% to Windows hosts
) else (
    echo [OK] %DOMAIN% already in Windows hosts
)

ipconfig /flushdns >nul 2>&1
echo [OK] DNS cache flushed

:: Save IP for reference
cd /d "%~dp0"
echo %LAN_IP%> storage\app\local-server-ip.txt

if exist "%PHP%" (
    "%PHP%" artisan config:clear >nul 2>&1
    echo [OK] Laravel config cleared
)

echo.
echo ============================================
echo   PC browser:  http://softwaresolution.cafe/
echo ============================================
echo.
echo MOBILE / TABLET (same WiFi) - ONE TIME router setup:
echo.
echo   1. Main router admin panel kholo
echo   2. Is PC ko STATIC IP do: %LAN_IP%
echo      (DHCP Reservation / Address Reservation)
echo   3. Local DNS / Host mapping add karo:
echo        %DOMAIN%  --^>  %LAN_IP%
echo      (TP-Link: Advanced ^> Network ^> DHCP ^> Address Reservation
echo       + Local Domain / DNS if available)
echo.
echo   Agar router mein Local DNS na ho:
echo   - Acrylic DNS Proxy PC par install karo (free)
echo   - Map: %DOMAIN% = %LAN_IP%
echo   - Router DHCP DNS = %LAN_IP% (PC ka IP)
echo.
echo   4. Laragon: Apache STOP then START
echo.
echo Phir har phone par sirf likho:
echo   http://softwaresolution.cafe/
echo.
pause
