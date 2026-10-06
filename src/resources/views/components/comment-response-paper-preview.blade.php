@props(['url'])

<div x-data="commentResponsePaperPreview(@js($url))" @comment-response-paper-view-change="setPaperExpanded($event.detail.expanded)" data-comment-response-preview data-comment-response-preview-url="{{ $url }}" {{ $attributes->class(['revision-pdf-viewer']) }}>
    <p x-show="!previewReady && !previewError" role="status" class="absolute inset-x-0 top-5 z-10 text-center text-sm text-gray-700 dark:text-slate-300">Loading Comment Response paper…</p>
    <div x-show="previewError" x-cloak role="alert" class="m-4 rounded-lg bg-red-50 p-4 text-base text-red-800">
        <p x-text="previewError"></p>
        <button type="button" @click="loadPaperPreview()" class="mt-3 rounded-lg bg-red-700 px-4 py-2 font-semibold text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">Try again</button>
    </div>
    <iframe x-ref="previewFrame" x-show="previewHtml" :srcdoc="previewHtml" @load="paperPreviewLoaded()" tabindex="-1" title="Comment Response paper" class="min-h-0 w-full flex-1 border-0 bg-white"></iframe>
    <footer x-show="previewExpanded && previewReady" x-cloak class="revision-reference-footer" role="group" aria-label="Comment Response preview zoom">
        <div data-revision-preview-zoom class="flex flex-wrap items-center gap-2">
            <button type="button" @click="decreaseProposalPreviewZoom()" :disabled="previewZoom <= 50" aria-label="Zoom out">−</button>
            <output x-text="`${previewZoom}%`" aria-live="polite"></output>
            <button type="button" @click="increaseProposalPreviewZoom()" :disabled="previewZoom >= 150" aria-label="Zoom in">+</button>
            <button type="button" @click="fitProposalPreview('page')" :aria-pressed="previewFit === 'page'">Fit page</button>
            <button type="button" @click="fitProposalPreview('width')" :aria-pressed="previewFit === 'width'">Fit width</button>
        </div>
    </footer>
</div>
