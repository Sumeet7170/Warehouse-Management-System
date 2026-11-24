@echo off
setlocal EnableDelayedExpansion
title Warehouse Management System (WMS)

cd /d "%~dp0"

echo 
echo              WAREHOUSE MANAGEMENT SYSTEM (WMS)
echo 

:: 1. Detect PHP
set "PHP_BIN="
where php >nul 2>&1
if !errorlevel! equ 0 (
    set "PHP_BIN=php"
) else (
    if exist "C:\xampp\php\php.exe" set "PHP_BIN=C:\xampp\php\php.exe"
    if not defined PHP_BIN if exist "D:\xampp\php\php.exe" set "PHP_BIN=D:\xampp\php\php.exe"
    if not defined PHP_BIN if exist "E:\xampp\php\php.exe" set "PHP_BIN=E:\xampp\php\php.exe"
)

if not defined PHP_BIN (
    echo [ERROR] PHP was not found!
    echo Please install XAMPP or add PHP to your Windows PATH.
    echo.
    pause
    exit /b 1
)

:: 2. Check and Start MySQL if needed
tasklist /FI "IMAGENAME eq mysqld.exe" 2>nul | find /I /N "mysqld.exe" >nul
if !errorlevel! equ 0 (
    echo [OK] MySQL is already running.
) else (
    echo [*] MySQL is not running. Starting MySQL server...
    set "MYSQL_BIN="
    set "MYSQL_INI="
    if exist "C:\xampp\mysql\bin\mysqld.exe" (
        set "MYSQL_BIN=C:\xampp\mysql\bin\mysqld.exe"
        set "MYSQL_INI=C:\xampp\mysql\bin\my.ini"
    ) else if exist "D:\xampp\mysql\bin\mysqld.exe" (
        set "MYSQL_BIN=D:\xampp\mysql\bin\mysqld.exe"
        set "MYSQL_INI=D:\xampp\mysql\bin\my.ini"
    ) else if exist "E:\xampp\mysql\bin\mysqld.exe" (
        set "MYSQL_BIN=E:\xampp\mysql\bin\mysqld.exe"
        set "MYSQL_INI=E:\xampp\mysql\bin\my.ini"
    )

    if defined MYSQL_BIN (
        start "MySQL Server" /min "!MYSQL_BIN!" --defaults-file="!MYSQL_INI!" --standalone
        echo [*] Waiting for MySQL to be ready...
        timeout /t 3 /nobreak >nul 2>&1 || ping -n 4 127.0.0.1 >nul
        echo [OK] MySQL started.
    ) else (
        echo [WARNING] Could not locate XAMPP MySQL.
        echo Please ensure MySQL is started from the XAMPP Control Panel.
    )
)
echo.

echo [*] Checking database status...
"!PHP_BIN!" backend\database\check_db.php >nul 2>&1
if !errorlevel! neq 0 (
    echo [*] Database 'wms_db' not initialized. Running first-time installer...
    "!PHP_BIN!" backend\database\install.php
    echo.
) else (
    echo [OK] Database is connected and ready.
)
echo.

set "PORT=8000"
netstat -ano -p tcp | findstr /C:" 0.0.0.0:8000 " /C:" 127.0.0.1:8000 " /C:" [::]:8000 " >nul 2>&1
if !errorlevel! equ 0 (
    set "PORT=8080"
    netstat -ano -p tcp | findstr /C:" 0.0.0.0:8080 " /C:" 127.0.0.1:8080 " /C:" [::]:8080 " >nul 2>&1
    if !errorlevel! equ 0 (
        set "PORT=8888"
    )
)

:: 5. Open Browser
echo [*] Opening WMS in your default browser...
start http://localhost:!PORT!/frontend/

echo.
echo   WMS is now RUNNING at:  http://localhost:!PORT!/frontend/
echo.
echo   Showcase Login:
echo     - Sumeet Tiwari: sumeettiwari22@gmail.com  /  Password: $M@rty22  (19 Warehouses, 5 Low Stock, 1 Expiring, 4 Expired)
echo.
echo   [!] Keep this window OPEN while using the application.
echo   [!] Press Ctrl+C in this window to STOP the server.
echo 
echo.

"!PHP_BIN!" -S localhost:!PORT!

pause
