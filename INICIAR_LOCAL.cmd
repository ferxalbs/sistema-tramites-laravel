@echo off
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy RemoteSigned -File "%~dp0scripts\Start-SistemaTramitesLocal.ps1" -Launch
if errorlevel 1 pause
