#!/bin/bash
# Deploy script for ShortnURL - dijalankan oleh GitHub Actions via SSH
set -e

cd /root/projects/shortnurl

echo "Pulling latest changes..."
git pull origin main

echo "Installing production dependencies..."
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --no-interaction --prefer-dist

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
