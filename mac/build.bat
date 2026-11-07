@echo off
setlocal
title TunnelDeck Lite - macOS Package Builder

echo ==============================================================
echo       TunnelDeck Lite - Native macOS Package Builder
echo ==============================================================
echo.

node "%~dp0builder\build-pkg.js"
if %ERRORLEVEL% neq 0 (
    echo [ERROR] Failed to build macOS package.
    pause
    exit /b 1
)

echo.
echo Build completed successfully.

