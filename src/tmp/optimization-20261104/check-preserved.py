import hashlib
import json
import tarfile
from pathlib import Path

source = Path('/mnt/c/laragon/www/athena-app/src')
output = source / 'tmp/optimization-20261104'
owned = {'app/Services/RmsQuarterlyReportDocument.php', 'app/Services/LibreOfficePdfWorker.php',
         'scripts/libreoffice-worker.py', 'tests/Unit/RmsQuarterlyReportDocumentTest.php',
         'tests/Unit/LibreOfficePdfWorkerProcessTest.php', 'tests/Feature/LibreOfficeDocumentPdfConverterTest.php'}
paths = set()
for name in ['tracked-changes.txt', 'untracked-changes.txt']:
    paths.update((output / name).read_text(encoding='utf-8-sig').splitlines())
different, checked = [], []
with tarfile.open(output / 'source.tar') as archive:
    for path in sorted(paths - owned):
        try:
            original = archive.extractfile(path).read()
        except KeyError:
            different.append({'path': path, 'reason': 'not in initial snapshot'})
            continue
        current = (source / path).read_bytes()
        checked.append(path)
        if original != current:
            different.append({'path': path, 'reason': 'bytes changed', 'whitespace_only': original.split() == current.split()})
result = {'checked': checked, 'different': different}
(output / 'preserved-user-changes.json').write_text(json.dumps(result, indent=2))
print(json.dumps(result))
