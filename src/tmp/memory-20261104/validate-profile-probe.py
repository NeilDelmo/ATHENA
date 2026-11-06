import json,sys
from pathlib import Path
name=sys.argv[1]
r=json.loads(Path('/var/tmp/athena-memory-20261104/results',name,'report.json').read_text())
assert r['server_config']['projects']==1000,r['server_config']
assert not r['memory_events_end']['oom_kill'],r['memory_events_end']
for p in r['phases']:
 if 'status' in p['result']:assert p['result']['status']==200,(p['phase'],p['result'])
queue=next(p for p in r['phases'] if p['phase']=='queue-wait')['result']['result']
assert queue['state']['status']=='ready',queue
assert queue['failed_jobs']==0,queue
assert queue['jobs']==0,queue
print('Warmup probe validated:1000 projects, every routeHTTP200, actualPDFqueue ready,0failedjobs,0OOM')
