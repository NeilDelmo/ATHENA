from pathlib import Path
out=Path('tmp/memory-20261104')
data=Path('tmp/optimization-20261104/exec-linux.sh').read_text().replace('athena-optimization-20261104','athena-memory-20261104').replace('athena-opt-runtime.service','athena-memory-2g.service').replace('athena_optimization_testing','athena_testing')
(out/'exec-linux.sh').write_text(data,newline='\n')
(out/'start-runtime.sh').write_text('''#!/bin/bash
set -euo pipefail
exec systemd-run --unit=athena-memory-2g --property=PrivateMounts=yes --property=PrivateNetwork=yes --property=MemoryMax=1792M --property=MemorySwapMax=0 --property=CPUQuota=100% --property=TasksMax=512 --wait --pipe /bin/bash /mnt/c/laragon/www/athena-app/src/tmp/memory-20261104/runtime-linux.sh
''',newline='\n')
(out/'start-prepare.sh').write_text('''#!/bin/bash
set -euo pipefail
exec systemd-run --unit=athena-memory-prepare --property=PrivateMounts=yes --property=PrivateNetwork=yes --wait --pipe /bin/bash /mnt/c/laragon/www/athena-app/src/tmp/memory-20261104/prepare-linux.sh
''',newline='\n')
print('Runtime and private DB helpers ready')
