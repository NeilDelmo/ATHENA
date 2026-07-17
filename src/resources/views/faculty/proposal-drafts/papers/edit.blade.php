<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" class="text-xs font-bold text-red-600 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">← Proposal package</a>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h2 class="text-2xl font-black tracking-tight text-gray-900">{{ $paper['label'] }}</h2>
                <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $documents->count() >= $paper['min_files'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ $documents->count() >= $paper['min_files'] ? 'Complete' : 'Not started' }}</span>
            </div>
            <p class="mt-1 text-xs text-gray-500">{{ $proposalDraft->project_title }}</p>
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
    @endphp

    <div class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('success'))
            <div role="status" class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <p class="font-bold">The paper could not be saved.</p>
                <ul class="mt-1 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-base font-black text-gray-900">Paper guidance</h3>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-600">{{ $paper['description'] }}</p>
                    <p class="mt-2 text-xs text-gray-500">Accepted: {{ collect($paper['accepted_extensions'])->map(fn ($extension) => strtoupper($extension))->implode(', ') }} · Maximum 25 MB per file@if ($paper['multiple']) · {{ $paper['min_files'] }}–{{ $paper['max_files'] }} files@endif</p>
                </div>
                @if ($template || $sampleAvailable)
                    <div class="flex shrink-0 flex-wrap gap-2">
                        @if ($template)<a href="{{ route('proposal-templates.download', $template) }}" class="inline-flex rounded-xl border border-red-200 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">Download template</a>@endif
                        @if ($sampleAvailable)<a href="{{ route('proposal-samples.show', $paper['sample_slug']) }}" target="_blank" rel="noopener" class="inline-flex rounded-xl border border-gray-300 px-3 py-2 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:ring-offset-2">View sample</a>@endif
                    </div>
                @endif
            </div>
        </section>

        @if ($documents->isNotEmpty())
            <section aria-labelledby="staged-files-heading" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <h3 id="staged-files-heading" class="text-base font-black text-gray-900">Saved {{ Str::plural('file', $documents->count()) }}</h3>
                    <span class="text-xs font-bold text-gray-500">{{ $documents->count() }}/{{ $paper['max_files'] }}</span>
                </div>
                <div class="mt-4 divide-y divide-gray-100 rounded-xl border border-gray-200">
                    @foreach ($documents as $document)
                        <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="break-all text-sm font-bold text-gray-900">{{ $document->original_filename }}</p>
                                <p class="mt-1 text-[11px] text-gray-500">{{ $document->file_size ? number_format($document->file_size / 1024, 1).' KB' : 'Size unavailable' }} · Saved {{ $document->updated_at->diffForHumans() }}</p>
                            </div>
                            <div class="grid shrink-0 grid-cols-2 gap-2">
                                <a href="{{ route('faculty.proposal-drafts.papers.download', [$proposalDraft, $paper['slug'], $document]) }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-3 py-2 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:ring-offset-2">View file</a>
                                <form action="{{ route('faculty.proposal-drafts.papers.remove', [$proposalDraft, $paper['slug'], $document]) }}" method="POST" onsubmit="return confirm('Remove this staged file?')">
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
            <h3 id="upload-paper-heading" class="text-base font-black text-gray-900">{{ $paper['multiple'] && $documents->isNotEmpty() ? 'Add more files' : ($documents->isNotEmpty() ? 'Replace saved file' : 'Upload paper') }}</h3>

            @if ($remainingSlots === 0)
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">The {{ $paper['max_files'] }}-file limit has been reached. Remove a file before adding another.</div>
            @else
                <form action="{{ route('faculty.proposal-drafts.papers.update', [$proposalDraft, $paper['slug']]) }}" method="POST" enctype="multipart/form-data" class="mt-5 space-y-5">
                    @csrf
                    @method('PUT')
                    <div>
                        <label for="documents" class="block text-xs font-black uppercase tracking-wider text-gray-600">Select {{ $paper['multiple'] ? 'files' : 'a file' }} <span class="text-red-600">Required</span></label>
                        <input id="documents" name="documents[]" type="file" accept="{{ $accept }}" @if ($paper['multiple']) multiple @endif required class="mt-2 block w-full cursor-pointer rounded-xl border border-gray-300 bg-white p-2.5 text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-900 file:px-3 file:py-2 file:text-xs file:font-bold file:text-white hover:file:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">
                        @if ($paper['multiple'])<p class="mt-2 text-[11px] text-gray-500">You may select up to {{ $remainingSlots }} more {{ Str::plural('file', $remainingSlots) }}.</p>@endif
                        @error('documents')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                        @error('documents.*')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:justify-end">
                        <a href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" class="inline-flex w-full items-center justify-center rounded-xl border border-gray-300 px-5 py-3 text-sm font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 sm:w-auto">Cancel</a>
                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">Save changes</button>
                    </div>
                </form>
            @endif

            @if ($remainingSlots === 0 || $documents->isNotEmpty())
                <a href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" class="mt-5 inline-flex w-full items-center justify-center rounded-xl border border-gray-300 px-5 py-3 text-sm font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 sm:w-auto">Return to proposal package</a>
            @endif
        </section>
    </div>
</x-app-layout>
