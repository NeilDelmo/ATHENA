#!/bin/bash
set -euo pipefail
run=/var/tmp/athena-capacity-20261103
output=/mnt/c/laragon/www/athena-app/src/tmp/capacity-full-20261103
test "$(realpath -e "$run")" = /var/tmp/athena-capacity-20261103
test -s "$output/capacity-report.json"
test -s "$output/measurement-evidence.zip"
for unit in athena-capacity-2g.service athena-capacity-2g-clean.service athena-capacity-2g-resume.service athena-capacity-4g.service athena-capacity-4g-retry.service; do
    if systemctl is-active --quiet "$unit"; then
        printf '%s\n' "Refusing to remove an active test filesystem: $unit" >&2
        exit 1
    fi
done
if mountpoint -q "$run/rootfs/proc" || mountpoint -q "$run/rootfs/dev"; then
    printf '%s\n' 'Refusing to remove a mounted test filesystem.' >&2
    exit 1
fi
rm -rf --one-file-system -- "$run"
test ! -e "$run"
printf '%s\n' 'Disposable Linux filesystem removed; workspace evidence retained.'
