# Build Payload Script for TunnelDeck Lite Windows Installer
# Packages the application files into a clean compressed payload.zip

$ErrorActionPreference = "Stop"

$RootDir = (Resolve-Path "$PSScriptRoot\..\..").Path
$WinDir = "$RootDir\win"
$StagingDir = "$PSScriptRoot\staging"
$ZipOutput = "$PSScriptRoot\payload.zip"

Write-Host "==============================================" -ForegroundColor Cyan
Write-Host "  TunnelDeck Lite: Packaging Installer Payload " -ForegroundColor Cyan
Write-Host "==============================================" -ForegroundColor Cyan

# 1. Clean previous build artifacts
if (Test-Path $StagingDir) {
    Remove-Item -Recurse -Force $StagingDir
}
if (Test-Path $ZipOutput) {
    Remove-Item -Force $ZipOutput
}

New-Item -ItemType Directory -Force -Path $StagingDir | Out-Null

# 2. Copy root launcher files
Write-Host "Staging root launcher files..." -ForegroundColor Yellow
$rootFiles = @('App.exe', 'app.ico', 'loading.html', 'serve.bat')
foreach ($f in $rootFiles) {
    $src = "$RootDir\$f"
    if (Test-Path $src) {
        Copy-Item -Path $src -Destination "$StagingDir\$f" -Force
        Write-Host "  + $f"
    } else {
        Write-Warning "Missing root file: $f"
    }
}

# 3. Copy bin/ directory (Portable PHP 8.4 runtime with extensions)
Write-Host "Staging bin/ portable runtime..." -ForegroundColor Yellow
if (Test-Path "$RootDir\bin") {
    Copy-Item -Path "$RootDir\bin" -Destination "$StagingDir\bin" -Recurse -Force
    Write-Host "  + bin/ folder copied"
}

# 4. Copy src/ directory (Laravel backend)
Write-Host "Staging src/ application tree..." -ForegroundColor Yellow
$srcStaging = "$StagingDir\src"
New-Item -ItemType Directory -Force -Path $srcStaging | Out-Null

# Exclude patterns
$excludePatterns = @(
    '\.git',
    '\.github',
    '\\tests\\',
    '\\tests$',
    '\\node_modules\\',
    '\\node_modules$',
    '\\\.app_data\\',
    '\\storage\\logs\\.*\.log',
    '\\src\\bin\\.*\.exe',
    '\\src\\bin\\.*\.tmp',
    '\\dist\\',
    '\\\.phpunit',
    'Thumbs\.db'
)

Get-ChildItem -Path "$RootDir\src" -Recurse | ForEach-Object {
    $item = $_
    $relPath = $item.FullName.Substring(("$RootDir\src").Length)
    
    # Check if item matches any exclude pattern
    $exclude = $false
    foreach ($pat in $excludePatterns) {
        if ($item.FullName -match $pat) {
            $exclude = $true
            break
        }
    }

    if (-not $exclude) {
        $targetPath = "$srcStaging$relPath"
        if ($item.PSIsContainer) {
            if (-not (Test-Path $targetPath)) {
                New-Item -ItemType Directory -Force -Path $targetPath | Out-Null
            }
        } else {
            $targetDir = [System.IO.Path]::GetDirectoryName($targetPath)
            if (-not (Test-Path $targetDir)) {
                New-Item -ItemType Directory -Force -Path $targetDir | Out-Null
            }
            Copy-Item -Path $item.FullName -Destination $targetPath -Force
        }
    }
}
Write-Host "  + src/ application tree copied (excluding dev/cache files)"

# Ensure storage directories exist in staging
$storageDirs = @(
    'src\storage\app',
    'src\storage\framework\cache\data',
    'src\storage\framework\sessions',
    'src\storage\framework\views',
    'src\storage\logs'
)
foreach ($sDir in $storageDirs) {
    $fullSDir = "$StagingDir\$sDir"
    if (-not (Test-Path $fullSDir)) {
        New-Item -ItemType Directory -Force -Path $fullSDir | Out-Null
        New-Item -ItemType File -Force -Path "$fullSDir\.gitignore" | Out-Null
    }
}

# 5. Compress staging directory to payload.zip
Write-Host "Compressing payload into payload.zip..." -ForegroundColor Yellow
Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory($StagingDir, $ZipOutput, [System.IO.Compression.CompressionLevel]::Optimal, $false)

# Measure and report
$zipSizeMb = [Math]::Round(((Get-Item $ZipOutput).Length / 1MB), 2)
Write-Host "Payload created successfully: $zipSizeMb MB" -ForegroundColor Green

# Clean up staging directory
Remove-Item -Recurse -Force $StagingDir
Write-Host "Staging directory cleaned up." -ForegroundColor Gray

