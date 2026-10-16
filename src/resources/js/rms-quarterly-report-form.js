export function rmsQuarterlyReportForm() {
    return {
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
