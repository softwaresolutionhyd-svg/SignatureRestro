@echo off
title Fix storage folders
set "PHP="
for /d %%d in ("C:\laragon\bin\php\php-*") do set "PHP=%%d\php.exe"
cd /d "%~dp0"
if not exist "storage\framework\views" mkdir "storage\framework\views"
if not exist "storage\framework\sessions" mkdir "storage\framework\sessions"
if not exist "storage\framework\cache\data" mkdir "storage\framework\cache\data"
if not exist "storage\logs" mkdir "storage\logs"
echo [OK] storage folders created
"%PHP%" artisan config:clear
"%PHP%" artisan view:clear
echo [OK] cache cleared — ab login try karo
pause
