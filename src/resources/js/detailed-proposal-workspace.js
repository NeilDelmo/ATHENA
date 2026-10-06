import { proposalPaperPreviewWorkspace } from './proposal-paper-workspace';
import { proposalCitationSelection } from './proposal-semantic-editor';

export function detailedProposalPreviewWorkspace() {
    return {
        ...proposalPaperPreviewWorkspace({ initiallyOpen: true, workspaceSelector: '[data-detailed-proposal-workspace]' }),
        initializeDetailedProposalPreview() { this.initializeProposalPaperPreview(); },
        scheduleDetailedProposalPreview(delay) { this.scheduleProposalPaperPreview(delay); },
        destroyDetailedProposalPreview() { this.destroyProposalPaperPreview(); },
        focusDetailedProposalPreview(fieldId) {
            this.focusProposalPaperPreview(fieldId?.startsWith('responsibility-duties-') ? 'responsibilities' : fieldId);
        },
    };
}

export function proposalTableHtml(rows, columns, header = true) {
    const rowCount = Math.max(1, Math.min(100, Number(rows) || 2));
    const columnCount = Math.max(1, Math.min(12, Number(columns) || 2));
    return `<table><tbody>${Array.from({ length: rowCount }, (_, row) => {
        const tag = row === 0 && header ? 'th' : 'td';
        return `<tr>${`<${tag}><p><br></p></${tag}>`.repeat(columnCount)}</tr>`;
    }).join('')}</tbody></table><p><br></p>`;
}

export function initializeDetailedProposalWritingTools(workspace, { openImagePicker, dropImages = () => {}, onActiveSection = () => {} }) {
    const toolbar = workspace.querySelector('[data-proposal-writing-toolbar]');
    if (!toolbar) return () => {};

    const target = toolbar.querySelector('[data-writing-target]');
    const status = toolbar.querySelector('[data-writing-status]');
    const tablePicker = toolbar.querySelector('[data-writing-table-picker]');
    const tableTools = toolbar.querySelector('[data-writing-table-tools]');
    let active = null;
    let activeImageSection = null;
    const imageSections = {
        'executive-brief': 'executive_brief', rationale: 'rationale', introduction: 'introduction',
        'related-literature': 'related_literature', 'methodology-research_design': 'research_design',
        'methodology-specific_methods': 'specific_methods', 'methodology-data_analysis': 'data_analysis',
    };

    const selectedTable = () => {
        const range = active?._semanticApi?.selection();
        const node = range?.startContainer;
        const cell = (node?.nodeType === Node.ELEMENT_NODE ? node : node?.parentElement)?.closest('td, th');
        return cell && active._semanticEditor.contains(cell) ? cell : null;
    };

    const update = () => {
        const available = Boolean(active?.isConnected && active._semanticApi);
        const range = available ? active._semanticApi.selection() : null;
        const canCite = available && active._semanticApi.canCite
            && proposalCitationSelection(active.id, active._semanticEditor, range);
        const canInsertTable = available && active.id !== 'references';
        toolbar.querySelectorAll('[data-writing-command]').forEach((button) => {
            button.disabled = !available;
            if (!['undo', 'redo'].includes(button.dataset.writingCommand)) {
                button.setAttribute('aria-pressed', String(available && document.queryCommandState(button.dataset.writingCommand)));
            }
        });
        toolbar.querySelector('[data-writing-image]').disabled = !activeImageSection;
        toolbar.querySelector('[data-writing-table]').disabled = !canInsertTable;
        toolbar.querySelector('[data-writing-cite]').disabled = !canCite;
        toolbar.querySelector('[data-writing-expand]').disabled = !available;
        const expanded = available && active._semanticEditor.parentElement.classList.contains('proposal-writing-expanded');
        toolbar.querySelector('[data-writing-expand-label]').textContent = expanded ? 'Shrink field' : 'Expand field';
        toolbar.querySelector('[data-writing-expand]').setAttribute('aria-pressed', String(expanded));
        tableTools.hidden = !selectedTable();
    };

    const activate = (field, imageSection = null) => {
        active = field;
        activeImageSection = field ? imageSections[field.id] : imageSection;
        workspace.querySelectorAll('.proposal-writing-active').forEach((element) => element.classList.remove('proposal-writing-active'));
        if (field) {
            const label = field.labels?.[0]?.textContent || field.getAttribute('aria-label') || field.name || 'Writing section';
            target.textContent = label.trim().replace(/\s+/g, ' ');
            field._semanticEditor?.parentElement.classList.add('proposal-writing-active');
            onActiveSection(field.id);
        } else {
            target.textContent = imageSection ? 'Specific Methods' : 'Select a writing field';
            if (imageSection) onActiveSection('methodology-specific_methods');
            tablePicker.hidden = true;
        }
        update();
    };

    const fieldAt = (element) => [...workspace.querySelectorAll('[data-semantic-editor]')]
        .find((field) => field._semanticEditor?.contains(element));
    const onFocus = (event) => {
        if (toolbar.contains(event.target)) return;
        const field = fieldAt(event.target);
        if (field) activate(field);
        else if (event.target.closest('#methodology-specific-methods')) activate(null, 'specific_methods');
        else if (event.target.matches('input, select, textarea')) activate(null);
    };
    const onSelection = () => {
        const selection = window.getSelection();
        const field = fieldAt(selection?.anchorNode);
        if (field) {
            active = field;
            field._semanticApi.saveSelection();
        }
        update();
    };
    const onPointerDown = (event) => {
        if (!event.target.closest('button')) return;
        active?._semanticApi?.saveSelection();
        event.preventDefault();
    };

    const replaceSelectedTable = (action) => {
        const cell = selectedTable();
        if (!cell) return;
        const table = cell.closest('table');
        const replacement = table.cloneNode(true);
        const rowIndex = cell.parentElement.rowIndex;
        const columnIndex = cell.cellIndex;
        if (action === 'add-row' && replacement.rows.length < 100) {
            const row = replacement.insertRow(rowIndex + 1);
            for (let column = 0; column < table.rows[0].cells.length; column++) row.insertCell().innerHTML = '<p><br></p>';
        } else if (action === 'add-column' && replacement.rows[0].cells.length < 12) {
            [...replacement.rows].forEach((row) => {
                const newCell = document.createElement(row.cells[columnIndex].tagName.toLowerCase());
                newCell.innerHTML = '<p><br></p>';
                row.cells[columnIndex].after(newCell);
            });
        } else if (action === 'remove-row') replacement.deleteRow(rowIndex);
        else if (action === 'remove-column') [...replacement.rows].forEach((row) => row.deleteCell(columnIndex));
        else if (action !== 'remove-table') {
            status.textContent = 'Tables support up to 100 rows and 12 columns.';
            return;
        }

        const range = document.createRange();
        range.selectNode(table);
        const selection = window.getSelection();
        active._semanticEditor.focus();
        selection.removeAllRanges();
        selection.addRange(range);
        const html = action === 'remove-table' || !replacement.rows.length || !replacement.rows[0].cells.length
            ? '<p><br></p>' : replacement.outerHTML;
        document.execCommand('insertHTML', false, html);
        active._semanticApi.saveSelection();
        active._semanticEditor.dispatchEvent(new Event('input', { bubbles: true }));
        status.textContent = 'Table updated.';
        update();
    };

    const onClick = (event) => {
        const button = event.target.closest('button');
        if (!button || button.disabled) return;
        if (button.hasAttribute('data-writing-sources')) {
            const selection = active?._semanticApi?.selection();
            const detail = proposalCitationSelection(active?.id, active?._semanticEditor, selection);
            if (detail) active._semanticCitationRange = selection.cloneRange();
            window.dispatchEvent(new CustomEvent('proposal-open-sources', { detail }));
            return;
        }
        if (button.hasAttribute('data-writing-image')) {
            openImagePicker(activeImageSection);
            return;
        }
        if (!active?._semanticApi) return;
        if (button.dataset.writingCommand) active._semanticApi.execute(button.dataset.writingCommand);
        if (button.hasAttribute('data-writing-cite')) active._semanticApi.cite();
        if (button.hasAttribute('data-writing-expand')) {
            active._semanticEditor.parentElement.classList.toggle('proposal-writing-expanded');
            active._semanticApi.restoreSelection();
            active._semanticEditor.focus();
        }
        if (button.hasAttribute('data-writing-table')) {
            tablePicker.hidden = !tablePicker.hidden;
            button.setAttribute('aria-expanded', String(!tablePicker.hidden));
            if (!tablePicker.hidden) tablePicker.querySelector('input').focus();
        }
        if (button.hasAttribute('data-writing-insert-table')) {
            const rows = tablePicker.querySelector('[data-table-rows]');
            const columns = tablePicker.querySelector('[data-table-columns]');
            if (!rows.reportValidity() || !columns.reportValidity()) return;
            active._semanticApi.execute('insertHTML', proposalTableHtml(rows.value, columns.value, tablePicker.querySelector('[data-table-header]').checked));
            tablePicker.hidden = true;
            toolbar.querySelector('[data-writing-table]').setAttribute('aria-expanded', 'false');
            status.textContent = `Table inserted in ${target.textContent}. Select a cell to edit rows and columns.`;
        }
        if (button.dataset.writingTableAction) replaceSelectedTable(button.dataset.writingTableAction);
        update();
    };
    const onKeydown = (event) => {
        if (event.key === 'Escape' && !tablePicker.hidden) {
            event.preventDefault();
            tablePicker.hidden = true;
            toolbar.querySelector('[data-writing-table]').setAttribute('aria-expanded', 'false');
            toolbar.querySelector('[data-writing-table]').focus();
        }
    };
    const onTableKeydown = (event) => {
        if (event.key !== 'Tab' || !fieldAt(event.target)) return;
        const cell = selectedTable();
        if (!cell) return;
        const cells = [...cell.closest('table').querySelectorAll('td, th')];
        const next = cells[cells.indexOf(cell) + (event.shiftKey ? -1 : 1)];
        if (!next) return;
        event.preventDefault();
        const range = document.createRange();
        range.selectNodeContents(next);
        range.collapse(true);
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
        active._semanticApi.saveSelection();
        update();
    };

    workspace.addEventListener('focusin', onFocus);
    workspace.addEventListener('input', update);
    const onDragover = (event) => {
        const field = fieldAt(event.target);
        if ((field && imageSections[field.id]) || event.target.closest('#methodology-specific-methods')) event.preventDefault();
    };
    const onDrop = (event) => {
        const field = fieldAt(event.target);
        const section = field ? imageSections[field.id] : event.target.closest('#methodology-specific-methods') ? 'specific_methods' : null;
        if (!section) return;
        event.preventDefault();
        activate(field, section);
        dropImages(event, section);
    };
    workspace.addEventListener('dragover', onDragover);
    workspace.addEventListener('drop', onDrop);
    workspace.addEventListener('keydown', onTableKeydown);
    toolbar.addEventListener('pointerdown', onPointerDown);
    toolbar.addEventListener('click', onClick);
    toolbar.addEventListener('keydown', onKeydown);
    document.addEventListener('selectionchange', onSelection);
    activate(null);

    return () => {
        workspace.removeEventListener('focusin', onFocus);
        workspace.removeEventListener('input', update);
        workspace.removeEventListener('dragover', onDragover);
        workspace.removeEventListener('drop', onDrop);
        workspace.removeEventListener('keydown', onTableKeydown);
        toolbar.removeEventListener('pointerdown', onPointerDown);
        toolbar.removeEventListener('click', onClick);
        toolbar.removeEventListener('keydown', onKeydown);
        document.removeEventListener('selectionchange', onSelection);
    };
}
