#!/usr/bin/env bash
# ==============================================================================
# PLAUD Companion — Automated Deployment Script for Ubuntu 24 CloudPanel VPS
# ==============================================================================
set -euo pipefail

SITE_USER="plaud-companion"
SITE_DIR="/home/${SITE_USER}/htdocs"

echo "=== [1/6] Installing Ubuntu 24 System Prerequisites ==="
sudo apt-get update -y
sudo apt-get install -y python3.11 python3.11-venv python3-pip nodejs npm git

echo "=== [2/6] Setting Up Python Virtual Environment & Backend ==="
cd "${SITE_DIR}/backend"
if [ ! -d ".venv" ]; then
    python3.11 -m venv .venv
fi
source .venv/bin/activate
pip install --upgrade pip
pip install -r requirements.txt

echo "=== [3/6] Applying Database Migrations ==="
if [ -f ".env" ]; then
    alembic upgrade head
    echo "Alembic migrations applied successfully."
else
    echo "Notice: No .env found. Please create ${SITE_DIR}/backend/.env with your DATABASE_URL."
fi

echo "=== [4/6] Installing Systemd Service for FastAPI Backend ==="
sudo cp "${SITE_DIR}/deploy/cloudpanel/plaud-backend.service" /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable plaud-backend
sudo systemctl restart plaud-backend
sudo systemctl status plaud-backend --no-pager

echo "=== [5/6] Building Next.js Web Admin ==="
cd "${SITE_DIR}/web"
npm install
npm run build

echo "=== [6/6] Starting / Reloading Next.js via PM2 ==="
if command -v pm2 >/dev/null 2>&1; then
    pm2 restart plaud-web || pm2 start npm --name "plaud-web" -- start
    pm2 save
else
    echo "PM2 not found. Install with: npm install -g pm2"
fi

echo "=========================================================================="
echo "Deployment Complete! Configure CloudPanel Nginx vHost with:"
echo "deploy/cloudpanel/nginx-cloudpanel.conf"
echo "=========================================================================="
