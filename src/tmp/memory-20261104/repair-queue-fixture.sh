#!/bin/bash
set -euo pipefail
root=/var/tmp/athena-memory-20261104/rootfs
out=/mnt/c/laragon/www/athena-app/src/tmp/memory-20261104
mkdir -p "$out/evidence-final"
cp -a /var/tmp/athena-memory-20261104/results/final-2fpm-probe-1000 "$out/evidence-final/final-2fpm-probe-invalid-shared-fixture"
cp "$out/final-2fpm-probe.log" "$out/evidence-final/final-2fpm-probe-invalid-shared-fixture.log"
cp "$out/repair-queue-fixture.php" "$out/workload-extraction.php" "$root/app/benchmark/"
/bin/bash "$out/exec-capacity.sh" php benchmark/repair-queue-fixture.php > "$out/queue-fixture-repair.json"
