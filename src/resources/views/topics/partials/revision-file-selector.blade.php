@php
    $revisionFiles = $files->where('document_type', '!=', \App\Models\ProposalVersionFile::TYPE_HEAD_UPLOAD);
    $oldRevisionFileIds = old('revision_file_ids');
@endphp

@if ($revisionFiles->isNotEmpty())
    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($revisionFiles as $file)
            @php
                $draftAnnotationCount = $file->annotations->whereNull('topic_review_file_revision_id')->count();
                $isSelected = is_array($oldRevisionFileIds)
                    ? in_array($file->id, $oldRevisionFileIds)
                    : $draftAnnotationCount > 0;
                $fileAvailable = $availableSubmittedFileIds->contains($file->id);
                $fileViewable = $viewableSubmittedFileIds->contains($file->id);
            @endphp

            <article
                x-data="{ needsRevision: @js($isSelected) }"
                :class="needsRevision ? 'border-red-600 bg-red-50 dark:border-red-700 dark:bg-red-950/30' : 'border-gray-300 bg-white dark:border-gray-700 dark:bg-gray-950'"
                class="rounded-2xl border p-4 transition"
                data-file-review-card="{{ $file->id }}"
            >
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h4 class="text-base font-black text-gray-900">{{ $file->label() }}</h4>
                            <span x-show="!needsRevision" class="rounded-full border border-gray-300 bg-white px-2.5 py-1 text-xs font-black text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200" data-file-review-status>No revision</span>
                            <span x-show="needsRevision" x-cloak class="rounded-full bg-red-700 px-2.5 py-1 text-xs font-black text-white" data-file-review-status>Needs revision</span>
                            @if ($draftAnnotationCount > 0)
                                <span class="rounded-full border border-red-300 bg-red-50 px-2.5 py-1 text-xs font-black text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ $draftAnnotationCount }} saved highlight(s)</span>
                            @endif
                        </div>
                        <p class="mt-1 break-all text-sm text-gray-600">{{ $file->original_filename }}</p>
                    </div>

                    <label class="inline-flex cursor-pointer items-center gap-2 self-start rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm font-black text-gray-700 shadow-sm">
                        <input
                            type="checkbox"
                            name="revision_file_ids[]"
                            value="{{ $file->id }}"
                            x-model="needsRevision"
                            @checked($isSelected)
                            class="rounded border-gray-300 text-red-700 focus:ring-red-700"
                        >
                        Mark for revision
                    </label>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    @if ($fileViewable)
                        <a href="{{ route('topics.versions.files.view', [$topic, $latestVersion, $file]) }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm font-bold text-gray-700 hover:bg-gray-50">View paper</a>
                        <a href="{{ route('topics.versions.files.annotations.index', [$topic, $latestVersion, $file]) }}" class="inline-flex items-center justify-center rounded-xl bg-red-700 px-3 py-2 text-sm font-black text-white hover:bg-red-800" data-highlight-paper>Highlight PDF</a>
                    @endif
                    @if ($fileAvailable)
                        <a href="{{ route('topics.versions.files.download', [$topic, $latestVersion, $file]) }}" class="inline-flex items-center justify-center rounded-xl bg-gray-900 px-3 py-2 text-sm font-bold text-white hover:bg-gray-800">Download</a>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
@else
    <p class="rounded-xl bg-gray-50 p-4 text-sm text-gray-600">No submitted files are available for review.</p>
@endif
