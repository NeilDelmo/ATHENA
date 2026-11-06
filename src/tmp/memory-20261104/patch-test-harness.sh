#!/bin/bash
set -euo pipefail
python3 - <<'PY'
from pathlib import Path
path=Path('/var/tmp/athena-memory-20261104/rootfs/app/routes/web.php')
s=path.read_text()
s=s.replace('require base_path("benchmark/task.php");\nrequire base_path("benchmark/routes-capacity.php");','if (! app()->environment("testing")) {\n    require_once base_path("benchmark/task.php");\n    require_once base_path("benchmark/routes-capacity.php");\n}')
path.write_text(s)
assert 'if (! app()->environment("testing"))' in s
print('Private harness routes excluded from testing application resets')
PY
