#!/bin/bash
set -euo pipefail
out=/mnt/c/laragon/www/athena-app/src/tmp/memory-20261104
/bin/bash "$out/run-driver.sh" athena-memory-2g.service final-2cpu-4fpm-probe-1000 --mode probe > "$out/final-2cpu-probe.log" 2>&1
/usr/bin/python3 "$out/validate-profile-probe.py" final-2cpu-4fpm-probe-1000
/bin/bash "$out/capture-final-run.sh" final-2cpu-4fpm-probe-1000
/bin/bash "$out/run-driver.sh" athena-memory-2g.service final-2cpu-4fpm-burst-1000 --mode burst --cases progress-highres-12,progress-highres-12 > "$out/final-2cpu-burst.log" 2>&1
/bin/bash "$out/capture-final-photo-run.sh" final-2cpu-4fpm-burst-1000
/bin/bash "$out/run-driver.sh" athena-memory-2g.service final-2cpu-terminal-scan-browse --mode combined --seconds 360 --max-heavy 2 --cases terminal-images-tables,signed-form-scan > "$out/final-2cpu-terminal-scan.log" 2>&1
/bin/bash "$out/capture-final-run.sh" final-2cpu-terminal-scan-browse
/bin/bash "$out/capture-health-checks.sh" final-2cpu-comparison
