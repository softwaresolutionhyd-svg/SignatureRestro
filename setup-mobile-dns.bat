@echo off
setlocal enabledelayedexpansion
title Mobile DNS - softwaresolution.cafe

:: --- Admin required ---
net session >nul 2>&1
if errorlevel 1 (
    echo Run setup-mobile-dns.vbs instead ^(double-click^)
    pause
    exit /b 1
)

set "DOMAIN=softwaresolution.cafe"
set "HOSTS=%SystemRoot%\System32\drivers\etc\hosts"
set "OUT=%~dp0config\generated-dns-hosts.txt"
set "PHP=C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe"

echo.
echo ================================================
echo   softwaresolution.cafe - MOBILE setup
echo ================================================
echo.

:: LAN IP
set "LAN_IP="
for /f "usebackq tokens=2 delims=:" %%a in (`ipconfig ^| findstr /c:"IPv4"`) do (
    set "ip=%%a"
    set "ip=!ip:~1!"
    echo !ip! | findstr /r "^192\.168\." >nul && set "LAN_IP=!ip!"
)

if not defined LAN_IP (
    echo ERROR: WiFi se connect karo. 192.168.x.x IP nahi mili.
    pause
    exit /b 1
)

echo Server PC IP: %LAN_IP%
echo.

:: PC hosts
findstr /c:"%DOMAIN%" "%HOSTS%" >nul 2>&1
if errorlevel 1 (
    echo 127.0.0.1 %DOMAIN% www.%DOMAIN%>> "%HOSTS%"
    echo %LAN_IP% %DOMAIN% www.%DOMAIN%>> "%HOSTS%"
    echo ::1 %DOMAIN% www.%DOMAIN%>> "%HOSTS%"
)
ipconfig /flushdns >nul 2>&1

:: Generate Acrylic / Technitium hosts file
if not exist "%~dp0config" mkdir "%~dp0config"
(
    echo # Copy into Acrylic DNS Proxy - HostsAcrylic.txt ^(last lines^)
    echo # OR Technitium: Add A record %DOMAIN% -^> %LAN_IP%
    echo.
    echo %LAN_IP% %DOMAIN%
    echo %LAN_IP% www.%DOMAIN%
) > "%OUT%"

echo %LAN_IP%> "%~dp0storage\app\local-server-ip.txt"

:: Firewall for DNS (Acrylic / Technitium on this PC)
netsh advfirewall firewall add rule name="Softwaresolution Local DNS UDP" dir=in action=allow protocol=UDP localport=53 >nul 2>&1
netsh advfirewall firewall add rule name="Softwaresolution Local DNS TCP" dir=in action=allow protocol=TCP localport=53 >nul 2>&1

if exist "%PHP%" (
    cd /d "%~dp0"
    "%PHP%" artisan config:clear >nul 2>&1
)

echo [OK] PC hosts + DNS config file ready
echo.
echo ================================================
echo   STEP A - DNS software PC par install karo
echo ================================================
echo.
echo Option 1 ^(asan^): Technitium DNS Server
echo   1. Download: https://technitium.com/dns/
echo   2. Install, open http://localhost:5380/
echo   3. Add zone: %DOMAIN%
echo   4. A record: @ and www  -^>  %LAN_IP%
echo   5. Settings -^> Allow access from network
echo.
echo Option 2: Acrylic DNS Proxy
echo   1. Download: https://mayakron.altervista.org/w/acrylic-home-page/
echo   2. File config\generated-dns-hosts.txt ki lines HostsAcrylic.txt mein paste karo
echo   3. Acrylic service Start
echo.
echo ================================================
echo   STEP B - ROUTER ^(sab phones ke liye^)
echo ================================================
echo.
echo   1. Router admin kholo ^(often 192.168.1.1 or 192.168.4.1^)
echo   2. DHCP -^> Address Reservation: is PC ko FIXED IP %LAN_IP%
echo   3. DHCP -^> Primary DNS Server = %LAN_IP%
echo      ^(extender AP mode mein ho, main router par set karo^)
echo   4. Save, router restart
echo.
echo ================================================
echo   STEP C - PHONE
echo ================================================
echo.
echo   1. WiFi OFF/ON ^(ya airplane mode^) - naya DNS lo
echo   2. Browser: http://softwaresolution.cafe/
echo.
echo   Test DNS on phone: install "Network Utilities" app
echo   ping %DOMAIN% - should show %LAN_IP%
echo.
echo Laragon Apache START hona chahiye.
echo.
pause
