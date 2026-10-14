@echo off
rem Doble clic para instalar y abrir el Sistema de Tramites en esta PC.
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\Install-SistemaTramites.ps1" %*
pause
