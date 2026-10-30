# TunnelDeck Lite

A modern developer tunnel management platform built with **Laravel (Backend)** and **Next.js (Frontend)**.

---

## 🚀 Features

- **Backend:** Laravel with SQLite database & Laravel Sanctum token-based authentication.
- **Frontend:** Next.js (App Router, Tailwind CSS, TypeScript, Lucide Icons).
- **Default Authentication:** Direct login without OTP.
  - **Email:** `admin@tunneldeck.online`
  - **Password:** `123456`
- **Dashboard:**
  - Real-time tunnel status (Active / Paused / Stopped)
  - Forwarding controls (Protocol, local port, public tunnel URLs)
  - SQLite status & metrics overview (total tunnels, active tunnels, connection stats)
  - One-click copy for public URLs and tunnel management modal

---

## 📁 Project Structure

```
tunneldeck-lite/
├── src/
│   ├── backend/          # Laravel SQLite API Backend
│   │   ├── app/
│   │   │   ├── Http/Controllers/Api/   # AuthController & TunnelController
│   │   │   └── Models/                 # User & Tunnel models
│   │   ├── database/
│   │   │   ├── database.sqlite         # SQLite database file
│   │   │   ├── migrations/             # Database migrations
│   │   │   └── seeders/DatabaseSeeder  # Seeds default admin & sample tunnels
│   │   └── routes/api.php              # REST API endpoints
│   │
│   └── frontend/         # Next.js Frontend Application
│       ├── app/
│       │   ├── page.tsx                # Renders Login page by default (or Dashboard if authenticated)
│       │   └── layout.tsx
│       ├── components/
│       │   ├── LoginForm.tsx           # Default login screen with demo autofill
│       │   └── Dashboard.tsx           # Full tunnel management dashboard
│       └── lib/
│           └── api.ts                  # API client & local auth management
└── README.md
```

---

## 🛠️ Getting Started

### 1. Start the Laravel Backend

```bash
cd src/backend

# Migrate and seed SQLite database (if not already done)
php artisan migrate --seed

# Start the Laravel development server (runs on port 8000)
php artisan serve
```

Backend will be active at `http://127.0.0.1:8000`.

### 2. Start the Next.js Frontend

```bash
cd src/frontend

# Run development server (runs on port 3000)
npm run dev
```

Visit `http://localhost:3000` in your browser.

---

## 🔑 Default Credentials

| Field | Value |
|---|---|
| **Email** | `admin@tunneldeck.online` |
| **Password** | `123456` |
| **OTP** | *None required (instant login)* |

---

## 📡 API Endpoints

| Method | Endpoint | Description | Auth Required |
|---|---|---|---|
| `POST` | `/api/login` | Login with email & password | No |
| `GET` | `/api/health` | SQLite database & API health check | No |
| `GET` | `/api/user` | Current authenticated user profile | Yes (`Bearer token`) |
| `POST` | `/api/logout` | Revoke current auth token | Yes (`Bearer token`) |
| `GET` | `/api/tunnels` | List all tunnels and overview metrics | Yes (`Bearer token`) |
| `POST` | `/api/tunnels` | Create a new forwarding tunnel | Yes (`Bearer token`) |
| `PATCH` | `/api/tunnels/{id}/toggle` | Toggle tunnel status (active / paused) | Yes (`Bearer token`) |
| `DELETE` | `/api/tunnels/{id}` | Delete a tunnel | Yes (`Bearer token`) |
