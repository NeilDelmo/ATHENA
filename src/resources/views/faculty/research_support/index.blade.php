<x-app-layout>
    @php($canUseResearcherTools = Auth::user()->isUsingWorkspace('faculty_researcher'))

    <x-slot name="header">
        <div class="athena-readable">
            <p class="text-sm font-bold uppercase tracking-wider text-red-700">Research support</p>
            <h2 class="mt-2 text-3xl font-black tracking-tight text-gray-950">Research Support</h2>
            <p class="mt-2 text-base leading-7 text-gray-600">{{ $canUseResearcherTools ? 'Find literature, review similarity, and discover suitable journals for your research.' : 'Find and save literature while preparing your research proposal.' }}</p>
        </div>
    </x-slot>

    @if (Auth::user()->isUsingWorkspace(['faculty', 'faculty_researcher']))
        <div
            x-data="{
                activeResearchTool: 'rrl',
                syncActiveResearchTool() {
                    const researcherTools = {
                        '#turnitin': 'turnitin',
                        '#journal-finder': 'journal',
                    };

                    this.activeResearchTool = @js($canUseResearcherTools)
                        ? (researcherTools[window.location.hash] ?? 'rrl')
                        : (window.location.hash === '#turnitin' ? 'turnitin' : 'rrl');
                },
            }"
            x-init="syncActiveResearchTool()"
            @hashchange.window="syncActiveResearchTool()"
        >
            <div class="athena-readable mb-4 mt-8">
                <p class="text-sm font-bold uppercase tracking-wider text-gray-500">Research tools</p>
            </div>

            <div class="athena-readable mb-6 overflow-x-auto border-b border-gray-200">
                <nav class="flex min-w-max gap-7" aria-label="Research help tools" role="tablist">
                    <button
                        type="button"
                        role="tab"
                        aria-controls="rrl-finder"
                        :aria-selected="activeResearchTool === 'rrl'"
                        @click="window.location.hash = 'rrl-finder'"
                        :class="activeResearchTool === 'rrl' ? 'border-red-700 text-red-700' : 'border-transparent text-gray-600 hover:border-red-300 hover:text-red-700'"
                        class="flex min-h-11 items-center gap-2 border-b-2 px-1 pb-3 text-sm font-bold transition"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.8 4.8 11 2l1.2 2.8L15 6l-2.8 1.2L11 10 9.8 7.2 7 6l2.8-1.2ZM16.9 13.9 18 11l1.1 2.9L22 15l-2.9 1.1L18 19l-1.1-2.9L14 15l2.9-1.1Z"></path></svg>
                        Literature Search and Source Organizer
                    </button>
                    <button
                        type="button"
                        role="tab"
                        aria-controls="turnitin"
                        :aria-selected="activeResearchTool === 'turnitin'"
                        @click="window.location.hash = 'turnitin'"
                        :class="activeResearchTool === 'turnitin' ? 'border-emerald-700 text-emerald-700' : 'border-transparent text-gray-600 hover:border-emerald-300 hover:text-emerald-700'"
                        class="flex min-h-11 items-center gap-2 border-b-2 px-1 pb-3 text-sm font-bold transition"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 12.8 2.3 2.2L15 9.8M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"></path></svg>
                        Turnitin
                    </button>
                    @if ($canUseResearcherTools)
                        <button
                            type="button"
                            role="tab"
                            aria-controls="journal-finder"
                            :aria-selected="activeResearchTool === 'journal'"
                            @click="window.location.hash = 'journal-finder'"
                            :class="activeResearchTool === 'journal' ? 'border-red-700 text-red-700' : 'border-transparent text-gray-600 hover:border-red-300 hover:text-red-700'"
                            class="flex min-h-11 items-center gap-2 border-b-2 px-1 pb-3 text-sm font-bold transition"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 4.75A1.75 1.75 0 0 1 6.75 3h10.5A1.75 1.75 0 0 1 19 4.75v14.5A1.75 1.75 0 0 1 17.25 21H6.75A1.75 1.75 0 0 1 5 19.25V4.75Z"></path><path stroke-linecap="round" d="M8.5 7.5h7M8.5 11h7M8.5 14.5H13"></path></svg>
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
                    <x-journal-finder
                        :endpoint="route('research-support.journal-search')"
                        heading="Find a journal for your paper"
                        description="Search by manuscript title, topic, or abstract. ATHENA recommends journals using related indexed articles and shows the evidence behind each match."
                    />
                </div>
            @endif
        </div>
    @endif
</x-app-layout>
