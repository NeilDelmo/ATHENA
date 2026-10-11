@props(['id'])

<div x-data="{ open: false }" @click.outside="open = false" @keydown.escape.stop.prevent="open = false; $refs.trigger.focus()" x-effect="if (!$store.researchAssistant.{{ $id === 'drawer' ? 'drawerOpen' : 'workspaceOpen' }}) open = false" class="static">
    <button x-ref="trigger" type="button" @click="open = !open" :aria-expanded="open" aria-controls="assistant-options-{{ $id }}" aria-label="Chat options" title="Chat options" class="inline-flex h-11 w-11 items-center justify-center rounded-xl text-gray-500 hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 dark:text-slate-400 dark:hover:bg-slate-800">
        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="19" cy="12" r="1.7"/></svg>
    </button>
    <div id="assistant-options-{{ $id }}" x-show="open" x-cloak x-transition.opacity class="absolute right-3 top-full z-30 mt-1 max-h-[min(28rem,calc(100dvh-13rem))] w-80 max-w-[calc(100%-1.5rem)] overflow-y-auto rounded-2xl border border-gray-200 bg-white p-2 shadow-xl dark:border-slate-700 dark:bg-slate-900">
        @if ($id === 'drawer')
            <button type="button" @click="$store.researchAssistant.newConversation(); open = false" class="min-h-11 w-full rounded-xl px-3 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-slate-200 dark:hover:bg-slate-800">New chat</button>
        @endif
        <button type="button" @click="open = false; $store.researchAssistant.openWorkspace(); $store.researchAssistant.openHistorySearch()" class="min-h-11 w-full rounded-xl px-3 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-slate-200 dark:hover:bg-slate-800">Search history</button>
        <template x-for="review in $store.researchAssistant.reviewStarterPrompts()" :key="review.action.review_focus">
            <button type="button" @click="$store.researchAssistant.startSuggestedReview(review, $refs.trigger); open = false" :disabled="$store.researchAssistant.reviewLaunchBusy()" :title="review.evidence" class="min-h-11 w-full rounded-xl px-3 text-left text-sm text-gray-700 hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-50 dark:text-slate-200 dark:hover:bg-slate-800" x-text="review.label"></button>
        </template>
        <button type="button" @click="$store.researchAssistant.copyConversation(); open = false" :disabled="!$store.researchAssistant.messages.length" class="min-h-11 w-full rounded-xl px-3 text-left text-sm text-gray-700 hover:bg-gray-100 disabled:opacity-40 dark:text-slate-200 dark:hover:bg-slate-800" x-text="$store.researchAssistant.copiedConversation ? 'Copied' : 'Copy chat'"></button>
        <button type="button" @click="$store.researchAssistant.exportConversation(); open = false" :disabled="!$store.researchAssistant.messages.length" class="min-h-11 w-full rounded-xl px-3 text-left text-sm text-gray-700 hover:bg-gray-100 disabled:opacity-40 dark:text-slate-200 dark:hover:bg-slate-800">Export .txt</button>
        <section aria-labelledby="assistant-privacy-{{ $id }}" class="mt-2 rounded-xl bg-gray-50 p-3 text-xs leading-5 text-gray-500 dark:bg-slate-800/60 dark:text-slate-400" data-assistant-privacy>
            <h4 id="assistant-privacy-{{ $id }}" class="mb-3 font-semibold text-gray-800 dark:text-slate-100">Privacy details</h4>
            <dl class="space-y-3">
                <div><dt class="font-medium text-gray-700 dark:text-slate-200">Chat storage</dt><dd>Chats are saved to your account.</dd></div>
                <div><dt class="font-medium text-gray-700 dark:text-slate-200">Project context</dt><dd>Athena uses the selected project’s saved records. On proposal forms, it can also use unsaved values in the field you’re editing.</dd></div>
                <div><dt class="font-medium text-gray-700 dark:text-slate-200">Sensitive information</dt><dd>Contact details are redacted. Avoid sharing confidential participant data.</dd></div>
            </dl>
        </section>
    </div>
</div>
