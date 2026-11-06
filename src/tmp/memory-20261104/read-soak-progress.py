import json
from pathlib import Path
root=Path('/var/tmp/athena-memory-20261104/results/final-4fpm-soak-20m')
progress=json.loads((root/'progress.json').read_text())
cases=Path('/mnt/c/laragon/www/athena-app/src/tmp/memory-20261104/soak-cases.txt').read_text().split(',')
progress['current_case']=cases[progress['heavy_completed'] % len(cases)]
print(json.dumps(progress))
