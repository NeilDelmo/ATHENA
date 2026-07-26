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
    $categoriesValue = old('categories', $researchCall?->categories?->pluck('name')->implode(', ') ?? '');
@endphp

<form
    id="{{ $formId }}"
    method="POST"
    action="{{ $isEditing ? route('research-calls.update', $researchCall) : route('research-calls.store') }}"
    enctype="multipart/form-data"
    class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,24rem)]"
    data-research-call-form
    data-extract-url="{{ route('research-calls.extract-image') }}"
>
    @csrf
    @if ($isEditing)
        @method('PUT')
    @endif

    <div class="space-y-4">
        <div class="grid gap-4 md:grid-cols-2">
            <div><label class="text-xs font-bold text-gray-600">Call title</label><input name="title" value="{{ $fieldValue('title') }}" required class="mt-1 block w-full rounded-xl border-gray-200 text-sm"></div>
            <div><label class="text-xs font-bold text-gray-600">Academic year</label><input name="academic_year" value="{{ $fieldValue('academic_year') }}" placeholder="2026-2027" required class="mt-1 block w-full rounded-xl border-gray-200 text-sm"></div>
            <div><label class="text-xs font-bold text-gray-600">Term / semester</label><input name="term" value="{{ $fieldValue('term') }}" class="mt-1 block w-full rounded-xl border-gray-200 text-sm"></div>
            <div><label class="text-xs font-bold text-gray-600">Categories</label><input name="categories" value="{{ $categoriesValue }}" placeholder="Environment, Education, Technology" required class="mt-1 block w-full rounded-xl border-gray-200 text-sm"><p class="mt-1 text-[11px] text-gray-400">Separate category names with commas.</p></div>
            <div><label class="text-xs font-bold text-gray-600">Submission starts</label><input type="datetime-local" name="opens_at" value="{{ $fieldValue('opens_at') }}" required class="mt-1 block w-full rounded-xl border-gray-200 text-sm"></div>
            <div><label class="text-xs font-bold text-gray-600">Submission ends</label><input type="datetime-local" name="closes_at" value="{{ $fieldValue('closes_at') }}" required class="mt-1 block w-full rounded-xl border-gray-200 text-sm"></div>
            <div><label class="text-xs font-bold text-gray-600">Active research limit per faculty</label><input type="number" name="max_active_research_per_faculty" value="{{ $fieldValue('max_active_research_per_faculty', 2) }}" min="1" max="20" required class="mt-1 block w-full rounded-xl border-gray-200 text-sm"><p class="mt-1 text-[11px] text-gray-400">Maximum projects that may be approved for one faculty researcher in this academic year. Proposal applications remain unlimited.</p></div>
            <div><label class="text-xs font-bold text-gray-600">Maximum budget (PHP)</label><input type="number" name="maximum_budget" value="{{ $fieldValue('maximum_budget', $institutionalBudgetCeiling) }}" min="0" max="{{ $institutionalBudgetCeiling }}" step="0.01" required class="mt-1 block w-full rounded-xl border-gray-200 text-sm"><p class="mt-1 text-[11px] text-gray-400">May be lower for a specific call, but cannot exceed the institutional ceiling of PHP {{ number_format($institutionalBudgetCeiling, 2) }}.</p></div>

            <div class="md:col-span-2">
                <div class="mb-3">
                    <h3 class="text-xs font-black uppercase tracking-wider text-gray-600">Research workflow dates <span class="font-normal normal-case tracking-normal text-gray-400">Optional</span></h3>
                    <p class="mt-1 text-[11px] text-gray-400">Use the start and end dates shown in the call poster. Leave a date blank when the poster does not specify it.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-3"><p class="text-xs font-black text-gray-700">Initial Evaluation</p><label class="mt-3 block text-[11px] font-bold text-gray-500">Start date<input type="date" name="initial_evaluation_start_date" value="{{ $fieldValue('initial_evaluation_start_date') }}" class="mt-1 block w-full rounded-lg border-gray-200 text-xs"></label><label class="mt-2 block text-[11px] font-bold text-gray-500">End date<input type="date" name="initial_evaluation_end_date" value="{{ $fieldValue('initial_evaluation_end_date') }}" class="mt-1 block w-full rounded-lg border-gray-200 text-xs"></label></div>
                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-3"><p class="text-xs font-black text-gray-700">Paper Revisions</p><p class="mt-0.5 text-[10px] text-gray-400">Based on Initial Screening</p><label class="mt-3 block text-[11px] font-bold text-gray-500">Start date<input type="date" name="paper_revisions_start_date" value="{{ $fieldValue('paper_revisions_start_date') }}" class="mt-1 block w-full rounded-lg border-gray-200 text-xs"></label><label class="mt-2 block text-[11px] font-bold text-gray-500">End date<input type="date" name="paper_revisions_end_date" value="{{ $fieldValue('paper_revisions_end_date') }}" class="mt-1 block w-full rounded-lg border-gray-200 text-xs"></label></div>
                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-3"><p class="text-xs font-black text-gray-700">Tentative LREC</p><p class="mt-0.5 text-[10px] text-gray-400">Local Research Evaluation</p><label class="mt-3 block text-[11px] font-bold text-gray-500">Start date<input type="date" name="lrec_start_date" value="{{ $fieldValue('lrec_start_date') }}" class="mt-1 block w-full rounded-lg border-gray-200 text-xs"></label><label class="mt-2 block text-[11px] font-bold text-gray-500">End date<input type="date" name="lrec_end_date" value="{{ $fieldValue('lrec_end_date') }}" class="mt-1 block w-full rounded-lg border-gray-200 text-xs"></label></div>
                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-3"><p class="text-xs font-black text-gray-700">Implementation</p><label class="mt-3 block text-[11px] font-bold text-gray-500">Start date<input type="date" name="implementation_start_date" value="{{ $fieldValue('implementation_start_date') }}" class="mt-1 block w-full rounded-lg border-gray-200 text-xs"></label><label class="mt-2 block text-[11px] font-bold text-gray-500">End date<input type="date" name="implementation_end_date" value="{{ $fieldValue('implementation_end_date') }}" class="mt-1 block w-full rounded-lg border-gray-200 text-xs"></label></div>
                </div>
            </div>

            <div class="md:col-span-2"><label class="text-xs font-bold text-gray-600">Description / guidelines</label><textarea name="description" rows="5" class="mt-1 block w-full rounded-xl border-gray-200 text-sm">{{ $fieldValue('description') }}</textarea><p class="mt-1 text-[11px] text-gray-400">Poster requirements are stored here and can be edited before saving.</p></div>

            @unless ($isEditing)
                <div><label class="text-xs font-bold text-gray-600">Publication</label><select name="status" class="mt-1 block w-full rounded-xl border-gray-200 text-sm"><option value="draft" @selected(old('status', 'draft') === 'draft')>Save as draft</option><option value="open" @selected(old('status') === 'open')>Publish and follow schedule</option></select><p class="mt-1 text-[11px] text-gray-400">A published call opens and ends automatically according to the dates above.</p></div>
            @endunless

            <div class="flex items-end justify-end {{ $isEditing ? 'md:col-span-2' : '' }}"><button class="rounded-xl bg-red-600 px-5 py-2.5 text-xs font-bold text-white">{{ $isEditing ? 'Save changes' : 'Create call' }}</button></div>
            @if ($errors->any())<div class="md:col-span-2 rounded-xl bg-red-50 p-3 text-xs text-red-700">{{ $errors->first() }}</div>@endif
        </div>
    </div>

    <aside class="lg:sticky lg:top-6 lg:self-start">
        <div class="rounded-2xl border border-dashed border-red-200 bg-red-50/50 p-4 dark:border-red-900/70 dark:bg-red-950/20">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <label class="text-xs font-black text-gray-700 dark:text-slate-200" for="{{ $imageInputId }}">{{ $isEditing ? 'Update poster image' : 'Reference poster image' }}</label>
                    <p class="mt-1 text-[11px] font-semibold text-gray-500 dark:text-slate-400">Optional · drop, paste, or browse your files</p>
                </div>
                <span class="rounded-full bg-white px-2 py-1 text-[10px] font-black uppercase tracking-wider text-red-700 shadow-sm dark:bg-slate-900 dark:text-red-300">Poster</span>
            </div>

            <label for="{{ $imageInputId }}" data-research-call-image-dropzone tabindex="0" class="group mt-4 flex min-h-[24rem] cursor-pointer flex-col items-center justify-center overflow-hidden rounded-xl border-2 border-dashed border-red-200 bg-white p-3 text-center transition hover:border-red-400 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 dark:border-red-900 dark:bg-slate-950 dark:hover:border-red-700 dark:hover:bg-red-950/30">
                <input id="{{ $imageInputId }}" name="reference_image" type="file" accept="image/jpeg,image/png,image/webp" data-research-call-image class="sr-only">
                <span data-research-call-image-empty class="{{ $currentImageUrl ? 'hidden' : 'flex' }} flex-col items-center gap-3 px-5 py-8">
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-red-50 text-red-600 dark:bg-red-950/50 dark:text-red-300">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5V6.75A2.25 2.25 0 015.25 4.5h13.5A2.25 2.25 0 0121 6.75v10.5a2.25 2.25 0 01-2.25 2.25H8.25M3 16.5l3.75-3.75a2.25 2.25 0 013.182 0L12 13.818m-9 2.682 2.25 2.25m12-7.5 1.5-1.5M15 8.25h.008v.008H15V8.25z" /><path stroke-linecap="round" stroke-linejoin="round" d="M3 19.5h6" /></svg>
                    </span>
                    <span>
                        <span class="block text-sm font-black text-gray-700 dark:text-slate-200">Drag and drop poster</span>
                        <span class="mt-1 block text-xs text-gray-400">or click to choose · Ctrl+V also works</span>
                    </span>
                </span>
                <img data-research-call-image-preview src="{{ $currentImageUrl ?? '' }}" alt="{{ $isEditing ? 'Current research call poster preview' : 'Selected research call poster preview' }}" class="{{ $currentImageUrl ? '' : 'hidden' }} max-h-[34rem] w-full rounded-lg object-contain">
            </label>

            <div class="mt-3 flex items-center justify-between gap-3">
                <p data-research-call-image-name class="min-w-0 truncate text-[11px] font-bold text-gray-600 dark:text-slate-300">{{ $currentImageUrl ? 'Current poster saved. Choose a new image to replace it.' : '' }}</p>
                <button type="button" data-research-call-extract class="shrink-0 rounded-xl bg-red-700 px-3 py-2.5 text-[11px] font-black text-white transition hover:bg-red-800 disabled:cursor-wait disabled:opacity-60">Read image</button>
            </div>
            <p class="mt-2 text-[11px] leading-4 text-gray-500 dark:text-slate-400">The image reader can copy the title, requirements, budget, categories, and dates into the fields on the left. Review everything before saving.</p>
            <p data-research-call-image-status role="status" class="mt-2 hidden text-xs font-semibold text-red-700 dark:text-red-300"></p>
        </div>
    </aside>
</form>
