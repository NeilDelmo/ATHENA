#!/bin/bash
set -euo pipefail
run=/var/tmp/athena-memory-20261104
out=/mnt/c/laragon/www/athena-app/src/tmp/memory-20261104
name=$1
mkdir -p "$out/evidence-final"
cp -a "$run/results/$name" "$out/evidence-final/"
cp "$out/validate-photo-output.py" "$run/rootfs/app/benchmark/validate-photo-output.py"
cp "$run/results/$name/report.json" "$run/rootfs/app/benchmark/validation-input.json"
chroot "$run/rootfs" /usr/bin/python3 /app/benchmark/validate-photo-output.py /app/benchmark/validation-input.json > "$out/evidence-final/$name-photo-validation.json"
