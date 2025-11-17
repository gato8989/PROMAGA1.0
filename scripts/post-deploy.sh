#!/bin/bash

echo "🔧 Ejecutando post-deploy scripts..."

# Ejecutar migraciones
php artisan migrate --force

# Ejecutar seeders
php artisan db:seed --force

# Optimizar la aplicación
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "✅ Post-deploy completado!"