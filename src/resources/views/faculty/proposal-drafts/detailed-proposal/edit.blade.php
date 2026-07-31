<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-2xl font-black tracking-tight text-gray-900">{{ $paper['label'] }}</h2>
                    <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $detailedProposalDocument?->completed_at ? 'bg-green-100 text-green-800' : ($detailedProposalDocument ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600') }}">{{ $detailedProposalDocument?->completed_at ? 'Complete' : ($detailedProposalDocument ? 'In progress' : 'Not started') }}</span>
                </div>
                <p class="mt-1 text-xs text-gray-500">Complete the official BatStateU-FO-RES-02 Rev. 04 form through structured inputs.</p>
            </div>
            <x-back-link data-paper-cancel-exit href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}#required-pdf-attachments" class="w-full shrink-0 sm:w-auto">Exit editor</x-back-link>
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
        data-paper-project-details-complete="{{ $projectDetailsComplete ? 'true' : 'false' }}"
        data-paper-dirty="{{ $errors->any() ? 'true' : 'false' }}"
        data-paper-edit-url="{{ route('faculty.proposal-drafts.detailed-proposal.edit', $proposalDraft) }}"
        data-paper-exit-url="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}#required-pdf-attachments"
        x-data="proposalDraftDetailedProposal({
            initialData: @js($initialData),
            workspacePeople: @js($workspacePeople),
            projectLeader: @js($proposalDraft->project_leader),
            expectedOutputKeys: @js(array_keys($expectedOutputs)),
            methodologyKeys: @js(array_keys($methodologyFields)),
            methodologySections: @js($methodologyFields),
            methodologyImageUrlTemplate: @js(route('faculty.proposal-drafts.detailed-proposal.methodology-images.show', [$proposalDraft, '__image_id__'])),
            previewUrl: @js(route('faculty.proposal-drafts.detailed-proposal.preview', $proposalDraft)),
            downloadUrl: @js(route('faculty.proposal-drafts.detailed-proposal.download', $proposalDraft)),
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

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
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

        <form data-paper-form x-ref="form" action="{{ route('faculty.proposal-drafts.detailed-proposal.update', $proposalDraft) }}" method="POST" enctype="multipart/form-data" class="space-y-6" novalidate>
            @csrf
            @method('PUT')
            <input type="hidden" name="document_version" value="{{ old('document_version', $detailedProposalDocument?->lock_version ?? 0) }}">
            <input type="hidden" name="save_as_draft" value="0" data-paper-save-mode>
            <input type="hidden" name="staff" value="">

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <h3 class="text-base font-black text-gray-900">II–III. Research alignment</h3>
                <div class="mt-5">
                    <label for="research-agenda" class="block text-xs font-black uppercase tracking-wider text-gray-600">II. BatStateU Research Agenda</label>
                    <input id="research-agenda" name="research_agenda" type="text" required maxlength="500" x-model="researchAgenda" placeholder="Type the applicable BatStateU research agenda" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                </div>
                <fieldset class="mt-6">
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

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div><h3 class="text-base font-black text-gray-900">IV. Project leader and staff</h3><p class="mt-1 text-xs text-gray-500">Names follow the official uppercase format. Add a professional title such as Asst Prof. or Dr. when applicable.</p></div>
                    <div class="relative w-full sm:w-80" x-on:click.outside="workspacePickerOpen = false">
                        <label for="workspace-person-search" class="sr-only">Add a workspace member</label>
                        <div class="relative">
                            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 3.474 9.765l3.63 3.63a.75.75 0 0 0 1.06-1.06l-3.629-3.63A5.5 5.5 0 0 0 9 3.5ZM5 9a4 4 0 1 1 8 0 4 4 0 0 1-8 0Z" clip-rule="evenodd"/></svg>
                            <input id="workspace-person-search" type="search" autocomplete="off" placeholder="Search workspace members" x-model="workspacePersonQuery" x-on:focus="workspacePickerOpen = true" x-on:input="workspacePickerOpen = true" x-on:keydown.escape="workspacePickerOpen = false" role="combobox" aria-autocomplete="list" x-bind:aria-expanded="workspacePickerOpen" aria-controls="workspace-person-options" class="block w-full rounded-xl border-gray-300 py-2.5 pl-9 pr-3 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                        </div>
                        <div id="workspace-person-options" x-show="workspacePickerOpen" x-transition.origin.top.right x-cloak role="listbox" class="absolute right-0 z-30 mt-2 max-h-72 w-full overflow-y-auto rounded-2xl border border-gray-200 bg-white p-2 shadow-xl">
                            <template x-for="person in filteredWorkspacePeople()" :key="person.key">
                                <button type="button" role="option" x-on:click="addWorkspacePerson(person.key)" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left hover:bg-gray-100 focus:bg-gray-100 focus:outline-none">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-red-100 text-xs font-black text-red-700">
                                        <img x-show="person.avatar" x-bind:src="person.avatar" x-bind:alt="personDisplayName(person.name)" x-on:error="person.avatar = ''" class="h-full w-full object-cover">
                                        <span x-show="!person.avatar" x-text="personInitials(person.name)"></span>
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-bold uppercase text-gray-900" x-text="personDisplayName(person.name)"></span>
                                        <span class="block truncate text-xs text-gray-500" x-text="person.email"></span>
                                    </span>
                                </button>
                            </template>
                            <p x-show="filteredWorkspacePeople().length === 0" class="px-3 py-5 text-center text-xs leading-5 text-gray-500">No available workspace member matches your search.</p>
                        </div>
                    </div>
                </div>
                <div class="mt-5 grid gap-4 rounded-xl border border-gray-200 bg-gray-50 p-4 sm:grid-cols-2 lg:grid-cols-[9rem_minmax(0,1fr)_minmax(0,1fr)_12rem]">
                    <div class="flex min-w-0 flex-col"><label for="leader-title" class="flex h-5 items-center gap-1 whitespace-nowrap text-[10px] font-black uppercase tracking-wider text-gray-500">Title <span class="font-normal normal-case tracking-normal text-gray-400">(optional)</span></label><input id="leader-title" name="leader_title" type="text" maxlength="50" list="detailed-proposal-professional-titles" x-model="leaderTitle" placeholder="e.g. Asst Prof." class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                    <div class="flex min-w-0 flex-col"><label for="leader-name" class="flex h-5 items-center text-[10px] font-black uppercase tracking-wider text-gray-500">Project Leader</label><input id="leader-name" type="text" value="{{ $proposalDraft->project_leader }}" readonly aria-readonly="true" class="mt-1.5 block h-11 w-full cursor-default rounded-xl border-gray-200 bg-white text-sm font-bold uppercase text-gray-900 shadow-sm focus:border-gray-300 focus:ring-0"></div>
                    <div class="flex min-w-0 flex-col"><label for="leader-email" class="flex h-5 items-center text-[10px] font-black uppercase tracking-wider text-gray-500">Email Address</label><input id="leader-email" name="leader_email" type="email" required maxlength="255" x-model="leaderEmail" class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                    <div class="flex min-w-0 flex-col"><label for="leader-contact" class="flex h-5 items-center text-[10px] font-black uppercase tracking-wider text-gray-500">Contact Number</label><input id="leader-contact" name="leader_contact" type="tel" required maxlength="11" inputmode="numeric" pattern="[0-9]{11}" autocomplete="tel" x-model="leaderContact" x-on:input="leaderContact = normalizeContactNumber($event.target.value); $event.target.value = leaderContact" class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                </div>
                <div class="mt-4 space-y-3">
                    <template x-for="(member, index) in staff" :key="member.id">
                        <div class="grid gap-3 rounded-xl border border-gray-200 p-4 md:grid-cols-2 lg:grid-cols-[9rem_minmax(0,1fr)_minmax(0,1fr)_12rem_auto] lg:items-end">
                            <div class="flex min-w-0 flex-col"><label class="flex h-5 items-center gap-1 whitespace-nowrap text-[10px] font-black uppercase tracking-wider text-gray-500" :for="`staff-title-${member.id}`">Title <span class="font-normal normal-case tracking-normal text-gray-400">(optional)</span></label><input :id="`staff-title-${member.id}`" :name="`staff[${index}][title]`" type="text" maxlength="50" list="detailed-proposal-professional-titles" x-model="member.title" placeholder="e.g. Dr." class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                            <div class="flex min-w-0 flex-col"><label class="flex h-5 items-center text-[10px] font-black uppercase tracking-wider text-gray-500" :for="`staff-name-${member.id}`">Project Staff</label><input :id="`staff-name-${member.id}`" :name="`staff[${index}][name]`" type="text" required maxlength="255" list="detailed-proposal-member-names" x-model="member.name" x-on:change="syncStaff(member)" placeholder="Full name" class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm uppercase shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                            <div class="flex min-w-0 flex-col"><label class="flex h-5 items-center text-[10px] font-black uppercase tracking-wider text-gray-500" :for="`staff-email-${member.id}`">Email Address</label><input :id="`staff-email-${member.id}`" :name="`staff[${index}][email]`" type="email" required maxlength="255" x-model="member.email" placeholder="name@example.com" class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                            <div class="flex min-w-0 flex-col"><label class="flex h-5 items-center text-[10px] font-black uppercase tracking-wider text-gray-500" :for="`staff-contact-${member.id}`">Contact Number</label><input :id="`staff-contact-${member.id}`" :name="`staff[${index}][contact]`" type="tel" required maxlength="11" inputmode="numeric" pattern="[0-9]{11}" autocomplete="tel" x-model="member.contact" x-on:input="member.contact = normalizeContactNumber($event.target.value); $event.target.value = member.contact" placeholder="09XXXXXXXXX" class="mt-1.5 block h-11 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                            <button type="button" x-on:click="removeStaff(index)" class="h-11 rounded-xl px-3 text-xs font-bold text-red-700 hover:bg-red-50">Remove</button>
                        </div>
                    </template>
                    <div class="flex flex-col items-start gap-1.5">
                        <button type="button" x-on:click="addStaff" class="inline-flex rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50">Add external project staff</button>
                        <p class="text-xs text-gray-500">Enter the external staff member&rsquo;s optional professional title, name, email, and 11-digit contact number manually.</p>
                    </div>
                </div>
                <datalist id="detailed-proposal-member-names">@foreach ($workspacePeople as $person)<option value="{{ $person['name'] }}">{{ $person['email'] }}</option>@endforeach</datalist>
                <datalist id="detailed-proposal-professional-titles">@foreach ($professionalTitles as $title)<option value="{{ $title }}"></option>@endforeach</datalist>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <h3 class="text-base font-black text-gray-900">V–VI. Proponent and cooperating agencies</h3>
                <p class="mt-1 text-xs text-gray-500">The Proponent Agency line is intentionally left blank on the official form.</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div><label for="proponent-department" class="block text-xs font-black uppercase tracking-wider text-gray-600">Department <span class="font-normal normal-case text-gray-400">Optional</span></label><input id="proponent-department" name="proponent_department" type="text" maxlength="255" x-model="proponentDepartment" placeholder="Leave blank if not applicable" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                    <div><label for="proponent-college" class="block text-xs font-black uppercase tracking-wider text-gray-600">College <span class="font-normal normal-case text-gray-400">From your profile</span></label><input id="proponent-college" name="proponent_college" type="text" required maxlength="255" x-model="proponentCollege" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                    <div><label for="proponent-campus" class="block text-xs font-black uppercase tracking-wider text-gray-600">Campus</label><input id="proponent-campus" name="proponent_campus" type="text" required maxlength="255" x-model="proponentCampus" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                    <div><label for="cooperating-agency" class="block text-xs font-black uppercase tracking-wider text-gray-600">VI. Cooperating Agency <span class="font-normal normal-case text-gray-400">Optional</span></label><input id="cooperating-agency" name="cooperating_agency" type="text" maxlength="500" x-model="cooperatingAgency" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                </div>
            </section>

            @foreach ([
                'executive_brief' => ['VII. Executive Brief', 'Summarize the proposed project and its intended contribution.'],
                'rationale' => ['VIII. Rationale', 'Include available statistics related to the problem.'],
                'objectives' => ['IX. Objectives of the Project', 'State the general and specific objectives.'],
            ] as $field => [$label, $help])
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <label for="{{ str_replace('_', '-', $field) }}" class="block text-base font-black text-gray-900">{{ $label }}</label>
                    <p class="mt-1 text-xs text-gray-500">{{ $help }}</p>
                    <textarea id="{{ str_replace('_', '-', $field) }}" name="{{ $field }}" rows="{{ $field === 'rationale' ? 14 : 9 }}" required maxlength="{{ config('detailed_proposal.maximum_narrative_length') }}" x-model="{{ \Illuminate\Support\Str::camel($field) }}" class="mt-4 block w-full rounded-xl border-gray-300 text-sm leading-6 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                </section>
            @endforeach

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <h3 class="text-base font-black text-gray-900">X. Expected Output of the Project</h3>
                <p class="mt-1 text-xs text-gray-500">Complete the applicable expanded 6Ps and 2Is. At least one output is required.</p>
                <div class="mt-5 grid gap-4 lg:grid-cols-2">
                    @foreach ($expectedOutputs as $key => $label)
                        <div><label for="expected-output-{{ $key }}" class="block text-xs font-black uppercase tracking-wider text-gray-600">{{ $label }}</label><textarea id="expected-output-{{ $key }}" name="expected_outputs[{{ $key }}]" rows="3" maxlength="{{ config('detailed_proposal.maximum_narrative_length') }}" x-model="expectedOutputs.{{ $key }}" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></textarea></div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <h3 class="text-base font-black text-gray-900">XI. Introduction and Related Studies and Literature</h3>
                <div class="mt-5 grid gap-5">
                    <div>
                        <label for="introduction" class="block text-xs font-black uppercase tracking-wider text-gray-600">Introduction</label>
                        <textarea id="introduction" name="introduction" rows="10" required maxlength="{{ config('detailed_proposal.maximum_narrative_length') }}" x-model="introduction" class="mt-2 block w-full rounded-xl border-gray-300 text-sm leading-6 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                    </div>
                    <div>
                        <label for="related-literature" class="block text-xs font-black uppercase tracking-wider text-gray-600">Related Studies and Literature</label>
                        <p class="mt-1 text-xs text-gray-500">Include at least ten relevant studies or literature sources.</p>
                        <textarea id="related-literature" name="related_literature" rows="14" required maxlength="{{ config('detailed_proposal.maximum_narrative_length') }}" x-model="relatedLiterature" class="mt-2 block w-full rounded-xl border-gray-300 text-sm leading-6 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
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
                            <label for="methodology-{{ $key }}" class="block text-xs font-black uppercase tracking-wider text-gray-600">&bull; {{ $label }} @if ($key === 'data_analysis')<span class="font-normal normal-case text-gray-400">Optional</span>@endif</label>
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
                                                        <div><label class="block text-[10px] font-black uppercase tracking-wider text-gray-500">Alignment</label><div class="mt-1 grid grid-cols-3 overflow-hidden rounded-lg border border-gray-300"><template x-for="alignment in ['left', 'center', 'right']" :key="alignment"><button type="button" x-on:click="image.alignment = alignment" x-bind:class="image.alignment === alignment ? 'bg-red-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'" class="px-2 py-1.5 text-xs font-bold" x-text="alignment.charAt(0).toUpperCase() + alignment.slice(1)"></button></template></div></div>
                                                        <div><label class="block text-[10px] font-black uppercase tracking-wider text-gray-500">Size</label><select x-model="image.size" class="mt-1 block w-full rounded-lg border-gray-300 py-1.5 text-xs focus:border-red-600 focus:ring-red-600"><option value="small">Small</option><option value="medium">Medium</option><option value="large">Large</option></select></div>
                                                    </div>
                                                    <div><label class="block text-[10px] font-black uppercase tracking-wider text-gray-500" :for="`methodology-image-caption-${image.clientId}`"><span x-text="`Figure ${methodologyImageFigureNumber(image)} title`"></span></label><input :id="`methodology-image-caption-${image.clientId}`" type="text" required maxlength="500" x-model="image.caption" placeholder="e.g., Proposed data-collection workflow" class="mt-1 block w-full rounded-lg border-gray-300 py-1.5 text-xs focus:border-red-600 focus:ring-red-600"></div>
                                                    <div class="flex flex-wrap items-center gap-2"><label :for="`methodology-image-file-${image.clientId}`" class="cursor-pointer rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-bold text-gray-700 hover:bg-gray-50">Replace image</label><button type="button" x-on:click="moveMethodologyImage(image, -1)" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-bold text-gray-700 hover:bg-gray-50">Move up</button><button type="button" x-on:click="moveMethodologyImage(image, 1)" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-bold text-gray-700 hover:bg-gray-50">Move down</button><button type="button" x-on:click="removeMethodologyImage(image)" class="rounded-lg px-3 py-1.5 text-xs font-bold text-red-700 hover:bg-red-50">Remove</button></div>
                                                </div>
                                            </div>
                                            <input type="hidden" :name="`methodology_images[${methodologyImageIndex(image)}][id]`" :value="image.id">
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
                            <textarea id="methodology-{{ $key }}" name="methodology[{{ $key }}]" rows="7" @required($key !== 'data_analysis') maxlength="{{ config('detailed_proposal.maximum_narrative_length') }}" x-model="methodology.{{ $key }}" aria-label="{{ $label }} narrative" class="mt-3 block w-full rounded-xl border-gray-300 text-sm leading-6 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-end justify-between gap-3"><div><h3 class="text-base font-black text-gray-900">XIII. Duties and Responsibilities of Each Member</h3><p class="mt-1 text-xs text-gray-500">Include the project leader and every participating member.</p></div><button type="button" x-on:click="addResponsibility" class="shrink-0 rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50">Add member</button></div>
                <div class="mt-5 space-y-4">
                    <template x-for="(responsibility, index) in responsibilities" :key="responsibility.id">
                        <div class="rounded-xl border border-gray-200 p-4">
                            <div class="grid items-end gap-3 sm:grid-cols-[minmax(0,1fr)_9rem_auto]">
                                <div><label class="text-[10px] font-black uppercase tracking-wider text-gray-500" :for="`responsibility-name-${responsibility.id}`">Member name</label><input :id="`responsibility-name-${responsibility.id}`" :name="`responsibilities[${index}][name]`" type="text" required maxlength="255" list="detailed-proposal-member-names" x-model="responsibility.name" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm uppercase shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                                <div><label class="text-[10px] font-black uppercase tracking-wider text-gray-500" :for="`responsibility-percentage-${responsibility.id}`">Responsibility %</label><input :id="`responsibility-percentage-${responsibility.id}`" :name="`responsibilities[${index}][percentage]`" type="number" required min="1" max="100" x-model="responsibility.percentage" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                                <button type="button" x-on:click="removeResponsibility(index)" x-bind:disabled="responsibilities.length === 1" class="rounded-xl px-3 py-2.5 text-xs font-bold text-red-700 hover:bg-red-50 disabled:opacity-40">Remove</button>
                            </div>
                            <label class="mt-3 block text-[10px] font-black uppercase tracking-wider text-gray-500" :for="`responsibility-duties-${responsibility.id}`">Duties and responsibilities</label><textarea :id="`responsibility-duties-${responsibility.id}`" :name="`responsibilities[${index}][duties]`" rows="5" required maxlength="{{ config('detailed_proposal.maximum_narrative_length') }}" x-model="responsibility.duties" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm leading-6 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                        </div>
                    </template>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <h3 class="text-base font-black text-gray-900">Approval Signatory Names</h3>
                <p class="mt-1 text-xs leading-5 text-gray-500">Faculty may enter the three names shown in the approval blocks. Names are converted to uppercase and bold in the preview and Word file; the official titles remain fixed.</p>
                <div class="mt-5 grid gap-4 lg:grid-cols-3">
                    <div>
                        <label for="checked-verified-by-name" class="block text-xs font-black uppercase tracking-wider text-gray-600">Checked and Verified by</label>
                        <input id="checked-verified-by-name" name="checked_verified_by_name" type="text" maxlength="255" x-model="checkedVerifiedByName" placeholder="Enter name" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm uppercase shadow-sm focus:border-red-600 focus:ring-red-600">
                        <p class="mt-1.5 text-xs text-gray-500">Head, Research Office</p>
                    </div>
                    <div>
                        <label for="recommending-approval-name" class="block text-xs font-black uppercase tracking-wider text-gray-600">Recommending Approval</label>
                        <input id="recommending-approval-name" name="recommending_approval_name" type="text" maxlength="255" x-model="recommendingApprovalName" placeholder="Enter name" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm uppercase shadow-sm focus:border-red-600 focus:ring-red-600">
                        <p class="mt-1.5 text-xs text-gray-500">Vice Chancellor for Research Development and Extension Services</p>
                    </div>
                    <div>
                        <label for="approved-by-name" class="block text-xs font-black uppercase tracking-wider text-gray-600">Final Approval</label>
                        <input id="approved-by-name" name="approved_by_name" type="text" maxlength="255" x-model="approvedByName" placeholder="Enter name" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm uppercase shadow-sm focus:border-red-600 focus:ring-red-600">
                        <p class="mt-1.5 text-xs text-gray-500">University President/Vice President for RDES</p>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <label for="references" class="block text-base font-black text-gray-900">XVI. References</label>
                <p class="mt-1 text-xs text-gray-500">Enter one reference per line or separate entries with blank lines.</p>
                <textarea id="references" name="references" rows="12" required maxlength="{{ config('detailed_proposal.maximum_narrative_length') }}" x-model="references" class="mt-4 block w-full rounded-xl border-gray-300 text-sm leading-6 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
            </section>

            <section class="rounded-2xl border border-blue-200 bg-blue-50 p-5 text-sm text-blue-900">
                <h3 class="font-black">Sections generated automatically</h3>
                <p class="mt-1 leading-6">XIV links Attachment A, XV pulls MOOE and Capital Outlay totals from Attachment B, XVII links Attachment C, and the prepared-by name and agency details repeat on the signature page. Approval titles are fixed; the three names come from the fields above.</p>
            </section>

            @include('faculty.proposal-drafts.partials.change-note')

            <div class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:flex-wrap sm:justify-end">
                <button type="button" x-on:click="generatePreview" x-bind:disabled="previewLoading" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-gray-900 px-5 py-3 text-sm font-bold text-gray-900 hover:bg-gray-50 disabled:opacity-50 sm:w-auto"><span x-show="previewLoading" x-cloak class="h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-gray-900"></span><span x-text="previewLoading ? 'Generating…' : 'Preview content'"></span></button>
                <button type="button" x-on:click="downloadDocument" x-bind:disabled="!isComplete()" @disabled(! $projectDetailsComplete) class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-red-200 px-5 py-3 text-sm font-bold text-red-700 hover:bg-red-50 disabled:opacity-50 sm:w-auto"><span x-show="downloadLoading" x-cloak class="h-4 w-4 animate-spin rounded-full border-2 border-red-200 border-t-red-700"></span><span x-text="downloadLoading ? 'Preparing…' : 'Download exact Word file'"></span></button>
                <button data-paper-save-exit type="submit" name="exit_after_save" value="1" class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-50 sm:w-auto">Save and exit</button>
            </div>
        </form>

        <div x-show="previewError || downloadError" x-cloak role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" x-text="previewError || downloadError"></div>
        <section x-show="previewHtml" x-cloak class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
            <div class="mb-4 flex items-start justify-between gap-3"><div><h3 class="text-base font-black text-gray-900">Detailed proposal content preview</h3><p class="mt-1 text-xs text-gray-500">Use the Word download for the exact official page layout.</p></div><button type="button" x-on:click="printPreview" x-bind:disabled="!previewReady" class="rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 disabled:opacity-50">Print preview</button></div>
            <iframe x-ref="previewFrame" x-bind:srcdoc="previewHtml" x-on:load="previewReady = true" title="Detailed Research Proposal content preview" class="h-[80vh] w-full rounded-xl border border-gray-200 bg-white"></iframe>
        </section>
    </div>
</x-app-layout>
