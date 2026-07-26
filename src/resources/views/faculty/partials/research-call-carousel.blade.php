@if ($researchCallCarouselItems->isNotEmpty())
    <section data-research-call-carousel class="relative isolate overflow-hidden rounded-3xl bg-slate-950 bg-cover bg-center bg-no-repeat" style="background-image: url('{{ asset('images/maingate.jpg') }}');" aria-label="Research call posters" aria-roledescription="carousel">
        <div class="pointer-events-none absolute inset-0 bg-white/35" aria-hidden="true"></div>
        <div class="pointer-events-none absolute inset-0" style="background: linear-gradient(90deg, rgba(255, 255, 255, 0.82) 0%, rgba(255, 255, 255, 0.62) 23%, rgba(255, 255, 255, 0.34) 50%, rgba(255, 255, 255, 0.62) 77%, rgba(255, 255, 255, 0.82) 100%);" aria-hidden="true"></div>

        <div data-research-call-viewport data-research-call-single-slide class="relative h-[13rem] overflow-hidden sm:h-[18rem]">
            @foreach ($researchCallCarouselItems as $carouselItem)
                <div data-research-call-slide class="group absolute left-1/2 top-1/2 h-[17rem] w-[11rem] cursor-zoom-in bg-transparent p-0 shadow-none transition-[transform,opacity,filter] duration-[350ms] ease-out sm:h-[24rem] sm:w-[16rem]" aria-hidden="{{ $loop->first ? 'false' : 'true' }}" aria-roledescription="slide" aria-label="{{ $loop->iteration }} of {{ $researchCallCarouselItems->count() }}">
                    <img src="{{ $carouselItem['url'] }}" alt="{{ $carouselItem['alt'] }}" data-research-call-poster-trigger class="h-full w-full cursor-zoom-in object-contain" loading="{{ $loop->first ? 'eager' : 'lazy' }}" decoding="async">
                    @if ($carouselItem['isResearchCall'] && $carouselItem['canSubmitProposal'])
                        <div class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center bg-slate-950/30 opacity-0 transition-opacity duration-300 group-hover:pointer-events-auto group-hover:opacity-100">
                            <a href="{{ route('faculty.proposal-drafts.create', ['research_call_id' => $carouselItem['researchCallId']]) }}" class="cursor-pointer rounded-xl bg-red-600 px-4 py-2.5 text-xs font-black text-white shadow-lg shadow-red-950/30 transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-red-600">Submit a proposal</a>
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
            @endif
        </div>

        <div data-research-call-lightbox class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-950/55 p-4 backdrop-blur-[2px] sm:p-8" role="dialog" aria-modal="true" aria-label="Research call poster preview" aria-hidden="true" tabindex="-1">
            <div class="relative flex max-h-[86vh] max-w-[42rem] items-center justify-center overflow-hidden rounded-xl bg-slate-950/20 p-1 shadow-2xl">
                <button type="button" data-research-call-lightbox-close class="absolute right-2 top-2 z-10 inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-slate-800 shadow-lg transition hover:scale-110 hover:bg-white focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-slate-950" aria-label="Close poster preview">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 6 12 12M18 6 6 18" /></svg>
                </button>
                <img data-research-call-lightbox-image src="" alt="" class="max-h-[82vh] max-w-[40rem] object-contain">
            </div>
        </div>
    </section>
@endif
