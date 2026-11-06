#!/bin/bash
set -euo pipefail
run=/var/tmp/athena-memory-20261104
out=/mnt/c/laragon/www/athena-app/src/tmp/memory-20261104
mkdir -p "$out/evidence-optimized"
cp -a "$run/results/optimized-2g-full-matrix" "$out/evidence-optimized/"
cp "$run/rootfs/app/storage/logs/laravel.log" "$out/evidence-optimized/matrix-laravel.log"
pid=$(systemctl show athena-memory-2g.service --property=MainPID --value)
nsenter -t "$pid" -m -n chroot "$run/rootfs" /usr/bin/supervisorctl -c /app/benchmark/supervisord.conf status > "$out/evidence-optimized/matrix-services-after.txt"
