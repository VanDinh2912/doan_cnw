@echo off
chcp 65001 >nul
title Reset du lieu demo Web POS
cd /d "%~dp0"
php artisan demo:reset --force
if errorlevel 1 (
    echo.
    echo [LOI] Reset that bai. Kiem tra MySQL, .env va PHP trong PATH.
    pause
    exit /b 1
)
echo.
echo [THANH CONG] Da reset du lieu demo.
pause
