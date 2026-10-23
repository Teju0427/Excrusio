#!/bin/sh
set -e
cd /var/www/html

# Render/Azure give the port to listen on in $PORT.
sed -i "s/^Listen .*/Listen ${PORT:-10000}/" /etc/apache2/ports.conf

# Optional: CA certificate for hosted MySQL (Aiven) supplied as an env var.
if [ -n "$DB_SSL_CA_CONTENT" ]; then
  printf '%s\n' "$DB_SSL_CA_CONTENT" > /etc/ssl/certs/db-ca.pem
  export MYSQL_ATTR_SSL_CA=/etc/ssl/certs/db-ca.pem
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force

# Seed demo data on first deploy only (set SEED_DEMO_DATA=true in the dashboard).
if [ "$SEED_DEMO_DATA" = "true" ]; then
  php artisan db:seed --force
fi

# Public demo only: start every boot from clean demo data (needs EXCURSIO_DEMO_RESET=true too).
if [ "$DEMO_RESET_ON_BOOT" = "true" ]; then
  php artisan excursio:demo-reset --force || echo "Demo reset skipped"
fi

exec apache2-foreground
