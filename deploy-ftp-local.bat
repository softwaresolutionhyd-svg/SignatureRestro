@echo off
title Deploy FTP - Signature (direct, no GitHub Actions)
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "scripts\deploy-ftp.ps1"
if errorlevel 1 pause
exit /b %ERRORLEVEL%
