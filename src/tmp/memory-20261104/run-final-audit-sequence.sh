#!/bin/bash
set -euo pipefail
out=/mnt/c/laragon/www/athena-app/src/tmp/memory-20261104
/bin/bash "$out/run-publication-probe.sh" > "$out/publication-probe-green.log" 2>&1
/bin/bash "$out/exec-linux.sh" php -d memory_limit=512M artisan test --compact tests/Unit/LibreOfficePdfWorkerProcessTest.php > "$out/final-worker-fixture-green.log" 2>&1
exec /bin/bash "$out/exec-linux.sh" env ATHENA_PDF_BENCHMARK=1 php -d memory_limit=512M artisan test --compact tests/Unit/RecoverProposalPdfsTest.php tests/Unit/LibreOfficeProcessTreeTest.php tests/Unit/LibreOfficePdfWorkerProcessTest.php tests/Unit/LibreOfficePdfWorkerNativeTest.php tests/Feature/LibreOfficeDocumentPdfConverterTest.php > "$out/final-audit-green-rerun.log" 2>&1
