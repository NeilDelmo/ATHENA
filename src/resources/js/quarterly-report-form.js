import { documentWritingToolbar } from './document-writing-toolbar.js';

export function quarterlyReportForm(config = {}) {
    return {
        ...documentWritingToolbar(),
        presentations: [], publications: [], seminars: [], manual_research: [], ip_assets: [], rowProofs: [], removedProofs: [], attachments: [],
        ...config,

        addActivity(section) {
            const bytes = crypto.getRandomValues(new Uint8Array(16));
            bytes[6] = (bytes[6] & 0x0f) | 0x40;
            bytes[8] = (bytes[8] & 0x3f) | 0x80;
            const hex = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
            const id = `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
            const fields = section === 'ip_assets'
                ? { title: '', type: '', inventors: '', date: '', remarks: '', evidence_url: '' }
                : section === 'manual_research'
                ? { title: '', duration: '', personnel: '', status: '' }
                : { event: '', research_title: '', date: '', place: '', personnel: '', project_id: '' };
            this[section].push({ id, ...fields });
        },
    };
}
