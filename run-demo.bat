@echo off
cd /d "%~dp0"
where php >nul 2>nul
if %errorlevel% equ 0 (
    php demo.php
) else if exist "%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" (
    "%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" demo.php
) else (
    echo PHP tidak ditemukan di PATH.
    pause
)
echo.
pause
