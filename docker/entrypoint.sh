#!/bin/sh
set -e
cd /var/www/app

# Carpetas de storage (el volumen puede venir vacío)
for d in logos letterheads signatures pdfs logs sessions cache; do
    mkdir -p "storage/$d"
done
chown -R www-data:www-data storage

if [ -z "$APP_KEY" ]; then
    echo "ERROR: APP_KEY no está definida en .env. Genérala con: openssl rand -base64 32" >&2
    exit 1
fi

# Esperar a la base de datos y aplicar migraciones pendientes
php bin/migrate.php --wait

exec "$@"
