# TunnelDeck Lite - macOS Package Installer

Native macOS flat `.pkg` package for **TunnelDeck Lite**.

---

## Installation on macOS

1. Double-click **`Tunneldeck.pkg`** in Finder.
2. The native macOS Installer wizard will open and guide you through installation.
3. Authenticate with your Mac password or Touch ID when prompted.
4. TunnelDeck will be installed to:
   ```
   /Applications/TunnelDeck.app
   ```
5. Launch **TunnelDeck** directly from **Launchpad**, **Spotlight**, or `/Applications`.

---

## Requirements

- **macOS Version:** macOS 10.15 (Catalina), macOS 11 (Big Sur), macOS 12 (Monterey), macOS 13 (Ventura), macOS 14 (Sonoma), macOS 15 (Sequoia) or later.
- **Architecture:** Apple Silicon (M1/M2/M3/M4) & Intel (x86_64).
- **PHP Runtime:** PHP 8.2+ with `pdo_sqlite` and `sqlite3` enabled.
  - Install via Homebrew:
    ```bash
    brew install php
    ```

---

## Building the `.pkg` Installer

To rebuild `Tunneldeck.pkg`:

- **On Windows:**
  ```cmd
  mac\build.bat
  ```

- **On macOS / Linux:**
  ```bash
  chmod +x mac/build.sh
  ./mac/build.sh
  ```

Output package location:
```
mac/Tunneldeck.pkg
```

