@echo off
title Onazul - Install email support (Composer + PHPMailer)
cd /d "%~dp0"

echo.
echo Checking for Composer...
where composer >nul 2>nul
if %errorlevel% neq 0 (
    echo Composer not found in PATH.
    echo.
    echo Option A - Install Composer globally:
    echo   1. Download from: https://getcomposer.org/download/
    echo   2. Run the Windows installer, then close and reopen this window.
    echo.
    echo Option B - Use Composer in this folder only:
    echo   Downloading composer.phar...
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php
    php -r "unlink('composer-setup.php');"
    if exist composer.phar (
        echo Running: php composer.phar install
        php composer.phar install
    ) else (
        echo Failed to download composer.phar. Install PHP and try Option A.
    )
) else (
    echo Running: composer install
    composer install
)

echo.
echo Next: Edit Api\config\mail_config.php with your Gmail and App Password.
echo Then open in browser: http://localhost/Employee_Tracking/Api/config/check_email_setup.php
echo.
pause
