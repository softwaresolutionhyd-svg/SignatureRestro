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

echo --- Extender / Phone ---
echo   URL: http://192.168.1.15:8080/  (PC ki IP — network-url se confirm karo)
echo   192.168.3.50 mat use karo jab tak fixed IP na ho
echo   Phone WiFi IP 192.168.1.x honi chahiye (Settings - WiFi - IP)
echo   Agar 192.168.2.x / 10.x hai = extender ROUTER mode (galat)
echo   Fix script: scripts\fix-extender-access.bat  (Admin)
echo   Extender: AP/Repeater mode, AP Isolation OFF
echo   Router: Guest WiFi band; WiFi se LAN access ON
echo.
echo Laragon: Apache + MySQL GREEN
echo Port 8080 setup: scripts\setup-signature-lan-network.bat  (Admin, ek dafa)
echo Firewall setup: same script (Admin)
echo.
pause
