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
            <h3 id="signature-progress-heading" class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $isSigningStage ? 'Upload the required signed PDFs' : 'Released signed copies' }}</h3>
            <p class="mt-2 text-base text-slate-500 dark:text-slate-400">{{ $isSigningStage ? 'Drop a signed PDF onto its paper below. Files save automatically.' : 'View or download the signed proposal papers.' }}</p>
        </div>
        <span data-signed-count="{{ $signedFileCount }}" :data-signed-count="count" class="text-base font-semibold tabular-nums text-slate-600 dark:text-slate-300" aria-live="polite"><span x-text="count">{{ $signedFileCount }}</span>/{{ $finalSigningFiles->count() }} saved</span>
    </div>

    @if ($isSigningStage)
        <p class="px-5 pb-5 text-sm text-slate-500 dark:text-slate-400 sm:px-6">One PDF per paper, up to 25 MB. You can also browse for a file.</p>
        <noscript><p role="alert" class="px-5 pb-5 text-base text-red-700">Enable JavaScript to upload signed copies.</p></noscript>
    @endif

    <div class="divide-y divide-slate-100 border-t border-slate-100 dark:divide-slate-800 dark:border-slate-800">
        @foreach ($finalSigningFiles as $source)
            <article data-signing-document data-upload-state="{{ $signedSourceFileIds->contains($source->id) ? 'uploaded' : 'awaiting' }}" :data-upload-state="document.saved ? 'uploaded' : 'awaiting'" x-data="{ document: documents.find(item => item.id === {{ $source->id }}), dragging: false, replacing: false }" class="grid min-w-0 gap-4 border-l-4 px-5 py-6 sm:px-6" :class="document.saved ? 'border-l-emerald-500 bg-emerald-50/60 dark:bg-emerald-950/20' : 'border-l-transparent'">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-3">
                        <h4 class="text-lg font-semibold leading-7 text-slate-900 dark:text-white">{{ $source->label() }}</h4>
                        <span data-uploaded-badge x-show="document.saved" x-cloak class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-3 py-1 text-base font-semibold text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m5 12 4 4L19 6" stroke-linecap="round" stroke-linejoin="round" /></svg>Uploaded</span>
                    </div>
                    <p x-show="document.saved" class="mt-2 break-all text-base leading-6 text-slate-700 dark:text-slate-200" x-text="document.filename"></p>
                    <p role="status" class="mt-2 text-base leading-6" :class="document.saved ? 'font-medium text-emerald-700 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400'" x-text="document.busy ? 'Saving ' + document.file.name + '…' : (document.saved ? 'Signed copy saved' : 'Awaiting signed copy')">{{ $signedSourceFileIds->contains($source->id) ? 'Signed copy saved' : 'Awaiting signed copy' }}</p>
                    <p x-show="document.error" x-cloak role="alert" class="mt-2 text-base text-red-700 dark:text-red-300" x-text="document.error"></p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" x-show="document.saved" x-cloak @click="previewDocument = document; $dispatch('open-modal', 'signed-paper-preview-{{ $topic->id }}')" aria-haspopup="dialog" data-signed-preview-button class="rh-button !min-h-12 gap-2 !text-base" :aria-label="'Preview signed ' + document.label"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" stroke-linejoin="round" /><circle cx="12" cy="12" r="3" /></svg><span>Preview</span></button>
                    <a x-show="document.saved" x-cloak :href="document.downloadUrl" class="rh-button-secondary !min-h-12 gap-2 !text-base" :aria-label="'Download signed ' + document.label"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 3v12m-4-4 4 4 4-4M4 16v4h16v-4" stroke-linecap="round" stroke-linejoin="round" /></svg><span>Download</span></a>
                    @if ($isSigningStage)
                        <button data-replace-signed-copy type="button" x-show="document.saved && !document.busy" x-cloak @click="replacing = !replacing" :aria-expanded="replacing" class="rh-button-secondary !min-h-12 !text-base" :aria-label="'Replace signed copy for ' + document.label"><span x-text="replacing ? 'Cancel replacement' : 'Replace PDF'">Replace PDF</span></button>
                        <div data-signed-dropzone x-show="!document.saved || replacing || document.busy" x-cloak class="order-first flex w-full flex-wrap items-center justify-between gap-4 rounded-xl border-2 border-dashed border-red-200 bg-red-50/50 p-5 transition-colors dark:border-red-900 dark:bg-red-950/20" :class="dragging ? '!border-brand !bg-red-100 dark:!bg-red-950' : ''"
                            @dragover.prevent.stop="if (!document.busy) dragging = true"
                            @dragleave.prevent.stop="if (!$el.contains($event.relatedTarget)) dragging = false"
                            @drop.prevent.stop="dragging = false; await drop(document, $event.dataTransfer.files); if (!document.error) replacing = false"
                            :aria-busy="document.busy">
                            <div class="flex min-w-0 items-center gap-3">
                                <svg class="h-6 w-6 shrink-0 text-brand dark:text-red-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 16V4m-4 4 4-4 4 4M4 16v4h16v-4" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                <div>
                                    <p class="text-base font-semibold text-slate-900 dark:text-white" x-text="document.busy ? 'Saving PDF…' : (document.saved ? 'Drop a PDF here to replace this signed copy' : 'Drop the signed PDF here')">Drop the signed PDF here</p>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">For {{ $source->label() }} · PDF, up to 25 MB</p>
                                </div>
                            </div>
                            <label class="rh-button !min-h-12 !text-base cursor-pointer gap-2 focus-within:outline focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-brand" :class="document.busy && 'cursor-wait opacity-50'">
                                <span x-text="document.busy ? 'Saving…' : (document.saved ? 'Replace PDF' : 'Browse PDF')">Browse PDF</span>
                                <input data-signed-paper-input type="file" accept=".pdf,application/pdf" class="sr-only" :disabled="document.busy" aria-label="Signed PDF for {{ $source->label() }}" @change="await upload(document, $event.target.files[0]); $event.target.value = ''; if (!document.error) replacing = false">
                            </label>
                        </div>
                        <button x-show="document.error && document.file" x-cloak type="button" @click="await upload(document, document.file); if (!document.error) replacing = false" :disabled="document.busy" class="rh-button-secondary !min-h-12 !text-base">Retry</button>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    @if ($isSigningStage)
        <div data-signing-next-step class="flex flex-col gap-4 border-t border-slate-200 bg-slate-50/60 px-5 py-5 dark:border-slate-800 dark:bg-slate-900/50 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <p role="status" class="text-base leading-6 text-slate-600 dark:text-slate-300" x-text="complete ? 'Signed papers ready' : @js($assessmentsComplete ? 'Save all three signed papers to continue.' : 'Complete the earlier assessments and save the signed papers.')">{{ $signaturesComplete ? 'Signed papers ready' : ($assessmentsComplete ? 'Save all three signed papers to continue.' : 'Complete the earlier assessments and save the signed papers.') }}</p>
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" @click="$dispatch('open-project-documents', { category: 'signed_papers' })" data-view-signed-papers class="rh-button-secondary !min-h-12 gap-2 !text-base"><svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M6 3h8l4 4v14H6V3Z M14 3v5h4 M9 12h6 M9 16h6" stroke-linecap="round" stroke-linejoin="round"/></svg><span>View signed papers</span></button>
            </div>
        </div>
    @endif
    <x-modal name="signed-paper-preview-{{ $topic->id }}" maxWidth="6xl" focusable class="!z-[140]" data-signed-preview-modal>
        <template x-if="show && previewDocument">
            <section role="dialog" aria-modal="true" aria-labelledby="signed-paper-preview-heading-{{ $topic->id }}">
                <header class="flex items-center justify-between gap-4 border-b border-slate-200 p-4 dark:border-slate-700">
                    <div class="min-w-0">
                        <h3 id="signed-paper-preview-heading-{{ $topic->id }}" class="text-base font-semibold text-slate-900 dark:text-white" x-text="previewDocument.label"></h3>
                        <p class="mt-1 break-all text-sm text-slate-500" x-text="previewDocument.filename"></p>
                    </div>
                    <button type="button" @click="$dispatch('close')" class="rh-button-secondary">Close preview</button>
                </header>
                <div x-data="pdfAnnotationWorkspace({ pdfUrl: previewDocument.viewUrl, annotations: [], canAnnotate: false, fitWidth: true })" class="revision-pdf-viewer !h-[75dvh]">
                    <p x-show="loading" role="status" class="absolute inset-x-0 top-5 text-center text-base text-slate-600">Loading PDF…</p>
                    <p x-show="loadError" x-cloak role="alert" class="m-4 rounded-lg bg-red-50 p-4 text-base text-red-800" x-text="loadError"></p>
                    <div x-ref="viewer" tabindex="0" aria-label="Signed paper preview" class="pdf-annotation-viewer revision-pdf-pages"></div>
                </div>
            </section>
        </template>
    </x-modal>
</section>
