@echo off
title Signature - Cafe Captive DNS (fix WiFi no-internet)
cd /d "%~dp0"
echo.
echo Yeh DNS Android ko batata hai: WiFi pe internet OK hai (local check).
echo Jab net slow / "Connected no internet" ho, phir bhi Order Taker chalega.
echo.
echo ZAROORI:
echo  1) Is window ko Admin se kholo (Run as administrator)
echo  2) Router DHCP DNS = 192.168.1.105 set karo
echo  3) Yeh window OPEN rehne do (band mat karo)
echo.
"C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe" "%~dp0cafe-captive-dns.php"
pause
