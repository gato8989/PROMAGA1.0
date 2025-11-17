#!/bin/bash

# Crear en tu proyecto: deployment/start.sh

echo "🚀 Iniciando aplicación Laravel..."

# Configurar permisos de storage
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/

# Optimizar Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Iniciar servidor
php artisan serve --host=0.0.0.0 --port=$PORT