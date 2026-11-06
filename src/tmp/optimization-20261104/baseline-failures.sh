#!/bin/bash
set -euo pipefail
root=/var/tmp/athena-optimization-20261104/rootfs
input=/mnt/c/laragon/www/athena-app/src/tmp/optimization-20261104
test -d "$root/app/optimization"
test -s "$input/failing-classes.txt"
cp "$root/app/app/Services/RmsQuarterlyReportDocument.php" "$root/app/optimization/optimized-rms.php"
cp "$root/app/app/Services/LibreOfficePdfWorker.php" "$root/app/optimization/optimized-worker.php"
restore() {
    cp "$root/app/optimization/optimized-rms.php" "$root/app/app/Services/RmsQuarterlyReportDocument.php"
    cp "$root/app/optimization/optimized-worker.php" "$root/app/app/Services/LibreOfficePdfWorker.php"
}
trap restore EXIT
cp "$input/original/RmsQuarterlyReportDocument.php" "$root/app/app/Services/RmsQuarterlyReportDocument.php"
cp "$input/original/LibreOfficePdfWorker.php" "$root/app/app/Services/LibreOfficePdfWorker.php"
filter=$(tr -d '\r' < "$input/failing-classes.txt" | paste -sd '|')
set +e
systemd-run --unit=athena-opt-baseline-failures --wait --pipe --collect \
    --property=MemoryMax=1536M --property=MemorySwapMax=0 --property=CPUQuota=100% \
    /bin/bash "$input/exec-linux.sh" /usr/bin/php artisan test --compact \
    "--filter=($filter)::" --log-junit=/app/optimization/baseline-failures.xml \
    > "$input/baseline-failures.log" 2>&1
status=$?
set -e
cp "$root/app/optimization/baseline-failures.xml" "$input/baseline-failures.xml"
tail -n 12 "$input/baseline-failures.log"
exit "$status"
