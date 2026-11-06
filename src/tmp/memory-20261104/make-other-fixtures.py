import hashlib
import json
import sys
from pathlib import Path
from xml.sax.saxutils import escape
from zipfile import ZIP_DEFLATED, ZipFile

output = Path(sys.argv[1]).resolve()
output.mkdir(parents=True, exist_ok=True)
namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'
headers = ['Source Record ID', 'Source Title', 'ISSN', 'EISSN', 'Active or Inactive', 'Coverage', 'Titles Discontinued by Scopus', 'Source Type']
manifest = {}
for count in [10000, 50000]:
    strings = headers + ['Active', '2000-2026', '', 'Journal'] + [f'Capacity Synthetic Journal {i:05d}' for i in range(1, count + 1)]
    rows = ['<row r="1">' + ''.join(f'<c r="{chr(65+i)}1" t="s"><v>{i}</v></c>' for i in range(8)) + '</row>']
    for i in range(1, count + 1):
        row = i + 1
        values = [(str(100000 + i), False), (str(11 + i), True), (f'{10000000+i:08d}', False), (f'{20000000+i:08d}', False), ('8', True), ('9', True), ('10', True), ('11', True)]
        rows.append(f'<row r="{row}">' + ''.join(f'<c r="{chr(65+j)}{row}"' + (' t="s"' if shared else '') + f'><v>{value}</v></c>' for j, (value, shared) in enumerate(values)) + '</row>')
    path = output / f'scopus-{count}.xlsx'
    with ZipFile(path, 'w', ZIP_DEFLATED) as archive:
        archive.writestr('xl/sharedStrings.xml', f'<sst xmlns="{namespace}">' + ''.join(f'<si><t>{escape(value)}</t></si>' for value in strings) + '</sst>')
        archive.writestr('xl/workbook.xml', f'<workbook xmlns="{namespace}" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Scopus Sources Nov 2026" sheetId="1" r:id="rId1"/></sheets></workbook>')
        archive.writestr('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="worksheets/sheet1.xml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"/></Relationships>')
        archive.writestr('xl/worksheets/sheet1.xml', f'<worksheet xmlns="{namespace}"><sheetData>' + ''.join(rows) + '</sheetData></worksheet>')
    with ZipFile(path) as archive:
        assert archive.testzip() is None
    manifest[str(count)] = {'path': str(path), 'rows': count, 'bytes': path.stat().st_size, 'sha256': hashlib.sha256(path.read_bytes()).hexdigest()}
(output / 'other-manifest.json').write_text(json.dumps(manifest, indent=2))
print(json.dumps(manifest))
