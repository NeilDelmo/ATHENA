#!/bin/bash
set -euo pipefail
python3 - <<'PY'
from pathlib import Path
import shutil
cache=Path('/var/tmp/athena-memory-20261104/rootfs/app/storage/app/private/image-previews/documents-v1')
assert not cache.is_symlink()
assert cache.resolve()==cache
if cache.exists():
    assert cache.is_dir()
    shutil.rmtree(cache)
print('Cleared only the authorized documents-v1 derivative cache; originals untouched')
PY
