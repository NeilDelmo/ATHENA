#!/bin/bash
set -euo pipefail
root=/var/tmp/athena-memory-20261104/rootfs
out=/mnt/c/laragon/www/athena-app/src/tmp/memory-20261104
name=$1
mkdir -p "$out/evidence-final"
cp "$out/capture-capacity-state.php" "$root/app/benchmark/capture-capacity-state.php"
/bin/bash "$out/exec-capacity.sh" php benchmark/capture-capacity-state.php > "$out/evidence-final/$name-queue-state.json"
/usr/bin/python3 "$out/capture-process-state.py" > "$out/evidence-final/$name-process-state.json"
systemctl show athena-memory-2g.service --property=MainPID,ControlGroup,MemoryMax,MemorySwapMax,CPUQuotaPerSecUSec > "$out/evidence-final/$name-unit.txt"
pid=$(systemctl show athena-memory-2g.service --property=MainPID --value)
nsenter -t "$pid" -m -n chroot "$root" /usr/bin/supervisorctl -c /app/benchmark/supervisord.conf status > "$out/evidence-final/$name-services.txt"
cp "$root/app/storage/logs/laravel.log" "$out/evidence-final/$name-laravel.log"
cp "$root/app/storage/app/private/capacity-context.json" "$out/context-final.json"
