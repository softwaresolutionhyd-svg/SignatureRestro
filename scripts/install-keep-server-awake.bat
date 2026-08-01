@echo off
title Signature - Prevent Server Sleep
echo.
echo Server PC sleep band hogi (jab tak power settings / Laragon keep-awake on hai).
echo Admin permission chahiye.
echo.

net session >nul 2>&1
if errorlevel 1 (
    powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process powershell -Verb RunAs -ArgumentList '-NoProfile -ExecutionPolicy Bypass -NoExit -File \"\"%~dp0install-keep-server-awake.ps1\"\"'"
    exit /b 0
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0install-keep-server-awake.ps1"
echo.
pause
