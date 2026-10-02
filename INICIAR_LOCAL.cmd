@echo off
cd /d "%~dp0"
set "PHP_LOCAL=%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.5_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
if not exist "%PHP_LOCAL%" (
    echo No se encontro PHP 8.5 en este equipo.
    pause
    exit /b 1
)
echo Sistema de Tramites: http://127.0.0.1:8000/login
echo Mantenga esta ventana abierta mientras usa el sistema.
"%PHP_LOCAL%" -d ffi.enable=true artisan serve --host=127.0.0.1 --port=8000
pause
