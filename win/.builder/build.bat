@echo off
setlocal enabledelayedexpansion
title TunnelDeck Lite - Windows Installer Builder

echo ==============================================================
echo       TunnelDeck Lite - Native Windows Installer Builder
echo ==============================================================
echo.

:: 1. Locate C# Compiler (CSC.EXE)
set "CSC_EXE="
if exist "C:\Windows\Microsoft.NET\Framework64\v4.0.30319\csc.exe" (
    set "CSC_EXE=C:\Windows\Microsoft.NET\Framework64\v4.0.30319\csc.exe"
) else if exist "C:\Windows\Microsoft.NET\Framework\v4.0.30319\csc.exe" (
    set "CSC_EXE=C:\Windows\Microsoft.NET\Framework\v4.0.30319\csc.exe"
)

if not defined CSC_EXE (
    echo [ERROR] Microsoft .NET Framework 4.x C# compiler [csc.exe] was not found.
    echo Please ensure .NET Framework 4.5+ is installed on this machine.
    pause
    exit /b 1
)

:: Set Directories
set "BUILDER_DIR=%~dp0"
if "%BUILDER_DIR:~-1%"=="\" set "BUILDER_DIR=%BUILDER_DIR:~0,-1%"
set "WIN_DIR=%BUILDER_DIR%\.."
set "ROOT_DIR=%WIN_DIR%\.."
set "OUTPUT_EXE=%WIN_DIR%\Tunneldeck.exe"
set "ICON_FILE=%ROOT_DIR%\app.ico"

echo [1/4] Compiling native Uninstaller...
"%CSC_EXE%" /nologo /optimize /target:winexe /win32icon:"%ICON_FILE%" /win32manifest:"%BUILDER_DIR%\app.manifest" ^
    /resource:"%BUILDER_DIR%\logo.png",logo.png ^
    /resource:"%ICON_FILE%",app.ico ^
    /out:"%BUILDER_DIR%\uninstall.exe" "%BUILDER_DIR%\Uninstaller.cs"
if %ERRORLEVEL% neq 0 (
    echo [ERROR] Failed to compile Uninstaller.
    pause
    exit /b 1
)
echo       + Created uninstaller successfully.

echo [2/4] Packaging application distribution into payload.zip...
powershell -NoProfile -ExecutionPolicy Bypass -File "%BUILDER_DIR%\build-payload.ps1"
if %ERRORLEVEL% neq 0 (
    echo [ERROR] Failed to package application payload.
    pause
    exit /b 1
)

if not exist "%BUILDER_DIR%\payload.zip" (
    echo [ERROR] payload.zip was not generated.
    pause
    exit /b 1
)

echo [3/4] Compiling standalone Windows Installer wizard...
"%CSC_EXE%" /nologo /optimize /target:winexe /win32icon:"%ICON_FILE%" /win32manifest:"%BUILDER_DIR%\app.manifest" ^
    /r:System.IO.Compression.dll ^
    /r:System.IO.Compression.FileSystem.dll ^
    /resource:"%BUILDER_DIR%\logo.png",logo.png ^
    /resource:"%BUILDER_DIR%\payload.zip",payload.zip ^
    /resource:"%BUILDER_DIR%\uninstall.exe",uninstall.exe ^
    /resource:"%ICON_FILE%",app.ico ^
    /out:"%OUTPUT_EXE%" ^
    "%BUILDER_DIR%\Installer.cs"

if %ERRORLEVEL% neq 0 (
    echo [ERROR] Failed to compile TunnelDeck-Lite-Setup.exe.
    pause
    exit /b 1
)

:: Clean up temporary build artifacts
if exist "%BUILDER_DIR%\payload.zip" del /q "%BUILDER_DIR%\payload.zip" >nul 2>&1
if exist "%BUILDER_DIR%\uninstall.exe" del /q "%BUILDER_DIR%\uninstall.exe" >nul 2>&1

echo [4/4] Finalizing build...
echo.
echo ==============================================================
echo   SUCCESS! Standalone Installer Created:
echo   %OUTPUT_EXE%
echo ==============================================================
echo.

:: Display file size
for %%I in ("%OUTPUT_EXE%") do (
    set /a SIZE_MB=%%~zI / 1048576
    echo   File Size: !SIZE_MB! MB (%%~zI bytes)
)
echo.
echo Users can now download and run TunnelDeck-Lite-Setup.exe directly!
echo.
