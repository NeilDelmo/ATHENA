@if ($researchCallCarouselItems->isNotEmpty())
    <section data-research-call-carousel class="bg-white dark:bg-slate-900" aria-label="Research Office announcements" aria-roledescription="carousel">
        <div data-research-call-viewport data-research-call-single-slide>
            @foreach ($researchCallCarouselItems as $carouselItem)
                <article data-research-call-slide data-announcement-layout="compact" class="grid gap-5 p-5 sm:grid-cols-[10rem_minmax(0,1fr)] sm:gap-6 sm:p-6" @if (! $loop->first) hidden inert style="display: none" @endif aria-hidden="{{ $loop->first ? 'false' : 'true' }}" aria-roledescription="slide" aria-label="{{ $loop->iteration }} of {{ $researchCallCarouselItems->count() }}">
                    <div class="flex min-w-0 items-center justify-center rounded-lg bg-slate-100 p-3 dark:bg-slate-950">
                        <button type="button" data-research-call-preview class="inline-flex max-w-full cursor-zoom-in rounded-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#7A0019] dark:focus-visible:outline-red-300" aria-label="Enlarge {{ $carouselItem['alt'] }}">
                            <img src="{{ $carouselItem['url'] }}" alt="{{ $carouselItem['alt'] }}" data-research-call-poster-trigger class="h-40 w-auto max-w-full object-contain sm:h-44" loading="{{ $loop->first ? 'eager' : 'lazy' }}" decoding="async">
                        </button>
                    </div>
                    <div class="flex min-w-0 flex-col items-start justify-center">

                        <h3 class="max-w-2xl text-lg font-semibold leading-snug text-slate-950 dark:text-white sm:text-xl">{{ $carouselItem['alt'] }}</h3>
                        <p class="mt-2 max-w-xl text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $carouselItem['canSubmitProposal'] ? 'Check the requirements and key dates before preparing your proposal.' : 'View the poster for research activities and updates.' }}</p>
                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            @if ($carouselItem['isResearchCall'] && $carouselItem['canSubmitProposal'])
                                <a href="{{ route('faculty.proposal-drafts.create') }}" class="dashboard-action">Start a proposal</a>
                            @endif
                            <button type="button" data-research-call-preview class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#7A0019] dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">View full poster</button>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
        @if ($researchCallCarouselItems->count() > 1)
            <div class="flex items-center justify-between gap-3 border-t border-slate-100 px-5 py-3 dark:border-slate-800 sm:px-6">
                <p data-research-call-counter class="text-xs text-slate-500 dark:text-slate-400" aria-live="polite" aria-atomic="true">Announcement 1 of {{ $researchCallCarouselItems->count() }}</p>
                <div class="flex gap-2">
                    <button type="button" data-research-call-previous class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#7A0019] dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" aria-label="Show previous announcement"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" /></svg></button>
                    <button type="button" data-research-call-next class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#7A0019] dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" aria-label="Show next announcement"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" /></svg></button>
                </div>
            </div>
        @endif
        <div data-research-call-lightbox class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/85 p-4 sm:p-8" role="dialog" aria-modal="true" aria-label="Announcement preview" aria-hidden="true" tabindex="-1">
            <button type="button" data-research-call-lightbox-close class="absolute right-4 top-4 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">Close preview</button>
            <img data-research-call-lightbox-image src="" alt="" class="max-h-[82vh] max-w-full object-contain">
        </div>
    </section>
@endif
