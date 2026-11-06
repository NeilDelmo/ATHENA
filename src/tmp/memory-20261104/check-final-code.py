import hashlib,json
from pathlib import Path
out=Path('/mnt/c/laragon/www/athena-app/src/tmp/memory-20261104')
root=Path('/var/tmp/athena-memory-20261104/rootfs/app')
expected=json.loads((out/'final-code-hashes.json').read_text())
actual={name:hashlib.sha256((root/name).read_bytes()).hexdigest() for name in expected}
(out/'runtime-final-code-hashes.json').write_text(json.dumps(actual,indent=2))
different=[name for name in expected if expected[name]!=actual[name]]
print('Final code fingerprint mismatches:',different)
assert not different
