@props(['context', 'editable' => true])

<div data-assistant-report-editor data-assistant-report-context="{{ json_encode($context) }}"
    x-data="assistantReportEditor(@js(['context' => $context, 'editable' => $editable, 'unsaved' => session()->hasOldInput()]))"
    @athena-prepare-report.window="onPrepare($event)" @athena-apply-report-change.window="onApply($event)" @athena-report-navigation.window="onNavigate($event)"
    @beforeunload.window="onNavigate($event)" x-on:livewire:navigate.document="onNavigate($event)"
    class="flex flex-wrap items-center gap-2 border-b border-stone-200 pb-4 dark:border-stone-700">
    <button type="button" @click="reviewReport()" :disabled="working || !context"
        class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-stone-300 bg-white px-3 py-2 text-sm font-semibold text-stone-800 hover:bg-stone-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100">
        <x-assistant-icon />
        Check with Athena
    </button>
    <button type="button" @click="requestChanges()" :disabled="working || !context || !editable"
        class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-red-900 px-3 py-2 text-sm font-semibold text-white hover:bg-red-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 disabled:cursor-not-allowed disabled:opacity-50">
        <x-assistant-icon />
        Review suggested edits
    </button>
    <p x-show="!context" class="w-full text-xs text-stone-600 dark:text-stone-300">Save a draft first to check or revise this report.</p>
    <p x-show="!editable" class="w-full text-xs text-stone-600 dark:text-stone-300">This submitted report is read only.</p>
    <p x-show="working" role="status" class="w-full text-xs text-stone-600 dark:text-stone-300">Preparing this report…</p>
    <p x-show="notice" x-text="notice" role="status" class="w-full text-sm text-stone-700 dark:text-stone-200"></p>
    <p x-show="error" x-text="error" role="alert" class="w-full text-sm text-red-800 dark:text-red-200"></p>
</div>
