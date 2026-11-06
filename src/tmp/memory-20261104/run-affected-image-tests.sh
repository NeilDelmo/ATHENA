#!/bin/bash
set -euo pipefail
exec /bin/bash /mnt/c/laragon/www/athena-app/src/tmp/memory-20261104/exec-linux.sh php -d memory_limit=512M artisan test --compact tests/Unit/DocumentImageServiceTest.php tests/Unit/ReportImageOptimizationTest.php tests/Feature/ResearchCoordinatorReportTest.php '--filter=DocumentImageServiceTest|ReportImageOptimizationTest|unsaved coordinator evidence previews bound photo dimensions'
