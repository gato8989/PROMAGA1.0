#!/bin/bash

echo "🚀 Iniciando aplicación Laravel..."

# Esperar a que la base de datos esté disponible (opcional)
echo "⏳ Esperando base de datos..."
sleep 5

# Ejecutar migraciones
echo "🗃️ Ejecutando migraciones..."
 # php artisan migrate --force

# Ejecutar seeders
echo "🌱 Ejecutando seeders..."


# Optimizar Laravel
echo "⚡ Optimizando aplicación..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Iniciar servidor
php artisan route:clear
php artisan cache:clear
php artisan config:clear
php artisan view:clear
echo "🌐 Iniciando servidor..."
php artisan serve --host=0.0.0.0 --port=$PORT