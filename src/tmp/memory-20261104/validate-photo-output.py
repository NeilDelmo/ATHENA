import hashlib
import json
import sys
from io import BytesIO
from pathlib import Path
from xml.etree import ElementTree
from zipfile import ZipFile
from PIL import Image

report = json.loads(Path(sys.argv[1]).read_text())
manifest = json.loads(Path('/app/storage/app/private/capacity-photos/manifest.json').read_text())
originals = []
for kind, records in manifest.items():
    for record in records:
        path = Path('/app/storage/app/private') / record['relative']
        actual = hashlib.sha256(path.read_bytes()).hexdigest()
        assert actual == record['sha256'], f'Original photo changed: {path}'
        originals.append({'path': record['relative'], 'sha256': actual, 'bytes': path.stat().st_size})
validated = []
responses = []
for phase in report['phases']:
    response = phase['result']
    if 'heavy' in response:
        responses.extend(response['heavy'])
    else:
        responses.append(response)
for response in responses:
    result = response.get('result', {})
    if result.get('case') not in ['progress-highres-6', 'progress-highres-12']:
        continue
    assert response['status'] == 200, response
    count = result['input_counts']['images']
    images = []
    with ZipFile(result['source_path']) as archive:
        names = [name for name in archive.namelist() if name.startswith('word/media/progress-figure-')]
        assert len(names) == count, (len(names), count)
        text = ''.join(ElementTree.fromstring(archive.read('word/document.xml')).itertext())
        for index in range(1, count + 1):
            marker = f'REPORTFIGURE{index}END'
            assert text.count(marker) == 1, (marker, text.count(marker))
        for name in names:
            data = archive.read(name)
            with Image.open(BytesIO(data)) as image:
                width, height = image.size
                assert max(width, height) <= 1600, (name, width, height)
                assert abs(width / height - 1.25) <= .002, (name, width, height)
                image.verify()
                images.append({'name': name, 'width': width, 'height': height, 'bytes': len(data)})
    validated.append({'case': result['case'], 'source': result['source_path'], 'pdf': result['pdf_path'], 'pages': result['pages'], 'all_captions_preserved': True, 'input_bytes': result['input_counts']['image_bytes'], 'exported_images': images})
assert validated, 'No successful highres photo result was available to validate'
assert report['memory_events_end']['oom_kill'] == 0, report['memory_events_end']
print(json.dumps({'run': report['name'], 'original_photos_verified': len(originals), 'all_original_sha256_unchanged': True, 'validated_exports': validated, 'oom_kills': 0}, indent=2))
