<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" class="text-xs font-bold text-red-600 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">&larr; Proposal package</a>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h2 class="text-2xl font-black tracking-tight text-gray-900">{{ $paper['label'] }}</h2>
                <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $lineItemBudgetDocument?->completed_at ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ $lineItemBudgetDocument?->completed_at ? 'Complete' : 'Not started' }}</span>
            </div>
            <p class="mt-1 text-xs text-gray-500">Complete the official line-item budget through structured inputs.</p>
        </div>
    </x-slot>

    @php
        $projectDetailsComplete = app(\App\Support\ProposalDraftReadiness::class)->projectDetailsAreComplete($proposalDraft);
        $initialData = array_replace($sourceData, old());
        $sampleDefinition = config('proposal_samples.'.$paper['sample_slug']);
        $sampleAvailable = is_array($sampleDefinition)
            && isset($sampleDefinition['path'])
            && \Illuminate\Support\Facades\Storage::disk('local')->exists($sampleDefinition['path']);
        $sections = config('line_item_budget.sections');
    @endphp

    <div
        class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8"
        data-paper-editor
        data-paper-dirty="false"
        data-paper-edit-url="{{ route('faculty.proposal-drafts.line-item-budget.edit', $proposalDraft) }}"
        data-paper-exit-url="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}"
        x-data="proposalDraftLineItemBudget({
            initialData: @js($initialData),
            sections: @js($sections),
            defaultCampus: @js(config('line_item_budget.default_campus')),
            workspacePeople: @js($workspacePeople),
            previewUrl: @js(route('faculty.proposal-drafts.line-item-budget.preview', $proposalDraft)),
            downloadUrl: @js(route('faculty.proposal-drafts.line-item-budget.download', $proposalDraft)),
            csrfToken: @js(csrf_token()),
        })"
    >
        @if (session('success'))
            <div role="status" class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <p class="font-bold">The Line-Item Budget could not be saved.</p>
                <ul class="mt-1 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div x-show="validationMessage" x-cloak role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800" x-text="validationMessage"></div>

        <x-paper-editor-submit-status />
        <x-paper-editor-shortcuts />

        @unless ($projectDetailsComplete)
            <div role="alert" class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                <p class="font-black">Complete Project Details first</p>
                <p class="mt-1 leading-6">Project title, planned dates, and project leader are required before Attachment B can be saved or generated.</p>
                <a href="{{ route('faculty.proposal-drafts.details.edit', $proposalDraft) }}" class="mt-3 inline-flex rounded-xl bg-amber-900 px-4 py-2.5 text-xs font-bold text-white focus:outline-none focus:ring-2 focus:ring-amber-900 focus:ring-offset-2">Complete Project Details</a>
            </div>
        @endunless

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-base font-black text-gray-900">Shared project information</h3>
                    <p class="mt-1 text-xs text-gray-500">Program Title stays empty. Project title, duration, dates, and project leader come from Project Details.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($sampleAvailable)<a href="{{ route('proposal-samples.show', $paper['sample_slug']) }}" target="_blank" rel="noopener" class="inline-flex rounded-xl border border-gray-300 px-3 py-2 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:ring-offset-2">View sample</a>@endif
                    <a href="{{ route('faculty.proposal-drafts.details.edit', $proposalDraft) }}" class="inline-flex rounded-xl border border-red-200 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">Edit details</a>
                </div>
            </div>
            <dl class="mt-5 grid gap-4 border-t border-gray-100 pt-5 sm:grid-cols-2 lg:grid-cols-4">
                <div class="sm:col-span-2 lg:col-span-4"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Title</dt><dd class="mt-1 text-sm font-normal text-gray-900">{{ $proposalDraft->project_title }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Leader</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->project_leader ?: 'Not provided' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Duration on paper</dt><dd class="mt-1 text-sm italic text-gray-900">{{ $proposalDraft->planned_start?->format('F j, Y') ?? 'Not provided' }} - {{ $proposalDraft->planned_end?->format('F j, Y') ?? 'Not provided' }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Call budget ceiling</dt><dd class="mt-1 text-sm font-semibold text-gray-900">Php {{ number_format((float) $proposalDraft->researchCall->maximum_budget, 2) }}</dd></div>
            </dl>
        </section>

        <form data-paper-form x-ref="form" x-on:submit="if (!validateForm()) $event.preventDefault()" action="{{ route('faculty.proposal-drafts.line-item-budget.update', $proposalDraft) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')
            <input type="hidden" name="document_version" value="{{ $lineItemBudgetDocument?->lock_version ?? 0 }}">

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div><h3 class="text-base font-black text-gray-900">Project leader and staff</h3><p class="mt-1 text-xs text-gray-500">Choose a proposal workspace member to reuse their account name and college, or type an external member manually.</p></div>
                    <button type="button" x-on:click="addStaff" class="inline-flex w-full items-center justify-center rounded-xl border border-red-200 px-4 py-2.5 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">Add project staff</button>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div><label for="leader-campus" class="block text-xs font-black uppercase tracking-wider text-gray-600">Project leader campus <span class="font-normal normal-case text-gray-400">Optional</span></label><input id="leader-campus" name="leader_campus" type="text" maxlength="120" x-model="leaderCampus" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                    <div><label for="leader-college" class="block text-xs font-black uppercase tracking-wider text-gray-600">Project leader college <span class="font-normal normal-case text-gray-400">Optional</span></label><input id="leader-college" name="leader_college" type="text" list="line-item-budget-colleges" maxlength="120" x-model="leaderCollege" placeholder="Select or type a college" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                </div>

                <div class="mt-5 space-y-3">
                    <template x-for="(member, index) in staff" :key="member.id">
                        <div class="grid gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4 lg:grid-cols-[1fr_1fr_1fr_auto] lg:items-end">
                            <div><label class="block text-[10px] font-black uppercase tracking-wider text-gray-500" :for="`staff-name-${member.id}`">Name</label><input :id="`staff-name-${member.id}`" :name="`staff[${index}][name]`" type="text" list="proposal-workspace-member-names" maxlength="120" x-model="member.name" x-on:change="syncStaff(member)" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                            <div><label class="block text-[10px] font-black uppercase tracking-wider text-gray-500" :for="`staff-campus-${member.id}`">Campus</label><input :id="`staff-campus-${member.id}`" :name="`staff[${index}][campus]`" type="text" maxlength="120" x-model="member.campus" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                            <div><label class="block text-[10px] font-black uppercase tracking-wider text-gray-500" :for="`staff-college-${member.id}`">College</label><input :id="`staff-college-${member.id}`" :name="`staff[${index}][college]`" type="text" list="line-item-budget-colleges" maxlength="120" x-model="member.college" placeholder="Select or type" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                            <button type="button" x-on:click="removeStaff(index)" class="rounded-xl px-3 py-2.5 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600">Remove</button>
                        </div>
                    </template>
                </div>
                <datalist id="line-item-budget-colleges">
                    @foreach (config('line_item_budget.college_options') as $college)
                        <option value="{{ $college }}"></option>
                    @endforeach
                </datalist>
                <datalist id="proposal-workspace-member-names">
                    @foreach ($workspacePeople as $workspacePerson)
                        <option value="{{ $workspacePerson['name'] }}">{{ $workspacePerson['email'] }}</option>
                    @endforeach
                </datalist>
            </section>

            @foreach (['mooe' => 'I. Maintenance and Other Operating Expenses (MOOE)', 'co' => 'II. Capital Outlays (CO)'] as $sectionKey => $sectionHeading)
                @php($customProperty = $sectionKey === 'mooe' ? 'customMooeItems' : 'customCoItems')
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div><h3 class="text-base font-black text-gray-900">{{ $sectionHeading }}</h3><p class="mt-1 text-xs text-gray-500">Amounts may be left empty. Enter numbers without commas.</p></div>
                        <button type="button" x-on:click="addCustomItem('{{ $sectionKey }}')" class="inline-flex w-full items-center justify-center rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:ring-offset-2 sm:w-auto">Add category or sub-category</button>
                    </div>

                    <div class="mt-5 overflow-hidden rounded-xl border border-gray-200">
                        <div class="grid grid-cols-[minmax(0,1fr)_10rem] bg-gray-100 px-4 py-2 text-[10px] font-black uppercase tracking-wider text-gray-600"><span>Particulars</span><span class="text-right">Amount (Php)</span></div>
                        @foreach ($sections[$sectionKey]['items'] as $item)
                            <div class="grid grid-cols-[minmax(0,1fr)_10rem] items-center gap-3 border-t border-gray-100 px-4 py-2.5">
                                <label for="amount-{{ $item['key'] }}" class="text-sm text-gray-800 {{ $item['level'] ? 'pl-6' : 'font-semibold' }}">{{ $item['label'] }}</label>
                                <input id="amount-{{ $item['key'] }}" name="amounts[{{ $item['key'] }}]" type="number" min="0" max="{{ config('line_item_budget.maximum_amount') }}" step="0.01" x-model="amounts['{{ $item['key'] }}']" class="block w-full rounded-lg border-gray-300 text-right text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                            </div>
                        @endforeach

                        <template x-for="(item, index) in {{ $customProperty }}" :key="item.id">
                            <div class="grid gap-3 border-t border-gray-100 bg-red-50/40 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_10rem_auto] sm:items-center">
                                <input :name="`custom_{{ $sectionKey }}_items[${index}][particular]`" type="text" maxlength="255" x-model="item.particular" aria-label="Custom {{ strtoupper($sectionKey) }} particular" placeholder="Custom category or sub-category" class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                <input :name="`custom_{{ $sectionKey }}_items[${index}][amount]`" type="number" min="0" max="{{ config('line_item_budget.maximum_amount') }}" step="0.01" x-model="item.amount" aria-label="Custom {{ strtoupper($sectionKey) }} amount" class="block w-full rounded-lg border-gray-300 text-right text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                <button type="button" x-on:click="removeCustomItem('{{ $sectionKey }}', index)" class="rounded-lg px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-600">Remove</button>
                            </div>
                        </template>
                    </div>

                    <div class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div><p class="text-xs font-black uppercase tracking-wider text-gray-500">{{ $sectionKey === 'mooe' ? 'Total MOOE' : 'Total Capital Outlays' }}</p><p class="mt-1 text-xl font-black text-gray-900">Php <span x-text="formatMoney(sectionTotal('{{ $sectionKey }}'))"></span></p></div>
                            <label class="inline-flex items-center gap-2 text-xs font-bold text-gray-700"><input type="checkbox" x-model="{{ $sectionKey === 'mooe' ? 'overrideMooe' : 'overrideCo' }}" class="rounded border-gray-300 text-red-600 focus:ring-red-600">Edit this total manually</label>
                        </div>
                        <input name="{{ $sectionKey }}_total_override" type="number" min="0" max="{{ config('line_item_budget.maximum_amount') }}" step="0.01" x-model="{{ $sectionKey === 'mooe' ? 'mooeOverride' : 'coOverride' }}" x-bind:disabled="!{{ $sectionKey === 'mooe' ? 'overrideMooe' : 'overrideCo' }}" x-show="{{ $sectionKey === 'mooe' ? 'overrideMooe' : 'overrideCo' }}" x-cloak aria-label="Manual {{ strtoupper($sectionKey) }} total" class="mt-3 block w-full rounded-xl border-gray-300 text-right text-sm shadow-sm focus:border-red-600 focus:ring-red-600 sm:max-w-xs sm:ml-auto">
                    </div>
                </section>
            @endforeach

            <section class="rounded-2xl border border-gray-900 bg-gray-900 p-5 text-white shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div><p class="text-xs font-black uppercase tracking-wider text-gray-300">Total Project Cost</p><p class="mt-1 text-2xl font-black">Php <span x-text="formatMoney(projectTotal())"></span></p></div>
                    <label class="inline-flex items-center gap-2 text-xs font-bold text-gray-200"><input type="checkbox" x-model="overrideProject" class="rounded border-gray-500 text-red-600 focus:ring-red-600">Edit project total manually</label>
                </div>
                <input name="project_total_override" type="number" min="0" max="{{ config('line_item_budget.maximum_amount') }}" step="0.01" x-model="projectOverride" x-bind:disabled="!overrideProject" x-show="overrideProject" x-cloak aria-label="Manual project total" class="mt-4 block w-full rounded-xl border-gray-600 bg-gray-800 text-right text-white shadow-sm focus:border-red-500 focus:ring-red-500 sm:max-w-xs sm:ml-auto">
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div><h3 class="text-base font-black text-gray-900">Research Office section</h3><p class="mt-1 text-xs text-gray-500">These fields are optional and may remain blank.</p></div>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div><label for="level-of-call" class="block text-xs font-black uppercase tracking-wider text-gray-600">Level of call</label><select id="level-of-call" name="level_of_call" x-model="levelOfCall" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"><option value="">Leave blank</option><option value="central_agency">Central Agency (VPRDES, President)</option><option value="constituent_campus">Constituent Campus (VCRDES, Chancellor)</option></select></div>
                    <div><label for="approval-body" class="block text-xs font-black uppercase tracking-wider text-gray-600">Approving body</label><select id="approval-body" name="approval_body" x-model="approvalBody" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"><option value="">Leave blank</option><option value="research_council">Research Council</option><option value="lrec">Local Research Evaluation Committee</option></select></div>
                    <div><label for="resolution-number" class="block text-xs font-black uppercase tracking-wider text-gray-600">Resolution number</label><input id="resolution-number" name="resolution_number" type="text" maxlength="50" x-model="resolutionNumber" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                    <div><label for="resolution-year" class="block text-xs font-black uppercase tracking-wider text-gray-600">Resolution year</label><input id="resolution-year" name="resolution_year" type="text" maxlength="10" x-model="resolutionYear" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"></div>
                </div>
                <div class="mt-5 rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
                    <p class="text-[10px] font-black uppercase tracking-wider text-gray-500">Certified correct by</p>
                    <p class="mt-2 font-black text-gray-900">{{ config('work_plan.verifier.name') }}</p>
                    <p class="text-xs text-gray-600">{{ config('work_plan.verifier.role') }}</p>
                    <p class="mt-2 text-xs text-gray-500">The signature lines and both Date Signed fields remain blank for handwriting.</p>
                </div>
            </section>

            @include('faculty.proposal-drafts.partials.change-note')

            <div class="flex flex-col-reverse gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:flex-wrap sm:justify-end">
                <a data-paper-discard href="{{ route('faculty.proposal-drafts.line-item-budget.edit', $proposalDraft) }}" class="inline-flex w-full items-center justify-center rounded-xl border border-gray-300 px-5 py-3 text-sm font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 sm:w-auto">Discard changes</a>
                <a data-paper-cancel-exit href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" class="inline-flex w-full items-center justify-center rounded-xl border border-gray-300 px-5 py-3 text-sm font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 sm:w-auto">Cancel and exit</a>
                <button type="button" x-on:click="generatePreview" @disabled(! $projectDetailsComplete) class="inline-flex w-full items-center justify-center rounded-xl border border-gray-900 px-5 py-3 text-sm font-bold text-gray-900 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"><span x-show="!previewLoading">Preview paper</span><span x-show="previewLoading" x-cloak>Generating&hellip;</span></button>
                <button type="button" x-on:click="downloadDocument" @disabled(! $projectDetailsComplete) class="inline-flex w-full items-center justify-center rounded-xl border border-red-200 px-5 py-3 text-sm font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"><span x-show="!downloadLoading">Download Word file</span><span x-show="downloadLoading" x-cloak>Preparing&hellip;</span></button>
                <button data-paper-save-exit type="submit" name="exit_after_save" value="1" @disabled(! $projectDetailsComplete) class="inline-flex w-full items-center justify-center rounded-xl border border-red-200 px-5 py-3 text-sm font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto">Save and exit</button>
                <button data-paper-save type="submit" @disabled(! $projectDetailsComplete) class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto">Save changes</button>
            </div>
        </form>

        <div x-show="previewError || downloadError" x-cloak role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><span x-text="previewError || downloadError"></span></div>

        <section x-show="previewHtml" x-cloak class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h3 class="text-base font-black text-gray-900">Line-Item Budget preview</h3><p class="mt-1 text-xs text-gray-500">The Word download uses the supplied official form.</p></div><button type="button" x-on:click="printPreview" x-bind:disabled="!previewReady" class="inline-flex w-full items-center justify-center rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:ring-offset-2 disabled:opacity-50 sm:w-auto">Print preview</button></div>
            <iframe x-ref="previewFrame" x-bind:srcdoc="previewHtml" x-on:load="previewReady = true" title="Attachment B Line-Item Budget preview" class="h-[80vh] w-full rounded-xl border border-gray-200 bg-white"></iframe>
        </section>
    </div>
</x-app-layout>
