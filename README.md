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

## 📁 Project Architecture

```
tunneldeck-lite/
├── src/
│   ├── backend/                      # Complete Laravel Application (Frontend + Backend)
│   │   ├── app/
│   │   │   ├── Http/Controllers/
│   │   │   │   ├── DashboardController.php   # Loads live metrics, tunnels, domains, activities
│   │   │   │   ├── WebAuthController.php     # Session auth, login, logout, password change
│   │   │   │   └── Api/                      # JSON API endpoints
│   │   │   └── Models/                       # User, Tunnel, Domain, Activity models
│   │   ├── database/
│   │   │   ├── database.sqlite               # Local SQLite database
│   │   │   └── seeders/                      # Default admin & sample data seeders
│   │   ├── public/
│   │   │   └── js/
│   │   │       └── app.js                    # Vanilla JS AJAX client (modals, forms, clipboard)
│   │   ├── resources/
│   │   │   └── views/
│   │   │       ├── auth/
│   │   │       │   └── login.blade.php       # Login view with branded logo & "Lite" pill badge
│   │   │       ├── layouts/
│   │   │       │   └── app.blade.php         # Master Blade layout & toast notification container
│   │   │       ├── partials/
│   │   │       │   ├── modals.blade.php      # All 8 application modals (Start, Add, Delete, Cloudflare)
│   │   │       │   ├── tunnels_table_rows.blade.php # Tunnel list rows
│   │   │       │   └── domains_table_rows.blade.php # Domain list rows
│   │   │       └── dashboard.blade.php       # Full tabbed dashboard view
│   │   └── routes/
│   │       └── web.php                       # Unified web & session-authenticated AJAX routes
│   └── frontend/                             # (Archived) Legacy Next.js decoupled frontend
└── README.md
```

---

## 🛠️ Quick Start (Single Command)

All you need is PHP installed on your machine. No Node.js required!

```bash
cd src/backend

# Start the Laravel application server
php artisan serve
```

Open your browser and navigate to:
👉 **[http://127.0.0.1:8000](http://127.0.0.1:8000)**

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
3. **Custom Domains:**
   - Configured domains table with validation badges (`Validated` / `Failed`).
   - Flush top right-hand Cloudflare setup guide.
   - Cloudflare instructions modal & domain deletion with custom modal.
4. **Platform Settings:**
   - SQLite status, PHP version, Laravel environment, and Cloudflare status.
   - Right sidebar to update password with toggle to autofill login credentials.
5. **Global Dynamic Toasts:**
   - Floating notifications for all actions: tunnel added, tunnel deleted, domain deleted, password saved, etc.

---

## 🧪 Testing

Run backend feature and unit tests with PHPUnit:

```bash
cd src/backend
./vendor/bin/phpunit
```
