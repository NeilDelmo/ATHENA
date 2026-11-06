#!/bin/bash
set -euo pipefail
run=/var/tmp/athena-memory-20261104
out=/mnt/c/laragon/www/athena-app/src/tmp/memory-20261104
pid=$(systemctl show athena-memory-2g.service --property=MainPID --value)
test "$pid" -gt 1
nsenter -t "$pid" -m -n chroot "$run/rootfs" /bin/bash -se <<'INNER'
mysqldump --socket=/run/mysqld/mysqld.sock --single-transaction --no-tablespaces --set-gtid-purged=OFF --column-statistics=0 athena_testing > /app/benchmark/baseline-database.sql
mysql --socket=/run/mysqld/mysqld.sock <<'SQL'
CREATE DATABASE capacity_athena CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL ON capacity_athena.* TO "capacity"@"localhost";
SQL
mysql --socket=/run/mysqld/mysqld.sock capacity_athena < /app/benchmark/baseline-database.sql
sed -i 's/^DB_DATABASE=athena_testing$/DB_DATABASE=capacity_athena/' /app/.env
INNER
cp "$run/rootfs/app/benchmark/baseline-database.sql" "$out/evidence-baseline/baseline-database.sql"
cp "$run/rootfs/app/.env" "$out/synthetic-runtime.env"
systemctl stop athena-memory-2g.service
for attempt in $(seq 1 10); do
    if ! systemctl is-active --quiet athena-memory-2g.service; then break; fi
    sleep 1
done
exec /bin/bash "$out/start-runtime.sh"
