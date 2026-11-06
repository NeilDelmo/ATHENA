#!/bin/bash
set -euo pipefail
run=/var/tmp/athena-capacity-20261103
output=/mnt/c/laragon/www/athena-app/src/tmp/capacity-full-20261103
mkdir -p "$output/results" "$output/artifacts" "$output/linux-logs"
cp -a "$run/results/." "$output/results/"
cp -a "$run/rootfs/app/storage/app/private/capacity-output/." "$output/artifacts/"
cp "$run/rootfs/app/storage/app/private/capacity-fixtures/manifest.json" "$output/artifacts/fixture-manifest.json"
cp "$run/rootfs/app/storage/app/private/capacity-context.json" "$output/context.json"
find "$run/rootfs/app/storage/logs" -maxdepth 1 -type f -exec cp -t "$output/linux-logs" {} +
