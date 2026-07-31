export function escapeAssistantHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

export function stripAssistantGroundingFooter(value) {
    const content = String(value ?? '').trim();
    const lines = content.split(/\r?\n/);
    const footerIndex = lines.findIndex((line) => line
        .replaceAll(/[#*_`>]/g, '')
        .trim()
        .replace(/:$/, '')
        .toLowerCase() === 'grounded with athena knowledge');

    if (footerIndex < 0) return content;

    const footerLines = lines
        .slice(footerIndex + 1)
        .map((line) => line.trim())
        .filter(Boolean);

    if (footerLines.length && !footerLines.every((line) => /\bATHENA\s+\d+\b/i.test(line))) {
        return content;
    }

    return lines.slice(0, footerIndex).join('\n').trim() || content;
}

function formatAssistantInlineMarkdown(value) {
    const codeSpans = [];
    let formatted = escapeAssistantHtml(value).replace(/`([^`\n]+)`/g, (_, code) => {
        const index = codeSpans.push(code) - 1;

        return `\uE000${index}\uE001`;
    });

    formatted = formatted
        .replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>')
        .replace(/__([^_\n]+)__/g, '<strong>$1</strong>')
        .replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g, '$1<em>$2</em>')
        .replace(/\[([^\]\n]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer" class="font-semibold text-red-700 underline decoration-red-300 underline-offset-2 hover:text-red-900 dark:text-red-300 dark:hover:text-red-200">$1</a>')
        .replace(/\uE000(\d+)\uE001/g, (_, index) => `<code class="rounded bg-gray-100 px-1 py-0.5 font-mono text-[0.9em] dark:bg-slate-800">${codeSpans[Number(index)]}</code>`)
        .trim();

    return formatted;
}

export function formatAssistantMarkdown(value) {
    const lines = String(value ?? '').replace(/\r\n?/g, '\n').split('\n');
    const blocks = [];
    let paragraph = [];
    let listType = null;
    let listItems = [];
    let codeFence = false;
    let codeLines = [];

    const flushParagraph = () => {
        if (!paragraph.length) return;

        blocks.push(`<p class="my-3 first:mt-0 last:mb-0">${formatAssistantInlineMarkdown(paragraph.join(' '))}</p>`);
        paragraph = [];
    };
    const flushList = () => {
        if (!listType || !listItems.length) return;

        const listClass = listType === 'ol'
            ? 'my-3 list-decimal space-y-1.5 pl-5 marker:font-semibold marker:text-gray-500 dark:marker:text-slate-400'
            : 'my-3 list-disc space-y-1.5 pl-5 marker:text-gray-500 dark:marker:text-slate-400';
        const items = listItems
            .map((item) => `<li class="pl-1">${formatAssistantInlineMarkdown(item)}</li>`)
            .join('');

        blocks.push(`<${listType} class="${listClass}">${items}</${listType}>`);
        listType = null;
        listItems = [];
    };
    const flushCode = () => {
        blocks.push(`<pre class="my-4 overflow-x-auto rounded-xl bg-slate-950 p-4 text-xs leading-6 text-slate-100"><code>${escapeAssistantHtml(codeLines.join('\n'))}</code></pre>`);
        codeLines = [];
    };

    lines.forEach((line) => {
        if (/^\s*```/.test(line)) {
            flushParagraph();
            flushList();

            if (codeFence) flushCode();
            codeFence = !codeFence;

            return;
        }

        if (codeFence) {
            codeLines.push(line);
            return;
        }

        if (!line.trim()) {
            flushParagraph();
            flushList();
            return;
        }

        const heading = line.match(/^\s*(#{1,4})\s+(.+?)\s*#*\s*$/);
        if (heading) {
            flushParagraph();
            flushList();
            const level = Math.min(4, heading[1].length + 1);
            const headingClass = level <= 2
                ? 'mb-2 mt-5 text-base font-black leading-6 text-gray-950 first:mt-0 dark:text-white'
                : 'mb-1.5 mt-4 text-sm font-black leading-6 text-gray-900 first:mt-0 dark:text-slate-100';

            blocks.push(`<h${level} class="${headingClass}">${formatAssistantInlineMarkdown(heading[2])}</h${level}>`);
            return;
        }

        if (/^\s*(?:---+|\*\*\*+)\s*$/.test(line)) {
            flushParagraph();
            flushList();
            blocks.push('<hr class="my-5 border-gray-200 dark:border-slate-700">');
            return;
        }

        const unorderedItem = line.match(/^\s*[-+*]\s+(.+)$/);
        const orderedItem = line.match(/^\s*\d+[.)]\s+(.+)$/);
        if (unorderedItem || orderedItem) {
            flushParagraph();
            const nextListType = orderedItem ? 'ol' : 'ul';

            if (listType && listType !== nextListType) flushList();
            listType = nextListType;
            listItems.push((orderedItem || unorderedItem)[1]);
            return;
        }

        const quote = line.match(/^\s*>\s?(.+)$/);
        if (quote) {
            flushParagraph();
            flushList();
            blocks.push(`<blockquote class="my-4 border-l-4 border-red-200 pl-4 italic text-gray-600 dark:border-red-900 dark:text-slate-300">${formatAssistantInlineMarkdown(quote[1])}</blockquote>`);
            return;
        }

        flushList();
        paragraph.push(line.trim());
    });

    flushParagraph();
    flushList();
    if (codeFence || codeLines.length) flushCode();

    return blocks.join('');
}
