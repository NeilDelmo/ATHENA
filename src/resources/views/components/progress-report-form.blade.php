@props(['topic'])

@php
    $draft = $topic->revisionDraft;
    $researcherNames = collect([$draft?->project_leader ?: $topic->user->name])
        ->merge($draft?->members?->pluck('name') ?? collect())
        ->filter()
        ->unique()
        ->implode("\n");
    $approvedStart = $draft?->planned_start ?? $topic->notice_to_proceed_issued_at?->copy()->startOfDay();
    $approvedEnd = $draft?->planned_end ?? $approvedStart?->copy()->addMonths((int) $topic->estimated_duration_months);
    $maxAccomplishments = (int) config('progress_report.max_accomplishments');
    $blankAccomplishment = ['objective' => '', 'target' => '', 'actual' => ''];
    $accomplishmentRows = collect(old('accomplishments', array_fill(0, 4, $blankAccomplishment)))
        ->map(fn ($row) => array_merge($blankAccomplishment, is_array($row) ? $row : []))
        ->pad($maxAccomplishments, $blankAccomplishment)
        ->take($maxAccomplishments);
    $maxFigures = (int) config('progress_report.max_figures');
@endphp

<details class="overflow-hidden rounded-2xl border border-emerald-100 bg-emerald-50/50" @if ($errors->narrativeProgress->any()) open @endif>
    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 text-sm font-black text-emerald-900">
        <span>
            Submit progress report
            <span class="mt-1 block text-xs font-normal text-emerald-700">BatStateU-REC-RES-02 · Revision 02</span>
        </span>
        <span class="rounded-full bg-white px-3 py-1 text-[10px] font-black uppercase text-emerald-700 shadow-sm">Open form</span>
    </summary>

    <form method="POST" action="{{ route('project-narrative-reports.store', $topic) }}" enctype="multipart/form-data" class="space-y-6 border-t border-emerald-100 bg-white p-5">
        @csrf

        @if ($errors->narrativeProgress->any())
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-xs text-red-700">
                <p class="font-black">Please correct the highlighted progress-report fields.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->narrativeProgress->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-3 rounded-xl bg-gray-50 p-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2">
                <p class="text-[10px] font-black uppercase tracking-wide text-gray-400">Research project title</p>
                <p class="mt-1 text-xs font-bold text-gray-800">{{ $topic->title }}</p>
            </div>
            <div>
                <p class="text-[10px] font-black uppercase tracking-wide text-gray-400">Project leader</p>
                <p class="mt-1 text-xs font-bold text-gray-800">{{ $topic->user->name }}</p>
            </div>
            <div>
                <p class="text-[10px] font-black uppercase tracking-wide text-gray-400">Approved budget</p>
                <p class="mt-1 text-xs font-bold text-gray-800">₱{{ number_format((float) $topic->estimated_budget, 2) }}</p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="progress_submission_date" class="text-[11px] font-bold text-gray-600">Submission date</label>
                <x-date-picker id="progress_submission_date" name="submission_date" :value="old('submission_date', now()->toDateString())" :max="now()->toDateString()" required class="mt-1" />
            </div>
            <label class="text-[11px] font-bold text-gray-600">Tracking number <span class="font-normal text-gray-400">(optional)</span>
                <input type="text" name="tracking_number" value="{{ old('tracking_number') }}" maxlength="100" class="mt-1 block w-full rounded-xl border-gray-200 text-xs" placeholder="Enter the official tracking number">
            </label>
        </div>

        <section class="grid gap-4 md:grid-cols-2">
            <label class="text-[11px] font-bold text-gray-600 md:col-span-2">II. Researchers
                <textarea name="researchers" rows="3" maxlength="1000" required class="mt-1 block w-full rounded-xl border-gray-200 text-xs" placeholder="Enter one researcher per line">{{ old('researchers', $researcherNames) }}</textarea>
            </label>
            <div>
                <label for="implementation_start" class="text-[11px] font-bold text-gray-600">III. Approved implementation start</label>
                <x-date-picker id="implementation_start" name="implementation_start" :value="old('implementation_start', $approvedStart?->toDateString())" required class="mt-1" />
            </div>
            <div>
                <label for="implementation_end" class="text-[11px] font-bold text-gray-600">III. Approved implementation end</label>
                <x-date-picker id="implementation_end" name="implementation_end" :value="old('implementation_end', $approvedEnd?->toDateString())" required class="mt-1" />
            </div>
            <label class="text-[11px] font-bold text-gray-600 md:col-span-2">V. Funding agency
                <input type="text" name="funding_agency" value="{{ old('funding_agency', 'Batangas State University') }}" maxlength="255" required class="mt-1 block w-full rounded-xl border-gray-200 text-xs">
            </label>
        </section>

        <section class="space-y-4">
            <div>
                <p class="text-sm font-black text-gray-900">VI. Summary of Accomplishment for the Monitoring Period</p>
                <p class="mt-1 text-xs text-gray-500">Match each approved objective with its target and the work actually completed during this period.</p>
            </div>

            <div class="space-y-3">
                @foreach ($accomplishmentRows as $index => $accomplishment)
                    <div class="grid gap-3 rounded-xl border border-gray-200 p-3 lg:grid-cols-3">
                        <label class="text-[11px] font-bold text-gray-600">Objective {{ $index + 1 }}
                            <textarea name="accomplishments[{{ $index }}][objective]" rows="3" maxlength="1000" class="mt-1 block w-full rounded-xl border-gray-200 text-xs" @required($index === 0)>{{ $accomplishment['objective'] }}</textarea>
                        </label>
                        <label class="text-[11px] font-bold text-gray-600">Target accomplishment
                            <textarea name="accomplishments[{{ $index }}][target]" rows="3" maxlength="2000" class="mt-1 block w-full rounded-xl border-gray-200 text-xs" @required($index === 0)>{{ $accomplishment['target'] }}</textarea>
                        </label>
                        <label class="text-[11px] font-bold text-gray-600">Actual accomplishment
                            <textarea name="accomplishments[{{ $index }}][actual]" rows="3" maxlength="2000" class="mt-1 block w-full rounded-xl border-gray-200 text-xs" @required($index === 0)>{{ $accomplishment['actual'] }}</textarea>
                        </label>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="space-y-4">
            <div>
                <p class="text-sm font-black text-gray-900">Narrative sections</p>
                <p class="mt-1 text-xs text-gray-500">The generated document follows the same continuous report layout as the client template.</p>
            </div>
            @foreach ([
                'introduction' => 'VII. Introduction',
                'rationale' => 'VIII. Rationale',
                'objectives' => 'VIII. Objectives',
                'methodology' => 'IX. Methodology',
                'results_discussion' => 'X. Results and Discussion',
            ] as $field => $label)
                <label class="block text-[11px] font-bold text-gray-600">{{ $label }}
                    <textarea name="{{ $field }}" rows="4" maxlength="5000" required class="mt-1 block w-full rounded-xl border-gray-200 text-xs">{{ old($field) }}</textarea>
                </label>
            @endforeach
        </section>

        <section class="space-y-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
            <div>
                <p class="text-xs font-black text-amber-900">Figures and photo documentation required</p>
                <p class="mt-1 text-[11px] text-amber-700">Attach at least one high-resolution JPG or PNG. Each figure will be placed under Methodology or Results and Discussion with its caption.</p>
            </div>
            @foreach (range(1, $maxFigures) as $photoIndex)
                @if ($photoIndex === 6)
                    <details class="rounded-xl border border-amber-200 bg-white/60">
                        <summary class="cursor-pointer px-4 py-3 text-[11px] font-black text-amber-900">Add more figures (6-{{ $maxFigures }})</summary>
                        <div class="space-y-3 border-t border-amber-100 p-3">
                @endif

                <div class="grid gap-3 rounded-xl border border-amber-100 bg-white p-3 sm:grid-cols-3">
                    <label class="text-[11px] font-bold text-gray-600">Figure {{ $photoIndex }} {{ $photoIndex === 1 ? '(required)' : '(optional)' }}
                        <input type="file" name="photo_{{ $photoIndex }}" accept=".jpg,.jpeg,.png" @required($photoIndex === 1) class="mt-1 block w-full rounded-xl border border-gray-200 bg-white p-2 text-xs">
                    </label>
                    <label class="text-[11px] font-bold text-gray-600">Place under
                        <select name="photo_section_{{ $photoIndex }}" @required($photoIndex === 1) class="mt-1 block w-full rounded-xl border-gray-200 text-xs">
                            <option value="results_discussion" @selected(old('photo_section_'.$photoIndex, 'results_discussion') === 'results_discussion')>Results and Discussion</option>
                            <option value="methodology" @selected(old('photo_section_'.$photoIndex) === 'methodology')>Methodology</option>
                        </select>
                    </label>
                    <label class="text-[11px] font-bold text-gray-600">Caption {{ $photoIndex }}
                        <input type="text" name="photo_caption_{{ $photoIndex }}" value="{{ old('photo_caption_'.$photoIndex) }}" maxlength="200" @required($photoIndex === 1) class="mt-1 block w-full rounded-xl border-gray-200 text-xs" placeholder="Describe what the photo shows">
                    </label>
                </div>

                @if ($photoIndex === $maxFigures && $maxFigures >= 6)
                        </div>
                    </details>
                @endif
            @endforeach
        </section>

        <div class="grid gap-4 rounded-xl bg-gray-50 p-4 sm:grid-cols-2 sm:items-end">
            <div>
                <label for="progress_prepared_by_date_signed" class="text-[11px] font-bold text-gray-600">Prepared-by date signed <span class="font-normal text-gray-400">(optional)</span></label>
                <x-date-picker id="progress_prepared_by_date_signed" name="prepared_by_date_signed" :value="old('prepared_by_date_signed')" :max="now()->toDateString()" class="mt-1" />
            </div>
            <button type="submit" class="rounded-xl bg-emerald-700 px-5 py-3 text-xs font-bold text-white shadow-sm">Submit progress report</button>
        </div>
    </form>
</details>
