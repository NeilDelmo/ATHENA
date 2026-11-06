#!/bin/bash
set -euo pipefail
exec systemd-run --unit=athena-memory-2g --property=PrivateMounts=yes --property=PrivateNetwork=yes --property=MemoryMax=1792M --property=MemorySwapMax=0 --property=CPUQuota=200% --property=TasksMax=512 --wait --pipe /bin/bash /mnt/c/laragon/www/athena-app/src/tmp/memory-20261104/runtime-linux.sh
