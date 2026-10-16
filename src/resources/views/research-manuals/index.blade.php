<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Research Manual" subtitle="Upload and review manual editions, then choose the edition used for general research guidance.">
            <x-slot name="actions">
                <a href="#upload-manual" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-red-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:focus:ring-offset-slate-950">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V3.75m0 0L7.5 8.25M12 3.75l4.5 4.5M3.75 15v3.75a1.5 1.5 0 0 0 1.5 1.5h13.5a1.5 1.5 0 0 0 1.5-1.5V15" /></svg>
                    Upload an edition
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-5" data-research-manuals>
        @if (session('success'))
            <div class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800 dark:border-emerald-900/70 dark:bg-emerald-950/30 dark:text-emerald-200" role="status">
                <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 4.5 4.5 10.5-10.5" /></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if ($manualError ?? null)
            <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200" role="alert">
                <p class="font-bold">The current manual could not be read</p>
                <p class="mt-1 leading-6">{{ $manualError }}</p>
            </div>
        @endif

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6" aria-labelledby="current-manual-heading">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex min-w-0 items-start gap-4">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300" aria-hidden="true">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75c-2.6-1.75-5.4-2.25-9-1.5v14.25c3.6-.75 6.4-.25 9 1.5m0-14.25c2.6-1.75 5.4-2.25 9-1.5v14.25c-3.6-.75-6.4-.25-9 1.5m0-14.25V21" /></svg>
                    </span>
                    <div class="min-w-0">
                        <h2 id="current-manual-heading" class="text-sm font-bold text-slate-500 dark:text-slate-400">Current manual</h2>
                        @if ($currentManual)
                            <p class="mt-1 break-words text-xl font-black tracking-tight text-slate-950 dark:text-white">{{ $currentManual->title }}</p>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $currentManual->edition }} &middot; {{ number_format($currentManual->page_count) }} pages</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Effective {{ $currentManual->effective_date?->format('M j, Y') ?? 'date not recorded' }}</p>
                        @elseif ($legacyManual)
                            <p class="mt-1 break-words text-xl font-black tracking-tight text-slate-950 dark:text-white">{{ $legacyManual['title'] }}</p>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Existing edition &middot; {{ number_format($legacyManual['page_count']) }} pages</p>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500 dark:text-slate-400">The existing manual remains in use until you activate an uploaded edition.</p>
                        @elseif ($manualError ?? null)
                            <p class="mt-1 text-xl font-black tracking-tight text-slate-950 dark:text-white">Current manual unavailable</p>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 dark:text-slate-400">The saved manual files remain stored. Restore the current manual index before replacing this edition.</p>
                        @else
                            <p class="mt-1 text-xl font-black tracking-tight text-slate-950 dark:text-white">No active manual</p>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 dark:text-slate-400">Upload a PDF and review its contents before activating it for general research guidance.</p>
                        @endif
                    </div>
                </div>
                @if ($currentManual)
                    <a href="{{ route('research-manuals.show', $currentManual) }}" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 shadow-sm transition hover:border-red-200 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-red-900 dark:hover:text-red-300 dark:focus:ring-offset-slate-950">View current edition</a>
                @endif
            </div>
            <p class="mt-5 border-t border-slate-100 pt-4 text-sm leading-6 text-slate-500 dark:border-slate-800 dark:text-slate-400">Activating a new edition updates general guidance. Existing proposals keep the manual edition assigned to them.</p>
        </section>

        <section id="upload-manual" class="scroll-mt-6 rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-labelledby="upload-manual-heading">
            <div class="grid gap-6 p-5 sm:p-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] lg:gap-10">
                <div>
                    <h2 id="upload-manual-heading" class="text-lg font-black tracking-tight text-slate-950 dark:text-white">Upload an edition</h2>
                    <p class="mt-2 max-w-sm text-sm leading-6 text-slate-500 dark:text-slate-400">Add the approved PDF and its edition details. Uploading saves it for review; you choose when to activate it.</p>
                    <p class="mt-4 max-w-sm text-sm leading-6 text-slate-500 dark:text-slate-400">When policy changes, review ATHENA’s forms and approval steps separately.</p>
                </div>

                <form method="POST" action="{{ route('research-manuals.store') }}" enctype="multipart/form-data" x-data="{ submitting: false }" x-on:submit="if (submitting) { $event.preventDefault(); } else { submitting = true; }" x-bind:aria-busy="submitting" class="min-w-0 space-y-5">
                    @csrf
                    @if ($errors->any())
                        <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                            <p class="font-bold">The edition could not be uploaded.</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div>
                        <label for="manual-title" class="block text-sm font-bold text-slate-800 dark:text-slate-100">Manual title</label>
                        <input id="manual-title" name="title" type="text" value="{{ old('title') }}" maxlength="255" required @error('title') aria-invalid="true" aria-describedby="manual-title-error" @enderror class="mt-2 block w-full rounded-xl border-gray-300 bg-white px-3.5 py-3 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white" placeholder="Enter the official manual title">
                        <x-input-error id="manual-title-error" :messages="$errors->get('title')" class="mt-2" />
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="manual-edition" class="block text-sm font-bold text-slate-800 dark:text-slate-100">Edition</label>
                            <input id="manual-edition" name="edition" type="text" value="{{ old('edition') }}" maxlength="100" required @error('edition') aria-invalid="true" aria-describedby="manual-edition-error" @enderror class="mt-2 block w-full rounded-xl border-gray-300 bg-white px-3.5 py-3 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white" placeholder="For example, 2026 revised edition">
                            <x-input-error id="manual-edition-error" :messages="$errors->get('edition')" class="mt-2" />
                        </div>
                        <div>
                            <label for="manual-effective-date" class="block text-sm font-bold text-slate-800 dark:text-slate-100">Effective date</label>
                            <x-date-picker id="manual-effective-date" name="effective_date" :value="old('effective_date')" required class="mt-2" />
                            <x-input-error id="manual-effective-date-error" :messages="$errors->get('effective_date')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <label for="manual-pdf" class="block text-sm font-bold text-slate-800 dark:text-slate-100">Manual PDF</label>
                        <p id="manual-pdf-hint" class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">PDF only, up to 25 MB. Include selectable text where possible. Select the file again if an upload needs correction.</p>
                        <input id="manual-pdf" name="manual" type="file" accept=".pdf,application/pdf" required aria-describedby="manual-pdf-hint{{ $errors->has('manual') ? ' manual-pdf-error' : '' }}" @error('manual') aria-invalid="true" @enderror class="mt-2 block w-full cursor-pointer rounded-xl border border-slate-300 bg-white text-sm text-slate-600 file:mr-3 file:cursor-pointer file:border-0 file:bg-slate-100 file:px-4 file:py-3 file:font-semibold file:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300 dark:file:bg-slate-800 dark:file:text-white">
                        <x-input-error id="manual-pdf-error" :messages="$errors->get('manual')" class="mt-2" />
                    </div>

                    <div class="flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                        <p class="max-w-sm text-xs leading-5 text-slate-500 dark:text-slate-400">Keep this page open while the PDF is uploaded and prepared for review.</p>
                        <button type="submit" x-bind:disabled="submitting" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-wait disabled:opacity-60 dark:focus:ring-offset-slate-900">
                            <span x-show="!submitting">Upload for review</span>
                            <span x-show="submitting" x-cloak>Preparing edition…</span>
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-labelledby="edition-history-heading">
            <div class="border-b border-slate-200 px-5 py-5 dark:border-slate-800 sm:px-6">
                <h2 id="edition-history-heading" class="text-lg font-black tracking-tight text-slate-950 dark:text-white">Edition history</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Uploaded editions remain available for review and reference.</p>
            </div>
            @if ($manuals->isNotEmpty())
                <div class="overflow-x-auto focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-red-600" role="region" aria-label="Research manual editions" tabindex="0">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <caption class="sr-only">Research manual editions, effective dates, review status, and PDF downloads</caption>
                        <thead class="border-b border-slate-200 bg-slate-50 text-xs font-bold text-slate-500 dark:border-slate-800 dark:bg-slate-950/50 dark:text-slate-400">
                            <tr>
                                <th scope="col" class="px-6 py-3">Manual and edition</th>
                                <th scope="col" class="px-4 py-3">Effective date</th>
                                <th scope="col" class="px-4 py-3">Uploaded</th>
                                <th scope="col" class="px-4 py-3">Status</th>
                                <th scope="col" class="px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($manuals as $manual)
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-950/25">
                                    <th scope="row" class="max-w-xs px-6 py-4 font-normal">
                                        <p class="break-words font-bold text-slate-950 dark:text-white">{{ $manual->title }}</p>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $manual->edition }} &middot; {{ number_format($manual->page_count) }} pages</p>
                                    </th>
                                    <td class="whitespace-nowrap px-4 py-4 text-slate-600 dark:text-slate-300">{{ $manual->effective_date?->format('M j, Y') ?? 'Not recorded' }}</td>
                                    <td class="px-4 py-4 text-slate-600 dark:text-slate-300">
                                        <p class="whitespace-nowrap">{{ $manual->created_at->format('M j, Y') }}</p>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $manual->uploadedBy?->name ?? 'Existing manual' }}</p>
                                    </td>
                                    <td class="px-4 py-4">
                                        @if ($currentManual?->is($manual))
                                            <span class="inline-flex whitespace-nowrap rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-800 ring-1 ring-inset ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-200 dark:ring-emerald-900">Current</span>
                                        @elseif ($manual->activated_at)
                                            <span class="inline-flex whitespace-nowrap rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">Previously activated</span>
                                        @else
                                            <span class="inline-flex whitespace-nowrap rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-800 ring-1 ring-inset ring-amber-200 dark:bg-amber-950/40 dark:text-amber-200 dark:ring-amber-900">Awaiting review</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('research-manuals.show', $manual) }}" aria-label="Review {{ $manual->edition }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 transition hover:border-red-200 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 dark:border-slate-700 dark:text-slate-200 dark:hover:border-red-900 dark:hover:text-red-300">Review</a>
                                            <a href="{{ route('research-manuals.download', $manual) }}" aria-label="Download PDF for {{ $manual->edition }}" class="inline-flex min-h-11 items-center justify-center rounded-lg px-3 py-2 text-xs font-bold text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white">PDF</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($manuals->hasPages())
                    <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800 sm:px-6">{{ $manuals->links() }}</div>
                @endif
            @else
                <div class="px-6 py-12 text-center">
                    <p class="text-sm font-bold text-slate-700 dark:text-slate-200">No uploaded editions yet</p>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Upload a manual above to start its review and edition history.</p>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
