@props(['inputName', 'accept', 'multiple', 'required', 'stagedFile', 'label', 'documentType', 'fileErrors'])

<div class="space-y-2 p-4">
        @if ($stagedFile)
            <p x-show="files.length === 0" class="break-all text-xs font-medium text-emerald-800 dark:text-emerald-200">Automatically uploaded: {{ $stagedFile->original_filename }}</p>
        @endif
        <label
            for="revision_{{ $inputName }}"
            data-file-dropzone
            tabindex="0"
            @dragenter.prevent="dragEnter()"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragLeave()"
            @drop.prevent="drop($event)"
            @keydown.enter.prevent="browse()"
            @keydown.space.prevent="browse()"
            :class="dragging ? 'border-red-500 bg-red-50 dark:bg-red-950/40' : 'border-gray-200 bg-gray-50 dark:border-slate-700 dark:bg-slate-950'"
            class="flex min-h-20 cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border border-dashed p-3 text-center transition hover:border-red-400 focus:outline-none focus:ring-2 focus:ring-red-500"
        >
            <span class="sr-only">Replace {{ $label }}</span>
            <input
                id="revision_{{ $inputName }}"
                x-ref="input"
                name="{{ $inputName }}{{ $multiple ? '[]' : '' }}"
                type="file"
                accept="{{ $accept }}"
                @if ($multiple) multiple @endif
                @required($required && ! $stagedFile)
                @change="syncFiles(true)"
                class="sr-only"
            >
            <span x-show="files.length === 0" class="text-xs font-semibold text-gray-700 dark:text-slate-200">Choose or drop replacement {{ $multiple ? 'files' : 'file' }}</span>
            <span x-show="files.length > 0" x-cloak class="text-xs font-semibold text-gray-700 dark:text-slate-200">{{ $multiple ? 'Add replacement files' : 'Choose a different file' }}</span>
            <span class="text-[11px] text-gray-500 dark:text-slate-400">{{ $documentType === 'expense_breakdown' ? 'PDF' : 'DOC, DOCX or PDF' }} · Up to 25 MB{{ $multiple ? ' each' : '' }}</span>
        </label>
        <ul x-show="files.length > 0" x-cloak class="space-y-2" aria-label="Selected replacement files">
            <template x-for="(file, index) in files" :key="file.name + '-' + file.size + '-' + file.lastModified">
                <li class="flex items-center justify-between gap-3 text-xs">
                    <span class="min-w-0 truncate text-gray-600 dark:text-slate-300" x-text="file.name"></span>
                    <button type="button" @click="remove(index)" :aria-label="'Remove ' + file.name" class="shrink-0 font-semibold text-red-700 hover:underline dark:text-red-300">Remove</button>
                </li>
            </template>
        </ul>
        <p x-show="message" x-cloak role="alert" class="text-xs font-semibold text-red-700 dark:text-red-300" x-text="message"></p>
        @foreach (\Illuminate\Support\Arr::flatten($fileErrors) as $fileError)
            <p role="alert" class="text-xs font-semibold text-red-700 dark:text-red-300">{{ $fileError }}</p>
        @endforeach

</div>
