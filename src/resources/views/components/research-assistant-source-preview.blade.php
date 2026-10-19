<x-modal name="assistant-source-preview" maxWidth="4xl" focusable class="!z-[150] sm:!py-[5vh]" data-assistant-source-preview x-effect="if (!show) $store.researchAssistant.sourcePreviewOpen = false">
    <template x-if="show && $store.researchAssistant.sourcePreview">
        <x-submitted-report-document name="assistant-source-preview" title="Source document" preview-url="" download-url="" viewer-label="Source PDF" loading-label="Loading source…" search-label="Search source" preview-url-expression="$store.researchAssistant.sourcePreview.pdfUrl" download-url-expression="$store.researchAssistant.sourcePreview.downloadUrl" title-expression="$store.researchAssistant.sourcePreview.title" description-expression="$store.researchAssistant.sourcePreview.description" initial-page-expression="$store.researchAssistant.sourcePreview.page" searchable expanded />
    </template>
</x-modal>
