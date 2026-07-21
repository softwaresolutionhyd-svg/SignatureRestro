@echo off
title Signature - Lock Cafe Server IP (192.168.1.105)
echo.
echo Is PC par IP permanently lock hogi — sirf yahi machine 192.168.1.105 use karegi.
echo Admin permission chahiye.
echo.

net session >nul 2>&1
if errorlevel 1 (
    powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process powershell -Verb RunAs -ArgumentList '-NoProfile -ExecutionPolicy Bypass -NoExit -File \"\"%~dp0lock-cafe-server-ip.ps1\"\"'"
    exit /b 0
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0lock-cafe-server-ip.ps1"
echo.
pause
