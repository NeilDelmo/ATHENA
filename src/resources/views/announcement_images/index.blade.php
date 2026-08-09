<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-red-700 dark:text-red-300">Research Office</p>
                <h2 class="mt-1 text-2xl font-black tracking-tight text-gray-950 dark:text-white">Faculty Announcements</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">Publish visual notices and control exactly when research-call posters appear.</p>
            </div>
            <x-back-link href="{{ route('research-calls.index') }}">Back to research calls</x-back-link>
        </div>
    </x-slot>

    <div class="space-y-6" data-announcement-palette="red-black-white">
        @if (session('success'))
            <div class="flex items-start gap-3 rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200">
                <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-red-700" aria-hidden="true"></span>
                <p class="font-bold">{{ session('success') }}</p>
            </div>
        @endif

        <section class="relative isolate overflow-hidden rounded-3xl bg-gray-950 px-6 py-7 text-white shadow-xl shadow-gray-950/10 sm:px-8 sm:py-9" aria-labelledby="announcement-overview-heading">
            <div class="pointer-events-none absolute -right-16 -top-24 h-64 w-64 rounded-full border-[44px] border-white/[0.04]" aria-hidden="true"></div>
            <div class="pointer-events-none absolute bottom-0 right-1/4 h-28 w-72 bg-red-700/25 blur-3xl" aria-hidden="true"></div>
            <div class="relative grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                <div class="max-w-2xl">
                    <span class="inline-flex rounded-full border border-red-400/20 bg-red-500/10 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-red-200">Visibility control</span>
                    <h3 id="announcement-overview-heading" class="mt-4 text-2xl font-black tracking-tight sm:text-3xl">No content guessing required.</h3>
                    <p class="mt-3 text-sm leading-6 text-gray-300">Link a call-for-proposals poster to its research call and ATHENA hides it automatically when submissions close. Keep general notices unlinked so they remain visible until removed.</p>
                </div>
                <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-2xl bg-white/10 text-center">
                    <div class="min-w-28 bg-white/[0.04] px-5 py-4">
                        <dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Announcements</dt>
                        <dd class="mt-1 text-2xl font-black">{{ $announcementImages->count() }}</dd>
                    </div>
                    <div class="min-w-28 bg-white/[0.04] px-5 py-4">
                        <dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Open calls</dt>
                        <dd class="mt-1 text-2xl font-black text-red-300">{{ $researchCalls->filter->isAcceptingSubmissions()->count() }}</dd>
                    </div>
                </dl>
            </div>
        </section>

        <section class="overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-labelledby="announcement-upload-heading">
            <div class="border-b border-gray-100 px-5 py-5 dark:border-slate-800 sm:px-6">
                <div class="border-l-4 border-red-700 pl-3">
                    <h3 id="announcement-upload-heading" class="text-base font-black text-gray-950 dark:text-white">Publish an announcement</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">JPG, PNG, or WebP up to 10 MB.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('announcement-images.store') }}" enctype="multipart/form-data" class="grid gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start sm:p-6" data-announcement-image-form>
                @csrf
                <label for="announcement-image" data-announcement-image-dropzone tabindex="0" class="group flex min-h-72 cursor-pointer flex-col items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed border-red-200 bg-red-50/40 p-4 text-center transition hover:border-red-500 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-700 dark:border-red-900/70 dark:bg-red-950/20 dark:hover:border-red-700">
                    <input id="announcement-image" name="image" type="file" accept="image/jpeg,image/png,image/webp" data-announcement-image class="sr-only">
                    <span data-announcement-image-empty class="flex flex-col items-center gap-3 px-5 py-8">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-red-700 shadow-sm dark:bg-slate-900 dark:text-red-300">
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5V6.75A2.25 2.25 0 015.25 4.5h13.5A2.25 2.25 0 0121 6.75v10.5a2.25 2.25 0 01-2.25 2.25H8.25M3 16.5l3.75-3.75a2.25 2.25 0 013.182 0L12 13.818m-9 2.682 2.25 2.25m12-7.5 1.5-1.5M15 8.25h.008v.008H15V8.25z" /><path stroke-linecap="round" stroke-linejoin="round" d="M3 19.5h6" /></svg>
                        </span>
                        <span>
                            <span class="block text-sm font-black text-gray-800 dark:text-slate-100">Drop or paste your announcement</span>
                            <span class="mt-1 block text-xs text-gray-400">Click to browse &middot; Ctrl+V also works</span>
                        </span>
                    </span>
                    <img data-announcement-image-preview src="" alt="Selected announcement preview" class="hidden max-h-96 w-full rounded-xl object-contain">
                </label>

                <div class="flex flex-col gap-4 rounded-2xl bg-gray-950 p-5 text-white">
                    <div>
                        <label for="announcement-research-call" class="text-xs font-black uppercase tracking-wider text-gray-300">Visibility rule</label>
                        <select id="announcement-research-call" name="research_call_id" class="mt-2 block w-full rounded-xl border-gray-700 bg-gray-900 text-sm text-white focus:border-red-500 focus:ring-red-500">
                            <option value="">General announcement &mdash; show until removed</option>
                            @foreach ($researchCalls as $researchCall)
                                <option value="{{ $researchCall->id }}" @selected((string) old('research_call_id') === (string) $researchCall->id)>
                                    {{ $researchCall->title }} ({{ ucfirst($researchCall->lifecycleStatus()) }})
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-[11px] leading-5 text-gray-400">Choose a research call for call-for-proposals artwork. It will only appear while that call accepts submissions.</p>
                    </div>

                    <div class="mt-auto border-t border-white/10 pt-4">
                        <p data-announcement-image-name class="min-h-5 truncate text-xs font-bold text-gray-300"></p>
                        <button type="submit" class="mt-3 inline-flex w-full items-center justify-center rounded-xl bg-red-700 px-5 py-3 text-xs font-black text-white shadow-sm transition hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-offset-2 focus:ring-offset-gray-950 disabled:cursor-not-allowed disabled:opacity-60">Publish announcement</button>
                        <p data-announcement-image-status role="status" class="mt-3 hidden text-xs font-semibold text-green-300"></p>
                        @if ($errors->any())
                            <p class="mt-3 rounded-xl bg-red-950 p-3 text-xs font-semibold text-red-200">{{ $errors->first() }}</p>
                        @endif
                    </div>
                </div>
            </form>
        </section>

        <section aria-labelledby="uploaded-announcements-heading">
            <div class="mb-4 flex items-end justify-between gap-4">
                <div>
                    <h3 id="uploaded-announcements-heading" class="text-lg font-black text-gray-950 dark:text-white">Published artwork</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Update an existing poster’s visibility rule at any time.</p>
                </div>
                <span class="rounded-full bg-gray-950 px-3 py-1 text-xs font-black text-white dark:bg-white dark:text-gray-950">{{ $announcementImages->count() }}</span>
            </div>

            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                @forelse ($announcementImages as $announcementImage)
                    @php
                        $isVisible = $announcementImage->researchCall === null || $announcementImage->researchCall->isAcceptingSubmissions();
                    @endphp
                    <article class="group overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:border-slate-800 dark:bg-slate-900">
                        <div class="relative flex h-72 items-center justify-center overflow-hidden bg-gray-100 p-3 dark:bg-slate-950">
                            <img src="{{ route('announcement-images.show', $announcementImage) }}" alt="Uploaded announcement image" class="h-full w-full object-contain transition duration-500 group-hover:scale-[1.02]" loading="lazy" decoding="async">
                            <span class="absolute left-3 top-3 rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider shadow-sm {{ $isVisible ? 'bg-gray-950 text-white' : 'bg-red-700 text-white' }}">
                                {{ $isVisible ? 'Visible' : 'Hidden with call' }}
                            </span>
                        </div>

                        <div class="space-y-4 border-t border-gray-200 p-4 dark:border-slate-800">
                            <div>
                                <p class="truncate text-xs font-black text-gray-900 dark:text-white">{{ basename($announcementImage->image_path) }}</p>
                                <p class="mt-1 text-[11px] text-gray-500 dark:text-slate-400">{{ $announcementImage->researchCall?->title ?? 'General announcement' }}</p>
                            </div>

                            <form method="POST" action="{{ route('announcement-images.update', $announcementImage) }}" class="space-y-2">
                                @csrf
                                @method('PATCH')
                                <label for="announcement-call-{{ $announcementImage->id }}" class="text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-400">Linked research call</label>
                                <div class="flex gap-2">
                                    <select id="announcement-call-{{ $announcementImage->id }}" name="research_call_id" class="min-w-0 flex-1 rounded-xl border-gray-200 text-xs focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                        <option value="">General announcement</option>
                                        @foreach ($researchCalls as $researchCall)
                                            <option value="{{ $researchCall->id }}" @selected($announcementImage->research_call_id === $researchCall->id)>{{ $researchCall->title }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="shrink-0 rounded-xl bg-gray-950 px-3 py-2 text-[11px] font-black text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 dark:bg-white dark:text-gray-950 dark:hover:bg-red-200">Save</button>
                                </div>
                            </form>

                            <form method="POST" action="{{ route('announcement-images.destroy', $announcementImage) }}" class="border-t border-gray-100 pt-3 dark:border-slate-800">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-[11px] font-black text-red-700 transition hover:text-red-900 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-red-300">Remove announcement</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <div class="rounded-3xl border border-dashed border-gray-300 bg-white px-6 py-14 text-center dark:border-slate-700 dark:bg-slate-900 sm:col-span-2 xl:col-span-3">
                        <div class="mx-auto h-1 w-12 rounded-full bg-red-700" aria-hidden="true"></div>
                        <p class="mt-5 text-sm font-black text-gray-800 dark:text-slate-100">No announcements published yet.</p>
                        <p class="mt-1 text-xs text-gray-400">Upload the first poster above.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
