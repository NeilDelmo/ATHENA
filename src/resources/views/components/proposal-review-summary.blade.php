@props(['topic', 'version' => null, 'workspace' => null])

@php
    $reviews = $topic->reviews->where('decision', '!=', 'head_upload')->sortBy([['created_at', 'desc'], ['id', 'desc']])->values();
    $requiredFiles = $workspace['requiredSignatureFiles'] ?? collect();
    $signedIds = $workspace['signedSourceFileIds'] ?? collect();
    $signedCount = $requiredFiles->filter(fn ($file) => $signedIds->contains($file->id))->count();
    $released = $topic->hasIssuedNoticeToProceed();
@endphp

<div data-proposal-review-summary class="space-y-5">
    <header class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950">
        <div class="flex items-start gap-4 px-5 py-6 sm:px-6">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400" aria-hidden="true">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4" /><circle cx="12" cy="12" r="9" /></svg>
            </span>
            <div class="min-w-0">
                <h3 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $released ? 'Proposal released' : 'Review complete' }}</h3>
                <p class="mt-2 max-w-prose text-sm leading-6 text-slate-600 dark:text-slate-400">{{ $released ? 'The signed proposal package and Notice to Proceed have been released. The review record remains available below.' : 'LREC review is complete. The proposal is cleared for signing; collect the signed papers before releasing the package.' }}</p>
            </div>
        </div>
        <dl class="grid grid-cols-1 gap-4 border-t border-slate-100 bg-slate-50/70 px-5 py-4 text-sm dark:border-slate-800 dark:bg-slate-900/50 sm:grid-cols-3 sm:px-6">
            <div><dt class="text-xs text-slate-500 dark:text-slate-400">Latest submission</dt><dd class="mt-1 font-semibold text-slate-800 dark:text-slate-200">{{ $version ? 'Version '.$version->version_number : 'No submitted version' }}</dd></div>
            <div><dt class="text-xs text-slate-500 dark:text-slate-400">Review record</dt><dd class="mt-1 font-semibold text-slate-800 dark:text-slate-200">{{ $reviews->count() }} {{ str('decision')->plural($reviews->count()) }}</dd></div>
            <div><dt class="text-xs text-slate-500 dark:text-slate-400">Revision rounds</dt><dd class="mt-1 font-semibold text-slate-800 dark:text-slate-200">{{ $reviews->where('decision', 'revision_requested')->count() }} requested</dd></div>
        </dl>
    </header>

    <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_21rem]">
        <section aria-labelledby="review-timeline-heading" class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4 dark:border-slate-800 sm:px-6">
                <h3 id="review-timeline-heading" class="text-base font-bold text-slate-900 dark:text-white">Review timeline</h3>
                <span class="text-xs text-slate-500 dark:text-slate-400">Most recent first</span>
            </div>
            <ol data-review-timeline class="px-5 py-5 sm:px-6">
                @forelse ($reviews as $review)
                    @php
                        $decisionLabel = match ($review->decision) {
                            'ready_for_signature' => 'Cleared for signing',
                            'gad_review' => 'Cleared for GAD assessment',
                            'lrec_queued' => 'Sent to LREC',
                            'lrec_review' => 'LREC review opened',
                            'revision_requested' => 'Revision requested',
                            'approved' => 'Proposal approved',
                            'rejected' => 'Proposal rejected',
                            default => str($review->decision)->replace('_', ' ')->ucfirst(),
                        };
                    @endphp
                    <li class="relative flex gap-4 {{ $loop->last ? '' : 'pb-6' }}">
                        @unless ($loop->last)<span class="absolute bottom-0 left-[15px] top-8 w-px bg-slate-200 dark:bg-slate-800" aria-hidden="true"></span>@endunless
                        <span class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $loop->first ? 'bg-brand text-white' : 'border border-slate-200 bg-white text-slate-400 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-500' }}" aria-hidden="true">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ match ($review->decision) { 'revision_requested' => 'M9 10 5 6l4-4M5 6h8a6 6 0 0 1 0 12h-2', 'rejected' => 'm6 6 12 12M6 18 18 6', default => 'm5 12 4 4L19 6' } }}" /></svg>
                        </span>
                        <div class="min-w-0 flex-1 pt-1">
                            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                <h4 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $decisionLabel }}</h4>
                                <time datetime="{{ $review->created_at->toIso8601String() }}" class="text-xs text-slate-500 dark:text-slate-400">{{ $review->created_at->format('M j, Y') }}</time>
                            </div>
                            <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $review->reviewer?->name ?? 'Former reviewer' }} <span aria-hidden="true">&middot;</span> {{ match ($review->review_stage) { 'lrec' => 'LREC review', 'gad' => 'GAD / Co-evaluator review', default => 'Research Head review' } }}</p>
                            @if ($review->comment)<p class="mt-2 whitespace-pre-line break-words text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $review->comment }}</p>@endif
                            @if ($review->decision === 'revision_requested')
                                <p class="mt-2 text-xs leading-5 text-slate-600 dark:text-slate-300">{{ $review->fileRevisions->count() }} {{ str('paper')->plural($review->fileRevisions->count()) }} marked for revision. Feedback and faculty responses are in the full record.</p>
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="text-sm leading-6 text-slate-500 dark:text-slate-400">No review decisions have been recorded. The proposal’s current status is shown above.</li>
                @endforelse
            </ol>
            <div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800 sm:px-6">
                <button type="button" @click="setTopicTab('history', 'version-history')" class="inline-flex min-h-11 items-center gap-2 rounded-lg text-xs font-semibold text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:text-red-300">View full decisions and version history <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" /></svg></button>
            </div>
        </section>

        <aside class="min-w-0 space-y-5" aria-label="Proposal next steps">
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950">
                <div class="border-t-4 border-brand px-5 pb-5 pt-4">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ $released ? 'Released documents' : 'Signing & release' }}</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-400">{{ $released ? 'Open the signed papers and the issued Notice to Proceed.' : 'Upload the signed proposal papers, then prepare the Notice to Proceed.' }}</p>
                    @if ($requiredFiles->isNotEmpty())
                        <div class="mt-5 flex items-center justify-between gap-3 text-xs"><span class="font-semibold text-slate-700 dark:text-slate-200">Signed papers</span><span data-review-signature-count class="font-semibold tabular-nums text-slate-500 dark:text-slate-400">{{ $signedCount }} of {{ $requiredFiles->count() }}</span></div>
                        <ul class="mt-3 space-y-3" data-review-signature-checklist>
                            @foreach ($requiredFiles as $file)
                                <li class="flex items-start gap-2.5 text-xs leading-5 text-slate-600 dark:text-slate-300">
                                    <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full {{ $signedIds->contains($file->id) ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400' : 'border border-slate-300 dark:border-slate-600' }}" aria-hidden="true">@if ($signedIds->contains($file->id))<svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>@endif</span>
                                    <span>{{ $file->label() }}<span class="sr-only">: {{ $signedIds->contains($file->id) ? 'Signed copy uploaded' : 'Awaiting signed copy' }}</span></span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-4 text-xs leading-5 text-slate-500 dark:text-slate-400">No required proposal papers are available in the latest submission.</p>
                    @endif
                    <button type="button" @click="setTopicTab('notice', 'notice-to-proceed')" class="mt-5 inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-soft focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">{{ $released ? 'View released documents' : 'Continue to signing' }}</button>
                </div>
            </section>
            <section class="px-1">
                <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Latest proposal package</h3>
                @if ($version)
                    <p class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400">Version {{ $version->version_number }} submitted {{ $version->created_at->format('M j, Y') }} by {{ $version->submitter?->name ?? $topic->user->name }}.</p>
                @endif
                <button type="button" @click="$dispatch('open-project-documents')" class="mt-2 inline-flex min-h-11 items-center gap-2 rounded-lg text-xs font-semibold text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:text-red-300"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v10H3V7Z" /></svg>Open proposal files</button>
            </section>
        </aside>
    </div>
</div>
