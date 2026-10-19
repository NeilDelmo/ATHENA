@props(['name', 'title', 'description' => null, 'previewUrl', 'downloadUrl', 'viewerLabel' => 'Submitted report PDF', 'expanded' => false, 'modalName' => null, 'searchable' => false, 'initialPageExpression' => null, 'loadingLabel' => 'Loading submitted report…', 'previewUrlExpression' => null, 'downloadUrlExpression' => null, 'titleExpression' => null, 'descriptionExpression' => null, 'searchLabel' => 'Search manual'])

@php
    $configuration = ['pdfUrl' => $previewUrl, 'annotations' => [], 'canAnnotate' => false, 'fitWidth' => true, 'loadWhenVisible' => ! $expanded, 'firstPageOnly' => ! $expanded, 'wheelZoom' => $expanded, 'maxZoom' => $expanded ? 400 : 150, 'searchable' => $searchable && $expanded];
@endphp

<div @if ($previewUrlExpression || $initialPageExpression) x-data="pdfAnnotationWorkspace({ ...@js($configuration), @if($previewUrlExpression) pdfUrl: {{ $previewUrlExpression }}, @endif initialPage: {{ $initialPageExpression ?? 'null' }} })" @else x-data="pdfAnnotationWorkspace" @endif x-init="previewFit = '{{ $expanded ? 'width' : 'page' }}'" data-pdf-annotation-config='@json($configuration)' data-submitted-report-document @if ($expanded) data-submitted-report-preview @else data-submitted-report-paper :style="{ '--submitted-paper-ratio': previewPageRatio }" @endif @if ($searchable && $expanded) @pdf-preview-page.window="if ($event.detail.url === config.pdfUrl) goToPage($event.detail.page)" @keydown.ctrl.f.prevent="$refs.find.focus()" @keydown.meta.f.prevent="$refs.find.focus()" @endif class="proposal-preview-dock submitted-report-preview {{ $expanded ? 'proposal-preview-dock-expanded' : 'submitted-report-preview-thumbnail' }}">
    <section @if ($expanded) role="dialog" aria-modal="true" @else role="region" @endif aria-labelledby="{{ $name }}-heading">
        <header class="{{ $expanded ? 'proposal-preview-dock-heading' : 'sr-only' }}">
            <div class="min-w-0">
                <h3 id="{{ $name }}-heading" @if($titleExpression) x-text="{{ $titleExpression }}" @endif>{{ $title }}</h3>
                @if ($descriptionExpression)<p x-show="{{ $descriptionExpression }}" x-text="{{ $descriptionExpression }}"></p>@elseif ($description)<p>{{ $description }}</p>@endif
            </div>
            @if ($expanded)
                <button type="button" @click="$dispatch('close')" aria-label="Close PDF preview" class="shrink-0">
                    <svg class="proposal-preview-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                    <span>Close preview</span>
                </button>
            @endif
        </header>
        @if ($expanded)
            <div class="proposal-preview-dock-actions" @if ($searchable) role="search" aria-label="{{ $searchLabel }}" x-id="['pdf-find', 'pdf-page']" @endif>
                <a @if($downloadUrlExpression) :href="{{ $downloadUrlExpression }}" @else href="{{ $downloadUrl }}" @endif class="proposal-preview-download">
                    <svg class="proposal-preview-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m-4-4 4 4 4-4M5 16v4h14v-4" /></svg>
                    <span>Download PDF</span>
                </a>
                @if ($searchable)
                    <div class="proposal-preview-search">
                        <label :for="$id('pdf-find')" class="sr-only">{{ $searchLabel }}</label>
                        <svg class="proposal-preview-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="10.5" cy="10.5" r="6.5" /><path stroke-linecap="round" d="m16 16 4 4" /></svg>
                        <input :id="$id('pdf-find')" x-ref="find" data-manual-find type="search" x-model="searchQuery" @input.debounce.200ms="findText()" @keydown.enter.prevent="findText($event.shiftKey, true)" @keydown.escape.stop.prevent="searchQuery = ''; findText()" :disabled="loading || !!loadError" placeholder="{{ $searchLabel }}">
                    </div>
                    <button type="button" @click="findText(true, true)" :disabled="loading || !searchQuery.trim()" aria-label="Previous match" class="proposal-preview-icon-button">
                        <svg class="proposal-preview-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 15 6-6 6 6" /></svg>
                    </button>
                    <button type="button" @click="findText(false, true)" :disabled="loading || !searchQuery.trim()" aria-label="Next match" class="proposal-preview-icon-button">
                        <svg class="proposal-preview-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
                    </button>
                    <div class="flex items-center gap-2">
                        <label :for="$id('pdf-page')" class="text-sm text-slate-600 dark:text-slate-300">Page</label>
                        <input :id="$id('pdf-page')" data-manual-page type="number" min="1" :max="pageCount" :value="currentPage" @change="goToPage($event.target.value); $event.target.value = currentPage" @keydown.enter.prevent="goToPage($event.target.value); $event.target.value = currentPage" :disabled="loading || !!loadError" class="proposal-preview-page-input">
                        <span class="text-sm text-slate-600 dark:text-slate-300">of <span x-text="pageCount"></span></span>
                    </div>
                    <span x-show="searchQuery.trim()" x-text="searchStatus" data-manual-find-status role="status" class="basis-full text-sm text-slate-600 dark:text-slate-300"></span>
                @endif
            </div>
        @endif
        <div class="proposal-preview-paper" :aria-busy="loading">
            <p x-show="loading" role="status" class="absolute inset-x-0 top-0 z-10 text-center">{{ $loadingLabel }}</p>
            <div x-show="loadError" x-cloak role="alert" class="proposal-preview-error">
                <p x-text="loadError"></p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button x-show="viewerRefreshRequired" type="button" @click="window.location.reload()">Reload page</button>
                    <button x-show="!viewerRefreshRequired" type="button" @click="loadPdf()">Try again</button>
                    <a @if($downloadUrlExpression) :href="{{ $downloadUrlExpression }}" @else href="{{ $downloadUrl }}" @endif class="proposal-preview-download">Download PDF</a>
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
                <p class="submitted-report-wheel-hint">Scroll to read · Pinch or Ctrl + wheel to zoom</p>
            </footer>
        @endif
    </section>
</div>
