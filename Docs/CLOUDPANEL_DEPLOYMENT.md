# Deploying PLAUD Companion on Ubuntu 24.6 CloudPanel VPS

This guide provides step-by-step instructions to host **PLAUD Companion** (FastAPI backend + Next.js Web Admin + PostgreSQL) on an existing **CloudPanel** installation on Ubuntu 24.04/24.6.

---

## 1. CloudPanel Architecture on VPS

```text
[Internet / Users]
        │
        ▼ (HTTPS 443 with Let's Encrypt SSL)
[CloudPanel Nginx Reverse Proxy]
        │
        ├──► /api/* & /docs ──► FastAPI (Python/Uvicorn on Port 8000)
        │                             │
        │                             ▼
        │                     [PostgreSQL 16 Database]
        │
        └──► /* (Admin UI)  ──► Next.js 14 (Node 20 / PM2 on Port 3000)
```

---

## 2. Step 1: Create Database in CloudPanel

1. Log into your **CloudPanel** Dashboard (`https://your-server-ip:8443`).
2. Go to **Databases** → Click **Add Database**.
3. Choose **PostgreSQL**:
   - **Database Name**: `plaud_companion`
   - **Database User**: `plaud`
   - **Password**: *(choose a strong password, e.g. `YourSecretDbPassword123!`)*
4. Click **Create Database**.

---

## 3. Step 2: Create a Site in CloudPanel

1. Go to **Sites** → Click **Add Site**.
2. Select **Node.js** (or Reverse Proxy Site):
   - **Domain Name**: e.g., `plaud.yourdomain.com` (or `app.yourdomain.com`)
   - **Node.js Version**: `20.x`
   - **Site User**: `plaud-companion`
   - **App Port**: `3000`
3. Click **Create Site**.
4. Enable SSL:
   - Go to your site → **SSL/TLS** tab.
   - Click **New Let's Encrypt Certificate** → Install.

---

## 4. Step 3: Deploy the Code via SSH

Connect to your VPS via SSH as `root` (or sudo user):

```bash
# 1. Switch to the site user created by CloudPanel
sudo su - plaud-companion

# 2. Clone the repository into htdocs
cd ~/htdocs
git clone <YOUR_GITHUB_REPOSITORY_URL> .

# 3. Create Backend Environment file
cd ~/htdocs/backend
cp ../.env.example .env
nano .env
```

Set the database connection in `backend/.env`:
```ini
DATABASE_URL="postgresql+asyncpg://plaud:YourSecretDbPassword123!@127.0.0.1:5432/plaud_companion"
JWT_SECRET_KEY="generate-a-strong-random-32-char-secret"
OPENAI_API_KEY=""
GEMINI_API_KEY=""
```

---

## 5. Step 4: Run the Deployment Script

Execute the automated deployment script:

```bash
chmod +x ~/htdocs/deploy/cloudpanel/deploy-cloudpanel.sh
sudo ~/htdocs/deploy/cloudpanel/deploy-cloudpanel.sh
```

This script:
1. Installs Python 3.11 virtual environment & backend dependencies.
2. Applies PostgreSQL migrations using `alembic upgrade head`.
3. Installs and starts the `plaud-backend.service` systemd service on port `8000`.
4. Builds the Next.js production bundle and launches it via PM2 on port `3000`.

---

## 6. Step 5: Configure Nginx vHost in CloudPanel

1. In CloudPanel, go to **Sites** → Click your domain (`plaud.yourdomain.com`).
2. Open the **Vhost** tab.
3. Paste the contents of `deploy/cloudpanel/nginx-cloudpanel.conf` into your configuration.
4. Click **Save**.

Your site is now live with automatic HTTPS:
- **Web Admin Dashboard**: `https://plaud.yourdomain.com`
- **Interactive REST API Docs**: `https://plaud.yourdomain.com/docs`
- **Backend API Endpoints**: `https://plaud.yourdomain.com/api/v1/`

---

## 7. Useful Maintenance Commands

- **Check Backend status & logs**:
  ```bash
  sudo systemctl status plaud-backend
  sudo journalctl -u plaud-backend -f
  ```
- **Restart Backend**:
  ```bash
  sudo systemctl restart plaud-backend
  ```
- **Check Next.js status**:
  ```bash
  pm2 status
  pm2 logs plaud-web
  ```
- **Pull latest changes & update**:
  ```bash
  cd ~/htdocs
  git pull origin main
  sudo ./deploy/cloudpanel/deploy-cloudpanel.sh
  ```
