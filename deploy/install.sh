#!/usr/bin/env bash
# Application Install Script
# Usage: bash install.sh
# Tested on Ubuntu 22.04 LTS

set -euo pipefail

DOMAIN="${DOMAIN:-app.example.com}"
APP_DIR="${APP_DIR:-/var/www/app}"
DB_NAME="${DB_NAME:-app}"
DB_USER="${DB_USER:-app}"
DB_PASS="${DB_PASS:-$(openssl rand -hex 16)}"
PHP_VER="8.2"

log() { echo -e "\n\033[1;32m[INSTALL]\033[0m $*"; }
err() { echo -e "\033[1;31m[ERROR]\033[0m $*" >&2; exit 1; }

# ─── Prerequisites ────────────────────────────────────────────────────────────
log "Updating packages and installing prerequisites..."
apt-get update -q
apt-get install -y -q \
    curl wget git unzip supervisor nginx \
    php${PHP_VER}-fpm php${PHP_VER}-mysql php${PHP_VER}-mbstring \
    php${PHP_VER}-xml php${PHP_VER}-curl php${PHP_VER}-zip \
    php${PHP_VER}-bcmath php${PHP_VER}-intl php${PHP_VER}-redis \
    mysql-server

# ─── Node.js 20 ───────────────────────────────────────────────────────────────
if ! command -v node &>/dev/null; then
    log "Installing Node.js 20..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt-get install -y nodejs
fi

# ─── Composer ─────────────────────────────────────────────────────────────────
if ! command -v composer &>/dev/null; then
    log "Installing Composer..."
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

# ─── MySQL database ───────────────────────────────────────────────────────────
log "Creating MySQL database and user..."
mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

# ─── App deployment ───────────────────────────────────────────────────────────
log "Deploying application to ${APP_DIR}..."
mkdir -p "${APP_DIR}"
# If repo is already cloned, pull; otherwise clone from current directory
if [ -d "${APP_DIR}/.git" ]; then
    git -C "${APP_DIR}" pull --ff-only
else
    cp -r . "${APP_DIR}/"
fi

cd "${APP_DIR}"

# .env setup
if [ ! -f .env ]; then
    cp .env.example .env
fi

sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" .env
sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" .env
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|" .env
sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=mysql|" .env
sed -i "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" .env
sed -i "s|^APP_ENV=.*|APP_ENV=production|" .env
sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|" .env

# ─── PHP dependencies ─────────────────────────────────────────────────────────
log "Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# ─── Node / build assets ──────────────────────────────────────────────────────
log "Building frontend assets..."
npm ci
npm run build

# ─── Laravel setup ────────────────────────────────────────────────────────────
log "Running Laravel setup..."
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --class=AdminSeeder --force
php artisan db:seed --class=SiteSettingsSeeder --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link

# ─── Permissions ──────────────────────────────────────────────────────────────
log "Setting permissions..."
chown -R www-data:www-data "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
chmod -R 775 "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"

# ─── Supervisor ───────────────────────────────────────────────────────────────
log "Installing Supervisor config..."
sed "s|/var/www/app|${APP_DIR}|g" "${APP_DIR}/deploy/supervisor.conf" \
    > /etc/supervisor/conf.d/laravel-queue.conf
supervisorctl reread
supervisorctl update
supervisorctl start laravel-queue:*

# ─── Nginx ────────────────────────────────────────────────────────────────────
log "Installing Nginx config..."
sed "s|YOUR_DOMAIN|${DOMAIN}|g; s|/var/www/app|${APP_DIR}|g" \
    "${APP_DIR}/deploy/nginx.conf" \
    > /etc/nginx/sites-available/app
ln -sf /etc/nginx/sites-available/app /etc/nginx/sites-enabled/app
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

# ─── SSL (Let's Encrypt) ──────────────────────────────────────────────────────
log "Installing SSL certificate..."
if ! command -v certbot &>/dev/null; then
    apt-get install -y certbot python3-certbot-nginx
fi
certbot --nginx -d "${DOMAIN}" --non-interactive --agree-tos -m "admin@${DOMAIN}" || \
    log "WARNING: certbot failed — configure SSL manually"

# ─── Backup cron ──────────────────────────────────────────────────────────────
log "Adding nightly DB backup cron..."
BACKUP_SCRIPT="/usr/local/bin/app-backup.sh"
cat > "${BACKUP_SCRIPT}" <<'BACKUP'
#!/bin/bash
DIR="/var/backups/app"
mkdir -p "$DIR"
mysqldump -u ${DB_USER} -p${DB_PASS} ${DB_NAME} | gzip > "$DIR/db-$(date +%Y%m%d).sql.gz"
find "$DIR" -name "*.sql.gz" -mtime +14 -delete
BACKUP
chmod +x "${BACKUP_SCRIPT}"
(crontab -l 2>/dev/null; echo "0 2 * * * ${BACKUP_SCRIPT}") | crontab -

log "Installation complete!"
echo ""
echo "  URL:            https://${DOMAIN}"
echo "  Admin login:    https://${DOMAIN}/admin/login"
echo "  DB name:        ${DB_NAME}"
echo "  DB user:        ${DB_USER}"
echo "  DB pass:        ${DB_PASS}"
echo ""
echo "  Next steps:"
echo "  1. Update admin password at https://${DOMAIN}/admin/profile/change-password"
