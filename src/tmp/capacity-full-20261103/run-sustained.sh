#!/bin/bash
set -euo pipefail
driver=/mnt/c/laragon/www/athena-app/src/tmp/capacity-full-20261103/run-driver.sh
unit=athena-capacity-4g.service
/bin/bash "$driver" "$unit" 4g-burst-1000 --mode burst
/bin/bash "$driver" "$unit" 4g-soak-20m --mode soak --seconds 1200
/bin/bash /mnt/c/laragon/www/athena-app/src/tmp/capacity-full-20261103/copy-results.sh
