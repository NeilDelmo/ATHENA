#!/bin/bash
set -euo pipefail
input=/mnt/c/laragon/www/athena-app/src/tmp/memory-20261104
task_dir=/var/tmp/athena-memory-20261104
test -f "$input/final-verification.json"
test "$(realpath -- "$task_dir")" = /var/tmp/athena-memory-20261104
test -f "$task_dir/rootfs/app/benchmark/supervisord.conf"
systemctl stop athena-memory-2g.service
python3 - <<'PY'
import json, os
from pathlib import Path
root = '/var/tmp/athena-memory-20261104/rootfs'
remaining = []
for proc in Path('/proc').iterdir():
    if proc.name.isdigit():
        try:
            if os.path.realpath(proc / 'root') == root:
                remaining.append({'pid': int(proc.name), 'command': (proc / 'cmdline').read_bytes().replace(b'\x00', b' ').decode(errors='replace')})
        except (FileNotFoundError, PermissionError, ProcessLookupError):
            pass
assert not remaining, remaining
for line in Path('/proc/self/mountinfo').read_text().splitlines():
    target = line.split()[4]
    assert target != root and not target.startswith(root + '/'), 'Mount remains: ' + target
print('Owned runtime stopped; no processes or mounts remain in its rootfs.')
PY
rm -rf --one-file-system -- "$task_dir"
test ! -e "$task_dir"
python3 - <<'PY'
import json
from datetime import datetime, timezone
from pathlib import Path
out = Path('/mnt/c/laragon/www/athena-app/src/tmp/memory-20261104')
(out / 'cleanup.json').write_text(json.dumps({'completed_at_utc': datetime.now(timezone.utc).isoformat(), 'runtime_rootfs_removed': True, 'owned_processes_remaining': 0, 'owned_mounts_remaining': 0}, indent=2) + '\n')
print('Removed disposable runtime; saved results and logs remain.')
PY
