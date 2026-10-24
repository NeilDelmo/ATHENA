@props(['reader', 'openOnLoad' => false])

@php
    $downloadUrl = $reader['pdf_url'].(str_contains($reader['pdf_url'], '?') ? '&' : '?').'download=1';
    $modalName = 'research-manual-preview-'.$reader['version'];
    $hasSummary = isset($summary);
@endphp

<div {{ $attributes->merge(['class' => 'space-y-5']) }} x-data="researchManualReader(@js($reader), @js($openOnLoad))" x-id="['manual-question']" data-research-manual-reader>
    <div @class(['grid grid-cols-[5rem_minmax(0,1fr)] items-start gap-4 lg:grid-cols-[9rem_minmax(0,1fr)] lg:gap-x-6' => $hasSummary, 'flex flex-col items-start gap-5 sm:flex-row' => ! $hasSummary])>
        <div @class(['w-full sm:row-span-3' => $hasSummary, 'contents' => ! $hasSummary])>
            <x-submitted-report-document :name="'manual-paper-'.$reader['version']" :modal-name="$modalName" :title="$reader['title']" :preview-url="$reader['pdf_url']" :download-url="$downloadUrl" viewer-label="Research manual PDF" loading-label="Loading manual…" />
        </div>
        <div @class(['contents' => $hasSummary, 'space-y-3 sm:pt-2' => ! $hasSummary])>
            @isset($summary)
                <div class="min-w-0">{{ $summary }}</div>
            @else
                <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">Open the paper to read and search the complete manual.</p>
            @endisset
            <div @class(['flex flex-wrap gap-2', 'col-span-2 sm:col-span-1 sm:col-start-2' => $hasSummary])>
                <button type="button" @click="openPreview()" data-manual-preview-button aria-haspopup="dialog" @class(['workspace-primary' => $hasSummary, 'workspace-secondary' => ! $hasSummary])>
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.5-6.75 9.75-6.75 9.75 6.75 9.75 6.75-3.5 6.75-9.75 6.75S2.25 12 2.25 12Z" /><circle cx="12" cy="12" r="3" /></svg>
                    Preview manual
                </button>
                <a href="{{ $downloadUrl }}" data-manual-download class="workspace-secondary">
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m-4-4 4 4 4-4M5 16v4h14v-4" /></svg>
                    Download PDF
                </a>
                {{ $actions ?? '' }}
            </div>
            @isset($note)
                <div class="col-span-2 sm:col-span-1 sm:col-start-2">{{ $note }}</div>
            @endisset
        </div>
    </div>

    <x-submitted-report-preview :name="$modalName" :title="$reader['title']" :description="$reader['edition']" :preview-url="$reader['pdf_url']" :download-url="$downloadUrl" viewer-label="Research manual PDF" loading-label="Loading manual…" searchable initial-page-expression="manualPage" data-research-manual-preview-modal />

    @if ($reader['guidance_url'])
        <details class="border-t border-slate-200 dark:border-slate-800" data-manual-guidance>
            <summary class="disclosure-summary px-5 py-5 text-sm font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-red-600 dark:text-white sm:px-6">Check guidance from this edition</summary>
            <div class="space-y-4 px-5 pb-6 sm:px-6">
                <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">Test chatbot answers using only this edition’s indexed passages. Review each AI answer and its citations; completing a check does not approve the answer or activate the manual. The latest ten checks remain on this page until you leave.</p>
                <div class="flex flex-wrap gap-2" aria-label="Sample guidance questions">
                    @foreach ($reader['guidance_questions'] ?? [] as $question)
                        <button type="button" x-on:click="askGuidance(@js($question))" x-bind:disabled="guidanceLoading || suiteRunning" class="min-h-11 rounded-lg border border-slate-200 px-3 py-2 text-left text-xs font-semibold text-slate-600 hover:border-red-300 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:opacity-50 dark:border-slate-700 dark:text-slate-300 dark:hover:text-red-300">{{ $question }}</button>
                    @endforeach
                </div>
                @if (($reader['guidance_questions'] ?? []) !== [])
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" x-on:click="runGuidanceChecks()" x-bind:disabled="guidanceLoading || suiteRunning" class="workspace-secondary disabled:opacity-50" data-manual-run-checks>Run {{ count($reader['guidance_questions']) }} suggested checks</button>
                        <button type="button" x-show="suiteRunning" x-cloak x-on:click="stopGuidanceChecks()" class="workspace-secondary">Stop checks</button>
                        <p x-show="suiteProgress" x-text="suiteProgress" class="text-sm text-slate-500 dark:text-slate-400" role="status"></p>
                    </div>
                @endif
                <form x-on:submit.prevent="if (!suiteRunning) askGuidance()" class="space-y-2" data-manual-guidance-form>
                    <label x-bind:for="$id('manual-question')" class="block text-sm font-semibold text-slate-800 dark:text-slate-200">Ask about this edition</label>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <input x-bind:id="$id('manual-question')" data-manual-question x-model="question" x-bind:disabled="suiteRunning" type="text" maxlength="1500" required placeholder="Enter a research policy question" class="min-h-11 min-w-0 flex-1 rounded-lg border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-red-600 focus:ring-red-600 disabled:opacity-60 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <button type="submit" x-bind:disabled="guidanceLoading || suiteRunning || !question.trim()" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-slate-900" x-text="guidanceLoading ? 'Checking…' : 'Check guidance'">Check guidance</button>
                    </div>
                </form>
                <p x-show="guidanceLoading" x-cloak role="status" class="text-sm text-slate-500 dark:text-slate-400">Checking this edition’s guidance…</p>
                <p x-show="guidanceError" x-cloak x-text="guidanceError" role="alert" class="text-sm text-red-700 dark:text-red-300"></p>
                <div class="space-y-4" data-manual-guidance-result aria-live="polite">
                    <template x-for="check in guidanceChecks" x-bind:key="check.question">
                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950/50">
                            <h3 x-text="check.question" class="text-sm font-bold text-slate-900 dark:text-white"></h3>
                            <p class="mt-1 text-xs font-bold text-slate-500 dark:text-slate-400">Edition <span x-text="check.edition"></span> · <span x-text="check.type === 'ai_response' ? 'AI answer — review cited passages' : (check.type === 'no_evidence' ? 'Insufficient indexed evidence' : 'Check unavailable')"></span></p>
                            <p x-show="check.error" x-text="check.error" class="mt-2 text-sm text-red-700 dark:text-red-300"></p>
                            <p x-text="check.reply" class="mt-2 whitespace-pre-wrap break-words text-sm leading-7 text-slate-700 dark:text-slate-200"></p>
                            <ul x-show="check.sources.length" class="mt-4 space-y-2" aria-label="Manual sources">
                                <template x-for="(source, index) in check.sources" x-bind:key="index">
                                    <li class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                                        <a x-bind:href="source.url" x-on:click.prevent="openPreview(source.page_start)" class="inline-flex min-h-11 items-center text-xs font-semibold text-red-700 underline-offset-4 hover:underline focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-red-300"><span x-text="source.reference || ('ATHENA ' + (index + 1))"></span><span class="mx-1" aria-hidden="true">·</span><span x-text="sourceLabel(source)"></span></a>
                                        <p x-text="source.content || source.excerpt || 'Open the cited PDF page to inspect this passage.'" class="whitespace-pre-wrap break-words text-sm leading-6 text-slate-600 dark:text-slate-300"></p>
                                    </li>
                                </template>
                            </ul>
                        </article>
                    </template>
                </div>
            </div>
        </details>
    @endif
</div>
