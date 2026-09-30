@php
    $uploadDocuments = $finalSigningFiles->map(function ($source) use ($workspace, $activeSignedCopiesBySource, $topic, $latestVersion) {
        $signed = ($workspace['signedCopiesBySource'] ?? collect())->get($source->id)
            ?? $activeSignedCopiesBySource->get($source->id, collect())->first();

        return [
            'id' => $source->id,
            'label' => $source->label(),
            'saved' => (bool) $signed,
            'filename' => $signed?->original_filename,
            'viewUrl' => $signed ? route('topics.versions.files.view', [$topic, $latestVersion, $signed]) : null,
            'downloadUrl' => $signed ? route('topics.versions.files.download', [$topic, $latestVersion, $signed]) : null,
        ];
    })->values();
    $uploadConfig = [
        'documents' => $uploadDocuments,
        'complete' => $signaturesComplete,
        'url' => route('topics.head-uploads.store', $topic),
        'csrf' => csrf_token(),
    ];
@endphp

<section data-signing-checklist x-data="proposalSignedUploads(@js($uploadConfig))" aria-labelledby="signature-progress-heading" class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950">
    <div class="flex flex-wrap items-start justify-between gap-4 px-5 py-6 sm:px-6">
        <div>
            <h3 id="signature-progress-heading" class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $isSigningStage ? 'Upload the required signed PDFs' : 'Released signed copies' }}</h3>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $isSigningStage ? 'Select a PDF beside each paper. Files save automatically.' : 'View or download the signed proposal papers.' }}</p>
        </div>
        <span class="text-sm font-semibold tabular-nums text-slate-600 dark:text-slate-300" aria-live="polite"><span x-text="count">{{ $signedFileCount }}</span>/{{ $finalSigningFiles->count() }} saved</span>
    </div>

    @if ($isSigningStage)
        <div class="px-5 pb-5 sm:px-6">
            <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus-within:outline focus-within:outline-2 focus-within:outline-brand dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-900">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 16V4m-4 4 4-4 4 4M4 16v4h16v-4" stroke-linecap="round" stroke-linejoin="round" /></svg>
                Choose multiple PDFs
                <input data-signed-batch-input type="file" multiple accept=".pdf,application/pdf" class="sr-only" @change="selectBatch($event.target.files); $event.target.value = ''">
            </label>
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Choose the matching paper for each file below to save it. PDF only, up to 25 MB each.</p>
            <noscript><p role="alert" class="mt-2 text-sm text-red-700">Enable JavaScript to upload signed copies.</p></noscript>
            <div x-show="pending.length" x-cloak class="mt-4 space-y-3">
                <template x-for="(item, index) in pending" :key="item.id">
                    <div class="flex flex-col gap-2 rounded-lg bg-slate-50 p-3 dark:bg-slate-900 sm:flex-row sm:items-center">
                        <span class="min-w-0 flex-1 break-all text-sm text-slate-700 dark:text-slate-200" x-text="item.file.name"></span>
                        <label class="sm:w-64"><span class="sr-only" x-text="'Matching paper for ' + item.file.name"></span>
                            <select class="rh-control w-full" @change="assign(item, $event.target.value)">
                                <option value="">Choose matching paper</option>
                                <template x-for="document in documents" :key="document.id"><option :value="document.id" :disabled="document.busy" x-text="document.label + (document.saved ? ' (replace)' : '')"></option></template>
                            </select>
                        </label>
                        <button type="button" @click="pending.splice(index, 1)" :aria-label="'Remove ' + item.file.name" class="min-h-11 px-2 text-sm text-slate-500 hover:text-red-700">Remove</button>
                    </div>
                </template>
            </div>
        </div>
    @endif

    <div class="divide-y divide-slate-100 border-t border-slate-100 dark:divide-slate-800 dark:border-slate-800">
        @foreach ($finalSigningFiles as $source)
            <article data-signing-document x-data="{ document: documents.find(item => item.id === {{ $source->id }}) }" class="grid min-w-0 gap-3 px-5 py-5 sm:px-6 md:grid-cols-[minmax(0,1fr)_auto] md:items-center">
                <div class="min-w-0">
                    <h4 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $source->label() }}</h4>
                    <p x-show="document.saved && !document.busy" class="mt-1 break-all text-xs text-slate-500 dark:text-slate-400" x-text="document.filename"></p>
                    <p role="status" class="mt-1 text-xs" :class="document.saved ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400'" x-text="document.busy ? 'Saving ' + document.file.name + '…' : (document.saved ? 'Signed copy saved' : 'Awaiting signed copy')">{{ $signedSourceFileIds->contains($source->id) ? 'Signed copy saved' : 'Awaiting signed copy' }}</p>
                    <p x-show="document.error" x-cloak role="alert" class="mt-2 text-sm text-red-700 dark:text-red-300" x-text="document.error"></p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <a x-show="document.saved" x-cloak :href="document.viewUrl" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center rounded text-sm font-semibold text-brand hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:text-red-300" :aria-label="'Preview signed ' + document.label">Preview</a>
                    <a x-show="document.saved" x-cloak :href="document.downloadUrl" class="inline-flex min-h-11 items-center rounded text-sm text-slate-600 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:text-slate-300" :aria-label="'Download signed ' + document.label">Download</a>
                    @if ($isSigningStage)
                        <label class="inline-flex min-h-11 cursor-pointer items-center justify-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus-within:outline focus-within:outline-2 focus-within:outline-brand dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-900" :class="document.busy && 'cursor-wait opacity-50'">
                            <span x-text="document.busy ? 'Saving…' : (document.saved ? 'Replace PDF' : 'Choose PDF')">Choose PDF</span>
                            <input data-signed-paper-input type="file" accept=".pdf,application/pdf" class="sr-only" :disabled="document.busy" aria-label="Signed PDF for {{ $source->label() }}" @change="upload(document, $event.target.files[0]); $event.target.value = ''">
                        </label>
                        <button x-show="document.error && document.file" x-cloak type="button" @click="upload(document, document.file)" :disabled="document.busy" class="rh-button-secondary">Retry</button>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    @if ($isSigningStage)
        <form action="{{ route('research_head.topics.finalizeApproval', $topic) }}" method="POST" @submit="if (!complete || busy || pending.length) $event.preventDefault()" class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 px-5 py-5 dark:border-slate-800 sm:px-6">
            @csrf
            @method('PATCH')
            <div class="text-xs leading-5 text-slate-500 dark:text-slate-400">
                @if ($assessmentsComplete)
                    <p>Earlier assessments are complete. <button type="button" @click="$dispatch('open-project-documents', { category: 'signed_papers' })" class="rounded font-semibold text-brand hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:text-red-300">View signed papers</button></p>
                @else
                    <p>Complete the GAD Checklist and Initial Screening Form in their review stages.</p>
                @endif
                <p x-text="complete ? 'All signed papers saved. Continue to prepare the Notice to Proceed.' : 'Save all three signed papers to continue.'">Save all three signed papers to continue.</p>
            </div>
            <button data-signing-continue type="submit" @disabled(! $signaturesComplete) :disabled="!complete || busy || pending.length > 0" class="rh-button disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-500 dark:disabled:bg-slate-800 dark:disabled:text-slate-400">Continue to Notice to Proceed</button>
        </form>
    @endif
</section>
