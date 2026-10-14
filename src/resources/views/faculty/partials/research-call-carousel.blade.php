@if ($researchCallCarouselItems->isNotEmpty())
    <section data-research-call-carousel class="relative isolate overflow-hidden rounded-2xl bg-[#7A0019] text-white shadow-bubble-sm" aria-label="Research Office announcements" aria-roledescription="carousel">
        <h2 class="sr-only">Research Office announcements</h2>
        <img data-research-call-backdrop src="{{ asset('images/cteb_building.png') }}" alt="" class="pointer-events-none absolute inset-y-0 right-0 h-full w-[34%] object-cover mix-blend-multiply" aria-hidden="true">
        <div data-research-call-viewport data-research-call-single-slide class="relative">
            @foreach ($researchCallCarouselItems as $carouselItem)
                <article data-research-call-slide data-announcement-layout="banner" class="grid grid-cols-[minmax(0,1fr)_5rem] items-center gap-x-4 gap-y-4 p-5 sm:grid-cols-[minmax(0,1fr)_11rem] sm:gap-x-8 sm:px-7" @if (! $loop->first) hidden inert style="display: none" @endif aria-hidden="{{ $loop->first ? 'false' : 'true' }}" aria-roledescription="slide" aria-label="{{ $loop->iteration }} of {{ $researchCallCarouselItems->count() }}">
                    <div class="min-w-0 space-y-2">
                        <p class="text-xs font-semibold text-rose-100">Research Office</p>
                        <h3 class="max-w-2xl break-words text-lg font-semibold leading-snug tracking-tight text-white sm:text-2xl">{{ $carouselItem['alt'] }}</h3>
                        <p class="max-w-xl text-xs leading-5 text-rose-100 sm:text-sm">{{ $carouselItem['canSubmitProposal'] ? 'Prepare your proposal for this research call.' : 'Research activities and updates from the Research Office.' }}</p>
                    </div>
                    <button type="button" data-research-call-preview class="col-start-2 row-start-1 inline-flex max-w-full cursor-zoom-in justify-self-center rounded-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white sm:row-span-2" aria-label="Enlarge {{ $carouselItem['alt'] }}">
                        <img src="{{ $carouselItem['url'] }}" alt="{{ $carouselItem['alt'] }}" data-research-call-poster-trigger class="h-28 w-auto max-w-full object-contain shadow-xl ring-1 ring-[#B04C60] sm:h-40" loading="{{ $loop->first ? 'eager' : 'lazy' }}" decoding="async">
                    </button>
                    <div class="col-span-2 flex flex-wrap items-center gap-3 sm:col-span-1">
                        @if ($carouselItem['isResearchCall'] && $carouselItem['canSubmitProposal'])
                            <a href="{{ route('faculty.proposal-drafts.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-white px-3 py-2 text-xs font-semibold text-[#7A0019] transition-colors hover:bg-stone-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white sm:px-4">Start a proposal</a>
                        @endif
                        <a href="{{ route('research-calls.index') }}" class="inline-flex min-h-11 items-center text-xs font-semibold text-rose-100 underline underline-offset-4 transition-colors hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white">View research calls</a>
                    </div>
                </article>
            @endforeach
        </div>
        @if ($researchCallCarouselItems->count() > 1)
            <div class="relative flex items-center justify-between gap-3 border-t border-brand-soft px-5 pb-3 pt-2 sm:px-7">
                <p data-research-call-counter class="text-xs text-rose-100" aria-live="polite" aria-atomic="true">Announcement 1 of {{ $researchCallCarouselItems->count() }}</p>
                <div class="flex gap-2">
                    <button type="button" data-research-call-previous class="inline-flex h-11 w-11 items-center justify-center rounded-lg bg-[#650015] text-white hover:bg-brand-soft focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white" aria-label="Show previous announcement"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" /></svg></button>
                    <button type="button" data-research-call-next class="inline-flex h-11 w-11 items-center justify-center rounded-lg bg-[#650015] text-white hover:bg-brand-soft focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white" aria-label="Show next announcement"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" /></svg></button>
                </div>
            </div>
        @endif
        <div data-research-call-lightbox class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/85 p-4 sm:p-8" role="dialog" aria-modal="true" aria-label="Announcement preview" aria-hidden="true" tabindex="-1">
            <button type="button" data-research-call-lightbox-close class="absolute right-4 top-4 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">Close preview</button>
            <img data-research-call-lightbox-image src="" alt="" class="max-h-[82vh] max-w-full object-contain">
        </div>
    </section>
@endif
