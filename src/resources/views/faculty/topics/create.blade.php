<x-app-layout>
    @php
        $packageInputs = [
            ['name' => 'detailed_proposal', 'label' => 'Detailed Research Proposal', 'help' => 'The complete proposal manuscript.', 'accept' => '.doc,.docx,.pdf', 'multiple' => false, 'sample' => 'detailed-proposal'],
            ['name' => 'line_item_budget', 'label' => 'Attachment B - Line-Item Budget', 'help' => 'The detailed project budget document.', 'accept' => '.doc,.docx,.pdf', 'multiple' => false, 'sample' => 'line-item-budget'],
            ['name' => 'expense_breakdown', 'label' => 'Estimated Expense Breakdown', 'help' => 'Upload the completed spreadsheet.', 'accept' => '.xls,.xlsx', 'multiple' => false, 'sample' => 'expense-breakdown'],
            ['name' => 'curricula_vitae', 'label' => 'Attachment C - Curriculum Vitae', 'help' => 'You may select multiple files for the project team.', 'accept' => '.doc,.docx,.pdf', 'multiple' => true, 'sample' => 'curriculum-vitae'],
            ['name' => 'gad_checklist', 'label' => 'GAD Generic Checklist', 'help' => 'Complete the gender-responsiveness checklist.', 'accept' => '.doc,.docx,.pdf', 'multiple' => false, 'sample' => 'gad-checklist'],
        ];
    @endphp

    <x-slot name="header">
        <div class="grid gap-3">
            <a href="{{ route('faculty.dashboard') }}" class="inline-flex w-fit items-center gap-1 text-xs font-bold text-gray-500 transition hover:text-red-600">&larr; Back to dashboard</a>
            <div>
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-red-600">Guided proposal workflow</p>
                <h2 class="text-2xl font-black tracking-tight text-gray-900">Submit a Research Proposal</h2>
                <p class="mt-1 text-xs text-gray-500">Enter the project information once. ATHENA will generate Attachment A and include it in your proposal package.</p>
            </div>
        </div>
    </x-slot>

    <div
        x-data="workPlanWizard({
            previewUrl: @js(route('faculty.work-plans.preview')),
            downloadUrl: @js(route('faculty.work-plans.download')),
            csrfToken: @js(csrf_token()),
            maxEntries: @js($maxWorkPlanObjectives),
            initialDuration: @js(old('total_duration_months')),
            initialEntries: @js(old('entries', [])),
        })"
        class="grid gap-6 xl:grid-cols-[300px_minmax(0,1fr)]"
    >
        <aside class="grid content-start gap-5">
            <section class="rounded-2xl border border-red-200 bg-red-50 p-5">
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-red-600">Proposal checklist</p>
                <h3 class="mt-2 text-base font-black text-gray-900">Four guided steps</h3>
                <ol class="mt-4 grid gap-4 text-xs leading-5 text-gray-700">
                    @foreach ([
                        ['Project details', 'Choose the research call and enter the dates used on Attachment A.'],
                        ['Work Plan', 'Add as many objectives as needed and shade their Gantt months.'],
                        ['Documents', 'Upload the remaining proposal requirements.'],
                        ['Review & submit', 'Preview, download, or print Attachment A before submitting.'],
                    ] as $index => [$label, $description])
                        <li class="flex gap-3">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-red-600 text-[10px] font-black text-white">{{ $index + 1 }}</span>
                            <span><strong class="block text-gray-900">{{ $label }}</strong>{{ $description }}</span>
                        </li>
                    @endforeach
                </ol>
            </section>

            <section class="rounded-2xl border border-green-200 bg-green-50 p-5 text-xs leading-5 text-green-900">
                <p class="font-black">Attachment A is automatic</p>
                <p class="mt-2">The generated Word file uses the official 13 × 8.5-inch landscape template, Times New Roman type, and shaded M1-M12 Gantt grid.</p>
            </section>

            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-4">
                    <h3 class="text-sm font-black text-gray-900">Other official templates</h3>
                    <p class="mt-1 text-xs text-gray-400">Use the latest files from the Research Office.</p>
                </div>
                <div class="divide-y divide-gray-100">
                    @forelse ($proposalTemplates as $template)
                        <div class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-black text-gray-800">{{ $template['name'] }}</p>
                                    @if ($template['revision_label'])<p class="mt-0.5 text-[10px] font-bold text-red-600">{{ $template['revision_label'] }}</p>@endif
                                    @if ($template['description'])<p class="mt-1 text-[11px] leading-4 text-gray-500">{{ $template['description'] }}</p>@endif
                                </div>
                                <a href="{{ route('proposal-templates.download', $template['key']) }}" class="shrink-0 rounded-lg bg-gray-900 px-3 py-2 text-[10px] font-bold text-white transition hover:bg-gray-800">Download</a>
                            </div>
                        </div>
                    @empty
                        <p class="p-5 text-xs font-semibold leading-5 text-gray-500">No additional templates are currently available.</p>
                    @endforelse
                </div>
            </section>
        </aside>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-6 py-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.18em] text-red-600">Step <span x-text="step"></span> of 4</p>
                        <h3 class="mt-1 text-base font-black text-gray-900" x-text="stepTitle()"></h3>
                    </div>
                    <p class="text-xs text-gray-400">Required fields are checked before you continue.</p>
                </div>

                <div class="mt-5 grid grid-cols-4 gap-2" aria-label="Proposal progress">
                    <template x-for="stepNumber in 4" :key="stepNumber">
                        <button
                            type="button"
                            @click="goToStep(stepNumber)"
                            :disabled="stepNumber >= step"
                            :aria-current="stepNumber === step ? 'step' : null"
                            class="group flex min-w-0 flex-col gap-2 text-left disabled:cursor-default"
                        >
                            <span :class="stepNumber <= step ? 'bg-red-600' : 'bg-gray-200'" class="h-1.5 w-full rounded-full transition-colors"></span>
                            <span :class="stepNumber === step ? 'text-red-600' : stepNumber < step ? 'text-gray-700' : 'text-gray-400'" class="truncate text-[10px] font-black uppercase tracking-wide" x-text="shortStepTitle(stepNumber)"></span>
                        </button>
                    </template>
                </div>
            </div>

            <form
                x-ref="form"
                action="{{ route('faculty.topics') }}"
                method="POST"
                enctype="multipart/form-data"
                @submit="if (step !== 4) { $event.preventDefault(); nextStep(); }"
                class="grid gap-6 p-6"
            >
                @csrf

                @if ($errors->submission->any())
                    <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-xs leading-5 text-red-700" role="alert">
                        <p class="font-black">Please review the following:</p>
                        <ul class="mt-2 list-disc space-y-1 pl-4">@foreach ($errors->submission->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                <div x-show="validationMessage" x-cloak role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-xs leading-5 text-red-700">
                    <p class="font-black">Please review this step.</p>
                    <p class="mt-1" x-text="validationMessage"></p>
                </div>

                <section data-work-plan-step="1" x-show="step === 1" class="grid gap-6">
                    <div class="grid gap-5 md:grid-cols-2">
                        <div class="grid gap-2">
                            <label for="research_call_id" class="text-xs font-black uppercase tracking-wider text-gray-500">Research Call <span class="text-red-600">Required</span></label>
                            <select id="research_call_id" name="research_call_id" required class="block w-full rounded-xl border-gray-200 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600">
                                <option value="">Select an open call</option>
                                @foreach ($activeCalls as $call)<option value="{{ $call->id }}" @selected(old('research_call_id') == $call->id)>{{ $call->title }} ({{ $call->academic_year }})</option>@endforeach
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <label for="title" class="text-xs font-black uppercase tracking-wider text-gray-500">Title <span class="text-red-600">Required</span></label>
                            <input id="title" name="title" type="text" value="{{ old('title') }}" maxlength="255" required class="block w-full rounded-xl border-gray-200 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600" placeholder="Enter the Attachment A title">
                            <p class="text-[11px] text-gray-400">This fills the first “Title” row on the official form.</p>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <label for="project_title" class="text-xs font-black uppercase tracking-wider text-gray-500">Project Title <span class="text-red-600">Required</span></label>
                        <input id="project_title" name="project_title" type="text" value="{{ old('project_title') }}" maxlength="255" required class="block w-full rounded-xl border-gray-200 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600" placeholder="Enter the complete research project title">
                        <p class="text-[11px] text-gray-400">This is also used as the proposal title in ATHENA.</p>
                    </div>

                    <div class="grid gap-5 md:grid-cols-3">
                        <div class="grid gap-2">
                            <label for="total_duration_months" class="text-xs font-black uppercase tracking-wider text-gray-500">Total Duration <span class="text-red-600">Required</span></label>
                            <div class="relative">
                                <input id="total_duration_months" name="total_duration_months" type="number" min="1" max="12" x-model.number="durationMonths" @input="$nextTick(() => limitMonthsToDuration())" required class="block w-full rounded-xl border-gray-200 pr-20 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600" placeholder="12">
                                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs font-bold text-gray-400">months</span>
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <label for="planned_start" class="text-xs font-black uppercase tracking-wider text-gray-500">Planned Start <span class="text-red-600">Required</span></label>
                            <input id="planned_start" name="planned_start" type="date" value="{{ old('planned_start') }}" required class="block w-full rounded-xl border-gray-200 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600">
                        </div>
                        <div class="grid gap-2">
                            <label for="planned_end" class="text-xs font-black uppercase tracking-wider text-gray-500">Planned End <span class="text-red-600">Required</span></label>
                            <input id="planned_end" name="planned_end" type="date" value="{{ old('planned_end') }}" required class="block w-full rounded-xl border-gray-200 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600">
                        </div>
                    </div>

                    <p class="rounded-xl bg-gray-50 p-4 text-xs leading-5 text-gray-500">The supplied Attachment A contains one Year 1 schedule, so this version supports M1 through M12.</p>
                </section>

                <section data-work-plan-step="2" x-show="step === 2" x-cloak class="grid gap-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h4 class="text-sm font-black text-gray-900">Objectives and Gantt schedule</h4>
                            <p class="mt-1 text-xs leading-5 text-gray-500">Each objective becomes one flexible table row. Selected months appear as a shaded bar.</p>
                        </div>
                        <span class="rounded-full bg-gray-100 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-gray-500"><span x-text="entries.length"></span> objective<span x-show="entries.length !== 1">s</span></span>
                    </div>

                    <div class="grid gap-4">
                        <template x-for="(entry, index) in entries" :key="entry.id">
                            <article class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-red-600">Objective <span x-text="index + 1"></span></p>
                                        <h5 class="mt-1 text-sm font-black text-gray-900">Output, activities, and timing</h5>
                                    </div>
                                    <button x-show="entries.length > 1" type="button" @click="removeEntry(index)" class="rounded-lg px-3 py-2 text-[10px] font-black uppercase tracking-wide text-gray-400 transition hover:bg-red-50 hover:text-red-600">Remove</button>
                                </div>

                                <div class="mt-4 grid gap-4 lg:grid-cols-3">
                                    <div class="grid gap-2">
                                        <label :for="`objective-${entry.id}`" class="text-xs font-black text-gray-700">Objective <span class="text-red-600">Required</span></label>
                                        <textarea :id="`objective-${entry.id}`" :name="`entries[${index}][objective]`" x-model="entry.objective" rows="4" maxlength="500" required class="block w-full resize-y rounded-xl border-gray-200 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600" placeholder="State one objective"></textarea>
                                        <p class="text-right text-[10px] text-gray-400"><span x-text="entry.objective.length"></span>/500</p>
                                    </div>
                                    <div class="grid gap-2">
                                        <label :for="`expected-output-${entry.id}`" class="text-xs font-black text-gray-700">Expected Output <span class="text-red-600">Required</span></label>
                                        <textarea :id="`expected-output-${entry.id}`" :name="`entries[${index}][expected_output]`" x-model="entry.expectedOutput" rows="4" maxlength="500" required class="block w-full resize-y rounded-xl border-gray-200 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600" placeholder="Describe the expected result"></textarea>
                                        <p class="text-right text-[10px] text-gray-400"><span x-text="entry.expectedOutput.length"></span>/500</p>
                                    </div>
                                    <div class="grid gap-2">
                                        <label :for="`activity-${entry.id}`" class="text-xs font-black text-gray-700">Activities or Work Plan <span class="text-red-600">Required</span></label>
                                        <textarea :id="`activity-${entry.id}`" :name="`entries[${index}][activity]`" x-model="entry.activity" rows="4" maxlength="1500" required class="block w-full resize-y rounded-xl border-gray-200 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600" placeholder="List activities on separate lines"></textarea>
                                        <p class="text-right text-[10px] text-gray-400"><span x-text="entry.activity.length"></span>/1500</p>
                                    </div>
                                </div>

                                <fieldset class="mt-4 rounded-xl border border-gray-200 bg-white p-4">
                                    <legend class="px-1 text-xs font-black text-gray-700">Gantt months <span class="text-red-600">Choose at least one</span></legend>
                                    <div class="grid grid-cols-4 gap-2 sm:grid-cols-6 lg:grid-cols-12">
                                        <template x-for="month in months" :key="month">
                                            <label :for="`entry-${entry.id}-month-${month}`" :class="!isMonthWithinDuration(month) ? 'cursor-not-allowed border-gray-100 bg-gray-50 text-gray-300' : entry.months.includes(month) ? 'border-gray-500 bg-gray-200 text-gray-900' : 'border-gray-200 text-gray-600 hover:border-red-300 hover:bg-red-50'" class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border px-2 py-2 text-xs font-bold transition">
                                                <input :id="`entry-${entry.id}-month-${month}`" :name="`entries[${index}][months][]`" :value="month" :disabled="!isMonthWithinDuration(month)" x-model.number="entry.months" @change="clearMonthError(index)" type="checkbox" class="rounded border-gray-300 text-red-600 focus:ring-red-500 disabled:cursor-not-allowed disabled:opacity-40">
                                                <span>M<span x-text="month"></span></span>
                                            </label>
                                        </template>
                                    </div>
                                    <p x-show="monthErrorIndexes.includes(index)" x-cloak class="mt-3 text-xs font-semibold text-red-600">Choose at least one month for this objective.</p>
                                </fieldset>
                            </article>
                        </template>
                    </div>

                    <button x-show="entries.length < maxEntries" type="button" @click="addEntry()" class="inline-flex w-fit items-center gap-2 rounded-xl border border-dashed border-red-300 px-4 py-3 text-xs font-black text-red-600 transition hover:border-red-500 hover:bg-red-50">
                        <span class="text-base leading-none">+</span> Add another objective
                    </button>

                    <div class="grid gap-4 border-t border-gray-100 pt-5 lg:grid-cols-2">
                        <article class="rounded-2xl border border-gray-200 bg-white p-5">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-red-600">Prepared by</p>
                            <h4 class="mt-1 text-sm font-black text-gray-900">Project Leader</h4>
                            <div class="mt-4 grid gap-4">
                                <div class="grid gap-2">
                                    <label for="prepared_by" class="text-xs font-black text-gray-700">Name <span class="text-red-600">Required</span></label>
                                    <input id="prepared_by" name="prepared_by" type="text" value="{{ old('prepared_by', $preparedBy) }}" maxlength="120" required class="block w-full rounded-xl border-gray-200 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600">
                                </div>
                            </div>
                        </article>

                        <article class="rounded-2xl border border-green-200 bg-green-50 p-5">
                            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-green-700">Checked &amp; Verified by</p>
                            <p class="mt-4 text-sm font-black text-gray-900">{{ $workPlanVerifier['name'] }}</p>
                            <p class="mt-1 text-xs font-semibold text-gray-600">{{ $workPlanVerifier['role'] }}</p>
                            <p class="mt-4 text-xs text-gray-600">Date Signed remains blank for a handwritten signature.</p>
                        </article>
                    </div>
                </section>

                <section data-work-plan-step="3" x-show="step === 3" x-cloak class="grid gap-5">
                    <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-xs leading-5 text-green-900">
                        Attachment A is generated from Steps 1 and 2, so no Work Plan upload is required.
                    </div>

                    <div class="flex flex-col gap-1 border-b border-gray-100 pb-4 sm:flex-row sm:items-end sm:justify-between">
                        <div><h4 class="text-sm font-black text-gray-900">Remaining required documents</h4><p class="mt-1 text-xs text-gray-500">Upload each completed file in its designated field.</p></div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Maximum 25 MB per file</p>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        @foreach ($packageInputs as $packageInput)
                            <article class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <label for="{{ $packageInput['name'] }}" class="text-xs font-black text-gray-800">{{ $packageInput['label'] }} <span class="text-red-600">Required</span></label>
                                        <p id="{{ $packageInput['name'] }}_help" class="mt-1 text-[11px] leading-4 text-gray-500">{{ $packageInput['help'] }}</p>
                                    </div>
                                    @if ($proposalSamples->contains($packageInput['sample']))
                                        <a href="{{ route('proposal-samples.show', $packageInput['sample']) }}" target="_blank" rel="noopener" class="shrink-0 text-[10px] font-black uppercase tracking-wide text-blue-700 hover:text-blue-900">View sample &nearr;</a>
                                    @endif
                                </div>
                                <input
                                    id="{{ $packageInput['name'] }}"
                                    name="{{ $packageInput['name'] }}{{ $packageInput['multiple'] ? '[]' : '' }}"
                                    type="file"
                                    accept="{{ $packageInput['accept'] }}"
                                    aria-describedby="{{ $packageInput['name'] }}_help"
                                    @if ($packageInput['multiple']) multiple @endif
                                    required
                                    class="mt-4 block w-full rounded-xl border border-gray-200 bg-white text-xs text-gray-600 shadow-sm file:mr-4 file:border-0 file:bg-gray-900 file:px-4 file:py-3 file:text-xs file:font-bold file:text-white hover:file:bg-gray-800 focus:border-red-600 focus:ring-red-600"
                                >
                                @error($packageInput['multiple'] ? $packageInput['name'].'.*' : $packageInput['name'], 'submission')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                            </article>
                        @endforeach
                    </div>
                </section>

                <section data-work-plan-step="4" x-show="step === 4" x-cloak class="grid gap-4">
                    <div class="flex flex-col gap-3 rounded-xl border border-blue-200 bg-blue-50 p-4 text-xs leading-5 text-blue-900 sm:flex-row sm:items-center sm:justify-between">
                        <span>Review the generated Attachment A. The Word version will be attached automatically when you submit.</span>
                        <span class="shrink-0 rounded-full bg-blue-100 px-3 py-1 text-[10px] font-black uppercase tracking-wide text-blue-700">Official preview</span>
                    </div>

                    <div x-show="previewLoading" class="flex min-h-80 items-center justify-center rounded-2xl border border-gray-200 bg-gray-50" aria-live="polite">
                        <div class="text-center">
                            <span class="mx-auto block h-8 w-8 animate-spin rounded-full border-4 border-gray-200 border-t-red-600"></span>
                            <p class="mt-3 text-xs font-bold text-gray-500">Building the official preview...</p>
                        </div>
                    </div>

                    <div x-show="previewError" x-cloak role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-xs leading-5 text-red-700">
                        <p class="font-black">The preview could not be generated.</p>
                        <p class="mt-1" x-text="previewError"></p>
                    </div>

                    <div x-show="previewHtml && !previewLoading" x-cloak class="overflow-hidden rounded-2xl border border-gray-300 bg-gray-100 shadow-inner">
                        <iframe x-ref="previewFrame" :srcdoc="previewHtml" @load="previewReady = true" title="Official Work Plan preview" class="h-[42rem] w-full bg-gray-100"></iframe>
                    </div>

                    <p x-show="downloadError" x-cloak class="rounded-xl border border-red-200 bg-red-50 p-4 text-xs text-red-700" x-text="downloadError"></p>
                </section>

                <div class="flex flex-col-reverse gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <a x-show="step === 1" href="{{ route('faculty.dashboard') }}" class="inline-flex items-center justify-center rounded-xl border border-gray-200 px-5 py-3 text-xs font-bold text-gray-600 transition hover:bg-gray-50">Cancel</a>
                        <button x-show="step > 1" x-cloak type="button" @click="previousStep()" class="inline-flex items-center justify-center rounded-xl border border-gray-200 px-5 py-3 text-xs font-bold text-gray-600 transition hover:bg-gray-50">&larr; Back</button>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:justify-end">
                        <button x-show="step < 3" type="button" @click="nextStep()" class="rounded-xl bg-red-600 px-6 py-3 text-xs font-bold text-white shadow-sm transition hover:bg-red-700">Continue</button>
                        <button x-show="step === 3" x-cloak type="button" @click="generatePreview()" class="rounded-xl bg-red-600 px-6 py-3 text-xs font-bold text-white shadow-sm transition hover:bg-red-700">Review proposal</button>
                        <button x-show="step === 4" x-cloak type="button" @click="generatePreview()" :disabled="previewLoading" class="rounded-xl border border-gray-200 px-5 py-3 text-xs font-bold text-gray-600 transition hover:bg-gray-50 disabled:cursor-wait disabled:opacity-60">Refresh preview</button>
                        <button x-show="step === 4" x-cloak type="button" @click="downloadDocument()" :disabled="downloadLoading" class="rounded-xl border border-red-200 px-5 py-3 text-xs font-bold text-red-700 transition hover:bg-red-50 disabled:cursor-wait disabled:opacity-60" x-text="downloadLoading ? 'Preparing Word file…' : 'Download Word file'"></button>
                        <button x-show="step === 4" x-cloak type="button" @click="printPreview()" :disabled="!previewReady || previewLoading" class="rounded-xl border border-gray-200 px-5 py-3 text-xs font-bold text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50">Print / Save PDF</button>
                        <button x-show="step === 4" x-cloak type="submit" class="rounded-xl bg-red-600 px-6 py-3 text-xs font-bold text-white shadow-sm transition hover:bg-red-700">Submit proposal package</button>
                    </div>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
