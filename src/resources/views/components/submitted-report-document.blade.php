@props(['name', 'title', 'description' => null, 'previewUrl', 'downloadUrl', 'viewerLabel' => 'Submitted report PDF', 'expanded' => false, 'modalName' => null])

@php
    $configuration = ['pdfUrl' => $previewUrl, 'annotations' => [], 'canAnnotate' => false, 'fitWidth' => true, 'loadWhenVisible' => ! $expanded, 'firstPageOnly' => ! $expanded, 'wheelZoom' => $expanded, 'maxZoom' => $expanded ? 400 : 150];
@endphp

<div x-data="pdfAnnotationWorkspace" x-init="previewFit = '{{ $expanded ? 'width' : 'page' }}'" data-pdf-annotation-config='@json($configuration)' data-submitted-report-document @if ($expanded) data-submitted-report-preview @else data-submitted-report-paper :style="{ '--submitted-paper-ratio': previewPageRatio }" @endif class="proposal-preview-dock submitted-report-preview {{ $expanded ? 'proposal-preview-dock-expanded' : 'submitted-report-preview-thumbnail' }}">
    <section @if ($expanded) role="dialog" aria-modal="true" @else role="region" @endif aria-labelledby="{{ $name }}-heading">
        <header class="{{ $expanded ? 'proposal-preview-dock-heading' : 'sr-only' }}">
            <div class="min-w-0">
                <h3 id="{{ $name }}-heading">{{ $title }}</h3>
                @if ($description)<p>{{ $description }}</p>@endif
            </div>
            @if ($expanded)
                <button type="button" @click="$dispatch('close')" aria-label="Close PDF preview" class="shrink-0">Close preview</button>
            @endif
        </header>
        @if ($expanded)
            <div class="proposal-preview-dock-actions">
                <a href="{{ $downloadUrl }}" class="proposal-preview-download">Download PDF</a>
            </div>
        @endif
        <div class="proposal-preview-paper" :aria-busy="loading">
            <p x-show="loading" role="status" class="absolute inset-x-0 top-0 z-10 text-center">Loading submitted report…</p>
            <div x-show="loadError" x-cloak role="alert" class="proposal-preview-error">
                <p x-text="loadError"></p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button x-show="viewerRefreshRequired" type="button" @click="window.location.reload()">Reload page</button>
                    <button x-show="!viewerRefreshRequired" type="button" @click="loadPdf()">Try again</button>
                    <a href="{{ $downloadUrl }}" class="proposal-preview-download">Download PDF</a>
                </div>
            </div>
            <div x-ref="viewer" tabindex="{{ $expanded ? '0' : '-1' }}" @if (! $expanded) aria-hidden="true" @endif aria-label="{{ $viewerLabel }}" class="pdf-annotation-viewer revision-pdf-pages"></div>
            @if (! $expanded)
                <button type="button" data-report-paper-maximize x-show="!loadError" @click="$dispatch('open-modal', @js($modalName))" class="proposal-preview-paper-open" aria-label="Enlarge the paper preview" aria-haspopup="dialog"></button>
            @endif
        </div>
        @if ($expanded)
            <footer class="proposal-preview-dock-footer">
                <div class="proposal-preview-zoom" role="group" aria-label="Document zoom controls">
                    <button type="button" @click="setPaperZoom(previewZoom - 10)" :disabled="loading || !!loadError || previewZoom <= 50" aria-label="Zoom out">−</button>
                    <output x-text="`${previewZoom}%`" aria-live="polite">100%</output>
                    <button type="button" @click="setPaperZoom(previewZoom + 10)" :disabled="loading || !!loadError || previewZoom >= maxPaperZoom" aria-label="Zoom in">+</button>
                    <button type="button" @click="fitPaper('page')" :disabled="loading || !!loadError" :aria-pressed="previewFit === 'page'">Fit page</button>
                    <button type="button" @click="fitPaper('width')" :disabled="loading || !!loadError" :aria-pressed="previewFit === 'width'">Fit width</button>
                </div>
                <p class="submitted-report-wheel-hint">Scroll to read · Ctrl + wheel to zoom</p>
            </footer>
        @endif
    </section>
</div>
