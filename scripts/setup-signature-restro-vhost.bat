@echo off
title Setup signature.restro
echo.
echo signature.restro virtual host setup
echo.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0setup-signature-restro-vhost.ps1"
powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process powershell -Verb RunAs -Wait -ArgumentList '-NoProfile -ExecutionPolicy Bypass -File \"%~dp0add-hosts-signature-restro.ps1\"'"
echo.
echo Browser: http://signature.restro/
echo.
pause
