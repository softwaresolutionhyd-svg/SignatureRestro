@echo off
REM ============================================================
REM  Signature POS - Firewall port 8080 allow (SIRF EK BAAR)
REM  Is file par right-click -> "Run as administrator"
REM ============================================================
net session >nul 2>&1
if %errorlevel% NEQ 0 (
    echo.
    echo  Yeh file ADMIN mode me chalayein:
    echo  Right-click -^> Run as administrator
    echo.
    pause
    exit /b
)

netsh advfirewall firewall delete rule name="Signature POS 8080" >nul 2>&1
netsh advfirewall firewall add rule name="Signature POS 8080" dir=in action=allow protocol=TCP localport=8080
echo.
echo  Ho gaya. Port 8080 ab doosre devices ke liye khula hai.
echo.
pause
