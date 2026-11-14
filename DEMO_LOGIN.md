# PLAUD Companion — Demo Login & Environment Guide

Welcome to **PLAUD Companion**, the production-oriented transcript management and AI productivity workspace.

---

## 🌐 Quick Access URLs

| Environment | URL | Purpose |
| :--- | :--- | :--- |
| **Local XAMPP (Apache)** | [http://localhost/plaud-companion/public](http://localhost/plaud-companion/public) | Local web workspace and API |
| **Local Direct Login** | [http://localhost/plaud-companion/public/login](http://localhost/plaud-companion/public/login) | Web sign-in with 1-click fill buttons |
| **Local Standalone Server** | `http://127.0.0.1:8000` | Run via `start-server.bat` |
| **Production CloudPanel** | `https://test.dawncsimmons.com` | Production deployment |
| **Production Updates** | `https://test.dawncsimmons.com/settings/updates` | Signed release updater & backups |

---

## 🔑 Demo Login Accounts

Three preconfigured accounts are seeded in the local MySQL database (`plaud_companion_local`):

### 1. Super-Admin (Workspace Owner)
* **Email**: `admin@dawncsimmons.com`
* **Password**: `AdminPass123!`
* **Role**: `owner` / `is_super_admin: true`
* **Capabilities**:
  * Full access to all workspace transcripts, multi-format import, AI prompts, and bulk exports.
  * Exclusive access to **System Updates & Backups** under **Settings** (`/settings/updates`).
  * Ability to create server snapshots, upload Ed25519-signed update ZIPs, apply atomic updates, and rollback.

### 2. Team Editor
* **Email**: `sarah@dawncsimmons.com`
* **Password**: `EditorPass123!`
* **Role**: `editor` / `is_super_admin: false`
* **Capabilities**:
  * Upload and segment recordings (.TXT, .SRT, .DOCX, .PDF).
  * Run 1-click AI executive summaries, action item extraction, and grounded assistant chat.
  * Generate Microsoft Word (.DOCX) and PDF export packages.
  * *Security*: The Super-Admin updater section is hidden from the UI and returns `HTTP 403 Forbidden`.

### 3. Client Reviewer (Viewer)
* **Email**: `alex@dawncsimmons.com`
* **Password**: `ViewerPass123!`
* **Role**: `viewer` / `is_super_admin: false`
* **Capabilities**:
  * Read-only access to transcripts, speaker turns, action items, and project notes.
  * Workspace isolation prevents modification of data.

---

## 📁 Seeded Demo Workspace: "Executive Strategy & Product"

The demo environment includes realistic meeting data:

### Active Projects (4)
1. **Business Meetings**: Team syncs, website redesign review, and executive decisions.
2. **Product Research**: User discovery, AI provider connectors, and export roadmap.
3. **Client Discussions**: Discovery calls, CloudPanel deployment specs, and RBAC requirements.
4. **Board & Investor Updates**: Quarterly board updates and investor summaries.

### Speaker-Diarized Transcripts (5)
1. **Weekly Business Meeting** (42 min · 3 speakers · DOCX import)
2. **Product Planning Session** (58 min · 4 speakers · SRT import)
3. **Client Discovery Call** (31 min · 2 speakers · PDF import)
4. **Website Review & Feedback** (26 min · 3 speakers · TXT import)
5. **Q4 Executive Roadmap & Budget Sync** (PLAUD Note sync)

### Pre-Generated Bulk Export Packages
* Downloadable `.zip` archive containing Microsoft Word (`.docx`) and `.pdf` files.

---

## 🔌 Hardware & Device Connections

* **Direct Upload (Active)**: In the official PLAUD mobile app, export any recording as TXT, SRT, DOCX, or PDF, then drop it into the web workspace.
* **USB-C Mass Storage (Active)**: Plug your PLAUD Note via USB-C to access recording audio and transcript files directly.
* **Bluetooth Low Energy (BLE Bridge)**: Mobile GATT service (`0000fee7-0000-1000-8000-00805f9b34fb`) in `mobile/lib/services/plaud_ble_service.dart`.
* **PLAUD Cloud Open API**: Configurable under workspace integrations (`/app/api/workspaces/{id}/integrations/plaud`).

---

## 🛠️ Local Database Configuration

* **DB Engine**: MySQL (XAMPP MariaDB) on `127.0.0.1:3306`
* **Database Name**: `plaud_companion_local`
* **Username**: `root`
* **Password**: *(empty)*
* **File Location**: `C:\xampp\htdocs\plaud-companion`
