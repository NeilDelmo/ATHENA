import { readFile } from 'node:fs/promises';
import { getDocument, Util, OPS } from 'pdfjs-dist/legacy/build/pdf.mjs';

const escapeXml = (text) => text.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;');
const task = getDocument({ data: new Uint8Array(await readFile(process.argv[2])), verbosity: 0, useSystemFonts: true });
const pdf = await task.promise;
const pages = [];
try {
    for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber++) {
        const page = await pdf.getPage(pageNumber);
        const viewport = page.getViewport({ scale: 1 });
        const content = await page.getTextContent();
        const lines = [];
        for (const item of content.items) {
            if (!item.str?.trim() || item.width <= 0) continue;
            const transform = Util.transform(viewport.transform, item.transform);
            const height = Math.hypot(transform[2], transform[3]);
            const x = transform[4], baseline = transform[5];
            const style = content.styles[item.fontName];
            const top = baseline - height * (style?.ascent || 0.8);
            let line = lines.find((line) => Math.abs(line.baseline - baseline) < 2);
            if (!line) { line = { baseline, top, bottom: top + height, words: [] }; lines.push(line); }
            line.top = Math.min(line.top, top);
            line.bottom = Math.max(line.bottom, top + height);
            line.words.push({ x, right: x + item.width, text: item.str });
        }
        const operators = await page.getOperatorList();
        let matrix = [1, 0, 0, 1, 0, 0];
        const stack = [], boxes = [];
        for (let index = 0; index < operators.fnArray.length; index++) {
            const operation = operators.fnArray[index], args = operators.argsArray[index];
            if (operation === OPS.save) stack.push([...matrix]);
            else if (operation === OPS.restore) matrix = stack.pop() || [1, 0, 0, 1, 0, 0];
            else if (operation === OPS.transform) matrix = Util.transform(matrix, args);
            else if (operation === OPS.constructPath && args[2]?.length === 4) {
                const bounds = args[2], transform = Util.transform(viewport.transform, matrix);
                const points = [[bounds[0], bounds[1]], [bounds[2], bounds[1]], [bounds[2], bounds[3]], [bounds[0], bounds[3]]]
                    .map(([x, y]) => [transform[0] * x + transform[2] * y + transform[4], transform[1] * x + transform[3] * y + transform[5]]);
                const left = Math.min(...points.map((p) => p[0])), right = Math.max(...points.map((p) => p[0]));
                const top = Math.min(...points.map((p) => p[1])), bottom = Math.max(...points.map((p) => p[1]));
                if (right - left > viewport.width * 0.65 && bottom - top > 4 && bottom - top < viewport.height * 0.95) {
                    boxes.push(`<box xMin="${left}" xMax="${right}" yMin="${top}" yMax="${bottom}"/>`);
                }
            }
        }
        const xml = lines.sort((a, b) => a.top - b.top).map((line) => {
            line.words.sort((a, b) => a.x - b.x);
            return `<line xMin="${line.words[0].x}" xMax="${Math.max(...line.words.map((w) => w.right))}" yMin="${line.top}" yMax="${line.bottom}">${line.words.map((w) => `<word>${escapeXml(w.text)}</word>`).join('')}</line>`;
        }).join('');
        pages.push(`<page width="${viewport.width}" height="${viewport.height}">${boxes.join('')}${xml}</page>`);
        page.cleanup();
    }
    process.stdout.write(`<document>${pages.join('')}</document>`);
} finally {
    await task.destroy();
}
