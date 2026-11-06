#!/bin/bash
set -euo pipefail
run=/var/tmp/athena-optimization-20261104
input=/mnt/c/laragon/www/athena-app/src/tmp/optimization-20261104
resolved=$(realpath "$run")
test "$resolved" = /var/tmp/athena-optimization-20261104
test -f "$run/setup-complete"
for unit in athena-opt-final.service athena-opt-suite.service athena-opt-baseline-failures.service; do
    if systemctl is-active --quiet "$unit"; then
        printf 'Test unit still active: %s\n' "$unit"
        exit 1
    fi
done
ps -C soffice.bin -o pid=,ppid=,pgid=,stat=,rss=,args= > "$input/office-processes-after.txt" || true
du -sh "$run"
systemctl stop athena-opt-runtime.service
test "$(systemctl show athena-opt-runtime.service --property=MainPID --value)" = 0
if mountpoint -q "$run/rootfs/proc" || mountpoint -q "$run/rootfs/dev"; then
    exit 1
fi
rm -rf --one-file-system -- "$resolved"
test ! -e "$run"
printf '%s\n' 'Removed the isolated Linux test root and stopped its private database.'
