@props([
    'id',
    'name',
    'label',
    'accept',
    'multiple' => false,
    'required' => false,
    'maxBytes' => 25600 * 1024,
])

@php
    $acceptedTypes = collect(explode(',', $accept))
        ->map(fn (string $type): string => str($type)->trim()->ltrim('.')->upper()->toString())
        ->join(', ');
    $maxMegabytes = (int) ceil($maxBytes / 1024 / 1024);
@endphp

<div
    x-data="fileDropzone({ accept: @js($accept), maxBytes: {{ $maxBytes }}, multiple: @js((bool) $multiple) })"
    @paste="paste($event)"
    {{ $attributes->merge(['class' => 'min-w-0']) }}
>
    <label for="{{ $id }}" class="text-[11px] font-bold text-gray-500">
        {{ $label }}
        @if ($required)
            <span class="text-red-600">Required</span>
        @endif
    </label>

    {{ $slot }}

    <label
        for="{{ $id }}"
        data-file-dropzone
        tabindex="0"
        @dragenter.prevent="dragEnter()"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragLeave()"
        @drop.prevent="drop($event)"
        @keydown.enter.prevent="browse()"
        @keydown.space.prevent="browse()"
        :class="dragging ? 'border-red-500 bg-red-50' : 'border-gray-200 bg-gray-50'"
        class="mt-1 flex min-h-28 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed p-4 text-center transition hover:border-red-400 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500"
    >
        <input
            id="{{ $id }}"
            x-ref="input"
            name="{{ $name }}"
            type="file"
            accept="{{ $accept }}"
            @if ($multiple) multiple @endif
            @required($required)
            @change="syncFiles(true)"
            class="sr-only"
        >

        <span x-show="files.length === 0" class="flex flex-col items-center gap-1.5">
            <svg class="h-7 w-7 text-red-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V3.75m0 0L7.5 8.25M12 3.75l4.5 4.5M5.25 15.75v2.25A2.25 2.25 0 0 0 7.5 20.25h9a2.25 2.25 0 0 0 2.25-2.25v-2.25" />
            </svg>
            <span class="text-xs font-black text-gray-700">Drop {{ str($label)->lower() }} here</span>
            <span class="text-[11px] text-gray-400">or click to browse &middot; {{ $acceptedTypes }} &middot; up to {{ $maxMegabytes }} MB</span>
        </span>

        <span x-show="files.length > 0" x-cloak class="flex max-w-full flex-col items-center gap-1">
            <span class="max-w-full truncate text-xs font-black text-red-700" x-text="files.length === 1 ? files[0].name : `${files.length} files selected`"></span>
            <span class="text-[11px] font-semibold text-gray-500" x-text="files.length === 1 ? formatSize(files[0].size) : 'Drop more files or click to browse'"></span>
        </span>
    </label>

    @if ($multiple)
        <ul x-show="files.length > 0" x-cloak class="mt-2 space-y-1.5" aria-label="Selected files">
            <template x-for="(file, index) in files" :key="`${file.name}-${file.size}-${file.lastModified}`">
                <li class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2">
                    <span class="min-w-0">
                        <span class="block truncate text-[11px] font-bold text-gray-700" x-text="file.name"></span>
                        <span class="block text-[10px] text-gray-400" x-text="formatSize(file.size)"></span>
                    </span>
                    <button type="button" @click="remove(index)" class="shrink-0 text-[10px] font-black text-red-600 hover:text-red-700">Remove</button>
                </li>
            </template>
        </ul>
    @endif

    <p x-show="message" x-cloak role="alert" class="mt-2 text-[11px] font-semibold text-red-700" x-text="message"></p>
</div>
