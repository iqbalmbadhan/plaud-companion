<p align="center">
  <img src="Docs/assets/hero-banner.svg" alt="PLAUD Companion Hero Banner" width="100%" />
</p>

<p align="center">
  <a href="https://github.com/iqbalmbadhan/plaud-companion/actions/workflows/ci-pipeline.yml">
    <img src="https://github.com/iqbalmbadhan/plaud-companion/actions/workflows/ci-pipeline.yml/badge.svg" alt="CI/CD Pipeline" />
  </a>
  <img src="https://img.shields.io/badge/Mobile-Flutter%20Android%20%26%20iOS-18A879?style=flat-square&logo=flutter&logoColor=white" alt="Flutter" />
  <img src="https://img.shields.io/badge/PWA-Installable%20%26%20Offline%20Ready-101B33?style=flat-square&logo=pwa&logoColor=white" alt="PWA" />
  <img src="https://img.shields.io/badge/Security-AES--GCM--256%20Vault-18A879?style=flat-square&logo=security&logoColor=white" alt="AES-GCM-256 Vault" />
  <img src="https://img.shields.io/badge/AI%20Studio-Gemini%20%7C%20OpenAI%20%7C%20Claude%20%7C%20Built--in-101B33?style=flat-square&logo=openai&logoColor=white" alt="AI Studio" />
  <img src="https://img.shields.io/badge/Web%20Admin-Laravel%2011%20%2B%20Next.js%2014-18A879?style=flat-square&logo=laravel&logoColor=white" alt="Laravel & Next.js" />
</p>

# PLAUD Companion

> **The Privacy-First AI Productivity & Hardware Bridge for PLAUD Note & NotePin**  
> *Transforming hours of raw voice recordings into instant, executive-grade meeting intelligence — without your data ever touching a central cloud.*

---

## 📖 The Story Behind PLAUD Companion

It all started on an ordinary afternoon with an unexpected phone call.

**Dawn C Simmons** dialed **Md. Iqbal Mahmud**. Dawn had been using her **PLAUD NotePin** to capture conversations, board discussions, and strategic partner calls. The hardware was brilliant — sleek, light, and always ready to record. 

*But the aftermath was exhausting.*

> *"Iqbal, recording the meetings is the easy part,"* Dawn explained. *"The real problem is what happens next. Exporting the transcripts, formatting them, chasing speaker diarization, turning raw transcripts into structured action items, executive summaries, follow-up emails, and Word documents... It’s eating hours out of my week. Every single day feels like wrestling with half-baked exports and clunky portals."*

On top of the time drain, Dawn raised an even bigger concern: **privacy**. In high-stakes business discussions, uploading raw meeting audio and confidential transcripts to third-party cloud servers wasn't just inconvenient — it was an unacceptable compliance risk.

Iqbal listened carefully to her frustration. Right there on the phone, he made a promise:

> *"Give me a little time, Dawn. I’m going to make this whole process simple, fast, and completely hassle-free for you. You won’t have to wrestle with exports again, and your meeting data will stay locked on your own devices."*

What started as a promise between colleagues quickly evolved into something much larger: **PLAUD Companion**. 

Today, it is a production-grade, privacy-first, cross-platform productivity ecosystem. It connects directly with PLAUD hardware via Bluetooth Low Energy, encrypts every transcript locally using on-device cryptography (AES-GCM-256), plugs into the world's most powerful AI models, and generates executive DOCX, PDF, and bulk ZIP bundles in seconds.

---

## 🌟 What PLAUD Companion Does

```
┌──────────────────────────────────────────────────────────────────────────────────┐
│                             PLAUD COMPANION ECOSYSTEM                            │
├────────────────────────┬─────────────────────────┬───────────────────────────────┤
│    HARDWARE & BLE      │   ON-DEVICE SECURITY    │       AI STUDIO & EXPORTS     │
├────────────────────────┼─────────────────────────┼───────────────────────────────┤
│ • PLAUD Note & NotePin │ • AES-GCM-256 Vault     │ • Google Gemini 2.0 Flash/Pro │
│ • Web Bluetooth GATT   │ • Zero Central Storage  │ • OpenAI GPT-4o & GPT-4o-mini │
│ • Battery & Status     │ • Client-Side IndexedDB │ • Anthropic Claude 3.5 Sonnet │
│ • Auto-Diarization Sync│ • .plaudvault Backups   │ • Built-in Offline Engine     │
│ • TXT/DOCX/PDF/SRT     │ • Isolated Workspaces   │ • Real DOCX, PDF & Bulk ZIP   │
└────────────────────────┴─────────────────────────┴───────────────────────────────┘
```

### 1. 🔒 On-Device Cryptography Vault (Zero Cloud Storage)
* **100% Client-Side Privacy**: Customer transcripts, audio recordings, diarized speaker turns, and notes are encrypted on your physical device using the **Web Cryptography API** (`window.crypto.subtle`) with **AES-GCM-256**.
* **Zero Central Cloud Retention**: No transcripts are stored in a central database. The server acts strictly as an authentication and compute relay.
* **Encrypted Portable Vault (`.plaudvault`)**: Export encrypted backups with 1-click and restore them seamlessly across devices without exposing plaintext data to any network or server.

### 2. ⚡ Direct Hardware Bridge (BLE & Web Bluetooth)
* **Bluetooth Low Energy (BLE)**: Connect to **PLAUD Note** and **PLAUD NotePin** directly from your mobile device or desktop browser via native Web Bluetooth GATT.
* **Real-Time Telemetry**: Monitor device battery level, firmware version, pairing state, and storage capacity in real time.
* **Universal Multi-Format Import**: Drag and drop existing recordings in `.txt`, `.docx`, `.pdf`, or `.srt` formats with automatic speaker diarization parsing.

### 3. 🧠 Universal Multi-Model AI Studio
* **Multi-Provider Parity**: Plug into your favorite intelligence engine:
  * **Google Gemini**: Gemini 2.0 Flash, Gemini 1.5 Pro
  * **OpenAI**: GPT-4o, GPT-4o-mini
  * **Anthropic Claude**: Claude 3.5 Sonnet, Claude 3.5 Haiku
  * **OpenRouter**: Unified access to 100+ open and proprietary models
  * **PLAUD Neural Engine**: Built-in offline synthesis with **0ms latency** for air-gapped environments.
* **Live Latency Benchmarking**: One-click ping diagnostic tests connection speed in real-time milliseconds (e.g., `🟢 42ms`).
* **Executive Action Presets**: Instantly generate structured deliverables:
  * 📋 *Executive Meeting Minutes & Executive Summary*
  * ✅ *Action Items Matrix with Owner & Deadline Tags*
  * ✉️ *Client-Ready Follow-up Email Drafts*
  * ⚠️ *Key Decisions, Controversies & Risk Register*

### 4. 📲 Installable Progressive Web App (PWA) & Mobile (Flutter)
* **Install Anywhere**: Install PLAUD Companion directly to your desktop (macOS, Windows, Linux) or mobile homescreen (iOS & Android) with native windowing, offline caching, and service worker background sync.
* **Offline Fallback Screen**: Work through local recordings even in airplane mode or during network drops.
* **Native Mobile Apps**: Dedicated cross-platform Flutter application for Android and iOS compiled via GitHub Actions CI/CD.

### 5. 👥 User Registration & Super-Admin Console
* **Instant Self-Registration**: New team members can register at `/register` and start using their auto-provisioned personal workspace immediately.
* **Super-Admin Management Console (`/settings/users`)**: Dedicated management interface for administrators to view all registered users, monitor workspace count, and toggle account suspension with one click (with built-in self-suspension protection).

### 6. 📦 1-Click Executive Exports
* **Real Microsoft Word (`.docx`)**: Clean typography, table of contents, action item callout boxes, and speaker timestamps.
* **Print-Ready PDF (`.pdf`)**: Formatted for executive distribution.
* **Bulk ZIP Archives (`.zip`)**: Download all session transcripts and summaries in an organized, validated zip package.

---

## 🚀 How to Use PLAUD Companion

### Scenario A: Working on Desktop / Web / PWA

1. **Sign Up or Sign In**:
   * Navigate to `http://localhost:8000/register` (or your production URL).
   * Enter your name, email, and password. Your personal workspace is provisioned automatically with your on-device encryption vault initialized.
2. **Connect Hardware or Import Files**:
   * **Pairing**: Click **"Scan BLE Devices"** in the Hardware Bridge card to pair your PLAUD Note or NotePin over Bluetooth.
   * **Importing**: Drag and drop any `.txt`, `.docx`, `.pdf`, or `.srt` file into the upload dropzone.
3. **Run AI Synthesis**:
   * Open the **AI Studio** tab.
   * Select your preferred model (e.g., *Google Gemini 2.0 Flash* or *OpenAI GPT-4o*).
   * Click **"Generate Action Items"** or **"Draft Follow-up Email"**. Watch the structured markdown stream live.
4. **Export Deliverables**:
   * Click **"Export DOCX"** to download a clean Microsoft Word file.
   * Or click **"Download All (ZIP)"** to export your entire workspace archive.
5. **Back Up Your Encrypted Vault**:
   * Click **"Export Backup (.plaudvault)"** to download an AES-GCM-256 encrypted archive. You can restore it anytime with zero server dependencies.

---

### Scenario B: Working on Mobile (Android & iOS)

1. **Download the Mobile App**:
   * Download the latest release from [`mobile_artifacts/app-release.apk`](mobile_artifacts/app-release.apk) or grab it directly from GitHub Actions build artifacts.
2. **Launch & Onboard**:
   * Open the app to experience the dark-navy and emerald mobile interface.
   * Toggle to **"Create Account"** or sign in directly to your workspace.
3. **Hardware Sync on the Go**:
   * Navigate to the **Hardware** tab to connect your PLAUD device via BLE.
   * Review recent recordings, view diarized speaker bubbles, and generate AI insights right from your pocket.

---

## 🛠️ System Architecture & Technology Stack

```text
plaud-companion/
├── backend-laravel/            # Production Laravel 11 Backend & Web Console
│   ├── app/Http/Controllers/   # WebAuth, UserManagement, Updates, API Controllers
│   ├── app/Services/           # AiService (Gemini, Claude, OpenAI, OpenRouter, Built-in)
│   ├── resources/views/        # Responsive Blade views (Workspace, Auth, Users, Updates)
│   └── public/                 # PWA Manifest, Service Worker, Crypto Vault JS
├── mobile/                     # Cross-Platform Flutter App (Android & iOS)
│   ├── lib/core/               # Cryptography, AppState, Theme Tokens (#101B33, #18A879)
│   ├── lib/features/           # Dashboard, Transcripts, AI Studio, Exports, Hardware
│   └── test/                   # Comprehensive Flutter widget and unit test suite
├── backend/                    # Python FastAPI API & pgvector Microservice
│   ├── app/                    # Importers (TXT/DOCX/PDF/SRT), DOCX/PDF/ZIP Exporters
│   └── tests/                  # Pytest test suite for import/export engines
├── web/                        # Next.js 14 + TypeScript Admin Console
├── Docs/                       # Comprehensive Architecture, API, and Security Specs
└── mobile_artifacts/           # Ready-to-install Android APK release bundles
```

---

## 💻 Developer Quick Start

### 1. Prerequisites
* **PHP 8.2+** with `pdo_mysql`, `openssl`, `mbstring`
* **Node.js 18+** & **npm**
* **Python 3.11+**
* **Flutter SDK 3.22+** (for mobile development)

### 2. Laravel Web & PWA Server
```bash
cd backend-laravel
copy .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan serve
# Web app running at http://localhost:8000
```

### 3. FastAPI Service
```bash
cd backend
python -m venv .venv
source .venv/bin/activate  # Or .venv\Scripts\activate on Windows
pip install -r requirements.txt
pytest
uvicorn app.main:app --reload --port 8001
```

### 4. Flutter Mobile App
```bash
cd mobile
flutter pub get
flutter test
flutter run
```

---

## 🛡️ Security & Privacy Guarantees

* **Untrusted Input Isolation**: All uploaded transcripts, speaker names, and external text are treated strictly as untrusted data and isolated from system prompts to prevent prompt injection.
* **Zero Client-Side Secrets**: Mobile APKs and web bundles contain zero AI provider API keys or signing secrets.
* **Super-Admin Protection**: Self-suspension protection prevents administrators from accidentally locking themselves out of the management console.
* **Cryptographic Vault Boundaries**: The server never stores user encryption keys; data decryption occurs strictly in volatile client memory.

---

## 👥 Credits & Team

* **Developer & Architect**: **[Md. Iqbal Mahmud](https://www.iqbalmahmud.com)**  
  *Website:* [www.iqbalmahmud.com](https://www.iqbalmahmud.com)
* **Technical Advisor & Inspiration**: **[Dawn C Simmons](https://www.dawncsimmons.com)**  
  *Website:* [www.dawncsimmons.com](https://www.dawncsimmons.com)
* **Brought to you by**: **[AI Infos](https://www.aiinfos.com)**  
  *Website:* [www.aiinfos.com](https://www.aiinfos.com)

---

## 📄 License

This project is proprietary software developed by **Md. Iqbal Mahmud** for **AI Infos**. All rights reserved.
