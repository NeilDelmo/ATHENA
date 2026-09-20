@props(['isHead' => false])

<section id="turnitin" data-turnitin-resource class="athena-readable mb-6 scroll-mt-36 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="turnitin-heading">
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 px-6 py-5 sm:px-8">
        <a href="https://www.turnitin.com/" target="_blank" rel="noopener noreferrer" aria-label="Visit Turnitin (opens in a new tab)" class="inline-flex items-center gap-3">
            <img src="https://in.turnitin.com/assets/images/shared-assets-1/product-logos/logo-tii.svg" alt="Turnitin" width="150" height="45" class="h-10 w-auto" onerror="this.hidden = true; this.nextElementSibling.hidden = false">
            <span hidden class="text-3xl font-bold tracking-tight text-[#003c46]">turnitin</span>
        </a>
        <span class="text-xs font-semibold tracking-wide text-slate-500">{{ $isHead ? 'RESEARCH OFFICE / SIMILARITY CHECK' : 'RESEARCH HELP FACILITY / SIMILARITY CHECK' }}</span>
    </div>
    <div class="grid lg:grid-cols-[1.1fr_1fr]">
        <div class="flex flex-col justify-center px-6 py-10 sm:px-10 sm:py-14">
            <p class="mb-5 text-sm font-semibold text-[#087e83]">{{ $isHead ? 'For the Research Head' : 'For faculty and faculty researchers' }}</p>
            <h3 id="turnitin-heading" class="max-w-xl text-4xl font-semibold leading-[1.08] tracking-tight text-[#003c46] sm:text-5xl">Original research.<br><span class="text-[#087e83]">Confident submission.</span></h3>
            <p class="mt-6 max-w-lg text-base leading-7 text-slate-600">{{ $isHead ? 'Review documents directly in Turnitin using the Research Office account. ATHENA provides access to the resource but does not submit documents to Turnitin or retrieve its results.' : 'Open Turnitin to review matched sources, improve attribution, and understand the similarity report before submitting your research.' }}</p>
            <div class="mt-8 flex flex-wrap items-center gap-4">
                @if ($isHead)
                    <a href="https://home.turnitin.com/" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-12 items-center justify-center gap-3 rounded-lg bg-[#003c46] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#087e83] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700">Open Turnitin <span aria-hidden="true">↗</span></a>
                    <a href="https://guides.turnitin.com/hc/en-us/articles/28310712438029-Accessing-the-Similarity-Report-and-similarity-score-via-Turnitin-Website" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-12 items-center gap-2 px-1 text-sm font-bold text-[#003c46] underline decoration-slate-300 underline-offset-4 hover:decoration-teal-700">Read the report guide <span aria-hidden="true">↗</span></a>
                @else
                    <a href="https://home.turnitin.com/" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-12 items-center justify-center gap-3 rounded-lg bg-[#003c46] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#087e83] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700">Open Turnitin <span aria-hidden="true">↗</span></a>
                    <a href="https://guides.turnitin.com/hc/en-us/articles/28310712438029-Accessing-the-Similarity-Report-and-similarity-score-via-Turnitin-Website" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-12 items-center gap-2 px-1 text-sm font-bold text-[#003c46] underline decoration-slate-300 underline-offset-4 hover:decoration-teal-700">Read the report guide <span aria-hidden="true">↗</span></a>
                @endif
            </div>
            <p class="mt-4 text-xs leading-5 text-slate-500">{{ $isHead ? 'Similarity findings should be interpreted in context and should not be treated as an automatic approval or rejection.' : 'ATHENA links to Turnitin and its official guidance; it does not submit documents or store similarity reports.' }} Institutional access may be required for direct Turnitin sign-in.</p>
        </div>
        <div class="relative flex flex-col justify-center overflow-hidden bg-[#dff5ef] px-6 py-10 sm:px-10 sm:py-14">
            <div aria-hidden="true" class="pointer-events-none absolute -right-32 -top-32 h-80 w-80 rounded-full border-[48px] border-white/40"></div>
            <div class="relative">
                @if ($isHead)
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#087e83]">Using Turnitin during review</p>
                    <h4 class="mt-3 text-2xl font-semibold tracking-tight text-[#003c46]">Review externally.<br>Decide with context.</h4>
                    <ol class="mt-8 divide-y divide-teal-900/15">
                        <li class="flex gap-5 pb-6"><span class="text-3xl font-light text-[#087e83]" aria-hidden="true">01</span><div><h5 class="text-base font-bold text-[#003c46]">Open Turnitin</h5><p class="mt-1 text-sm leading-6 text-slate-600">Sign in through the institution's authorized Turnitin account.</p></div></li>
                        <li class="flex gap-5 py-6"><span class="text-3xl font-light text-[#087e83]" aria-hidden="true">02</span><div><h5 class="text-base font-bold text-[#003c46]">Examine the document</h5><p class="mt-1 text-sm leading-6 text-slate-600">Review matched passages, sources, exclusions, and the overall similarity score in Turnitin.</p></div></li>
                        <li class="flex gap-5 pt-6"><span class="text-3xl font-light text-[#087e83]" aria-hidden="true">03</span><div><h5 class="text-base font-bold text-[#003c46]">Use professional judgment</h5><p class="mt-1 text-sm leading-6 text-slate-600">Use the findings as supporting evidence when communicating citation or revision concerns.</p></div></li>
                    </ol>
                @else
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#087e83]">Your path to a reviewed document</p>
                    <h4 class="mt-3 text-2xl font-semibold tracking-tight text-[#003c46]">From submission<br>to a clearer next step.</h4>
                    <ol class="mt-8 divide-y divide-teal-900/15">
                        <li class="flex gap-5 pb-6"><span class="text-3xl font-light text-[#087e83]" aria-hidden="true">01</span><div><h5 class="text-base font-bold text-[#003c46]">Request your check</h5><p class="mt-1 text-sm leading-6 text-slate-600">Choose your submitted document in ATHENA.</p></div></li>
                        <li class="flex gap-5 py-6"><span class="text-3xl font-light text-[#087e83]" aria-hidden="true">02</span><div><h5 class="text-base font-bold text-[#003c46]">The office reviews it</h5><p class="mt-1 text-sm leading-6 text-slate-600">The Research Office processes the request and attaches the Turnitin result.</p></div></li>
                        <li class="flex gap-5 pt-6"><span class="text-3xl font-light text-[#087e83]" aria-hidden="true">03</span><div><h5 class="text-base font-bold text-[#003c46]">Read your report</h5><p class="mt-1 text-sm leading-6 text-slate-600">Download the result, examine matched passages, and review your citations.</p></div></li>
                    </ol>
                @endif
            </div>
        </div>
    </div>
    <div class="grid gap-6 border-t border-slate-200 bg-slate-50 px-6 py-6 sm:px-8 md:grid-cols-2">
        <div><h4 class="text-sm font-bold text-[#003c46]">Make sense of your results</h4><p class="mt-2 text-sm leading-6 text-slate-600">Use Turnitin's official guide to locate your Similarity Report and review matched sources.</p><a href="https://guides.turnitin.com/hc/en-us/articles/28310712438029-Accessing-the-Similarity-Report-and-similarity-score-via-Turnitin-Website" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex min-h-10 items-center text-sm font-bold text-[#087e83] underline underline-offset-4">Read the report guide ↗</a></div>
        <div><h4 class="text-sm font-bold text-[#003c46]">Need help getting access?</h4><p class="mt-2 text-sm leading-6 text-slate-600">Sign-in options depend on your institution. Check the official instructions for account and learning-platform access.</p><a href="https://guides.turnitin.com/hc/en-us/articles/23962252386061-Logging-in-to-Feedback-Studio-or-Originality-Check" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex min-h-10 items-center text-sm font-bold text-[#087e83] underline underline-offset-4">View sign-in help ↗</a></div>
    </div>
</section>
