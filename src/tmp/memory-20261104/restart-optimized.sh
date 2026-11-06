#!/bin/bash
set -euo pipefail
systemctl stop athena-memory-2g.service
exec /bin/bash /mnt/c/laragon/www/athena-app/src/tmp/memory-20261104/start-runtime.sh
