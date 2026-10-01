@props(['report', 'photo', 'photoIndex', 'figureNumber'])

@php($previewId = 'narrative-figure-'.$report->id.'-'.$photoIndex)

<figure class="space-y-3 py-2" data-report-figure="{{ $figureNumber }}">
    <button type="button" @click="$dispatch('open-modal', '{{ $previewId }}')" aria-haspopup="dialog" aria-label="Preview figure {{ $figureNumber }}" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-base font-semibold text-brand transition hover:border-brand hover:bg-red-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200 dark:hover:bg-red-950">
        <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.5-7.5 9.75-7.5 9.75 7.5 9.75 7.5-3.5 7.5-9.75 7.5S2.25 12 2.25 12Z"/><circle cx="12" cy="12" r="3"/></svg>
        Preview figure
    </button>
    <button type="button" @click="$dispatch('open-modal', '{{ $previewId }}')" aria-haspopup="dialog" aria-label="Preview figure {{ $figureNumber }} in full size" class="block w-full overflow-hidden rounded-lg border border-gray-200 bg-white p-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand dark:border-slate-700">
        <img src="{{ route('project-narrative-reports.photos.view', [$report, $photoIndex]) }}" alt="{{ $photo['caption'] ?? 'Report figure' }}" loading="lazy" class="mx-auto max-h-[32rem] w-full object-contain">
    </button>
    <figcaption class="break-words text-base leading-7 text-gray-700 dark:text-slate-200"><span class="font-bold text-brand dark:text-red-200">Figure {{ $figureNumber }}.</span> {{ $photo['caption'] ?? '' }}</figcaption>
</figure>

<x-modal :name="$previewId" maxWidth="6xl" focusable class="!z-[140]" data-narrative-figure-preview-modal>
    <template x-if="show">
        <section role="dialog" aria-modal="true" aria-labelledby="{{ $previewId }}-heading">
            <header class="flex items-center justify-between gap-4 border-b border-red-200 bg-red-50 px-5 py-4 dark:border-red-900 dark:bg-red-950/30">
                <h3 id="{{ $previewId }}-heading" class="text-xl font-bold text-brand dark:text-red-200">Figure {{ $figureNumber }} preview</h3>
                <button type="button" @click="$dispatch('close')" aria-label="Close figure preview" class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-lg border border-red-200 bg-white text-brand hover:bg-red-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand dark:border-red-800 dark:bg-slate-900 dark:text-red-200">
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
