from pathlib import Path
import tarfile
out=Path('tmp/memory-20261104')
with tarfile.open(out/'source.tar') as old, tarfile.open(out/'source-sanitized.tar','w') as new:
 for member in old:
  if Path(member.name).name.startswith('.env'): continue
  new.addfile(member,old.extractfile(member) if member.isfile() else None)
(out/'source-sanitized.tar').replace(out/'source.tar')
print('Removed all .env variants from snapshot')
