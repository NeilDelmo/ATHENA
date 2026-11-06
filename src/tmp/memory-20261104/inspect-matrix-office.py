import json
from pathlib import Path
r=json.loads(Path('/var/tmp/athena-memory-20261104/results/optimized-2g-full-matrix/report.json').read_text())
for phase in r['phases']:
 if phase['phase'] not in ['terminal-images-tables','quarterly-evidence']: continue
 print(json.dumps({'phase':phase['phase'],'charged_peak_mib':phase['resources']['peak_mib'],'pss_peak_mib':phase['resources']['peak_pss_mib'],'events':phase['resources']['memory_events_end'],'office_at_peak':[p for p in phase['resources']['processes_at_peak'] if 'soffice' in p['command'] or 'libreoffice-worker.py' in p['command']]},indent=2))
