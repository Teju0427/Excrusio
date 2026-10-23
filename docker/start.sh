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

# Database setup runs in the background so the web server opens its port straight away
# (Render gives up if no port is open within a few minutes; seeding a remote DB can take longer).
(
  set +e
  echo "[setup] Running migrations…"
  php artisan migrate --force || { echo "[setup] MIGRATION FAILED — check the DB_* settings"; exit 1; }

  # Demo data: seeded only when the database is empty, so restarts never duplicate it.
  if [ "$SEED_DEMO_DATA" = "true" ]; then
    php artisan excursio:seed-if-empty || echo "[setup] SEEDING FAILED"
  fi

  # Public demo only: start every boot from clean demo data (needs EXCURSIO_DEMO_RESET=true too).
  if [ "$DEMO_RESET_ON_BOOT" = "true" ]; then
    php artisan excursio:demo-reset --force || echo "[setup] Demo reset skipped"
  fi
  echo "[setup] Done."
) &

exec apache2-foreground
