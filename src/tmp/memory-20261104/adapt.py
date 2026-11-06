from pathlib import Path
out=Path('tmp/memory-20261104'); previous=Path('tmp/capacity-full-20261103')
for path in previous.iterdir():
 if path.suffix in {'.php','.py','.conf','.sh'} and path.name not in {'cleanup-linux.sh','cleanup-workspace.ps1','finalize-results.py','copy-results.sh'}:
  data=path.read_text().replace('/var/tmp/athena-capacity-20261103','/var/tmp/athena-memory-20261104').replace('/mnt/c/laragon/www/athena-app/src/tmp/capacity-full-20261103','/mnt/c/laragon/www/athena-app/src/tmp/memory-20261104')
  if path.name=='prepare-linux.sh': data=data.replace('capacity_athena','athena_testing').replace('capacity_testing','athena_testing')
  (out/path.name).write_text(data,newline='\n')
print('Adapted reusable full-stack scripts')
