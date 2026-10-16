import fs from 'node:fs/promises';
import { FileBlob, SpreadsheetFile } from '@oai/artifact-tool';

const sheets = ['RMS-Presentation', 'RMS-Publication', 'RMS-IP Assets'];
for (const [name, path] of [
    ['template', '../src/resources/documents/RMS-Quarterly-Monitoring-Report.xlsx'],
    ['export', 'rms-export-check.xlsx'],
    ['expanded', 'rms-export-expanded-check.xlsx'],
]) {
    const workbook = await SpreadsheetFile.importXlsx(await FileBlob.load(new URL(path, import.meta.url).pathname.replace(/^\/(?=[A-Za-z]:)/, '')));
    console.log(name, (await workbook.inspect({ kind: 'sheet', include: 'id,name', maxChars: 1000 })).ndjson);
    for (const [index, sheetName] of sheets.entries()) {
        const preview = await workbook.render({ sheetName, range: name === 'expanded' ? `A1:${index === 2 ? 'H' : 'R'}35` : `A1:${index === 2 ? 'H' : 'R'}33`, scale: 1, format: 'png' });
        await fs.writeFile(new URL(`rms-${name}-${index + 1}.png`, import.meta.url), new Uint8Array(await preview.arrayBuffer()));
    }
}
