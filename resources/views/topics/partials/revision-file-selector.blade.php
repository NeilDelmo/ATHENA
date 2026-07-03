@if ($files->isNotEmpty())
    <details class="rounded-xl border border-blue-200 bg-blue-50/60 p-3" open>
        <summary class="cursor-pointer text-[11px] font-black uppercase tracking-wider text-blue-800">Files requiring revision</summary>
        <p class="mt-2 text-[11px] leading-4 text-blue-700">When requesting a revision, select every paper the faculty must replace. Add a specific note when useful.</p>
        <div class="mt-3 space-y-3">
            @foreach ($files as $file)
                <div class="rounded-xl border border-blue-100 bg-white p-3">
                    <label class="flex cursor-pointer items-start gap-2">
                        <input type="checkbox" name="revision_file_ids[]" value="{{ $file->id }}" @checked(in_array($file->id, old('revision_file_ids', []))) class="mt-0.5 rounded border-gray-300 text-blue-700 focus:ring-blue-600">
                        <span class="min-w-0"><span class="block text-xs font-black text-gray-800">{{ $file->label() }}</span><span class="block truncate text-[10px] text-gray-400">{{ $file->original_filename }}</span></span>
                    </label>
                    <textarea name="revision_file_notes[{{ $file->id }}]" rows="2" maxlength="2000" placeholder="What should change in this file?" class="mt-2 block w-full rounded-lg border-gray-200 text-[11px]">{{ old('revision_file_notes.'.$file->id) }}</textarea>
                </div>
            @endforeach
        </div>
    </details>
@endif
