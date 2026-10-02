<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Initial Screening Form" :subtitle="$screeningForm['project_title'].' · Version '.$version->version_number">
            <x-slot name="actions">
                <x-back-link fixed :href="route('topics.show', $topic).'#proposal-review'">Back to review</x-back-link>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-5">
        @if (session('success'))
            <p role="status" class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/30 dark:text-green-200">{{ session('success') }}</p>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200">
                <p class="font-bold">Check the form before saving.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        <section class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <div class="max-w-xl">
                <h3 class="text-base font-bold text-gray-950 dark:text-white">Complete, save, and print for signatures</h3>
                <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $canEdit ? 'Fill in the screening results below, then save. Downloads use your last saved entries. Print the form and obtain the required wet signatures.' : 'This version is closed for editing. Its saved form remains available for printing and wet signatures.' }}</p>
            </div>
            @if ($version->research_head_screening !== null)
                <div class="flex flex-wrap gap-2">
                    <a data-screening-pdf href="{{ route('research_head.topics.initial-screening-form.pdf', [$topic, $version]) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-bold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Open PDF for printing</a>
                    <a data-screening-download href="{{ route('research_head.topics.initial-screening-form.download', [$topic, $version]) }}" class="inline-flex min-h-11 items-center rounded-lg bg-red-700 px-4 py-2 text-sm font-bold text-white hover:bg-red-800">Download DOCX</a>
                </div>
            @endif
        </section>

        <form method="POST" action="{{ route('research_head.topics.initial-screening-form.update', [$topic, $version]) }}" class="space-y-5" data-research-head-screening-editor>
            @csrf
            @method('PUT')
            <fieldset @disabled(! $canEdit) class="space-y-5">
                <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 sm:p-6">
                    <h3 class="text-lg font-bold text-gray-950 dark:text-white">Project details</h3>
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div><dt class="text-sm text-gray-500 dark:text-gray-400">Research Project Title</dt><dd class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $screeningForm['project_title'] }}</dd></div>
                        <div><dt class="text-sm text-gray-500 dark:text-gray-400">Project Leader</dt><dd class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $screeningForm['project_leader'] }}</dd></div>
                    </dl>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200">Order of submission
                            <select name="order_of_submission" required class="mt-2 block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                                @foreach (['first_submission' => 'First Submission', 'revised_with_minor_changes' => 'Revised with Minor Changes', 'revised_with_major_changes' => 'Revised with Major Changes'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('order_of_submission', $screeningForm['order_of_submission']) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200">Level of call
                            <select name="level_of_call" class="mt-2 block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                                <option value="">Select level</option>
                                @foreach (['central_agency' => 'Central Administration', 'constituent_campus' => 'Constituent Campus'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('level_of_call', $screeningForm['level_of_call']) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        @foreach (['requested_budget' => ['Requested budget', 'number', '0', '0.01'], 'duration_months' => ['Duration (months)', 'number', '1', '1'], 'researcher_count' => ['Number of researchers involved', 'number', '1', '1'], 'department' => ['Department', 'text', null, null], 'college' => ['College', 'text', null, null], 'campus' => ['Campus', 'text', null, null]] as $key => [$label, $type, $min, $step])
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $label }}
                                <input name="{{ $key }}" type="{{ $type }}" value="{{ old($key, $screeningForm[$key]) }}" @if ($type === 'number') required min="{{ $min }}" step="{{ $step }}" @else maxlength="255" @endif class="mt-2 block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                            </label>
                        @endforeach
                    </div>
                </section>

                <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 sm:p-6">
                    <h3 class="text-lg font-bold text-gray-950 dark:text-white">Checklist of submitted documents</h3>
                    @foreach (\App\Support\ResearchHeadScreeningData::DOCUMENTS as $type => $label)
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-3 dark:border-gray-800">
                            <label class="flex items-center gap-3 text-sm font-semibold text-gray-700 dark:text-gray-200">
                                <input type="hidden" name="documents[{{ $type }}][attached]" value="0">
                                <input type="checkbox" name="documents[{{ $type }}][attached]" value="1" @checked(old('documents.'.$type.'.attached', $screeningForm['documents'][$type]['attached'])) class="rounded border-gray-300 text-red-700 focus:ring-red-700">
                                {{ $label }}
                            </label>
                            <label class="flex items-center gap-3 text-sm text-gray-600 dark:text-gray-300">No. of pages
                                <input type="number" min="1" max="10000" name="documents[{{ $type }}][pages]" value="{{ old('documents.'.$type.'.pages', $screeningForm['documents'][$type]['pages']) }}" class="w-24 rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                            </label>
                        </div>
                    @endforeach
                </section>

                <section x-data="{ scores: @js(old('scores', $screeningForm['scores'])) }" class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 sm:p-6">
                    <h3 class="text-lg font-bold text-gray-950 dark:text-white">Rubrics</h3>
                    @foreach (\App\Support\ResearchHeadScreeningData::CRITERIA as $key => $criterion)
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $loop->iteration }}. {{ $criterion['label'] }} ({{ max(array_keys($criterion['scores'])) }}%)
                            <select name="scores[{{ $key }}]" x-model.number="scores.{{ $key }}" required class="mt-2 block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                                <option value="">Select rating</option>
                                @foreach ($criterion['scores'] as $score => $label)<option value="{{ $score }}" @selected((string) old('scores.'.$key, $screeningForm['scores'][$key] ?? '') === (string) $score)>{{ $label }} · {{ $score }} points</option>@endforeach
                            </select>
                        </label>
                    @endforeach
                    <p role="status" class="rounded-lg bg-gray-50 p-4 text-sm font-bold text-gray-900 dark:bg-gray-950 dark:text-gray-100">Total score: <span x-text="Object.values(scores).reduce((sum, score) => sum + (Number(score) || 0), 0)">{{ array_sum($screeningForm['scores']) }}</span>/100 · Passing score: 80</p>
                </section>

                <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 sm:p-6">
                    <h3 class="text-lg font-bold text-gray-950 dark:text-white">Recommendation and narrative evaluation</h3>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200">Recommended action
                        <select name="recommended_action" required class="mt-2 block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                            <option value="">Select recommendation</option>
                            @foreach (\App\Support\InitialScreeningSubmissionOrder::recommendations() as $value)<option value="{{ $value }}" @selected(old('recommended_action', $screeningForm['recommended_action']) === $value)>{{ \App\Support\InitialScreeningSubmissionOrder::recommendationLabel($value) }}</option>@endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200">Narrative evaluation
                        <textarea name="narrative_evaluation" required rows="8" maxlength="5000" class="mt-2 block w-full rounded-lg border-gray-300 text-sm leading-6 dark:border-gray-700 dark:bg-gray-950 dark:text-white">{{ old('narrative_evaluation', $screeningForm['narrative_evaluation']) }}</textarea>
                    </label>
                </section>

                <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 sm:p-6">
                    <h3 class="text-lg font-bold text-gray-950 dark:text-white">Signatories</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Names will appear on the printed form. Signatures and dates are completed by hand.</p>
                    @foreach (['screening_head' => 'Head, Research / Head, Research and Extension', 'screening_center' => 'Center Head / Assistant Director for Research', 'screening_verifier' => 'Director, Research / Vice Chancellor for RDES'] as $key => $label)
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $label }}
                            <input name="{{ $key }}" maxlength="160" @required($key === 'screening_head') @readonly($key !== 'screening_center') value="{{ $key === 'screening_center' ? old($key, $screeningForm[$key]) : $screeningForm[$key] }}" class="mt-2 block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                        </label>
                    @endforeach
                </section>
                @if ($canEdit)
                    <div class="flex justify-end"><button type="submit" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-red-700 px-6 py-3 text-sm font-bold text-white hover:bg-red-800">Save Initial Screening Form</button></div>
                @endif
            </fieldset>
        </form>
    </div>
</x-app-layout>
