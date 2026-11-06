#!/bin/bash
set -euo pipefail
out=/mnt/c/laragon/www/athena-app/src/tmp/memory-20261104
children=$1
[[ "$children" = 2 || "$children" = 4 ]]
/bin/bash "$out/run-driver.sh" athena-memory-2g.service "final-${children}fpm-probe-1000" --mode probe > "$out/final-${children}fpm-probe.log" 2>&1
/usr/bin/python3 "$out/validate-profile-probe.py" "final-${children}fpm-probe-1000"
/bin/bash "$out/capture-final-run.sh" "final-${children}fpm-probe-1000"
/bin/bash "$out/run-driver.sh" athena-memory-2g.service "final-${children}fpm-burst-1000" --mode burst --cases progress-highres-12,progress-highres-12 > "$out/final-${children}fpm-burst.log" 2>&1
/bin/bash "$out/capture-final-photo-run.sh" "final-${children}fpm-burst-1000"
