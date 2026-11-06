import json,tarfile,pathlib,hashlib,datetime
base=pathlib.Path.cwd(); out=base/'tmp/memory-20261104'
manifest=json.loads((base/'public/build/manifest.json').read_text())
assets={'public/build/manifest.json'}
for item in manifest.values():
 for field in ('file','css','assets'):
  value=item.get(field,[])
  if isinstance(value,str): value=[value]
  assets.update('public/build/'+v for v in value)
excluded={'.git','.env','.env.example','vendor','node_modules','storage','tmp','.agents','.codex','bootstrap/cache','public/build','public/storage'}
files=[]
with tarfile.open(out/'source.tar','w') as archive:
 for path in base.rglob('*'):
  rel=path.relative_to(base).as_posix()
  if any(rel==x or rel.startswith(x+'/') for x in excluded): continue
  if path.is_file() and not path.is_symlink():
   archive.add(path,arcname=rel,recursive=False); files.append(rel)
 for rel in sorted(assets):
  path=base/rel
  if path.exists(): archive.add(path,arcname=rel,recursive=False); files.append(rel)
with tarfile.open(out/'vendor.tar','w') as archive: archive.add(base/'vendor',arcname='vendor')
info={'captured_at':datetime.datetime.now(datetime.timezone.utc).isoformat(),'files':len(files),'source_sha256':hashlib.sha256((out/'source.tar').read_bytes()).hexdigest(),'referenced_assets':sorted(assets)}
(out/'snapshot.json').write_text(json.dumps(info,indent=2)); (out/'snapshot-ready').touch(); print(json.dumps(info))
