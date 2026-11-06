#!/bin/bash
set -euo pipefail
for attempt in $(seq 1 60); do
    if MYSQL_PWD=capacity-synthetic-only mysqladmin --socket=/run/mysqld/mysqld.sock --user=capacity ping --silent >/dev/null 2>&1; then
        exec "$@"
    fi
    sleep 1
done
exit 1
