const fs = require('fs');
const path = require('path');
const zlib = require('zlib');
const crypto = require('crypto');
const cp = require('child_process');

const rootDir = path.resolve(__dirname, '../..');
const macDir = path.resolve(__dirname, '..');
const builderDir = __dirname;
const stagingDir = path.join(builderDir, 'staging');
const outputPkg = path.join(macDir, 'Tunneldeck.pkg');

console.log('==============================================================');
console.log('      TunnelDeck Lite - Native macOS PKG Builder');
console.log('==============================================================\n');

// 1. Clean previous build staging
if (fs.existsSync(stagingDir)) {
  fs.rmSync(stagingDir, { recursive: true, force: true });
}
if (fs.existsSync(outputPkg)) {
  fs.unlinkSync(outputPkg);
}

const stagePayload = path.join(stagingDir, 'stage_payload');
const stageScripts = path.join(stagingDir, 'stage_scripts');
const appBundle = path.join(stagePayload, 'TunnelDeck.app');
const contentsDir = path.join(appBundle, 'Contents');
const macOsDir = path.join(contentsDir, 'MacOS');
const resourcesDir = path.join(contentsDir, 'Resources');
const appTargetDir = path.join(resourcesDir, 'app');

fs.mkdirSync(macOsDir, { recursive: true });
fs.mkdirSync(appTargetDir, { recursive: true });
fs.mkdirSync(stageScripts, { recursive: true });

// 2. Generate AppIcon.icns from branding-icon.png
console.log('[1/5] Generating Apple ICNS icon bundle...');
const pngPath = path.join(rootDir, 'src/public/images/branding-icon.png');
const pngData = fs.readFileSync(pngPath);

// Create valid ICNS with 1024x1024 retina element (ic10)
const ic10Tag = Buffer.from('ic10', 'ascii');
const ic10Len = Buffer.alloc(4);
ic10Len.writeUInt32BE(8 + pngData.length, 0);
const ic10Elem = Buffer.concat([ic10Tag, ic10Len, pngData]);

const icnsLen = Buffer.alloc(4);
icnsLen.writeUInt32BE(8 + ic10Elem.length, 0);
const icnsHeader = Buffer.concat([Buffer.from('icns', 'ascii'), icnsLen]);
const icnsBuffer = Buffer.concat([icnsHeader, ic10Elem]);

const icnsPath = path.join(resourcesDir, 'AppIcon.icns');
fs.writeFileSync(icnsPath, icnsBuffer);
console.log('      + Created AppIcon.icns (' + (icnsBuffer.length / 1024).toFixed(1) + ' KB)');

// 3. Create Info.plist and PkgInfo
console.log('[2/5] Creating macOS bundle metadata (Info.plist, PkgInfo)...');
const infoPlist = `<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>CFBundleExecutable</key>
    <string>TunnelDeck</string>
    <key>CFBundleIconFile</key>
    <string>AppIcon</string>
    <key>CFBundleIdentifier</key>
    <string>online.tunneldeck.lite</string>
    <key>CFBundleName</key>
    <string>TunnelDeck</string>
    <key>CFBundleDisplayName</key>
    <string>TunnelDeck</string>
    <key>CFBundlePackageType</key>
    <string>APPL</string>
    <key>CFBundleShortVersionString</key>
    <string>1.0.0</string>
    <key>CFBundleVersion</key>
    <string>1.0.0</string>
    <key>LSMinimumSystemVersion</key>
    <string>10.15</string>
    <key>NSHighResolutionCapable</key>
    <true/>
    <key>NSSupportsAutomaticGraphicsSwitching</key>
    <true/>
    <key>LSUIElement</key>
    <false/>
</dict>
</plist>\n`;

fs.writeFileSync(path.join(contentsDir, 'Info.plist'), infoPlist.replace(/\r\n/g, '\n'), 'utf8');
fs.writeFileSync(path.join(contentsDir, 'PkgInfo'), 'APPL????', 'ascii');

// 4. Create macOS executable launcher script (TunnelDeck)
const launcherScript = `#!/bin/bash
# ==============================================================================
# TunnelDeck Lite - macOS Native Launcher
# ==============================================================================

export PATH="/opt/homebrew/bin:/opt/homebrew/sbin:/usr/local/bin:/usr/local/sbin:$PATH:/usr/bin:/bin:/usr/sbin:/sbin"

APP_DIR="$(cd "$(dirname "$0")/../Resources/app" && pwd)"
cd "$APP_DIR" || exit 1

# Setup dedicated writable user application data directory
USER_DATA_DIR="$HOME/Library/Application Support/TunnelDeck"
mkdir -p "$USER_DATA_DIR/browser"
mkdir -p "$USER_DATA_DIR/logs"

LOG_FILE="$USER_DATA_DIR/logs/startup.log"
echo "[TunnelDeck Launching: $(date)]" > "$LOG_FILE"

# 1. Terminate previous stale TunnelDeck server instances
pkill -f "tunneldeck.*server.php" 2>/dev/null || true

# 2. Pick a random free port between 9000 and 9999
get_free_port() {
    while true; do
        PORT=$((9000 + RANDOM % 1000))
        if ! lsof -Pi :$PORT -sTCP:LISTEN -t >/dev/null 2>&1; then
            echo "$PORT"
            return
        fi
    done
}
PORT=$(get_free_port)
echo "Allocated Port: $PORT" >> "$LOG_FILE"

# 3. Locate PHP 8.2+ runtime
PHP_BIN=""
CANDIDATE_PHPS=(
    "$APP_DIR/bin/php"
    "/opt/homebrew/bin/php"
    "/usr/local/bin/php"
    "$(which php 2>/dev/null)"
    "$HOME/.herd/bin/php"
    "$HOME/.orbstack/bin/php"
)

for p in "\${CANDIDATE_PHPS[@]}"; do
    if [ -n "$p" ] && [ -x "$p" ]; then
        PHP_BIN="$p"
        break
    fi
done

if [ -z "$PHP_BIN" ]; then
    osascript -e 'display dialog "TunnelDeck requires a local PHP runtime (PHP 8.2+).\\n\\nPlease install PHP via Homebrew in Terminal:\\n  brew install php" with title "TunnelDeck - Runtime Required" buttons {"Open Instructions", "OK"} default button "OK" with icon caution' >/dev/null 2>&1
    open "https://brew.sh"
    exit 1
fi
echo "Using PHP: $PHP_BIN" >> "$LOG_FILE"

# 4. Check for cloudflared binary
if [ -f "$APP_DIR/bin/cloudflared" ]; then
    chmod +x "$APP_DIR/bin/cloudflared"
fi

# 5. Locate Chrome, Edge, or Default Browser
SPLASH_URL="file://$APP_DIR/loading.html?port=$PORT"
BROWSER_APP=""
if [ -d "/Applications/Google Chrome.app" ]; then
    BROWSER_APP="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
elif [ -d "$HOME/Applications/Google Chrome.app" ]; then
    BROWSER_APP="$HOME/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
elif [ -d "/Applications/Microsoft Edge.app" ]; then
    BROWSER_APP="/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge"
elif [ -d "$HOME/Applications/Microsoft Edge.app" ]; then
    BROWSER_APP="$HOME/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge"
elif [ -d "/Applications/Brave Browser.app" ]; then
    BROWSER_APP="/Applications/Brave Browser.app/Contents/MacOS/Brave Browser"
fi

# 6. Launch splash loader window
if [ -n "$BROWSER_APP" ]; then
    "$BROWSER_APP" --app="$SPLASH_URL" --allow-file-access-from-files --user-data-dir="$USER_DATA_DIR/browser" --no-first-run --no-default-browser-check --app-id=tunneldeck --window-size=1280,800 >/dev/null 2>&1 &
    BROWSER_PID=$!
else
    open "$SPLASH_URL"
fi

# 7. Start PHP Server
mkdir -p "$APP_DIR/src/storage/logs"
cd "$APP_DIR/src"
"$PHP_BIN" -d curl.cainfo="$APP_DIR/src/storage/cacert.pem" -d openssl.cafile="$APP_DIR/src/storage/cacert.pem" -S 127.0.0.1:$PORT -t "$APP_DIR/src/public" "$APP_DIR/src/server.php" >> "$LOG_FILE" 2>&1 &
SERVER_PID=$!

# 8. Clean shutdown trap on window close or exit
cleanup() {
    echo "Stopping TunnelDeck server (PID: $SERVER_PID)..." >> "$LOG_FILE"
    kill -TERM "$SERVER_PID" 2>/dev/null || true
    pkill -f "tunneldeck.*server.php" 2>/dev/null || true
    exit 0
}
trap cleanup SIGINT SIGTERM EXIT

# Monitor browser lifetime if running in app mode
if [ -n "$BROWSER_PID" ]; then
    while kill -0 "$BROWSER_PID" 2>/dev/null; do
        sleep 1
    done
    cleanup
else
    wait "$SERVER_PID"
fi
\n`;

const launcherPath = path.join(macOsDir, 'TunnelDeck');
fs.writeFileSync(launcherPath, launcherScript.replace(/\r\n/g, '\n'), 'utf8');

// Copy serve.sh into appTargetDir as well for CLI users
fs.writeFileSync(path.join(appTargetDir, 'serve.sh'), launcherScript.replace(/\r\n/g, '\n'), 'utf8');

// Copy root launcher files into appTargetDir
console.log('[3/5] Staging application source code into app bundle...');
const rootFiles = ['loading.html', 'app.ico'];
for (const f of rootFiles) {
  const p = path.join(rootDir, f);
  if (fs.existsSync(p)) {
    fs.copyFileSync(p, path.join(appTargetDir, f));
  }
}

// Copy src/ recursively into appTargetDir/src
function copyDirFiltered(src, dest, excludes) {
  if (!fs.existsSync(dest)) fs.mkdirSync(dest, { recursive: true });
  const entries = fs.readdirSync(src, { withFileTypes: true });
  for (const entry of entries) {
    const srcPath = path.join(src, entry.name);
    const destPath = path.join(dest, entry.name);
    
    let isExcluded = false;
    for (const ex of excludes) {
      if (srcPath.includes(ex)) {
        isExcluded = true;
        break;
      }
    }
    if (isExcluded) continue;

    if (entry.isDirectory()) {
      copyDirFiltered(srcPath, destPath, excludes);
    } else {
      fs.copyFileSync(srcPath, destPath);
    }
  }
}

const excludes = [
  path.normalize('.git'),
  path.normalize('node_modules'),
  path.normalize('tests'),
  path.normalize('.phpunit'),
  path.normalize('.app_data'),
  '.log',
  '.exe',
  '.dll'
];

copyDirFiltered(path.join(rootDir, 'src'), path.join(appTargetDir, 'src'), excludes);

// Ensure storage directories exist in staged bundle
const storageDirs = [
  'src/storage/app',
  'src/storage/framework/cache/data',
  'src/storage/framework/sessions',
  'src/storage/framework/views',
  'src/storage/logs'
];
for (const sd of storageDirs) {
  fs.mkdirSync(path.join(appTargetDir, sd), { recursive: true });
}

// 5. Create postinstall script
const postinstallScript = `#!/bin/bash
# ==============================================================================
# TunnelDeck Lite - macOS Post-Install Hook
# ==============================================================================
set -e

APP_PATH="/Applications/TunnelDeck.app"

if [ -d "$APP_PATH" ]; then
    # 1. Set standard application permissions
    chmod -R 755 "$APP_PATH"
    chmod +x "$APP_PATH/Contents/MacOS/TunnelDeck"
    if [ -f "$APP_PATH/Contents/Resources/app/serve.sh" ]; then
        chmod +x "$APP_PATH/Contents/Resources/app/serve.sh"
    fi

    # 2. Grant write permissions for SQLite database, cache, and logs
    chmod -R 777 "$APP_PATH/Contents/Resources/app/src/storage" 2>/dev/null || true
    chmod -R 777 "$APP_PATH/Contents/Resources/app/src/bootstrap/cache" 2>/dev/null || true
    if [ -d "$APP_PATH/Contents/Resources/app/src/database" ]; then
        chmod -R 777 "$APP_PATH/Contents/Resources/app/src/database" 2>/dev/null || true
    fi

    # 3. Clear macOS Quarantine flag so Gatekeeper permits execution
    xattr -dr com.apple.quarantine "$APP_PATH" 2>/dev/null || true
fi

exit 0
\n`;

const postinstallPath = path.join(stageScripts, 'postinstall');
fs.writeFileSync(postinstallPath, postinstallScript.replace(/\r\n/g, '\n'), 'utf8');

// 6. Build Payload (cpio.gz) and Scripts (cpio.gz) using bsdtar (tar.exe)
console.log('[4/5] Archiving Payload & Scripts into compressed CPIO archives...');
const payloadCpioGz = path.join(stagingDir, 'Payload');
const scriptsCpioGz = path.join(stagingDir, 'Scripts');

cp.execSync(`tar.exe -czf "${payloadCpioGz}" --format cpio -C "${stagePayload}" TunnelDeck.app`, { stdio: 'inherit' });
cp.execSync(`tar.exe -czf "${scriptsCpioGz}" --format cpio -C "${stageScripts}" postinstall`, { stdio: 'inherit' });

const payloadStat = fs.statSync(payloadCpioGz);
const scriptsStat = fs.statSync(scriptsCpioGz);
console.log('      + Payload archive: ' + (payloadStat.size / 1024 / 1024).toFixed(2) + ' MB');
console.log('      + Scripts archive: ' + (scriptsStat.size / 1024).toFixed(1) + ' KB');

// 7. Generate PackageInfo XML
const packageInfoXml = `<?xml version="1.0" encoding="utf-8"?>
<pkg-info format-version="2" identifier="online.tunneldeck.lite" version="1.0.0" install-location="/Applications" auth="root">
    <payload installKBytes="${Math.ceil(payloadStat.size / 1024)}" numberOfFiles="1800"/>
    <scripts>
        <postinstall file="./postinstall"/>
    </scripts>
    <bundle-version>
        <bundle id="online.tunneldeck.lite" CFBundleShortVersionString="1.0.0" path="./TunnelDeck.app"/>
    </bundle-version>
</pkg-info>\n`;

// 8. Pack flat XAR package
console.log('[5/5] Assembling native flat XAR package -> Tunneldeck.pkg...');

function buildXarPackage(destPath, entries) {
  let currentOffset = 20; // SHA1 digest of compressed TOC is at offset 0-19 of heap
  const fileEntries = [];
  const heapBuffers = [];

  for (let i = 0; i < entries.length; i++) {
    const item = entries[i];
    const data = item.data;
    const sha1 = crypto.createHash('sha1').update(data).digest('hex');
    fileEntries.push({
      id: i + 1,
      name: item.name,
      size: data.length,
      length: data.length,
      offset: currentOffset,
      sha1: sha1
    });
    currentOffset += data.length;
    heapBuffers.push(data);
  }

  let tocXml = '<?xml version="1.0" encoding="UTF-8"?>\n<xar>\n  <toc>\n    <checksum style="sha1">\n      <size>20</size>\n      <offset>0</offset>\n    </checksum>\n';
  for (const fe of fileEntries) {
    tocXml += `    <file id="${fe.id}">\n      <name>${fe.name}</name>\n      <type>file</type>\n      <data>\n        <length>${fe.length}</length>\n        <offset>${fe.offset}</offset>\n        <size>${fe.size}</size>\n        <encoding style="application/octet-stream"/>\n        <archived-checksum style="sha1">${fe.sha1}</archived-checksum>\n        <extracted-checksum style="sha1">${fe.sha1}</extracted-checksum>\n      </data>\n    </file>\n`;
  }
  tocXml += '  </toc>\n</xar>\n';

  const tocBuf = Buffer.from(tocXml, 'utf8');
  const compressedToc = zlib.deflateSync(tocBuf);
  const tocSha1 = crypto.createHash('sha1').update(compressedToc).digest();

  const header = Buffer.alloc(28);
  header.write('xar!', 0, 4, 'ascii');
  header.writeUInt16BE(28, 4);
  header.writeUInt16BE(1, 6);
  header.writeBigUInt64BE(BigInt(compressedToc.length), 8);
  header.writeBigUInt64BE(BigInt(tocBuf.length), 16);
  header.writeUInt32BE(1, 24); // SHA1

  const fullPkg = Buffer.concat([header, compressedToc, tocSha1, ...heapBuffers]);
  fs.writeFileSync(destPath, fullPkg);
}

const pkgEntries = [
  { name: 'PackageInfo', data: Buffer.from(packageInfoXml, 'utf8') },
  { name: 'Payload', data: fs.readFileSync(payloadCpioGz) },
  { name: 'Scripts', data: fs.readFileSync(scriptsCpioGz) }
];

buildXarPackage(outputPkg, pkgEntries);

const finalStat = fs.statSync(outputPkg);
console.log('\n==============================================================');
console.log('  SUCCESS');
console.log('  ' + outputPkg);
console.log('==============================================================');
console.log('  File Size: ' + (finalStat.size / 1024 / 1024).toFixed(2) + ' MB (' + finalStat.size + ' bytes)\n');

// Clean staging directory
fs.rmSync(stagingDir, { recursive: true, force: true });
console.log('Staging directory cleaned up.');

