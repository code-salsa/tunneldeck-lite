# TunnelDeck Lite

A modern, lightweight developer tunnel management platform built as a **pure Laravel application** with **native Blade views, vanilla HTML, CSS, and native JavaScript AJAX** — zero Node.js runtime required!

---

## 🚀 Key Highlights

- **100% Pure Laravel:** Both frontend (Blade templates) and backend (Laravel routes & controllers) run seamlessly in a single Laravel process.
- **Zero Node.js Dependency:** No npm, no node runtime, no Webpack/Vite build step required. Uses Tailwind CSS and Lucide Icons via high-speed CDN.
- **Vanilla JavaScript AJAX:** Dynamic actions (tunnel starting/stopping, domain validation, deleting, password updates, filtering) use native `window.fetch()` with CSRF token authentication.
- **SQLite Database:** Zero database setup; runs out-of-the-box with a local SQLite database file.
- **Pre-filled Instant Login:** Direct login without OTP.
  - **Email:** `admin@tunneldeck.online`
  - **Password:** `123456`

---

## 🚀 Quick Start (One-Click Launch)

No manual commands, complex PHP configuration, or Node.js required! 

Simply double-click **`App.exe`** in the root directory:
- ⚡ **Zero Setup:** Automatically detects existing PHP or installs a portable runtime into `.app_data/php`.
- 🖥️ **Desktop Shortcut:** Automatically creates a convenient TunnelDeck shortcut on your Windows desktop.
- 🌐 **Instant Launch:** Automatically boots the local engine and opens the application workspace.

---

## 🗑️ Clean Uninstallation

To remove installed runtime libraries, cache, and clean up your system at any time:

Simply double-click **`uninstall.exe`** in the root directory:
- 🧹 **Package & Runtime Cleanup:** Safely removes the installed/bundled portable PHP runtime (`bin/php`) and `.app_data` directory.
- 🖥️ **Shortcut Removal:** Deletes the TunnelDeck desktop shortcut (`TunnelDeck.lnk`).
- 🛑 **Process Termination:** Safely terminates any active background PHP or Cloudflared processes.
- ⚡ **Silent Mode (Optional):** Run `uninstall.exe /silent` from CLI for silent, unattended uninstallation.

---

## 🔑 Default Credentials

| Field | Value |
|---|---|
| **Email** | `admin@tunneldeck.online` |
| **Password** | `123456` |
| **Authentication** | Direct login (No OTP required) |

---

## 🖥️ Application Features & Tabs

1. **Dashboard Overview:**
   - 4 summary metrics cards: Total Tunnels, Active Tunnels, Custom Domains, Cloudflare Network status.
   - Quick Active Tunnels table with copy-to-clipboard for URLs.
   - Recent Activities stream.
2. **Tunnels Manager:**
   - Live tunnel statuses (`Active`, `Paused`, `Stopped`, `Error`).
   - Copyable Local URLs (`http://localhost:XXXX`) with instant visual checkmark feedback.
   - Public URLs with real-time routing preview.
   - **Start Tunnel Routing Modal:** Apex domain vs Subdomain selection with auto-updating endpoint previews.
   - **Add New Tunnel Modal:** Input tunnel name and port number.
   - **Custom Delete Modal:** Clean confirmation dialog instead of browser alert.
3. **Platform Settings:**
   - SQLite status, PHP version, Laravel environment, and Cloudflare engine status.
   - Profile management, password updates, and licensing synchronization.
4. **Global Dynamic Notifications:**
   - Real-time toast notifications for all platform actions (tunnel start/stop, settings changes, network alerts).
