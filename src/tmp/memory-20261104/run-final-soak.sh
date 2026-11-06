#!/bin/bash
set -euo pipefail
out=/mnt/c/laragon/www/athena-app/src/tmp/memory-20261104
cases=$(cat "$out/soak-cases.txt")
exec /bin/bash "$out/run-driver.sh" athena-memory-2g.service final-4fpm-soak-20m --mode soak --seconds 1200 --cases "$cases"
