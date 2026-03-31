#!/bin/sh
set -e

echo "[entrypoint] Running bootstrap..."
php /var/www/html/init/bootstrap.php

# ADMIN_PASS is only needed during bootstrap to create the initial admin user.
# Unset it before exec so it is absent from PHP-FPM's process environment and
# cannot be read from /proc/*/environ if the container is ever compromised.
# DB_PASS and APP_SECRET must remain — they are read on every request.
unset ADMIN_PASS

echo "[entrypoint] Starting PHP-FPM..."
exec "$@"
