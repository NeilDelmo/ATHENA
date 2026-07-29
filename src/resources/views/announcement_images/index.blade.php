<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-black tracking-tight text-gray-900 dark:text-white">Announcement Images</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Upload poster-style announcements that will appear in the faculty dashboard carousel.</p>
            </div>
            <x-back-link href="{{ route('research-calls.index') }}">Back to research calls</x-back-link>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (session('success'))
            <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700 dark:border-green-900 dark:bg-green-950/30 dark:text-green-300">{{ session('success') }}</div>
        @endif

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-labelledby="announcement-upload-heading">
            <div>
                <h3 id="announcement-upload-heading" class="text-base font-black text-gray-900 dark:text-white">Upload an announcement</h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Use a JPG, PNG, or WebP image up to 10 MB. The saved image will be added to the faculty carousel.</p>
            </div>

            <form method="POST" action="{{ route('announcement-images.store') }}" enctype="multipart/form-data" class="mt-5 grid gap-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-end" data-announcement-image-form>
                @csrf
                <label for="announcement-image" data-announcement-image-dropzone tabindex="0" class="group flex min-h-64 cursor-pointer flex-col items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed border-red-200 bg-red-50/40 p-4 text-center transition hover:border-red-400 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 dark:border-red-900/70 dark:bg-red-950/20 dark:hover:border-red-700 dark:hover:bg-red-950/40">
                    <input id="announcement-image" name="image" type="file" accept="image/jpeg,image/png,image/webp" data-announcement-image class="sr-only">
                    <span data-announcement-image-empty class="flex flex-col items-center gap-3 px-5 py-8">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-red-600 shadow-sm dark:bg-slate-900 dark:text-red-300">
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5V6.75A2.25 2.25 0 015.25 4.5h13.5A2.25 2.25 0 0121 6.75v10.5a2.25 2.25 0 01-2.25 2.25H8.25M3 16.5l3.75-3.75a2.25 2.25 0 013.182 0L12 13.818m-9 2.682 2.25 2.25m12-7.5 1.5-1.5M15 8.25h.008v.008H15V8.25z" /><path stroke-linecap="round" stroke-linejoin="round" d="M3 19.5h6" /></svg>
                        </span>
                        <span>
                            <span class="block text-sm font-black text-gray-700 dark:text-slate-200">Drag and drop announcement image</span>
                            <span class="mt-1 block text-xs text-gray-400">or click to browse · Ctrl+V also works</span>
                        </span>
                    </span>
                    <img data-announcement-image-preview src="" alt="Selected announcement preview" class="hidden max-h-80 w-full rounded-xl object-contain">
                </label>

                <div class="flex flex-col gap-3 md:min-w-48">
                    <p data-announcement-image-name class="min-h-5 truncate text-xs font-bold text-gray-600 dark:text-slate-300"></p>
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-xs font-black text-white shadow-sm shadow-red-600/20 transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60">Upload announcement</button>
                    <p data-announcement-image-status role="status" class="hidden text-xs font-semibold text-green-700 dark:text-green-300"></p>
                    @if ($errors->any())
                        <p class="rounded-xl bg-red-50 p-3 text-xs font-semibold text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ $errors->first() }}</p>
                    @endif
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-labelledby="uploaded-announcements-heading">
            <div class="flex items-center justify-between gap-4 border-b border-gray-100 px-5 py-4 dark:border-slate-800">
                <div>
                    <h3 id="uploaded-announcements-heading" class="text-base font-black text-gray-900 dark:text-white">Uploaded announcements</h3>
                    <p class="mt-0.5 text-xs text-gray-400">These images are included in the faculty dashboard carousel.</p>
                </div>
                <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-black text-gray-600 dark:bg-slate-800 dark:text-slate-300">{{ $announcementImages->count() }}</span>
            </div>

            <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($announcementImages as $announcementImage)
                    <article class="overflow-hidden rounded-2xl border border-gray-200 bg-gray-50 dark:border-slate-700 dark:bg-slate-950">
                        <div class="flex h-72 items-center justify-center bg-white p-3 dark:bg-slate-900">
                            <img src="{{ route('announcement-images.show', $announcementImage) }}" alt="Uploaded announcement image" class="h-full w-full object-contain" loading="lazy" decoding="async">
                        </div>
                        <div class="flex items-center justify-between gap-3 border-t border-gray-200 p-3 dark:border-slate-700">
                            <p class="min-w-0 truncate text-[11px] font-semibold text-gray-500 dark:text-slate-400">{{ basename($announcementImage->image_path) }}</p>
                            <form method="POST" action="{{ route('announcement-images.destroy', $announcementImage) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="shrink-0 rounded-lg px-3 py-2 text-[11px] font-black text-red-600 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-red-300 dark:hover:bg-red-950/40">Remove</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-gray-300 px-6 py-12 text-center dark:border-slate-700 sm:col-span-2 lg:col-span-3">
                        <p class="text-sm font-black text-gray-700 dark:text-slate-200">No announcement images uploaded yet.</p>
                        <p class="mt-1 text-xs text-gray-400">Upload the first announcement poster above.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
