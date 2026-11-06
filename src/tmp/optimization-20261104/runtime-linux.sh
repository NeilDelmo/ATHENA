#!/bin/bash
set -euo pipefail
run=/var/tmp/athena-optimization-20261104
root=$run/rootfs
test -f "$run/setup-complete"
mount -t proc proc "$root/proc"
mount --rbind /dev "$root/dev"
mount --make-rslave "$root/dev"
ip link set lo up
mkdir -p "$root/app/optimization" "$root/run/mysqld" "$root/var/www"
cat > "$root/app/optimization/mysql.cnf" <<'CONF'
[mysqld]
user=mysql
datadir=/var/lib/mysql
socket=/run/mysqld/mysqld.sock
pid-file=/run/mysqld/mysqld.pid
skip-networking
mysqlx=OFF
innodb_buffer_pool_size=128M
innodb_redo_log_capacity=64M
performance_schema=OFF
max_connections=30
log-error=/var/lib/mysql/optimization.log
CONF
cat > "$root/app/.env" <<'ENV'
APP_NAME=AthenaOptimizationTests
APP_ENV=testing
APP_KEY=base64:ujxsuviFR7pfrz0GSqv20FyDsIEJ81G6FZiXGy4IvX8=
APP_DEBUG=false
APP_URL=http://localhost
DB_CONNECTION=mysql
DB_SOCKET=/run/mysqld/mysqld.sock
DB_DATABASE=athena_optimization_testing
DB_USERNAME=root
DB_PASSWORD=
CACHE_STORE=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
BROADCAST_CONNECTION=null
MAIL_MAILER=array
LIBREOFFICE_BINARY=/usr/bin/soffice
LIBREOFFICE_PYTHON_BINARY=/usr/bin/python3
PDF_SECTION_NODE_BINARY=/usr/bin/node
PDFTOTEXT_BINARY=/usr/bin/pdftotext
PDFTOPPM_BINARY=/usr/bin/pdftoppm
PDFINFO_BINARY=/usr/bin/pdfinfo
ENV
chroot "$root" /bin/bash -euc '
    chown -R mysql:mysql /var/lib/mysql /run/mysqld
    chmod 755 /run/mysqld
    mysqld --defaults-file=/app/optimization/mysql.cnf &
    optimization_mysql_pid=$!
    trap "kill $optimization_mysql_pid 2>/dev/null || true; wait $optimization_mysql_pid 2>/dev/null || true" EXIT
    for attempt in $(seq 1 90); do
        if mysqladmin --socket=/run/mysqld/mysqld.sock ping --silent; then break; fi
        sleep 1
    done
    mysql --socket=/run/mysqld/mysqld.sock -e "CREATE DATABASE IF NOT EXISTS athena_optimization_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    cd /app
    php artisan package:discover --no-interaction
    printf "%s\n" "[runtime] private test database ready"
    while kill -0 "$optimization_mysql_pid" 2>/dev/null; do sleep 1; done
'
