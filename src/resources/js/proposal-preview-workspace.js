export function clampProposalPreviewPosition({ left, top, width, height, viewportWidth, viewportHeight, margin = 8 }) {
    return {
        left: Math.min(Math.max(margin, left), Math.max(margin, viewportWidth - width - margin)),
        top: Math.min(Math.max(margin, top), Math.max(margin, viewportHeight - height - margin)),
    };
}

export function initializePaperPagination(doc) {
    if (doc?.paperPagination) return doc.paperPagination;
    if (!doc?.body || typeof doc.querySelectorAll !== 'function' || !doc.defaultView?.getComputedStyle) return null;
    const sheets = [...doc.querySelectorAll('[data-paper-sheet], .assessment-page')];
    if (!sheets.length) return null;
    const win = doc.defaultView;
    const pageStyle = {};
    const breaks = [];
    const readRules = rules => {
        for (const rule of rules) {
            if (rule.type === 6 && !rule.selectorText?.includes(':')) {
                for (const property of ['size', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left']) {
                    const value = rule.style.getPropertyValue(property);
                    if (value) pageStyle[property] = value;
                }
            } else if (rule.selectorText && rule.style) {
                for (const direction of ['before', 'after']) {
                    if (['page', 'always', 'left', 'right'].includes(rule.style.getPropertyValue(`break-${direction}`))
                        || rule.style.getPropertyValue(`page-break-${direction}`) === 'always') breaks.push([rule.selectorText, direction]);
                }
            }
            if (rule.cssRules) readRules(rule.cssRules);
        }
    };
    for (const stylesheet of doc.styleSheets) {
        try { readRules(stylesheet.cssRules); } catch { /* Cross-origin font styles do not define the paper. */ }
    }
    const probe = doc.createElement('div');
    probe.style.cssText = 'position:absolute;visibility:hidden;height:0;pointer-events:none';
    doc.body.append(probe);
    const pixels = value => {
        probe.style.width = '';
        probe.style.width = value || '0px';
        return Number.parseFloat(win.getComputedStyle(probe).width) || 0;
    };
    const sizes = { a4: ['210mm', '297mm'], letter: ['8.5in', '11in'], legal: ['8.5in', '14in'] };
    const size = String(pageStyle.size || '').toLowerCase().split(/\s+/);
    let dimensions = sizes[size[0]] ? [...sizes[size[0]]] : size;
    if (size.includes('landscape') && dimensions.length === 2 && sizes[size[0]]) dimensions.reverse();
    const style = doc.createElement('style');
    style.dataset.paperPaginationStyle = '';
    style.textContent = `
        .paper-pagination-controls { display:block;position:sticky;top:0;z-index:100;width:max-content;margin:0 16px 0 auto;padding:7px 12px;border:1px solid #cbd5e1;border-radius:6px;background:#f8fafc;color:#475569;font:12px/1.3 system-ui,sans-serif; }
        @media screen {
            .paper-pagination-sheet { box-sizing:border-box!important;display:block;position:relative!important;width:var(--paper-width)!important;max-width:none!important;height:var(--paper-height)!important;min-height:0!important;margin:20px auto!important;padding:var(--paper-top) var(--paper-right) var(--paper-bottom) var(--paper-left)!important;overflow:hidden!important;background:white;box-shadow:0 1px 4px #0f172a26; }
            [data-paper-pagination-source] { position:absolute!important;left:-100000px!important;top:0!important;margin:0!important;visibility:hidden!important; }
            .paper-pagination-flow { box-sizing:content-box!important;display:block!important;width:var(--paper-content-width)!important;height:var(--paper-content-height)!important;column-width:var(--paper-content-width);column-gap:var(--paper-horizontal-margin);column-fill:auto; }
            .paper-pagination-fragment { display:flow-root;width:var(--paper-content-width); }
            .paper-pagination-flow img,.paper-pagination-fragment img { max-width:100%;max-height:calc(var(--paper-content-height) - 48px)!important;object-fit:contain; }
            .paper-pagination-flow p,.paper-pagination-flow td,.paper-pagination-fragment p,.paper-pagination-fragment td { overflow-wrap:anywhere!important; }
            .paper-pagination-flow [data-paper-break-before] { break-before:column!important; }
            .paper-pagination-flow [data-paper-break-after] { break-after:column!important; }
            .paper-pagination-flow .paper-allow-break { break-inside:auto!important;page-break-inside:auto!important; }
            .paper-pagination-continuation { margin-top:0!important;text-indent:0!important; }
            li.paper-pagination-continuation { list-style-type:none!important; }
            .paper-pagination-sheet > [data-paper-footer] { position:absolute!important;left:var(--paper-left)!important;right:var(--paper-right)!important;bottom:10px!important;margin:0!important; }
            .paper-pagination-number { position:absolute;right:var(--paper-right);bottom:10px;font:9pt 'Times New Roman',serif;color:#333; }
            .paper-legacy-page-number { display:none!important; }
        }
        @media print {
            .paper-pagination-controls,.paper-pagination-number,.paper-legacy-page-number,[data-paper-rendered] { display:none!important; }
            .paper-pagination-flow { display:contents!important; }
        }
    `;
    doc.head.append(style);
    const records = sheets.map(sheet => {
        const computed = win.getComputedStyle(sheet);
        const fixed = sheet.matches('.assessment-page, [data-paper-fixed]');
        const width = fixed ? sheet.offsetWidth : pixels(dimensions[0]) || Number.parseFloat(computed.width) || sheet.offsetWidth;
        const height = fixed ? sheet.offsetHeight : pixels(dimensions[1]) || Number.parseFloat(computed.minHeight) || sheet.offsetHeight || pixels('13in');
        const margins = ['top', 'right', 'bottom', 'left'].map(direction => fixed ? 0 : pixels(pageStyle[`margin-${direction}`]) || pixels(computed.getPropertyValue(`padding-${direction}`)));
        const [top, right, bottom, left] = margins;
        const nodes = [...sheet.childNodes];
        const footers = nodes.filter(node => node.nodeType === 1 && node.matches('[data-paper-footer]'));
        const content = nodes.filter(node => !footers.includes(node));
        const flow = fixed ? null : doc.createElement('div');
        if (flow) {
            flow.className = 'paper-pagination-flow';
            flow.append(...content);
            sheet.replaceChildren(flow, ...footers);
            sheet.setAttribute('data-paper-pagination-source', '');
            sheet.setAttribute('aria-hidden', 'true');
            for (const [selector, direction] of breaks) {
                try { flow.querySelectorAll(selector).forEach(element => element.setAttribute(`data-paper-break-${direction}`, '')); } catch { /* Ignore selectors unsupported by this browser. */ }
            }
            flow.querySelectorAll('table').forEach((table, index) => {
                table.dataset.paperTable = index;
                [...table.rows].forEach((row, rowIndex) => {
                    row.dataset.paperRow = rowIndex;
                    [...row.cells].forEach((cell, cellIndex) => { cell.dataset.paperCell = cellIndex; });
                });
            });
            flow.querySelectorAll('ol').forEach(list => {
                const items = [...list.children].filter(child => child.tagName === 'LI');
                const step = list.reversed ? -1 : 1;
                let value = list.hasAttribute('start') ? list.start : list.reversed ? items.length : 1;
                items.forEach(item => {
                    if (item.hasAttribute('value')) value = item.value;
                    item.dataset.paperListValue = value;
                    value += step;
                });
            });
        } else {
            sheet.setAttribute('data-paper-pagination-sheet', '');
        }
        sheet.classList.add('paper-pagination-sheet');
        const values = { width, height, top, right, bottom, left, 'content-width': Math.max(1, width - left - right), 'content-height': Math.max(1, height - top - bottom), 'horizontal-margin': left + right };
        for (const [property, value] of Object.entries(values)) sheet.style.setProperty(`--paper-${property}`, `${value}px`);
        sheet.querySelectorAll('footer span,footer p,[class*="-page-number"]').forEach(element => {
            if (/^Page\s+\d+\s+of\s+\d+$/i.test(element.textContent.trim())) element.classList.add('paper-legacy-page-number');
        });
        return { sheet, flow, nodes, content, footers, width, contentHeight: values['content-height'], gap: left + right, pages: [], locations: new WeakMap(), count: 1, start: 1 };
    });
    probe.remove();
    const toolbar = doc.createElement('output');
    toolbar.className = 'paper-pagination-controls';
    toolbar.setAttribute('aria-label', 'Document pages');
    doc.body.prepend(toolbar);
    let pages = [];
    const update = (page, layoutChanged = false) => {
        const changed = state.page !== page;
        state.page = page;
        toolbar.textContent = `Page ${page} of ${state.count}`;
        if (changed || layoutChanged) doc.dispatchEvent(new win.CustomEvent('paper-pagination-change', { detail: { page, count: state.count, layoutChanged } }));
    };
    const renderPages = record => {
        const { flow } = record;
        const origin = flow.getBoundingClientRect().left;
        const scaleWidth = record.sheet.getBoundingClientRect().width;
        const column = rect => Math.max(0, Math.min(record.count - 1, Math.floor((rect.left - origin + 0.1) / scaleWidth)));
        const boundaries = Array(record.count + 1);
        boundaries[0] = [flow, 0];
        boundaries[record.count] = [flow, flow.childNodes.length];
        const walker = doc.createTreeWalker(flow, win.NodeFilter.SHOW_ELEMENT | win.NodeFilter.SHOW_TEXT);
        const range = doc.createRange();
        let node;
        while ((node = walker.nextNode())) {
            if (node.nodeType === 1) {
                const rect = node.getClientRects()[0];
                if (!rect) continue;
                const page = column(rect);
                record.locations.set(node, page);
                if (!boundaries[page]) boundaries[page] = [node.parentNode, [...node.parentNode.childNodes].indexOf(node)];
            } else if (node.textContent.trim() && !node.parentElement.closest('svg,script,style')) {
                range.selectNodeContents(node);
                const rects = [...range.getClientRects()];
                if (!rects.length) continue;
                const first = column(rects[0]);
                const last = column(rects.at(-1));
                for (let page = first; page <= last; page++) {
                    if (boundaries[page]) continue;
                    let low = 0;
                    let high = node.length;
                    while (low < high) {
                        const middle = Math.floor((low + high) / 2);
                        range.setStart(node, middle);
                        range.setEnd(node, middle + 1);
                        if (column(range.getBoundingClientRect()) >= page) high = middle;
                        else low = middle + 1;
                    }
                    boundaries[page] = [node, low];
                }
            }
        }
        for (let page = boundaries.length - 2; page > 0; page--) boundaries[page] ||= boundaries[page + 1];
        for (let page = 0; page < record.count; page++) {
            range.setStart(...boundaries[page]);
            range.setEnd(...boundaries[page + 1]);
            let fragment = range.cloneContents();
            let ancestor = range.commonAncestorContainer;
            if (ancestor.nodeType === 3) ancestor = ancestor.parentElement;
            while (ancestor && ancestor !== flow) {
                const wrapper = ancestor.cloneNode(false);
                wrapper.append(fragment);
                fragment = wrapper;
                ancestor = ancestor.parentElement;
            }
            const sheet = record.sheet.cloneNode(false);
            sheet.removeAttribute('data-paper-sheet');
            sheet.removeAttribute('data-paper-pagination-source');
            sheet.removeAttribute('aria-hidden');
            sheet.setAttribute('data-paper-pagination-sheet', '');
            sheet.setAttribute('data-paper-rendered', '');
            const content = doc.createElement('div');
            content.className = 'paper-pagination-fragment';
            content.append(fragment);
            if (boundaries[page][0].nodeType === 3) content.querySelector('p')?.classList.add('paper-pagination-continuation');
            content.querySelectorAll('li[data-paper-list-value]').forEach(item => { item.value = Number(item.dataset.paperListValue); });
            if (boundaries[page][0].nodeType === 3 && boundaries[page][0].parentElement.closest('li')) content.querySelector('li')?.classList.add('paper-pagination-continuation');
            content.querySelectorAll('table[data-paper-table]').forEach(table => {
                const original = flow.querySelector(`table[data-paper-table="${table.dataset.paperTable}"]`);
                if (!table.querySelector(':scope > colgroup')) {
                    let columns = original.querySelector(':scope > colgroup')?.cloneNode(true);
                    if (!columns) {
                        columns = doc.createElement('colgroup');
                        const row = [...original.rows].reduce((widest, candidate) => !widest || candidate.cells.length > widest.cells.length ? candidate : widest, null);
                        for (const cell of row?.cells || []) {
                            for (let index = 0; index < cell.colSpan; index++) {
                                const col = doc.createElement('col');
                                col.style.width = `${(cell.getClientRects()[0]?.width || 0) * record.width / scaleWidth / cell.colSpan}px`;
                                columns.append(col);
                            }
                        }
                    }
                    table.prepend(columns);
                }
                // A continued cell keeps its column even when neighboring cells ended on an earlier page.
                for (const row of table.rows) {
                    const originalRow = original.rows[Number(row.dataset.paperRow)];
                    for (let index = 0; index < originalRow.cells.length; index++) {
                        if ([...row.cells].some(cell => Number(cell.dataset.paperCell) === index)) continue;
                        const next = [...row.cells].find(cell => Number(cell.dataset.paperCell) > index);
                        row.insertBefore(originalRow.cells[index].cloneNode(false), next || null);
                    }
                }
            });
            sheet.append(content, ...record.footers.map(footer => footer.cloneNode(true)));
            sheet.querySelectorAll('[id]').forEach(element => { element.dataset.paperOriginalId = element.id; element.removeAttribute('id'); });
            sheet.removeAttribute('id');
            record.pages.push(sheet);
        }
        record.sheet.after(...record.pages);
    };
    const state = {
        page: 1, count: 1, printing: false,
        go(value) {
            const page = Math.max(1, Math.min(this.count, Math.round(Number(value)) || 1));
            const sheet = pages[page - 1];
            if (sheet) win.scrollBy({ top: sheet.getBoundingClientRect().top - 20, behavior: 'instant' });
            update(page);
        },
        reveal(element) {
            let sheet = element.closest('[data-paper-pagination-sheet]');
            let target = element;
            if (!sheet || !pages.includes(sheet)) {
                const record = records.find(candidate => candidate.sheet.contains(element));
                if (!record) return;
                sheet = record.pages[record.locations.get(element) || 0];
                const section = element.dataset.proposalPreviewSection;
                target = element === record.sheet ? sheet
                    : section ? [...sheet.querySelectorAll('[data-proposal-preview-section]')].find(candidate => candidate.dataset.proposalPreviewSection === section)
                    : element.id ? [...sheet.querySelectorAll('[data-paper-original-id]')].find(candidate => candidate.dataset.paperOriginalId === element.id)
                    : null;
            }
            win.scrollBy({ top: (target || sheet).getBoundingClientRect().top - 20, behavior: 'instant' });
            this.sync();
        },
        sync() {
            if (this.printing || !pages.length) return;
            const line = win.innerHeight * .35;
            const index = pages.findIndex(sheet => sheet.getBoundingClientRect().bottom > line);
            update(index < 0 ? this.count : index + 1);
        },
        refresh() {
            if (this.printing) return;
            const previousTop = pages[this.page - 1]?.getBoundingClientRect().top;
            let start = 1;
            for (const record of records) {
                if (record.flow) {
                    record.pages.forEach(sheet => sheet.remove());
                    record.pages = [];
                    record.flow.querySelectorAll('tr,figure,section').forEach(element => {
                        if (element.offsetHeight > record.contentHeight) element.classList.add('paper-allow-break');
                    });
                    record.count = Math.max(1, Math.ceil((record.flow.scrollWidth + record.gap - 1) / record.width));
                    renderPages(record);
                } else record.pages = [record.sheet];
                record.start = start;
                start += record.count;
            }
            pages = records.flatMap(record => record.pages);
            this.count = pages.length;
            pages.forEach((sheet, index) => {
                sheet.dataset.paperPage = index + 1;
                sheet.setAttribute('aria-label', `Page ${index + 1} of ${this.count}`);
                if (sheet.hasAttribute('data-paper-rendered')) {
                    const number = doc.createElement('span');
                    number.className = 'paper-pagination-number';
                    number.textContent = `Page ${index + 1} of ${this.count}`;
                    sheet.append(number);
                }
            });
            const page = Math.min(this.page, this.count);
            if (Number.isFinite(previousTop)) win.scrollBy({ top: pages[page - 1].getBoundingClientRect().top - previousTop, behavior: 'instant' });
            update(page, true);
        },
    };
    win.addEventListener('beforeprint', () => {
        state.printing = true;
        records.forEach(record => {
            if (record.flow) {
                record.sheet.replaceChildren(...record.nodes);
                record.sheet.removeAttribute('aria-hidden');
            }
        });
    });
    win.addEventListener('afterprint', () => {
        records.forEach(record => {
            if (record.flow) {
                record.flow.append(...record.content);
                record.sheet.replaceChildren(record.flow, ...record.footers);
                record.sheet.setAttribute('aria-hidden', 'true');
            }
        });
        state.printing = false;
        state.refresh();
    });
    let refreshFrame;
    let scrollFrame;
    const schedule = () => {
        win.cancelAnimationFrame(refreshFrame);
        refreshFrame = win.requestAnimationFrame(() => state.refresh());
    };
    win.addEventListener('scroll', () => {
        win.cancelAnimationFrame(scrollFrame);
        scrollFrame = win.requestAnimationFrame(() => state.sync());
    }, { passive: true });
    doc.addEventListener('load', event => { if (event.target.tagName === 'IMG' && !event.target.closest('[data-paper-rendered]')) schedule(); }, true);
    if (doc.fonts?.status === 'loading') doc.fonts.ready.then(schedule);
    doc.fonts?.addEventListener?.('loadingdone', schedule);
    doc.paperPagination = state;
    state.refresh();
    return state;
}

export function proposalPreviewWorkspace() {
    return {
        previewPaneOpen: false,
        previewTab: 'edit',
        previewFullscreen: false,
        previewDragging: false,
        previewZoom: 100,
        previewFit: 'width',
        previewStale: false,
        previewRevision: 0,
        previewPage: 1,
        previewPageCount: 1,

        bindProposalPreviewPages(frame = this.$refs.previewFrame) {
            const doc = frame?.contentDocument;
            const pagination = initializePaperPagination(doc);
            if (!pagination) return;
            doc.querySelector('.paper-pagination-controls').style.display = 'none';
            if (frame.paperPaginationBinding?.document !== doc) {
                frame.paperPaginationBinding?.cleanup();
                const update = event => {
                    this.previewPage = pagination.page;
                    this.previewPageCount = pagination.count;
                    if (event.detail.layoutChanged) this.applyProposalPreviewZoom?.();
                };
                doc.addEventListener('paper-pagination-change', update);
                frame.paperPaginationBinding = { document: doc, cleanup: () => doc.removeEventListener('paper-pagination-change', update) };
            }
            this.previewPage = pagination.page;
            this.previewPageCount = pagination.count;
        },

        showProposalPreview() {
            this.previewPaneOpen = true;
            this.previewTab = 'preview';
            if ((!this.previewHtml || this.previewStale) && !this.previewLoading) this.generatePreview();
            this.$nextTick?.(() => this.applyProposalPreviewZoom());
        },

        closeProposalPreview() {
            this.stopProposalPreviewDrag();
            this.previewPaneOpen = false;
            this.previewFullscreen = false;
            this.previewTab = 'edit';
        },

        startProposalPreviewDrag(event) {
            if (
                this.previewFullscreen
                || window.innerWidth < 640
                || event.button !== 0
                || event.target?.closest?.('button, a, input, select, textarea, label')
            ) return;

            const panel = this.$refs.previewPanel;

            if (!panel) return;

            this.stopProposalPreviewDrag();

            const panelRect = panel.getBoundingClientRect();
            const startPointerX = event.clientX;
            const startPointerY = event.clientY;
            const startLeft = panelRect.left;
            const startTop = panelRect.top;

            Object.assign(panel.style, {
                bottom: 'auto',
                height: `${panelRect.height}px`,
                left: `${startLeft}px`,
                right: 'auto',
                top: `${startTop}px`,
                width: `${panelRect.width}px`,
            });

            const movePanel = (pointerEvent) => {
                const position = clampProposalPreviewPosition({
                    left: startLeft + pointerEvent.clientX - startPointerX,
                    top: startTop + pointerEvent.clientY - startPointerY,
                    width: panelRect.width,
                    height: panelRect.height,
                    viewportWidth: window.innerWidth,
                    viewportHeight: window.innerHeight,
                });

                panel.style.left = `${position.left}px`;
                panel.style.top = `${position.top}px`;
            };
            const finishDragging = () => this.stopProposalPreviewDrag();

            this.previewDragging = true;
            this.proposalPreviewDragCleanup = () => {
                window.removeEventListener('pointermove', movePanel);
                window.removeEventListener('pointerup', finishDragging);
                window.removeEventListener('pointercancel', finishDragging);
                this.previewDragging = false;
                this.proposalPreviewDragCleanup = null;
            };

            window.addEventListener('pointermove', movePanel);
            window.addEventListener('pointerup', finishDragging);
            window.addEventListener('pointercancel', finishDragging);
            event.preventDefault();
        },

        stopProposalPreviewDrag() {
            this.proposalPreviewDragCleanup?.();
        },

        constrainProposalPreviewToViewport() {
            const panel = this.$refs.previewPanel;

            if (!panel || this.previewFullscreen || window.innerWidth < 640 || !panel.style.left) return;

            const panelRect = panel.getBoundingClientRect();
            const position = clampProposalPreviewPosition({
                left: panelRect.left,
                top: panelRect.top,
                width: panelRect.width,
                height: panelRect.height,
                viewportWidth: window.innerWidth,
                viewportHeight: window.innerHeight,
            });

            panel.style.left = `${position.left}px`;
            panel.style.top = `${position.top}px`;
        },

        markProposalPreviewStale() {
            this.previewRevision += 1;
            if (this.previewHtml) this.previewStale = true;
        },

        setProposalPreviewZoom(value) {
            this.previewZoom = Math.max(50, Math.min(150, Math.round(Number(value) * 100) / 100 || 100));
            this.applyProposalPreviewZoom();
        },

        decreaseProposalPreviewZoom() {
            this.setProposalPreviewZoom(this.previewZoom - 10);
        },

        increaseProposalPreviewZoom() {
            this.setProposalPreviewZoom(this.previewZoom + 10);
        },

        fitProposalPreview(mode = 'page') {
            this.previewFit = mode;
            this.previewZoom = 100;
            this.applyProposalPreviewZoom();
        },

        bindProposalPreviewWheel(frame = this.$refs.previewFrame, isExpanded = () => this.previewFullscreen || (this.previewPaneOpen && this.$refs.previewPanel?.matches('.proposal-preview-pane')), onZoom = (amount) => this.setProposalPreviewZoom(this.previewZoom + amount), getZoom = () => this.previewZoom) {
            const previewDocument = frame?.contentDocument;
            if (typeof previewDocument?.addEventListener !== 'function' || frame.proposalPreviewWheelBinding?.document === previewDocument) return;
            this.unbindProposalPreviewWheel(frame);
            let wheelDelta = 0;
            let gestureZoom = null;
            let gestureScale = 1;
            const handler = (event) => {
                if (event.defaultPrevented || !event.ctrlKey || event.shiftKey || !event.deltaY || Math.abs(event.deltaX) > Math.abs(event.deltaY)) {
                    wheelDelta = 0;
                    return;
                }
                // The containing revision preview owns zoom, including PDFs inside its iframe.
                event.stopPropagation();
                if (!isExpanded()) return;
                event.preventDefault();
                if (gestureZoom !== null) return;
                if (event.deltaMode === 0 && Math.abs(event.deltaY) < 40) {
                    wheelDelta = 0;
                    onZoom(getZoom() * (Math.exp(-event.deltaY * .01) - 1));
                    return;
                }
                const units = event.deltaMode === 1 ? 16 : event.deltaMode === 2 ? frame.clientHeight || 800 : 1;
                if (Math.sign(wheelDelta) !== Math.sign(event.deltaY)) wheelDelta = 0;
                wheelDelta += event.deltaY * units;
                if (Math.abs(wheelDelta) < 40) return;
                onZoom(wheelDelta < 0 ? 10 : -10);
                wheelDelta = 0;
            };
            const start = (event) => {
                gestureZoom = null;
                if (event.defaultPrevented || !Number.isFinite(event.scale) || event.scale <= 0) return;
                event.stopPropagation();
                if (!isExpanded()) return;
                event.preventDefault();
                gestureZoom = getZoom();
                gestureScale = event.scale;
            };
            const change = (event) => {
                if (event.defaultPrevented || gestureZoom === null || !Number.isFinite(event.scale) || event.scale <= 0) return;
                event.stopPropagation();
                if (!isExpanded()) { gestureZoom = null; return; }
                event.preventDefault();
                onZoom(gestureZoom * event.scale / gestureScale - getZoom());
            };
            const end = (event) => {
                if (gestureZoom !== null && !event.defaultPrevented) {
                    event.stopPropagation();
                    event.preventDefault();
                }
                gestureZoom = null;
            };
            const handlers = { wheel: handler, gesturestart: start, gesturechange: change, gestureend: end };
            for (const [type, callback] of Object.entries(handlers)) {
                previewDocument.addEventListener(type, callback, { passive: false, capture: true });
            }
            frame.proposalPreviewWheelBinding = {
                document: previewDocument,
                cleanup() {
                    for (const [type, callback] of Object.entries(handlers)) previewDocument.removeEventListener(type, callback, { capture: true });
                },
            };
        },

        unbindProposalPreviewWheel(frame = this.$refs?.previewFrame) {
            frame?.proposalPreviewWheelBinding?.cleanup();
            if (frame) delete frame.proposalPreviewWheelBinding;
        },

        applyProposalPreviewZoom() {
            const frame = this.$refs.previewFrame;
            const previewDocument = frame?.contentDocument;
            const body = previewDocument?.body;
            const documentElement = previewDocument?.documentElement;

            if (!body || !documentElement) return;

            const pagination = previewDocument.paperPagination;
            const activeSheet = pagination && body.querySelector?.(`[data-paper-page="${pagination.page}"]`);
            const anchorLine = Number(frame.clientHeight || 0) * .35;
            const previousBounds = activeSheet?.getBoundingClientRect?.();
            const anchorFraction = previousBounds?.height ? (anchorLine - previousBounds.top) / previousBounds.height : null;
            body.style.zoom = '1';
            this.bindProposalPreviewPages?.(frame);
            body.style.overflowX = 'auto';
            body.style.marginInline = 'auto';
            documentElement.style.overflowX = 'auto';

            const viewportWidth = Number(frame.clientWidth || documentElement.clientWidth || 0);
            const paperWidth = Number(body.scrollWidth || body.offsetWidth || 0);
            const requestedScale = this.previewZoom / 100;
            let fitScale = viewportWidth > 0 && paperWidth > 0
                ? Math.min(1, viewportWidth / paperWidth)
                : 1;

            if (this.previewFit === 'page') {
                const sheet = body.querySelector?.('[data-paper-pagination-sheet]:not([hidden])')
                    || body.querySelector?.('.gad-page, .assessment-page, .sheet, .paper, [class$="-sheet"]')
                    || body.querySelector?.('main, article');
                const sheetStyle = sheet && frame.contentWindow?.getComputedStyle(sheet);
                const bodyStyle = frame.contentWindow?.getComputedStyle(body);
                // Continuous forms can span many printed pages. Fit one physical sheet.
                const pageHeight = sheet?.matches?.('[data-paper-pagination-sheet], .gad-page, .assessment-page')
                    ? sheet.offsetHeight
                    : Number.parseFloat(sheetStyle?.minHeight) || sheet?.offsetHeight || body.scrollHeight;
                const spacing = (Number.parseFloat(bodyStyle?.paddingTop) || 0)
                    + (Number.parseFloat(bodyStyle?.paddingBottom) || 0)
                    + (Number.parseFloat(bodyStyle?.marginTop) || 0)
                    + (Number.parseFloat(bodyStyle?.marginBottom) || 0);
                const viewportHeight = Number(frame.clientHeight || documentElement.clientHeight || 0);
                if (viewportHeight > 0 && pageHeight > 0) {
                    fitScale = Math.min(fitScale, viewportHeight / (pageHeight + spacing));
                }
            }

            body.style.zoom = String(requestedScale * fitScale);
            if (anchorFraction !== null) {
                const bounds = activeSheet.getBoundingClientRect();
                frame.contentWindow?.scrollBy?.({ top: bounds.top + anchorFraction * bounds.height - anchorLine, behavior: 'instant' });
                pagination.sync();
            }
        },

        proposalPreviewLoaded() {
            this.previewReady = Boolean(this.previewHtml);
            this.bindProposalPreviewWheel();
            this.applyProposalPreviewZoom();
        },

        toggleProposalPreviewFullscreen() {
            this.stopProposalPreviewDrag();
            this.previewFullscreen = !this.previewFullscreen;
            this.previewFit = 'width';
            this.previewZoom = 100;
            if (this.previewFullscreen) {
                this.previewPaneOpen = true;
                this.previewTab = 'preview';
            }
            this.$nextTick?.(() => this.applyProposalPreviewZoom());
        },
    };
}
