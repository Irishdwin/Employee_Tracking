@echo off
title XAMPP MySQL - Fix unexpected shutdown
echo.
echo This script will try to fix "MySQL shutdown unexpectedly" by:
echo 1. Backing up InnoDB log files
echo 2. Removing the log files so MySQL can recreate them on next start
echo.
echo IMPORTANT: Stop MySQL from XAMPP Control Panel first, then press any key.
pause >nul

set MYSQL_DATA=C:\xampp\mysql\data
set BACKUP_DIR=%MYSQL_DATA%\backup_innodb_logs

if not exist "%MYSQL_DATA%" (
    echo Error: MySQL data folder not found at %MYSQL_DATA%
    echo Edit this script if XAMPP is installed elsewhere.
    pause
    exit /b 1
)

echo.
echo Creating backup folder...
if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

echo Backing up ib_logfile0 and ib_logfile1...
if exist "%MYSQL_DATA%\ib_logfile0" (
    copy /Y "%MYSQL_DATA%\ib_logfile0" "%BACKUP_DIR%\ib_logfile0.bak" >nul 2>&1
    del /F /Q "%MYSQL_DATA%\ib_logfile0"
    echo   - Removed ib_logfile0
) else (
    echo   - ib_logfile0 not present
)
if exist "%MYSQL_DATA%\ib_logfile1" (
    copy /Y "%MYSQL_DATA%\ib_logfile1" "%BACKUP_DIR%\ib_logfile1.bak" >nul 2>&1
    del /F /Q "%MYSQL_DATA%\ib_logfile1"
    echo   - Removed ib_logfile1
) else (
    echo   - ib_logfile1 not present
)

echo.
echo Done. Now:
echo 1. Open XAMPP Control Panel
echo 2. Click Start next to MySQL
echo.
echo If MySQL still fails, check the log: %MYSQL_DATA%\mysql_error.log
echo.
pause
