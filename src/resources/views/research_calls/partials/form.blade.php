@php
    $isEditing = $researchCall !== null;
    $formId = $isEditing ? 'edit-research-call-'.$researchCall->id : 'create-research-call';
    $imageInputId = $formId.'-reference-image';
    $currentImageUrl = $isEditing && $researchCall->reference_image_path
        ? route('research-calls.reference-image', $researchCall)
        : null;
    $fieldValue = function (string $field, mixed $default = '') use ($researchCall): string {
        $value = old($field, $researchCall?->{$field} ?? $default);

        if ($value instanceof \DateTimeInterface) {
            return str_ends_with($field, '_at') ? $value->format('Y-m-d\\TH:i') : $value->format('Y-m-d');
        }

        return (string) ($value ?? '');
    };
    $inputClass = 'mt-2 block w-full rounded-xl border-gray-300 bg-white px-3.5 py-3 text-sm font-semibold text-gray-900 shadow-sm transition placeholder:text-gray-400 hover:border-gray-400 focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:hover:border-slate-500';
    $labelClass = 'text-sm font-black text-gray-800 dark:text-slate-100';
    $hintClass = 'mt-1.5 text-xs leading-5 text-gray-500 dark:text-slate-400';
    $workflowDates = [
        ['number' => '01', 'title' => 'Initial evaluation', 'description' => 'First review by the Research Office.', 'start' => 'initial_evaluation_start_date', 'end' => 'initial_evaluation_end_date'],
        ['number' => '02', 'title' => 'Paper revisions', 'description' => 'Based on the initial screening.', 'start' => 'paper_revisions_start_date', 'end' => 'paper_revisions_end_date'],
        ['number' => '03', 'title' => 'Tentative LREC', 'description' => 'Local Research Evaluation.', 'start' => 'lrec_start_date', 'end' => 'lrec_end_date'],
        ['number' => '04', 'title' => 'Implementation', 'description' => 'Planned project implementation.', 'start' => 'implementation_start_date', 'end' => 'implementation_end_date'],
    ];
@endphp

<form
    id="{{ $formId }}"
    method="POST"
    action="{{ $isEditing ? route('research-calls.update', $researchCall) : route('research-calls.store') }}"
    enctype="multipart/form-data"
    class="grid gap-6 border-t border-gray-100 px-5 pb-6 pt-5 dark:border-slate-800 sm:px-6 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,24rem)]"
    data-research-call-form
    data-extract-url="{{ route('research-calls.extract-image') }}"
>
    @csrf
    @if ($isEditing)
        @method('PUT')
    @endif

    <x-research-call-poster-reading-loading-screen />

    <div class="space-y-5">
        <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
            <div>
                <p class="text-[11px] font-black uppercase tracking-[0.18em] text-red-700 dark:text-red-300">Call details</p>
                <h3 class="mt-1 text-lg font-black tracking-tight text-gray-950 dark:text-white">Name the call</h3>
            </div>
            <div class="mt-5 grid gap-5 md:grid-cols-2">
                <label class="block md:col-span-2"><span class="{{ $labelClass }}">Call name</span><input name="title" value="{{ $fieldValue('title') }}" required placeholder="e.g. Call for Proposals — August 2026 Implementation" class="{{ $inputClass }}"><span class="{{ $hintClass }} block">This identifies the call. Faculty enter their separate project title when they create a proposal.</span></label>
                <label class="block"><span class="{{ $labelClass }}">Academic year</span><input name="academic_year" value="{{ $fieldValue('academic_year') }}" placeholder="2026–2027" required class="{{ $inputClass }}"></label>
                <label class="block"><span class="{{ $labelClass }}">Term / semester <span class="font-medium text-gray-400">Optional</span></span><input name="term" value="{{ $fieldValue('term') }}" placeholder="1st semester" class="{{ $inputClass }}"></label>
            </div>
        </section>

        <section class="rounded-2xl border border-red-100 bg-red-50/40 p-4 shadow-sm dark:border-red-950/70 dark:bg-red-950/15 sm:p-5">
            <div class="flex items-start gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-red-600 text-xs font-black text-white">01</span>
                <div><p class="text-[11px] font-black uppercase tracking-[0.18em] text-red-700 dark:text-red-300">Submission window</p><h3 class="mt-1 text-lg font-black tracking-tight text-gray-950 dark:text-white">When faculty can submit</h3><p class="mt-1 text-xs leading-5 text-gray-600 dark:text-slate-400">Choose the opening and closing date, then set the time from the same calendar.</p></div>
            </div>
            <div class="mt-5 grid gap-5 md:grid-cols-2">
                <label class="block"><span class="{{ $labelClass }}">Submission starts</span><x-date-time-picker id="{{ $formId }}-opens-at" name="opens_at" :value="$fieldValue('opens_at')" required class="mt-2" /></label>
                <label class="block"><span class="{{ $labelClass }}">Submission ends</span><x-date-time-picker id="{{ $formId }}-closes-at" name="closes_at" :value="$fieldValue('closes_at')" required class="mt-2" /></label>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
            <div class="grid gap-5 md:grid-cols-2">
                <label class="block"><span class="{{ $labelClass }}">Active research limit per faculty</span><input type="number" name="max_active_research_per_faculty" value="{{ $fieldValue('max_active_research_per_faculty', 2) }}" min="1" max="2" required class="{{ $inputClass }}"><span class="{{ $hintClass }} block">The institutional hard limit is two concurrent approved projects across all calls and academic years.</span></label>
                <div><span class="{{ $labelClass }} block">Maximum allowed budget</span><div class="mt-2 flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-3 text-gray-900 dark:border-slate-700 dark:bg-slate-950 dark:text-white"><span class="text-xs font-black uppercase tracking-wider text-red-700 dark:text-red-300">PHP</span><span class="text-base font-black">{{ number_format($institutionalBudgetCeiling, 2) }}</span></div><p class="{{ $hintClass }}">Proposals may request up to PHP {{ number_format($institutionalBudgetCeiling, 2) }}.</p></div>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
            <div class="flex items-start justify-between gap-4"><div><p class="text-[11px] font-black uppercase tracking-[0.18em] text-red-700 dark:text-red-300">Workflow schedule</p><h3 class="mt-1 text-lg font-black tracking-tight text-gray-950 dark:text-white">Optional review milestones</h3><p class="mt-1 text-xs leading-5 text-gray-500 dark:text-slate-400">Use the dates on the poster. Leave either date blank when it was not announced.</p></div><span class="rounded-full bg-gray-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-gray-500 dark:bg-slate-800 dark:text-slate-300">Optional</span></div>
            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                @foreach ($workflowDates as $workflowDate)
                    <article class="rounded-2xl border border-gray-200 bg-gray-50/70 p-4 dark:border-slate-800 dark:bg-slate-950/45">
                        <div class="flex gap-3"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white text-[10px] font-black text-red-700 shadow-sm dark:bg-slate-900 dark:text-red-300">{{ $workflowDate['number'] }}</span><div><h4 class="text-sm font-black text-gray-900 dark:text-white">{{ $workflowDate['title'] }}</h4><p class="mt-0.5 text-xs text-gray-500 dark:text-slate-400">{{ $workflowDate['description'] }}</p></div></div>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <label class="block text-xs font-bold text-gray-600 dark:text-slate-300">Starts<x-date-picker id="{{ $formId }}-{{ $workflowDate['start'] }}" name="{{ $workflowDate['start'] }}" :value="$fieldValue($workflowDate['start'])" placeholder="Select date" class="mt-1.5" /></label>
                            <label class="block text-xs font-bold text-gray-600 dark:text-slate-300">Ends<x-date-picker id="{{ $formId }}-{{ $workflowDate['end'] }}" name="{{ $workflowDate['end'] }}" :value="$fieldValue($workflowDate['end'])" placeholder="Select date" class="mt-1.5" /></label>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
            <label class="block"><span class="{{ $labelClass }}">Description / guidelines</span><textarea name="description" rows="6" placeholder="Describe the proposal requirements, priorities, and other instructions for faculty." class="{{ $inputClass }} resize-y">{{ $fieldValue('description') }}</textarea><span class="{{ $hintClass }} block">Poster requirements stay editable. When the reader can verify them against the poster text, visual wraps are grouped into one requirement per line.</span></label>
            <div class="mt-5 flex flex-col gap-4 border-t border-gray-100 pt-5 dark:border-slate-800 sm:flex-row sm:items-end sm:justify-between">
                @unless ($isEditing)
                    <label class="block sm:max-w-sm"><span class="{{ $labelClass }}">Publication</span><select name="status" class="{{ $inputClass }}"><option value="draft" @selected($fieldValue('status', 'draft') === 'draft')>Save as draft</option><option value="open" @selected($fieldValue('status') === 'open')>Publish and follow schedule</option></select><span class="{{ $hintClass }} block">A published call opens and ends automatically according to the dates above.</span></label>
                @endunless
                <button class="inline-flex min-h-11 items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-black text-white shadow-sm shadow-red-600/20 transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:focus:ring-offset-slate-900">{{ $isEditing ? 'Save changes' : 'Create call' }}</button>
            </div>
        </section>

        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ $errors->first() }}</div>
        @endif
    </div>

    <aside class="lg:sticky lg:top-6 lg:self-start">
        <div class="rounded-2xl border border-dashed border-red-200 bg-red-50/50 p-4 dark:border-red-900/70 dark:bg-red-950/20">
            <div class="flex items-start justify-between gap-3"><div><label class="text-sm font-black text-gray-800 dark:text-slate-100" for="{{ $imageInputId }}">{{ $isEditing ? 'Update poster image' : 'Reference poster image' }}</label><p class="mt-1 text-xs font-semibold text-gray-500 dark:text-slate-400">Optional · drop, paste, or browse your files</p></div><span class="rounded-full bg-white px-2 py-1 text-[10px] font-black uppercase tracking-wider text-red-700 shadow-sm dark:bg-slate-900 dark:text-red-300">Poster</span></div>
            <label for="{{ $imageInputId }}" data-research-call-image-dropzone tabindex="0" class="group mt-4 flex min-h-[24rem] cursor-pointer flex-col items-center justify-center overflow-hidden rounded-xl border-2 border-dashed border-red-200 bg-white p-3 text-center transition hover:border-red-400 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 dark:border-red-900 dark:bg-slate-950 dark:hover:border-red-700 dark:hover:bg-red-950/30">
                <input id="{{ $imageInputId }}" name="reference_image" type="file" accept="image/jpeg,image/png,image/webp" data-research-call-image class="sr-only">
                <span data-research-call-image-empty class="{{ $currentImageUrl ? 'hidden' : 'flex' }} flex-col items-center gap-3 px-5 py-8"><span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-red-50 text-red-600 dark:bg-red-950/50 dark:text-red-300"><svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5V6.75A2.25 2.25 0 015.25 4.5h13.5A2.25 2.25 0 0121 6.75v10.5a2.25 2.25 0 01-2.25 2.25H8.25M3 16.5l3.75-3.75a2.25 2.25 0 013.182 0L12 13.818m-9 2.682 2.25 2.25m12-7.5 1.5-1.5M15 8.25h.008v.008H15V8.25z" /><path stroke-linecap="round" stroke-linejoin="round" d="M3 19.5h6" /></svg></span><span><span class="block text-sm font-black text-gray-700 dark:text-slate-200">Drag and drop poster</span><span class="mt-1 block text-xs text-gray-400">or click to choose · Ctrl+V also works</span></span></span>
                <img data-research-call-image-preview src="{{ $currentImageUrl ?? '' }}" alt="{{ $isEditing ? 'Current research call poster preview' : 'Selected research call poster preview' }}" class="{{ $currentImageUrl ? '' : 'hidden' }} max-h-[34rem] w-full rounded-lg object-contain">
            </label>
            <div class="mt-3 flex items-center justify-between gap-3">
                <p data-research-call-image-name class="min-w-0 truncate text-xs font-bold text-gray-600 dark:text-slate-300">{{ $currentImageUrl ? 'Current poster saved. Choose a new image to replace it.' : '' }}</p>
                <button type="button" data-research-call-extract disabled class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-red-700 px-3.5 py-2.5 text-xs font-black text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-slate-950">
                    <svg data-research-call-extract-spinner class="hidden h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-30" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3"></circle><path class="opacity-90" fill="currentColor" d="M21 12a9 9 0 00-9-9v3a6 6 0 016 6h3z"></path></svg>
                    <span data-research-call-extract-label>Read image</span>
                </button>
            </div>
            <p class="mt-3 text-xs leading-5 text-gray-500 dark:text-slate-400">Choosing a poster only previews it. Click <span class="font-bold text-gray-700 dark:text-slate-200">Read image</span> to suggest the call name, requirements, and dates; existing entries will not be replaced.</p>
            <p data-research-call-image-status role="status" aria-live="polite" class="mt-2 hidden text-xs font-semibold text-red-700 dark:text-red-300"></p>

            <div data-research-call-extraction-summary class="mt-4 hidden space-y-3 rounded-2xl border border-gray-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="grid gap-3 sm:grid-cols-2">
                    <section class="rounded-xl border border-green-200 bg-green-50 p-3 dark:border-green-900/70 dark:bg-green-950/25">
                        <h4 class="text-xs font-black text-green-800 dark:text-green-200">Detected from the poster</h4>
                        <ul data-research-call-detected-fields class="mt-2 list-disc space-y-1 pl-4 text-xs leading-5 text-green-800 dark:text-green-200"></ul>
                    </section>
                    <section class="rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-900/70 dark:bg-amber-950/25">
                        <h4 class="text-xs font-black text-amber-900 dark:text-amber-200">Not found or incomplete</h4>
                        <ul data-research-call-missing-fields class="mt-2 list-disc space-y-1 pl-4 text-xs leading-5 text-amber-900 dark:text-amber-200"></ul>
                    </section>
                </div>

                <section data-research-call-warning-section class="hidden rounded-xl border border-red-200 bg-red-50 p-3 dark:border-red-900/70 dark:bg-red-950/25">
                    <h4 class="text-xs font-black text-red-800 dark:text-red-200">Review before saving</h4>
                    <ul data-research-call-warning-list class="mt-2 list-disc space-y-1 pl-4 text-xs leading-5 text-red-800 dark:text-red-200"></ul>
                </section>

                <section data-research-call-expired-schedule-section class="hidden rounded-xl border border-red-300 bg-red-50 p-3 dark:border-red-800 dark:bg-red-950/35">
                    <h4 class="text-xs font-black text-red-900 dark:text-red-100">Schedule needs replacement</h4>
                    <p data-research-call-expired-schedule-warning class="mt-1 text-xs font-semibold leading-5 text-red-800 dark:text-red-200"></p>
                </section>

                <section data-research-call-clear-schedule-section class="hidden rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-900/70 dark:bg-amber-950/25">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs leading-5 text-amber-900 dark:text-amber-200">Dates copied from this poster can be cleared without changing the poster, call details, or guidelines.</p>
                        <button type="button" data-research-call-clear-schedule class="inline-flex shrink-0 items-center justify-center rounded-lg border border-amber-300 bg-white px-3 py-2 text-xs font-black text-amber-900 transition hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-500 dark:border-amber-800 dark:bg-slate-900 dark:text-amber-100 dark:hover:bg-amber-950/50">Clear extracted schedule</button>
                    </div>
                </section>

                <details data-research-call-transcription-section class="hidden overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-slate-700 dark:bg-slate-950/60">
                    <summary class="cursor-pointer px-3 py-2.5 text-xs font-black text-gray-700 dark:text-slate-200">Review extracted poster text</summary>
                    <pre data-research-call-transcription class="max-h-72 overflow-auto whitespace-pre-wrap border-t border-gray-200 px-3 py-3 font-sans text-xs leading-5 text-gray-600 dark:border-slate-700 dark:text-slate-300"></pre>
                </details>
            </div>
        </div>
    </aside>
</form>
