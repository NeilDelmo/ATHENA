<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$paper['label']" subtitle="Complete the official BatStateU-FO-RES-02 Rev. 04 form through structured inputs.">
            <x-slot name="actions">
                <span data-detailed-proposal-completion-status class="rounded-full px-3 py-1 text-xs font-semibold {{ $detailedProposalComplete ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">{{ $detailedProposalComplete ? 'Complete' : ($detailedProposalDocument ? 'In progress' : 'Not started') }}</span>
                <x-back-link fixed data-paper-cancel-exit href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}#required-pdf-attachments">Exit editor</x-back-link>
            </x-slot>
        </x-page-header>
    </x-slot>

    @php
        $projectDetailsComplete = app(\App\Support\ProposalDraftReadiness::class)->projectDetailsAreComplete($proposalDraft);
        $initialData = array_replace_recursive($sourceData, old());
        $sdgs = config('detailed_proposal.sdgs');
        $expectedOutputs = config('detailed_proposal.expected_outputs');
        $methodologyFields = config('detailed_proposal.methodology');
        $professionalTitles = config('detailed_proposal.professional_titles');
        $sectionHeadings = config('detailed_proposal.section_headings');
    @endphp

    <div
        class="detailed-proposal-writing-workspace mx-auto w-full space-y-6 px-4 py-8 sm:px-6 lg:px-8"
        data-detailed-proposal-workspace
        data-proposal-paper-workspace
        data-paper-editor
        data-paper-draft-save="true"
        data-detailed-proposal-autosave="true"
        data-paper-project-details-complete="{{ $projectDetailsComplete ? 'true' : 'false' }}"
        data-paper-dirty="{{ $errors->any() ? 'true' : 'false' }}"
        data-paper-edit-url="{{ route('faculty.proposal-drafts.detailed-proposal.edit', $proposalDraft) }}"
        data-paper-exit-url="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}#required-pdf-attachments"
        x-on:proposal-cite-selection.window="openCitationPicker($event.detail)"
        x-on:proposal-open-sources.window="openSourceWorkspaceFromToolbar($event.detail)"
        x-on:athena-insert-literature.window="applyAssistantLiterature($event.detail)"
        x-data="proposalDraftDetailedProposal({
            proposalDraftId: @js($proposalDraft->id),
            initialData: @js($initialData),
            recheckCompletion: @js($detailedProposalDocument !== null && $detailedProposalDocument->completed_at === null),
            detailedProposalStarted: @js($detailedProposalDocument !== null),
            detailedProposalComplete: @js($detailedProposalComplete),
            completionErrors: @js($completionErrors),
            requirementsReviewed: @js($errors->any()),
            literatureSources: @js($literatureSources),
            initialLiteratureSourceId: @js($initialLiteratureSourceId),
            initialLiteratureAction: @js($initialLiteratureAction),
            workspacePeople: @js($workspacePeople),
            projectLeader: @js($proposalDraft->project_leader),
            proposalTitle: @js($proposalDraft->project_title),
            expectedOutputKeys: @js(array_keys($expectedOutputs)),
            methodologyKeys: @js(array_keys($methodologyFields)),
            methodologySections: @js($methodologyFields),
            figureSections: @js(config('detailed_proposal.image_sections')),
            methodologyImageUrlTemplate: @js(route('faculty.proposal-drafts.detailed-proposal.methodology-images.show', [$proposalDraft, '__image_id__'])),
            literatureSearchUrl: @js(route('research-support.literature-search')),
            literatureLibrarySearchUrl: @js(route('research-support.literature-library.index')),
            literatureLibrarySaveUrl: @js(route('research-support.literature-library.store')),
            literatureAttachUrlTemplate: @js(route('faculty.proposal-drafts.literature-sources.store', [$proposalDraft, '__literature_source__'])),
            literatureDraftUpdateUrlTemplate: @js(route('faculty.proposal-drafts.literature-drafts.update', [$proposalDraft, '__proposal_literature_source__'])),
            literatureSynthesisUrl: @js(route('research-support.literature-synthesis')),
            literatureFullTextPreviewUrl: @js(route('research-support.literature-full-text-preview')),
            literatureEvidenceBase: @js(str_replace('/__link__/evidence', '', route('faculty.proposal-drafts.literature-evidence.show', [$proposalDraft, '__link__']))),
            literatureEvidenceAssistanceUrl: @js(route('faculty.proposal-drafts.literature-evidence-assistance', $proposalDraft)),
            literatureMetadataUrl: @js(route('research-support.literature-metadata')),
            previewUrl: @js(route('faculty.proposal-drafts.detailed-proposal.preview', $proposalDraft)),
            downloadUrl: @js(route('faculty.proposal-drafts.detailed-proposal.download', $proposalDraft)),
            updateUrl: @js(route('faculty.proposal-drafts.detailed-proposal.update', $proposalDraft)),
            csrfToken: @js(csrf_token()),
            revisionUploadUrl: @js($proposalDraft->topic_id ? route('faculty.proposal-drafts.revision-files.store', $proposalDraft) : null),
            revisionDocumentType: @js($paper['document_type']),
            revisionAttachmentLabel: @js($paper['label']),
            revisionReviewUrl: @js($proposalDraft->topic_id ? route('faculty.topics.revision', $proposalDraft->topic_id).'#review-and-submit' : null),
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
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-4 dark:border-slate-700" data-proposal-writing-status>
        <div x-show="showDetailedProposalSaveStatus" x-cloak>
            <x-proposal-autosave-status />
        </div>
            <button type="button" @click="checkDetailedProposalRequirements()" data-proposal-check-requirements class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:border-slate-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#7A0019] dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">Check requirements</button>
        </div>
        <section x-show="showCompletionChecklist" x-cloak data-proposal-completion-checklist class="rounded-xl border border-slate-200 border-l-4 border-l-[#7A0019] bg-white p-5 dark:border-slate-700 dark:border-l-red-400 dark:bg-slate-900" aria-labelledby="proposal-completion-heading" aria-live="polite">
            <h3 id="proposal-completion-heading" class="text-sm font-semibold text-slate-900 dark:text-white">Before generating your proposal</h3>
            <p class="mt-1 max-w-prose text-xs leading-5 text-slate-500 dark:text-slate-400">Your draft is saved as you write. Select a requirement to finish that part of the form.</p>
            <ul class="mt-3 grid gap-x-6 gap-y-1 sm:grid-cols-2">
                <template x-for="(messages, field) in completionErrors" :key="field">
                    <li><button type="button" x-on:click="focusProposalRequirement(field)" class="min-h-10 w-full rounded-md py-2 text-left text-xs leading-5 text-slate-700 underline decoration-slate-300 underline-offset-4 hover:text-[#7A0019] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#7A0019] dark:text-slate-300 dark:decoration-slate-600 dark:hover:text-red-300" x-text="messages.join(' ')"></button></li>
                </template>
            </ul>
        </section>
        <x-proposal-collaboration-monitor
            :loaded-version="(int) old('document_version', $detailedProposalDocument?->lock_version ?? 0)"
            :state-url="route('faculty.proposal-drafts.edit-state', [$proposalDraft, $paper['document_type'], 0])"
            :reload-url="route('faculty.proposal-drafts.detailed-proposal.edit', $proposalDraft)"
            :label="$paper['label']"
        />

        @unless ($projectDetailsComplete)
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                <p class="max-w-prose text-xs leading-5">You can start writing now. Add the project title, dates and leader in Project Details before generating the document.</p>
                <a href="{{ route('faculty.proposal-drafts.details.edit', $proposalDraft) }}" class="inline-flex min-h-10 items-center rounded-lg px-2 text-xs font-semibold text-[#7A0019] underline underline-offset-4 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#7A0019] dark:text-red-300">Complete Project Details</a>
            </div>
        @endunless

        <x-detailed-proposal-writing-toolbar />
        <x-proposal-source-workspace />
        <div class="proposal-preview-workspace proposal-writing-columns" :class="{ 'proposal-writing-preview-hidden': !previewPaneOpen }" @resize.window.debounce.150ms="resizeProposalPaperPreview()">
        <div data-proposal-official-form-source :inert="previewFullscreen" class="proposal-edit-pane space-y-6" aria-label="Proposal editing form">
        <section data-revision-section="section-project-information" data-revision-shared-summary class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex justify-end">
                <a href="{{ route('faculty.proposal-drafts.details.edit', $proposalDraft) }}" class="inline-flex shrink-0 rounded-xl border border-red-200 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">Edit shared details</a>
            </div>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="sm:col-span-2 lg:col-span-4"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">{{ $sectionHeadings['project-information'] }}</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->project_title }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Leader</dt><dd class="mt-1 text-sm font-semibold uppercase text-gray-900">{{ $proposalDraft->project_leader }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">MOOE from Attachment B</dt><dd class="mt-1 text-sm font-semibold text-gray-900">Php {{ number_format($budgetTotals['mooe_total'], 2) }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Capital Outlay from Attachment B</dt><dd class="mt-1 text-sm font-semibold text-gray-900">Php {{ number_format($budgetTotals['co_total'], 2) }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Official form</dt><dd class="mt-1 text-sm font-semibold text-gray-900">BatStateU-FO-RES-02 Rev. 04</dd></div>
            </dl>
        </section>

        <form data-paper-form data-detailed-proposal-autosave-form x-ref="form" @input="markProposalPreviewStale()" @change="markProposalPreviewStale()" action="{{ route('faculty.proposal-drafts.detailed-proposal.update', $proposalDraft) }}" method="POST" enctype="multipart/form-data" class="space-y-6" novalidate>
            @csrf
            @method('PUT')
            <input type="hidden" name="document_version" value="{{ old('document_version', $detailedProposalDocument?->lock_version ?? 0) }}">
            <input type="hidden" name="draft_version" value="{{ old('draft_version', $proposalDraft->lock_version) }}">
            <input type="hidden" name="save_as_draft" value="0" data-paper-save-mode>
            <input x-ref="methodologyImagePicker" type="file" accept="image/jpeg,image/png,image/gif,image/bmp" multiple class="sr-only" x-on:change="addMethodologyImages($event.target.files, methodologyImageTarget)">
            <input type="hidden" name="methodology_images_present" value="1">
            <input type="hidden" name="staff" value="">
            <input type="hidden" name="literature_research_history" x-bind:value="JSON.stringify(literatureSearchHistory)">
            <input id="literature-citations" type="hidden" name="literature_citations" x-bind:value="JSON.stringify(literatureCitations)">

            <section data-revision-section="section-research-agenda" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <h3 class="text-base font-black text-gray-900">Research alignment</h3>
                <div class="mt-5">
                    <label for="research-agenda" class="block text-xs font-black uppercase tracking-wider text-gray-600">{{ $sectionHeadings['research-agenda'] }}</label>
                    <input id="research-agenda" name="research_agenda" type="text" required maxlength="500" x-model="researchAgenda" placeholder="Type the applicable BatStateU research agenda" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                </div>
                <fieldset data-revision-section="section-sdgs" data-detailed-proposal-validation-group="sdgs" tabindex="-1" class="mt-6">
                    <legend class="text-xs font-black uppercase tracking-wider text-gray-600">{{ $sectionHeadings['sdgs'] }} <span class="font-normal normal-case text-gray-500">(Check all applicable SDG)</span></legend>
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
                    <h3 class="text-base font-black text-gray-900 dark:text-white">{{ $sectionHeadings['project-team'] }}</h3>
                    <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-slate-400">Names follow the official uppercase format. Add a professional title such as Asst Prof. or Dr. when applicable.</p>
                </div>

                <div class="mt-5 grid gap-4 border-t border-gray-100 pt-5 dark:border-slate-800 lg:grid-cols-[minmax(0,1fr)_17rem]">
                    <div class="rounded-2xl border border-red-100 bg-red-50/50 p-4 dark:border-red-950/80 dark:bg-red-950/20 sm:p-5">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-xs font-black uppercase tracking-wider text-red-700 dark:text-red-300">Proposal workspace</p>
                                <h4 class="mt-1 text-sm font-black text-gray-900 dark:text-white">Add a workspace member</h4>
                                <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-slate-400">Search the available team members and add them directly as project staff.</p>
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
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="flex min-w-0 flex-col">
                        <label for="leader-title" class="flex h-5 items-center justify-between gap-2 text-[10px] font-black uppercase tracking-wider text-gray-600 dark:text-slate-300"><span>Professional title</span><span class="rounded-full border border-gray-300 bg-white px-2 py-0.5 text-[9px] normal-case tracking-normal text-gray-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-400">Optional</span></label>
                        <input id="leader-title" name="leader_title" type="text" maxlength="50" list="detailed-proposal-professional-titles" x-model="leaderTitle" placeholder="e.g. Asst Prof." aria-describedby="leader-title-help" class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                        <p id="leader-title-help" class="mt-1.5 text-[10px] leading-4 text-gray-500 dark:text-slate-400">Leave blank when no professional title applies.</p>
                    </div>
                    <div class="flex min-w-0 flex-col">
                        <label for="leader-name" class="flex h-5 items-center justify-between gap-2 text-[10px] font-black uppercase tracking-wider text-gray-600 dark:text-slate-300"><span>IV. Project Leader:</span><span class="text-[9px] text-red-600">Required</span></label>
                        <input id="leader-name" name="project_leader" type="text" required maxlength="120" list="detailed-proposal-member-names" x-model="projectLeader" x-on:change="syncProjectLeader()" placeholder="Type or choose a workspace member" aria-describedby="leader-name-help" class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm uppercase shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                        <p id="leader-name-help" class="mt-1.5 text-[10px] leading-4 text-gray-500 dark:text-slate-400">Changes also update Project Details and the prepared-by name.</p>
                    </div>
                    <div class="flex min-w-0 flex-col"><label for="leader-email" class="flex h-5 items-center text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-300">Email Address</label><input id="leader-email" name="leader_email" type="email" required maxlength="255" x-model="leaderEmail" class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></div>
                    <div class="flex min-w-0 flex-col"><label for="leader-contact" class="flex h-5 items-center text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-300">Contact Number</label><input id="leader-contact" name="leader_contact" type="tel" required maxlength="11" inputmode="numeric" pattern="[0-9]{11}" autocomplete="tel" x-model="leaderContact" x-on:input="leaderContact = normalizeContactNumber($event.target.value); $event.target.value = leaderContact" class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></div>
                    </div>
                </div>

                <div class="mt-5 border-t border-gray-100 pt-5 dark:border-slate-800">
                    <div class="flex items-center justify-between gap-3"><div><p class="text-xs font-black uppercase tracking-wider text-gray-600 dark:text-slate-300">Project Staff (s):</p><p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Each staff member can have an optional professional title.</p></div><span class="rounded-full bg-gray-100 px-2.5 py-1 text-[10px] font-black text-gray-600 dark:bg-slate-800 dark:text-slate-300" x-text="`${staff.length} ${staff.length === 1 ? 'member' : 'members'}`"></span></div>
                    <div class="mt-4 space-y-3">
                    <p x-show="staff.length === 0" class="rounded-xl bg-gray-50 px-4 py-3 text-xs leading-5 text-gray-500 dark:bg-slate-800/60 dark:text-slate-400">No project staff added yet. Choose a workspace member or add an external person above.</p>
                    <template x-for="(member, index) in staff" :key="member.id">
                        <div class="grid gap-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900 sm:grid-cols-2">
                            <div class="flex min-w-0 flex-col"><label class="flex h-5 items-center justify-between gap-2 text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-300" :for="`staff-title-${member.id}`"><span>Professional title</span><span class="rounded-full border border-gray-300 px-2 py-0.5 text-[9px] normal-case tracking-normal text-gray-500 dark:border-slate-600 dark:text-slate-400">Optional</span></label><input :id="`staff-title-${member.id}`" :name="`staff[${index}][title]`" type="text" maxlength="50" list="detailed-proposal-professional-titles" x-model="member.title" placeholder="e.g. Dr." class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-800 dark:text-white"></div>
                            <div class="flex min-w-0 flex-col"><label class="flex h-5 items-center text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-300" :for="`staff-name-${member.id}`">Project Staff (s):</label><input :id="`staff-name-${member.id}`" :name="`staff[${index}][name]`" type="text" required maxlength="255" list="detailed-proposal-member-names" x-model="member.name" x-on:change="syncStaff(member)" placeholder="Full name" class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm uppercase shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-800 dark:text-white"></div>
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
                <h3 class="text-base font-black text-gray-900">{{ $sectionHeadings['proponent'] }}</h3>
                <p class="mt-1 text-xs text-gray-500">The Proponent Agency line is intentionally left blank on the official form.</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div><label for="proponent-department" class="block text-xs font-black uppercase tracking-wider text-gray-600">Department <span class="font-normal normal-case text-gray-400">Optional</span></label><input id="proponent-department" name="proponent_department" type="text" maxlength="255" x-model="proponentDepartment" placeholder="Leave blank if not applicable" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                    <div><label for="proponent-college" class="block text-xs font-black uppercase tracking-wider text-gray-600">College <span class="font-normal normal-case text-gray-400">From your profile</span></label><input id="proponent-college" name="proponent_college" type="text" required maxlength="255" x-model="proponentCollege" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                    <div><label for="proponent-campus" class="block text-xs font-black uppercase tracking-wider text-gray-600">Campus</label><input id="proponent-campus" name="proponent_campus" type="text" required maxlength="255" x-model="proponentCampus" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                    <div data-revision-section="section-cooperating-agency"><label for="cooperating-agency" class="block text-xs font-black uppercase tracking-wider text-gray-600">{{ $sectionHeadings['cooperating-agency'] }} <span class="font-normal normal-case text-gray-400">Optional</span></label><input id="cooperating-agency" name="cooperating_agency" type="text" maxlength="500" x-model="cooperatingAgency" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                </div>
            </section>

            @foreach ([
                'executive_brief' => [$sectionHeadings['executive-brief'], 'Summarize the proposed project and its intended contribution.'],
                'rationale' => [$sectionHeadings['rationale'], 'Include available statistics related to the problem.'],
            ] as $field => [$label, $help])
                <section data-revision-section="section-{{ str_replace('_', '-', $field) }}" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <label for="{{ str_replace('_', '-', $field) }}" class="block text-base font-black text-gray-900">{{ $label }}</label>
                    <p class="mt-1 text-xs text-gray-500">{{ $help }}</p>
                    <x-proposal-figure-input :section="$field" compact />
                    <textarea id="{{ str_replace('_', '-', $field) }}" name="{{ $field }}" rows="{{ $field === 'rationale' ? 14 : 9 }}" required maxlength="{{ config('detailed_proposal.maximum_narrative_length') }}" x-model="{{ \Illuminate\Support\Str::camel($field) }}" data-semantic-editor class="mt-4 block w-full rounded-xl border-gray-300 text-sm leading-6 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                </section>
            @endforeach

            <section data-revision-section="section-objectives" id="specific-objectives" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <input type="hidden" name="specific_objectives_present" value="1">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-base font-black text-gray-900">{{ $sectionHeadings['objectives'] }}</h3>
                        <p class="mt-1 text-xs text-gray-500">Enter at least one specific objective in the required fields below. The general objective is optional. Numbering is generated automatically.</p>
                    </div>
                    <button type="button" x-on:click="addSpecificObjective" class="inline-flex shrink-0 rounded-xl border border-red-200 px-4 py-2 text-xs font-bold text-red-700 hover:bg-red-50">Add specific objective</button>
                </div>
                <label for="general-objective" class="mt-5 block text-xs font-black uppercase tracking-wider text-gray-600">General objective <span class="font-normal normal-case text-gray-400">Optional</span></label>
                <textarea id="general-objective" name="general_objective" rows="4" maxlength="{{ config('detailed_proposal.maximum_narrative_length') }}" x-model="generalObjective" data-semantic-editor class="mt-2 block w-full rounded-xl border-gray-300 text-sm leading-6 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                <div class="mt-5 space-y-3" data-specific-objectives-fields>
                    <h4 class="text-xs font-black uppercase tracking-wider text-gray-600">Specific objectives <span class="font-normal normal-case text-red-700">Required — at least one</span></h4>
                    <p class="text-xs leading-5 text-gray-500">Describe the concrete results or tasks your project will achieve. Text in General objective does not fill these fields.</p>
                    <p x-show="requirementsReviewed && completionErrors.specific_objectives" x-cloak class="text-xs font-semibold text-red-700 dark:text-red-300" x-text="(completionErrors.specific_objectives || []).join(' ')"></p>
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
                            <textarea :id="`specific-objective-${objective.id}`" :name="`specific_objectives[${index}][description]`" rows="3" required maxlength="{{ config('detailed_proposal.maximum_narrative_length') }}" x-model="objective.description" placeholder="e.g. Identify the needs of the target community." class="mt-2 block w-full rounded-xl border-gray-300 text-sm leading-6 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                        </article>
                    </template>
                </div>
            </section>

            <section data-revision-section="section-expected-outputs" id="expected-outputs" data-detailed-proposal-validation-group="expected-outputs" tabindex="-1" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h3 class="text-base font-black text-gray-900">{{ $sectionHeadings['expected-outputs'] }}</h3>
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

            <section data-revision-section="section-literature" x-ref="introductionSection" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <h3 class="text-base font-black text-gray-900">{{ $sectionHeadings['literature'] }}</h3>
                <div class="mt-5 grid gap-5">
                    <div>
                        <label for="introduction" class="block text-xs font-black uppercase tracking-wider text-gray-600">Review of Related Literature — opening paragraphs <span class="font-normal normal-case text-gray-500">Optional</span></label>
                        <p class="mt-1 text-xs text-gray-500">Previously saved introduction text is retained here and included at the start of Section XI.</p>
                        <x-proposal-figure-input section="introduction" compact />
                        <textarea id="introduction" name="introduction" rows="10" maxlength="{{ config('detailed_proposal.maximum_narrative_length') }}" x-model="introduction" data-semantic-editor class="mt-2 block w-full rounded-xl border-gray-300 text-sm leading-6 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                    </div>
                    <aside class="rounded-2xl border border-red-100 bg-red-50/60 p-4 dark:border-red-900/60 dark:bg-red-950/20" aria-labelledby="proposal-sources-heading">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 id="proposal-sources-heading" class="text-sm font-black text-slate-950 dark:text-white">Sources for this proposal</h4>
                                    <span class="rounded-full bg-white px-2.5 py-1 text-[10px] font-black text-slate-700 shadow-sm dark:bg-slate-900 dark:text-slate-200" x-text="`${literatureSources.length} saved`"></span>
                                    <span class="rounded-full bg-red-700 px-2.5 py-1 text-[10px] font-black text-white" x-text="`${literatureWorkspaceCitedSourcesCount()} cited`"></span>
                                </div>
                                <p class="mt-1 text-xs leading-5 text-slate-600 dark:text-slate-300">Import papers, read passages, and keep evidence beside your writing. Use <span class="font-semibold text-red-800 dark:text-red-200">Insert citation</span> at the cursor in any supported narrative section.</p>
                            </div>
                            <button type="button" x-on:click="openSourceWorkspace('paper')" class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-xl bg-red-700 px-4 text-xs font-semibold text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">Open Sources</button>
                        </div>
                    </aside>
                    <div>
                        <label for="related-literature" class="block text-xs font-black uppercase tracking-wider text-gray-600">{{ $sectionHeadings['literature'] }}</label>
                        <p class="mt-1 text-xs text-gray-500">Include at least ten relevant studies or literature sources. Write your review, then use <span class="font-semibold text-red-800">Insert citation</span> to cite the sources supporting each claim.</p>
                        <x-proposal-figure-input section="related_literature" compact />
                        <textarea id="related-literature" name="related_literature" rows="14" required maxlength="{{ config('detailed_proposal.maximum_narrative_length') }}" x-model="relatedLiterature" data-semantic-editor class="mt-2 block w-full scroll-mt-36 rounded-xl border-gray-300 text-sm leading-6 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                    </div>
                </div>
            </section>

            <section data-revision-section="section-methodology" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-base font-black text-gray-900">{{ $sectionHeadings['methodology'] }}</h3>
                        <p class="mt-1 text-xs leading-5 text-gray-500">Add figures to any methodology part. Data Analysis is optional and is omitted from the output when blank.</p>
                    </div>
                </div>
                <div class="mt-5 space-y-5">
                    @foreach ($methodologyFields as $key => $label)
                        <div>
                            @if ($key === 'specific_methods')
                                <h4 class="text-xs font-black uppercase tracking-wider text-gray-600">&bull; {{ $label }}</h4>
                            @else
                                <label for="methodology-{{ $key }}" class="block text-xs font-black uppercase tracking-wider text-gray-600">&bull; {{ $label }} @if ($key === 'data_analysis')<span class="font-normal normal-case text-gray-400">Optional</span>@endif</label>
                            @endif
                            <x-proposal-figure-input :section="$key" compact />
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
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><h3 class="text-base font-black text-gray-900">{{ $sectionHeadings['responsibilities'] }}</h3><p class="mt-1 text-xs text-gray-500">Include the project leader and every participating member.</p></div><button type="button" x-on:click="addResponsibility" class="self-start shrink-0 rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50">Add member</button></div>
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

            <section data-revision-section="section-work-plan" data-proposal-attachment-reference class="border-l-2 border-slate-200 py-1 pl-3 dark:border-slate-700">
                <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $sectionHeadings['work-plan'] }}</h3>
                <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Provided in Work Plan (Form A).</p>
            </section>

            <section data-revision-section="section-budget" data-proposal-attachment-reference class="border-l-2 border-slate-200 py-1 pl-3 dark:border-slate-700">
                <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $sectionHeadings['budget'] }}</h3>
                <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Provided in Line-Item Budget (Form B).</p>
            </section>

            <section data-revision-section="section-references" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <label for="references" class="block text-base font-black text-gray-900">{{ $sectionHeadings['references'] }}</label>
                <p class="mt-1 text-xs text-gray-500">Enter one reference per line or separate entries with blank lines.</p>
                <textarea id="references" name="references" rows="12" required maxlength="{{ config('detailed_proposal.maximum_narrative_length') }}" x-model="references" data-semantic-editor class="mt-4 block w-full scroll-mt-36 rounded-xl border-gray-300 text-sm leading-6 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
            </section>

            <section data-revision-section="section-curriculum-vitae" data-proposal-attachment-reference class="border-l-2 border-slate-200 py-1 pl-3 dark:border-slate-700">
                <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $sectionHeadings['curriculum-vitae'] }}</h3>
                <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Provided in Curriculum Vitae (Form C).</p>
            </section>

            <x-proposal-signatory-summary :proposal-draft="$proposalDraft" paper="detailed_proposal" />

            <noscript>
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700 sm:w-auto">Save Detailed Proposal</button>
            </noscript>
        </form>

        <div x-show="previewError || downloadError" x-cloak role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" x-text="previewError || downloadError"></div>
        </div>
        <x-detailed-proposal-preview />
        </div>
        <button type="button" x-show="!previewPaneOpen" x-cloak @click="showProposalPreview()" aria-controls="proposal-preview-panel" :aria-expanded="previewPaneOpen" class="proposal-writing-preview-launcher">Preview paper</button>
    </div>
</x-app-layout>
