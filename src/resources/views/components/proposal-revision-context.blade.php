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
            <strong class="text-xs font-bold">Research Head comment{{ $targetLabel ? ' · '.$targetLabel : '' }}</strong>
            <a data-paper-cancel-exit href="{{ route('topics.show', $proposalDraft->topic_id) }}#submit-revision" class="shrink-0 text-xs font-semibold underline underline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2">All revision tasks</a>
        </div>
        <p class="mt-2 whitespace-pre-line break-words" data-revision-instruction>{{ $annotation->comment }}</p>
        <p class="mt-2 text-xs leading-5 opacity-80" data-revision-context-help>This comment stays visible while you edit the highlighted field below.</p>
        <p @if (! $targetLabel || $editorTarget) hidden @endif data-revision-target-unavailable class="mt-2 text-xs">This field could not be located reliably in the current paper. Use these instructions to update it, or return to the revision tasks to view the PDF highlight.</p>
    </section>
@endif
