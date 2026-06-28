#!/usr/bin/env bash
echo "Installation des dépendances PHP..."
composer install --no-dev --working-dir=/var/www/html

echo "Optimisation des caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Exécution des migrations de base de données..."
php artisan migrate --force
