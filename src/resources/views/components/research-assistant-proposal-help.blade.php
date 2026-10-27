@props(['compact' => false])

<section x-show="$store.researchAssistant.proposalHelpOpen" x-cloak class="mx-auto w-full max-w-4xl px-4 py-6 sm:px-6" aria-labelledby="proposal-help-heading-{{ $compact ? 'drawer' : 'workspace' }}" data-assistant-proposal-help>
    <div class="rounded-xl border border-stone-300 bg-white p-4 dark:border-stone-700 dark:bg-stone-900 sm:p-5">
        <h4 id="proposal-help-heading-{{ $compact ? 'drawer' : 'workspace' }}" class="text-lg font-semibold text-stone-950 dark:text-stone-100">How can Athena help?</h4>
        <p class="mt-2 break-words text-sm font-medium text-red-900 dark:text-red-200" x-text="$store.researchAssistant.reviewTargetLabel()"></p>
        <p class="mt-2 text-sm leading-6 text-stone-700 dark:text-stone-300">Choose a starting point, or ask your own question below. Suggestions use this proposal’s saved records; you decide what to change.</p>
        <p class="mt-2 text-xs leading-5 text-stone-600 dark:text-stone-300">Access follows your current workspace and the proposals you’re permitted to view.</p>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <template x-for="option in $store.researchAssistant.proposalHelpOptions()" :key="option.key">
                <button type="button" @click="$store.researchAssistant.startProposalHelp(option, $event.currentTarget)" :disabled="$store.researchAssistant.reviewLaunchBusy() || !!$store.researchAssistant.draft.trim()" class="flex min-h-11 items-start gap-3 rounded-lg border border-stone-300 bg-stone-50 px-4 py-3 text-left hover:border-red-800 hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-stone-600 dark:bg-stone-800 dark:hover:border-red-300 dark:hover:bg-stone-900">
                    <x-assistant-icon />
                    <span><span class="block text-sm font-semibold text-stone-950 dark:text-stone-100" x-text="option.label"></span><span class="mt-1 block text-xs leading-5 text-stone-600 dark:text-stone-300" x-text="option.description"></span></span>
                </button>
            </template>
        </div>
    </div>
</section>
