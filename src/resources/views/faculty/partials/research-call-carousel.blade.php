@if ($researchCallCarouselItems->isNotEmpty())
    <section data-research-call-carousel class="relative isolate overflow-hidden rounded-3xl bg-gray-950 text-white shadow-xl shadow-gray-950/10" aria-label="Research Office announcements" aria-roledescription="carousel">
        <img src="{{ asset('images/maingate.jpg') }}" alt="" class="pointer-events-none absolute inset-0 h-full w-full object-cover opacity-20 grayscale" aria-hidden="true">
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-gray-950 via-gray-950/90 to-red-950/90" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -right-20 -top-24 h-64 w-64 rounded-full border-[44px] border-white/[0.04]" aria-hidden="true"></div>

        <div class="relative flex items-end justify-between gap-4 border-b border-white/10 px-5 py-4 sm:px-6">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-red-300">Research Office Bulletin</p>
                <h3 class="mt-1 text-lg font-black tracking-tight">Calls and announcements</h3>
            </div>
            <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-gray-300">{{ $researchCallCarouselItems->count() }} live</span>
        </div>

        <div data-research-call-viewport data-research-call-single-slide class="relative h-[22rem] overflow-hidden sm:h-[28rem]">
            @foreach ($researchCallCarouselItems as $carouselItem)
                <article data-research-call-slide class="group absolute left-1/2 top-1/2 flex h-[88%] w-[calc(100%-2rem)] max-w-[48rem] cursor-zoom-in flex-col overflow-hidden rounded-2xl border border-white/10 bg-white p-2 text-gray-950 shadow-2xl transition-[transform,opacity,filter] duration-700 ease-[cubic-bezier(0.22,1,0.36,1)] sm:w-[calc(100%-7rem)]" aria-hidden="{{ $loop->first ? 'false' : 'true' }}" aria-roledescription="slide" aria-label="{{ $loop->iteration }} of {{ $researchCallCarouselItems->count() }}">
                    <div class="relative min-h-0 flex-1 overflow-hidden rounded-xl bg-gray-100">
                        <img src="{{ $carouselItem['url'] }}" alt="{{ $carouselItem['alt'] }}" data-research-call-poster-trigger class="h-full w-full cursor-zoom-in object-contain transition-transform duration-500 ease-out" loading="{{ $loop->first ? 'eager' : 'lazy' }}" decoding="async">

                        @if ($carouselItem['isResearchCall'] && $carouselItem['canSubmitProposal'])
                            <div data-research-call-submit-overlay class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center bg-gray-950/55 opacity-0 backdrop-blur-[1px] transition-all duration-300 group-hover:pointer-events-auto group-hover:opacity-100">
                                <a href="{{ route('faculty.proposal-drafts.create', ['research_call_id' => $carouselItem['researchCallId']]) }}" class="translate-y-3 cursor-pointer rounded-xl bg-red-700 px-5 py-3 text-xs font-black text-white opacity-0 shadow-xl shadow-red-950/30 transition duration-300 hover:scale-105 hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-red-700 group-hover:translate-y-0 group-hover:opacity-100">Start a proposal</a>
                            </div>
                        @endif
                    </div>

                    <div class="flex items-center justify-between gap-4 px-2 pb-1 pt-3">
                        <div class="min-w-0">
                            <p class="text-[9px] font-black uppercase tracking-[0.18em] text-red-700">{{ $carouselItem['isResearchCall'] ? 'Open research call' : 'General announcement' }}</p>
                            <p class="mt-0.5 truncate text-xs font-black text-gray-950">{{ $carouselItem['alt'] }}</p>
                        </div>
                        <span class="shrink-0 text-[10px] font-bold text-gray-400">Click to enlarge</span>
                    </div>
                </article>
            @endforeach

            @if ($researchCallCarouselItems->count() > 1)
                <button type="button" data-research-call-previous class="group absolute left-3 top-1/2 z-40 inline-flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full border border-white/15 bg-gray-950/80 text-white shadow-lg backdrop-blur transition duration-300 hover:scale-110 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-offset-2 focus:ring-offset-gray-950 sm:left-5" aria-label="Show previous announcement">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" /></svg>
                </button>
                <button type="button" data-research-call-next class="group absolute right-3 top-1/2 z-40 inline-flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full border border-white/15 bg-gray-950/80 text-white shadow-lg backdrop-blur transition duration-300 hover:scale-110 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-offset-2 focus:ring-offset-gray-950 sm:right-5" aria-label="Show next announcement">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" /></svg>
                </button>

                <div class="absolute bottom-2 left-1/2 z-40 flex -translate-x-1/2 items-center gap-2" role="group" aria-label="Choose an announcement">
                    @foreach ($researchCallCarouselItems as $carouselItem)
                        <button type="button" data-research-call-indicator class="{{ $loop->first ? 'w-5 bg-red-600' : 'w-2 bg-white/80' }} h-2 rounded-full shadow-sm transition-all duration-300 hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-offset-2 focus:ring-offset-gray-950" aria-label="Show announcement {{ $loop->iteration }}" aria-current="{{ $loop->first ? 'true' : 'false' }}"></button>
                    @endforeach
                </div>
            @endif
        </div>

        <div data-research-call-lightbox class="fixed inset-0 z-[100] hidden items-center justify-center bg-gray-950/75 p-4 backdrop-blur-sm sm:p-8" role="dialog" aria-modal="true" aria-label="Announcement preview" aria-hidden="true" tabindex="-1">
            <div class="relative flex max-h-[88vh] max-w-[54rem] items-center justify-center overflow-hidden rounded-2xl border border-white/10 bg-gray-950 p-2 shadow-2xl">
                <img data-research-call-lightbox-image src="" alt="" class="max-h-[84vh] max-w-full transform-gpu object-contain">
            </div>
        </div>
    </section>
@endif
