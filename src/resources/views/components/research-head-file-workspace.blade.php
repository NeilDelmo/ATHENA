@props(['topic', 'workspace', 'showFacultyFiles' => true])

@php
    $latestVersion = $workspace['latestVersion'];
    $facultySubmittedFiles = $workspace['facultySubmittedFiles'];
    $headUploadedFiles = $workspace['headUploadedFiles'];
    $supplementalHeadUploads = $workspace['supplementalHeadUploads'];
    $headUploadsBySource = $workspace['headUploadsBySource'];
    $availableFileIds = $workspace['availableFileIds'];
    $viewableFileIds = $workspace['viewableFileIds'];
    $requiredSignatureFiles = $workspace['requiredSignatureFiles'];
    $signedSourceFileIds = $workspace['signedSourceFileIds'];
    $missingSignatureFiles = $workspace['missingSignatureFiles'];
    $signaturesComplete = $workspace['signaturesComplete'];
    $isSigningStage = $topic->status === \App\Models\TopicProposal::STATUS_READY_FOR_SIGNATURE;
    $canUploadRevisionCopy = in_array($topic->status, ['pending', 'expert_review', 'for_final_decision', 'resubmitted', 'revision_requested'], true);
    $activeSignedCopiesBySource = $headUploadedFiles
        ->filter(fn ($file) => ($file->source_data['purpose'] ?? null) === \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED && ! $file->isSuperseded())
        ->groupBy('source_version_file_id');
    $supersededSignedCopiesBySource = $headUploadedFiles
        ->filter(fn ($file) => ($file->source_data['purpose'] ?? null) === \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED && $file->isSuperseded())
        ->groupBy('source_version_file_id');
@endphp

<div data-research-head-file-workspace {{ $attributes->merge(['class' => 'space-y-5']) }}>
    @if ($showFacultyFiles)
    <section class="relative overflow-hidden rounded-2xl border border-red-200 bg-gradient-to-br from-red-50 via-white to-white p-5 shadow-sm dark:border-red-950 dark:from-red-950/40 dark:via-gray-950 dark:to-gray-950 sm:p-7">
        <div class="absolute inset-y-0 left-0 w-1.5 bg-red-700" aria-hidden="true"></div>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="max-w-3xl">
                <p class="text-sm font-bold text-red-700 dark:text-red-300">Research Head workspace</p>
                <h3 class="mt-1 font-serif text-2xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-3xl">Review faculty files</h3>
                <p class="mt-3 text-base leading-7 text-gray-700 dark:text-gray-200">Open the faculty original, highlight PDF revisions when needed, and attach a reviewed copy only when there is a separate file to return. Scanned PDFs can be marked by area; corrections and signatures are verified manually.</p>
            </div>
            <span class="inline-flex w-fit rounded-full border border-gray-300 bg-gray-950 px-3.5 py-2 text-sm font-bold text-white dark:border-gray-700 dark:bg-white dark:text-gray-950">
                {{ $latestVersion ? 'Version '.$latestVersion->version_number.' · '.$headUploadedFiles->count().' uploaded' : 'No submitted version' }}
            </span>
        </div>
    </section>
    @endif

    @if ($errors->headUpload->any())
        <div role="alert" class="rounded-2xl border border-red-300 bg-red-50 p-5 text-sm text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-100">
            <p class="font-black">The file could not be uploaded.</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->headUpload->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if ($requiredSignatureFiles->isNotEmpty() && ($isSigningStage || $topic->status === 'approved'))
        <section aria-labelledby="signature-progress-heading" class="rounded-2xl border border-red-300 bg-white p-5 shadow-sm dark:border-red-900 dark:bg-gray-950 sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-bold text-red-700 dark:text-red-300">Final signing</p>
                    <h3 id="signature-progress-heading" class="mt-1 font-serif text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                        {{ $topic->status === 'approved' ? 'Released signed copies' : 'Upload the required signed PDFs' }}
                    </h3>
                    <p class="mt-3 max-w-3xl text-base leading-7 text-gray-700 dark:text-gray-200">Signed PDFs are required for all five listed proposal papers. Attachment C and Estimated Expense Breakdown stay in the package without signatures.</p>
                </div>
                <span class="inline-flex w-fit rounded-full {{ $missingSignatureFiles->isEmpty() ? 'bg-gray-950 text-white dark:bg-white dark:text-gray-950' : 'border border-red-300 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200' }} px-3 py-1.5 text-sm font-black">
                    {{ $requiredSignatureFiles->count() - $missingSignatureFiles->count() }}/{{ $requiredSignatureFiles->count() }} uploaded
                </span>
            </div>

            <div class="mt-5 divide-y divide-gray-200 overflow-hidden rounded-2xl border border-gray-200 dark:divide-gray-800 dark:border-gray-800">
                @foreach ($requiredSignatureFiles as $requiredSignatureFile)
                    @php
                        $hasSignedCopy = $signedSourceFileIds->contains($requiredSignatureFile->id);
                        $activeSignedCopy = $activeSignedCopiesBySource->get($requiredSignatureFile->id, collect())->first();
                        $supersededSignedCopies = $supersededSignedCopiesBySource->get($requiredSignatureFile->id, collect());
                    @endphp
                    <article x-data="{ previewOpen: false }" class="grid gap-4 bg-white p-4 dark:bg-gray-950 lg:grid-cols-[minmax(0,1fr)_minmax(22rem,0.9fr)] lg:items-start">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h4 class="font-serif text-lg font-bold text-gray-950 dark:text-white">{{ $requiredSignatureFile->label() }}</h4>
                                <span class="rounded-full {{ $hasSignedCopy ? 'bg-emerald-700 text-white dark:bg-emerald-500 dark:text-emerald-950' : 'border border-red-300 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200' }} px-2.5 py-1 text-sm font-bold">
                                    {{ $hasSignedCopy ? 'Signed PDF uploaded' : 'Waiting for signed PDF' }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm font-semibold text-gray-500 dark:text-gray-400">Required faculty paper</p>
                            <p class="mt-2 break-all text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $requiredSignatureFile->original_filename }}</p>
                        </div>

                        <div class="space-y-3">
                            @if ($activeSignedCopy)
                                <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900/70 dark:bg-emerald-950/25" aria-label="Uploaded signed PDF">
                                    <div class="flex items-start gap-3">
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-700 text-white shadow-sm dark:bg-emerald-500 dark:text-emerald-950" aria-hidden="true">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4.25 4.25L19 6.5" /></svg>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-black text-emerald-950 dark:text-emerald-100">Uploaded signed copy</p>
                                            <p class="mt-1 break-all text-sm font-semibold text-emerald-900 dark:text-emerald-200">{{ $activeSignedCopy->original_filename }}</p>
                                        </div>
                                    </div>

                                    <div class="mt-4 grid grid-cols-2 gap-2 text-sm">
                                        <div class="rounded-xl bg-white/80 px-3 py-2.5 dark:bg-gray-950/70">
                                            <p class="font-bold text-emerald-800 dark:text-emerald-300">File size</p>
                                            <p class="mt-1 font-black text-emerald-950 dark:text-emerald-100">{{ $activeSignedCopy->file_size ? \Illuminate\Support\Number::fileSize($activeSignedCopy->file_size) : 'Size unavailable' }}</p>
                                        </div>
                                        <div class="rounded-xl bg-white/80 px-3 py-2.5 dark:bg-gray-950/70">
                                            <p class="font-bold text-emerald-800 dark:text-emerald-300">Uploaded</p>
                                            <p class="mt-1 font-black text-emerald-950 dark:text-emerald-100">{{ $activeSignedCopy->created_at->diffForHumans() }}</p>
                                        </div>
                                    </div>

                                    <div class="mt-4 flex flex-wrap gap-2">
                                        <button type="button" @click="previewOpen = ! previewOpen" :aria-expanded="previewOpen" class="inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-300 bg-white px-3 py-2.5 text-sm font-black text-emerald-900 transition hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 dark:border-emerald-800 dark:bg-gray-950 dark:text-emerald-200 dark:hover:bg-emerald-950 dark:focus:ring-emerald-400 dark:focus:ring-offset-gray-950">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" /><circle cx="12" cy="12" r="2.25" /></svg>
                                            <span x-text="previewOpen ? 'Close preview' : 'Preview signed PDF'">Preview signed PDF</span>
                                        </button>
                                        <a href="{{ route('topics.versions.files.download', [$topic, $latestVersion, $activeSignedCopy]) }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-700 px-3 py-2.5 text-sm font-black text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 dark:bg-emerald-500 dark:text-emerald-950 dark:hover:bg-emerald-400 dark:focus:ring-emerald-400 dark:focus:ring-offset-gray-950">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4m-4 6.75v1.5A2.25 2.25 0 0 0 6.25 21h11.5A2.25 2.25 0 0 0 20 18.75v-1.5" /></svg>
                                            Download
                                        </a>
                                    </div>
                                </section>
                            @endif

                            @if ($isSigningStage)
                                <form action="{{ route('topics.head-uploads.store', $topic) }}" method="POST" enctype="multipart/form-data" class="rounded-2xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-900/50">
                                    @csrf
                                    <input type="hidden" name="source_file_id" value="{{ $requiredSignatureFile->id }}">
                                    <input type="hidden" name="purpose" value="{{ \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED }}">
                                    <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                                        <label class="block text-sm font-bold text-gray-800 dark:text-gray-200">
                                            {{ $hasSignedCopy ? 'Replace signed final PDF' : 'Signed final PDF' }}
                                            <span class="mt-1 block text-sm font-medium leading-6 text-gray-500 dark:text-gray-400">{{ $hasSignedCopy ? 'Use this only if the uploaded copy needs to be replaced.' : 'PDF only. The uploaded copy will appear here for preview.' }}</span>
                                            <input name="review_file" type="file" accept=".pdf" required class="mt-2 block w-full rounded-xl border border-gray-300 bg-white p-2.5 text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-bold file:text-gray-800 hover:file:bg-gray-200 focus:border-red-700 focus:outline-none focus:ring-2 focus:ring-red-700 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200 dark:file:bg-gray-800 dark:file:text-white dark:focus:border-red-400 dark:focus:ring-red-400">
                                        </label>
                                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-red-700 px-4 py-3 text-sm font-black text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2 sm:w-auto dark:focus:ring-offset-gray-950">
                                            {{ $hasSignedCopy ? 'Replace PDF' : 'Upload PDF' }}
                                        </button>
                                    </div>
                                </form>
                            @endif
                        </div>

                        @if ($activeSignedCopy)
                            <section x-show="previewOpen" x-cloak x-transition.opacity class="overflow-hidden rounded-2xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-900/50 lg:col-span-2" aria-label="Signed PDF preview">
                                <div class="flex flex-wrap items-center justify-between gap-3 pb-3">
                                    <div>
                                        <p class="text-sm font-black text-gray-950 dark:text-white">Signed PDF preview</p>
                                        <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">Check the uploaded copy here before finalizing approval.</p>
                                    </div>
                                    <a href="{{ route('topics.versions.files.view', [$topic, $latestVersion, $activeSignedCopy]) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm font-bold text-gray-800 transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-700 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:hover:bg-gray-800 dark:focus:ring-gray-400 dark:focus:ring-offset-gray-950">Open in new tab</a>
                                </div>
                                <iframe data-signed-copy-preview src="{{ route('topics.versions.files.view', [$topic, $latestVersion, $activeSignedCopy]) }}" title="Preview of {{ $activeSignedCopy->original_filename }}" class="h-[34rem] w-full rounded-xl border border-gray-300 bg-white shadow-inner dark:border-gray-700"></iframe>
                            </section>
                        @endif
                    </article>
                    @if ($supersededSignedCopies->isNotEmpty())
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">
                            {{ $supersededSignedCopies->count() }} superseded signed {{ \Illuminate\Support\Str::plural('copy', $supersededSignedCopies->count()) }} retained in the Research Head audit record.
                        </div>
                    @endif
                @endforeach
            </div>

            @if ($isSigningStage)
                <form action="{{ route('research_head.topics.finalizeApproval', $topic) }}" method="POST" class="mt-5 flex flex-col gap-3 border-t border-gray-200 pt-5 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                    @csrf
                    @method('PATCH')
                    <p class="text-sm font-semibold leading-6 text-gray-700 dark:text-gray-300">
                        {{ $signaturesComplete ? 'Signed papers are ready. Prepare the signed Notice to Proceed to release the complete package.' : 'Final approval stays locked until all five required papers have signed PDFs.' }}
                    </p>
                    <button type="submit" @disabled(! $signaturesComplete) class="inline-flex shrink-0 items-center justify-center rounded-xl bg-red-700 px-5 py-3 text-sm font-black text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-600 dark:disabled:bg-gray-800 dark:disabled:text-gray-500">
                        Continue to Notice to Proceed
                    </button>
                </form>
            @endif
        </section>
    @endif

    @if ($showFacultyFiles)
    <section aria-labelledby="head-upload-files-heading" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-950 sm:p-6">
        <div>
            <h3 id="head-upload-files-heading" class="font-serif text-2xl font-bold tracking-tight text-gray-950 dark:text-white">Faculty-submitted files</h3>
            <p class="mt-2 text-base leading-7 text-gray-700 dark:text-gray-200">These are the faculty originals. They are never overwritten by a reviewed or signed copy.</p>
        </div>

        <div class="mt-5 grid gap-4">
            @forelse ($facultySubmittedFiles as $facultyFile)
                @php
                    $facultyFileAvailable = $availableFileIds->contains($facultyFile->id);
                    $facultyFileViewable = $viewableFileIds->contains($facultyFile->id);
                    $facultyFileAnnotationCount = $facultyFile->annotations
                        ->where('feedback_source', \App\Models\ProposalFileAnnotation::SOURCE_HEAD)
                        ->count();
                    $isInitialScreeningForm = $facultyFile->document_type === \App\Models\ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM;
                    $researchHeadCopies = $headUploadsBySource->get($facultyFile->id, collect())
                        ->reject(fn ($copy) => ($copy->source_data['purpose'] ?? null) === \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED);
                @endphp
                <article class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950">
                    <div class="grid gap-4 p-4 sm:p-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start">
                        <div class="flex min-w-0 gap-4">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $facultyFileAvailable ? 'bg-red-700 text-white' : 'bg-gray-200 text-gray-600 dark:bg-gray-800 dark:text-gray-300' }}" aria-hidden="true">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h7.5l3 3v13.5H6.75V3.75Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 3.75v3h3" /></svg>
                            </span>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="font-serif text-lg font-bold text-gray-950 dark:text-white">{{ $facultyFile->label() }}</h4>
                                    <span class="rounded-full border border-gray-300 bg-white px-2.5 py-1 text-sm font-bold text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">Faculty original</span>
                                    @unless ($facultyFileAvailable)<span class="rounded-full border border-red-300 bg-red-50 px-2.5 py-1 text-sm font-bold text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">Unavailable</span>@endunless
                                </div>
                                <p class="mt-2 break-all text-sm font-bold text-gray-800 dark:text-gray-200">{{ $facultyFile->original_filename }}</p>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $facultyFile->file_size ? \Illuminate\Support\Number::fileSize($facultyFile->file_size) : 'Size unavailable' }} · Submitted {{ $latestVersion->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                        <div class="grid w-full grid-cols-1 gap-2 sm:w-auto sm:grid-cols-3">
                            @if ($facultyFileViewable)
                                <a href="{{ route('topics.versions.files.annotations.index', [$topic, $latestVersion, $facultyFile]) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-red-700 px-4 py-2.5 text-sm font-black text-white hover:bg-red-800">
                                    {{ $facultyFileAnnotationCount > 0 ? 'Highlights ('.$facultyFileAnnotationCount.')' : 'Highlight PDF' }}
                                </a>
                                <a href="{{ route('topics.versions.files.view', [$topic, $latestVersion, $facultyFile]) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-bold text-gray-800 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:hover:bg-gray-800">View PDF</a>
                            @endif
                            @if ($facultyFileAvailable)
                                <a href="{{ route('topics.versions.files.download', [$topic, $latestVersion, $facultyFile]) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-gray-950 px-4 py-2.5 text-sm font-bold text-white hover:bg-black dark:bg-white dark:text-gray-950 dark:hover:bg-gray-200">Download</a>
                            @endif
                        </div>
                    </div>

                    @if ($researchHeadCopies->isNotEmpty())
                        <div class="border-t border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900/60 sm:p-5">
                            <p class="text-sm font-bold text-gray-700 dark:text-gray-300">Research office copies</p>
                            <div class="mt-3 grid gap-3">
                                @foreach ($researchHeadCopies as $researchHeadCopy)
                                    @php
                                        $copyAvailable = $availableFileIds->contains($researchHeadCopy->id);
                                        $copyViewable = $viewableFileIds->contains($researchHeadCopy->id);
                                    @endphp
                                    <div class="grid gap-3 rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-950 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="break-all text-sm font-black text-gray-950 dark:text-white">{{ $researchHeadCopy->original_filename }}</p>
                                                <span class="rounded-full border border-gray-300 px-2.5 py-1 text-sm font-bold text-gray-700 dark:border-gray-700 dark:text-gray-300">{{ $researchHeadCopy->headUploadPurposeLabel() }}</span>
                                            </div>
                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Uploaded by {{ $researchHeadCopy->uploadedBy?->name ?? 'Research Head' }} · {{ $researchHeadCopy->created_at->format('M j, Y g:i A') }}</p>
                                            @if (($researchHeadCopy->source_data['purpose'] ?? null) === \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION)
                                                <div class="mt-3 rounded-xl border border-blue-200 bg-blue-50 p-3 text-sm leading-6 text-blue-950 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-100">
                                                    <p class="font-black">Co-evaluator · {{ $researchHeadCopy->source_data['co_evaluator_name'] ?? 'Name unavailable' }}</p>
                                                    <p class="mt-2 text-sm font-bold text-blue-700 dark:text-blue-300">Extracted Narrative Evaluation</p>
                                                    <p class="mt-1 whitespace-pre-line text-base leading-7">{{ $researchHeadCopy->source_data['narrative_evaluation'] ?? 'No narrative was extracted.' }}</p>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="flex gap-2">
                                            @if ($copyViewable)<a href="{{ route('topics.versions.files.view', [$topic, $latestVersion, $researchHeadCopy]) }}" target="_blank" rel="noopener" class="inline-flex flex-1 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-bold text-gray-800 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-white sm:flex-none">View</a>@endif
                                            @if ($copyAvailable)<a href="{{ route('topics.versions.files.download', [$topic, $latestVersion, $researchHeadCopy]) }}" class="inline-flex flex-1 items-center justify-center rounded-lg bg-gray-950 px-3 py-2 text-sm font-bold text-white hover:bg-black dark:bg-white dark:text-gray-950 sm:flex-none">Download</a>@endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($canUploadRevisionCopy)
                        <form action="{{ route('topics.head-uploads.store', $topic) }}" method="POST" enctype="multipart/form-data" data-co-evaluator-screening-panel="{{ $isInitialScreeningForm ? 'true' : 'false' }}" class="grid gap-4 border-t {{ $isInitialScreeningForm ? 'border-blue-200 bg-blue-50/60 dark:border-blue-900 dark:bg-blue-950/20' : 'border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950' }} p-4 sm:p-6 {{ $isInitialScreeningForm ? 'md:grid-cols-2' : 'md:grid-cols-[minmax(0,1fr)_auto]' }} md:items-end">
                            @csrf
                            <input type="hidden" name="source_file_id" value="{{ $facultyFile->id }}">
                            <input type="hidden" name="purpose" value="{{ $isInitialScreeningForm ? \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION : \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_REVISION }}">
                            @if ($isInitialScreeningForm)
                                <div class="flex items-start gap-4 md:col-span-2">
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-700 text-white dark:bg-blue-500 dark:text-blue-950" aria-hidden="true">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                    </span>
                                    <div>
                                        <p class="font-serif text-xl font-bold text-gray-950 dark:text-white">Add the co-evaluator’s completed Initial Screening Form</p>
                                        <p class="mt-2 text-base leading-7 text-gray-700 dark:text-gray-200">ATHENA extracts only the Narrative Evaluation and places it in a separate Co-evaluator Comment-Response Form. The original upload remains in the proposal record.</p>
                                    </div>
                                </div>
                                <label class="block text-base font-bold text-gray-800 dark:text-gray-100">Co-evaluator name
                                    <input name="co_evaluator_name" type="text" maxlength="160" required value="{{ old('co_evaluator_name') }}" placeholder="Full name" class="mt-2 block min-h-12 w-full rounded-xl border-gray-300 text-base focus:border-blue-700 focus:ring-blue-700 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                </label>
                            @endif
                            <label class="block text-base font-bold text-gray-800 dark:text-gray-100">
                                {{ $isInitialScreeningForm ? 'Completed Initial Screening Form' : 'Attach a reviewed copy for revision' }}
                                <input name="review_file" type="file" accept="{{ $isInitialScreeningForm ? '.pdf,.docx' : '.pdf,.doc,.docx,.xls,.xlsx' }}" required class="mt-2 block w-full rounded-xl border border-gray-300 bg-white p-2.5 text-base text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-2.5 file:text-sm file:font-bold file:text-gray-800 hover:file:bg-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:file:bg-gray-800 dark:file:text-white">
                            </label>
                            <button type="submit" class="inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-red-700 px-5 py-3 text-base font-bold text-white transition hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 md:w-auto {{ $isInitialScreeningForm ? 'md:col-span-2 md:justify-self-end' : '' }}">{{ $isInitialScreeningForm ? 'Upload and extract evaluation' : 'Upload reviewed copy' }}</button>
                        </form>
                    @endif
                </article>
            @empty
                <div class="rounded-2xl border border-gray-200 p-8 text-center dark:border-gray-800">
                    <p class="text-base font-black text-gray-900 dark:text-white">No faculty-submitted files are available</p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">A submitted proposal version is required before reviewed files can be attached.</p>
                </div>
            @endforelse
        </div>
    </section>
    @endif

    @if (! $isSigningStage)
    <section x-data="{ open: false }" data-supplemental-papers-disclosure data-initially-open="false" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-950">
        <button type="button" @click="open = !open" :aria-expanded="open" aria-controls="supplemental-papers-content" class="flex min-h-16 w-full items-center justify-between gap-4 p-5 text-left transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-red-700 dark:hover:bg-gray-900 sm:p-6">
            <span class="flex items-start gap-4">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-700 dark:bg-gray-900 dark:text-gray-200" aria-hidden="true">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h6l1.5 2.25h9v9.75a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5v-12Z" /></svg>
                </span>
                <span>
                    <span class="block font-serif text-xl font-bold text-gray-950 dark:text-white">Administrative and supplemental papers</span>
                    <span class="mt-1 block text-base font-normal leading-6 text-gray-600 dark:text-gray-300">Use this only for a separate paper received from another office or source.</span>
                </span>
            </span>
            <span class="flex shrink-0 items-center gap-3">
                <span class="rounded-full bg-gray-950 px-3 py-1.5 text-sm font-bold text-white dark:bg-white dark:text-gray-950">{{ $supplementalHeadUploads->count() }}</span>
                <span class="flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600 shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-300" aria-hidden="true"><svg :class="open ? 'rotate-180' : ''" class="h-5 w-5 transition-transform duration-200 motion-reduce:transition-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg></span>
            </span>
        </button>

        <div id="supplemental-papers-content" x-show="open" x-cloak x-transition class="border-t border-gray-200 p-5 dark:border-gray-800 sm:p-6">
            @if ($supplementalHeadUploads->isNotEmpty())
                <div class="grid gap-3">
                    @foreach ($supplementalHeadUploads as $supplementalPaper)
                        @php
                            $supplementalAvailable = $availableFileIds->contains($supplementalPaper->id);
                            $supplementalViewable = $viewableFileIds->contains($supplementalPaper->id);
                        @endphp
                        <article class="grid gap-3 rounded-xl border border-gray-200 p-4 dark:border-gray-800 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                            <div class="min-w-0">
                                <h4 class="font-serif text-lg font-bold text-gray-950 dark:text-white">{{ $supplementalPaper->label() }}</h4>
                                <p class="mt-1 break-all text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $supplementalPaper->original_filename }}</p>
                                <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">Uploaded by {{ $supplementalPaper->uploadedBy?->name ?? 'Research Head' }}@if ($supplementalPaper->source_data['issuing_office'] ?? null) · {{ $supplementalPaper->source_data['issuing_office'] }}@endif · {{ $supplementalPaper->created_at->format('M j, Y g:i A') }}</p>
                            </div>
                            <div class="flex gap-2">
                                @if ($supplementalViewable)<a href="{{ route('topics.versions.files.view', [$topic, $latestVersion, $supplementalPaper]) }}" target="_blank" rel="noopener" class="inline-flex flex-1 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-bold text-gray-800 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-white sm:flex-none">View</a>@endif
                                @if ($supplementalAvailable)<a href="{{ route('topics.versions.files.download', [$topic, $latestVersion, $supplementalPaper]) }}" class="inline-flex flex-1 items-center justify-center rounded-lg bg-gray-950 px-3 py-2 text-sm font-bold text-white hover:bg-black dark:bg-white dark:text-gray-950 sm:flex-none">Download</a>@endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif

            @if ($latestVersion)
                @php
                    $isSupplementalForm = old('purpose') === \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SUPPLEMENTAL;
                @endphp
                <form action="{{ route('topics.head-uploads.store', $topic) }}" method="POST" enctype="multipart/form-data" class="mt-5 grid gap-4 border-t border-gray-200 pt-5 dark:border-gray-800 lg:grid-cols-3 lg:items-end">
                    @csrf
                    <input type="hidden" name="purpose" value="{{ \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SUPPLEMENTAL }}">
                    <label class="block text-sm font-bold text-gray-800 dark:text-gray-200">Document title
                        <input name="document_title" type="text" maxlength="255" required value="{{ $isSupplementalForm ? old('document_title') : '' }}" placeholder="Regional endorsement memorandum" class="mt-2 block w-full rounded-xl border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                    </label>
                    <label class="block text-sm font-bold text-gray-800 dark:text-gray-200">Issuing office or source
                        <input name="issuing_office" type="text" maxlength="255" value="{{ $isSupplementalForm ? old('issuing_office') : '' }}" placeholder="Office of the Regional Director" class="mt-2 block w-full rounded-xl border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                    </label>
                    <label class="block text-sm font-bold text-gray-800 dark:text-gray-200">Paper
                        <input name="review_file" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx" required class="mt-2 block w-full rounded-xl border border-gray-300 bg-white p-2.5 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-bold file:text-gray-800 hover:file:bg-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:file:bg-gray-800 dark:file:text-white">
                    </label>
                    <div class="lg:col-span-3 lg:flex lg:justify-end">
                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-red-700 px-5 py-3 text-sm font-black text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2 lg:w-auto">Upload supplemental paper</button>
                    </div>
                </form>
            @endif
        </div>
    </section>

    @if ($headUploadsBySource->get(0, collect())->isNotEmpty())
        <section class="rounded-2xl border border-red-200 bg-red-50 p-5 dark:border-red-900 dark:bg-red-950/30 sm:p-6">
            <h3 class="text-base font-black text-red-900 dark:text-red-100">Earlier unlinked Research Head uploads</h3>
            <p class="mt-1 text-sm leading-6 text-red-800 dark:text-red-200">These older files remain in the proposal record but are not linked to a specific faculty paper.</p>
            <div class="mt-3 grid gap-2">
                @foreach ($headUploadsBySource->get(0) as $unlinkedCopy)
                    <p class="break-all rounded-xl bg-white px-3 py-2 text-sm font-semibold text-gray-700 dark:bg-gray-950 dark:text-gray-300">{{ $unlinkedCopy->original_filename }}</p>
                @endforeach
            </div>
        </section>
    @endif
    @endif

    @if ($showFacultyFiles)
    <section class="rounded-2xl bg-gray-950 p-5 text-white dark:border dark:border-gray-800 sm:p-6">
        <p class="font-black">Faculty originals are always preserved.</p>
        <p class="mt-1 text-sm leading-6 text-gray-300">PDF highlights hold the exact revision comments. Upload a reviewed copy only when you have a separate annotated or corrected file to return.</p>
    </section>
    @endif
</div>
