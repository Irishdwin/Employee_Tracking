@echo off
title XAMPP MySQL - Full repair (Aria + mysql.plugin)
echo.
echo Running automated repair script...
echo This fixes Aria log corruption and mysql.plugin table issues.
echo Your databases (onazul_hris_db, etc.) are NOT deleted.
echo.
powershell -ExecutionPolicy Bypass -File "%~dp0repair_xampp_mysql.ps1"
echo.
pause
