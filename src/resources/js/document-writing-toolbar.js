export function documentWritingToolbar() {
    return {
        writingToolbarOpen: true,
        previewFullscreen: false,
        activeWritingField: null,

        closeWritingToolbar() {
            this.writingToolbarOpen = false;
            const toolbar = this.$el.closest?.('[data-proposal-workspace-toolbar]') || this.$el;
            this.$nextTick(() => toolbar.querySelector('[data-writing-toolbar-open]')?.focus({ preventScroll: true }));
        },

        showWritingToolbar() {
            this.writingToolbarOpen = true;
            const toolbar = this.$el.closest?.('[data-proposal-workspace-toolbar]') || this.$el;
            this.$nextTick(() => toolbar.querySelector('[data-writing-toolbar-close]')?.focus({ preventScroll: true }));
        },

        focusWritingField(event) {
            const target = event.target;
            if (target.closest('[data-proposal-workspace-toolbar]')) return;
            const field = target.closest('.proposal-semantic-field')?.previousElementSibling;
            if (field?.matches('textarea[data-semantic-editor]')) {
                this.activeWritingField = field;
            } else if (target.matches('input, select, textarea')) {
                this.activeWritingField = null;
            }
        },

        formatWriting(command) {
            if (!this.activeWritingField?.isConnected
                || !['bold', 'italic', 'underline', 'insertOrderedList', 'insertUnorderedList', 'undo', 'redo', 'removeFormat'].includes(command)) return;
            this.activeWritingField._semanticApi?.execute(command);
        },
    };
}
