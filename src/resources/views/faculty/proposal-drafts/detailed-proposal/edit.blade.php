<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" class="text-xs font-bold text-red-600 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">&larr; Proposal package</a>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h2 class="text-2xl font-black tracking-tight text-gray-900">{{ $paper['label'] }}</h2>
                <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $detailedProposalDocument?->completed_at ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ $detailedProposalDocument?->completed_at ? 'Complete' : 'Not started' }}</span>
            </div>
            <p class="mt-1 text-xs text-gray-500">Complete the official BatStateU-FO-RES-02 Rev. 04 form through structured inputs.</p>
        </div>
    </x-slot>

    @php
        $projectDetailsComplete = app(\App\Support\ProposalDraftReadiness::class)->projectDetailsAreComplete($proposalDraft);
        $initialData = array_replace_recursive($sourceData, old());
        $sdgs = config('detailed_proposal.sdgs');
        $expectedOutputs = config('detailed_proposal.expected_outputs');
        $methodologyFields = config('detailed_proposal.methodology');
    @endphp

    <div
        class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8"
        data-paper-editor
        data-paper-dirty="false"
        data-paper-edit-url="{{ route('faculty.proposal-drafts.detailed-proposal.edit', $proposalDraft) }}"
        data-paper-exit-url="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}"
        x-data="proposalDraftDetailedProposal({
            initialData: @js($initialData),
            workspacePeople: @js($workspacePeople),
            projectLeader: @js($proposalDraft->project_leader),
            expectedOutputKeys: @js(array_keys($expectedOutputs)),
            methodologyKeys: @js(array_keys($methodologyFields)),
            previewUrl: @js(route('faculty.proposal-drafts.detailed-proposal.preview', $proposalDraft)),
            downloadUrl: @js(route('faculty.proposal-drafts.detailed-proposal.download', $proposalDraft)),
            csrfToken: @js(csrf_token()),
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
                <p class="mt-1 leading-6">Project title, dates, duration, and project leader are shared with every generated paper.</p>
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
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Leader</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->project_leader }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">MOOE from Attachment B</dt><dd class="mt-1 text-sm font-semibold text-gray-900">Php {{ number_format($budgetTotals['mooe_total'], 2) }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Capital Outlay from Attachment B</dt><dd class="mt-1 text-sm font-semibold text-gray-900">Php {{ number_format($budgetTotals['co_total'], 2) }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Official form</dt><dd class="mt-1 text-sm font-semibold text-gray-900">BatStateU-FO-RES-02 Rev. 04</dd></div>
            </dl>
        </section>

        <form data-paper-form x-ref="form" x-on:submit="if (!validateForm()) $event.preventDefault()" action="{{ route('faculty.proposal-drafts.detailed-proposal.update', $proposalDraft) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')
            <input type="hidden" name="document_version" value="{{ old('document_version', $detailedProposalDocument?->lock_version ?? 0) }}">
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
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div><h3 class="text-base font-black text-gray-900">IV. Project leader and staff</h3><p class="mt-1 text-xs text-gray-500">Workspace accounts can supply the institutional name and email; contact numbers stay editable.</p></div>
                    <div class="flex w-full gap-2 sm:w-auto">
                        <select x-model="selectedWorkspacePerson" aria-label="Workspace member to add" class="min-w-0 flex-1 rounded-xl border-gray-300 text-xs shadow-sm focus:border-red-600 focus:ring-red-600 sm:w-64">
                            <option value="">Choose workspace member</option>
                            <template x-for="person in availableWorkspacePeople()" :key="person.key"><option :value="person.key" x-text="`${person.name} — ${person.email}`"></option></template>
                        </select>
                        <button type="button" x-on:click="addWorkspacePerson" class="shrink-0 rounded-xl border border-red-200 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-50">Add</button>
                    </div>
                </div>
                <div class="mt-5 grid gap-4 rounded-xl border border-gray-200 bg-gray-50 p-4 sm:grid-cols-3">
                    <div><p class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Leader</p><p class="mt-2 text-sm font-black text-gray-900">{{ $proposalDraft->project_leader }}</p></div>
                    <div><label for="leader-email" class="text-[10px] font-black uppercase tracking-wider text-gray-500">Email Address</label><input id="leader-email" name="leader_email" type="email" required maxlength="255" x-model="leaderEmail" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                    <div><label for="leader-contact" class="text-[10px] font-black uppercase tracking-wider text-gray-500">Contact Number</label><input id="leader-contact" name="leader_contact" type="text" required maxlength="80" x-model="leaderContact" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                </div>
                <div class="mt-4 space-y-3">
                    <template x-for="(member, index) in staff" :key="member.id">
                        <div class="grid gap-3 rounded-xl border border-gray-200 p-4 lg:grid-cols-[1fr_1fr_1fr_auto] lg:items-end">
                            <div><label class="text-[10px] font-black uppercase tracking-wider text-gray-500" :for="`staff-name-${member.id}`">Project Staff</label><input :id="`staff-name-${member.id}`" :name="`staff[${index}][name]`" type="text" required maxlength="255" list="detailed-proposal-member-names" x-model="member.name" x-on:change="syncStaff(member)" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                            <div><label class="text-[10px] font-black uppercase tracking-wider text-gray-500" :for="`staff-email-${member.id}`">Email Address</label><input :id="`staff-email-${member.id}`" :name="`staff[${index}][email]`" type="email" required maxlength="255" x-model="member.email" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                            <div><label class="text-[10px] font-black uppercase tracking-wider text-gray-500" :for="`staff-contact-${member.id}`">Contact Number</label><input :id="`staff-contact-${member.id}`" :name="`staff[${index}][contact]`" type="text" required maxlength="80" x-model="member.contact" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                            <button type="button" x-on:click="removeStaff(index)" class="rounded-xl px-3 py-2.5 text-xs font-bold text-red-700 hover:bg-red-50">Remove</button>
                        </div>
                    </template>
                    <button type="button" x-on:click="addStaff" class="inline-flex rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50">Add external project staff</button>
                </div>
                <datalist id="detailed-proposal-member-names">@foreach ($workspacePeople as $person)<option value="{{ $person['name'] }}">{{ $person['email'] }}</option>@endforeach</datalist>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <h3 class="text-base font-black text-gray-900">V–VI. Proponent and cooperating agencies</h3>
                <p class="mt-1 text-xs text-gray-500">Proponent Agency is fixed to {{ config('detailed_proposal.proponent_agency') }}.</p>
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
                'related_literature' => ['XI. Review of Related Literature', 'Include at least ten relevant literature or studies.'],
            ] as $field => [$label, $help])
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <label for="{{ str_replace('_', '-', $field) }}" class="block text-base font-black text-gray-900">{{ $label }}</label>
                    <p class="mt-1 text-xs text-gray-500">{{ $help }}</p>
                    <textarea id="{{ str_replace('_', '-', $field) }}" name="{{ $field }}" rows="{{ in_array($field, ['related_literature', 'rationale'], true) ? 14 : 9 }}" required maxlength="{{ config('detailed_proposal.maximum_narrative_length') }}" x-model="{{ \Illuminate\Support\Str::camel($field) }}" class="mt-4 block w-full rounded-xl border-gray-300 text-sm leading-6 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
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
                <h3 class="text-base font-black text-gray-900">XII. Methodology</h3>
                <div class="mt-5 space-y-5">
                    @foreach ($methodologyFields as $key => $label)
                        <div><label for="methodology-{{ $key }}" class="block text-xs font-black uppercase tracking-wider text-gray-600">{{ $label }}</label><textarea id="methodology-{{ $key }}" name="methodology[{{ $key }}]" rows="7" required maxlength="{{ config('detailed_proposal.maximum_narrative_length') }}" x-model="methodology.{{ $key }}" class="mt-2 block w-full rounded-xl border-gray-300 text-sm leading-6 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea></div>
                    @endforeach
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-end justify-between gap-3"><div><h3 class="text-base font-black text-gray-900">XIII. Duties and Responsibilities of Each Member</h3><p class="mt-1 text-xs text-gray-500">Include the project leader and every participating member.</p></div><button type="button" x-on:click="addResponsibility" class="shrink-0 rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50">Add member</button></div>
                <div class="mt-5 space-y-4">
                    <template x-for="(responsibility, index) in responsibilities" :key="responsibility.id">
                        <div class="rounded-xl border border-gray-200 p-4">
                            <div class="flex items-end gap-3"><div class="flex-1"><label class="text-[10px] font-black uppercase tracking-wider text-gray-500" :for="`responsibility-name-${responsibility.id}`">Member name</label><input :id="`responsibility-name-${responsibility.id}`" :name="`responsibilities[${index}][name]`" type="text" required maxlength="255" list="detailed-proposal-member-names" x-model="responsibility.name" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div><button type="button" x-on:click="removeResponsibility(index)" x-bind:disabled="responsibilities.length === 1" class="rounded-xl px-3 py-2.5 text-xs font-bold text-red-700 hover:bg-red-50 disabled:opacity-40">Remove</button></div>
                            <label class="mt-3 block text-[10px] font-black uppercase tracking-wider text-gray-500" :for="`responsibility-duties-${responsibility.id}`">Duties and responsibilities</label><textarea :id="`responsibility-duties-${responsibility.id}`" :name="`responsibilities[${index}][duties]`" rows="5" required maxlength="{{ config('detailed_proposal.maximum_narrative_length') }}" x-model="responsibility.duties" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm leading-6 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                        </div>
                    </template>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <label for="references" class="block text-base font-black text-gray-900">XVI. References</label>
                <p class="mt-1 text-xs text-gray-500">Enter one reference per line or separate entries with blank lines.</p>
                <textarea id="references" name="references" rows="12" required maxlength="{{ config('detailed_proposal.maximum_narrative_length') }}" x-model="references" class="mt-4 block w-full rounded-xl border-gray-300 text-sm leading-6 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
            </section>

            <section class="rounded-2xl border border-blue-200 bg-blue-50 p-5 text-sm text-blue-900">
                <h3 class="font-black">Sections generated automatically</h3>
                <p class="mt-1 leading-6">XIV links Attachment A, XV pulls MOOE and Capital Outlay totals from Attachment B, XVII links Attachment C, and the prepared-by name and agency details repeat on the signature page. Research Office fields remain blank.</p>
            </section>

            @include('faculty.proposal-drafts.partials.change-note')

            <div class="flex flex-col-reverse gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:flex-wrap sm:justify-end">
                <a data-paper-discard href="{{ route('faculty.proposal-drafts.detailed-proposal.edit', $proposalDraft) }}" class="inline-flex w-full items-center justify-center rounded-xl border border-gray-300 px-5 py-3 text-sm font-bold text-gray-700 hover:bg-gray-50 sm:w-auto">Discard changes</a>
                <a data-paper-cancel-exit href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" class="inline-flex w-full items-center justify-center rounded-xl border border-gray-300 px-5 py-3 text-sm font-bold text-gray-700 hover:bg-gray-50 sm:w-auto">Cancel and exit</a>
                <button type="button" x-on:click="generatePreview" @disabled(! $projectDetailsComplete) class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-gray-900 px-5 py-3 text-sm font-bold text-gray-900 hover:bg-gray-50 disabled:opacity-50 sm:w-auto"><span x-show="previewLoading" x-cloak class="h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-gray-900"></span><span x-text="previewLoading ? 'Generating…' : 'Preview content'"></span></button>
                <button type="button" x-on:click="downloadDocument" @disabled(! $projectDetailsComplete) class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-red-200 px-5 py-3 text-sm font-bold text-red-700 hover:bg-red-50 disabled:opacity-50 sm:w-auto"><span x-show="downloadLoading" x-cloak class="h-4 w-4 animate-spin rounded-full border-2 border-red-200 border-t-red-700"></span><span x-text="downloadLoading ? 'Preparing…' : 'Download exact Word file'"></span></button>
                <button data-paper-save-exit type="submit" name="exit_after_save" value="1" @disabled(! $projectDetailsComplete) class="inline-flex w-full items-center justify-center rounded-xl border border-red-200 px-5 py-3 text-sm font-bold text-red-700 hover:bg-red-50 disabled:opacity-50 sm:w-auto">Save and exit</button>
                <button data-paper-save type="submit" @disabled(! $projectDetailsComplete) class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-50 sm:w-auto">Save changes</button>
            </div>
        </form>

        <div x-show="previewError || downloadError" x-cloak role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" x-text="previewError || downloadError"></div>
        <section x-show="previewHtml" x-cloak class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
            <div class="mb-4 flex items-start justify-between gap-3"><div><h3 class="text-base font-black text-gray-900">Detailed proposal content preview</h3><p class="mt-1 text-xs text-gray-500">Use the Word download for the exact official page layout.</p></div><button type="button" x-on:click="printPreview" x-bind:disabled="!previewReady" class="rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 disabled:opacity-50">Print preview</button></div>
            <iframe x-ref="previewFrame" x-bind:srcdoc="previewHtml" x-on:load="previewReady = true" title="Detailed Research Proposal content preview" class="h-[80vh] w-full rounded-xl border border-gray-200 bg-white"></iframe>
        </section>
    </div>
</x-app-layout>
