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

        <x-research-manual-checks :manual="$manual" />

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

        <section id="manual-comparison" class="space-y-5 rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900 sm:p-6" aria-labelledby="manual-comparison-heading" data-manual-comparison>
            <div>
                <h2 id="manual-comparison-heading" class="text-lg font-black tracking-tight text-slate-950 dark:text-white">Compare with the current manual</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Review differences in extracted text before activation. Formatting is ignored. Renamed or moved sections and PDF extraction changes may appear as added or removed text. Highlights include words between separate edits in a passage.</p>
            </div>
            @if ($comparisonError)
                <p role="alert" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-100">Comparison is unavailable: {{ $comparisonError }} Review both PDFs before activation.</p>
            @elseif ($isActive)
                <p class="text-sm text-slate-600 dark:text-slate-300">This is the current edition. Open an uploaded edition to compare it before activation.</p>
            @elseif ($comparison === null)
                <p class="text-sm text-slate-600 dark:text-slate-300">There is no current manual to compare. Review this edition and its guidance before first activation.</p>
            @else
                <x-research-manual-reader :reader="$currentReader">
                    <x-slot:summary>
                        <h3 class="font-bold text-slate-900 dark:text-white">Current: {{ $currentReader['title'] }}</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $currentReader['edition'] ?? 'Legacy edition' }}</p>
                    </x-slot:summary>
                </x-research-manual-reader>
                <div class="flex flex-wrap gap-2 text-xs font-semibold text-slate-600 dark:text-slate-300" aria-label="Extracted text comparison summary">
                    @foreach (['changed' => 'changed passages', 'added' => 'added passages', 'removed' => 'removed passages', 'unreadable' => 'unmatched passages needing PDF review'] as $type => $label)
                        <span class="rounded-lg bg-slate-100 px-3 py-2 dark:bg-slate-800">{{ $comparisonCounts[$type] ?? 0 }} {{ $label }}</span>
                    @endforeach
                    <span class="rounded-lg bg-slate-100 px-3 py-2 dark:bg-slate-800">{{ $comparison['unchanged_sections'] }} unchanged sections</span>
                </div>
                @if ($currentReader['unindexed_pages'] !== [] || $unindexedPages !== [])
                    <aside class="rounded-xl bg-amber-50 p-4 text-sm leading-6 text-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
                        <p class="font-bold">Unreadable pages are excluded from the comparison.</p>
                        <p>Missing extracted text does not establish that a policy was added or removed. Review these PDF pages and any unmatched passages.</p>
                        @foreach ([['reader' => $currentReader, 'label' => 'Current edition'], ['reader' => $manualReader, 'label' => 'Uploaded edition']] as $edition)
                            @if ($edition['reader']['unindexed_pages'] !== [])
                                <p class="mt-2 font-semibold">{{ $edition['label'] }}:</p>
                                <ul class="flex flex-wrap gap-x-4 gap-y-1" aria-label="{{ $edition['label'] }} unreadable pages">
                                    @foreach ($edition['reader']['unindexed_pages'] as $page)
                                        <li><a href="{{ $edition['reader']['read_url'] }}#page={{ $page }}" x-data x-on:click.prevent="$dispatch('research-manual:page', { version: @js($edition['reader']['version']), page: {{ $page }} })" class="inline-flex min-h-11 items-center underline underline-offset-4 focus:outline-none focus:ring-2 focus:ring-amber-600">Page {{ $page }}</a></li>
                                    @endforeach
                                </ul>
                            @endif
                        @endforeach
                    </aside>
                @endif
                @forelse ($comparisonChanges as $change)
                    <article class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-700">
                        <div class="flex flex-wrap items-center justify-between gap-2 bg-slate-50 px-4 py-3 dark:bg-slate-950/50">
                            <h3 class="break-words text-sm font-bold text-slate-900 dark:text-white">{{ $change['section'] }}</h3>
                            <span class="text-xs font-bold text-slate-600 dark:text-slate-300">{{ $change['source_kind'] === 'appendix' ? 'Manual appendix · ' : '' }}{{ $change['type'] === 'unreadable' ? 'Unmatched text — PDF review needed' : ucfirst($change['type']).' extracted text' }}</span>
                        </div>
                        <div class="grid divide-y divide-slate-200 dark:divide-slate-700 md:grid-cols-2 md:divide-x md:divide-y-0">
                            @foreach ([['passage' => $change['before'], 'reader' => $currentReader, 'label' => 'Current edition', 'before' => true], ['passage' => $change['after'], 'reader' => $manualReader, 'label' => 'Uploaded edition', 'before' => false]] as $side)
                                <div class="min-w-0 p-4">
                                    <p class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $side['label'] }} · {{ $side['reader']['edition'] ?? 'Legacy edition' }}</p>
                                    @if ($side['passage'])
                                        <a href="{{ $side['reader']['read_url'] }}#page={{ $side['passage']['page_start'] }}" x-data x-on:click.prevent="$dispatch('research-manual:page', { version: @js($side['reader']['version']), page: {{ $side['passage']['page_start'] }} })" class="inline-flex min-h-11 items-center text-xs font-semibold text-red-700 underline underline-offset-4 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-red-300">{{ $side['passage']['page_start'] === $side['passage']['page_end'] ? 'Page '.$side['passage']['page_start'] : 'Pages '.$side['passage']['page_start'].'–'.$side['passage']['page_end'] }}</a>
                                        <p class="break-words text-sm leading-7 text-slate-700 dark:text-slate-200">@foreach ($side['passage']['segments'] as $segment)@if ($segment['changed'])<mark @class(['bg-red-100 text-red-900 dark:bg-red-950 dark:text-red-200 line-through' => $side['before'], 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200' => ! $side['before']])>{{ $segment['text'] }}</mark>@else{{ $segment['text'] }}@endif@endforeach</p>
                                    @else
                                        <p class="mt-3 text-sm leading-6 text-slate-500 dark:text-slate-400">No matching extracted passage in this section.{{ $change['type'] === 'unreadable' ? ' Check the unreadable PDF pages before interpreting this difference.' : '' }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </article>
                @empty
                    <p class="text-sm text-slate-600 dark:text-slate-300">No differences were found in the indexed section text. Review PDF pages with unreadable text separately.</p>
                @endforelse
                {{ $comparisonChanges->links() }}
                <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">Changes to manual appendices, forms or approval steps may require separate application updates. This review does not change forms or workflows.</p>
            @endif
        </section>

        <x-research-manual-reader :reader="$manualReader" class="rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900 sm:p-6">
            <x-slot:summary>
                <h2 class="font-bold text-slate-900 dark:text-white">{{ $isActive ? 'Current edition' : 'Uploaded edition' }}: {{ $manual->title }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $manual->edition }} · {{ number_format($manual->page_count) }} pages</p>
            </x-slot:summary>
        </x-research-manual-reader>

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
