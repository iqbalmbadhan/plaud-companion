#!/usr/bin/env bash
# ==============================================================================
# PLAUD Companion (Laravel 12) — CloudPanel Deployment Script
# Target: Ubuntu 24.04 / 24.6 | PHP 8.3 / 8.4 | Nginx | MySQL / MariaDB
# ==============================================================================
set -euo pipefail

SITE_USER="${SITE_USER:-plaud-companion}"
DOMAIN_NAME="${DOMAIN_NAME:-plaud.example.com}"
SITE_DIR="${SITE_DIR:-/home/${SITE_USER}/htdocs/${DOMAIN_NAME}}"
LARAVEL_DIR="${SITE_DIR}/backend-laravel"

# Auto-detect PHP 8.5, 8.4 or 8.3 binary
if [ -z "${PHP_BIN:-}" ]; then
    if command -v php8.5 >/dev/null 2>&1; then
        PHP_BIN="php8.5"
    elif command -v php8.4 >/dev/null 2>&1; then
        PHP_BIN="php8.4"
    elif command -v php8.3 >/dev/null 2>&1; then
        PHP_BIN="php8.3"
    else
        PHP_BIN="php"
    fi
fi

echo "=== [1/5] Verifying PHP (${PHP_BIN}) and Required Extensions ==="
"${PHP_BIN}" -v
REQUIRED_EXTS=(mbstring openssl sodium zip pdo_mysql xml curl)
MISSING_EXTS=()
for ext in "${REQUIRED_EXTS[@]}"; do
    if ! "${PHP_BIN}" -m | grep -qi "^${ext}$"; then
        MISSING_EXTS+=("${ext}")
    fi
done

if [ "${#MISSING_EXTS[@]}" -gt 0 ]; then
    echo "Missing PHP extensions: ${MISSING_EXTS[*]}"
    echo "Install on Ubuntu 24 with: sudo apt-get install -y ${MISSING_EXTS[*]/#/php8.3-}"
    exit 1
fi

echo "=== [2/5] Preparing Laravel Writable Directories ==="
cd "${LARAVEL_DIR}"
mkdir -p \
    storage/app/private/updates \
    storage/app/updates \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chmod -R 775 storage bootstrap/cache
if id "${SITE_USER}" >/dev/null 2>&1; then
    chown -R "${SITE_USER}:www-data" storage bootstrap/cache || true
fi

echo "=== [3/5] Installing Composer Dependencies (--no-dev) ==="
if [ -f "composer.json" ]; then
    if [ ! -d "vendor" ]; then
        "${PHP_BIN}" "$(command -v composer)" install --no-dev --optimize-autoloader --no-interaction --prefer-dist
    else
        echo "vendor/ already present (skipping composer install; pass CLEAN_VENDOR=1 to reinstall)."
        if [ "${CLEAN_VENDOR:-0}" = "1" ]; then
            "${PHP_BIN}" "$(command -v composer)" install --no-dev --optimize-autoloader --no-interaction --prefer-dist
        fi
    fi
fi

echo "=== [4/5] Checking Installer State & Optimizing Caches ==="
if [ -f "storage/app/installed.lock" ] && [ -f ".env" ]; then
    echo "Application already installed. Running additive migrations & refreshing caches..."
    "${PHP_BIN}" artisan migrate --force
    "${PHP_BIN}" artisan config:cache
    "${PHP_BIN}" artisan route:cache
    "${PHP_BIN}" artisan view:cache
else
    echo "Fresh installation detected (storage/app/installed.lock not found)."
    echo "Open https://${DOMAIN_NAME}/install in your browser to complete the 1-Click Installer."
fi

echo "=== [5/5] Reloading PHP-FPM & Nginx (if root/sudo) ==="
if [ "$(id -u)" -eq 0 ]; then
    for svc in php8.5-fpm php8.4-fpm php8.3-fpm nginx; do
        if systemctl is-active --quiet "${svc}"; then
            systemctl reload "${svc}"
            echo "Reloaded ${svc}."
        fi
    done
fi

echo "=========================================================================="
echo "CloudPanel Laravel Setup Complete!"
echo "Web Root: ${LARAVEL_DIR}/public"
echo "vHost Template: deploy/cloudpanel/nginx-cloudpanel-laravel.conf"
echo "=========================================================================="
