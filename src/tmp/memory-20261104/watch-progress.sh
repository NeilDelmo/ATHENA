#!/bin/bash
set -euo pipefail
results=/var/tmp/athena-memory-20261104/results/4g-soak-20m
for attempt in $(seq 1 90); do
    cat "$results/progress.json"
    if grep -q '"finished_at"' "$results/report.json"; then exit 0; fi
    sleep 20
done
exit 1
