<x-app-layout>
    @php($canUseResearcherTools = Auth::user()->isUsingWorkspace('faculty_researcher'))

    <x-slot name="header">
        <x-page-header class="athena-readable" title="Research Support" />
    </x-slot>

    @if (Auth::user()->isUsingWorkspace(['faculty', 'faculty_researcher']))
        <div
            x-data="{
                activeResearchTool: @js($journalTopic ? 'journal' : 'rrl'),
                syncActiveResearchTool() {
                    const researcherTools = {
                        '#turnitin': 'turnitin',
                        '#journal-finder': 'journal',
                        '#journal-submissions': 'journal',
                    };

                    this.activeResearchTool = @js($canUseResearcherTools)
                        ? (researcherTools[window.location.hash] ?? @js($journalTopic ? 'journal' : 'rrl'))
                        : (window.location.hash === '#turnitin' ? 'turnitin' : 'rrl');
                },
            }"
            x-init="syncActiveResearchTool()"
            @hashchange.window="syncActiveResearchTool()"
        >
            <div class="athena-readable mb-6 mt-6 overflow-x-auto rounded-xl border border-gray-200 bg-white p-1.5 dark:border-slate-800 dark:bg-slate-900">
                <nav class="flex min-w-max gap-1" aria-label="Research help tools" role="tablist">
                    <button
                        type="button"
                        role="tab"
                        aria-controls="rrl-finder"
                        :aria-selected="activeResearchTool === 'rrl'"
                        @click="window.location.hash = 'rrl-finder'"
                        :class="activeResearchTool === 'rrl' ? 'bg-gray-950 text-white shadow-sm dark:bg-white dark:text-gray-950' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-950 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white'"
                        class="flex items-center gap-2 rounded-lg px-4 py-2.5 text-xs font-bold transition"
                    >
                        <x-assistant-icon />
                        Literature search
                    </button>
                    <button
                        type="button"
                        role="tab"
                        aria-controls="turnitin"
                        :aria-selected="activeResearchTool === 'turnitin'"
                        @click="window.location.hash = 'turnitin'"
                        :class="activeResearchTool === 'turnitin' ? 'bg-gray-950 text-white shadow-sm dark:bg-white dark:text-gray-950' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-950 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white'"
                        class="flex items-center gap-2 rounded-lg px-4 py-2.5 text-xs font-bold transition"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 12.8 2.3 2.2L15 9.8M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"></path></svg>
                        Turnitin
                    </button>
                    @if ($canUseResearcherTools)
                        <button
                            type="button"
                            role="tab"
                            aria-controls="journal-finder"
                            :aria-selected="activeResearchTool === 'journal'"
                            @click="window.location.hash = 'journal-finder'"
                            :class="activeResearchTool === 'journal' ? 'bg-gray-950 text-white shadow-sm dark:bg-white dark:text-gray-950' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-950 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white'"
                            class="flex items-center gap-2 rounded-lg px-4 py-2.5 text-xs font-bold transition"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 4.75A1.75 1.75 0 0 1 6.75 3h10.5A1.75 1.75 0 0 1 19 4.75v14.5A1.75 1.75 0 0 1 17.25 21H6.75A1.75 1.75 0 0 1 5 19.25V4.75Z"></path><path stroke-linecap="round" d="M8.5 7.5h7M8.5 11h7M8.5 14.5H13"></path></svg>
                            Journal Finder
                        </button>
                    @endif
                </nav>
            </div>
            <div x-show="activeResearchTool === 'rrl'" x-cloak>
                <x-rrl-finder
                    :proposal-drafts="$proposalDrafts"
                    :literature-collections="$literatureCollections"
                    :shared-literature-sources="$sharedLiteratureSources"
                />
            </div>

            <div x-show="activeResearchTool === 'turnitin'" x-cloak>
                <x-turnitin-resource />
            </div>

            @if ($canUseResearcherTools)
                <div x-show="activeResearchTool === 'journal'" x-cloak class="athena-readable mb-6">
                    @if ($journalTopic)
                        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950/30">
                            <div>
                                <p class="text-sm font-bold text-gray-950 dark:text-white">{{ $journalTopic->title }}</p>
                                <p class="mt-1 text-sm text-gray-600 dark:text-slate-300">The manuscript title and report abstract are ready. Searches and submissions stay linked to this project.</p>
                            </div>
                            <div class="flex flex-wrap gap-4 text-sm font-semibold text-red-700 dark:text-red-300">
                                <a wire:navigate href="{{ route('topics.show', $journalTopic) }}#project-monitoring" class="hover:underline">Back to project</a>
                                <a href="#journal-submissions" class="hover:underline">Submission tracker</a>
                                <a wire:navigate href="{{ route('research.dissemination.show', $journalTopic) }}#conferences" class="hover:underline">Conference shortlist</a>
                            </div>
                        </div>
                    @endif
                    <x-journal-finder
                        :endpoint="$journalTopic ? route('research.dissemination.journals.search', $journalTopic) : route('research-support.journal-search')"
                        :initial-query="$journalTopic?->title ?? ''"
                        :initial-context="$projectAbstract"
                        :can-track="$journalTopic !== null"
                        :finder-url="route('research-support.index', $journalTopic ? ['topic' => $journalTopic->id] : []).'#journal-finder'"
                        heading="Find a journal for your paper"
                        description="Search by manuscript title, topic, or abstract. ATHENA recommends journals using related indexed articles and shows the evidence behind each match."
                    />
                    @if ($journalTopic)
                        <div class="mt-6">
                            <x-journal-submission-tracker :topic="$journalTopic" :journal-submissions="$journalSubmissions" :can-track-journals="true" :return-to-finder="true" />
                        </div>
                    @endif
                </div>
            @endif
        </div>
    @endif
</x-app-layout>
