@echo off
REM Ejecutar Composer usando PHP y Composer de Laragon (si no están en el PATH)
set LARAGON_BIN=c:\laragon\bin
set PHP_DIR=%LARAGON_BIN%\php\php-8.3.29-Win32-vs16-x64
if not exist "%PHP_DIR%\php.exe" set PHP_DIR=%LARAGON_BIN%\php\php-8.2.30-Win32-vs16-x64
if not exist "%PHP_DIR%\php.exe" set PHP_DIR=%LARAGON_BIN%\php\php-8.1.10-Win32-vs16-x64

set COMPOSER_PHAR=%LARAGON_BIN%\composer\composer.phar

where composer >nul 2>&1
if %ERRORLEVEL% equ 0 (
    composer %*
    exit /b %ERRORLEVEL%
)

if exist "%PHP_DIR%\php.exe" if exist "%COMPOSER_PHAR%" (
    "%PHP_DIR%\php.exe" "%COMPOSER_PHAR%" %*
    exit /b %ERRORLEVEL%
)

echo Composer no encontrado. Ejecuta Composer desde el menu de Laragon: Terminal
echo o anade a PATH: %LARAGON_BIN%\php\php-8.3.29-Win32-vs16-x64 y %LARAGON_BIN%\composer
exit /b 1
