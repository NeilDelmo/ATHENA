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

export function safeAssistantSourceUrl(value) {
    if (typeof value !== 'string' || /[\u0000-\u0020\u007f]/.test(value)) return '';
    if (!/^https?:\/\//i.test(value)) return '';

    try {
        const url = new URL(value);
        return ['http:', 'https:'].includes(url.protocol) && !url.username && !url.password ? url.href : '';
    } catch {
        return '';
    }
}

function evidenceLink(source, showReference = false) {
    const url = safeAssistantSourceUrl(source?.url);
    if (!url) return '';

    const reference = String(source.reference ?? '').replace(/^\[|\]$/g, '').trim();
    const title = String(source.title || reference || 'Saved evidence');
    return `<a href="${escapeAssistantHtml(url)}" target="_blank" rel="noopener noreferrer" class="assistant-evidence-link inline-flex min-h-11 items-center gap-2 rounded-lg border px-3 py-2" aria-label="Open evidence: ${escapeAssistantHtml(title)}">Open evidence${showReference ? `<span class="assistant-evidence-reference">${escapeAssistantHtml(reference)}</span>` : ''}</a>`;
}

function formatAssistantInlineMarkdown(value, sources) {
    const tokens = /`([^`\n]+)`|\[([^\]\n]+)\]\(([^\s)]+)\)|\[([^\]\n]+)\]|\*\*([^*\n]+)\*\*|__([^_\n]+)__|\*([^*\n]+)\*/g;
    const text = String(value ?? '');
    let html = '';
    let cursor = 0;

    for (const match of text.matchAll(tokens)) {
        html += escapeAssistantHtml(text.slice(cursor, match.index));
        const [, code, label, link, reference, strong, alternateStrong, emphasis] = match;
        if (code !== undefined) {
            html += `<code>${escapeAssistantHtml(code)}</code>`;
        } else if (label !== undefined) {
            const url = safeAssistantSourceUrl(link);
            html += url ? `<a href="${escapeAssistantHtml(url)}" target="_blank" rel="noopener noreferrer" class="assistant-inline-link">${escapeAssistantHtml(label)}</a>` : escapeAssistantHtml(match[0]);
        } else if (reference !== undefined) {
            const index = sources.findIndex(source => String(source?.reference ?? '').replace(/^\[|\]$/g, '').trim() === reference.trim());
            const source = sources[index];
            const label = source?.title || (/^ATHENA\s+\d+$/i.test(reference.trim()) ? 'Source' : reference.trim());
            html += source ? `<button type="button" class="assistant-citation" data-assistant-citation="${index}" aria-haspopup="dialog" aria-expanded="false" aria-label="View source: ${escapeAssistantHtml(source.title || reference)}">${escapeAssistantHtml(label)}</button>` : escapeAssistantHtml(match[0]);
        } else if (strong !== undefined || alternateStrong !== undefined) {
            html += `<strong>${formatAssistantInlineMarkdown(strong ?? alternateStrong, sources)}</strong>`;
        } else {
            html += `<em>${formatAssistantInlineMarkdown(emphasis, sources)}</em>`;
        }
        cursor = match.index + match[0].length;
    }

    return html + escapeAssistantHtml(text.slice(cursor));
}

function listMarker(line) {
    const match = line.match(/^( *)([-+*]|\d+[.)])( +)(.+)$/);
    if (!match) return null;
    return { indent: match[1].length, contentIndent: match[1].length + match[2].length + match[3].length, type: /^\d/.test(match[2]) ? 'ol' : 'ul', start: parseInt(match[2], 10), content: match[4] };
}

function startsBlock(line) {
    return listMarker(line) || /^\s*(?:#{1,6}\s|```|>|---+\s*$|\*\*\*+\s*$)/.test(line);
}

function renderList(lines, startIndex, sources) {
    const first = listMarker(lines[startIndex]);
    const items = [];
    let index = startIndex;

    while (index < lines.length) {
        const marker = listMarker(lines[index]);
        if (!marker || marker.indent !== first.indent || marker.type !== first.type) break;
        const body = [marker.content];
        let afterBlank = false;
        index++;
        while (index < lines.length) {
            const line = lines[index];
            const nextMarker = listMarker(line);
            if (nextMarker && nextMarker.indent <= first.indent) break;
            if (!line.trim()) {
                body.push('');
                afterBlank = true;
                index++;
                continue;
            }
            const indent = line.match(/^ */)[0].length;
            if (indent <= first.indent && (afterBlank || startsBlock(line))) break;
            body.push(line.slice(Math.min(indent, marker.contentIndent)));
            afterBlank = false;
            index++;
        }
        items.push(`<li>${renderAssistantBlocks(body, sources)}</li>`);
    }

    const start = first.type === 'ol' && first.start !== 1 ? ` start="${first.start}"` : '';
    return { html: `<${first.type}${start}>${items.join('')}</${first.type}>`, index };
}

function renderAssistantBlocks(lines, sources) {
    const blocks = [];
    let index = 0;

    while (index < lines.length) {
        const line = lines[index];
        if (!line.trim()) {
            index++;
            continue;
        }
        if (/^\s*```/.test(line)) {
            const code = [];
            index++;
            while (index < lines.length && !/^\s*```/.test(lines[index])) code.push(lines[index++]);
            if (index < lines.length) index++;
            blocks.push(`<pre><code>${escapeAssistantHtml(code.join('\n'))}</code></pre>`);
            continue;
        }
        const heading = line.match(/^\s*(#{1,6})\s+(.+?)\s*#*\s*$/);
        if (heading) {
            const level = Math.min(6, heading[1].length + 1);
            blocks.push(`<h${level}>${formatAssistantInlineMarkdown(heading[2], sources)}</h${level}>`);
            index++;
            continue;
        }
        if (/^\s*(?:---+|\*\*\*+)\s*$/.test(line)) {
            blocks.push('<hr>');
            index++;
            continue;
        }
        if (listMarker(line)) {
            const list = renderList(lines, index, sources);
            blocks.push(list.html);
            index = list.index;
            continue;
        }
        if (/^\s*>/.test(line)) {
            const quote = [];
            while (index < lines.length && /^\s*>/.test(lines[index])) quote.push(lines[index++].replace(/^\s*>\s?/, ''));
            blocks.push(`<blockquote>${renderAssistantBlocks(quote, sources)}</blockquote>`);
            continue;
        }
        const paragraph = [line.trim()];
        index++;
        while (index < lines.length && lines[index].trim() && !startsBlock(lines[index])) paragraph.push(lines[index++].trim());
        blocks.push(`<p>${formatAssistantInlineMarkdown(paragraph.join(' '), sources)}</p>`);
    }

    return blocks.join('');
}

export function formatAssistantMarkdown(value, sources = []) {
    const lines = String(value ?? '').replace(/\r\n?/g, '\n').replaceAll('\t', '    ').split('\n');
    const html = renderAssistantBlocks(lines, Array.isArray(sources) ? sources : []);
    return html ? `<div class="assistant-prose">${html}</div>` : '';
}

export function formatAssistantReview(review) {
    if (!review || typeof review !== 'object') return '';
    const statuses = { addressed: 'Already addressed', partly_addressed: 'Partly addressed', not_evidenced: 'Evidence missing', cannot_verify: 'Cannot verify', observed_mismatch: 'Needs attention', needs_attention: 'Needs attention' };
    const findings = (Array.isArray(review.findings) ? review.findings : []).filter(finding => finding && typeof finding === 'object').slice(0, 5);
    const summary = review.summary ? `<p class="assistant-review-summary">${escapeAssistantHtml(review.summary)}</p>` : '';

    return `<div class="assistant-prose assistant-review">${summary}${findings.map(finding => {
        const status = Object.hasOwn(statuses, finding.status) ? finding.status : 'cannot_verify';
        const evidence = (Array.isArray(finding.evidence) ? finding.evidence : []).filter(item => item && typeof item === 'object');
        const nextEdit = finding.next_edit ? `<div class="assistant-review-next-edit"><h4>Suggested next step</h4><p>${escapeAssistantHtml(finding.next_edit)}</p></div>` : '';
        const explanation = finding.explanation ? `<p class="assistant-review-explanation">${escapeAssistantHtml(finding.explanation)}</p>` : '';
        const supportingText = evidence.map(item => {
            const reference = String(item.reference ?? '').replace(/^\[|\]$/g, '').trim();
            const location = String(item.location ?? '');
            const kind = reference === 'Reviewer feedback'
                ? (/Faculty response/i.test(location) ? 'Your response' : /Previously reviewed fields/i.test(location) ? 'Proposal' : 'Reviewer request')
                : 'Proposal';
            const caption = `${kind}${reference && reference !== 'Reviewer feedback' ? ` · ${escapeAssistantHtml(reference)}` : ''}${location ? ` · <span class="assistant-evidence-location">${escapeAssistantHtml(location)}</span>` : ''}`;
            return `<figure class="assistant-review-evidence-item"><figcaption>${caption}</figcaption>${item.quote ? `<blockquote>${escapeAssistantHtml(item.quote)}</blockquote>` : ''}${evidenceLink(item)}</figure>`;
        }).join('');
        return `<section class="assistant-review-finding"><div class="assistant-review-narrative"><header class="assistant-review-heading"><h3>${escapeAssistantHtml(finding.title)}</h3><span class="assistant-review-status" data-review-status="${status}">${statuses[status]}</span></header>${nextEdit}${explanation}</div>${evidence.length ? `<details class="assistant-review-evidence"><summary>See supporting text (${evidence.length})</summary><div class="assistant-review-evidence-content">${supportingText}</div></details>` : ''}</section>`;
    }).join('')}</div>`;
}
