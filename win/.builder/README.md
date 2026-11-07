# TunnelDeck Lite — Native Windows Installer

This folder contains the complete, self-contained Windows Installer system for **TunnelDeck Lite**.

It produces a **single, standalone `.exe` setup file** (`TunnelDeck-Lite-Setup.exe`) that delivers the genuine, native Windows installer wizard experience.

---

## 🌟 User Experience & Workflow

When a user downloads and double-clicks `TunnelDeck-Lite-Setup.exe`:

1. **Native Setup Wizard Window**:
   - Modern Segoe UI typography, native button controls, and TunnelDeck branding.
   - **Step 1 — Welcome**: Explains the application purpose and features.
   - **Step 2 — Destination Location**: Asks the user where they want to install TunnelDeck Lite.
     - Default: `%LOCALAPPDATA%\Programs\TunnelDeck Lite` (per-user installation, requires **no admin UAC privileges**).
     - **Browse...** button opens the native Windows Folder Browser dialog.
     - Displays required disk space and real-time available disk space on the chosen drive.
   - **Step 3 — Additional Tasks**: Options to create a Desktop shortcut, Start Menu shortcut, and automatic Windows startup.
   - **Step 4 — Real-time Progress Bar**: Shows extraction progress bar (0–100%) and current file status as the application tree and portable runtimes are extracted.
   - **Step 5 — Completion**: Congratulates the user on successful installation, includes a `"Launch TunnelDeck Lite now"` checkbox, and exits cleanly into the application on **Finish**.

2. **Windows Shell & Control Panel Integration**:
   - Creates a **Desktop Shortcut** with the official TunnelDeck icon (`app.ico`).
   - Creates a **Start Menu Folder** (`Start Menu > Programs > TunnelDeck Lite`) with launcher and uninstaller shortcuts.
   - Registers in Windows **Settings > Apps > Installed Apps** (and Control Panel **Add or Remove Programs**) with full metadata (version 1.0.0, publisher, install path, icon, size).

3. **Built-in Native Uninstaller**:
   - An `uninstall.exe` is placed in the installation directory.
   - Can be triggered directly or via Windows Settings / Control Panel.
   - Prompts for user confirmation, cleanly removes all files, shortcuts, and registry keys, and deletes itself.

---

## 📁 Folder Structure

```
tunneldeck-lite/
├── win/
│   ├── build.bat                   <-- Run this script to re-compile the installer
│   ├── build-payload.ps1           <-- Stages clean project files and creates payload.zip
│   ├── Installer.cs                <-- C# WinForms source for the native Setup Wizard
│   ├── Uninstaller.cs              <-- C# WinForms source for the uninstaller
│   ├── setup.iss                   <-- Optional Inno Setup script (for ISCC users)
│   ├── README.md                   <-- This guide
│   └── output/
│       └── TunnelDeck-Lite-Setup.exe  <-- Standalone installer (.exe) ready for distribution
```

---

## 🔨 How to Build / Rebuild

The build system requires **zero external third-party tools or SDKs**. It utilizes the Microsoft C# compiler (`csc.exe`) built directly into every Windows 10 and Windows 11 installation:

1. Open a terminal or double-click:
   ```cmd
   d:\FINAL SAAS PROJECTS\tunneldeck-lite\win\build.bat
   ```
2. The script will automatically:
   - Compile `uninstall.exe`
   - Stage the latest project files (excluding dev caches, tests, and logs) into `payload.zip`
   - Embed `payload.zip` and `uninstall.exe` directly inside the final installer
   - Output `win\output\TunnelDeck-Lite-Setup.exe` (~24 MB)

---

## 🚀 Distribution

To distribute TunnelDeck Lite to end users:
- Simply share `win\output\TunnelDeck-Lite-Setup.exe`.
- Users do **not** need PHP, Node.js, Composer, or .NET SDK installed.
- Works on Windows 10, Windows 11, and Windows Server 2016+.

