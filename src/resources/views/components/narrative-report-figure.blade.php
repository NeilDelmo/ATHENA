@props(['report', 'photo', 'photoIndex', 'figureNumber'])

@php($previewId = 'narrative-figure-'.$report->id.'-'.$photoIndex)

<figure class="space-y-3 py-2" data-report-figure="{{ $figureNumber }}">
    <button type="button" @click="$dispatch('open-modal', '{{ $previewId }}')" aria-haspopup="dialog" aria-label="Preview figure {{ $figureNumber }}" class="group relative block w-full overflow-hidden rounded-lg border border-slate-200 bg-slate-50 p-3 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700 dark:border-slate-700 dark:bg-slate-950">
        <span class="sr-only">Preview figure</span>
        <img src="{{ route('project-narrative-reports.photos.view', [$report, $photoIndex]) }}" alt="{{ $photo['caption'] ?? 'Report figure' }}" loading="lazy" class="mx-auto max-h-[26rem] w-full object-contain">
        <span aria-hidden="true" class="absolute bottom-3 right-3 rounded-md border border-slate-200 bg-white p-2 text-slate-500 group-hover:text-slate-900 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 3H3v5m13-5h5v5M3 16v5h5m13-5v5h-5" /></svg></span>
    </button>
    <figcaption class="break-words text-sm leading-6 text-slate-500 dark:text-slate-400"><span class="font-medium text-slate-700 dark:text-slate-200">Figure {{ $figureNumber }}.</span> {{ $photo['caption'] ?? '' }}</figcaption>
</figure>

<x-modal :name="$previewId" maxWidth="6xl" focusable class="!z-[140]" data-narrative-figure-preview-modal>
    <template x-if="show">
        <section role="dialog" aria-modal="true" aria-labelledby="{{ $previewId }}-heading">
            <header class="flex items-center justify-between gap-4 border-b border-slate-200 px-5 py-3 dark:border-slate-700">
                <h3 id="{{ $previewId }}-heading" class="text-base font-semibold text-slate-900 dark:text-white">Figure {{ $figureNumber }}</h3>
                <button type="button" @click="$dispatch('close')" aria-label="Close figure preview" class="monitoring-report-control">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg>
                </button>
            </header>
            <div class="max-h-[75dvh] space-y-4 overflow-auto p-5">
                <img src="{{ route('project-narrative-reports.photos.view', [$report, $photoIndex]) }}" alt="{{ $photo['caption'] ?? 'Report figure' }}" class="mx-auto h-auto max-w-full">
                <p class="break-words text-base leading-7 text-gray-700 dark:text-slate-200"><span class="font-bold">Figure {{ $figureNumber }}.</span> {{ $photo['caption'] ?? '' }}</p>
            </div>
        </section>
    </template>
</x-modal>
