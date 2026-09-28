@if (request()->boolean('revision_embed'))
    <div hidden data-revision-editor-context
        data-draft-id="{{ $proposalDraft->id }}"
        data-topic-id="{{ $proposalDraft->topic_id }}"
        data-document-type="{{ $documentType }}"
        data-targets="{{ json_encode($revisionTargets) }}"
        data-original-source="{{ json_encode($originalSourceData) }}"></div>
@elseif ($annotation)
    <section data-revision-context data-revision-target="{{ $editorTarget }}" class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-950 shadow-sm dark:border-red-800 dark:bg-red-950 dark:text-red-100" aria-label="Requested revision">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <strong class="text-xs font-bold">{{ $annotation->feedbackLabel() }} comment{{ $targetLabel ? ' · '.$targetLabel : '' }}</strong>
            <a data-paper-cancel-exit href="{{ route('faculty.topics.revision', $proposalDraft->topic_id) }}" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg border border-red-200 bg-white px-4 py-2.5 text-sm font-bold text-red-800 shadow-sm transition hover:bg-red-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 dark:border-red-900 dark:bg-slate-900 dark:text-red-200 dark:hover:bg-red-950/40 dark:focus-visible:ring-offset-slate-900">All revision tasks</a>
        </div>
        <p class="mt-2 whitespace-pre-line break-words" data-revision-instruction>{{ $annotation->comment }}</p>
        <a href="{{ route('topics.versions.files.annotations.index', [$proposalDraft->topic_id, $annotation->file->proposal_version_id, $annotation->file]) }}?annotation={{ $annotation->id }}" target="_blank" rel="noopener" class="mt-3 inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-red-200 bg-white px-4 py-2.5 text-sm font-bold text-red-800 shadow-sm transition hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 dark:border-red-900 dark:bg-slate-900 dark:text-red-200 dark:hover:bg-red-950/40 dark:focus-visible:ring-offset-slate-900"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 4h6v6M20 4 4 20M9 4H4v16h16v-5" /></svg>View exact PDF highlight</a>
        <p class="mt-2 text-xs leading-5 opacity-80" data-revision-context-help>This comment stays visible while you edit the marked section below.</p>
        <p @if (! $targetLabel || $editorTarget) hidden @endif data-revision-target-unavailable class="mt-2 text-xs">This section could not be located reliably in the current paper. Use these instructions to update it, or return to the revision tasks to view the PDF highlight.</p>
    </section>
@endif
