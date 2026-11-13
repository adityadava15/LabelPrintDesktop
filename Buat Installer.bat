@echo off
title PCB Barcode Print - Installer Builder
cd /d "%~dp0"

echo ==========================================================
echo        PCB BARCODE PRINT - BUILDER INSTALLER v2.0         
echo ==========================================================
echo.
echo Sedang membuat installer mandiri dan paket portable...
echo.

php\php.exe build_installer.php

echo.
echo ==========================================================
echo Selesai! File installer tersedia di folder: dist\
echo ==========================================================
explorer.exe dist
pause
