#!/bin/bash
set -euo pipefail
input=/mnt/c/laragon/www/athena-app/src/tmp/memory-20261104
root=/var/tmp/athena-memory-20261104/rootfs
test -f "$input/final-full-suite.exit-code"
restore() {
    python3 - <<'PY'
import hashlib, json, shutil
from pathlib import Path
out = Path('/mnt/c/laragon/www/athena-app/src/tmp/memory-20261104')
root = Path('/var/tmp/athena-memory-20261104/rootfs/app')
backup = Path('/var/tmp/athena-memory-20261104/test-baseline-backup')
if (out / 'baseline-overlay-files.json').exists():
    for name in json.loads((out / 'baseline-overlay-files.json').read_text()):
        shutil.copyfile(backup / name, root / name)
    for name, expected in json.loads((out / 'final-test-source-hashes.json').read_text()).items():
        assert hashlib.sha256((root / name).read_bytes()).hexdigest() == expected, name
    (out / 'baseline-overlay-restored').touch()
PY
}
trap restore EXIT
python3 - <<'PY'
import json, shutil, tarfile, xml.etree.ElementTree as ET
from pathlib import Path
out = Path('/mnt/c/laragon/www/athena-app/src/tmp/memory-20261104')
root = Path('/var/tmp/athena-memory-20261104/rootfs/app')
backup = Path('/var/tmp/athena-memory-20261104/test-baseline-backup')
assert not backup.exists(), 'Baseline backup already exists; inspect before rerunning.'
failures = []
for case in ET.parse(out / 'final-full-suite.xml').iter('testcase'):
    problem = case.find('failure')
    if problem is None:
        problem = case.find('error')
    if problem is not None:
        name = case.get('file', '').split('::', 1)[0]
        if name.startswith('/app/'):
            name = name[5:]
        if not name:
            parts = case.get('classname').removeprefix('P\\').split('\\')
            parts[0] = 'tests'
            name = '/'.join(parts) + '.php'
        failures.append({'class': case.get('classname'), 'name': case.get('name'), 'file': name, 'message': (problem.text or '')[:700]})
(out / 'full-suite-failures.json').write_text(json.dumps(failures, indent=2) + '\n')
tests = sorted({item['file'] for item in failures})
assert tests, 'No failed tests need baseline comparison.'
with tarfile.open(out / 'source.tar') as archive:
    members = {member.name: member for member in archive.getmembers()}
    for name in tests:
        assert name in members, 'New test failed; investigate first: ' + name
    production = [name for name in json.loads((out / 'final-test-source-hashes.json').read_text()) if not name.startswith('tests/') and name in members]
    files = sorted(set(production + tests))
    for name in files:
        destination = (root / name).resolve()
        assert destination.is_relative_to(root.resolve()), name
        (backup / name).parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(destination, backup / name)
    (out / 'baseline-overlay-files.json').write_text(json.dumps(files, indent=2) + '\n')
    for name in files:
        (root / name).write_bytes(archive.extractfile(members[name]).read())
(out / 'baseline-test-files.txt').write_text('\n'.join(tests) + '\n')
print(f'Checking {len(failures)} failed cases against frozen original source across {len(tests)} test files.')
PY
mapfile -t tests < "$input/baseline-test-files.txt"
set +e
systemd-run --unit=athena-memory-baseline-suite --wait --pipe --collect \
    --property=MemoryMax=1536M --property=MemorySwapMax=0 --property=CPUQuota=100% \
    /bin/bash "$input/exec-linux.sh" /usr/bin/php artisan test --compact "${tests[@]}" \
    --log-junit=/app/benchmark/test-results/baseline-suite.xml \
    > "$input/final-baseline-suite.log" 2>&1
status=$?
set -e
printf '%s\n' "$status" > "$input/final-baseline-suite.exit-code"
cp "$root/app/benchmark/test-results/baseline-suite.xml" "$input/final-baseline-suite.xml"
python3 - <<'PY'
import json, xml.etree.ElementTree as ET
from pathlib import Path
out = Path('/mnt/c/laragon/www/athena-app/src/tmp/memory-20261104')
def cases(name):
    return {(case.get('classname'), case.get('name')) for case in ET.parse(out / name).iter('testcase') if case.find('failure') is not None or case.find('error') is not None}
current = cases('final-full-suite.xml')
baseline = cases('final-baseline-suite.xml')
summary = {'current_failures': len(current), 'baseline_failures_in_compared_files': len(baseline), 'all_current_failures_reproduced_on_original': current <= baseline, 'current_only': sorted(current - baseline), 'baseline_only': sorted(baseline - current)}
(out / 'full-suite-baseline-comparison.json').write_text(json.dumps(summary, indent=2) + '\n')
print(json.dumps(summary))
assert current <= baseline, 'Some failures require further investigation.'
PY
