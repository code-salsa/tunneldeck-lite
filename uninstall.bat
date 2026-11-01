@echo off
setlocal enabledelayedexpansion
cd /d "%~dp0"

echo ===================================================
echo TunnelDeck Lite - Uninstaller
echo ===================================================
echo.

if exist "%~dp0uninstall.exe" (
    "%~dp0uninstall.exe" %*
    exit /b %ERRORLEVEL%
)

:: Standalone fallback
echo Stopping TunnelDeck processes...
powershell -NoProfile -ExecutionPolicy Bypass -Command "$procs = Get-CimInstance Win32_Process -Filter \"Name = 'php.exe' or Name = 'cloudflared.exe'\" -ErrorAction SilentlyContinue; foreach ($p in $procs) { if ($p.CommandLine -match 'tunneldeck-lite') { Stop-Process -Id $p.ProcessId -Force -ErrorAction SilentlyContinue } }" >nul 2>&1

echo Removing Desktop shortcuts...
if exist "%USERPROFILE%\Desktop\TunnelDeck.lnk" del /f /q "%USERPROFILE%\Desktop\TunnelDeck.lnk" >nul 2>&1
if exist "%USERPROFILE%\Desktop\TunnelDeck Lite.lnk" del /f /q "%USERPROFILE%\Desktop\TunnelDeck Lite.lnk" >nul 2>&1
if exist "%PUBLIC%\Desktop\TunnelDeck.lnk" del /f /q "%PUBLIC%\Desktop\TunnelDeck.lnk" >nul 2>&1
if exist "%PUBLIC%\Desktop\TunnelDeck Lite.lnk" del /f /q "%PUBLIC%\Desktop\TunnelDeck Lite.lnk" >nul 2>&1

echo Removing installed packages and cache...
if exist "%~dp0bin\php" rmdir /s /q "%~dp0bin\php" >nul 2>&1
if exist "%~dp0.app_data" rmdir /s /q "%~dp0.app_data" >nul 2>&1
if exist "%~dp0status.json" del /f /q "%~dp0status.json" >nul 2>&1
if exist "%~dp0src\storage\logs\startup.log" del /f /q "%~dp0src\storage\logs\startup.log" >nul 2>&1

echo.
echo TunnelDeck Lite uninstallation completed successfully.
exit /b 0
