import json,os,subprocess
from pathlib import Path
root=Path('/var/tmp/athena-memory-20261104/rootfs')
cgroup=subprocess.check_output(['systemctl','show','athena-memory-2g.service','--property=ControlGroup','--value'],text=True).strip()
owned={int(pid) for pid in (Path('/sys/fs/cgroup')/cgroup.lstrip('/')/'cgroup.procs').read_text().split()}
processes=[]
for entry in Path('/proc').iterdir():
 if not entry.name.isdigit():continue
 try:
  if (entry/'root').resolve()!=root:continue
  pid=int(entry.name)
  command=(entry/'cmdline').read_bytes().replace(b'\0',b' ').decode(errors='replace').strip()
  processes.append({'pid':pid,'group':os.getpgid(pid),'inside_runtime_cgroup':pid in owned,'command':command})
 except (OSError,ValueError):pass
transient=[p for p in processes if 'convert-office-pdf.py' in p['command'] or 'libreoffice-worker.py --once' in p['command'] or '--convert-to' in p['command']]
office=[p for p in processes if 'soffice.bin' in p['command']]
wrappers=[p for p in processes if 'libreoffice-worker.py' in p['command']]
outside=[p for p in processes if not p['inside_runtime_cgroup']]
result={'runtime_cgroup':cgroup,'processes':processes,'persistent_office_processes':office,'persistent_supervisors':wrappers,'leftover_transient_office_processes':transient,'app_root_processes_outside_runtime':outside,'no_leftover_process_groups':not transient and not outside and len(office)==1 and len(wrappers)==1}
print(json.dumps(result,indent=2))
assert result['no_leftover_process_groups'],result
