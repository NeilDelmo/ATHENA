#!/bin/bash
set -euo pipefail
out=/mnt/c/laragon/www/athena-app/src/tmp/memory-20261104
/bin/bash "$out/clear-document-cache.sh"
/bin/bash "$out/run-driver.sh" athena-memory-2g.service final-2g-cache-cold12 --mode matrix --cases progress-highres-12 > "$out/final-cache-cold12.log" 2>&1
/bin/bash "$out/capture-final-photo-run.sh" final-2g-cache-cold12
/bin/bash "$out/run-driver.sh" athena-memory-2g.service final-2g-cache-warm12 --mode matrix --cases progress-highres-12 > "$out/final-cache-warm12.log" 2>&1
/bin/bash "$out/capture-final-photo-run.sh" final-2g-cache-warm12
