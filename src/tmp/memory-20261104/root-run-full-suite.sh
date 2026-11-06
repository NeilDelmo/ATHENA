#!/bin/bash
set -euo pipefail
input=/mnt/c/laragon/www/athena-app/src/tmp/memory-20261104
root=/var/tmp/athena-memory-20261104/rootfs
test -f "$input/capacity-measurements-complete"
test -f "$root/app/artisan"
python3 - <<'PY'
import hashlib, json, shutil
from pathlib import Path
source = Path('/mnt/c/laragon/www/athena-app/src')
target = Path('/var/tmp/athena-memory-20261104/rootfs/app')
out = source / 'tmp/memory-20261104'
files = list(json.loads((out / 'runtime-final-code-hashes.json').read_text()))
files += ['app/Actions/SubmitProposalDraft.php', 'tests/Feature/ProposalDraftWorkflowTest.php',
          'tests/Feature/LibreOfficeDocumentPdfConverterTest.php', 'tests/Feature/ResearchCoordinatorReportTest.php', 'tests/Feature/ProjectNarrativeReportTest.php',
          'tests/Unit/DocumentImageServiceTest.php', 'tests/Unit/ReportImageOptimizationTest.php',
          'tests/Unit/LibreOfficeProcessTreeTest.php', 'tests/Unit/RecoverProposalPdfsTest.php',
          'tests/Unit/QueuedPdfConversionBudgetTest.php', 'tests/Unit/LibreOfficePdfWorkerProcessTest.php']
hashes = {}
for name in files:
    shutil.copyfile(source / name, target / name)
    digest = hashlib.sha256((source / name).read_bytes()).hexdigest()
    assert hashlib.sha256((target / name).read_bytes()).hexdigest() == digest, name
    hashes[name] = digest
(out / 'final-test-source-hashes.json').write_text(json.dumps(hashes, indent=2) + '\n')
preserved = json.loads((out / 'preserved-user-changes.json').read_text())
for item in preserved['files']:
    assert hashlib.sha256((source / item['path']).read_bytes()).hexdigest() == item['baseline_sha256'], item['path']
print('Final test source synchronized; all 24 unrelated user changes remain byte-identical.')
PY
/bin/bash "$input/exec-linux.sh" /usr/bin/supervisorctl -c /app/benchmark/supervisord.conf \
    stop fpm nginx pdf-recovery pdf-queue default-queue reverb scheduler
mkdir -p "$root/app/benchmark/test-results"
set +e
systemd-run --unit=athena-memory-full-suite --wait --pipe --collect \
    --property=MemoryMax=1536M --property=MemorySwapMax=0 --property=CPUQuota=100% \
    /bin/bash "$input/exec-linux.sh" /usr/bin/php artisan test --compact \
    --log-junit=/app/benchmark/test-results/full-suite.xml \
    > "$input/final-full-suite.log" 2>&1
status=$?
set -e
printf '%s\n' "$status" > "$input/final-full-suite.exit-code"
cp "$root/app/benchmark/test-results/full-suite.xml" "$input/final-full-suite.xml"
tail -n 13 "$input/final-full-suite.log"
exit "$status"
