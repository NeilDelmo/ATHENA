@php
    $unindexedPages = $index['unindexed_pages'] ?? $manual->unindexed_pages ?? [];
    $effectiveDateIsFuture = $manual->effective_date?->isAfter(today()) ?? false;
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Review manual edition" :subtitle="$manual->title">
            <x-slot name="actions">
                <x-back-link href="{{ route('research-manuals.index') }}">Back to manuals</x-back-link>
                <a href="{{ route('research-manuals.download', $manual) }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-red-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:focus:ring-offset-slate-950">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3.75V16.5m0 0 4.5-4.5M12 16.5 7.5 12M3.75 15v3.75a1.5 1.5 0 0 0 1.5 1.5h13.5a1.5 1.5 0 0 0 1.5-1.5V15" /></svg>
                    Download PDF
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-5" data-research-manual-review>
        @if (session('success'))
            <div class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800 dark:border-emerald-900/70 dark:bg-emerald-950/30 dark:text-emerald-200" role="status">
                <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 4.5 4.5 10.5-10.5" /></svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6" aria-labelledby="edition-details-heading">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="edition-details-heading" class="text-lg font-black tracking-tight text-slate-950 dark:text-white">Edition details</h2>
                @if ($isActive)
                    <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-800 ring-1 ring-inset ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-200 dark:ring-emerald-900">Current manual</span>
                @elseif ($manual->activated_at)
                    <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">Previously activated</span>
                @else
                    <span class="inline-flex rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-800 ring-1 ring-inset ring-amber-200 dark:bg-amber-950/40 dark:text-amber-200 dark:ring-amber-900">Awaiting review</span>
                @endif
            </div>
            <dl class="mt-5 grid gap-x-6 gap-y-5 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div><dt class="text-slate-500 dark:text-slate-400">Edition</dt><dd class="mt-1 break-words font-bold text-slate-900 dark:text-white">{{ $manual->edition }}</dd></div>
                <div><dt class="text-slate-500 dark:text-slate-400">Effective date</dt><dd class="mt-1 font-bold text-slate-900 dark:text-white">{{ $manual->effective_date?->format('M j, Y') ?? 'Not recorded' }}</dd></div>
                <div><dt class="text-slate-500 dark:text-slate-400">Pages</dt><dd class="mt-1 font-bold text-slate-900 dark:text-white">{{ number_format($manual->page_count) }}</dd></div>
                <div><dt class="text-slate-500 dark:text-slate-400">Uploaded by</dt><dd class="mt-1 font-bold text-slate-900 dark:text-white">{{ $manual->uploadedBy?->name ?? 'Existing manual' }}<span class="mt-1 block text-xs font-normal text-slate-500 dark:text-slate-400">{{ $manual->created_at->format('M j, Y') }}</span></dd></div>
                <div class="sm:col-span-2 lg:col-span-4"><dt class="text-slate-500 dark:text-slate-400">Version reference</dt><dd class="mt-1 break-all text-xs font-semibold text-slate-600 dark:text-slate-300">{{ $manual->version }}</dd></div>
            </dl>
        </section>

        @if (count($unindexedPages) > 0)
            <aside class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100" aria-labelledby="missing-text-heading" role="alert">
                <div class="flex items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.3 3.9L10.4 3.7a1.875 1.875 0 0 1 3.2 0l7.7 12.95a1.875 1.875 0 0 1-1.6 2.85H4.3a1.875 1.875 0 0 1-1.6-2.85ZM12 15.75h.008v.008H12v-.008Z" /></svg>
                    <div class="min-w-0">
                        <h2 id="missing-text-heading" class="text-sm font-bold">Some pages need a PDF review</h2>
                        <p class="mt-1 max-w-3xl text-sm leading-6 text-amber-800 dark:text-amber-200">Text could not be read on the pages below. Review them in the PDF before activating this edition. Their content may be missing from research guidance.</p>
                        <ul class="mt-3 flex flex-wrap gap-2 text-xs font-bold" aria-label="Pages with unreadable text">
                            @foreach ($unindexedPages as $page)
                                <li><a href="#page={{ $page }}" x-data x-on:click.prevent="$dispatch('research-manual:page', { version: @js($manual->version), page: {{ $page }} })" class="inline-flex min-h-11 items-center rounded-lg border border-amber-200 bg-white/60 px-2.5 py-1.5 underline underline-offset-4 focus:outline-none focus:ring-2 focus:ring-amber-600 dark:border-amber-800 dark:bg-amber-950/50">Page {{ $page }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </aside>
        @endif

        <x-research-manual-reader :reader="$manualReader" class="rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900 sm:p-6" />

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6" aria-labelledby="activate-manual-heading">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] lg:gap-10">
                <div>
                    <h2 id="activate-manual-heading" class="text-lg font-black tracking-tight text-slate-950 dark:text-white">Activate this edition</h2>
                    <p class="mt-2 max-w-sm text-sm leading-6 text-slate-500 dark:text-slate-400">Activation updates the manual used for general guidance. Existing proposals keep their assigned edition.</p>
                    <p class="mt-4 max-w-sm text-sm leading-6 text-slate-500 dark:text-slate-400">Review forms and approval steps separately if the new policy changes them.</p>
                </div>
                <div class="min-w-0">
                    @if ($isActive)
                        <p class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold leading-6 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200">This edition is already the current manual for general research guidance.</p>
                        <button type="button" disabled class="mt-4 inline-flex min-h-11 cursor-default items-center justify-center rounded-xl bg-slate-100 px-5 py-3 text-sm font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-400">Current edition</button>
                    @else
                        <form method="POST" action="{{ route('research-manuals.activate', $manual) }}" x-data="{ submitting: false }" x-on:submit="if (submitting) { $event.preventDefault(); } else { submitting = true; }" x-bind:aria-busy="submitting" class="space-y-4">
                            @csrf
                            @if ($errors->any())
                                <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                                    <p class="font-bold">This edition could not be activated.</p>
                                    <ul class="mt-2 list-disc space-y-1 pl-5">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            @if ($effectiveDateIsFuture)
                                <p id="activation-date-note" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">This edition can be activated on or after its effective date, {{ $manual->effective_date->format('M j, Y') }}.</p>
                            @endif
                            <fieldset @disabled($effectiveDateIsFuture) class="space-y-4 disabled:opacity-60">
                                <legend class="sr-only">Confirm the manual review</legend>
                                <div>
                                    <label for="manual-review-confirmation" class="flex cursor-pointer items-start gap-3 text-sm leading-6 text-slate-700 dark:text-slate-200">
                                        <input id="manual-review-confirmation" name="review_confirmation" type="checkbox" value="1" required @checked(old('review_confirmation')) @error('review_confirmation') aria-invalid="true" aria-describedby="manual-review-confirmation-error" @enderror class="mt-1 h-4 w-4 shrink-0 rounded border-slate-300 text-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950">
                                        <span>I have reviewed this edition and confirm it is the approved manual for general research guidance.</span>
                                    </label>
                                    <x-input-error id="manual-review-confirmation-error" :messages="$errors->get('review_confirmation')" class="mt-2" />
                                </div>
                                @if (count($unindexedPages) > 0)
                                    <div>
                                        <label for="manual-unindexed-confirmation" class="flex cursor-pointer items-start gap-3 text-sm leading-6 text-slate-700 dark:text-slate-200">
                                            <input id="manual-unindexed-confirmation" name="acknowledge_unindexed_pages" type="checkbox" value="1" required @checked(old('acknowledge_unindexed_pages')) @error('acknowledge_unindexed_pages') aria-invalid="true" aria-describedby="manual-unindexed-confirmation-error" @enderror class="mt-1 h-4 w-4 shrink-0 rounded border-slate-300 text-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950">
                                            <span>I have reviewed the pages with unreadable text in the PDF and understand their content may be missing from guidance.</span>
                                        </label>
                                        <x-input-error id="manual-unindexed-confirmation-error" :messages="$errors->get('acknowledge_unindexed_pages')" class="mt-2" />
                                    </div>
                                @endif
                                <div class="border-t border-slate-100 pt-5 dark:border-slate-800">
                                    <button type="submit" @disabled($effectiveDateIsFuture) x-bind:disabled="submitting || @js($effectiveDateIsFuture)" @if ($effectiveDateIsFuture) aria-describedby="activation-date-note" @endif class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto dark:focus:ring-offset-slate-900">
                                        <span x-show="!submitting">Activate edition</span>
                                        <span x-show="submitting" x-cloak>Activating…</span>
                                    </button>
                                </div>
                            </fieldset>
                        </form>
                    @endif
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
