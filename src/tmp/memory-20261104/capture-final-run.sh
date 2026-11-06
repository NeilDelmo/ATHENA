#!/bin/bash
set -euo pipefail
run=/var/tmp/athena-memory-20261104
out=/mnt/c/laragon/www/athena-app/src/tmp/memory-20261104
mkdir -p "$out/evidence-final"
cp -a "$run/results/$1" "$out/evidence-final/"
