@echo off
setlocal enabledelayedexpansion
title Mobile URLs

echo.
echo ============================================
echo   PHONE - same WiFi subnet par kholo:
echo ============================================
echo.

set "found=0"
for /f "usebackq tokens=2 delims=:" %%a in (`ipconfig ^| findstr /c:"IPv4"`) do (
    set "ip=%%a"
    set "ip=!ip:~1!"
    echo !ip! | findstr /r "^192\.168\." >nul && (
        echo   http://!ip!/
        set "found=1"
    )
)

if "!found!"=="0" echo   (192.168.x.x IP nahi mili - WiFi connect karo)

echo.
echo --- 192.168.3.x alag WiFi ---
echo   PC ko us WiFi se connect karo, phir upar naya IP aayega
echo   Example: http://192.168.3.105/
echo.
echo Laragon: Apache + MySQL GREEN
echo.
pause
