#!/bin/bash
# Deploy script for ShortnURL - dijalankan oleh GitHub Actions via SSH
set -Eeuo pipefail

PROJECT_DIR="${1:-/root/projects/shortnurl}"
WEB_ROOT="/var/www/shortnurl"

if [[ ! -d "$PROJECT_DIR/.git" || ! -d "$WEB_ROOT" ]]; then
  echo "Deploy aborted: project or web root directory is missing." >&2
  exit 1
fi

cd "$PROJECT_DIR"

echo "Pulling latest changes..."
git pull origin main

echo "Installing production dependencies..."
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --no-interaction --prefer-dist

echo "Validating PHP syntax..."
find src config public -name '*.php' -print0 | xargs -0 -n1 php -l
php -l index.php

echo "Syncing to web root..."
rsync -a --delete \
  --exclude='.env' \
  --exclude='vendor/' \
  --exclude='.git/' \
  --exclude='tests/' \
  --exclude='.github/' \
  --exclude='.gitignore' \
  --exclude='AGENTS.md' \
  "$PROJECT_DIR/" "$WEB_ROOT/"
chown -R www-data:www-data "$WEB_ROOT/src" "$WEB_ROOT/config" "$WEB_ROOT/public"

echo "Reloading PHP-FPM..."
systemctl reload php8.3-fpm

echo "Deploy completed."
