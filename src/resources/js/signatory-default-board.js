export function signatoryDefaultBoard(config) {
    return {
        options: config.options,
        templates: config.templates,
        assignments: { ...config.assignments },
        savedAssignments: { ...config.savedAssignments },
        query: '',
        selectedKey: '',
        draggingKey: '',
        hoveringRole: '',
        message: '',

        get people() {
            const query = this.query.trim().toLowerCase();
            return Object.entries(this.options)
                .filter(([, person]) => `${person.name} ${person.position}`.toLowerCase().includes(query))
                .map(([key, person]) => ({ key, ...person }));
        },

        get dirty() {
            return Object.keys(this.savedAssignments)
                .some(role => (this.assignments[role] || '') !== this.savedAssignments[role]);
        },

        displayName(role) {
            return this.options[this.assignments[role]]?.name || this.templates[role].name;
        },

        assign(role, key) {
            if (!Object.hasOwn(this.templates, role) || !Object.hasOwn(this.options, key)) return false;
            this.assignments[role] = this.options[key].name === this.templates[role].name ? '' : key;
            this.message = `${this.options[key].name} assigned. Save defaults to apply your changes.`;
            return true;
        },

        startDrag(key, event) {
            this.draggingKey = key;
            event.dataTransfer.setData('application/x-athena-signatory', key);
            event.dataTransfer.setData('text/plain', this.options[key].name);
            event.dataTransfer.effectAllowed = 'copy';
        },

        drop(role, event) {
            const key = event.dataTransfer.getData('application/x-athena-signatory') || this.draggingKey;
            this.assign(role, key);
            this.draggingKey = '';
            this.hoveringRole = '';
        },

        reset() {
            this.assignments = { ...this.savedAssignments };
            this.selectedKey = '';
            this.message = 'Unsaved changes discarded.';
        },
    };
}
