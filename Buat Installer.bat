@echo off
title PCB Barcode Print - Installer Builder v2.4
cd /d "%~dp0"

echo ==========================================================
echo        PCB BARCODE PRINT - BUILDER INSTALLER v2.4         
echo ==========================================================
echo.
echo [1/2] Mengompilasi PCBBarcodePrint.exe terbaru...
call build.bat

echo.
echo [2/2] Membangun paket installer mandiri dan portable...
echo.
php\php.exe build_installer.php

echo.
echo ==========================================================
echo Selesai! File installer v2.4 tersedia di folder: dist\
echo ==========================================================
explorer.exe dist
pause
