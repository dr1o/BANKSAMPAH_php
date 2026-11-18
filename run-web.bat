@echo off
cd /d "%~dp0"

set PHP_EXE=php
where php >nul 2>nul
if not %errorlevel% equ 0 (
    if exist "%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe" (
        set PHP_EXE="%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
    ) else (
        echo PHP tidak ditemukan di PATH.
        pause
        exit /b 1
    )
)

if "%PORT%"=="" set PORT=8081

if not exist "public\index.html" (
    echo Menyalin frontend dari proyek Java...
    %PHP_EXE% tools\salin-frontend.php
)

echo ======================================================
echo   WEB BANK SAMPAH - VERSI PHP
echo   http://127.0.0.1:%PORT%
echo   Tekan Ctrl+C untuk menghentikan server.
echo ======================================================
start "" http://127.0.0.1:%PORT%/
%PHP_EXE% -S 127.0.0.1:%PORT% -t public router.php
