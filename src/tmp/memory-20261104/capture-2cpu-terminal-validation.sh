#!/bin/bash
set -euo pipefail
root=/var/tmp/athena-memory-20261104/rootfs
out=/mnt/c/laragon/www/athena-app/src/tmp/memory-20261104
cp "$out/validate-terminal.py" "$root/app/benchmark/validate-terminal.py"
cp "$out/evidence-final/final-2cpu-terminal-scan-browse/report.json" "$root/app/benchmark/terminal-validation-input.json"
chroot "$root" /usr/bin/python3 /app/benchmark/validate-terminal.py > "$out/evidence-final/final-2cpu-terminal-validation.json"
