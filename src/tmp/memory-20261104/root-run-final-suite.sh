#!/bin/bash
set -euo pipefail
input=/mnt/c/laragon/www/athena-app/src/tmp/memory-20261104
root=/var/tmp/athena-memory-20261104/rootfs
test -f "$input/baseline-overlay-restored"
python3 - <<'PY'
import hashlib, json, shutil
from pathlib import Path
source = Path('/mnt/c/laragon/www/athena-app/src')
target = Path('/var/tmp/athena-memory-20261104/rootfs/app')
out = source / 'tmp/memory-20261104'
hashes = json.loads((out / 'final-test-source-hashes.json').read_text())
hashes['tests/Feature/ProjectNarrativeReportTest.php'] = ''
for name in hashes:
    shutil.copyfile(source / name, target / name)
    hashes[name] = hashlib.sha256((source / name).read_bytes()).hexdigest()
    assert hashlib.sha256((target / name).read_bytes()).hexdigest() == hashes[name], name
(out / 'final-test-source-hashes.json').write_text(json.dumps(hashes, indent=2) + '\n')
print('Final source hashes match; rerunning suite after test fixture corrections.')
PY
set +e
systemd-run --unit=athena-memory-final-suite --wait --pipe --collect \
    --property=MemoryMax=1536M --property=MemorySwapMax=0 --property=CPUQuota=200% \
    /bin/bash "$input/exec-linux.sh" /usr/bin/php vendor/pestphp/pest/bin/pest --no-output \
    --log-junit=/app/benchmark/test-results/final-suite.xml \
    > "$input/final-suite.log" 2>&1
status=$?
set -e
printf '%s\n' "$status" > "$input/final-suite.exit-code"
cp "$root/app/benchmark/test-results/final-suite.xml" "$input/final-suite.xml"
python3 - <<'PY'
import json, xml.etree.ElementTree as ET
from pathlib import Path
out = Path('/mnt/c/laragon/www/athena-app/src/tmp/memory-20261104')
cases = list(ET.parse(out / 'final-suite.xml').iter('testcase'))
failures = [case for case in cases if case.find('failure') is not None or case.find('error') is not None]
skipped = [case for case in cases if case.find('skipped') is not None]
summary = {'total': len(cases), 'passed': len(cases)-len(failures)-len(skipped), 'failed': len(failures), 'skipped': len(skipped), 'assertions': sum(int(case.get('assertions', '0')) for case in cases)}
(out / 'final-suite-summary.json').write_text(json.dumps(summary, indent=2) + '\n')
print(json.dumps(summary))
PY
