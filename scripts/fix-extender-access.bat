@echo off
title Signature - Extender / Phone Fix
echo.
echo Admin chahiye (firewall + Apache 8080).
echo.

net session >nul 2>&1
if errorlevel 1 (
    powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process powershell -Verb RunAs -ArgumentList '-NoProfile -ExecutionPolicy Bypass -File \"\"%~dp0fix-extender-access.ps1\"\"'"
    exit /b 0
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0fix-extender-access.ps1"
echo.
pause
