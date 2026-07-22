<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <a href="{{ route('topics.show', $topic) }}" class="text-xs font-bold text-red-600 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">&larr; Submitted proposal</a>
                <h2 class="mt-2 text-2xl font-black tracking-tight text-gray-900">Review and Upload Files</h2>
                <p class="mt-1 text-xs text-gray-500">Review the faculty package, attach reviewed copies, or upload standalone papers received from another office.</p>
            </div>
            <span class="inline-flex w-fit rounded-full bg-cyan-100 px-3 py-1.5 text-xs font-black text-cyan-800">
                {{ $latestVersion ? 'Version '.$latestVersion->version_number.' · '.$headUploadedFiles->count().' uploaded' : 'No submitted version' }}
            </span>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('success'))
            <div role="status" class="rounded-2xl border border-green-200 bg-green-50 p-4 text-sm font-semibold text-green-800">{{ session('success') }}</div>
        @endif

        @if ($errors->headUpload->any())
            <div role="alert" class="rounded-2xl border border-red-200 bg-red-50 p-5 text-sm text-red-800">
                <p class="font-black">The Research Head file could not be uploaded.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->headUpload->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section aria-labelledby="head-upload-details-heading" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 id="head-upload-details-heading" class="text-lg font-black text-gray-900">Project Details</h3>
                    <p class="mt-1 text-xs text-gray-500">Shared across the submitted proposal package.</p>
                </div>
                <span class="inline-flex w-fit rounded-full bg-amber-50 px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-amber-800">{{ str_replace('_', ' ', $topic->status) }}</span>
            </div>
            <dl class="mt-5 grid gap-4 border-t border-gray-100 pt-5 sm:grid-cols-2 lg:grid-cols-3">
                <div class="sm:col-span-2 lg:col-span-3"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Title</dt><dd class="mt-1 text-sm font-bold text-gray-900">{{ $topic->title }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Research Call</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $topic->researchCall->title }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Duration</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $topic->estimated_duration_months }} {{ Str::plural('month', $topic->estimated_duration_months) }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Leader</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $topic->user->name }}</dd></div>
            </dl>
        </section>

        <section aria-labelledby="supplemental-papers-heading" class="rounded-2xl border border-violet-200 bg-violet-50/40 p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 id="supplemental-papers-heading" class="text-lg font-black text-gray-900">Administrative and supplemental papers</h3>
                    <p class="mt-1 text-xs leading-5 text-gray-600">The Research Head uploads papers received from a higher office or another source. These are kept with the proposal but are not forms the faculty must complete or replace.</p>
                </div>
                <span class="inline-flex w-fit rounded-full bg-violet-100 px-3 py-1.5 text-[10px] font-black uppercase tracking-wider text-violet-800">{{ $supplementalHeadUploads->count() }} supplemental</span>
            </div>

            @if ($supplementalHeadUploads->isNotEmpty())
                <div class="mt-5 space-y-3">
                    @foreach ($supplementalHeadUploads as $supplementalPaper)
                        @php
                            $supplementalAvailable = $availableFileIds->contains($supplementalPaper->id);
                            $supplementalViewable = $viewableFileIds->contains($supplementalPaper->id);
                        @endphp
                        <article class="flex flex-col gap-3 rounded-xl border border-violet-100 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="text-sm font-black text-gray-900">{{ $supplementalPaper->label() }}</h4>
                                    <span class="rounded-full bg-violet-100 px-2 py-0.5 text-[9px] font-black uppercase text-violet-800">Supplemental paper</span>
                                </div>
                                <p class="mt-1 break-all text-xs font-semibold text-gray-700">{{ $supplementalPaper->original_filename }}</p>
                                <p class="mt-1 text-[11px] text-gray-500">Uploaded by {{ $supplementalPaper->uploadedBy?->name ?? 'Research Head' }}@if ($supplementalPaper->source_data['issuing_office'] ?? null) · From {{ $supplementalPaper->source_data['issuing_office'] }}@endif · {{ $supplementalPaper->created_at->format('M j, Y g:i A') }}</p>
                                @if ($supplementalPaper->source_data['note'] ?? null)<p class="mt-1 text-xs leading-5 text-gray-600">{{ $supplementalPaper->source_data['note'] }}</p>@endif
                            </div>
                            <div class="flex w-full shrink-0 gap-2 sm:w-auto">
                                @if ($supplementalViewable)<a href="{{ route('topics.versions.files.view', [$topic, $latestVersion, $supplementalPaper]) }}" target="_blank" rel="noopener" class="inline-flex flex-1 items-center justify-center rounded-lg border border-violet-200 bg-white px-3 py-2 text-[11px] font-bold text-violet-800 hover:bg-violet-50 sm:flex-none">View</a>@endif
                                @if ($supplementalAvailable)<a href="{{ route('topics.versions.files.download', [$topic, $latestVersion, $supplementalPaper]) }}" class="inline-flex flex-1 items-center justify-center rounded-lg bg-violet-700 px-3 py-2 text-[11px] font-bold text-white hover:bg-violet-800 sm:flex-none">Download</a>@endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif

            @if ($latestVersion)
                @php
                    $isSupplementalForm = old('purpose') === \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SUPPLEMENTAL;
                @endphp
                <form action="{{ route('topics.head-uploads.store', $topic) }}" method="POST" enctype="multipart/form-data" class="mt-5 grid gap-4 border-t border-violet-200 pt-5 sm:grid-cols-2">
                    @csrf
                    <input type="hidden" name="purpose" value="{{ \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SUPPLEMENTAL }}">
                    <label class="block text-[11px] font-bold text-gray-700">Document title <span class="text-red-600">Required</span>
                        <input name="document_title" type="text" maxlength="255" required value="{{ $isSupplementalForm ? old('document_title') : '' }}" placeholder="e.g. Regional endorsement memorandum" class="mt-1 block w-full rounded-xl border-violet-200 text-xs focus:border-violet-500 focus:ring-violet-500">
                    </label>
                    <label class="block text-[11px] font-bold text-gray-700">Issuing office or source (optional)
                        <input name="issuing_office" type="text" maxlength="255" value="{{ $isSupplementalForm ? old('issuing_office') : '' }}" placeholder="e.g. Office of the Regional Director" class="mt-1 block w-full rounded-xl border-violet-200 text-xs focus:border-violet-500 focus:ring-violet-500">
                    </label>
                    <label class="block text-[11px] font-bold text-gray-700">Paper <span class="text-red-600">Required</span>
                        <input name="review_file" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx" required class="mt-1 block w-full rounded-xl border border-violet-200 bg-white p-2 text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-violet-100 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-violet-800 hover:file:bg-violet-200">
                    </label>
                    <label class="block text-[11px] font-bold text-gray-700">Record note (optional)
                        <input name="note" type="text" maxlength="2000" value="{{ $isSupplementalForm ? old('note') : '' }}" placeholder="Why this paper belongs with the proposal" class="mt-1 block w-full rounded-xl border-violet-200 text-xs focus:border-violet-500 focus:ring-violet-500">
                    </label>
                    <div class="sm:col-span-2 sm:flex sm:justify-end">
                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-violet-700 px-5 py-2.5 text-xs font-black text-white transition hover:bg-violet-800 focus:outline-none focus:ring-2 focus:ring-violet-700 focus:ring-offset-2 sm:w-auto">Upload supplemental paper</button>
                    </div>
                </form>
            @else
                <p class="mt-5 rounded-xl border border-violet-200 bg-white p-4 text-xs text-violet-800">The Research Head can upload supplemental papers after the faculty turns in the first proposal draft.</p>
            @endif
        </section>

        <section aria-labelledby="head-upload-files-heading" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div>
                <h3 id="head-upload-files-heading" class="text-lg font-black text-gray-900">Faculty-submitted files</h3>
                <p class="mt-1 text-xs text-gray-500">The faculty originals remain unchanged. Attach a reviewed file to the exact original below and identify whether it is for revision or is already signed.</p>
            </div>

            <div class="mt-5 divide-y divide-gray-100 rounded-xl border border-gray-200">
                @forelse ($facultySubmittedFiles as $facultyFile)
                    @php
                        $facultyFileAvailable = $availableFileIds->contains($facultyFile->id);
                        $facultyFileViewable = $viewableFileIds->contains($facultyFile->id);
                        $facultyFileAnnotationCount = $facultyFile->annotations->count();
                        $researchHeadCopies = $headUploadsBySource->get($facultyFile->id, collect());
                        $isCurrentForm = (int) old('source_file_id') === $facultyFile->id;
                        $selectedPurpose = $isCurrentForm ? old('purpose', \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_REVISION) : \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_REVISION;
                    @endphp
                    <article class="p-4 sm:p-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex min-w-0 gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $facultyFileAvailable ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-500' }} text-[10px] font-black">PDF</span>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h4 class="text-sm font-black text-gray-900">{{ $facultyFile->label() }}</h4>
                                        <span class="rounded-full bg-green-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-green-800">Faculty original</span>
                                        @unless ($facultyFileAvailable)<span class="rounded-full bg-red-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-red-800">Unavailable</span>@endunless
                                    </div>
                                    <p class="mt-2 break-all text-xs font-bold text-gray-800">{{ $facultyFile->original_filename }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ $facultyFile->file_size ? \Illuminate\Support\Number::fileSize($facultyFile->file_size) : 'Size unavailable' }} · Submitted {{ $latestVersion->created_at->diffForHumans() }}</p>
                                </div>
                            </div>
                            <div class="flex w-full shrink-0 gap-2 sm:w-auto">
                                @if ($facultyFileViewable)
                                    <a href="{{ route('topics.versions.files.annotations.index', [$topic, $latestVersion, $facultyFile]) }}" class="inline-flex flex-1 items-center justify-center rounded-xl bg-amber-500 px-4 py-2.5 text-xs font-black text-amber-950 hover:bg-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 sm:flex-none">{{ $facultyFileAnnotationCount > 0 ? 'Annotations ('.$facultyFileAnnotationCount.')' : 'Annotate PDF' }}</a>
                                @endif
                                @if ($facultyFileViewable)
                                    <a href="{{ route('topics.versions.files.view', [$topic, $latestVersion, $facultyFile]) }}" target="_blank" rel="noopener" class="inline-flex flex-1 items-center justify-center rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:flex-none">View PDF</a>
                                @endif
                                @if ($facultyFileAvailable)
                                    <a href="{{ route('topics.versions.files.download', [$topic, $latestVersion, $facultyFile]) }}" class="inline-flex flex-1 items-center justify-center rounded-xl bg-gray-900 px-4 py-2.5 text-xs font-bold text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2 sm:flex-none">Download</a>
                                @endif
                            </div>
                        </div>

                        @if ($researchHeadCopies->isNotEmpty())
                            <div class="mt-4 space-y-2 border-t border-gray-100 pt-4">
                                <p class="text-[10px] font-black uppercase tracking-wider text-cyan-800">Research Head copies</p>
                                @foreach ($researchHeadCopies as $researchHeadCopy)
                                    @php
                                        $copyAvailable = $availableFileIds->contains($researchHeadCopy->id);
                                        $copyViewable = $viewableFileIds->contains($researchHeadCopy->id);
                                        $isSignedCopy = ($researchHeadCopy->source_data['purpose'] ?? null) === \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED;
                                    @endphp
                                    <div class="flex flex-col gap-3 rounded-xl border border-cyan-100 bg-cyan-50/60 p-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="flex min-w-0 items-start gap-3">
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $isSignedCopy ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }} text-[9px] font-black">{{ $isSignedCopy ? 'SIGN' : 'REV' }}</span>
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2"><p class="break-all text-xs font-black text-gray-900">{{ $researchHeadCopy->original_filename }}</p><span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase {{ $isSignedCopy ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">{{ $researchHeadCopy->headUploadPurposeLabel() }}</span></div>
                                                <p class="mt-1 text-[11px] text-gray-500">Uploaded by {{ $researchHeadCopy->uploadedBy?->name ?? 'Research Head' }} · {{ $researchHeadCopy->created_at->format('M j, Y g:i A') }}</p>
                                                @if ($researchHeadCopy->source_data['note'] ?? null)<p class="mt-1 text-xs leading-5 text-gray-600">{{ $researchHeadCopy->source_data['note'] }}</p>@endif
                                            </div>
                                        </div>
                                        <div class="flex w-full shrink-0 gap-2 sm:w-auto">
                                            @if ($copyViewable)<a href="{{ route('topics.versions.files.view', [$topic, $latestVersion, $researchHeadCopy]) }}" target="_blank" rel="noopener" class="inline-flex flex-1 items-center justify-center rounded-lg border border-cyan-200 bg-white px-3 py-2 text-[11px] font-bold text-cyan-800 hover:bg-cyan-50 sm:flex-none">View</a>@endif
                                            @if ($copyAvailable)<a href="{{ route('topics.versions.files.download', [$topic, $latestVersion, $researchHeadCopy]) }}" class="inline-flex flex-1 items-center justify-center rounded-lg bg-cyan-800 px-3 py-2 text-[11px] font-bold text-white hover:bg-cyan-900 sm:flex-none">Download</a>@endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <form action="{{ route('topics.head-uploads.store', $topic) }}" method="POST" enctype="multipart/form-data" class="mt-4 grid gap-3 border-t border-gray-100 pt-4 lg:grid-cols-[170px_minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-end">
                            @csrf
                            <input type="hidden" name="source_file_id" value="{{ $facultyFile->id }}">
                            <label class="block text-[11px] font-bold text-gray-600">Purpose
                                <select name="purpose" required class="mt-1 block w-full rounded-xl border-gray-200 text-xs font-semibold">
                                    <option value="revision" @selected($selectedPurpose === 'revision')>For revision</option>
                                    <option value="signed" @selected($selectedPurpose === 'signed')>Signed copy</option>
                                </select>
                            </label>
                            <label class="block text-[11px] font-bold text-gray-600">Reviewed file <span class="text-red-600">Required</span>
                                <input name="review_file" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx" required class="mt-1 block w-full rounded-xl border border-gray-200 p-2 text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-gray-700 hover:file:bg-gray-200">
                            </label>
                            <label class="block text-[11px] font-bold text-gray-600">Note (optional)
                                <input name="note" type="text" maxlength="2000" value="{{ $isCurrentForm ? old('note') : '' }}" placeholder="What changed, who signed, or why it is needed" class="mt-1 block w-full rounded-xl border-gray-200 text-xs">
                            </label>
                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-cyan-700 px-5 py-2.5 text-xs font-black text-white transition hover:bg-cyan-800 focus:outline-none focus:ring-2 focus:ring-cyan-700 focus:ring-offset-2 lg:w-auto">Upload copy</button>
                        </form>
                    </article>
                @empty
                    <div class="p-8 text-center">
                        <p class="text-sm font-black text-gray-800">No faculty-submitted files are available</p>
                        <p class="mt-1 text-xs text-gray-500">A submitted proposal version is required before the Research Head can attach reviewed files.</p>
                    </div>
                @endforelse
            </div>
        </section>

        @if ($headUploadsBySource->get(0, collect())->isNotEmpty())
            <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 sm:p-6">
                <h3 class="text-sm font-black text-amber-900">Earlier unlinked Research Head uploads</h3>
                <p class="mt-1 text-xs leading-5 text-amber-800">These files were uploaded before exact faculty-file linking was available. They remain preserved in the proposal audit trail.</p>
                <div class="mt-3 space-y-2">
                    @foreach ($headUploadsBySource->get(0) as $unlinkedCopy)
                        <p class="break-all rounded-xl bg-white px-3 py-2 text-xs font-semibold text-gray-700">{{ $unlinkedCopy->original_filename }}</p>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="rounded-2xl border border-blue-200 bg-blue-50 p-5 sm:p-6">
            <p class="font-black text-blue-900">The faculty originals are always preserved.</p>
            <p class="mt-1 text-sm leading-6 text-blue-800">Files attached to a faculty original can support a revision request. Administrative and supplemental papers remain separate and never require the faculty to replace a file.</p>
        </section>
    </div>
</x-app-layout>
