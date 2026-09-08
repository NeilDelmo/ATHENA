<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-2xl font-black tracking-tight text-gray-900">{{ $paper['label'] }}</h2>
                    <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $detailedProposalComplete ? 'bg-green-100 text-green-800' : ($detailedProposalDocument ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600') }}">{{ $detailedProposalComplete ? 'Complete' : ($detailedProposalDocument ? 'In progress' : 'Not started') }}</span>
                </div>
                <p class="mt-1 text-xs text-gray-500">Complete the official BatStateU-FO-RES-02 Rev. 04 form through structured inputs.</p>
            </div>
            <x-back-link fixed data-paper-cancel-exit href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}#required-pdf-attachments">Exit editor</x-back-link>
        </div>
    </x-slot>

    @php
        $projectDetailsComplete = app(\App\Support\ProposalDraftReadiness::class)->projectDetailsAreComplete($proposalDraft);
        $initialData = array_replace_recursive($sourceData, old());
        $sdgs = config('detailed_proposal.sdgs');
        $expectedOutputs = config('detailed_proposal.expected_outputs');
        $methodologyFields = config('detailed_proposal.methodology');
        $professionalTitles = config('detailed_proposal.professional_titles');
    @endphp

    <div
        class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8"
        data-paper-editor
        data-paper-draft-save="true"
        data-detailed-proposal-autosave="true"
        data-paper-project-details-complete="{{ $projectDetailsComplete ? 'true' : 'false' }}"
        data-paper-dirty="{{ $errors->any() ? 'true' : 'false' }}"
        data-paper-edit-url="{{ route('faculty.proposal-drafts.detailed-proposal.edit', $proposalDraft) }}"
        data-paper-exit-url="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}#required-pdf-attachments"
        x-on:proposal-cite-selection.window="openCitationPicker($event.detail)"
        x-data="proposalDraftDetailedProposal({
            initialData: @js($initialData),
            recheckCompletion: @js($detailedProposalDocument !== null && $detailedProposalDocument->completed_at === null),
            literatureSources: @js($literatureSources),
            initialLiteratureSourceId: @js($initialLiteratureSourceId),
            initialLiteratureAction: @js($initialLiteratureAction),
            workspacePeople: @js($workspacePeople),
            projectLeader: @js($proposalDraft->project_leader),
            proposalTitle: @js($proposalDraft->project_title),
            expectedOutputKeys: @js(array_keys($expectedOutputs)),
            methodologyKeys: @js(array_keys($methodologyFields)),
            methodologySections: @js($methodologyFields),
            methodologyImageUrlTemplate: @js(route('faculty.proposal-drafts.detailed-proposal.methodology-images.show', [$proposalDraft, '__image_id__'])),
            literatureSearchUrl: @js(route('research-support.literature-search')),
            literatureLibrarySearchUrl: @js(route('research-support.literature-library.index')),
            literatureLibrarySaveUrl: @js(route('research-support.literature-library.store')),
            literatureAttachUrlTemplate: @js(route('faculty.proposal-drafts.literature-sources.store', [$proposalDraft, '__literature_source__'])),
            literatureDraftUpdateUrlTemplate: @js(route('faculty.proposal-drafts.literature-drafts.update', [$proposalDraft, '__proposal_literature_source__'])),
            literatureSynthesisUrl: @js(route('research-support.literature-synthesis')),
            literatureFullTextPreviewUrl: @js(route('research-support.literature-full-text-preview')),
            previewUrl: @js(route('faculty.proposal-drafts.detailed-proposal.preview', $proposalDraft)),
            downloadUrl: @js(route('faculty.proposal-drafts.detailed-proposal.download', $proposalDraft)),
            updateUrl: @js(route('faculty.proposal-drafts.detailed-proposal.update', $proposalDraft)),
            csrfToken: @js(csrf_token()),
            revisionUploadUrl: @js($proposalDraft->topic_id ? route('faculty.proposal-drafts.revision-files.store', $proposalDraft) : null),
            revisionDocumentType: @js($paper['document_type']),
            revisionAttachmentLabel: @js($paper['label']),
            revisionReviewUrl: @js($proposalDraft->topic_id ? route('topics.show', $proposalDraft->topic_id).'#review-and-submit' : null),
        })"
    >
        @if (session('success'))
            <x-proposal-alert>{{ session('success') }}</x-proposal-alert>
        @endif

        @if ($errors->any())
            <x-proposal-alert type="error">
                <p class="font-bold">The Detailed Research Proposal could not be saved.</p>
                <ul class="mt-1 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-proposal-alert>
        @endif

        <div x-show="validationMessage" x-cloak role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800" x-text="validationMessage"></div>
        <x-paper-editor-submit-status />
        <x-proposal-revision-context :proposal-draft="$proposalDraft" :document-type="$paper['document_type']" />
        <x-proposal-autosave-status />
        <x-proposal-collaboration-monitor
            :loaded-version="(int) old('document_version', $detailedProposalDocument?->lock_version ?? 0)"
            :state-url="route('faculty.proposal-drafts.edit-state', [$proposalDraft, $paper['document_type'], 0])"
            :reload-url="route('faculty.proposal-drafts.detailed-proposal.edit', $proposalDraft)"
            :history-url="route('faculty.proposal-drafts.history.index', [$proposalDraft, 'paper' => $paper['slug']])"
            :label="$paper['label']"
        />

        @unless ($projectDetailsComplete)
            <div role="alert" class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                <p class="font-black">Complete Project Details first</p>
                <p class="mt-1 leading-6">Project title, dates, duration, and project leader are required before this paper can be previewed or generated. You can still save your progress as a draft.</p>
                <a href="{{ route('faculty.proposal-drafts.details.edit', $proposalDraft) }}" class="mt-3 inline-flex rounded-xl bg-amber-900 px-4 py-2.5 text-xs font-bold text-white focus:outline-none focus:ring-2 focus:ring-amber-900 focus:ring-offset-2">Complete Project Details</a>
            </div>
        @endunless

        <section data-revision-section="section-project-information" data-revision-shared-summary class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-base font-black text-gray-900">Official form source</h3>
                    <p class="mt-1 max-w-3xl text-xs leading-5 text-gray-500">The Word download is produced from the university's original DOCX. Its legal-size portrait setup, logo, borders, labels, footer page fields, privacy notice, and Research Office approval page are retained.</p>
                </div>
                <a href="{{ route('faculty.proposal-drafts.details.edit', $proposalDraft) }}" class="inline-flex shrink-0 rounded-xl border border-red-200 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">Edit shared details</a>
            </div>
            <dl class="mt-5 grid gap-4 border-t border-gray-100 pt-5 sm:grid-cols-2 lg:grid-cols-4">
                <div class="sm:col-span-2 lg:col-span-4"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">I. Research Project Title</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->project_title }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Leader</dt><dd class="mt-1 text-sm font-semibold uppercase text-gray-900">{{ $proposalDraft->project_leader }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">MOOE from Attachment B</dt><dd class="mt-1 text-sm font-semibold text-gray-900">Php {{ number_format($budgetTotals['mooe_total'], 2) }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Capital Outlay from Attachment B</dt><dd class="mt-1 text-sm font-semibold text-gray-900">Php {{ number_format($budgetTotals['co_total'], 2) }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Official form</dt><dd class="mt-1 text-sm font-semibold text-gray-900">BatStateU-FO-RES-02 Rev. 04</dd></div>
            </dl>
        </section>

        <form data-paper-form data-detailed-proposal-autosave-form x-ref="form" action="{{ route('faculty.proposal-drafts.detailed-proposal.update', $proposalDraft) }}" method="POST" enctype="multipart/form-data" class="space-y-6" novalidate>
            @csrf
            @method('PUT')
            <input type="hidden" name="document_version" value="{{ old('document_version', $detailedProposalDocument?->lock_version ?? 0) }}">
            <input type="hidden" name="draft_version" value="{{ old('draft_version', $proposalDraft->lock_version) }}">
            <input type="hidden" name="save_as_draft" value="0" data-paper-save-mode>
            <input type="hidden" name="staff" value="">
            <input type="hidden" name="literature_research_history" x-bind:value="JSON.stringify(literatureSearchHistory)">
            <input id="literature-citations" type="hidden" name="literature_citations" x-bind:value="JSON.stringify(literatureCitations)">

            <section data-revision-section="section-research-agenda" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <h3 class="text-base font-black text-gray-900">II–III. Research alignment</h3>
                <div class="mt-5">
                    <label for="research-agenda" class="block text-xs font-black uppercase tracking-wider text-gray-600">II. BatStateU Research Agenda</label>
                    <input id="research-agenda" name="research_agenda" type="text" required maxlength="500" x-model="researchAgenda" placeholder="Type the applicable BatStateU research agenda" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                </div>
                <fieldset data-revision-section="section-sdgs" data-detailed-proposal-validation-group="sdgs" tabindex="-1" class="mt-6">
                    <legend class="text-xs font-black uppercase tracking-wider text-gray-600">III. Sustainable Development Goal <span class="font-normal normal-case text-gray-500">(check all applicable SDGs)</span></legend>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($sdgs as $number => $label)
                            <label class="flex items-start gap-3 rounded-xl border border-gray-200 p-3 text-sm text-gray-800 hover:bg-gray-50">
                                <input name="sdgs[]" type="checkbox" value="{{ $number }}" x-model.number="sdgs" class="mt-0.5 rounded border-gray-300 text-red-600 focus:ring-red-600">
                                <span><strong>SDG{{ $number }}:</strong> {{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            </section>

            <section data-revision-section="section-project-team" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6">
                <div>
                    <h3 class="text-base font-black text-gray-900 dark:text-white">IV. Project leader and staff</h3>
                    <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-slate-400">Names follow the official uppercase format. Add a professional title such as Asst Prof. or Dr. when applicable.</p>
                </div>

                <div class="mt-5 grid gap-4 border-t border-gray-100 pt-5 dark:border-slate-800 lg:grid-cols-[minmax(0,1fr)_17rem]">
                    <div class="rounded-2xl border border-red-100 bg-red-50/50 p-4 dark:border-red-950/80 dark:bg-red-950/20 sm:p-5">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-xs font-black uppercase tracking-wider text-red-700 dark:text-red-300">Proposal workspace</p>
                                <h4 class="mt-1 text-sm font-black text-gray-900 dark:text-white">Add a workspace member</h4>
                                <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-slate-400">Search the available collaborators and add them directly as project staff.</p>
                            </div>
                            <span class="w-fit rounded-full bg-white px-2.5 py-1 text-[10px] font-black text-gray-600 ring-1 ring-gray-200 dark:bg-slate-900 dark:text-slate-300 dark:ring-slate-700" x-text="`${availableWorkspacePeople().length} available`"></span>
                        </div>

                        <div class="relative mt-4" x-on:click.outside="workspacePickerOpen = false">
                            <label for="workspace-person-search" class="sr-only">Search workspace members</label>
                            <div class="relative">
                                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 3.474 9.765l3.63 3.63a.75.75 0 0 0 1.06-1.06l-3.629-3.63A5.5 5.5 0 0 0 9 3.5ZM5 9a4 4 0 1 1 8 0 4 4 0 0 1-8 0Z" clip-rule="evenodd" /></svg>
                                <input id="workspace-person-search" type="search" autocomplete="off" placeholder="Search workspace members" x-model="workspacePersonQuery" x-on:focus="workspacePickerOpen = true" x-on:input="workspacePickerOpen = true" x-on:keydown.escape="workspacePickerOpen = false" role="combobox" aria-autocomplete="list" x-bind:aria-expanded="workspacePickerOpen" aria-controls="workspace-person-options" class="block w-full rounded-xl border-gray-300 bg-white py-2.5 pl-9 pr-3 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                            </div>
                            <div id="workspace-person-options" x-show="workspacePickerOpen" x-transition.origin.top x-cloak role="listbox" class="absolute z-30 mt-2 max-h-72 w-full overflow-y-auto rounded-2xl border border-gray-200 bg-white p-2 shadow-xl shadow-gray-900/10 dark:border-slate-700 dark:bg-slate-800">
                                <template x-for="person in filteredWorkspacePeople()" :key="person.key">
                                    <button type="button" role="option" x-on:click="addWorkspacePerson(person.key)" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition hover:bg-red-50 focus:bg-red-50 focus:outline-none dark:hover:bg-red-950/40 dark:focus:bg-red-950/40">
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-red-100 text-xs font-black text-red-700 ring-1 ring-red-200 dark:bg-red-950 dark:text-red-200 dark:ring-red-900">
                                            <img x-show="person.avatar" x-bind:src="person.avatar" x-bind:alt="personDisplayName(person.name)" x-on:error="person.avatar = ''" class="h-full w-full object-cover">
                                            <span x-show="!person.avatar" x-text="personInitials(person.name)"></span>
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-bold uppercase text-gray-900 dark:text-white" x-text="personDisplayName(person.name)"></span>
                                            <span class="block truncate text-xs text-gray-500 dark:text-slate-400" x-text="person.email"></span>
                                        </span>
                                        <svg class="h-4 w-4 shrink-0 text-red-600 dark:text-red-300" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                    </button>
                                </template>
                                <div x-show="filteredWorkspacePeople().length === 0" class="px-3 py-5 text-center">
                                    <p class="text-sm font-bold text-gray-700 dark:text-slate-200">No available workspace member matches your search.</p>
                                    <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-slate-400">Members already on the project staff list are hidden.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col justify-between rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-4 dark:border-slate-700 dark:bg-slate-800/60 sm:p-5">
                        <div>
                            <p class="text-xs font-black uppercase tracking-wider text-gray-600 dark:text-slate-300">External team member</p>
                            <h4 class="mt-1 text-sm font-black text-gray-900 dark:text-white">Add someone manually</h4>
                            <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-slate-400">Enter the external staff member&rsquo;s optional professional title, name, email, and 11-digit contact number manually.</p>
                        </div>
                        <button type="button" x-on:click="addStaff" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-xs font-bold text-gray-700 transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:ring-offset-2 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                            Add external project staff
                        </button>
                    </div>
                </div>

                <div class="mt-5 rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-slate-700 dark:bg-slate-800/60 sm:p-5">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between sm:gap-4"><div><p class="text-xs font-black uppercase tracking-wider text-gray-600 dark:text-slate-300">Project leader</p><p class="mt-1 text-xs leading-5 text-gray-500 dark:text-slate-400">This information is used across the proposal papers and prepared-by block.</p></div><span class="w-fit rounded-full bg-white px-2.5 py-1 text-[10px] font-black text-red-700 ring-1 ring-red-200 dark:bg-slate-900 dark:text-red-300 dark:ring-red-900">Required</span></div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-[10rem_minmax(0,1fr)_minmax(0,1fr)_12rem]">
                    <div class="flex min-w-0 flex-col">
                        <label for="leader-title" class="flex h-5 items-center justify-between gap-2 text-[10px] font-black uppercase tracking-wider text-gray-600 dark:text-slate-300"><span>Professional title</span><span class="rounded-full border border-gray-300 bg-white px-2 py-0.5 text-[9px] normal-case tracking-normal text-gray-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-400">Optional</span></label>
                        <input id="leader-title" name="leader_title" type="text" maxlength="50" list="detailed-proposal-professional-titles" x-model="leaderTitle" placeholder="e.g. Asst Prof." aria-describedby="leader-title-help" class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                        <p id="leader-title-help" class="mt-1.5 text-[10px] leading-4 text-gray-500 dark:text-slate-400">Leave blank when no professional title applies.</p>
                    </div>
                    <div class="flex min-w-0 flex-col">
                        <label for="leader-name" class="flex h-5 items-center justify-between gap-2 text-[10px] font-black uppercase tracking-wider text-gray-600 dark:text-slate-300"><span>Project Leader</span><span class="text-[9px] text-red-600">Required</span></label>
                        <input id="leader-name" name="project_leader" type="text" required maxlength="120" list="detailed-proposal-member-names" x-model="projectLeader" x-on:change="syncProjectLeader()" placeholder="Type or choose a workspace member" aria-describedby="leader-name-help" class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm uppercase shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                        <p id="leader-name-help" class="mt-1.5 text-[10px] leading-4 text-gray-500 dark:text-slate-400">Changes also update Project Details and the prepared-by name.</p>
                    </div>
                    <div class="flex min-w-0 flex-col"><label for="leader-email" class="flex h-5 items-center text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-300">Email Address</label><input id="leader-email" name="leader_email" type="email" required maxlength="255" x-model="leaderEmail" class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></div>
                    <div class="flex min-w-0 flex-col"><label for="leader-contact" class="flex h-5 items-center text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-300">Contact Number</label><input id="leader-contact" name="leader_contact" type="tel" required maxlength="11" inputmode="numeric" pattern="[0-9]{11}" autocomplete="tel" x-model="leaderContact" x-on:input="leaderContact = normalizeContactNumber($event.target.value); $event.target.value = leaderContact" class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></div>
                    </div>
                </div>

                <div class="mt-5 border-t border-gray-100 pt-5 dark:border-slate-800">
                    <div class="flex items-center justify-between gap-3"><div><p class="text-xs font-black uppercase tracking-wider text-gray-600 dark:text-slate-300">Project staff</p><p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Each staff member can have an optional professional title.</p></div><span class="rounded-full bg-gray-100 px-2.5 py-1 text-[10px] font-black text-gray-600 dark:bg-slate-800 dark:text-slate-300" x-text="`${staff.length} ${staff.length === 1 ? 'member' : 'members'}`"></span></div>
                    <div class="mt-4 space-y-3">
                    <p x-show="staff.length === 0" class="rounded-xl bg-gray-50 px-4 py-3 text-xs leading-5 text-gray-500 dark:bg-slate-800/60 dark:text-slate-400">No project staff added yet. Choose a workspace member or add an external person above.</p>
                    <template x-for="(member, index) in staff" :key="member.id">
                        <div class="grid gap-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900 md:grid-cols-2 lg:grid-cols-[9rem_minmax(0,1fr)_minmax(0,1fr)_12rem_auto] lg:items-end">
                            <div class="flex min-w-0 flex-col"><label class="flex h-5 items-center justify-between gap-2 text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-300" :for="`staff-title-${member.id}`"><span>Professional title</span><span class="rounded-full border border-gray-300 px-2 py-0.5 text-[9px] normal-case tracking-normal text-gray-500 dark:border-slate-600 dark:text-slate-400">Optional</span></label><input :id="`staff-title-${member.id}`" :name="`staff[${index}][title]`" type="text" maxlength="50" list="detailed-proposal-professional-titles" x-model="member.title" placeholder="e.g. Dr." class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-800 dark:text-white"></div>
                            <div class="flex min-w-0 flex-col"><label class="flex h-5 items-center text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-300" :for="`staff-name-${member.id}`">Project Staff</label><input :id="`staff-name-${member.id}`" :name="`staff[${index}][name]`" type="text" required maxlength="255" list="detailed-proposal-member-names" x-model="member.name" x-on:change="syncStaff(member)" placeholder="Full name" class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm uppercase shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-800 dark:text-white"></div>
                            <div class="flex min-w-0 flex-col"><label class="flex h-5 items-center text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-300" :for="`staff-email-${member.id}`">Email Address</label><input :id="`staff-email-${member.id}`" :name="`staff[${index}][email]`" type="email" required maxlength="255" x-model="member.email" placeholder="name@example.com" class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-800 dark:text-white"></div>
                            <div class="flex min-w-0 flex-col"><label class="flex h-5 items-center text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-300" :for="`staff-contact-${member.id}`">Contact Number</label><input :id="`staff-contact-${member.id}`" :name="`staff[${index}][contact]`" type="tel" required maxlength="11" inputmode="numeric" pattern="[0-9]{11}" autocomplete="tel" x-model="member.contact" x-on:input="member.contact = normalizeContactNumber($event.target.value); $event.target.value = member.contact" placeholder="09XXXXXXXXX" class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-800 dark:text-white"></div>
                            <button type="button" x-on:click="removeStaff(index)" class="h-11 rounded-xl px-3 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-red-300 dark:hover:bg-red-950/40">Remove</button>
                        </div>
                    </template>
                    </div>
                </div>
                <datalist id="detailed-proposal-member-names">@foreach ($workspacePeople as $person)<option value="{{ $person['name'] }}">{{ $person['email'] }}</option>@endforeach</datalist>
                <datalist id="detailed-proposal-professional-titles">@foreach ($professionalTitles as $title)<option value="{{ $title }}"></option>@endforeach</datalist>
            </section>

            <section data-revision-section="section-proponent" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <h3 class="text-base font-black text-gray-900">V–VI. Proponent and cooperating agencies</h3>
                <p class="mt-1 text-xs text-gray-500">The Proponent Agency line is intentionally left blank on the official form.</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div><label for="proponent-department" class="block text-xs font-black uppercase tracking-wider text-gray-600">Department <span class="font-normal normal-case text-gray-400">Optional</span></label><input id="proponent-department" name="proponent_department" type="text" maxlength="255" x-model="proponentDepartment" placeholder="Leave blank if not applicable" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                    <div><label for="proponent-college" class="block text-xs font-black uppercase tracking-wider text-gray-600">College <span class="font-normal normal-case text-gray-400">From your profile</span></label><input id="proponent-college" name="proponent_college" type="text" required maxlength="255" x-model="proponentCollege" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                    <div><label for="proponent-campus" class="block text-xs font-black uppercase tracking-wider text-gray-600">Campus</label><input id="proponent-campus" name="proponent_campus" type="text" required maxlength="255" x-model="proponentCampus" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                    <div data-revision-section="section-cooperating-agency"><label for="cooperating-agency" class="block text-xs font-black uppercase tracking-wider text-gray-600">VI. Cooperating Agency <span class="font-normal normal-case text-gray-400">Optional</span></label><input id="cooperating-agency" name="cooperating_agency" type="text" maxlength="500" x-model="cooperatingAgency" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                </div>
            </section>

            @foreach ([
                'executive_brief' => ['VII. Executive Brief', 'Summarize the proposed project and its intended contribution.'],
                'rationale' => ['VIII. Rationale', 'Include available statistics related to the problem.'],
            ] as $field => [$label, $help])
                <section data-revision-section="section-{{ str_replace('_', '-', $field) }}" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <label for="{{ str_replace('_', '-', $field) }}" class="block text-base font-black text-gray-900">{{ $label }}</label>
                    <p class="mt-1 text-xs text-gray-500">{{ $help }}</p>
                    <textarea id="{{ str_replace('_', '-', $field) }}" name="{{ $field }}" rows="{{ $field === 'rationale' ? 14 : 9 }}" required maxlength="{{ config('detailed_proposal.maximum_narrative_length') }}" x-model="{{ \Illuminate\Support\Str::camel($field) }}" data-semantic-editor class="mt-4 block w-full rounded-xl border-gray-300 text-sm leading-6 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                </section>
            @endforeach

            <section data-revision-section="section-objectives" id="specific-objectives" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-base font-black text-gray-900">IX. Objectives of the Project</h3>
                        <p class="mt-1 text-xs text-gray-500">Add one optional general objective, then list the specific objectives. Numbering is generated automatically.</p>
                    </div>
                    <button type="button" x-on:click="addSpecificObjective" class="inline-flex shrink-0 rounded-xl border border-red-200 px-4 py-2 text-xs font-bold text-red-700 hover:bg-red-50">Add specific objective</button>
                </div>
                <label for="general-objective" class="mt-5 block text-xs font-black uppercase tracking-wider text-gray-600">General objective <span class="font-normal normal-case text-gray-400">Optional</span></label>
                <textarea id="general-objective" name="general_objective" rows="4" maxlength="{{ config('detailed_proposal.maximum_narrative_length') }}" x-model="generalObjective" data-semantic-editor class="mt-2 block w-full rounded-xl border-gray-300 text-sm leading-6 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                <div class="mt-5 space-y-3">
                    <template x-for="(objective, index) in specificObjectives" :key="objective.id">
                        <article class="rounded-xl border border-gray-200 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <label class="text-xs font-black uppercase tracking-wider text-gray-600" :for="`specific-objective-${objective.id}`"><span x-text="`${index + 1}. Specific objective`"></span></label>
                                <div class="flex gap-1">
                                    <button type="button" x-on:click="moveSpecificObjective(index, -1)" :disabled="index === 0" class="rounded-lg px-2 py-1 text-xs font-bold text-gray-500 hover:bg-gray-100 disabled:opacity-40">Up</button>
                                    <button type="button" x-on:click="moveSpecificObjective(index, 1)" :disabled="index === specificObjectives.length - 1" class="rounded-lg px-2 py-1 text-xs font-bold text-gray-500 hover:bg-gray-100 disabled:opacity-40">Down</button>
                                    <button type="button" x-on:click="removeSpecificObjective(index)" class="rounded-lg px-2 py-1 text-xs font-bold text-red-700 hover:bg-red-50">Remove</button>
                                </div>
                            </div>
                            <textarea :id="`specific-objective-${objective.id}`" :name="`specific_objectives[${index}][description]`" rows="3" required maxlength="{{ config('detailed_proposal.maximum_narrative_length') }}" x-model="objective.description" class="mt-2 block w-full rounded-xl border-gray-300 text-sm leading-6 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                        </article>
                    </template>
                </div>
            </section>

            <section data-revision-section="section-expected-outputs" id="expected-outputs" data-detailed-proposal-validation-group="expected-outputs" tabindex="-1" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h3 class="text-base font-black text-gray-900">X. Expected Output of the Project</h3>
                        <p class="mt-1 text-xs text-gray-500">Add only the 6Ps and 2Is that apply. At least one output is required.</p>
                    </div>
                </div>
                <div class="mt-5 grid gap-x-6 gap-y-3 lg:grid-cols-2">
                    @foreach ($expectedOutputs as $key => $label)
                        <section class="border-b border-gray-100 py-3 first:pt-0 lg:[&:nth-child(2)]:pt-0">
                            <div class="flex items-center justify-between gap-3">
                                <h4 class="text-xs font-black uppercase tracking-wider text-gray-700">{{ $label }}</h4>
                                <button type="button" x-on:click="addExpectedOutput('{{ $key }}')" class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-red-200 bg-white px-3 py-1.5 text-[11px] font-bold text-red-700 shadow-sm transition hover:border-red-300 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 active:bg-red-100">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M8 3v10M3 8h10" stroke-linecap="round"/></svg>
                                    Add output
                                </button>
                            </div>
                            <div class="space-y-2">
                                <template x-for="(output, index) in expectedOutputs.{{ $key }}" :key="output.id">
                                    <div class="flex items-start gap-2">
                                        <textarea :name="`expected_outputs[{{ $key }}][${index}][description]`" rows="2" required maxlength="{{ config('detailed_proposal.maximum_narrative_length') }}" x-model="output.description" aria-label="{{ $label }} description" placeholder="Describe the expected output" class="block w-full rounded-lg border-gray-300 text-sm leading-5 focus:border-red-600 focus:ring-red-600"></textarea>
                                        <button type="button" x-on:click="removeExpectedOutput('{{ $key }}', index)" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-700 transition hover:border-red-300 hover:bg-red-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 active:bg-red-200" :aria-label="`Remove {{ $label }} output ${index + 1}`" title="Remove output">
                                            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 4h10M6 4V2.5h4V4M5 6.5v5M8 6.5v5M11 6.5v5M4 4l.7 10h6.6L12 4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            <span class="sr-only">Remove</span>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </section>
                    @endforeach
                </div>
            </section>

            @if (false)
            <section x-ref="literatureWorkspace" class="rounded-2xl border border-red-100 bg-gradient-to-br from-red-50/80 via-white to-amber-50/60 p-5 shadow-sm sm:p-6">
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-5" aria-labelledby="literature-assistant-heading">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 id="literature-assistant-heading" class="text-base font-black text-slate-950 dark:text-white">Literature Assistant</h3>
                                <span class="rounded-full bg-red-100 px-2.5 py-1 text-[10px] font-black text-red-800 dark:bg-red-950/50 dark:text-red-200">Proposal-aware search</span>
                            </div>
                            <p class="mt-1 max-w-3xl text-xs leading-5 text-slate-500 dark:text-slate-400">Build an editable search from this proposal, review the evidence, then choose what belongs in the RRL or references. Nothing is cited or written automatically.</p>
                        </div>
                        <button type="button" x-on:click="refreshSuggestedLiteratureQuery()" class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-xl border border-slate-300 bg-white px-3.5 text-xs font-black text-slate-700 transition hover:border-red-200 hover:bg-red-50 hover:text-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-red-900 dark:hover:bg-red-950/30 dark:hover:text-red-200">Update from proposal</button>
                    </div>

                    <div class="mt-4 rounded-xl border border-red-100 bg-red-50/60 p-3.5 dark:border-red-900/70 dark:bg-red-950/20">
                        <p class="text-[10px] font-black uppercase tracking-wider text-red-800 dark:text-red-200">Use in the suggested search</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <template x-for="context in literatureSearchContextOptions()" :key="context.key">
                                <button
                                    type="button"
                                    x-show="context.available"
                                    x-on:click="toggleLiteratureSearchContext(context.key)"
                                    x-bind:aria-pressed="literatureSearchContext[context.key] ? 'true' : 'false'"
                                    x-bind:class="literatureSearchContext[context.key] ? 'border-red-600 bg-red-600 text-white' : 'border-red-200 bg-white text-red-800 hover:bg-red-100 dark:border-red-900 dark:bg-slate-950 dark:text-red-200 dark:hover:bg-red-950/40'"
                                    class="rounded-lg border px-2.5 py-1.5 text-[10px] font-black transition focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2"
                                    x-text="context.label"
                                ></button>
                            </template>
                        </div>
                        <p class="mt-2 text-[11px] leading-5 text-red-900/80 dark:text-red-200/80">The query is only a suggestion. You can edit it before ATHENA contacts the academic indexes.</p>
                    </div>

                    <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                        <label class="block min-w-0 flex-1 text-xs font-black text-slate-700 dark:text-slate-200" for="proposal-literature-query">
                            Search query
                            <input id="proposal-literature-query" type="search" maxlength="180" x-model="literatureSearchQuery" x-on:input="literatureSearchError = ''" x-on:keydown.enter.prevent="searchSuggestedLiterature()" placeholder="Use the proposal title, objectives, or your own research terms" class="mt-1.5 block h-11 w-full rounded-xl border-slate-300 bg-white text-sm text-slate-900 shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-500">
                        </label>
                        <button type="button" x-on:click="searchSuggestedLiterature()" x-bind:disabled="literatureSearchLoading || literatureSearchQuery.trim().length < 3" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl bg-red-700 px-4 text-xs font-black text-white shadow-sm transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40" x-text="literatureSearchLoading ? 'Searching academic indexes...' : 'Search related literature'"></button>
                    </div>

                    <p x-show="literatureSearchError" x-cloak class="mt-3 rounded-xl border border-red-200 bg-red-50 px-3.5 py-3 text-xs font-semibold leading-5 text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200" x-text="literatureSearchError" role="alert"></p>
                    <p x-show="literatureSearchNotice" x-cloak class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-3 text-xs font-semibold leading-5 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200" x-text="literatureSearchNotice" role="status"></p>

                    <div x-show="literatureSearchResults.length" x-cloak class="mt-5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-xs font-black text-slate-900 dark:text-white"><span x-text="literatureSearchResults.length"></span> related papers found</p>
                            <p class="text-[10px] font-semibold text-slate-500 dark:text-slate-400">Scroll through results; expand an abstract before deciding.</p>
                        </div>
                        <div class="mt-3 max-h-[44rem] space-y-3 overflow-y-auto pr-1" aria-live="polite">
                            <template x-for="result in literatureSearchResults" :key="literatureResultKey(result)">
                                <article x-data="{ abstractExpanded: false }" class="rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-700 dark:bg-slate-950/40">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <h4 class="text-sm font-black leading-5 text-slate-950 dark:text-white" x-text="result.title"></h4>
                                            <p class="mt-1 text-[11px] leading-4 text-slate-500 dark:text-slate-400"><span x-text="result.authors || 'Authors not listed'"></span><span x-show="result.year"> &middot; <span x-text="result.year"></span></span></p>
                                        </div>
                                        <div class="flex shrink-0 flex-wrap gap-1.5 text-[9px] font-black">
                                            <span class="rounded bg-red-100 px-2 py-1 text-red-800 dark:bg-red-950/50 dark:text-red-200" x-text="result.relevance_label || 'Potential match'"></span>
                                            <span class="rounded bg-slate-200 px-2 py-1 text-slate-700 dark:bg-slate-800 dark:text-slate-200" x-text="result.access_label || 'Access not listed'"></span>
                                            <span x-show="result._linked" class="rounded bg-slate-900 px-2 py-1 text-white dark:bg-white dark:text-slate-900">Saved to proposal library</span>
                                            <span x-show="result._linkedSource && literatureSourceUsage(result._linkedSource).usedInRrl" class="rounded bg-red-100 px-2 py-1 text-red-800 dark:bg-red-950/50 dark:text-red-200">RRL added</span>
                                            <span x-show="result._linkedSource && literatureSourceUsage(result._linkedSource).addedToReferences" class="rounded bg-red-100 px-2 py-1 text-red-800 dark:bg-red-950/50 dark:text-red-200">Cited as <span x-text="`[${literatureSourceUsage(result._linkedSource).referenceNumber}]`"></span></span>
                                        </div>
                                    </div>
                                    <p class="mt-3 text-[11px] font-semibold leading-5 text-slate-600 dark:text-slate-300"><span class="font-black text-red-800 dark:text-red-200">Why this matched:</span> <span x-text="result.match_reason || 'Matched the proposal search context.'"></span></p>
                                    <p x-show="Array.isArray(result.matched_terms) && result.matched_terms.length" class="mt-2 text-[10px] font-semibold text-slate-500 dark:text-slate-400">Matched terms: <span x-text="result.matched_terms.join(', ')"></span></p>
                                    <p class="mt-3 whitespace-pre-wrap text-xs leading-6 text-slate-700 dark:text-slate-300" x-bind:class="abstractExpanded ? '' : 'line-clamp-5'" x-text="result.description"></p>
                                    <button type="button" x-show="String(result.description || '').length > 720" x-on:click="abstractExpanded = !abstractExpanded" x-bind:aria-expanded="abstractExpanded.toString()" class="mt-2 inline-flex min-h-8 items-center rounded-lg px-2 text-[11px] font-black text-red-800 transition hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:text-red-200 dark:hover:bg-red-950/40"><span x-text="abstractExpanded ? 'Show less' : 'Show full abstract'"></span></button>
                                    <p x-show="!hasUsableSuggestedAbstract(result)" class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-[10px] font-semibold leading-4 text-amber-800 dark:bg-amber-950/30 dark:text-amber-200">This record does not include a usable abstract, so ATHENA will not prepare an RRL paragraph from it.</p>
                                    <div class="mt-4 flex flex-col gap-2 border-t border-slate-200 pt-3 dark:border-slate-700 sm:flex-row sm:flex-wrap">
                                        <button type="button" x-on:click="prepareSuggestedLiteratureReview(result)" x-bind:disabled="isSavingSuggestedLiterature(result) || !hasUsableSuggestedAbstract(result)" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-red-700 px-3.5 text-[11px] font-black text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40" x-text="isSavingSuggestedLiterature(result) ? 'Preparing...' : 'Review and add RRL'"></button>
                                        <button type="button" x-on:click="saveSuggestedLiterature(result)" x-bind:disabled="isSavingSuggestedLiterature(result) || result._linked" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-3.5 text-[11px] font-black text-slate-800 transition hover:border-red-300 hover:bg-red-50 hover:text-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:hover:border-red-900 dark:hover:bg-red-950/30" x-text="result._linked ? 'Saved to proposal library' : (isSavingSuggestedLiterature(result) ? 'Saving...' : 'Save to proposal library')"></button>
                                        <a x-show="result.url" x-bind:href="result.url" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-10 items-center justify-center rounded-lg px-3 text-[11px] font-black text-red-800 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:text-red-200 dark:hover:bg-red-950/30">Open source</a>
                                    </div>
                                    <p class="mt-2 text-[10px] font-semibold leading-4 text-slate-500 dark:text-slate-400">Saving adds this paper to the shared library and this proposal. Its IEEE reference is added only after you cite the RRL text it supports.</p>
                                    <p x-show="result._actionNotice" x-cloak class="mt-3 rounded-lg border border-slate-200 bg-white px-3 py-2 text-[10px] font-bold text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" x-text="result._actionNotice" role="status"></p>
                                </article>
                            </template>
                        </div>
                    </div>

                    <div x-show="literatureSearchHistory.length" x-cloak class="mt-5 border-t border-slate-200 pt-4 dark:border-slate-700">
                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">Recent literature searches</p>
                        <div class="mt-2 space-y-2">
                            <template x-for="entry in literatureSearchHistory" :key="entry.id">
                                <div class="flex flex-col gap-2 rounded-lg bg-slate-50 px-3 py-2.5 dark:bg-slate-950/50 sm:flex-row sm:items-center sm:justify-between">
                                    <p class="min-w-0 text-[11px] leading-5 text-slate-600 dark:text-slate-300" x-bind:title="entry.query"><span class="font-black text-slate-900 dark:text-white" x-text="literatureSearchHistoryTitle(entry)"></span><span class="text-slate-400"> &middot; </span><span x-text="literatureSearchHistorySummary(entry)"></span></p>
                                    <button type="button" x-on:click="runLiteratureSearchHistory(entry)" class="inline-flex min-h-8 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white px-2.5 text-[10px] font-black text-slate-700 hover:border-red-200 hover:bg-red-50 hover:text-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-red-900 dark:hover:bg-red-950/30 dark:hover:text-red-200">Run again</button>
                                </div>
                            </template>
                        </div>
                    </div>
                </section>

                <div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-base font-black text-gray-900">Literature linked to this proposal</h3>
                            <span class="rounded-full bg-red-100 px-2.5 py-1 text-[10px] font-black text-red-700"><span x-text="literatureSources.length"></span> linked</span>
                        </div>
                        <p class="mt-1 max-w-3xl text-xs leading-5 text-gray-500">Saved papers are shared-library records linked to this draft. When you cite an RRL sentence or add a confirmed RRL paragraph, ATHENA adds one synchronized IEEE reference.</p>
                        </div>
                </div>

                <p x-show="literatureSourceNotice" x-cloak class="mt-4 rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs font-semibold text-slate-800" x-text="literatureSourceNotice" role="status"></p>

                <div x-show="literatureSources.length === 0" class="mt-5 rounded-xl border border-dashed border-gray-300 bg-white/80 px-4 py-6 text-center">
                    <p class="text-sm font-black text-gray-800">No shared literature linked to this proposal yet</p>
                    <p class="mt-1 text-xs text-gray-500">Search above, save a paper to this proposal library, then cite the RRL text it supports.</p>
                </div>

                <div x-show="literatureSources.length" x-cloak class="mt-5 grid gap-3 lg:grid-cols-2">
                    <template x-for="source in literatureSources" :key="source.id">
                        <article class="flex flex-col rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-black leading-5 text-gray-900" x-text="source.title"></p>
                                    <p class="mt-1 line-clamp-2 text-[11px] leading-4 text-gray-500" x-text="source.authors || 'Authors not listed'"></p>
                                </div>
                                <span class="shrink-0 rounded-md bg-gray-100 px-2 py-1 text-[9px] font-black text-gray-600" x-text="source.year || 'n.d.'"></span>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-1.5 text-[9px] font-bold text-gray-500">
                                <span class="rounded bg-red-50 px-2 py-1 text-red-700" x-text="source.source"></span>
                                <span x-show="source.venue" class="max-w-56 truncate rounded bg-gray-50 px-2 py-1" x-text="source.venue"></span>
                                <a x-show="source.url" :href="source.url" target="_blank" rel="noopener noreferrer" class="rounded px-2 py-1 font-black text-red-700 hover:bg-red-50">Verify source</a>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-1.5 text-[9px] font-black">
                                <span class="rounded bg-slate-900 px-2 py-1 text-white dark:bg-white dark:text-slate-900">Linked to proposal</span>
                                <span x-show="literatureSourceUsage(source).usedInRrl" class="rounded bg-red-100 px-2 py-1 text-red-800 dark:bg-red-950/50 dark:text-red-200">Used in RRL <span x-text="`[${literatureSourceUsage(source).referenceNumber}]`"></span></span>
                                <span x-show="literatureSourceUsage(source).addedToReferences" class="rounded bg-red-100 px-2 py-1 text-red-800 dark:bg-red-950/50 dark:text-red-200">Reference synchronized</span>
                            </div>
                            <p x-show="source.rrl_draft_status && source.rrl_draft_status !== 'none'" class="mt-3 text-[10px] font-bold text-emerald-700" x-text="`${source.rrl_draft_status === 'confirmed' ? 'Confirmed' : 'Saved'} RRL draft · ${source.rrl_evidence_basis === 'full_text' ? 'loaded open-access full text' : 'indexed abstract'} · ${source.rrl_word_count || 0} words`"></p>
                            <p x-show="source.reference_incomplete" class="mt-2 text-[10px] font-bold text-amber-700">IEEE reference has incomplete source metadata; unavailable details were omitted.</p>
                            <div class="mt-auto flex flex-wrap gap-2 pt-4">
                                <button x-show="!literatureSourceUsage(source).usedInRrl" type="button" x-on:click="source.rrl_draft_status === 'confirmed' ? addLiteratureSourceToRrl(source) : prepareLinkedLiteratureReview(source)" class="inline-flex min-h-9 items-center justify-center rounded-lg bg-red-700 px-3 text-[10px] font-black text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2" x-text="source.rrl_draft_status === 'confirmed' ? 'Add to Section XI' : (source.rrl_draft_status === 'draft' ? 'Review saved RRL' : 'Review RRL')"></button>
                                <p x-show="literatureSourceUsage(source).usedInRrl" class="self-center text-[10px] font-bold text-slate-500">Its IEEE reference is synchronized in Section XVI.</p>
                            </div>
                            <button x-show="literatureSourceUsage(source).usedInRrl" type="button" x-on:click="removeCitationSource(source)" class="mt-3 inline-flex h-8 self-start items-center justify-center rounded-md border border-red-200 bg-white px-2.5 text-[10px] font-black text-red-800 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600">Remove citations</button>
                        </article>
                    </template>
                </div>
            </section>

            <div x-show="literatureReviewOpen" x-cloak x-on:keydown.escape.window="closeLiteratureReview()" class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="proposal-literature-review-title">
                <button type="button" x-on:click="closeLiteratureReview()" class="absolute inset-0 cursor-default bg-slate-950/55" aria-label="Close RRL review"></button>
                <section class="relative z-10 flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900">
                    <header class="sticky top-0 z-20 flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 bg-white px-5 py-4 dark:border-slate-700 dark:bg-slate-900 sm:px-6">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 id="proposal-literature-review-title" class="text-lg font-black text-slate-950 dark:text-white">Review RRL paragraph</h3>
                                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-amber-800 dark:bg-amber-950/50 dark:text-amber-200" x-text="literatureReviewBasis === 'full_text' ? 'Loaded public full text' : 'Indexed abstract' "></span>
                            </div>
                            <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Evidence stays visible while you review and confirm the editable wording.</p>
                        </div>
                        <button type="button" x-on:click="closeLiteratureReview()" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Close RRL review">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                        </button>
                    </header>

                    <div class="grid min-h-0 flex-1 overflow-y-auto lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:overflow-hidden">
                        <section class="border-b border-slate-200 bg-slate-50/80 p-5 dark:border-slate-700 dark:bg-slate-950/40 lg:overflow-y-auto lg:border-b-0 lg:border-r sm:p-6">
                            <p class="text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">Source evidence</p>
                            <h4 class="mt-3 text-base font-black leading-6 text-slate-950 dark:text-white" x-text="literatureReviewSource?.title || 'Selected paper'"></h4>
                            <p class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400"><span x-text="literatureReviewSource?.authors || 'Authors not listed'"></span><span x-show="literatureReviewSource?.year"> &middot; <span x-text="literatureReviewSource?.year"></span></span></p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <button type="button" x-on:click="literatureReviewBasis = 'abstract'" x-bind:class="literatureReviewBasis === 'abstract' ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'border border-slate-300 bg-white text-slate-700 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200'" class="rounded-lg px-3 py-2 text-[10px] font-black focus:outline-none focus:ring-2 focus:ring-red-600">Use abstract</button>
                                <button type="button" x-show="literatureReviewSource?.full_text_token" x-on:click="loadLiteratureReviewFullText()" x-bind:disabled="literatureReviewLoadingFullText" x-bind:class="literatureReviewBasis === 'full_text' ? 'bg-emerald-700 text-white' : 'border border-emerald-200 bg-white text-emerald-800 dark:border-emerald-900 dark:bg-slate-950 dark:text-emerald-200'" class="rounded-lg px-3 py-2 text-[10px] font-black focus:outline-none focus:ring-2 focus:ring-red-600 disabled:cursor-not-allowed disabled:opacity-40" x-text="literatureReviewLoadingFullText ? 'Loading public text...' : (literatureReviewFullText ? 'Use loaded public text' : 'Load public full text')"></button>
                            </div>
                            <p x-show="literatureReviewFullTextError" x-cloak class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs font-semibold leading-5 text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200" x-text="literatureReviewFullTextError"></p>
                            <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                                <p class="text-xs font-black text-slate-800 dark:text-slate-100" x-text="literatureReviewBasis === 'full_text' ? 'Loaded open-access evidence' : 'Indexed abstract evidence'"></p>
                                <p class="mt-3 whitespace-pre-wrap text-sm leading-7 text-slate-600 dark:text-slate-300" x-text="literatureReviewEvidence()"></p>
                            </div>
                            <a x-show="literatureReviewSource?.url" x-bind:href="literatureReviewSource?.url" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex text-xs font-black text-red-800 hover:underline dark:text-red-200">Open source record</a>
                        </section>

                        <section class="flex min-h-[28rem] flex-col p-5 dark:bg-slate-900 lg:overflow-y-auto sm:p-6">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">Editable RRL paragraph</p>
                                    <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Generate a cautious draft from the visible evidence or write your own. Adding it to the RRL also creates its synchronized reference.</p>
                                </div>
                                <button type="button" x-on:click="generateLiteratureReviewDraft()" x-bind:disabled="literatureReviewGenerating || !hasLiteratureReviewEvidence()" class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-xl bg-red-700 px-3.5 text-xs font-black text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40" x-text="literatureReviewGenerating ? 'Drafting...' : 'Draft from evidence'"></button>
                            </div>
                            <textarea x-model="literatureReviewDraft" rows="12" maxlength="5000" placeholder="Write or generate a source-supported RRL paragraph after reviewing the evidence." class="mt-4 min-h-64 w-full flex-1 resize-y rounded-2xl border-slate-300 bg-white p-4 text-sm leading-7 text-slate-900 shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-500"></textarea>
                            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs">
                                <p class="font-medium text-slate-500 dark:text-slate-400"><span x-text="literatureReviewWordCount()"></span> words <span aria-hidden="true">&middot;</span> Review required before saving</p>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black text-slate-700 dark:bg-slate-800 dark:text-slate-200" x-text="literatureReviewBasis === 'full_text' ? 'Public full-text based' : 'Abstract based'"></span>
                            </div>
                            <p x-show="literatureReviewNotice" x-cloak class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-3 text-xs font-semibold leading-5 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200" x-text="literatureReviewNotice" role="status"></p>
                            <p x-show="literatureReviewError" x-cloak class="mt-3 rounded-xl border border-red-200 bg-red-50 px-3.5 py-3 text-xs font-semibold leading-5 text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200" x-text="literatureReviewError" role="alert"></p>
                            <div class="mt-5 flex flex-col-reverse gap-2 border-t border-slate-200 pt-4 dark:border-slate-700 sm:flex-row sm:justify-end">
                                <button type="button" x-on:click="saveLiteratureReview()" x-bind:disabled="literatureReviewSaving || literatureReviewDraft.trim().length < 40" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-black text-slate-700 transition hover:border-red-200 hover:bg-red-50 hover:text-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-red-900 dark:hover:bg-red-950/30" x-text="literatureReviewSaving ? 'Saving...' : 'Save for later'"></button>
                                <button type="button" x-on:click="saveLiteratureReview(true)" x-bind:disabled="literatureReviewSaving || literatureReviewDraft.trim().length < 40" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-red-700 px-4 text-sm font-black text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40" x-text="literatureReviewSaving ? 'Adding...' : 'Add RRL + reference'"></button>
                            </div>
                        </section>
                    </div>
                </section>
            </div>

            @endif

            @include('faculty.proposal-drafts.detailed-proposal.partials.literature-workspace')

            <div x-show="citationPickerOpen" x-cloak x-on:keydown.escape.window="closeCitationPicker()" class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="proposal-citation-picker-title">
                <button type="button" x-on:click="closeCitationPicker()" class="absolute inset-0 cursor-default bg-slate-950/55" aria-label="Close citation picker"></button>
                <section class="relative z-10 flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900">
                    <header class="sticky top-0 z-20 flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 bg-white px-5 py-4 dark:border-slate-700 dark:bg-slate-900 sm:px-6">
                        <div class="min-w-0">
                            <h3 id="proposal-citation-picker-title" class="text-lg font-black text-slate-950 dark:text-white">Support this passage with literature</h3>
                            <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Choose verified evidence for the selected claim. ATHENA will cite it in place and keep Section XVI synchronized.</p>
                        </div>
                        <button type="button" x-on:click="closeCitationPicker()" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Close citation picker">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                        </button>
                    </header>

                    <div class="min-h-0 flex-1 space-y-5 overflow-y-auto p-5 sm:p-6">
                        <div class="rounded-2xl border border-red-200 bg-gradient-to-br from-red-50 via-white to-slate-50 p-4 shadow-sm dark:border-red-900/70 dark:from-red-950/30 dark:via-slate-950 dark:to-slate-900">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-red-700 px-2.5 py-1 text-[10px] font-black text-white" x-text="citationPickerSelection?.sectionLabel || 'Proposal section'"></span>
                                <span class="text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">Selected claim</span>
                            </div>
                            <p class="mt-3 border-l-2 border-red-600 pl-3 text-sm font-semibold leading-6 text-slate-800 dark:text-slate-100" x-text="citationPickerSelection?.selectedText"></p>
                        </div>

                        <label class="block text-xs font-black text-slate-700 dark:text-slate-200" for="citation-locator">
                            Page or locator <span class="font-normal text-slate-500">Optional for direct quotations</span>
                            <input id="citation-locator" type="text" maxlength="100" x-model="citationPickerLocator" placeholder="e.g. p. 14 or Table 2" class="mt-1.5 block h-10 w-full rounded-xl border-slate-300 bg-white text-sm text-slate-900 shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white">
                        </label>

                        <section>
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h4 class="text-sm font-black text-slate-900 dark:text-white">Already linked to this proposal</h4>
                                    <p class="mt-1 text-[11px] leading-4 text-slate-500 dark:text-slate-400">Choose a saved paper to cite it immediately.</p>
                                </div>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black text-slate-700 dark:bg-slate-800 dark:text-slate-200" x-text="`${literatureSources.length} available`"></span>
                            </div>
                            <div x-show="literatureSources.length" class="mt-3 space-y-2">
                                <template x-for="source in literatureSources" :key="`citation-linked-${source.id}`">
                                    <button type="button" x-on:click="citeSelectedText(source)" x-bind:disabled="citationPickerSaving" class="block w-full rounded-xl border border-slate-200 bg-white p-3 text-left transition hover:border-red-300 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:bg-slate-950 dark:hover:border-red-900 dark:hover:bg-red-950/30">
                                        <span class="block text-xs font-black leading-5 text-slate-900 dark:text-white" x-text="source.title"></span>
                                        <span class="mt-1 block text-[10px] font-semibold text-slate-500 dark:text-slate-400"><span x-text="source.authors || 'Authors not listed'"></span><span x-show="source.year"> &middot; <span x-text="source.year"></span></span></span>
                                    </button>
                                </template>
                            </div>
                            <p x-show="!literatureSources.length" class="mt-3 rounded-xl border border-dashed border-slate-300 px-3 py-3 text-xs leading-5 text-slate-500 dark:border-slate-700 dark:text-slate-400">No paper is linked yet. Search the shared library below, then choose a verified record.</p>
                        </section>

                        <section class="border-t border-slate-200 pt-5 dark:border-slate-700">
                            <h4 class="text-sm font-black text-slate-900 dark:text-white">Search the shared literature library</h4>
                            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                <label class="sr-only" for="citation-library-query">Search saved papers</label>
                                <input id="citation-library-query" type="search" maxlength="180" x-model="citationPickerQuery" x-on:keydown.enter.prevent="searchCitationLibrary()" placeholder="Title, author, venue, or DOI" class="block h-10 min-w-0 flex-1 rounded-xl border-slate-300 bg-white text-sm text-slate-900 shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white">
                                <button type="button" x-on:click="searchCitationLibrary()" x-bind:disabled="citationPickerLoading" class="inline-flex h-10 shrink-0 items-center justify-center rounded-xl bg-red-700 px-4 text-xs font-black text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40" x-text="citationPickerLoading ? 'Searching...' : 'Search library'"></button>
                            </div>
                            <p x-show="citationPickerError" x-cloak class="mt-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2.5 text-xs font-semibold text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200" x-text="citationPickerError" role="alert"></p>
                            <div x-show="citationPickerResults.length" class="mt-3 space-y-2">
                                <template x-for="source in citationPickerResults" :key="`citation-library-${source.id}`">
                                    <button type="button" x-on:click="citeSelectedText(linkedCitationSourceForLibrarySource(source) || source, Boolean(linkedCitationSourceForLibrarySource(source)))" x-bind:disabled="citationPickerSaving" class="block w-full rounded-xl border border-slate-200 bg-white p-3 text-left transition hover:border-red-300 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:bg-slate-950 dark:hover:border-red-900 dark:hover:bg-red-950/30">
                                        <span class="block text-xs font-black leading-5 text-slate-900 dark:text-white" x-text="source.title"></span>
                                        <span class="mt-1 block text-[10px] font-semibold text-slate-500 dark:text-slate-400"><span x-text="source.authors || 'Authors not listed'"></span><span x-show="source.year"> &middot; <span x-text="source.year"></span></span><span x-show="linkedCitationSourceForLibrarySource(source)" class="text-red-700 dark:text-red-200"> &middot; already linked</span></span>
                                    </button>
                                </template>
                            </div>
                        </section>
                    </div>
                </section>
            </div>

            <section data-revision-section="section-literature" x-ref="introductionSection" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <h3 class="text-base font-black text-gray-900">XI. Introduction and Related Studies and Literature</h3>
                <div class="mt-5 grid gap-5">
                    <div>
                        <label for="introduction" class="block text-xs font-black uppercase tracking-wider text-gray-600">Introduction</label>
                        <textarea id="introduction" name="introduction" rows="10" required maxlength="{{ config('detailed_proposal.maximum_narrative_length') }}" x-model="introduction" data-semantic-editor class="mt-2 block w-full rounded-xl border-gray-300 text-sm leading-6 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                    </div>
                    <aside class="rounded-2xl border border-red-100 bg-red-50/60 p-4 dark:border-red-900/60 dark:bg-red-950/20" aria-labelledby="proposal-sources-heading">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 id="proposal-sources-heading" class="text-sm font-black text-slate-950 dark:text-white">Sources for this proposal</h4>
                                    <span class="rounded-full bg-white px-2.5 py-1 text-[10px] font-black text-slate-700 shadow-sm dark:bg-slate-900 dark:text-slate-200" x-text="`${literatureSources.length} saved`"></span>
                                    <span class="rounded-full bg-red-700 px-2.5 py-1 text-[10px] font-black text-white" x-text="`${literatureWorkspaceCitedSourcesCount()} cited`"></span>
                                </div>
                                <p class="mt-1 text-xs leading-5 text-slate-600 dark:text-slate-300">Find verified papers, then highlight a claim in any supported narrative section and choose <span class="font-black text-red-800 dark:text-red-200">Support with source</span>.</p>
                            </div>
                            <button type="button" x-on:click="openLiteratureWorkspace()" class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-xl bg-red-700 px-4 text-xs font-black text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">Open literature workspace</button>
                        </div>
                    </aside>
                    <div>
                        <label for="related-literature" class="block text-xs font-black uppercase tracking-wider text-gray-600">Related Studies and Literature</label>
                        <p class="mt-1 text-xs text-gray-500">Include at least ten relevant studies or literature sources. Highlight a supported claim, then choose <span class="font-black text-red-800">Support with source</span>.</p>
                        <textarea id="related-literature" name="related_literature" rows="14" required maxlength="{{ config('detailed_proposal.maximum_narrative_length') }}" x-model="relatedLiterature" data-semantic-editor class="mt-2 block w-full scroll-mt-36 rounded-xl border-gray-300 text-sm leading-6 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                    </div>
                </div>
            </section>

            <section data-revision-section="section-methodology" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-base font-black text-gray-900">XII. Methodology</h3>
                        <p class="mt-1 text-xs leading-5 text-gray-500">The three methodology parts are shown as bullets. Images belong to Research Design only; Data Analysis is optional and is omitted from the output when blank.</p>
                    </div>
                    <button type="button" x-on:click="openMethodologyImagePicker('research_design')" class="inline-flex shrink-0 items-center justify-center rounded-xl border border-red-200 px-4 py-2.5 text-xs font-bold text-red-700 hover:bg-red-50">Add Research Design visual</button>
                    <input x-ref="methodologyImagePicker" type="file" accept="image/jpeg,image/png,image/gif,image/bmp" multiple class="sr-only" x-on:change="addMethodologyImages($event.target.files, methodologyImageTarget)">
                </div>
                <input type="hidden" name="methodology_images_present" value="1">
                <div class="mt-5 space-y-5">
                    @foreach ($methodologyFields as $key => $label)
                        <div>
                            @if ($key === 'specific_methods')
                                <h4 class="text-xs font-black uppercase tracking-wider text-gray-600">&bull; {{ $label }}</h4>
                            @else
                                <label for="methodology-{{ $key }}" class="block text-xs font-black uppercase tracking-wider text-gray-600">&bull; {{ $label }} @if ($key === 'data_analysis')<span class="font-normal normal-case text-gray-400">Optional</span>@endif</label>
                            @endif
                            @if ($key === 'research_design')
                            <div class="mt-3 rounded-xl border-2 border-dashed border-gray-200 bg-gray-50 p-3 transition hover:border-red-300 hover:bg-red-50/30" x-on:dragover.prevent x-on:drop.prevent="handleMethodologyDrop($event, '{{ $key }}')">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-xs font-semibold text-gray-600">Drop a visual here to place it under Research Design.</p>
                                    <button type="button" x-on:click="openMethodologyImagePicker('{{ $key }}')" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-bold text-gray-700 hover:bg-gray-100">Choose image</button>
                                </div>
                                <p x-show="methodologyImagesFor('{{ $key }}').length === 0" class="mt-3 text-xs text-gray-500">PNG, JPG, GIF, or BMP up to 10 MB.</p>
                                <div class="mt-3 space-y-3">
                                    <template x-for="image in methodologyImagesFor('{{ $key }}')" :key="image.clientId">
                                        <article class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
                                            <div class="flex flex-col gap-3 sm:flex-row">
                                                <a x-bind:href="image.previewUrl" target="_blank" rel="noopener" class="flex min-h-64 w-full items-center justify-center rounded-lg border border-gray-300 bg-gray-50 p-2 sm:w-96" title="Open full-size preview">
                                                    <img x-bind:src="image.previewUrl" x-bind:alt="image.caption || 'Methodology visual'" class="max-h-80 w-full object-contain">
                                                </a>
                                                <div class="min-w-0 flex-1 space-y-3">
                                                    <div class="flex items-start justify-between gap-3"><p class="truncate text-xs font-bold text-gray-800" x-text="image.originalFilename || 'Methodology visual'"></p><a x-bind:href="image.previewUrl" target="_blank" rel="noopener" class="shrink-0 text-xs font-bold text-red-700 hover:underline">Full preview</a></div>
                                                    <div class="grid gap-3 sm:grid-cols-2">
                                                        <div><label class="block text-[10px] font-black uppercase tracking-wider text-gray-500">Alignment</label><div class="mt-1 grid grid-cols-3 overflow-hidden rounded-lg border border-gray-300"><template x-for="alignment in ['left', 'center', 'right']" :key="alignment"><button type="button" x-on:click="image.alignment = alignment; scheduleDetailedProposalAutoSave()" x-bind:class="image.alignment === alignment ? 'bg-red-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'" class="px-2 py-1.5 text-xs font-bold" x-text="alignment.charAt(0).toUpperCase() + alignment.slice(1)"></button></template></div></div>
                                                        <div><label class="block text-[10px] font-black uppercase tracking-wider text-gray-500">Size</label><select x-model="image.size" class="mt-1 block w-full rounded-lg border-gray-300 py-1.5 text-xs focus:border-red-600 focus:ring-red-600"><option value="small">Small</option><option value="medium">Medium</option><option value="large">Large</option></select></div>
                                                    </div>
                                                    <div><label class="block text-[10px] font-black uppercase tracking-wider text-gray-500" :for="`methodology-image-caption-${image.clientId}`"><span x-text="`Figure ${methodologyImageFigureNumber(image)} title`"></span></label><input :id="`methodology-image-caption-${image.clientId}`" type="text" required maxlength="500" x-model="image.caption" placeholder="e.g., Proposed data-collection workflow" class="mt-1 block w-full rounded-lg border-gray-300 py-1.5 text-xs focus:border-red-600 focus:ring-red-600"></div>
                                                    <div class="flex flex-wrap items-center gap-2"><label :for="`methodology-image-file-${image.clientId}`" class="cursor-pointer rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-bold text-gray-700 hover:bg-gray-50">Replace image</label><button type="button" x-on:click="moveMethodologyImage(image, -1)" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-bold text-gray-700 hover:bg-gray-50">Move up</button><button type="button" x-on:click="moveMethodologyImage(image, 1)" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-bold text-gray-700 hover:bg-gray-50">Move down</button><button type="button" x-on:click="removeMethodologyImage(image)" class="rounded-lg px-3 py-1.5 text-xs font-bold text-red-700 hover:bg-red-50">Remove</button></div>
                                                </div>
                                            </div>
                                            <input type="hidden" :name="`methodology_images[${methodologyImageIndex(image)}][id]`" :value="image.id">
                                            <input type="hidden" :name="`methodology_images[${methodologyImageIndex(image)}][client_id]`" :value="image.clientId">
                                            <input type="hidden" :name="`methodology_images[${methodologyImageIndex(image)}][section]`" value="research_design">
                                            <input type="hidden" :name="`methodology_images[${methodologyImageIndex(image)}][alignment]`" :value="image.alignment">
                                            <input type="hidden" :name="`methodology_images[${methodologyImageIndex(image)}][size]`" :value="image.size">
                                            <input type="hidden" :name="`methodology_images[${methodologyImageIndex(image)}][caption]`" :value="image.caption">
                                            <input :id="`methodology-image-file-${image.clientId}`" :name="`methodology_images[${methodologyImageIndex(image)}][image]`" type="file" accept="image/jpeg,image/png,image/gif,image/bmp" class="sr-only" x-on:change="replaceMethodologyImage(image, $event.target.files)">
                                        </article>
                                    </template>
                                </div>
                            </div>
                            @endif
                            @if ($key === 'specific_methods')
                                <div id="methodology-specific-methods" class="mt-3">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <p class="text-sm leading-6 text-gray-600">Write each method heading in your own words, then list the numbered methods below it.</p>
                                        <button type="button" x-on:click="addSpecificMethodGroup()" class="inline-flex min-h-10 shrink-0 items-center justify-center gap-2 rounded-xl border border-red-200 bg-white px-3.5 text-sm font-bold text-red-700 transition hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2">
                                            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M8 3v10M3 8h10" stroke-linecap="round"/></svg>
                                            Add method group
                                        </button>
                                    </div>
                                    <input type="hidden" name="methodology[specific_methods]" x-bind:value="specificMethodsDocumentText()">
                                    <div x-show="specificMethodGroups.length" class="mt-4 divide-y divide-gray-200 overflow-hidden rounded-xl border border-gray-200 bg-white">
                                        <template x-for="(group, groupIndex) in specificMethodGroups" :key="`specific-method-group-${group.id}`">
                                            <article class="p-4 sm:p-5">
                                                <div class="flex items-start gap-3">
                                                    <span class="inline-flex h-8 min-w-8 shrink-0 items-center justify-center rounded-full bg-red-700 px-2 text-sm font-black text-white" x-text="`${specificMethodGroupLetter(groupIndex)}.`"></span>
                                                    <textarea :name="`specific_method_objectives[${groupIndex}][heading]`" rows="2" required maxlength="{{ config('detailed_proposal.maximum_narrative_length') }}" x-model="group.heading" :aria-label="`Method heading ${specificMethodGroupLetter(groupIndex)}`" placeholder="Write this method heading" class="block min-w-0 flex-1 rounded-xl border-gray-300 text-sm font-black leading-6 text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                                                    <button x-show="specificMethodGroups.length > 1" type="button" x-on:click="removeSpecificMethodGroup(groupIndex)" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-red-200 bg-white text-red-700 transition hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2" :aria-label="`Remove method group ${specificMethodGroupLetter(groupIndex)}`" title="Remove method group">
                                                        <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 4h10M6 4V2.5h4V4M5 6.5v5M8 6.5v5M11 6.5v5M4 4l.7 10h6.6L12 4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                        <span class="sr-only">Remove method group</span>
                                                    </button>
                                                </div>
                                                <div class="mt-4 space-y-3">
                                                    <template x-for="(method, methodIndex) in group.methods" :key="method.id">
                                                        <div class="flex items-start gap-3">
                                                            <span class="w-6 shrink-0 pt-3 text-sm font-black text-gray-500" x-text="`${methodIndex + 1}.`"></span>
                                                            <textarea :name="`specific_method_objectives[${groupIndex}][methods][${methodIndex}][description]`" rows="3" required maxlength="{{ config('detailed_proposal.maximum_narrative_length') }}" x-model="method.description" :aria-label="`Method ${methodIndex + 1} for group ${specificMethodGroupLetter(groupIndex)}`" placeholder="Describe the method used to attain this heading" class="block min-w-0 flex-1 rounded-xl border-gray-300 text-sm leading-6 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                                                            <button x-show="group.methods.length > 1" type="button" x-on:click="removeSpecificMethod(group, methodIndex)" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-red-200 bg-white text-red-700 transition hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2" :aria-label="`Remove method ${methodIndex + 1}`" title="Remove method">
                                                                <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 4h10M6 4V2.5h4V4M5 6.5v5M8 6.5v5M11 6.5v5M4 4l.7 10h6.6L12 4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                                <span class="sr-only">Remove</span>
                                                            </button>
                                                        </div>
                                                    </template>
                                                    <template x-if="!group.methods.length">
                                                        <input type="hidden" :name="`specific_method_objectives[${groupIndex}][methods][0][description]`" value="">
                                                    </template>
                                                </div>
                                                <button type="button" x-on:click="addSpecificMethod(group)" class="mt-4 inline-flex min-h-10 items-center gap-2 rounded-xl border border-red-200 bg-white px-3.5 text-sm font-bold text-red-700 transition hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2">
                                                    <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M8 3v10M3 8h10" stroke-linecap="round"/></svg>
                                                    Add method
                                                </button>
                                            </article>
                                        </template>
                                    </div>
                                    <p x-show="!specificMethodGroups.length" class="mt-4 rounded-xl border border-dashed border-gray-300 px-4 py-5 text-sm leading-6 text-gray-600">Add a method group to start outlining the procedures.</p>
                                </div>
                            @else
                                <textarea id="methodology-{{ $key }}" name="methodology[{{ $key }}]" rows="7" @required($key !== 'data_analysis') maxlength="{{ config('detailed_proposal.maximum_narrative_length') }}" x-model="methodology.{{ $key }}" aria-label="{{ $label }} narrative" data-semantic-editor class="mt-3 block w-full rounded-xl border-gray-300 text-sm leading-6 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            <section data-revision-section="section-responsibilities" id="responsibilities" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-end justify-between gap-3"><div><h3 class="text-base font-black text-gray-900">XIII. Duties and Responsibilities of Each Member</h3><p class="mt-1 text-xs text-gray-500">Include the project leader and every participating member.</p></div><button type="button" x-on:click="addResponsibility" class="shrink-0 rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50">Add member</button></div>
                <div class="mt-5 space-y-4">
                    <template x-for="(responsibility, index) in responsibilities" :key="responsibility.id">
                        <div class="rounded-xl border border-gray-200 p-4">
                            <div class="grid items-end gap-3 sm:grid-cols-[minmax(0,1fr)_9rem_auto]">
                                <div><label class="text-[10px] font-black uppercase tracking-wider text-gray-500" :for="`responsibility-name-${responsibility.id}`">Member name</label><input :id="`responsibility-name-${responsibility.id}`" :name="`responsibilities[${index}][name]`" type="text" required maxlength="255" list="detailed-proposal-member-names" x-model="responsibility.name" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm uppercase shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                                <div><label class="text-[10px] font-black uppercase tracking-wider text-gray-500" :for="`responsibility-percentage-${responsibility.id}`">Responsibility %</label><input :id="`responsibility-percentage-${responsibility.id}`" :name="`responsibilities[${index}][percentage]`" type="number" required min="1" max="100" x-model="responsibility.percentage" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                                <button type="button" x-on:click="removeResponsibility(index)" x-bind:disabled="responsibilities.length === 1" class="rounded-xl px-3 py-2.5 text-xs font-bold text-red-700 hover:bg-red-50 disabled:opacity-40">Remove</button>
                            </div>
                            <label class="mt-3 block text-[10px] font-black uppercase tracking-wider text-gray-500" :for="`responsibility-duties-${responsibility.id}`">Duties and responsibilities</label><textarea :id="`responsibility-duties-${responsibility.id}`" :name="`responsibilities[${index}][duties]`" rows="5" required maxlength="{{ config('detailed_proposal.maximum_narrative_length') }}" x-model="responsibility.duties" data-semantic-editor class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm leading-6 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                        </div>
                    </template>
                </div>
            </section>

            <x-proposal-signatory-summary :proposal-draft="$proposalDraft" paper="detailed_proposal" />

            <section data-revision-section="section-references" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <label for="references" class="block text-base font-black text-gray-900">XVI. References</label>
                <p class="mt-1 text-xs text-gray-500">Enter one reference per line or separate entries with blank lines.</p>
                <textarea id="references" name="references" rows="12" required maxlength="{{ config('detailed_proposal.maximum_narrative_length') }}" x-model="references" data-semantic-editor class="mt-4 block w-full scroll-mt-36 rounded-xl border-gray-300 text-sm leading-6 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
            </section>

            <section data-revision-section="section-work-plan section-budget section-curriculum-vitae" class="rounded-2xl border border-blue-200 bg-blue-50 p-5 text-sm text-blue-900">
                <h3 class="font-black">Sections generated automatically</h3>
                <p class="mt-1 leading-6">XIV links Attachment A, XV pulls MOOE and Capital Outlay totals from Attachment B, XVII links Attachment C, and the prepared-by name and agency details repeat on the signature page. Approval titles are fixed; the three names come from the fields above.</p>
            </section>

            <div class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:flex-wrap sm:justify-end">
                <button type="button" x-on:click="generatePreview" x-bind:disabled="previewLoading" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-gray-900 px-5 py-3 text-sm font-bold text-gray-900 hover:bg-gray-50 disabled:opacity-50 sm:w-auto"><span x-show="previewLoading" x-cloak class="h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-gray-900"></span><span x-text="previewLoading ? 'Generating…' : 'Preview content'"></span></button>
                <button type="button" x-on:click="downloadDocument" x-bind:disabled="!isComplete()" @disabled(! $projectDetailsComplete) class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-red-200 px-5 py-3 text-sm font-bold text-red-700 hover:bg-red-50 disabled:opacity-50 sm:w-auto"><span x-show="downloadLoading" x-cloak class="h-4 w-4 animate-spin rounded-full border-2 border-red-200 border-t-red-700"></span><span x-text="downloadLoading ? 'Preparing…' : 'Download exact Word file'"></span></button>
                <noscript>
                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700 sm:w-auto">Save Detailed Proposal</button>
                </noscript>
            </div>
        </form>

        <div x-show="previewError || downloadError" x-cloak role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" x-text="previewError || downloadError"></div>
        <section x-show="previewHtml" x-cloak class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
            <div class="mb-4 flex items-start justify-between gap-3"><div><h3 class="text-base font-black text-gray-900">Detailed proposal content preview</h3><p class="mt-1 text-xs text-gray-500">Use the Word download for the exact official page layout.</p></div><button type="button" x-on:click="printPreview" x-bind:disabled="!previewReady" class="rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 disabled:opacity-50">Print preview</button></div>
            <iframe x-ref="previewFrame" x-bind:srcdoc="previewHtml" x-on:load="previewReady = true" title="Detailed Research Proposal content preview" class="h-[80vh] w-full rounded-xl border border-gray-200 bg-white"></iframe>
        </section>
    </div>
</x-app-layout>
