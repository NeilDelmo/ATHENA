<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Account Profile">
            <x-slot name="actions">
                <x-back-link href="{{ route('dashboard') }}">Back to workspace</x-back-link>
            </x-slot>
        </x-page-header>
    </x-slot>

    @php
        $workspaceDefinitions = \App\Models\User::workspaceDefinitions();
        $cvDefaults = old('cv_defaults', $user->cv_defaults ?? []);
        $cvDefaults = is_array($cvDefaults) ? $cvDefaults : [];
        $inputClass = 'mt-1.5 block min-h-11 w-full rounded-xl border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:focus:border-red-400 dark:focus:ring-red-400';
        $researcherProfile = $user->researcherProfile;
        $orcid = $researcherProfile?->orcid;
        $orcidUrl = is_string($orcid) && preg_match('~^(?:https://orcid\.org/)?(\d{4}-\d{4}-\d{4}-\d{3}[\dX])$~i', $orcid, $orcidMatches) ? 'https://orcid.org/'.$orcidMatches[1] : null;
        $scholarUrl = $researcherProfile?->scholar_url;
        $scholarUrl = is_string($scholarUrl) && preg_match('~^https://scholar\.google\.com/citations\?[^#]*\buser=[A-Za-z0-9_-]+(?:&[^#]*)?$~', $scholarUrl) ? $scholarUrl : null;
        $openalexId = $researcherProfile?->openalex_id;
        $openalexUrl = is_string($openalexId) && preg_match('~^(?:https://openalex\.org/)?(A\d+)$~', $openalexId, $openalexMatches) ? 'https://openalex.org/'.$openalexMatches[1] : null;
    @endphp

    <div class="grid items-start gap-6 xl:grid-cols-[300px_minmax(0,1fr)]" data-account-profile>
        <aside class="min-w-0 space-y-6">
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="h-16 bg-red-700 dark:bg-red-900" aria-hidden="true"></div>
                <div class="relative px-6 pb-6">
                    <x-user-avatar :user="$user" class="-mt-8 h-16 w-16 rounded-2xl border-4 border-white bg-red-700 text-xl dark:border-slate-900" />
                    <p class="mt-5 text-xs font-semibold text-gray-500 dark:text-slate-400">Institutional identity</p>
                    <h2 class="mt-1 break-words text-xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $user->name }}</h2>
                    <p class="mt-2 break-all text-sm text-gray-600 dark:text-slate-300">{{ $user->email }}</p>
                    <p class="mt-4 text-xs leading-5 text-gray-500 dark:text-slate-400">Your photo, name, and university email synchronize from Google when you sign in.</p>

                    <dl class="mt-5 space-y-4 border-t border-gray-100 pt-5 dark:border-slate-800">
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-slate-400">Authentication</dt>
                            <dd class="mt-1 text-sm font-semibold text-gray-800 dark:text-slate-200">BatStateU Google Workspace</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-slate-400">Google connection</dt>
                            <dd class="mt-1 text-sm font-semibold {{ $user->google_id ? 'text-green-700 dark:text-green-400' : 'text-amber-700 dark:text-amber-300' }}">{{ $user->google_id ? 'Connected' : 'Pending first Google sign-in' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-slate-400">ATHENA member since</dt>
                            <dd class="mt-1 text-sm font-semibold text-gray-800 dark:text-slate-200">{{ $user->created_at->format('F Y') }}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            @if ($researcherProfile)
                <section class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">Publication matching</h2>
                    <p class="mt-2 text-xs leading-5 text-gray-500 dark:text-slate-400">Your existing researcher connection helps identify publications in project dissemination. Edit reusable curriculum vitae details in the profile form.</p>
                    <p class="mt-4 break-words text-sm font-semibold text-gray-800 dark:text-slate-200">{{ $researcherProfile->display_name ?: $user->name }}</p>
                    <div class="mt-2 flex flex-col items-start gap-2 text-sm">
                        @if ($orcidUrl)<a href="{{ $orcidUrl }}" target="_blank" rel="noopener noreferrer" class="break-all rounded text-red-700 underline decoration-red-200 underline-offset-4 focus:outline-none focus:ring-2 focus:ring-red-500 dark:text-red-300 dark:decoration-red-900">ORCID {{ $orcidMatches[1] }}<span class="sr-only"> (opens in a new tab)</span></a>@endif
                        @if ($scholarUrl)<a href="{{ $scholarUrl }}" target="_blank" rel="noopener noreferrer" class="rounded text-red-700 underline decoration-red-200 underline-offset-4 focus:outline-none focus:ring-2 focus:ring-red-500 dark:text-red-300 dark:decoration-red-900">Google Scholar<span class="sr-only"> (opens in a new tab)</span></a>@endif
                        @if ($openalexUrl)<a href="{{ $openalexUrl }}" target="_blank" rel="noopener noreferrer" class="rounded text-red-700 underline decoration-red-200 underline-offset-4 focus:outline-none focus:ring-2 focus:ring-red-500 dark:text-red-300 dark:decoration-red-900">OpenAlex author<span class="sr-only"> (opens in a new tab)</span></a>@endif
                    </div>
                </section>
            @endif

            <section class="px-1">
                <h2 class="text-sm font-semibold text-gray-800 dark:text-slate-200">Institutional account</h2>
                <p class="mt-2 text-xs leading-5 text-gray-500 dark:text-slate-400">Your name, email, password, and account recovery are managed by Batangas State University. Google profile changes synchronize the next time you sign in.</p>
                <form method="POST" action="{{ route('logout') }}" class="mt-4" data-proposal-confirm data-confirm-title="Log out of ATHENA?" data-confirm-text="You will need to sign in again to continue working in ATHENA." data-confirm-button="Log out" data-cancel-button="Stay signed in" data-confirm-icon="warning">
                    @csrf
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-700 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/40 dark:focus:ring-offset-slate-950">Sign out securely</button>
                </form>
            </section>
        </aside>

        <div class="min-w-0 space-y-6">
            <form method="POST" action="{{ route('profile.details.update') }}" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" data-profile-details>
                @csrf
                @method('PATCH')

                <div class="border-b border-gray-100 p-5 sm:p-6 dark:border-slate-800">
                    <h2 class="text-lg font-bold tracking-tight text-gray-900 dark:text-white">Proposal defaults</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-500 dark:text-slate-400">Save the information you often repeat. New proposal forms and curriculum vitae can start with these details, and you can edit them for each proposal.</p>
                </div>

                <div class="space-y-6 p-5 sm:p-6">
                    @if ($errors->any())
                        <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                            <p class="font-semibold">Please correct the following:</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                        </div>
                    @endif

                    <div class="grid gap-5 md:grid-cols-2">
                        <div @if ($user->hasRole('research_coordinator')) data-college-locked @endif>
                            @if ($user->hasRole('research_coordinator'))
                                <input type="hidden" name="college" value="{{ $user->college }}">
                                <x-input-label for="college-locked" value="College" />
                                <select id="college-locked" disabled class="mt-1.5 block min-h-11 w-full scroll-mt-36 cursor-not-allowed rounded-xl border-amber-200 bg-amber-50 text-sm text-amber-900 shadow-sm disabled:opacity-100 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200" aria-describedby="college-help">
                                    <option selected>{{ $user->college ?: 'No college set' }}</option>
                                </select>
                                <p id="college-help" class="mt-2 text-xs leading-5 text-amber-700 dark:text-amber-300">College is locked while you are a Research Coordinator. Ask the Research Head to remove your Research Coordinator assignment before changing it.</p>
                            @else
                                <x-input-label for="college" value="College" />
                                <select id="college" name="college" required x-init="if (window.location.hash === '#college') $nextTick(() => $el.focus())" @hashchange.window="if (window.location.hash === '#college') $el.focus()" class="{{ $inputClass }} scroll-mt-36" @if ($errors->has('college')) aria-invalid="true" aria-describedby="college-error" @endif>
                                    <option value="" disabled @selected(! old('college', $user->college))>Set your college</option>
                                    @foreach (\App\Models\User::COLLEGES as $college)
                                        <option value="{{ $college }}" @selected(old('college', $user->college) === $college)>{{ $college }}</option>
                                    @endforeach
                                </select>
                            @endif
                            <x-input-error id="college-error" :messages="$errors->get('college')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="department">Department <span class="font-normal text-gray-500 dark:text-slate-400">(optional)</span></x-input-label>
                            <input id="department" name="department" type="text" maxlength="255" value="{{ old('department', $user->department) }}" class="{{ $inputClass }}" @if ($errors->has('department')) aria-invalid="true" aria-describedby="department-error" @endif>
                            <x-input-error id="department-error" :messages="$errors->get('department')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="professional_title">Professional title <span class="font-normal text-gray-500 dark:text-slate-400">(optional)</span></x-input-label>
                            <input id="professional_title" name="professional_title" type="text" list="profile-professional-titles" maxlength="50" value="{{ old('professional_title', $user->professional_title) }}" class="{{ $inputClass }}" aria-describedby="professional-title-help" @if ($errors->has('professional_title')) aria-invalid="true" @endif>
                            <datalist id="profile-professional-titles">@foreach (config('detailed_proposal.professional_titles') as $title)<option value="{{ $title }}"></option>@endforeach</datalist>
                            <p id="professional-title-help" class="mt-2 text-xs leading-5 text-gray-500 dark:text-slate-400">Choose a suggested title or type your own, such as Dr. or Asst Prof.</p>
                            <x-input-error id="professional-title-error" :messages="$errors->get('professional_title')" class="mt-2" />
                        </div>
                        <div data-profile-contact-number>
                            <x-input-label for="contact_number">Contact number <span class="font-normal text-gray-500 dark:text-slate-400">(optional)</span></x-input-label>
                            <input id="contact_number" name="contact_number" type="tel" inputmode="numeric" pattern="[0-9]{11}" maxlength="11" value="{{ old('contact_number', $user->contact_number) }}" placeholder="09171234567" autocomplete="tel" class="{{ $inputClass }}" aria-describedby="contact-number-help" @if ($errors->has('contact_number')) aria-invalid="true" @endif>
                            <p id="contact-number-help" class="mt-2 text-xs leading-5 text-gray-500 dark:text-slate-400">Use 11 digits. Included in proposal documents when saved; this is not a public profile.</p>
                            <x-input-error id="contact-number-error" :messages="$errors->get('contact_number')" class="mt-2" />
                        </div>
                    </div>

                    <section class="space-y-5 border-t border-gray-100 pt-6 dark:border-slate-800" aria-labelledby="cv-defaults-heading">
                        <div>
                            <h2 id="cv-defaults-heading" class="text-base font-bold text-gray-900 dark:text-white">Curriculum vitae defaults</h2>
                            <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-slate-400">Agency, education, and areas of specialization used in proposal documents.</p>
                        </div>
                        <div>
                            <x-input-label for="cv_agency">Agency / institution <span class="font-normal text-gray-500 dark:text-slate-400">(optional)</span></x-input-label>
                            <input id="cv_agency" name="cv_defaults[agency]" type="text" maxlength="255" value="{{ $cvDefaults['agency'] ?? '' }}" class="{{ $inputClass }}" @if ($errors->has('cv_defaults.agency')) aria-invalid="true" aria-describedby="cv-agency-error" @endif>
                            <x-input-error id="cv-agency-error" :messages="$errors->get('cv_defaults.agency')" class="mt-2" />
                        </div>

                        @foreach (['academic_background', 'specializations'] as $sectionKey)
                            @php
                                $section = config('curriculum_vitae.sections.'.$sectionKey);
                                $emptyRow = array_fill_keys(array_column($section['fields'], 'key'), '');
                                $sectionRows = $cvDefaults[$sectionKey] ?? [];
                                $sectionRows = is_array($sectionRows) ? array_values($sectionRows) : [];
                                $sectionRows = array_map(static fn (mixed $row): array => array_replace($emptyRow, is_array($row) ? $row : []), $sectionRows);
                                $sectionHasErrors = $errors->has('cv_defaults.'.$sectionKey) || $errors->has('cv_defaults.'.$sectionKey.'.*');
                            @endphp
                            <details @if (count($sectionRows) || $sectionHasErrors) open @endif class="rounded-xl border border-gray-200 dark:border-slate-700" data-profile-cv-section="{{ $sectionKey }}" x-data="{
                                rows: @js($sectionRows).map((row, index) => ({ ...row, _key: index + 1, _errorIndex: index })),
                                nextId: {{ count($sectionRows) }},
                                errors: @js($errors->getMessages()),
                            }">
                                <summary class="disclosure-summary cursor-pointer select-none rounded-xl px-4 py-4 text-sm font-semibold text-gray-900 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-red-500 dark:text-slate-100">{{ $section['label'] }} <span class="ml-1 font-normal text-gray-500 dark:text-slate-400" x-text="`(${rows.length})`"></span></summary>
                                <div class="space-y-4 border-t border-gray-100 p-4 dark:border-slate-800">
                                    <input type="hidden" name="cv_defaults[{{ $sectionKey }}]" value="">
                                    <x-input-error :messages="$errors->get('cv_defaults.'.$sectionKey)" />
                                    <p x-show="rows.length === 0" class="text-sm leading-6 text-gray-500 dark:text-slate-400">No entries yet. Add the information you want to reuse in new curriculum vitae.</p>
                                    <div x-ref="entries" class="space-y-4">
                                        <template x-for="(row, rowIndex) in rows" :key="row._key">
                                            <fieldset class="min-w-0 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-slate-700 dark:bg-slate-950/50">
                                                <legend class="px-1 text-xs font-semibold text-gray-700 dark:text-slate-300" x-text="`Entry ${rowIndex + 1}`"></legend>
                                                <div class="grid gap-4 md:grid-cols-2">
                                                    @foreach ($section['fields'] as $field)
                                                        @php($errorExpression = "errors['cv_defaults.{$sectionKey}.' + row._errorIndex + '.{$field['key']}']?.[0] || ''")
                                                        <div class="{{ ($field['wide'] ?? false) ? 'md:col-span-2' : '' }}">
                                                            <label :for="`profile-{{ $sectionKey }}-${row._key}-{{ $field['key'] }}`" class="block text-sm font-medium text-gray-700 dark:text-slate-300">{{ $field['label'] }}</label>
                                                            @if ($field['type'] === 'select' || $field['type'] === 'yes_no')
                                                                <select :id="`profile-{{ $sectionKey }}-${row._key}-{{ $field['key'] }}`" :name="`cv_defaults[{{ $sectionKey }}][${rowIndex}][{{ $field['key'] }}]`" x-model="row.{{ $field['key'] }}" @if ($field['key'] === 'status') @change="if (row.status === 'Ongoing') row.year_end = ''" @endif class="{{ $inputClass }}" :aria-invalid="Boolean({{ $errorExpression }})" :aria-describedby="`profile-{{ $sectionKey }}-${row._key}-{{ $field['key'] }}-error`">
                                                                    <option value="">Leave blank</option>
                                                                    @if ($field['type'] === 'yes_no')<option value="yes">Yes</option><option value="no">No</option>@else @foreach ($field['options'] as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach @endif
                                                                </select>
                                                            @elseif ($field['type'] === 'text' && ($field['wide'] ?? false))
                                                                <textarea :id="`profile-{{ $sectionKey }}-${row._key}-{{ $field['key'] }}`" :name="`cv_defaults[{{ $sectionKey }}][${rowIndex}][{{ $field['key'] }}]`" x-model="row.{{ $field['key'] }}" rows="2" maxlength="500" class="{{ $inputClass }}" :aria-invalid="Boolean({{ $errorExpression }})" :aria-describedby="`profile-{{ $sectionKey }}-${row._key}-{{ $field['key'] }}-error`"></textarea>
                                                            @else
                                                                <input :id="`profile-{{ $sectionKey }}-${row._key}-{{ $field['key'] }}`" :name="`cv_defaults[{{ $sectionKey }}][${rowIndex}][{{ $field['key'] }}]`" type="{{ $field['type'] === 'year' ? 'number' : 'text' }}" @if ($field['type'] === 'year') min="1900" max="2100" step="1" @else maxlength="500" @endif x-model="row.{{ $field['key'] }}" @if ($field['key'] === 'year_end') :disabled="row.status === 'Ongoing'" @endif class="{{ $inputClass }} disabled:cursor-not-allowed disabled:bg-gray-100 dark:disabled:bg-slate-800" :aria-invalid="Boolean({{ $errorExpression }})" :aria-describedby="`profile-{{ $sectionKey }}-${row._key}-{{ $field['key'] }}-error`">
                                                                @if ($field['key'] === 'year_end')<p x-show="row.status === 'Ongoing'" class="mt-2 text-xs text-gray-500 dark:text-slate-400">Ongoing studies appear as Present in the curriculum vitae.</p>@endif
                                                            @endif
                                                            <p :id="`profile-{{ $sectionKey }}-${row._key}-{{ $field['key'] }}-error`" x-show="{{ $errorExpression }}" x-text="{{ $errorExpression }}" class="mt-2 text-sm text-red-600 dark:text-red-400"></p>
                                                        </div>
                                                    @endforeach
                                                </div>
                                                <div class="mt-4 flex justify-end">
                                                    <button type="button" @click="const addButton = $refs.add; rows.splice(rowIndex, 1); $nextTick(() => addButton.focus())" :aria-label="`Remove {{ strtolower($section['label']) }} entry ${rowIndex + 1}`" class="min-h-11 rounded-lg px-3 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500 dark:text-red-300 dark:hover:bg-red-950/50">Remove entry</button>
                                                </div>
                                            </fieldset>
                                        </template>
                                    </div>
                                    <button x-ref="add" type="button" @click="rows.push({ ...@js($emptyRow), _key: ++nextId, _errorIndex: null }); $nextTick(() => $refs.entries.lastElementChild.querySelector('input, select, textarea')?.focus())" :disabled="rows.length >= {{ config('curriculum_vitae.max_rows_per_section') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-red-200 px-4 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/40">{{ $sectionKey === 'academic_background' ? 'Add academic background' : 'Add specialization' }}</button>
                                </div>
                            </details>
                        @endforeach
                    </section>
                </div>

                <div class="flex flex-col gap-4 border-t border-gray-100 bg-gray-50 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-slate-800 dark:bg-slate-950/40">
                    <div>
                        <p class="max-w-lg text-xs leading-5 text-gray-500 dark:text-slate-400">Changes apply to new proposal papers. Saved papers remain unchanged.</p>
                        <noscript><p class="mt-2 text-sm text-amber-700 dark:text-amber-300">Enable JavaScript to save your profile and curriculum vitae details.</p></noscript>
                        @if (in_array(session('status'), ['profile-details-updated', 'college-updated', 'contact-number-updated'], true))
                            <p role="status" class="mt-2 text-sm font-semibold text-green-700 dark:text-green-400">Profile details saved.</p>
                        @endif
                    </div>
                    <button type="submit" disabled x-init="$el.disabled = false" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-slate-950">Save profile details</button>
                </div>
            </form>

            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-slate-800 dark:bg-slate-900" aria-labelledby="profile-access-heading">
                <div class="border-b border-gray-100 px-5 py-5 sm:px-6 dark:border-slate-800">
                    <h2 id="profile-access-heading" class="text-base font-bold text-gray-900 dark:text-white">Access and permissions</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">Your assigned roles and available ATHENA workspaces.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @forelse ($user->getRoleNames() as $role)
                            <span class="rounded-lg bg-red-50 px-2.5 py-1 text-xs font-semibold capitalize text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ $workspaceDefinitions[$role]['label'] ?? str_replace('_', ' ', $role) }}</span>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-slate-400">No role has been assigned. Contact the Research Office for access.</p>
                        @endforelse
                    </div>
                </div>
                <dl class="divide-y divide-gray-100 dark:divide-slate-800">
                    @foreach ($user->availableWorkspaces() as $workspace)
                        <div class="px-5 py-4 sm:px-6">
                            <dt class="text-sm font-semibold text-gray-800 dark:text-slate-200">{{ $workspace['label'] }}</dt>
                            <dd class="mt-1 text-sm leading-6 text-gray-500 dark:text-slate-400">{{ $workspace['description'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        </div>
    </div>
</x-app-layout>
