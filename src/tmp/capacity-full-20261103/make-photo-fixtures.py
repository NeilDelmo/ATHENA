import hashlib
import json
import runpy
import sys
from pathlib import Path

output = Path(sys.argv[1]).resolve()
output.mkdir(parents=True, exist_ok=True)
jpeg = runpy.run_path(str(Path(__file__).with_name('make-fixtures.py')))['jpeg']
manifest = {'highres': [], 'normal': []}
for kind, count, size, report in [('highres', 12, (5000, 4000), False), ('normal', 30, (2400, 1600), True)]:
    (output / kind).mkdir(exist_ok=True)
    for index in range(1, count + 1):
        path = output / kind / f'photo-{index:02d}.jpg'
        jpeg(path, size, 200 + index, report=report)
        assert 0 < path.stat().st_size <= 5 * 1024 * 1024
        manifest[kind].append({'relative': f'capacity-photos/{kind}/{path.name}', 'bytes': path.stat().st_size,
                               'width': size[0], 'height': size[1], 'sha256': hashlib.sha256(path.read_bytes()).hexdigest()})
    assert len({item['sha256'] for item in manifest[kind]}) == count
assert sum(item['bytes'] for item in manifest['highres']) < 64 * 1024 * 1024
(output / 'manifest.json').write_text(json.dumps(manifest, indent=2))
print(json.dumps({key: {'images': len(values), 'bytes': sum(item['bytes'] for item in values)} for key, values in manifest.items()}))
