#!/bin/bash
set -euo pipefail
exec systemd-run --unit=athena-memory-prepare --property=PrivateMounts=yes --property=PrivateNetwork=yes --wait --pipe /bin/bash /mnt/c/laragon/www/athena-app/src/tmp/memory-20261104/prepare-linux.sh
