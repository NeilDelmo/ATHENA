import json
import sys
from pathlib import Path

root = Path('/var/tmp/athena-memory-20261104/results')
summaries = []
for file in sorted(root.glob('*/report.json')):
    if len(sys.argv) > 1 and file.parent.name not in sys.argv[1:]:
        continue
    report = json.loads(file.read_text())
    phases = []
    for phase in report.get('phases', []):
        result = phase['result']
        nested = result.get('result', {})
        phases.append({'phase': phase['phase'], 'elapsed': round(phase['seconds'], 3),
                       'peak_mib': round(phase['resources'].get('peak_mib', 0), 2),
                       'anon_mib': round(phase['resources'].get('peak_anon_mib', 0), 2),
                       'pss_mib': round(phase['resources'].get('peak_pss_mib', 0), 2),
                       'status': result.get('status'), 'errors': result.get('errors'),
                       'p50_seconds': result.get('p50_seconds'), 'p95_seconds': result.get('p95_seconds'),
                       'count': result.get('count'), 'php_peak_mib': nested.get('php_peak_mib'),
                       'pages': nested.get('pages'), 'input_counts': nested.get('input_counts'),
                       'heavy': [{'case': item.get('case'), 'status': item.get('status'), 'seconds': item.get('seconds')} for item in result.get('heavy', [])]})
    summaries.append({'run': report['name'], 'limits': report.get('limits'), 'phases': phases,
                      'oom': report.get('memory_events_end'), 'peak_mib': report.get('cgroup_peak_mib'),
                      'fatal': report.get('fatal'), 'reverb_errors': report.get('reverb_heartbeat_errors'),
                      'finished': bool(report.get('finished_at'))})
for report in summaries:
    print(json.dumps({key: value for key, value in report.items() if key != 'phases'}, separators=(',', ':')))
    for phase in report['phases']:
        print(json.dumps({key: value for key, value in phase.items() if value is not None and value != []}, separators=(',', ':')))
