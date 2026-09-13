<x-app-layout>
    <x-slot name="header">
        <div class="space-y-3">
            <x-back-link href="{{ route('topics.show', $topic) }}">Back to project</x-back-link>
            <div>
                <p class="text-sm font-bold uppercase tracking-wider text-red-700 dark:text-red-300">{{ $topic->project_status }} project</p>
                <h2 class="mt-1 text-3xl font-black tracking-tight text-gray-950 dark:text-white">Journal Finder</h2>
                <p class="mt-2 text-base text-gray-600 dark:text-slate-300">{{ $topic->title }}</p>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-950 sm:p-6">
            <div class="flex items-start gap-4">
                <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.75 5.75A1.75 1.75 0 0 1 6.5 4h11A1.75 1.75 0 0 1 19.25 5.75v12.5A1.75 1.75 0 0 1 17.5 20h-11a1.75 1.75 0 0 1-1.75-1.75V5.75Z"></path><path stroke-linecap="round" d="M8 8h8M8 12h8M8 16h5"></path></svg>
                </span>
                <div>
                    <h3 class="text-lg font-bold text-gray-950 dark:text-white">Choose a suitable publishing venue</h3>
                    <p class="mt-1 text-base leading-7 text-gray-600 dark:text-slate-300">
                        ATHENA now focuses on discovering and comparing journals for this project. It does not create a publication record or claim that the paper has already been published.
                    </p>
                    @if ($topic->isCompletedProject())
                        <p class="mt-2 text-sm font-semibold text-gray-700 dark:text-slate-200">Research reporting is complete and remains archived. Journal searches do not alter those reports.</p>
                    @endif
                    @if (Auth::user()->isUsingWorkspace('research_head'))
                        <p class="mt-2 text-sm font-semibold text-red-700 dark:text-red-300">Research Head view: you can review and run recommendations without changing the project.</p>
                    @endif
                </div>
            </div>
        </section>

        <x-journal-finder
            :endpoint="route('research.dissemination.journals.search', $topic)"
            :initial-query="$topic->title"
            :initial-context="$projectAbstract"
            heading="Recommend journals for this completed project"
            description="The project title and report abstract are prefilled. Refine them to match the manuscript you intend to submit."
        />
    </div>
</x-app-layout>
