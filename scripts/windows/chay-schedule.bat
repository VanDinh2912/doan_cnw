@echo off
rem Chay Laravel Task Scheduler moi phut. Dat file nay o scripts\windows\.
rem Task Scheduler goi file o bat ky thu muc nao cung duoc: script tu ve goc du an.
setlocal EnableExtensions

cd /d "%~dp0..\.."

rem Cho phep chi dinh PHP khac (vi du: set PHP_BINARY=D:\xampp\php\php.exe).
rem Mac dinh dung PHP 8.3.28 cua WAMP (trung phien ban Apache dang chay).
if not defined PHP_BINARY set "PHP_BINARY=D:\Study\xampp\bin\php\php8.3.28\php.exe"
set "PHP_EXE=php"
if defined PHP_BINARY set "PHP_EXE=%PHP_BINARY%"

if not exist "storage\logs" mkdir "storage\logs"

set "NGAY=run"
for /f "usebackq delims=" %%i in (`powershell -NoProfile -Command "Get-Date -Format yyyy-MM-dd"`) do set "NGAY=%%i"

"%PHP_EXE%" artisan schedule:run --no-interaction >> "storage\logs\schedule-%NGAY%.log" 2>&1
set "RC=%ERRORLEVEL%"

rem Giu log scheduler 14 ngay, cung chu ky voi log Laravel.
forfiles /p "storage\logs" /m "schedule-*.log" /d -14 /c "cmd /c del @path" >nul 2>&1

endlocal & exit /b %RC%
