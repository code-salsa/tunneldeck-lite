@echo off
setlocal enabledelayedexpansion
cd /d "%~dp0"

:: 1. Terminate any previous stale TunnelDeck server processes
powershell -NoProfile -ExecutionPolicy Bypass -Command "$curr = '%~dp0'.TrimEnd('\'); $procs = Get-CimInstance Win32_Process -Filter \"Name = 'php.exe'\" -ErrorAction SilentlyContinue; foreach ($p in $procs) { if ($p.CommandLine -and ($p.CommandLine -like ('*' + $curr + '*') -or $p.CommandLine -match 'tunneldeck' -or $p.CommandLine -match 'server\.php')) { Stop-Process -Id $p.ProcessId -Force -ErrorAction SilentlyContinue } }" >nul 2>&1

:: 2. Find a free port between 9000 and 9999
:pick_port
set /a PORT=9000 + (!RANDOM! %% 1000)
netstat -ano | findstr /R /C:":!PORT! " >nul 2>&1
if !ERRORLEVEL! equ 0 (
    goto :pick_port
)

:: 3. Locate Microsoft Edge or Google Chrome executable
set "BROWSER_EXE="

if exist "%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe" (
    set "BROWSER_EXE=%ProgramFiles(x86)%\Microsoft\Edge\Application\msedge.exe"
) else if exist "%ProgramFiles%\Microsoft\Edge\Application\msedge.exe" (
    set "BROWSER_EXE=%ProgramFiles%\Microsoft\Edge\Application\msedge.exe"
) else if exist "%LocalAppData%\Microsoft\Edge\Application\msedge.exe" (
    set "BROWSER_EXE=%LocalAppData%\Microsoft\Edge\Application\msedge.exe"
)

if not defined BROWSER_EXE (
    if exist "%ProgramFiles%\Google\Chrome\Application\chrome.exe" (
        set "BROWSER_EXE=%ProgramFiles%\Google\Chrome\Application\chrome.exe"
    ) else if exist "%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe" (
        set "BROWSER_EXE=%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe"
    ) else if exist "%LocalAppData%\Google\Chrome\Application\chrome.exe" (
        set "BROWSER_EXE=%LocalAppData%\Google\Chrome\Application\chrome.exe"
    )
)

:: Set splash screen URL
set "SPLASH_URL=file:///%~dp0loading.html?port=!PORT!"

:: Setup dedicated, writable browser profile directory in LocalAppData
set "DATA_DIR=%LOCALAPPDATA%\TunnelDeck\browser"
if not exist "!DATA_DIR!" mkdir "!DATA_DIR!" >nul 2>&1

:: Initialize status file
echo {"step":"env","status":"running","message":"Initializing desktop launcher..."} > "%~dp0status.json"

:: 4. Launch loader window immediately so user sees activities right away
if defined BROWSER_EXE (
    start "" "!BROWSER_EXE!" --app="!SPLASH_URL!" --allow-file-access-from-files --user-data-dir="!DATA_DIR!" --no-first-run --no-default-browser-check --app-id=tunneldeck --window-size=1280,800
) else (
    start "" "!SPLASH_URL!"
)

echo {"step":"env","status":"done","message":"Desktop environment and workspace ready"} > "%~dp0status.json"

:: 5b. Initiate non-blocking background download of cloudflared binary if not present
set "CF_EXE=%~dp0src\bin\windows\x64\cloudflared.exe"
if not exist "!CF_EXE!" (
    start /b "" powershell -NoProfile -ExecutionPolicy Bypass -Command "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; $dir = '%~dp0src\bin\windows\x64'; if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Force -Path $dir | Out-Null }; $tmp = \"$dir\cloudflared.exe.tmp\"; $dst = \"$dir\cloudflared.exe\"; if (-not (Test-Path $tmp)) { try { (New-Object System.Net.WebClient).DownloadFile('https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe', $tmp); if ((Get-Item $tmp).Length -gt 10000000) { Move-Item -Force $tmp $dst } else { Remove-Item -Force $tmp } } catch {} }" >nul 2>&1
)


:: 6. Locate PHP runtime engine
echo {"step":"php","status":"running","message":"Loading runtime engine..."} > "%~dp0status.json"
set "PHP_CMD="

:: 1st Priority: Pre-bundled portable PHP in bin\php
if exist "%~dp0bin\php\php.exe" (
    set "PHP_CMD=%~dp0bin\php\php.exe"
)

:: 2nd Priority: Portable PHP in .app_data\php
if not defined PHP_CMD (
    if exist "%~dp0.app_data\php\php.exe" (
        set "PHP_CMD=%~dp0.app_data\php\php.exe"
    )
)

:: 3rd Priority: System PATH PHP >= 8.2
if not defined PHP_CMD (
    for /f "delims=" %%I in ('where php 2^>nul') do (
        if not defined PHP_CMD (
            "%%I" -r "exit((PHP_VERSION_ID >= 80200) ? 0 : 1);" >nul 2>&1
            if !ERRORLEVEL! equ 0 set "PHP_CMD=%%I"
        )
    )
)

:: 4th Priority: Scan common installation directories
if not defined PHP_CMD (
    for %%P in (
        "C:\php\php.exe"
        "C:\tools\php\php.exe"
        "C:\xampp\php\php.exe"
        "D:\xampp\php\php.exe"
        "E:\xampp\php\php.exe"
        "%LOCALAPPDATA%\Programs\php\php.exe"
    ) do (
        if not defined PHP_CMD (
            if exist %%P (
                %%P -r "exit((PHP_VERSION_ID >= 80200) ? 0 : 1);" >nul 2>&1
                if !ERRORLEVEL! equ 0 set "PHP_CMD=%%~P"
            )
        )
    )
)

if not defined PHP_CMD (
    for /d %%D in ("C:\laragon\bin\php\*") do (
        if not defined PHP_CMD (
            if exist "%%D\php.exe" (
                "%%D\php.exe" -r "exit((PHP_VERSION_ID >= 80200) ? 0 : 1);" >nul 2>&1
                if !ERRORLEVEL! equ 0 set "PHP_CMD=%%D\php.exe"
            )
        )
    )
)

if not defined PHP_CMD (
    for /d %%D in ("C:\Program Files\PHP\*") do (
        if not defined PHP_CMD (
            if exist "%%D\php.exe" (
                "%%D\php.exe" -r "exit((PHP_VERSION_ID >= 80200) ? 0 : 1);" >nul 2>&1
                if !ERRORLEVEL! equ 0 set "PHP_CMD=%%D\php.exe"
            )
        )
    )
)

:: If PHP is still not found, report error
if not defined PHP_CMD (
    echo {"step":"php","status":"error","message":"Runtime not found. Please ensure bin/php is included or install runtime."} > "%~dp0status.json"
    exit /b 1
)

:: Prepend PHP directory to session PATH and isolate PHPRC
for %%F in ("!PHP_CMD!") do set "PHP_DIR=%%~dpF"
if defined PHP_DIR (
    if "!PHP_DIR:~-1!"=="\" set "PHP_DIR=!PHP_DIR:~0,-1!"
    set "PHPRC=!PHP_DIR!"
    set "PATH=!PHP_DIR!;!PHP_DIR!\ext;!PATH!"
)

:: Locate php.ini and ext directory
set "PHP_INI="
if exist "!PHP_DIR!\php.ini" (
    set "PHP_INI=!PHP_DIR!\php.ini"
) else if exist "%~dp0bin\php\php.ini" (
    set "PHP_INI=%~dp0bin\php\php.ini"
)

set "PHP_EXT_DIR="
if exist "!PHP_DIR!\ext" (
    set "PHP_EXT_DIR=!PHP_DIR!\ext"
) else if exist "%~dp0bin\php\ext" (
    set "PHP_EXT_DIR=%~dp0bin\php\ext"
)

:: Ensure required runtime DLLs are mirrored in ext directory for Win32 LoadLibrary resolution
if defined PHP_EXT_DIR (
    if not exist "!PHP_EXT_DIR!" mkdir "!PHP_EXT_DIR!" >nul 2>&1
    for %%D in ("!PHP_DIR!\*.dll") do (
        if not exist "!PHP_EXT_DIR!\%%~nxD" (
            copy /y "%%D" "!PHP_EXT_DIR!\%%~nxD" >nul 2>&1
        )
    )
)

:: Build unified PHP execution command
set "PHP_EXEC="!PHP_CMD!""
if defined PHP_INI (
    set "PHP_EXEC=!PHP_EXEC! -c "!PHP_INI!""
)
if defined PHP_EXT_DIR (
    set "PHP_EXEC=!PHP_EXEC! -d extension_dir="!PHP_EXT_DIR!""
)

echo {"step":"php","status":"done","message":"Runtime verified"} > "%~dp0status.json"

:: 7. Verify and auto-configure required PHP SQLite & core extensions
echo {"step":"database","status":"running","message":"Verifying SQLite database and extensions..."} > "%~dp0status.json"

:: Check if pdo_sqlite is already active
!PHP_EXEC! -r "exit(extension_loaded('pdo_sqlite') ? 0 : 1);" >nul 2>&1
if !ERRORLEVEL! neq 0 (
    :: Attempt A: Dynamically enable extensions via -d flags
    !PHP_EXEC! -d extension=pdo_sqlite -d extension=sqlite3 -r "exit(extension_loaded('pdo_sqlite') ? 0 : 1);" >nul 2>&1
    if !ERRORLEVEL! equ 0 (
        set "PHP_EXEC=!PHP_EXEC! -d extension=pdo_sqlite -d extension=sqlite3"
    ) else (
        :: Attempt B: Explicit .dll suffix for Windows PHP
        !PHP_EXEC! -d extension=php_pdo_sqlite.dll -d extension=php_sqlite3.dll -r "exit(extension_loaded('pdo_sqlite') ? 0 : 1);" >nul 2>&1
        if !ERRORLEVEL! equ 0 (
            set "PHP_EXEC=!PHP_EXEC! -d extension=php_pdo_sqlite.dll -d extension=php_sqlite3.dll"
        ) else (
            :: Attempt C: Auto-uncomment required extensions in php.ini if present and commented out
            if defined PHP_INI if exist "!PHP_INI!" (
                powershell -NoProfile -ExecutionPolicy Bypass -Command "$p = '!PHP_INI!'; if (Test-Path $p) { (Get-Content $p) | ForEach-Object { $_ -replace '^[;\s]*extension\s*=\s*(pdo_sqlite|sqlite3|curl|openssl|mbstring|fileinfo)\b', 'extension=$1' } | Set-Content $p }" >nul 2>&1
            )
        )
    )
)

:: Final verification: check if pdo_sqlite extension is operational
!PHP_EXEC! -r "exit(extension_loaded('pdo_sqlite') ? 0 : 1);" >nul 2>&1
if !ERRORLEVEL! neq 0 (
    set "ERR_TMP=%TEMP%\td_db_err_%RANDOM%.log"
    !PHP_EXEC! -d display_startup_errors=On -d display_errors=On -r "new PDO('sqlite::memory:');" > "!ERR_TMP!" 2>&1
    set "ERR_DETAIL="
    if exist "!ERR_TMP!" (
        for /f "usebackq delims=" %%E in ("!ERR_TMP!") do (
            if not defined ERR_DETAIL set "ERR_DETAIL=%%E"
        )
        del "!ERR_TMP!" >nul 2>&1
    )
    if not defined ERR_DETAIL (
        set "ERR_DETAIL=The pdo_sqlite extension could not be loaded. Please ensure bin/php/ext/php_pdo_sqlite.dll is present or enable extension=pdo_sqlite in php.ini."
    )
    echo {"step":"database","status":"error","message":"!ERR_DETAIL!"} > "%~dp0status.json"
    exit /b 1
)

:: 8. Ensure storage and log directories exist
if not exist "%~dp0src\storage\framework\cache\data" mkdir "%~dp0src\storage\framework\cache\data" >nul 2>&1
if not exist "%~dp0src\storage\framework\sessions" mkdir "%~dp0src\storage\framework\sessions" >nul 2>&1
if not exist "%~dp0src\storage\framework\views" mkdir "%~dp0src\storage\framework\views" >nul 2>&1
if not exist "%~dp0src\storage\logs" mkdir "%~dp0src\storage\logs" >nul 2>&1

:: 9. Ensure .env file exists
if not exist "%~dp0src\.env" (
    if exist "%~dp0src\.env.example" (
        copy /y "%~dp0src\.env.example" "%~dp0src\.env" >nul 2>&1
    )
)

:: 10. Ensure database.sqlite exists and has tables
set "DB_FILE=%~dp0src\database\database.sqlite"
set "NEED_INIT=0"
if not exist "!DB_FILE!" (
    set "NEED_INIT=1"
) else (
    for %%A in ("!DB_FILE!") do if %%~zA equ 0 set "NEED_INIT=1"
)
if "!NEED_INIT!"=="1" (
    type nul > "!DB_FILE!"
    if exist "%~dp0src\database\schema\sqlite-schema.sql" (
        !PHP_EXEC! -r "$db = new PDO('sqlite:%~dp0src/database/database.sqlite'); $sql = file_get_contents('%~dp0src/database/schema/sqlite-schema.sql'); $db->exec($sql);" >nul 2>&1
    )
)

echo {"step":"database","status":"done","message":"SQLite database and environment ready"} > "%~dp0status.json"

:: 11. Run server directly with built-in router and bundled SSL CA bundle
echo {"step":"server","status":"running","message":"Starting local server on port !PORT!..."} > "%~dp0status.json"
echo [TunnelDeck Server Starting on 127.0.0.1:!PORT!] > "%~dp0src\storage\logs\startup.log"
cd /d "%~dp0src"
start /b "" !PHP_EXEC! -d curl.cainfo="%~dp0src\storage\cacert.pem" -d openssl.cafile="%~dp0src\storage\cacert.pem" -S 127.0.0.1:!PORT! -t "%~dp0src\public" "%~dp0src\server.php" >> "%~dp0src\storage\logs\startup.log" 2>&1

:: 12. Monitor browser lifetime - when user closes window, terminate backend cleanly
if defined BROWSER_EXE (
    powershell -NoProfile -ExecutionPolicy Bypass -Command "$dir = '!DATA_DIR!'; while (Get-CimInstance Win32_Process -Filter \"Name = 'msedge.exe' or Name = 'chrome.exe'\" -ErrorAction SilentlyContinue | Where-Object { $_.CommandLine -and $_.CommandLine.Contains($dir) }) { Start-Sleep -Milliseconds 800 }" >nul 2>&1
)

:: User closed browser window: cleanly stop PHP server and background tunnels
powershell -NoProfile -ExecutionPolicy Bypass -Command "$curr = '%~dp0'.TrimEnd('\'); $procs = Get-CimInstance Win32_Process -Filter \"Name = 'php.exe'\" -ErrorAction SilentlyContinue; foreach ($p in $procs) { if ($p.CommandLine -and ($p.CommandLine -like ('*' + $curr + '*') -or $p.CommandLine -match 'server\.php')) { Stop-Process -Id $p.ProcessId -Force -ErrorAction SilentlyContinue } }" >nul 2>&1
exit /b 0
