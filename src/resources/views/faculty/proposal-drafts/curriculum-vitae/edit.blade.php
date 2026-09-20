<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-2xl font-black tracking-tight text-gray-900">{{ $paper['label'] }}</h2>
                    <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $curriculumVitaeDocument?->completed_at ? 'bg-green-100 text-green-800' : ($curriculumVitaeDocument ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600') }}">{{ $curriculumVitaeDocument?->completed_at ? 'Complete' : ($curriculumVitaeDocument ? 'In progress' : 'Not started') }}</span>
                </div>
                <p class="mt-1 text-xs text-gray-500">Create one official CV form for every member of the research team.</p>
            </div>
            <x-back-link fixed data-paper-cancel-exit href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}#required-pdf-attachments">Exit editor</x-back-link>
        </div>
    </x-slot>

    @php
        $initialPeople = old('people', $sourceData['people'] ?? []);
        $sections = config('curriculum_vitae.sections');
        $sampleDefinition = config('proposal_samples.'.$paper['sample_slug']);
        $sampleAvailable = is_array($sampleDefinition)
            && isset($sampleDefinition['path'])
            && \Illuminate\Support\Facades\Storage::disk('local')->exists($sampleDefinition['path']);
    @endphp

    <div
        class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8"
        data-paper-editor
        data-paper-draft-save="true"
        data-curriculum-vitae-autosave="true"
        data-paper-dirty="{{ $errors->any() ? 'true' : 'false' }}"
        data-paper-edit-url="{{ route('faculty.proposal-drafts.curriculum-vitae.edit', $proposalDraft) }}"
        data-paper-exit-url="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}#required-pdf-attachments"
        x-data="proposalDraftCurriculumVitae({
            initialPeople: @js($initialPeople),
            workspacePeople: @js($workspacePeople),
            sections: @js($sections),
            updateUrl: @js(route('faculty.proposal-drafts.curriculum-vitae.update', $proposalDraft)),
            previewUrl: @js(route('faculty.proposal-drafts.curriculum-vitae.preview', $proposalDraft)),
            downloadUrl: @js(route('faculty.proposal-drafts.curriculum-vitae.download', $proposalDraft)),
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
                <p class="font-bold">The Curriculum Vitae package could not be saved.</p>
                <ul class="mt-1 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-proposal-alert>
        @endif

        <div x-show="validationMessage" x-cloak role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800" x-text="validationMessage"></div>

        <x-proposal-revision-context :proposal-draft="$proposalDraft" :document-type="$paper['document_type']" />
        <x-proposal-autosave-status />
        <x-proposal-collaboration-monitor
            :loaded-version="(int) old('document_version', $curriculumVitaeDocument?->lock_version ?? 0)"
            :state-url="route('faculty.proposal-drafts.edit-state', [$proposalDraft, $paper['document_type'], 0])"
            :reload-url="route('faculty.proposal-drafts.curriculum-vitae.edit', $proposalDraft)"
            :history-url="route('faculty.proposal-drafts.history.index', [$proposalDraft, 'paper' => $paper['slug']])"
            :label="$paper['label']"
        />

        <section data-revision-shared-summary class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-base font-black text-gray-900">Research team CV package</h3>
                    <p class="mt-1 max-w-3xl text-xs leading-5 text-gray-500">Add an account from this proposal workspace to fill in their name and institutional email automatically, or create a blank CV for an unlisted person.</p>
                </div>
                @if ($sampleAvailable)<a href="{{ route('proposal-samples.show', $paper['sample_slug']) }}" target="_blank" rel="noopener" class="inline-flex w-full shrink-0 items-center justify-center rounded-xl border border-gray-300 px-3 py-2 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:ring-offset-2 sm:w-auto">View sample</a>@endif
            </div>

            <div class="mt-5 grid gap-4 border-t border-gray-100 pt-5 lg:grid-cols-[minmax(0,1fr)_17rem]">
                <div class="rounded-2xl border border-red-100 bg-red-50/50 p-4 sm:p-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-black uppercase tracking-wider text-red-700">Proposal workspace</p>
                            <h4 class="mt-1 text-sm font-black text-gray-900">Add a workspace member</h4>
                            <p class="mt-1 text-xs leading-5 text-gray-500">Search the project leader and collaborators. Members with an existing CV are hidden.</p>
                        </div>
                        <span class="w-fit rounded-full bg-white px-2.5 py-1 text-[10px] font-black text-gray-600 ring-1 ring-gray-200" x-text="`${availableWorkspacePeople().length} available`"></span>
                    </div>

                    <div class="relative mt-4" x-on:click.outside="workspacePickerOpen = false">
                        <label for="workspace-cv-person-search" class="sr-only">Search proposal workspace members</label>
                        <div class="relative">
                            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 3.474 9.765l3.63 3.63a.75.75 0 0 0 1.06-1.06l-3.629-3.63A5.5 5.5 0 0 0 9 3.5ZM5 9a4 4 0 1 1 8 0 4 4 0 0 1-8 0Z" clip-rule="evenodd" /></svg>
                            <input id="workspace-cv-person-search" type="search" autocomplete="off" placeholder="Search workspace members" x-model="workspacePersonQuery" x-on:focus="workspacePickerOpen = true" x-on:input="workspacePickerOpen = true" x-on:keydown.escape="workspacePickerOpen = false" role="combobox" aria-autocomplete="list" x-bind:aria-expanded="workspacePickerOpen" aria-controls="workspace-cv-person-options" class="block w-full rounded-xl border-gray-300 bg-white py-2.5 pl-9 pr-3 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                        </div>
                        <div id="workspace-cv-person-options" x-show="workspacePickerOpen" x-transition.origin.top x-cloak role="listbox" class="absolute z-30 mt-2 max-h-72 w-full overflow-y-auto rounded-2xl border border-gray-200 bg-white p-2 shadow-xl shadow-gray-900/10">
                            <template x-for="person in filteredWorkspacePeople()" :key="person.key">
                                <button type="button" role="option" x-on:click="addWorkspacePerson(person.key)" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition hover:bg-red-50 focus:bg-red-50 focus:outline-none">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-red-100 text-xs font-black text-red-700 ring-1 ring-red-200">
                                        <img x-show="person.avatar" x-bind:src="person.avatar" x-bind:alt="personDisplayName(person.name)" x-on:error="person.avatar = ''" class="h-full w-full object-cover">
                                        <span x-show="!person.avatar" x-text="personInitials(person.name)"></span>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-bold uppercase text-gray-900" x-text="personDisplayName(person.name)"></span>
                                        <span class="block truncate text-xs text-gray-500" x-text="person.email"></span>
                                    </span>
                                    <svg class="h-4 w-4 shrink-0 text-red-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                </button>
                            </template>
                            <div x-show="filteredWorkspacePeople().length === 0" class="px-3 py-5 text-center">
                                <p class="text-sm font-bold text-gray-700">No available workspace member matches your search.</p>
                                <p class="mt-1 text-xs leading-5 text-gray-500">Members already included in this CV package do not appear here.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col justify-between rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-4 sm:p-5">
                    <div>
                        <p class="text-xs font-black uppercase tracking-wider text-gray-600">External team member</p>
                        <h4 class="mt-1 text-sm font-black text-gray-900">Create a blank CV</h4>
                        <p class="mt-1 text-xs leading-5 text-gray-500">Use this for a person who is not yet in the proposal workspace.</p>
                    </div>
                    <button type="button" x-on:click="addPerson" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-xs font-bold text-gray-700 transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:ring-offset-2">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        Add blank CV
                    </button>
                </div>
            </div>

            <div class="mt-5 border-t border-gray-100 pt-5">
                <div class="flex items-center justify-between gap-3"><p class="text-[10px] font-black uppercase tracking-wider text-gray-600">CVs in this package</p><span class="rounded-full bg-gray-100 px-2.5 py-1 text-[10px] font-black text-gray-600" x-text="`${people.length} ${people.length === 1 ? 'member' : 'members'}`"></span></div>
                <div class="mt-3 flex flex-wrap gap-2">
                <template x-for="(person, index) in people" :key="person.id">
                    <button type="button" x-on:click="focusPerson(index)" class="rounded-full bg-gray-100 px-3 py-1.5 text-xs font-bold text-gray-700 transition hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-red-600" x-text="`${index + 1}. ${personLabel(person)}`"></button>
                </template>
                </div>
            </div>
        </section>

        <form data-paper-form data-curriculum-vitae-autosave-form x-ref="form" action="{{ route('faculty.proposal-drafts.curriculum-vitae.update', $proposalDraft) }}" method="POST" class="space-y-6" novalidate>
            @csrf
            @method('PUT')
            <input type="hidden" name="document_version" value="{{ old('document_version', $curriculumVitaeDocument?->lock_version ?? 0) }}">
            <input type="hidden" name="save_as_draft" value="0" data-paper-save-mode>

            <template x-for="(person, personIndex) in people" :key="person.id">
                <article class="space-y-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6" :data-person-index="personIndex">
                    <div class="flex flex-col gap-3 border-b border-gray-100 pb-5 sm:flex-row sm:items-start sm:justify-between">
                        <div><p class="text-[10px] font-black uppercase tracking-wider text-red-600">CV <span x-text="personIndex + 1"></span> of <span x-text="people.length"></span></p><h3 class="mt-1 text-lg font-black text-gray-900" x-text="personLabel(person)"></h3></div>
                        <button type="button" x-on:click="removePerson(personIndex)" x-bind:disabled="people.length === 1" class="rounded-xl px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:cursor-not-allowed disabled:opacity-40">Remove member</button>
                    </div>

                    <details :data-revision-section="`section-cv-${personIndex + 1}-personal`" :id="`cv-${person.id}-personal`" open class="rounded-xl border border-gray-200">
                        <summary class="cursor-pointer select-none px-4 py-3 text-sm font-black text-gray-900 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-red-600">Personal Information</summary>
                        <div class="grid gap-4 border-t border-gray-100 p-4 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ([['last_name', 'Last Name', true], ['first_name', 'First Name', true], ['middle_name', 'Middle Name', false], ['agency', 'Agency', false], ['birthday', 'Birthday', false], ['street', 'Street', false], ['barangay', 'Barangay', false], ['municipality', 'Municipality', false], ['province', 'Province', false], ['landline', 'Landline Number', false], ['cellphone', 'Cellphone Number', false], ['email', 'Email Address', false]] as [$key, $label, $required])
                                @php($isContactNumber = in_array($key, ['landline', 'cellphone'], true))
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-wider text-gray-600" :for="`cv-${person.id}-{{ $key }}`">{{ $label }} @if ($required)<span class="text-red-600">Required</span>@endif</label>
                                    @if ($key === 'birthday')
                                        <x-date-picker id-expression="`cv-${person.id}-{{ $key }}`" name-expression="`people[${personIndex}][{{ $key }}]`" model="person.{{ $key }}" :max="now()->toDateString()" class="mt-1.5" />
                                    @else
                                        <input :id="`cv-${person.id}-{{ $key }}`" :name="`people[${personIndex}][{{ $key }}]`" type="{{ $key === 'email' ? 'email' : ($isContactNumber ? 'tel' : 'text') }}" maxlength="{{ $isContactNumber ? 11 : ($key === 'agency' || $key === 'email' ? 255 : 120) }}" @if ($isContactNumber) inputmode="numeric" pattern="[0-9]{11}" autocomplete="tel" @endif x-model="person.{{ $key }}" @if ($isContactNumber) x-on:input="person.{{ $key }} = normalizeContactNumber($event.target.value); $event.target.value = person.{{ $key }}" @endif @if ($required) required @endif class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                    @endif
                                </div>
                            @endforeach
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-wider text-gray-600" :for="`cv-${person.id}-gender`">Gender</label>
                                <select :id="`cv-${person.id}-gender`" :name="`people[${personIndex}][gender]`" x-model="person.gender" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"><option value="">Leave blank</option><option value="male">Male</option><option value="female">Female</option></select>
                            </div>
                        </div>
                    </details>

                    @foreach ($sections as $sectionKey => $section)
                        <details :data-revision-section="`section-cv-${personIndex + 1}-{{ $sectionKey }}`" :id="`cv-${person.id}-{{ $sectionKey }}`" class="rounded-xl border border-gray-200">
                            <summary class="cursor-pointer select-none px-4 py-3 text-sm font-black text-gray-900 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-red-600">{{ $section['label'] }} <span class="font-semibold text-gray-400" x-text="`(${person.{{ $sectionKey }}.length})`"></span></summary>
                            <div class="space-y-4 border-t border-gray-100 p-4">
                                <div class="flex justify-end"><button type="button" x-on:click="addSectionRow(personIndex, '{{ $sectionKey }}')" class="inline-flex w-full items-center justify-center rounded-xl border border-red-200 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 sm:w-auto">Add {{ Str::singular(strtolower($section['label'])) }} entry</button></div>
                                <p x-show="person.{{ $sectionKey }}.length === 0" class="rounded-xl bg-gray-50 px-4 py-3 text-xs text-gray-500">No entries. Preview and Word output will retain {{ $section['default_rows'] }} blank rows for this section.</p>
                                <template x-for="(row, rowIndex) in person.{{ $sectionKey }}" :key="row.id">
                                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                            @foreach ($section['fields'] as $field)
                                                <div class="{{ ($field['wide'] ?? false) ? 'sm:col-span-2' : '' }}">
                                                    <label class="block text-[10px] font-black uppercase tracking-wider text-gray-600" :for="`cv-${person.id}-{{ $sectionKey }}-${row.id}-{{ $field['key'] }}`">{{ $field['label'] }}</label>
                                                    @if ($field['type'] === 'select')
                                                        <select :id="`cv-${person.id}-{{ $sectionKey }}-${row.id}-{{ $field['key'] }}`" :name="`people[${personIndex}][{{ $sectionKey }}][${rowIndex}][{{ $field['key'] }}]`" @if ($sectionKey === 'academic_background' && $field['key'] === 'status') x-bind:value="row.status" x-on:change="updateAcademicStatus(row, $event.target.value)" @else x-model="row.{{ $field['key'] }}" @endif class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                                            <option value="">Leave blank</option>
                                                            @foreach ($field['options'] as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                                                        </select>
                                                    @elseif ($sectionKey === 'academic_background' && $field['key'] === 'year_end')
                                                        <template x-if="row.status === 'Ongoing'">
                                                            <input :id="`cv-${person.id}-{{ $sectionKey }}-${row.id}-{{ $field['key'] }}`" type="text" value="Present" disabled class="mt-1.5 block w-full cursor-not-allowed rounded-xl border-gray-200 bg-gray-100 text-sm font-bold text-gray-700 shadow-sm">
                                                        </template>
                                                        <template x-if="row.status !== 'Ongoing'">
                                                            <input :id="`cv-${person.id}-{{ $sectionKey }}-${row.id}-{{ $field['key'] }}`" :name="`people[${personIndex}][{{ $sectionKey }}][${rowIndex}][{{ $field['key'] }}]`" type="number" min="1900" max="2100" step="1" x-model="row.{{ $field['key'] }}" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                                        </template>
                                                        <p x-show="row.status === 'Ongoing'" class="mt-1 text-[11px] font-semibold text-gray-500">Ongoing studies automatically end in Present.</p>
                                                    @elseif ($field['type'] === 'yes_no')
                                                        <select :id="`cv-${person.id}-{{ $sectionKey }}-${row.id}-{{ $field['key'] }}`" :name="`people[${personIndex}][{{ $sectionKey }}][${rowIndex}][{{ $field['key'] }}]`" x-model="row.{{ $field['key'] }}" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600"><option value="">Leave blank</option><option value="yes">Yes</option><option value="no">No</option></select>
                                                    @elseif ($field['type'] === 'suggestions')
                                                        <input :id="`cv-${person.id}-{{ $sectionKey }}-${row.id}-{{ $field['key'] }}`" :name="`people[${personIndex}][{{ $sectionKey }}][${rowIndex}][{{ $field['key'] }}]`" :list="`cv-${person.id}-{{ $sectionKey }}-${row.id}-{{ $field['key'] }}-options`" type="text" maxlength="500" x-model="row.{{ $field['key'] }}" data-cv-suggestions class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                                        <datalist :id="`cv-${person.id}-{{ $sectionKey }}-${row.id}-{{ $field['key'] }}-options`">
                                                            @foreach ($field['options'] as $option)<option value="{{ $option }}"></option>@endforeach
                                                        </datalist>
                                                        <p class="mt-1 text-[11px] font-semibold text-gray-500">Choose a suggested value or type your own.</p>
                                                    @elseif ($field['type'] === 'date')
                                                        <x-date-picker id-expression="`cv-${person.id}-{{ $sectionKey }}-${row.id}-{{ $field['key'] }}`" name-expression="`people[${personIndex}][{{ $sectionKey }}][${rowIndex}][{{ $field['key'] }}]`" model="row.{{ $field['key'] }}" class="mt-1.5" />
                                                    @else
                                                        <input :id="`cv-${person.id}-{{ $sectionKey }}-${row.id}-{{ $field['key'] }}`" :name="`people[${personIndex}][{{ $sectionKey }}][${rowIndex}][{{ $field['key'] }}]`" type="{{ $field['type'] === 'money' || $field['type'] === 'year' ? 'number' : 'text' }}" @if ($field['type'] === 'money') min="0" max="999999999.99" step="0.01" @elseif ($field['type'] === 'year') min="1900" max="2100" step="1" @else maxlength="500" @endif x-model="row.{{ $field['key'] }}" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                        <div class="mt-3 flex justify-end"><button type="button" x-on:click="removeSectionRow(personIndex, '{{ $sectionKey }}', rowIndex)" class="rounded-lg px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-600">Remove entry</button></div>
                                    </div>
                                </template>
                            </div>
                        </details>
                    @endforeach
                </article>
            </template>

            <div class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:flex-wrap sm:justify-end">
                <button type="button" x-on:click="generatePreview" x-bind:disabled="previewLoading" class="inline-flex w-full items-center justify-center rounded-xl border border-gray-900 px-5 py-3 text-sm font-bold text-gray-900 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"><span x-show="!previewLoading">Preview package</span><span x-show="previewLoading" x-cloak>Generating&hellip;</span></button>
                <button type="button" x-on:click="downloadDocument" x-bind:disabled="!isComplete()" class="inline-flex w-full items-center justify-center rounded-xl border border-red-200 px-5 py-3 text-sm font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"><span x-show="!downloadLoading">Download Word file</span><span x-show="downloadLoading" x-cloak>Preparing&hellip;</span></button>
                <noscript><button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">Save Curriculum Vitae</button></noscript>
            </div>
        </form>

        <div x-show="previewError || downloadError" x-cloak role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><span x-text="previewError || downloadError"></span></div>

        <section x-show="previewHtml" x-cloak class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h3 class="text-base font-black text-gray-900">Curriculum Vitae package preview</h3><p class="mt-1 text-xs text-gray-500">Every member begins with a new official CV block.</p></div><button type="button" x-on:click="printPreview" x-bind:disabled="!previewReady" class="inline-flex w-full items-center justify-center rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:ring-offset-2 disabled:opacity-50 sm:w-auto">Print preview</button></div>
            <iframe x-ref="previewFrame" x-bind:srcdoc="previewHtml" x-on:load="previewReady = true" title="Attachment C Curriculum Vitae package preview" class="h-[80vh] w-full rounded-xl border border-gray-200 bg-white"></iframe>
        </section>
    </div>
</x-app-layout>
