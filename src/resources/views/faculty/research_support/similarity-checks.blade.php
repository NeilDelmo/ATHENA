<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold text-gray-950 dark:text-white">Similarity checks</h2>
        <p class="mt-1 text-sm text-gray-500">{{ $isHead ? 'Manage requests and attach the Turnitin result to the submitted document version.' : 'Request a check for your submitted document and download the result here.' }}</p>
    </x-slot>
    <div class="mx-auto max-w-5xl space-y-5 py-6 sm:px-6">
        <div class="flex flex-wrap gap-4 text-sm font-semibold text-red-700 dark:text-red-300">
            @if (! $isHead)<a href="{{ route('research-support.index') }}">Research Help Facility</a>@endif
            @if (request('topic'))<a href="{{ route('similarity-checks.index') }}">All similarity checks</a>@endif
            <a href="https://www.turnitin.com/" target="_blank" rel="noopener">Open Turnitin ↗</a>
        </div>
        @if ($isHead)
            <x-turnitin-resource :is-head="true" />
        @endif
        @if (session('success'))<p role="status" class="rounded-lg bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</p>@endif
        @if ($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-sm text-red-800">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        @if (! $isHead)
            <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
                <h3 class="font-semibold text-gray-950 dark:text-white">Request a similarity check</h3>
                <p class="mt-1 text-sm text-gray-500">The research office checks the submitted document. A revised version needs its own check.</p>
                <div class="mt-4 divide-y divide-gray-100 dark:divide-slate-800">
                    @forelse ($topics as $topic)
                        @php
                            $version = $topic->versions->first();
                            $file = $version?->files->firstWhere('document_type', 'detailed_proposal');
                        @endphp
                        @if ($file)
                            <form method="POST" action="{{ route('similarity-checks.store', $topic) }}" class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                                @csrf
                                <input type="hidden" name="proposal_version_file_id" value="{{ $file->id }}">
                                <div><p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $topic->title }}</p><p class="mt-1 text-xs text-gray-500">document · Version {{ $version->version_number }}</p></div>
                                <button class="shrink-0 rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white">Request check</button>
                            </form>
                        @endif
                    @empty
                        <p class="py-4 text-sm text-gray-500">Submit a document first to request a similarity check.</p>
                    @endforelse
                </div>
            </section>
        @endif
        @if ($checks->isNotEmpty())
        <section id="similarity-requests" class="scroll-mt-6 space-y-3" aria-label="Similarity-check requests">
            @foreach ($checks as $check)
                @php
                    $version = $check->file->version;
                    $topic = $version->topic;
                @endphp
                <article class="rounded-xl border border-gray-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div><a href="{{ route('topics.show', $topic) }}" class="font-semibold text-gray-950 dark:text-white">{{ $topic->title }}</a><p class="mt-1 text-xs text-gray-500">document · Version {{ $version->version_number }} · Requested {{ $check->created_at->format('M j, Y') }}</p></div>
                        <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 dark:bg-slate-800 dark:text-slate-200">{{ str($check->status)->replace('_', ' ')->title() }}</span>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-4 text-sm font-semibold text-red-700 dark:text-red-300">
                        <a href="{{ route('topics.versions.files.download', [$topic, $version, $check->file]) }}">Download submitted document</a>
                        @if ($check->status === 'completed')<a href="{{ route('similarity-checks.download', $check) }}">Download similarity report</a>@endif
                    </div>
                    @if ($check->similarity_score !== null)<p class="mt-3 text-sm text-gray-700 dark:text-slate-200">Similarity: <strong>{{ $check->similarity_score }}%</strong> · For review; this is not an approval or rejection.</p>@endif
                    @if ($check->notes)<p class="mt-3 whitespace-pre-line text-sm text-gray-600 dark:text-slate-300">{{ $check->notes }}</p>@endif
                    @if ($isHead && $check->status !== 'completed')
                        <form method="POST" action="{{ route('similarity-checks.update', $check) }}" enctype="multipart/form-data" x-data="{ status: @js($check->status === 'in_progress' ? 'completed' : 'in_progress') }" class="mt-5 space-y-3 border-t border-gray-100 pt-4 dark:border-slate-800">
                            @csrf @method('PATCH')
                            <label class="block text-sm text-gray-700 dark:text-slate-200">Update status<select name="status" x-model="status" class="mt-1 block rounded-lg border-gray-300 text-sm dark:bg-slate-800"><option value="in_progress">Check in progress</option><option value="completed">Report ready</option></select></label>
                            <div x-show="status === 'completed'" x-cloak class="grid gap-3 sm:grid-cols-2">
                                <div x-data="fileDropzone({ accept: '.pdf', maxBytes: 26214400, multiple: false })" data-similarity-report-upload>
                                    <label for="report-{{ $check->id }}" class="block text-sm text-gray-700 dark:text-slate-200">Turnitin report PDF</label>
                                    <label for="report-{{ $check->id }}" tabindex="0"
                                        @dragenter.prevent="dragEnter()" @dragover.prevent="dragging = true"
                                        @dragleave.prevent="dragLeave()" @drop.prevent="drop($event)"
                                        @keydown.enter.prevent="browse()" @keydown.space.prevent="browse()"
                                        :class="dragging ? 'border-red-500 bg-red-50 dark:bg-slate-800' : 'border-gray-300 bg-gray-50 dark:border-slate-600 dark:bg-slate-950'"
                                        class="mt-2 flex min-h-24 cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed p-4 text-center focus:outline-none focus:ring-2 focus:ring-red-500">
                                        <input id="report-{{ $check->id }}" x-ref="input" type="file" name="report" accept=".pdf,application/pdf"
                                            :required="status === 'completed'" :disabled="status !== 'completed'" @change="syncFiles(true)" class="sr-only">
                                        <span class="text-sm font-semibold text-gray-800 dark:text-slate-100">Drop the PDF here or click to browse</span>
                                        <span class="text-xs text-gray-500 dark:text-slate-400">One report per request · PDF · Up to 25 MB</span>
                                    </label>
                                    <ul x-show="files.length" x-cloak class="mt-2 space-y-2" aria-label="Selected report">
                                        <template x-for="(file, index) in files" :key="file.name">
                                            <li class="flex min-w-0 items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-800">
                                                <div class="min-w-0"><p class="truncate text-sm font-semibold text-gray-800 dark:text-slate-100" x-text="file.name"></p><p class="text-xs text-gray-500 dark:text-slate-400" x-text="formatSize(file.size) + ' · Ready to upload'"></p></div>
                                                <button type="button" @click="remove(index)" class="shrink-0 text-xs font-semibold text-red-700 dark:text-red-300">Remove</button>
                                            </li>
                                        </template>
                                    </ul>
                                    <p x-show="message" x-cloak role="alert" class="mt-2 text-xs text-red-700 dark:text-red-300" x-text="message"></p>
                                </div>
                                <label class="text-sm text-gray-700 dark:text-slate-200">Similarity percentage (optional)<input type="number" name="similarity_score" min="0" max="100" step="0.01" :disabled="status !== 'completed'" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:bg-slate-800"></label>
                            </div>
                            <label class="block text-sm text-gray-700 dark:text-slate-200">Notes for faculty (optional)<textarea name="notes" rows="2" maxlength="5000" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:bg-slate-800">{{ $check->notes }}</textarea></label>
                            <button class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white">Save update</button>
                        </form>
                    @endif
                </article>
            @endforeach
            {{ $checks->links() }}
        </section>
        @endif
    </div>
</x-app-layout>
