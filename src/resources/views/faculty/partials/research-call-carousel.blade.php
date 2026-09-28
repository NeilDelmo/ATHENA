@if ($researchCallCarouselItems->isNotEmpty())
    <section data-research-call-carousel class="relative isolate overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-label="Research Office announcements" aria-roledescription="carousel">
        <img src="{{ asset('images/cteb_building.png') }}" alt="" class="pointer-events-none absolute inset-0 h-full w-full object-cover opacity-[0.45] grayscale dark:opacity-[0.22]" aria-hidden="true">
        <div class="pointer-events-none absolute inset-0 bg-white/60 dark:bg-slate-950/70" aria-hidden="true"></div>

        <div data-research-call-viewport data-research-call-single-slide class="relative h-[18rem] overflow-hidden sm:h-[22rem]">
            @foreach ($researchCallCarouselItems as $carouselItem)
                <article data-research-call-slide class="group absolute left-1/2 top-1/2 h-full w-[calc(100%-6rem)] max-w-md cursor-zoom-in overflow-hidden bg-transparent p-0 text-gray-950 transition-[transform,opacity,filter] duration-700 ease-[cubic-bezier(0.22,1,0.36,1)] sm:w-[calc(100%-10rem)]" aria-hidden="{{ $loop->first ? 'false' : 'true' }}" aria-roledescription="slide" aria-label="{{ $loop->iteration }} of {{ $researchCallCarouselItems->count() }}">
                    <div class="relative h-full overflow-hidden bg-transparent">
                        <img src="{{ $carouselItem['url'] }}" alt="{{ $carouselItem['alt'] }}" data-research-call-poster-trigger class="relative z-[1] h-full w-full cursor-zoom-in object-contain transition-transform duration-500 ease-out" loading="{{ $loop->first ? 'eager' : 'lazy' }}" decoding="async">

                        @if ($carouselItem['isResearchCall'] && $carouselItem['canSubmitProposal'])
                            <div data-research-call-submit-overlay class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center bg-gray-950/55 opacity-0 backdrop-blur-[1px] transition-all duration-300 group-hover:pointer-events-auto group-hover:opacity-100">
                                <a href="{{ route('faculty.proposal-drafts.create') }}" class="translate-y-3 cursor-pointer rounded-xl bg-red-700 px-5 py-3 text-xs font-black text-white opacity-0 shadow-xl shadow-red-950/30 transition duration-300 hover:scale-105 hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-red-700 group-hover:translate-y-0 group-hover:opacity-100">Start a proposal</a>
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach

            @if ($researchCallCarouselItems->count() > 1)
                <button type="button" data-research-call-previous class="group absolute left-3 top-1/2 z-40 inline-flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full border border-gray-200 bg-white/95 text-gray-500 shadow-md backdrop-blur-sm transition duration-200 hover:scale-110 hover:border-red-200 hover:bg-red-50 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-900/95 dark:text-slate-300 dark:hover:border-red-800 dark:hover:bg-red-950/60 dark:hover:text-red-300 dark:focus:ring-offset-slate-900 sm:left-5" aria-label="Show previous announcement">
                    <svg class="h-5 w-5 transition-transform group-hover:-translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" /></svg>
                </button>
                <button type="button" data-research-call-next class="group absolute right-3 top-1/2 z-40 inline-flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full border border-gray-200 bg-white/95 text-gray-500 shadow-md backdrop-blur-sm transition duration-200 hover:scale-110 hover:border-red-200 hover:bg-red-50 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-900/95 dark:text-slate-300 dark:hover:border-red-800 dark:hover:bg-red-950/60 dark:hover:text-red-300 dark:focus:ring-offset-slate-900 sm:right-5" aria-label="Show next announcement">
                    <svg class="h-5 w-5 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" /></svg>
                </button>
            @endif
        </div>

        <div data-research-call-lightbox class="fixed inset-0 z-[100] hidden items-center justify-center bg-gray-950/75 p-4 backdrop-blur-sm sm:p-8" role="dialog" aria-modal="true" aria-label="Announcement preview" aria-hidden="true" tabindex="-1">
            <div class="relative flex max-h-[88vh] max-w-[54rem] items-center justify-center overflow-hidden rounded-2xl border border-white/10 bg-gray-950 p-2 shadow-2xl">
                <img data-research-call-lightbox-image src="" alt="" class="max-h-[84vh] max-w-full transform-gpu object-contain">
            </div>
        </div>
    </section>
@endif
