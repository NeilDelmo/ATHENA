@props(['isHead' => false])

<section id="turnitin" data-turnitin-resource class="athena-readable mb-6 scroll-mt-36 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900 sm:p-6" aria-labelledby="turnitin-heading">
    <h2 id="turnitin-heading" class="text-xl font-semibold text-slate-950 dark:text-white"><a href="https://www.turnitin.com/" target="_blank" rel="noopener noreferrer" aria-label="Visit Turnitin (opens in a new tab)" class="hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-red-700">Turnitin</a></h2>
    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">ATHENA links to Turnitin and its official guidance; it does not submit documents or store similarity reports.</p>
    @if ($isHead)
        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">Use the Research Office account to review documents. Interpret matched passages and sources in context; the similarity score alone does not determine approval.</p>
    @endif
    <div class="mt-4 flex flex-wrap items-center gap-4">
        <a href="https://home.turnitin.com/" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-red-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-red-700">Open Turnitin <span aria-hidden="true">↗</span></a>
        <a href="https://guides.turnitin.com/hc/en-us/articles/28310712438029-Accessing-the-Similarity-Report-and-similarity-score-via-Turnitin-Website" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-red-700 underline underline-offset-4 dark:text-red-300">Read the report guide <span aria-hidden="true">↗</span></a>
        <a href="https://guides.turnitin.com/hc/en-us/articles/23962252386061-Logging-in-to-Feedback-Studio-or-Originality-Check" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-red-700 underline underline-offset-4 dark:text-red-300">View sign-in help <span aria-hidden="true">↗</span></a>
    </div>
    <p class="mt-3 text-xs leading-5 text-slate-500 dark:text-slate-400">Institutional access may be required for direct Turnitin sign-in.</p>
</section>
