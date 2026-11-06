import hashlib,json,sys
from io import BytesIO
from pathlib import Path
from zipfile import ZipFile
from PIL import Image
report=json.loads(Path('/app/benchmark/terminal-validation-input.json').read_text())
phase=next((p for p in report['phases'] if p['phase']=='terminal-240'),None)
if phase is not None:
 response=phase['result']
else:
 phase=next(p for p in report['phases'] if p['phase']=='combined')
 response=next(r for r in phase['result']['heavy'] if r['case']=='terminal-images-tables')
result=response['result']
assert response['status']==200
fixtures=json.loads(Path('/app/storage/app/private/capacity-fixtures/manifest.json').read_text())['fixtures']
verified=[]
for key,record in fixtures.items():
 path=Path(record['path']);actual=hashlib.sha256(path.read_bytes()).hexdigest()
 assert actual==record['sha256'],key
 verified.append(key)
photos=json.loads(Path('/app/storage/app/private/capacity-photos/manifest.json').read_text())
for records in photos.values():
 for record in records:
  path=Path('/app/storage/app/private')/record['relative']
  assert hashlib.sha256(path.read_bytes()).hexdigest()==record['sha256'],str(path)
images=[]
with ZipFile(result['source_path']) as archive:
 for name in archive.namelist():
  if not name.startswith('word/media/progress-figure-'):continue
  data=archive.read(name)
  with Image.open(BytesIO(data)) as image:
   assert max(image.size)<=1600,(name,image.size)
   images.append({'name':name,'width':image.width,'height':image.height,'bytes':len(data)})
assert len(images)==12,len(images)
print(json.dumps({'original_fixture_sha256_unchanged':True,'fixture_files_verified':len(verified),'original_photo_sha256_unchanged':True,'photo_files_verified':sum(map(len,photos.values())),'embedded_image_count':len(images),'all_embedded_images_bounded_1600':True,'images':images,'case':result,'resources':phase['resources'],'request_seconds':response['seconds']},indent=2))
