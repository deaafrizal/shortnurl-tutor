#!/bin/bash
# Deploy script for ShortnURL - dijalankan oleh GitHub Actions via SSH
set -e

PROJECT_DIR="/root/projects/shortnurl"
WEB_ROOT="/var/www/shortnurl"

cd "$PROJECT_DIR"

echo "Pulling latest changes..."
git pull origin main

echo "Installing production dependencies..."
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --no-interaction --prefer-dist

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

echo "Validating PHP syntax..."
php -l public/index.php
php -l index.php
php -l config/database.php
php -l src/Shorten.php
php -l src/Redirect.php
php -l src/NotFoundException.php

echo "Reloading PHP-FPM..."
systemctl reload php8.3-fpm

echo "Deploy completed."
