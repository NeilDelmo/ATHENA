@props(['topic', 'reportLabel', 'panelId', 'formId', 'saveMethod', 'standalone' => false])

<x-proposal-writing-toolbar
    :toolbar-id="$formId.'-tools'"
    data-monitoring-writing-toolbar
    aria-label="{{ $reportLabel }} tools"
    {{ $attributes }}
>
    <x-slot:context>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <p class="proposal-writing-target">Editing: <span>{{ $reportLabel }}</span></p>
            <x-proposal-autosave-status />
        </div>
    </x-slot:context>

    <div class="proposal-writing-toolbar-row" role="toolbar" aria-label="{{ $reportLabel }} editing and preview">
        <div class="proposal-writing-buttons">
            <button type="button" @click="{{ $saveMethod }}()" :disabled="autoSaveInFlight || autoSaveBlocked || submitting">Save draft</button>
            <button
                type="button"
                data-proposal-preview-toggle
                @click="previewPaneOpen ? closeProposalPreview() : showProposalPreview()"
                :disabled="previewLoading || submitting"
                :aria-expanded="previewPaneOpen"
                aria-controls="{{ $panelId }}"
                x-text="previewPaneOpen ? 'Hide preview' : 'Preview paper'"
            ></button>
            <button
                type="submit"
                form="{{ $formId }}"
                :disabled="!submissionOpen || submitting || previewLoading"
                :title="!submissionOpen ? 'Official PDF preparation opens ' + submissionOpensAt : ''"
                class="!border-red-700 !bg-red-700 !text-white hover:!bg-red-800"
            >
                <span x-show="!submitting">Prepare official PDF</span>
                <span x-show="submitting" x-cloak>Preparing PDF…</span>
            </button>
            @if ($standalone)
                <a data-paper-cancel-exit href="{{ route('research.show', $topic) }}#project-monitoring" class="proposal-preview-download inline-flex items-center">Exit monitoring</a>
            @endif
        </div>
    </div>
    <p class="proposal-writing-hint">Changes save automatically. Open the paper preview to review your report.</p>
</x-proposal-writing-toolbar>

<button
    type="button"
    data-monitoring-preview-launcher
    x-show="!previewPaneOpen"
    x-cloak
    @click="showProposalPreview()"
    :disabled="previewLoading || submitting"
    aria-controls="{{ $panelId }}"
    :aria-expanded="previewPaneOpen"
    class="proposal-writing-preview-launcher"
>Preview paper</button>
