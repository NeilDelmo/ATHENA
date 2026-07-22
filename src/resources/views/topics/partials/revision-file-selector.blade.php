@php
    $revisionFiles = $files->where('document_type', '!=', \App\Models\ProposalVersionFile::TYPE_HEAD_UPLOAD);
    $oldRevisionFileIds = old('revision_file_ids');
@endphp

@if ($revisionFiles->isNotEmpty())
    <details class="rounded-xl border border-blue-200 bg-blue-50/60 p-3" open>
        <summary class="cursor-pointer text-[11px] font-black uppercase tracking-wider text-blue-800">Files requiring revision</summary>
        <p class="mt-2 text-[11px] leading-4 text-blue-700">Select every paper the faculty must replace. Files with draft PDF annotations are selected automatically.</p>
        <div class="mt-3 space-y-3">
            @foreach ($revisionFiles as $file)
                @php
                    $draftAnnotationCount = $file->annotations->whereNull('topic_review_file_revision_id')->count();
                    $isSelected = is_array($oldRevisionFileIds)
                        ? in_array($file->id, $oldRevisionFileIds)
                        : $draftAnnotationCount > 0;
                @endphp
                <div class="rounded-xl border border-blue-100 bg-white p-3">
                    <label class="flex cursor-pointer items-start gap-2">
                        <input type="checkbox" name="revision_file_ids[]" value="{{ $file->id }}" @checked($isSelected) class="mt-0.5 rounded border-gray-300 text-blue-700 focus:ring-blue-600">
                        <span class="min-w-0">
                            <span class="flex flex-wrap items-center gap-2"><span class="block text-xs font-black text-gray-800">{{ $file->label() }}</span>@if ($draftAnnotationCount > 0)<span class="rounded-full bg-amber-100 px-2 py-0.5 text-[9px] font-black uppercase text-amber-800">{{ $draftAnnotationCount }} annotation(s)</span>@endif</span>
                            <span class="block truncate text-[10px] text-gray-400">{{ $file->original_filename }}</span>
                        </span>
                    </label>
                    <textarea name="revision_file_notes[{{ $file->id }}]" rows="2" maxlength="2000" placeholder="What should change in this file?" class="mt-2 block w-full rounded-lg border-gray-200 text-[11px]">{{ old('revision_file_notes.'.$file->id) }}</textarea>
                </div>
            @endforeach
        </div>
    </details>
@endif
