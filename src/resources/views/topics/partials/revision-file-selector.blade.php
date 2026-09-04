@php
    $revisionFiles = $files->where('document_type', '!=', \App\Models\ProposalVersionFile::TYPE_HEAD_UPLOAD);
    $oldRevisionFileIds = old('revision_file_ids');
    $disableUnlessRevision = $disableUnlessRevision ?? false;
@endphp

@if ($revisionFiles->isNotEmpty())
    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($revisionFiles as $file)
            @php
                $draftAnnotationCount = $file->annotations->whereNull('topic_review_file_revision_id')->count();
                $fileAvailable = $availableSubmittedFileIds->contains($file->id);
                $fileViewable = $viewableSubmittedFileIds->contains($file->id);
                $canSelectHighlightedPdf = ! $fileViewable || $draftAnnotationCount > 0;
                $isSelected = is_array($oldRevisionFileIds)
                    ? in_array($file->id, $oldRevisionFileIds) && $canSelectHighlightedPdf
                    : $draftAnnotationCount > 0;
                $annotationUrl = route('topics.versions.files.annotations.index', [$topic, $latestVersion, $file]);

                if ($disableUnlessRevision) {
                    $annotationUrl .= '?decision=revision_requested';
                }
            @endphp

            <article
                x-data="{ needsRevision: @js($isSelected), savedHighlightCount: @js($draftAnnotationCount) }"
                @annotation-saved.window="if (Number($event.detail.fileId) === {{ $file->id }}) { savedHighlightCount = Number($event.detail.annotationCount); needsRevision = savedHighlightCount > 0; }"
                :class="needsRevision ? 'border-red-600 bg-red-50 dark:border-red-700 dark:bg-red-950/30' : 'border-gray-300 bg-white dark:border-gray-700 dark:bg-gray-950'"
                class="rounded-2xl border p-4 transition"
                id="file-review-card-{{ $file->id }}"
                data-file-review-card="{{ $file->id }}"
            >
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h4 class="text-base font-black text-gray-900">{{ $file->label() }}</h4>
                            <span x-show="!needsRevision" class="rounded-full border border-gray-300 bg-white px-2.5 py-1 text-xs font-black text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200" data-file-review-status>No revision</span>
                            <span x-show="needsRevision" x-cloak class="rounded-full bg-red-700 px-2.5 py-1 text-xs font-black text-white" data-file-review-status>Needs revision</span>
                            <span x-show="savedHighlightCount > 0" @if ($draftAnnotationCount === 0) x-cloak @endif class="rounded-full border border-red-300 bg-red-50 px-2.5 py-1 text-xs font-black text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200"><span x-text="savedHighlightCount">{{ $draftAnnotationCount }}</span> saved highlight(s)</span>
                        </div>
                        <p class="mt-1 break-all text-sm text-gray-600">{{ $file->original_filename }}</p>
                    </div>

                    @if ($fileViewable)
                        <span x-show="savedHighlightCount === 0" @if ($draftAnnotationCount > 0) x-cloak @endif class="inline-flex items-center gap-2 self-start rounded-xl border border-red-300 bg-white px-3 py-2 text-sm font-black text-red-700 shadow-sm">Annotate before revision</span>
                        <label x-show="savedHighlightCount > 0" @if ($draftAnnotationCount === 0) x-cloak @endif class="inline-flex cursor-pointer items-center gap-2 self-start rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm font-black text-gray-700 shadow-sm">
                            <input
                                type="checkbox"
                                name="revision_file_ids[]"
                                value="{{ $file->id }}"
                                x-model="needsRevision"
                                @checked($isSelected)
                                @if ($disableUnlessRevision) x-bind:disabled="decision !== 'revision_requested'" @endif
                                class="rounded border-gray-300 text-red-700 focus:ring-red-700"
                            >
                            Mark for revision
                        </label>
                    @else
                        <label class="inline-flex cursor-pointer items-center gap-2 self-start rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm font-black text-gray-700 shadow-sm">
                            <input
                                type="checkbox"
                                name="revision_file_ids[]"
                                value="{{ $file->id }}"
                                x-model="needsRevision"
                                @checked($isSelected)
                                @if ($disableUnlessRevision) x-bind:disabled="decision !== 'revision_requested'" @endif
                                class="rounded border-gray-300 text-red-700 focus:ring-red-700"
                            >
                            Mark for revision
                        </label>
                    @endif
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    @if ($fileViewable)
                        <a href="{{ $annotationUrl }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-red-700 px-3 py-2 text-sm font-black text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2" data-review-and-highlight>
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.688-1.688a1.5 1.5 0 1 1 2.121 2.121L10.94 14.652a4.5 4.5 0 0 1-1.897 1.124l-3.268.98.98-3.268a4.5 4.5 0 0 1 1.124-1.897l9.983-9.984Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v4.125a2.625 2.625 0 0 1-2.625 2.625H5.625A2.625 2.625 0 0 1 3 18.375V7.125A2.625 2.625 0 0 1 5.625 4.5H9.75" /></svg>
                            Review &amp; highlight
                        </a>
                        <a href="{{ route('topics.versions.files.view', [$topic, $latestVersion, $file]) }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm font-bold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">Preview PDF</a>
                    @endif
                    @if ($fileAvailable)
                        <a href="{{ route('topics.versions.files.download', [$topic, $latestVersion, $file]) }}" class="inline-flex items-center justify-center rounded-xl bg-gray-900 px-3 py-2 text-sm font-bold text-white hover:bg-gray-800">Download</a>
                    @endif
                </div>

                @if (! $fileViewable)
                    <label x-show="needsRevision" x-cloak class="mt-4 block text-sm font-bold text-gray-700">
                        Exact revision instructions <span class="text-red-600">Required</span>
                        <textarea
                            name="revision_file_notes[{{ $file->id }}]"
                            rows="3"
                            maxlength="2000"
                            :required="needsRevision"
                            @if ($disableUnlessRevision) x-bind:disabled="decision !== 'revision_requested'" @endif
                            class="mt-2 block w-full rounded-xl border-gray-300 text-sm leading-6 focus:border-red-600 focus:ring-red-600"
                            placeholder="Name the sheet, cell range, section, or other exact part that must change."
                        >{{ old('revision_file_notes.'.$file->id) }}</textarea>
                        <span class="mt-2 block text-xs font-normal leading-5 text-gray-500">This file cannot be highlighted in the PDF viewer, so give the faculty member a precise location and required change.</span>
                        @error('revision_file_notes.'.$file->id)<span class="mt-2 block text-xs font-semibold text-red-600">{{ $message }}</span>@enderror
                    </label>
                @endif
            </article>
        @endforeach
    </div>
@else
    <p class="rounded-xl bg-gray-50 p-4 text-sm text-gray-600">No submitted files are available for review.</p>
@endif
