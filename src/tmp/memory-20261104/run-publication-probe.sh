#!/bin/bash
set -euo pipefail
exec /bin/bash /mnt/c/laragon/www/athena-app/src/tmp/memory-20261104/exec-linux.sh php vendor/bin/pest --no-configuration --test-directory tmp/worker-publication-probe --compact tmp/worker-publication-probe/WorkerPublicationRaceTest.php '--filter=normal shutdown'
