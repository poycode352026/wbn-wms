#!/bin/bash
# deploy.sh — run this on server after git pull

set -e

echo "==> Pulling latest code..."
git pull

echo "==> Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader --quiet

echo "==> Installing JS dependencies..."
npm install --silent

echo "==> Building assets..."
npm run build

echo "==> Running migrations..."
php artisan migrate --force

echo "==> Clearing & caching config..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo ""
echo "✓ Deploy done."
