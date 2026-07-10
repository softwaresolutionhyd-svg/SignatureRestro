@echo off
setlocal enabledelayedexpansion
title Signature - Mobile / Extender URLs

echo.
echo ============================================
echo   SIGNATURE - har device / extender par:
echo   (same WiFi network — AP mode extender)
echo ============================================
echo.

set "found=0"
for /f "usebackq tokens=2 delims=:" %%a in (`ipconfig ^| findstr /c:"IPv4"`) do (
    set "ip=%%a"
    set "ip=!ip:~1!"
    echo !ip! | findstr /r "^192\.168\." >nul && (
        echo   http://!ip!:8080/
        echo   POS         http://!ip!:8080/restaurant-pos
        echo   Order Taker http://!ip!:8080/order-taker
        echo   Kitchen     http://!ip!:8080/kitchen
        echo.
        set "found=1"
    )
)

if "!found!"=="0" (
    echo   192.168.x.x IP nahi mili — PC WiFi se connect karo
    echo   Fixed IP setup: scripts\set-cafe-lan-ip.ps1
)

echo --- Extender ---
echo   Repeater/AP mode = same URL har jagah chalega
echo   Router mode extender = kaam nahi karega
echo.
echo Laragon: Apache + MySQL GREEN (port 8080)
echo Firewall setup: scripts\setup-signature-lan-network.ps1  (Admin)
echo.
pause
