import { documentWritingToolbar } from './document-writing-toolbar.js';

export function quarterlyReportForm(config = {}) {
    return {
        ...documentWritingToolbar(),
        presentations: [], publications: [], seminars: [], manual_research: [], ip_assets: [], rowProofs: [], removedProofs: [], attachments: [],
        ...config,

        serialize({ formData }) {
            const details = { presentations: [], publications: [], seminars: [], manual_research: [], ip_assets: [], saved_evidence_captions: {}, remove_evidence: [], evidence_captions: [] };
            for (const [name, value] of [...formData.entries()]) {
                const row = /^(presentations|publications|seminars|manual_research|ip_assets)\[(\d+)\]\[([^\]]+)\]$/.exec(name);
                const caption = /^(saved_evidence_captions|evidence_captions)\[([^\]]+)\]$/.exec(name);
                if (row) {
                    const [, section, index, field] = row;
                    (details[section][index] ??= {})[field] = value;
                } else if (caption) {
                    details[caption[1]][caption[2]] = value;
                } else if (name === 'remove_evidence[]') {
                    details.remove_evidence.push(value);
                } else {
                    continue;
                }
                formData.delete(name);
            }
            formData.set('report_details_json', JSON.stringify(details));
            formData.set('evidence_file_count', String([...formData.values()].filter(value => value instanceof File && value.size > 0).length));
            formData.set('form_complete', '1');
        },

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
