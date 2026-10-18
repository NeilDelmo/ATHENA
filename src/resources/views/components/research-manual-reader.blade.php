@props(['reader', 'openOnLoad' => false])

@php
    $downloadUrl = $reader['pdf_url'].(str_contains($reader['pdf_url'], '?') ? '&' : '?').'download=1';
    $modalName = 'research-manual-preview-'.$reader['version'];
@endphp

<div {{ $attributes->merge(['class' => 'space-y-5']) }} x-data="researchManualReader(@js($reader), @js($openOnLoad))" x-id="['manual-question']" data-research-manual-reader>
    <div class="flex flex-col items-start gap-5 sm:flex-row">
        <x-submitted-report-document :name="'manual-paper-'.$reader['version']" :modal-name="$modalName" :title="$reader['title']" :preview-url="$reader['pdf_url']" :download-url="$downloadUrl" viewer-label="Research manual PDF" loading-label="Loading manual…" />
        <div class="space-y-3 sm:pt-2">
            <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">Open the paper to read and search the complete manual.</p>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="openPreview()" data-manual-preview-button aria-haspopup="dialog" class="workspace-secondary">Preview research manual</button>
                <a href="{{ $downloadUrl }}" data-manual-download class="workspace-secondary">Download PDF</a>
            </div>
        </div>
    </div>

    <x-submitted-report-preview :name="$modalName" :title="$reader['title']" :description="$reader['edition']" :preview-url="$reader['pdf_url']" :download-url="$downloadUrl" viewer-label="Research manual PDF" loading-label="Loading manual…" searchable initial-page-expression="manualPage" data-research-manual-preview-modal />

    @if ($reader['guidance_url'])
        <details class="border-t border-slate-200 dark:border-slate-800" data-manual-guidance>
            <summary class="cursor-pointer px-5 py-5 text-sm font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-red-600 dark:text-white sm:px-6">Check guidance from this edition</summary>
            <div class="space-y-4 px-5 pb-6 sm:px-6">
                <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">Test guidance from this edition before activation. This check does not activate or change the manual.</p>
                <div class="flex flex-wrap gap-2" aria-label="Sample guidance questions">
                    @foreach (['What are the requirements for a research proposal?', 'What ethics review is required?', 'How are research outputs submitted?'] as $question)
                        <button type="button" x-on:click="askGuidance(@js($question))" x-bind:disabled="guidanceLoading" class="rounded-lg border border-slate-200 px-3 py-2 text-left text-xs font-semibold text-slate-600 hover:border-red-300 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:opacity-50 dark:border-slate-700 dark:text-slate-300 dark:hover:text-red-300">{{ $question }}</button>
                    @endforeach
                </div>
                <form x-on:submit.prevent="askGuidance()" class="space-y-2" data-manual-guidance-form>
                    <label x-bind:for="$id('manual-question')" class="block text-sm font-semibold text-slate-800 dark:text-slate-200">Ask about this edition</label>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <input x-bind:id="$id('manual-question')" data-manual-question x-model="question" type="text" maxlength="1500" required placeholder="Enter a research policy question" class="min-h-11 min-w-0 flex-1 rounded-lg border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <button type="submit" x-bind:disabled="guidanceLoading || !question.trim()" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-slate-900" x-text="guidanceLoading ? 'Checking…' : 'Check guidance'">Check guidance</button>
                    </div>
                </form>
                <p x-show="guidanceLoading" x-cloak role="status" class="text-sm text-slate-500 dark:text-slate-400">Checking this edition’s guidance…</p>
                <p x-show="guidanceError" x-cloak x-text="guidanceError" role="alert" class="text-sm text-red-700 dark:text-red-300"></p>
                <div x-show="reply" x-cloak class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950/50" data-manual-guidance-result role="status">
                    <p class="text-xs font-bold text-slate-500 dark:text-slate-400">Guidance checked against <span x-text="guidanceEdition"></span></p>
                    <p x-text="reply" class="mt-2 whitespace-pre-wrap break-words text-sm leading-7 text-slate-700 dark:text-slate-200"></p>
                    <ul x-show="sources.length" class="mt-4 flex flex-wrap gap-2" aria-label="Manual sources">
                        <template x-for="(source, index) in sources" x-bind:key="index">
                            <li><a x-bind:href="source.url" target="_blank" rel="noopener" x-text="sourceLabel(source)" class="inline-flex min-h-11 items-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-red-700 underline-offset-4 hover:underline focus:outline-none focus:ring-2 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-900 dark:text-red-300"></a></li>
                        </template>
                    </ul>
                </div>
            </div>
        </details>
    @endif
</div>
