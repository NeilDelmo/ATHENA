<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <a href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" class="text-xs font-bold text-red-600 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">&larr; Proposal package</a>
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <h2 class="text-2xl font-black tracking-tight text-gray-900">{{ $paper['label'] }}</h2>
                    <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $documents->count() >= $paper['min_files'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ $documents->count() >= $paper['min_files'] ? 'Uploaded' : 'Upload required' }}</span>
                </div>
                <p class="mt-1 text-xs text-gray-500">{{ $proposalDraft->project_title }}</p>
            </div>
            <a href="{{ route('faculty.proposal-drafts.history.index', [$proposalDraft, 'paper' => $paper['slug']]) }}" class="inline-flex w-full shrink-0 items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-xs font-bold text-gray-800 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-700 focus:ring-offset-2 sm:w-auto">View version history</a>
        </div>
    </x-slot>

    @php
        $sampleDefinition = filled($paper['sample_slug']) ? config('proposal_samples.'.$paper['sample_slug']) : null;
        $sampleAvailable = is_array($sampleDefinition)
            && isset($sampleDefinition['path'])
            && \Illuminate\Support\Facades\Storage::disk('local')->exists($sampleDefinition['path']);
        $remainingSlots = $paper['multiple']
            ? max(0, $paper['max_files'] - $documents->count())
            : 1;
        $accept = collect($paper['accepted_extensions'])->map(fn ($extension) => '.'.$extension)->implode(',');
        $isExpenseBreakdown = $paper['slug'] === 'expense-breakdown';
        $fileLabel = 'PDF';
        $uploadHeading = $paper['multiple'] && $documents->isNotEmpty()
            ? 'Add completed files'
            : ($documents->isNotEmpty() ? 'Replace the uploaded '.$fileLabel : 'Upload the completed '.$fileLabel);
        $submitLabel = $documents->isNotEmpty() && ! $paper['multiple']
            ? 'Replace '.$fileLabel
            : 'Upload '.($paper['multiple'] ? 'files' : $fileLabel);
    @endphp

    <div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('success'))
            <div role="status" class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <p class="font-bold">The {{ $fileLabel }} could not be uploaded.</p>
                <ul class="mt-1 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_21rem] lg:items-start">
            <div class="space-y-6">
                @if ($documents->isNotEmpty())
                    <section aria-labelledby="uploaded-files-heading" class="rounded-2xl border border-green-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-wider text-green-700">Attached to this draft</p>
                                <h3 id="uploaded-files-heading" class="mt-1 text-base font-black text-gray-900">PDF attachment</h3>
                            </div>
                            <span class="rounded-full bg-green-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-green-800">Attached</span>
                        </div>

                        <div class="mt-4 divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200">
                            @foreach ($documents as $document)
                                <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="break-all text-sm font-bold text-gray-900">{{ $document->original_filename }}</p>
                                        <p class="mt-1 text-[11px] text-gray-500">{{ $document->file_size ? number_format($document->file_size / 1024, 1).' KB' : 'Size unavailable' }} &middot; Uploaded {{ $document->updated_at->diffForHumans() }}</p>
                                    </div>
                                    <div class="grid shrink-0 grid-cols-2 gap-2">
                                        <a href="{{ route('faculty.proposal-drafts.papers.download', [$proposalDraft, $paper['slug'], $document]) }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-3 py-2 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:ring-offset-2">Download</a>
                                        <form action="{{ route('faculty.proposal-drafts.papers.remove', [$proposalDraft, $paper['slug'], $document]) }}" method="POST" onsubmit="return confirm('Remove this uploaded file?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg border border-red-200 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">Remove</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                <section aria-labelledby="upload-paper-heading" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <p class="text-[10px] font-black uppercase tracking-wider text-red-700">PDF upload</p>
                    <h3 id="upload-paper-heading" class="mt-1 text-lg font-black text-gray-900">{{ $uploadHeading }}</h3>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-600">
                        @if ($isExpenseBreakdown)
                            Complete the official spreadsheet outside ATHENA, export or save it as a PDF, then attach that final PDF here. ATHENA will preserve it exactly as uploaded.
                        @else
                            Complete the required file outside ATHENA, then upload the finished copy here for inclusion in your proposal package.
                        @endif
                    </p>

                    @if ($remainingSlots === 0)
                        <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">The {{ $paper['max_files'] }}-file limit has been reached. Remove a file before adding another.</div>
                    @else
                        <form action="{{ route('faculty.proposal-drafts.papers.update', [$proposalDraft, $paper['slug']]) }}" method="POST" enctype="multipart/form-data" class="mt-6 space-y-5">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="document_version" value="{{ $documents->first()?->lock_version ?? 0 }}">

                            <div>
                                <label for="documents" class="block text-xs font-black uppercase tracking-wider text-gray-700">Choose completed PDF <span class="text-red-600">Required</span></label>
                                <div class="mt-2 rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-4 sm:p-5">
                                    <input id="documents" name="documents[]" type="file" accept="{{ $accept }}" @if ($paper['multiple']) multiple @endif required class="block w-full cursor-pointer rounded-xl border border-gray-300 bg-white p-2.5 text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-900 file:px-3 file:py-2 file:text-xs file:font-bold file:text-white hover:file:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">
                                    <p class="mt-3 text-[11px] leading-5 text-gray-500">
                                        {{ collect($paper['accepted_extensions'])->map(fn ($extension) => strtoupper($extension))->implode(' or ') }} only &middot; Maximum 25 MB per file
                                        @if ($paper['multiple']) &middot; Up to {{ $remainingSlots }} more {{ Str::plural('file', $remainingSlots) }} @endif
                                    </p>
                                </div>
                                @error('documents')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                                @error('documents.*')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
                                <a href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" class="inline-flex w-full items-center justify-center rounded-xl border border-gray-300 px-5 py-3 text-sm font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 sm:w-auto">Return to proposal package</a>
                                <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">{{ $submitLabel }}</button>
                            </div>
                        </form>
                    @endif

                    @if ($remainingSlots === 0)
                        <a href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" class="mt-5 inline-flex w-full items-center justify-center rounded-xl border border-gray-300 px-5 py-3 text-sm font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 sm:w-auto">Return to proposal package</a>
                    @endif
                </section>
            </div>

            <aside aria-labelledby="upload-guidance-heading" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6 lg:sticky lg:top-6">
                <h3 id="upload-guidance-heading" class="text-base font-black text-gray-900">How this paper works</h3>
                <p class="mt-2 text-sm leading-6 text-gray-600">{{ $paper['description'] }}</p>

                <ol class="mt-5 space-y-4">
                    <li class="flex gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gray-900 text-xs font-black text-white">1</span>
                        <div><p class="text-sm font-bold text-gray-900">Get the official format</p><p class="mt-1 text-xs leading-5 text-gray-500">Download the template or use the latest copy supplied by the Research Office.</p></div>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gray-900 text-xs font-black text-white">2</span>
                        <div><p class="text-sm font-bold text-gray-900">Complete and export it</p><p class="mt-1 text-xs leading-5 text-gray-500">Fill in the official spreadsheet using the appropriate desktop application, then export the finished copy as PDF.</p></div>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gray-900 text-xs font-black text-white">3</span>
                        <div><p class="text-sm font-bold text-gray-900">Attach the finished PDF</p><p class="mt-1 text-xs leading-5 text-gray-500">This exact PDF becomes the version included when the proposal is turned in.</p></div>
                    </li>
                </ol>

                @if ($template || $sampleAvailable)
                    <div class="mt-6 grid gap-2 border-t border-gray-100 pt-5">
                        @if ($template)<a href="{{ route('proposal-templates.download', $template) }}" class="inline-flex items-center justify-center rounded-xl bg-gray-900 px-4 py-2.5 text-xs font-bold text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2">Download official template</a>@endif
                        @if ($sampleAvailable)<a href="{{ route('proposal-samples.show', $paper['sample_slug']) }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:ring-offset-2">View sample</a>@endif
                    </div>
                @endif
            </aside>
        </div>
    </div>
</x-app-layout>
