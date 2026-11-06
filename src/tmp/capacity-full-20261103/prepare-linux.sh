#!/bin/bash
set -euo pipefail
run=/var/tmp/athena-capacity-20261103
root=$run/rootfs
input=/mnt/c/laragon/www/athena-app/src/tmp/capacity-full-20261103
test -f "$run/setup-complete"
mount -t proc proc "$root/proc"
mount --rbind /dev "$root/dev"
mount --make-rslave "$root/dev"
mkdir -p "$root/app/benchmark" "$root/run/mysqld" "$root/var/www" "$root/app/storage/app/private/capacity-output"
cp "$input"/*.php "$input"/*.py "$input"/*.conf "$input"/mysql.cnf "$root/app/benchmark/"
cp "$input/supervisord.conf" "$root/app/benchmark/"
cat > "$root/app/.env" <<'ENV'
APP_NAME=AthenaCapacity
APP_ENV=production
APP_KEY=base64:ujxsuviFR7pfrz0GSqv20FyDsIEJ81G6FZiXGy4IvX8=
APP_DEBUG=false
APP_URL=http://localhost:8000
LOG_CHANNEL=single
LOG_LEVEL=warning
DB_CONNECTION=mysql
DB_SOCKET=/run/mysqld/mysqld.sock
DB_DATABASE=capacity_athena
DB_USERNAME=capacity
DB_PASSWORD=capacity-synthetic-only
CACHE_STORE=database
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=false
QUEUE_CONNECTION=database
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=capacity
REVERB_APP_KEY=capacitykey
REVERB_APP_SECRET=capacity-synthetic-secret
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
MAIL_MAILER=log
LIBREOFFICE_BINARY=/usr/bin/soffice
LIBREOFFICE_PYTHON_BINARY=/usr/bin/python3
PDF_SECTION_NODE_BINARY=/usr/bin/node
PDFTOTEXT_BINARY=/usr/bin/pdftotext
PDFTOPPM_BINARY=/usr/bin/pdftoppm
PDFINFO_BINARY=/usr/bin/pdfinfo
LITERATURE_WEB_HARVEST_ENABLED=false
CAPACITY_ENABLED=1
CAPACITY_TOKEN=capacity-local-synthetic-20261103-token-7fba39881
CAPACITY_PROJECT_COUNT=100
ENV
if ! grep -q 'benchmark/task.php' "$root/app/routes/web.php"; then
    printf '\nrequire base_path("benchmark/task.php");\nrequire base_path("benchmark/routes-capacity.php");\n' >> "$root/app/routes/web.php"
fi
cat > "$root/etc/php/8.5/fpm/conf.d/99-capacity.ini" <<'INI'
opcache.enable=1
opcache.memory_consumption=128
opcache.validate_timestamps=0
INI
chroot "$root" /bin/bash -euc '
    chown -R mysql:mysql /var/lib/mysql /run/mysqld
    chmod 755 /run/mysqld
    chown -R www-data:www-data /app/storage /app/bootstrap/cache /var/www
    touch /app/storage/logs/capacity-mysql.log
    chown mysql:mysql /app/storage/logs/capacity-mysql.log
    /usr/sbin/mysqld --defaults-file=/app/benchmark/mysql.cnf &
    mysql_pid=$!
    trap "kill $mysql_pid 2>/dev/null || true; wait $mysql_pid 2>/dev/null || true" EXIT
    for attempt in $(seq 1 90); do
        if mysqladmin --socket=/run/mysqld/mysqld.sock ping --silent; then break; fi
        sleep 1
    done
    mysql --socket=/run/mysqld/mysqld.sock <<SQL
CREATE DATABASE IF NOT EXISTS capacity_athena CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS capacity_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS "capacity"@"localhost" IDENTIFIED BY "capacity-synthetic-only";
GRANT ALL ON capacity_athena.* TO "capacity"@"localhost";
GRANT ALL ON capacity_testing.* TO "capacity"@"localhost";
SQL
    cd /app
    php artisan package:discover --no-interaction
    php artisan migrate --force --no-interaction
    python3 benchmark/make-fixtures.py storage/app/private/capacity-fixtures
    php -d memory_limit=512M benchmark/seed-run.php
    php artisan view:cache --no-interaction
    chown -R www-data:www-data /app/storage /app/bootstrap/cache
    chown mysql:mysql /app/storage/logs/capacity-mysql.log
    php -v
    libreoffice --version
    mysql --version
    php artisan --version
'
cp "$root/app/storage/app/private/capacity-context.json" "$input/context.json"
printf '%s\n' '[prepare] completed'
touch "$run/prepare-complete"
