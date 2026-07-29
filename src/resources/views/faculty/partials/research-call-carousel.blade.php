@if ($researchCallCarouselItems->isNotEmpty())
    <section data-research-call-carousel class="relative isolate overflow-hidden rounded-3xl bg-slate-950 bg-cover bg-center bg-no-repeat" style="background-image: url('{{ asset('images/maingate.jpg') }}');" aria-label="Research call posters" aria-roledescription="carousel">
        <div class="pointer-events-none absolute inset-0 bg-white/35" aria-hidden="true"></div>
        <div class="pointer-events-none absolute inset-0" style="background: linear-gradient(90deg, rgba(255, 255, 255, 0.82) 0%, rgba(255, 255, 255, 0.62) 23%, rgba(255, 255, 255, 0.34) 50%, rgba(255, 255, 255, 0.62) 77%, rgba(255, 255, 255, 0.82) 100%);" aria-hidden="true"></div>

        <div data-research-call-viewport data-research-call-single-slide class="relative h-[13rem] overflow-hidden sm:h-[18rem]">
            @foreach ($researchCallCarouselItems as $carouselItem)
                <div data-research-call-slide class="group absolute left-1/2 top-1/2 inline-flex h-full w-auto cursor-zoom-in bg-transparent p-0 shadow-none transition-[transform,opacity,filter] duration-700 ease-[cubic-bezier(0.22,1,0.36,1)]" aria-hidden="{{ $loop->first ? 'false' : 'true' }}" aria-roledescription="slide" aria-label="{{ $loop->iteration }} of {{ $researchCallCarouselItems->count() }}">
                    <img src="{{ $carouselItem['url'] }}" alt="{{ $carouselItem['alt'] }}" data-research-call-poster-trigger class="h-full w-auto max-w-none cursor-zoom-in object-contain transition-transform duration-500 ease-out" loading="{{ $loop->first ? 'eager' : 'lazy' }}" decoding="async">
                    @if ($carouselItem['isResearchCall'] && $carouselItem['canSubmitProposal'])
                        <div data-research-call-submit-overlay class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center bg-slate-950/35 opacity-0 transition-all duration-300 group-hover:pointer-events-auto group-hover:opacity-100">
                            <a href="{{ route('faculty.proposal-drafts.create', ['research_call_id' => $carouselItem['researchCallId']]) }}" class="translate-y-3 cursor-pointer rounded-xl bg-red-600 px-4 py-2.5 text-xs font-black text-white opacity-0 shadow-lg shadow-red-950/30 transition duration-300 hover:scale-105 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-red-600 group-hover:translate-y-0 group-hover:opacity-100">Submit a proposal</a>
                        </div>
                    @endif
                </div>
            @endforeach

            @if ($researchCallCarouselItems->count() > 1)
                <button type="button" data-research-call-previous class="group absolute left-4 top-1/2 z-40 inline-flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/45 text-gray-700 shadow-md backdrop-blur-sm transition duration-300 hover:scale-110 hover:bg-white/75 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2" aria-label="Show previous research call poster">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" /></svg>
                </button>
                <button type="button" data-research-call-next class="group absolute right-4 top-1/2 z-40 inline-flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/45 text-gray-700 shadow-md backdrop-blur-sm transition duration-300 hover:scale-110 hover:bg-white/75 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2" aria-label="Show next research call poster">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" /></svg>
                </button>

                <div class="absolute bottom-3 left-1/2 z-40 flex -translate-x-1/2 items-center gap-2" role="group" aria-label="Choose a research call poster">
                    @foreach ($researchCallCarouselItems as $carouselItem)
                        <button type="button" data-research-call-indicator class="{{ $loop->first ? 'w-5 bg-red-600' : 'w-2 bg-white/80' }} h-2 rounded-full shadow-sm transition-all duration-300 hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2" aria-label="Show poster {{ $loop->iteration }}" aria-current="{{ $loop->first ? 'true' : 'false' }}"></button>
                    @endforeach
                </div>
            @endif
        </div>

        <div data-research-call-lightbox class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-950/55 p-4 backdrop-blur-[2px] sm:p-8" role="dialog" aria-modal="true" aria-label="Research call poster preview" aria-hidden="true" tabindex="-1">
            <div class="relative flex max-h-[86vh] max-w-[42rem] items-center justify-center overflow-hidden rounded-xl bg-slate-950/20 p-1 shadow-2xl">
                <img data-research-call-lightbox-image src="" alt="" class="max-h-[82vh] max-w-[40rem] transform-gpu object-contain">
            </div>
        </div>
    </section>
@endif
