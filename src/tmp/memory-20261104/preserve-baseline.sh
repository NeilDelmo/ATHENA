#!/bin/bash
set -euo pipefail
run=/var/tmp/athena-memory-20261104
out=/mnt/c/laragon/www/athena-app/src/tmp/memory-20261104
mkdir -p "$out/evidence-baseline"
cp -a "$run/results/baseline-2g" "$out/evidence-baseline/"
cp "$run/rootfs/app/storage/app/private/capacity-photos/manifest.json" "$out/evidence-baseline/photo-manifest.json"
cp "$run/rootfs/app/storage/logs/laravel.log" "$out/evidence-baseline/laravel.log"
cp "$run/rootfs/app/.env" "$out/evidence-baseline/synthetic-runtime.env"
systemctl show athena-memory-2g.service --property=MemoryMax,MemorySwapMax,CPUQuotaPerSecUSec,ControlGroup,MainPID > "$out/evidence-baseline/runtime-unit.txt"
pid=$(systemctl show athena-memory-2g.service --property=MainPID --value)
nsenter -t "$pid" -m -n chroot "$run/rootfs" /usr/bin/supervisorctl -c /app/benchmark/supervisord.conf status > "$out/evidence-baseline/services-after.txt"
python3 - <<'PY'
import json
from pathlib import Path
r=json.loads(Path('/var/tmp/athena-memory-20261104/results/baseline-2g/report.json').read_text())
for x in r['phases']:
 print(x['phase'],x['result'].get('status'),x['resources'].get('peak_mib'),x['resources'].get('oom_kills_delta'),x['resources'].get('memory_events_end'))
print('events_end',r['memory_events_end'])
PY
