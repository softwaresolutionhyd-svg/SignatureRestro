@echo off
title Signature - Install Cafe LAN Keepalive
cd /d "%~dp0"
echo.
echo Admin rights chahiye — Task Scheduler mein keepalive install hoga.
echo.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0install-cafe-lan-keepalive.ps1"
echo.
pause
