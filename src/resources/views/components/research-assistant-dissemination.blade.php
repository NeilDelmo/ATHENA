<template x-if="message.dissemination">
    <section class="mt-4 grid gap-3" aria-label="Venue search results">
        <template x-if="message.dissemination.needs_project">
            <button type="button" @click="$store.researchAssistant.focusDisseminationProjectPicker()" x-show="$store.researchAssistant.hasContextOptions()" class="min-h-11 justify-self-start rounded-lg border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 dark:border-slate-700 dark:text-slate-200">Choose a project</button>
        </template>
        <template x-for="result in message.dissemination.results" :key="result.id">
            <article class="min-w-0 rounded-xl border border-gray-200 p-4 dark:border-slate-700">
                <p class="break-words font-semibold text-gray-900 dark:text-white" x-text="result.title"></p>
                <p class="mt-1 break-words text-xs leading-5 text-gray-500 dark:text-slate-400" x-text="result.detail"></p>
                <p class="mt-2 break-words text-xs leading-5" x-text="result.description"></p>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <a x-show="result.url" :href="result.url" target="_blank" rel="noopener noreferrer" class="text-xs font-semibold text-red-700 underline underline-offset-4 dark:text-red-300">View source</a>
                    <button type="button" @click="$store.researchAssistant.selectDisseminationResult(message, result.id)" :disabled="result.saved || $store.researchAssistant.disseminationSaving || $store.researchAssistant.isLoading" class="min-h-11 rounded-lg bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 disabled:opacity-50 dark:bg-slate-800 dark:text-slate-100" x-text="result.saved ? 'Saved to project' : 'Review and add'"></button>
                </div>
            </article>
        </template>
        <template x-if="$store.researchAssistant.selectedDisseminationResult(message)">
            <div class="grid gap-3 rounded-xl bg-gray-50 p-4 dark:bg-slate-800/60">
                <p class="text-xs font-semibold text-gray-900 dark:text-white">Confirm shortlist entry</p>
                <p class="break-words text-sm font-medium" x-text="$store.researchAssistant.selectedDisseminationResult(message).title"></p>
                <p class="break-words text-xs leading-5">Project: <span x-text="message.dissemination.project_title"></span></p>
                <label x-show="message.dissemination.type === 'journal'" class="grid gap-1 text-xs font-medium">
                    Manuscript title
                    <input type="text" x-model="message.dissemination.manuscript_title" maxlength="500" :disabled="$store.researchAssistant.disseminationSaving" class="w-full rounded-lg border-gray-300 bg-white text-sm text-gray-900 focus:border-red-500 focus:ring-red-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                </label>
                <p class="text-xs leading-5 text-gray-500 dark:text-slate-400">This saves a shortlisted entry in ATHENA. Review the official venue requirements before submitting your manuscript.</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="$store.researchAssistant.confirmDisseminationResult(message)" :disabled="$store.researchAssistant.disseminationSaving || $store.researchAssistant.isLoading" class="min-h-11 rounded-lg bg-red-700 px-3 py-2 text-sm font-semibold text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 disabled:opacity-50 dark:bg-red-600" x-text="$store.researchAssistant.disseminationSaving ? 'Saving…' : 'Confirm add to project'"></button>
                    <button type="button" @click="message.dissemination.selected_index = null" :disabled="$store.researchAssistant.disseminationSaving" class="min-h-11 rounded-lg px-3 py-2 text-sm font-semibold text-gray-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 dark:text-slate-300">Cancel</button>
                </div>
            </div>
        </template>
        <p x-show="message.dissemination.error" role="alert" class="text-xs leading-5 text-red-700 dark:text-red-300" x-text="message.dissemination.error"></p>
        <a x-show="message.dissemination.project_url" :href="message.dissemination.project_url" class="justify-self-start text-xs font-semibold text-red-700 underline underline-offset-4 dark:text-red-300">Open project shortlist</a>
    </section>
</template>
