#!/bin/sh
set -e

echo "[entrypoint] Running bootstrap..."
php /var/www/html/init/bootstrap.php

echo "[entrypoint] Starting PHP-FPM..."
exec "$@"
