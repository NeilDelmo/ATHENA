@php
    $revisionFiles = $files->where('document_type', '!=', \App\Models\ProposalVersionFile::TYPE_HEAD_UPLOAD);
    $oldRevisionFileIds = old('revision_file_ids');
    $disableUnlessRevision = $disableUnlessRevision ?? false;
@endphp

@if ($revisionFiles->isNotEmpty())
    <div x-data="{ selectedFiles: {} }" data-revision-file-list>
        <p class="mb-4 text-sm leading-6 text-gray-600 dark:text-gray-300">Open a document to review it. To request changes to a PDF, save a highlight with a comment, then select the document.</p>
        <ul role="list" class="divide-y divide-gray-200 border-y border-gray-200 dark:divide-gray-800 dark:border-gray-800">
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

                <li
                    x-data="{ needsRevision: @js($isSelected), savedHighlightCount: @js($draftAnnotationCount), menuOpen: false }"
                    x-init="selectedFiles[{{ $file->id }}] = needsRevision; $watch('needsRevision', value => selectedFiles[{{ $file->id }}] = value)"
                    @annotation-saved.window="if (Number($event.detail.fileId) === {{ $file->id }}) { savedHighlightCount = Number($event.detail.annotationCount); needsRevision = savedHighlightCount > 0; }"
                    :class="needsRevision ? 'bg-red-50/60 dark:bg-red-950/20' : ''"
                    class="px-2 py-4 transition-colors sm:px-3"
                    id="file-review-card-{{ $file->id }}"
                    data-file-review-card="{{ $file->id }}"
                >
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-2 sm:grid-cols-[minmax(0,1fr)_11rem_auto]">
                        <div class="flex min-w-0 items-center gap-3">
                            <label
                                @if ($disableUnlessRevision) x-show="decision === 'revision_requested'" x-cloak @endif
                                class="inline-flex shrink-0 items-center p-1"
                                @if ($fileViewable) :title="savedHighlightCount === 0 ? 'Save a highlight and comment before selecting this document.' : 'Include this document in the revision request.'" @endif
                            >
                                <input
                                    type="checkbox"
                                    name="revision_file_ids[]"
                                    value="{{ $file->id }}"
                                    x-model="needsRevision"
                                    @checked($isSelected)
                                    x-bind:disabled="{{ $disableUnlessRevision ? "decision !== 'revision_requested' || " : '' }}{{ $fileViewable ? 'savedHighlightCount === 0' : 'false' }}"
                                    class="h-4 w-4 rounded border-gray-300 text-red-700 focus:ring-red-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-600 dark:bg-gray-900"
                                >
                                <span class="sr-only">Mark for revision: {{ $file->label() }}</span>
                            </label>
                            <h5 class="min-w-0 text-sm font-semibold leading-6 text-gray-900 dark:text-gray-100">{{ $file->label() }}</h5>
                        </div>

                        <div class="col-start-1 row-start-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs sm:col-start-2 sm:row-start-1 sm:flex-col sm:items-start">
                            <span x-show="needsRevision" @if (! $isSelected) x-cloak @endif class="font-semibold text-red-700 dark:text-red-300" data-file-review-status>Needs revision</span>
                            <span x-show="savedHighlightCount > 0" @if ($draftAnnotationCount === 0) x-cloak @endif class="text-gray-500 dark:text-gray-400" x-text="savedHighlightCount + (savedHighlightCount === 1 ? ' comment' : ' comments')">{{ $draftAnnotationCount }} {{ $draftAnnotationCount === 1 ? 'comment' : 'comments' }}</span>
                            @if (! $fileAvailable)
                                <span class="text-amber-700 dark:text-amber-300">File unavailable</span>
                            @endif
                        </div>

                        <div class="col-start-2 row-span-2 row-start-1 flex items-center gap-1 sm:col-start-3 sm:row-span-1">
                            @if ($fileViewable)
                                <a href="{{ $annotationUrl }}" aria-label="Review {{ $file->label() }}" class="inline-flex min-h-10 items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-red-300 dark:hover:bg-red-950/40" data-review-and-highlight>
                                    Review
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" /></svg>
                                </a>
                            @elseif ($fileAvailable)
                                <a href="{{ route('topics.versions.files.download', [$topic, $latestVersion, $file]) }}" class="inline-flex min-h-10 items-center rounded-lg px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-red-300 dark:hover:bg-red-950/40" aria-label="Download {{ $file->label() }}">Download</a>
                            @endif

                            <div class="relative" @keydown.escape.stop.prevent="menuOpen = false; $refs.moreButton.focus()">
                                <button x-ref="moreButton" type="button" @click="menuOpen = !menuOpen" :aria-expanded="menuOpen" aria-controls="file-review-options-{{ $file->id }}" aria-label="More options for {{ $file->label() }}" class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-gray-400 dark:hover:bg-gray-800">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.8" /><circle cx="12" cy="12" r="1.8" /><circle cx="19" cy="12" r="1.8" /></svg>
                                </button>
                                <div id="file-review-options-{{ $file->id }}" x-show="menuOpen" x-cloak @click.outside="menuOpen = false" @focusout="if (!$el.parentElement.contains($event.relatedTarget)) menuOpen = false" class="absolute bottom-full right-0 z-20 mb-2 w-72 max-w-[calc(100vw-3rem)] rounded-xl border border-gray-200 bg-white p-1.5 shadow-lg dark:border-gray-700 dark:bg-gray-900">
                                    <p class="break-words px-3 py-2 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $file->original_filename }}</p>
                                    @if ($fileViewable)
                                        <a href="{{ route('topics.versions.files.view', [$topic, $latestVersion, $file]) }}" target="_blank" rel="noopener" @click="menuOpen = false" class="block rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-gray-200 dark:hover:bg-gray-800">Preview PDF <span class="sr-only">(opens in a new tab)</span></a>
                                        @if ($fileAvailable)
                                            <a href="{{ route('topics.versions.files.download', [$topic, $latestVersion, $file]) }}" @click="menuOpen = false" class="block rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-gray-200 dark:hover:bg-gray-800">Download</a>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    @if (! $fileViewable)
                        <label x-show="needsRevision{{ $disableUnlessRevision ? " && decision === 'revision_requested'" : '' }}" x-cloak class="mt-3 block text-sm font-semibold text-gray-700 dark:text-gray-200">
                            Revision instructions <span class="text-red-600 dark:text-red-400">Required</span>
                            <textarea
                                name="revision_file_notes[{{ $file->id }}]"
                                rows="3"
                                maxlength="2000"
                                :required="needsRevision"
                                @if ($disableUnlessRevision) x-bind:disabled="decision !== 'revision_requested'" @endif
                                class="mt-2 block w-full rounded-xl border-gray-300 text-sm leading-6 focus:border-red-600 focus:ring-red-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                placeholder="Name the sheet, cell range, section, or other exact part that must change."
                            >{{ old('revision_file_notes.'.$file->id) }}</textarea>
                            <span class="mt-2 block text-xs font-normal leading-5 text-gray-500 dark:text-gray-400">This document cannot be highlighted. Describe the exact location and change needed.</span>
                            @error('revision_file_notes.'.$file->id)<span class="mt-2 block text-xs font-semibold text-red-600">{{ $message }}</span>@enderror
                        </label>
                    @endif
                </li>
            @endforeach
        </ul>
        <p @if ($disableUnlessRevision) x-show="decision === 'revision_requested'" x-cloak @endif class="mt-3 text-sm text-gray-600 dark:text-gray-300" role="status">
            <span class="font-semibold text-gray-900 dark:text-gray-100" x-text="Object.values(selectedFiles).filter(Boolean).length + (Object.values(selectedFiles).filter(Boolean).length === 1 ? ' document marked for revision.' : ' documents marked for revision.')"></span>
            Select only the documents that need changes.
        </p>
    </div>
@else
    <p class="rounded-xl bg-gray-50 p-4 text-sm text-gray-600 dark:bg-gray-900 dark:text-gray-300">No submitted files are available for review.</p>
@endif
