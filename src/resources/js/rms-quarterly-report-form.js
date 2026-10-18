export function rmsQuarterlyReportForm() {
    return {
        section: null,
        openSection(section) {
            if (section === null || ['presentations', 'publications', 'ip_assets'].includes(section)) {
                this.section = section;
            }
        },
        serialize(event) {
            const rows = {};
            for (const input of event.currentTarget.elements) {
                const match = /^row_details\[([^\]]+)\]\[([^\]]+)\]$/.exec(input.name);
                if (!match || input.disabled) continue;
                const [, key, field] = match;
                (rows[key] ??= {})[field] = input.value;
                event.formData.delete(input.name);
            }
            event.formData.set('row_details_json', JSON.stringify(rows));
        },
    };
}
