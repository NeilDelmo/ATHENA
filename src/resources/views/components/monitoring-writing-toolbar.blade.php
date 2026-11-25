@props(['topic', 'reportLabel', 'panelId', 'formId', 'saveMethod', 'standalone' => false, 'formatting' => false, 'figuresId' => null, 'tablesId' => null])

<x-proposal-writing-toolbar
    :toolbar-id="$formId.'-tools'"
    data-monitoring-writing-toolbar
    aria-label="{{ $reportLabel }} tools"
    {{ $attributes }}
>
    <x-slot:context>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
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
            @if ($figuresId)
                <a href="#{{ $figuresId }}" class="proposal-preview-download inline-flex items-center gap-1.5"><x-proposal-writing-icon name="image" />Figures</a>
            @endif
            @if ($tablesId)
                <a href="#{{ $tablesId }}" class="proposal-preview-download inline-flex items-center gap-1.5"><x-proposal-writing-icon name="table" />Tables</a>
            @endif
        </div>
    </div>
    @if ($formatting)
        <x-document-formatting-tools />
    @endif
</x-proposal-writing-toolbar>

@if ($standalone)
    <x-back-link fixed data-paper-cancel-exit x-show="!previewFullscreen" href="{{ route('research.show', $topic) }}#project-monitoring">Back to monitoring</x-back-link>
@endif
