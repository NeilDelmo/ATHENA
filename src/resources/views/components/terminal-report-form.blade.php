@props(['topic', 'draft' => null, 'defaults' => [], 'evidence' => [], 'standalone' => false])
@php
    $data = array_replace($defaults, $draft?->source_data ?? []);
    $terminal = $data['terminal_data'] ?? [];
    $value = fn ($key, $fallback = '') => old($key, data_get($data, $key, $fallback));
    $input = 'mt-2 block min-h-12 w-full rounded-xl border-gray-300 bg-white px-3 py-2.5 text-base text-gray-950 shadow-sm transition placeholder:text-gray-400 focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-800 dark:text-white';
    $authors = $value('terminal_data.authors', []);
    $accomplishments = $value('accomplishments', []) ?: [['objective' => '', 'target' => '', 'actual' => '']];
    $selectedCover = $value('reuse_cover_image');
    $coverPreview = $evidence[$selectedCover]['preview_url'] ?? '';
    $usedFigureSlots = collect(range(1, 30))
        ->filter(fn (int $index): bool => filled($value('reuse_photo_'.$index)) || filled($value('photo_caption_'.$index)))
        ->max();
    $initialFigureCount = max(1, (int) ($usedFigureSlots ?: 1));
    $monitoringSources = collect($defaults['monitoring_reference'] ?? []);
    $missingPeriods = collect($defaults['missing_monitoring_periods'] ?? []);
    $objectivesFromWorkPlan = (bool) ($defaults['objectives_from_work_plan'] ?? false);
    $objectiveCount = count($accomplishments);
    $evidenceCount = count($evidence);
@endphp

<section
    class="bg-slate-50/70 p-4 sm:p-6 lg:p-8"
    data-narrative-progress-autosave="true"
    x-data="narrativeProgressReportForm({previewUrl: @js(route('project-narrative-reports.preview', $topic)), draftSaveUrl: @js(route('project-narrative-reports.draft', $topic)), initialDraftVersion: @js((int) ($draft?->lock_version ?? 0)), csrfToken: @js(csrf_token())})"
>
    <form
        x-ref="form"
        data-narrative-progress-autosave-form
        method="POST"
        action="{{ route('project-narrative-reports.prepare', $topic) }}"
        enctype="multipart/form-data"
        class="mx-auto max-w-6xl space-y-8 text-base leading-7 text-gray-800 dark:text-slate-200 {{ $standalone ? 'pb-44 sm:pb-32' : '' }}"
        @submit="submitting = true"
    >
        @csrf
        <input type="hidden" name="report_type" value="terminal">
        <input type="hidden" name="draft_version" value="{{ $draft?->lock_version ?? 0 }}">
        <x-proposal-autosave-status />

        <header class="overflow-hidden rounded-3xl border border-red-100 bg-gradient-to-br from-red-50 via-white to-amber-50 shadow-sm dark:border-red-950 dark:from-slate-900 dark:via-slate-900 dark:to-red-950/30">
            <div class="grid gap-6 px-6 py-7 sm:px-8 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-red-700 dark:text-red-300">Terminal report workspace</p>
                    <h2 class="mt-2 max-w-3xl text-3xl font-black leading-tight tracking-tight text-slate-950 dark:text-white sm:text-4xl">Assemble the final project record</h2>
                    <p class="mt-3 max-w-3xl text-base leading-7 text-slate-600 dark:text-slate-300">Approved objectives stay connected to the Work Plan. Quarterly accomplishments, report evidence, final findings, and the project poster are brought together here.</p>
                </div>
                <div class="rounded-2xl border border-white bg-white/90 px-5 py-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <p class="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">Official form</p>
                    <p class="mt-1 text-lg font-black text-slate-950 dark:text-white">BatStateU-REC-RES-04</p>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Revision 02</p>
                </div>
            </div>
            <nav aria-label="Terminal report sections" class="grid border-t border-red-100 bg-white/70 sm:grid-cols-3 dark:border-red-950 dark:bg-slate-900/70">
                <a href="#terminal-accomplishments" class="flex min-h-14 items-center justify-center gap-2 border-b border-red-100 px-4 py-3 text-sm font-bold text-slate-700 transition hover:bg-red-50 hover:text-red-800 sm:border-b-0 sm:border-r dark:border-red-950 dark:text-slate-200 dark:hover:bg-red-950/40">1. Confirm outcomes</a>
                <a href="#terminal-cover" class="flex min-h-14 items-center justify-center gap-2 border-b border-red-100 px-4 py-3 text-sm font-bold text-slate-700 transition hover:bg-red-50 hover:text-red-800 sm:border-b-0 sm:border-r dark:border-red-950 dark:text-slate-200 dark:hover:bg-red-950/40">2. Add project poster</a>
                <a href="#terminal-figures" class="flex min-h-14 items-center justify-center gap-2 px-4 py-3 text-sm font-bold text-slate-700 transition hover:bg-red-50 hover:text-red-800 dark:text-slate-200 dark:hover:bg-red-950/40">3. Attach evidence</a>
            </nav>
        </header>

        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" aria-label="Terminal report source summary">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs font-black uppercase tracking-wider text-slate-400">Approved objectives</p>
                <p class="mt-2 text-2xl font-black tabular-nums text-slate-950 dark:text-white">{{ $objectiveCount }}</p>
                <p class="mt-1 text-xs text-slate-500">Carried from the approved Work Plan</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs font-black uppercase tracking-wider text-slate-400">Monitoring sources</p>
                <p class="mt-2 text-2xl font-black tabular-nums text-slate-950 dark:text-white">{{ $monitoringSources->count() }}</p>
                <p class="mt-1 text-xs text-slate-500">Quarterly records combined below</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs font-black uppercase tracking-wider text-slate-400">Reusable images</p>
                <p class="mt-2 text-2xl font-black tabular-nums text-slate-950 dark:text-white">{{ $evidenceCount }}</p>
                <p class="mt-1 text-xs text-slate-500">From earlier progress reports</p>
            </article>
            <article class="rounded-2xl border p-4 shadow-sm {{ $missingPeriods->isEmpty() ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/30' : 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/30' }}">
                <p class="text-xs font-black uppercase tracking-wider {{ $missingPeriods->isEmpty() ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300' }}">Reporting readiness</p>
                <p class="mt-2 text-lg font-black text-slate-950 dark:text-white">{{ $missingPeriods->isEmpty() ? 'Ready to prepare' : $missingPeriods->count().' period(s) missing' }}</p>
                <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">Drafting remains available at any time</p>
            </article>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900" aria-labelledby="terminal-data-flow-heading">
            <h3 id="terminal-data-flow-heading" class="text-sm font-black text-slate-950 dark:text-white">How the final report is assembled</h3>
            <div class="mt-4 grid gap-3 md:grid-cols-4">
                @foreach ([['1', 'Approved proposal', 'Introduction, rationale, methods, objectives'], ['2', 'Work Plan', 'Locked objectives and target outputs'], ['3', 'Monitoring records', 'Quarterly accomplishments and evidence'], ['4', 'Terminal report', 'Final results, conclusions, poster, and signatures']] as [$number, $title, $description])
                    <div class="flex gap-3 rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-red-700 text-xs font-black text-white">{{ $number }}</span>
                        <div><p class="text-sm font-black text-slate-900 dark:text-white">{{ $title }}</p><p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $description }}</p></div>
                    </div>
                @endforeach
            </div>
        </section>

        @if ($errors->narrativeProgress->any())
            <div role="alert" class="rounded-2xl border border-red-200 bg-red-50 p-5 text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-100">
                <p class="font-bold">Please review these fields:</p>
                <ul class="mt-2 list-disc space-y-1 pl-6">@foreach ($errors->narrativeProgress->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5 text-blue-950 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-100">
            Review the carried-over proposal content and confirm the final dates, spending, and findings. Image files are not autosaved, so choose new uploads again if you leave before preparing the report.
        </div>

        @if (($defaults['missing_monitoring_periods'] ?? []) !== [])
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-950 dark:border-amber-900 dark:bg-amber-950">
                Before preparing the official copy, complete monitoring reports for {{ implode(', ', $defaults['missing_monitoring_periods']) }}. You can still save and preview this draft.
            </div>
        @endif

        <section class="rounded-2xl border border-gray-200 bg-gray-50 dark:border-slate-700 dark:bg-slate-800/50" x-data="{ open: false }">
            <button
                type="button"
                class="flex min-h-14 w-full items-center justify-between gap-4 rounded-2xl px-5 py-4 text-left font-bold text-gray-950 transition hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 dark:text-white dark:hover:bg-slate-800"
                @click="open = !open"
                :aria-expanded="open.toString()"
                aria-controls="terminal-monitoring-reference"
            >
                <span>
                    Earlier monitoring information
                    <span class="mt-1 block font-normal text-gray-600 dark:text-slate-300">Quarterly accomplishments are prefilled into the matching Work Plan objectives. Open this only when you need the detailed source records.</span>
                </span>
                <svg aria-hidden="true" class="h-6 w-6 shrink-0 transition-transform" :class="open && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div id="terminal-monitoring-reference" x-show="open" x-cloak class="border-t border-gray-200 px-5 pb-5 dark:border-slate-700">
                @forelse ($defaults['monitoring_reference'] ?? [] as $source)
                    <article class="mt-5 space-y-3 border-b border-gray-200 pb-5 last:border-0 dark:border-slate-700">
                        <h4 class="text-xl font-bold text-gray-950 dark:text-white">{{ $source['period'] }}</h4>
                        <p class="whitespace-pre-line">{{ $source['accomplishments'] }}</p>
                        @foreach ($source['work_plan'] ?? [] as $activity)
                            <p><strong>{{ $activity['activity'] ?? '' }}:</strong> {{ $activity['actual_accomplishment'] ?? '' }} {{ $activity['findings'] ?? '' }}</p>
                        @endforeach
                        @foreach ($source['budget_utilization'] ?? [] as $budget)
                            <p class="text-gray-600 dark:text-slate-300">{{ $budget['type'] ?? '' }} — {{ $budget['details'] ?? '' }}: ₱{{ number_format((float) ($budget['actual_amount'] ?? 0), 2) }}</p>
                        @endforeach
                    </article>
                @empty
                    <p class="pt-5">No submitted monitoring information is available yet.</p>
                @endforelse
            </div>
        </section>

        <section class="space-y-6" aria-labelledby="terminal-project-details">
            <div class="border-b border-slate-200 pb-3 dark:border-white">
                <p class="font-semibold uppercase tracking-[0.16em] text-red-700 dark:text-red-300">Sections I–II</p>
                <h3 id="terminal-project-details" class="text-2xl font-bold text-gray-950 dark:text-white">Cover and project details</h3>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-slate-700 dark:bg-slate-800/50">
                <p class="text-2xl font-bold text-gray-950 dark:text-white">{{ $terminal['project_title'] ?? $topic->title }}</p>
                <div class="mt-3 grid gap-2 text-gray-600 sm:grid-cols-2 dark:text-slate-300">
                    <p>Approved period: {{ $terminal['approved_start'] ?? 'Not recorded' }} – {{ $terminal['approved_end'] ?? 'Not recorded' }} ({{ $terminal['approved_duration_months'] ?? $topic->estimated_duration_months }} months)</p>
                    <p>Approved budget: ₱{{ number_format((float) ($terminal['approved_budget'] ?? $topic->estimated_budget), 2) }}</p>
                </div>
            </div>

            <section
                id="terminal-cover"
                data-terminal-cover-image
                class="scroll-mt-24 overflow-hidden rounded-3xl border border-gray-300 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900"
                x-data="{ previewUrl: @js($coverPreview), selected: @js($selectedCover), evidence: @js($evidence) }"
            >
                <div class="grid lg:grid-cols-[minmax(0,1.1fr)_minmax(320px,.9fr)]">
                    <div class="relative flex min-h-80 items-center justify-center overflow-hidden bg-slate-100 p-6 dark:bg-slate-950">
                        <img x-show="previewUrl" :src="previewUrl" :alt="$refs.coverCaption?.value || 'Terminal report cover poster preview'" class="max-h-[28rem] w-full rounded-xl object-contain shadow-2xl">
                        <div x-show="!previewUrl" class="max-w-sm text-center text-slate-500 dark:text-slate-300">
                            <svg aria-hidden="true" class="mx-auto h-14 w-14 text-red-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                            <p class="mt-4 text-2xl font-black tracking-tight text-slate-900 dark:text-white">Front-cover poster</p>
                            <p class="mt-2">Add the project poster, featured output, or strongest visual from the completed study.</p>
                        </div>
                    </div>
                    <div class="space-y-5 p-5 sm:p-6">
                        <div>
                            <h4 class="text-2xl font-bold text-gray-950 dark:text-white">Cover image</h4>
                            <p class="mt-1 text-gray-600 dark:text-slate-300">JPG or PNG, up to 10 MB. Landscape images work best on the cover.</p>
                        </div>
                        <label class="block font-bold">
                            Upload a new image
                            <input
                                type="file"
                                name="cover_image"
                                accept=".jpg,.jpeg,.png"
                                class="{{ $input }} cursor-pointer file:mr-4 file:rounded-lg file:border-0 file:bg-red-700 file:px-4 file:py-2 file:font-bold file:text-white hover:file:bg-red-800"
                                @change="if ($event.target.files[0]) { previewUrl = URL.createObjectURL($event.target.files[0]); selected = ''; }"
                            >
                        </label>
                        <label class="block font-bold">
                            Or reuse earlier evidence
                            <select
                                x-model="selected"
                                name="reuse_cover_image"
                                class="{{ $input }}"
                                @change="if (evidence[selected]) { previewUrl = evidence[selected].preview_url; $refs.coverCaption.value = evidence[selected].caption || $refs.coverCaption.value; $refs.coverCaption.dispatchEvent(new Event('input', { bubbles: true })); } else { previewUrl = ''; }"
                            >
                                <option value="">No earlier image selected</option>
                                @foreach ($evidence as $key => $photo)<option value="{{ $key }}">{{ $photo['label'] }}</option>@endforeach
                            </select>
                        </label>
                        <label class="block font-bold">
                            Cover caption and image description
                            <input x-ref="coverCaption" name="cover_image_caption" value="{{ $value('cover_image_caption') }}" maxlength="200" class="{{ $input }}" placeholder="Describe what the image shows">
                        </label>
                    </div>
                </div>
            </section>

            <div class="grid gap-5 sm:grid-cols-2">
                @foreach (['submission_date' => 'Submission date', 'implementation_start' => 'Actual start date', 'implementation_end' => 'Actual completion date'] as $field => $label)
                    <label class="font-bold">{{ $label }}<input type="date" name="{{ $field }}" value="{{ $value($field) }}" max="{{ now()->toDateString() }}" required class="{{ $input }}"></label>
                @endforeach
                <label class="font-bold">Tracking number <span class="font-normal text-gray-500">(optional)</span><input name="tracking_number" value="{{ $value('tracking_number') }}" maxlength="100" class="{{ $input }}"></label>
                <div x-data="{ spent: @js($value('terminal_data.total_expenditure')), budget: @js((float) ($terminal['approved_budget'] ?? $topic->estimated_budget)) }">
                    <label class="font-bold">Final total expenditure (₱)<input type="number" min="0" max="{{ (float) ($terminal['approved_budget'] ?? $topic->estimated_budget ?? 0) }}" step="0.01" name="terminal_data[total_expenditure]" x-model="spent" required class="{{ $input }}"><span class="mt-1 block text-xs font-normal text-gray-500 dark:text-slate-400">Must not exceed the approved project budget of ₱{{ number_format((float) ($terminal['approved_budget'] ?? $topic->estimated_budget ?? 0), 2) }}.</span></label>
                    <p class="mt-2 font-semibold text-red-700 dark:text-red-300" x-text="budget > 0 && spent !== '' ? 'Budget utilization: ' + (Number(spent) / budget * 100).toFixed(2) + '%' : 'Budget utilization: N/A'"></p>
                    <p class="mt-1 text-gray-500 dark:text-slate-400">Confirm the final total; do not add repeated cumulative monitoring amounts together.</p>
                </div>
                <label class="font-bold">Collaborating agency <span class="font-normal text-gray-500">(if any)</span><input name="terminal_data[collaborating_agency]" value="{{ $value('terminal_data.collaborating_agency') }}" maxlength="1000" placeholder="None" class="{{ $input }}"></label>
            </div>

            <div class="space-y-4" x-data="{ authors: @js($authors) }">
                <div>
                    <h4 class="text-xl font-bold text-gray-950 dark:text-white">Authors and prepared-by signatures</h4>
                    <p class="mt-1 text-gray-600 dark:text-slate-300">Names populate the cover, author list, and signature blocks. Leave signing dates blank until signed.</p>
                </div>
                <template x-for="(author, index) in authors" :key="index">
                    <article class="grid gap-4 rounded-2xl border border-gray-200 p-5 sm:grid-cols-2 lg:grid-cols-3 dark:border-slate-700">
                        <template x-for="field in ['name', 'rank', 'campus', 'college']" :key="field">
                            <label class="font-bold"><span x-text="field === 'rank' ? 'Academic rank' : field.charAt(0).toUpperCase() + field.slice(1)"></span><input :name="`terminal_data[authors][${index}][${field}]`" x-model="author[field]" :required="field === 'name'" maxlength="255" class="{{ $input }}"></label>
                        </template>
                        <label class="font-bold">Project role<select :name="`terminal_data[authors][${index}][role]`" x-model="author.role" class="{{ $input }}"><option>Project Leader</option><option>Project Staff</option></select></label>
                        <label class="font-bold">Date signed <span class="font-normal text-gray-500">(optional)</span><input type="date" :name="`terminal_data[authors][${index}][date_signed]`" x-model="author.date_signed" max="{{ now()->toDateString() }}" class="{{ $input }}"></label>
                        <button type="button" @click="authors.splice(index, 1); $dispatch('input')" :disabled="authors.length === 1" class="min-h-11 justify-self-start rounded-xl border border-red-200 px-4 py-2 font-bold text-red-700 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950">Remove author</button>
                    </article>
                </template>
                <button type="button" @click="authors.push({name:'',rank:'',campus:'',college:'',role:'Project Staff',date_signed:''}); $dispatch('input')" :disabled="authors.length >= 30" class="min-h-12 rounded-xl bg-red-700 px-5 py-3 font-bold text-white transition hover:bg-red-800 disabled:opacity-40">Add another author</button>
            </div>
        </section>

        <section id="terminal-accomplishments" data-approved-work-plan-objectives="{{ $objectivesFromWorkPlan ? 'true' : 'false' }}" class="scroll-mt-24 space-y-5" x-data="{ rows: @js($accomplishments) }" aria-labelledby="terminal-accomplishments-heading">
            <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-end sm:justify-between dark:border-slate-700">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.16em] text-red-700 dark:text-red-300">Section III</p>
                    <h3 id="terminal-accomplishments-heading" class="mt-1 text-2xl font-black tracking-tight text-slate-950 dark:text-white">Approved objectives and final outcomes</h3>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $objectivesFromWorkPlan ? 'Objectives and targets are locked to the approved Work Plan. Quarterly accomplishments are combined automatically; review and refine only the final outcome.' : 'No structured approved Work Plan was found, so objectives can be entered manually.' }}</p>
                </div>
                @if ($objectivesFromWorkPlan)
                    <span class="inline-flex w-fit items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-black text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span> Synced with Work Plan
                    </span>
                @endif
            </div>
            <template x-for="(row, index) in rows" :key="index">
                <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-5 py-3 dark:border-slate-700 dark:bg-slate-800">
                        <p class="text-sm font-black text-slate-950 dark:text-white" x-text="'Objective ' + (index + 1)"></p>
                        <span x-show="@js($objectivesFromWorkPlan)" class="text-xs font-bold text-emerald-700 dark:text-emerald-300">Approved source</span>
                    </div>
                    <div class="grid gap-4 p-5 lg:grid-cols-[1fr_1fr_1.25fr]">
                        <template x-for="field in ['objective','target','actual']" :key="field">
                            <label class="text-sm font-bold text-slate-700 dark:text-slate-200">
                                <span x-text="field === 'objective' ? 'Approved objective' : (field === 'target' ? 'Target output' : 'Final actual accomplishment')"></span>
                                <textarea :name="`accomplishments[${index}][${field}]`" x-model="row[field]" :maxlength="field === 'objective' ? 1000 : 2000" :readonly="@js($objectivesFromWorkPlan) && field !== 'actual'" required rows="6" class="{{ $input }}" :class="@js($objectivesFromWorkPlan) && field !== 'actual' ? 'cursor-not-allowed border-slate-200 bg-slate-50 text-slate-600 shadow-none dark:bg-slate-800' : ''"></textarea>
                                <span x-show="field === 'actual'" class="mt-1 block text-xs font-normal text-slate-500">Quarterly entries are prefilled with their reporting period. Edit this into the final concise result.</span>
                            </label>
                        </template>
                    </div>
                    @unless ($objectivesFromWorkPlan)
                        <div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800"><button type="button" @click="rows.splice(index, 1); $dispatch('input')" :disabled="rows.length === 1" class="min-h-10 rounded-xl border border-red-200 px-4 py-2 text-sm font-bold text-red-700 hover:bg-red-50 disabled:opacity-40 dark:border-red-900 dark:text-red-300">Remove objective</button></div>
                    @endunless
                </article>
            </template>
            @unless ($objectivesFromWorkPlan)
                <button type="button" @click="rows.push({objective:'',target:'',actual:''}); $dispatch('input')" :disabled="rows.length >= 30" class="min-h-12 rounded-xl bg-red-700 px-5 py-3 font-bold text-white transition hover:bg-red-800 disabled:opacity-40">Add another objective</button>
            @endunless
        </section>

        <section class="space-y-6" aria-labelledby="terminal-narrative">
            <div class="border-b border-slate-200 pb-3 dark:border-white">
                <p class="font-semibold uppercase tracking-[0.16em] text-red-700 dark:text-red-300">Sections IV–VII</p>
                <h3 id="terminal-narrative" class="text-2xl font-bold text-gray-950 dark:text-white">Research narrative</h3>
                <p class="mt-2 text-gray-600 dark:text-slate-300">Use the larger editor to format headings, emphasis, and lists. Figures and tables can be positioned after a paragraph in Methodology or Results and Discussion.</p>
            </div>
            <div x-data="{ abstract: @js(app(\App\Support\TerminalReportData::class)->plain($value('terminal_data.abstract'))) }">
                <label class="block text-xl font-bold text-gray-950 dark:text-white">IV. Abstract <span class="font-sans text-base font-normal text-gray-500">(200–250 words)</span><textarea name="terminal_data[abstract]" x-model="abstract" required rows="8" class="{{ $input }}"></textarea></label>
                <p class="mt-2 font-semibold" :class="(abstract.trim() ? abstract.trim().split(/\s+/).length : 0) >= 200 && (abstract.trim() ? abstract.trim().split(/\s+/).length : 0) <= 250 ? 'text-emerald-700' : 'text-amber-700'" x-text="(abstract.trim() ? abstract.trim().split(/\s+/).length : 0) + ' words'"></p>
            </div>
            @foreach (['introduction' => 'Introduction', 'rationale' => 'Rationale', 'terminal_data.literature_review' => 'Review of Literature', 'objectives' => 'General objective (optional)', 'methodology' => 'VI. Materials and Methods / Methodology', 'results_discussion' => 'VII. Results and Discussion', 'terminal_data.conclusions' => 'Conclusions', 'terminal_data.recommendations' => 'Recommendations', 'terminal_data.bibliography' => 'Bibliography'] as $field => $label)
                <label class="block text-xl font-bold text-gray-950 dark:text-white">
                    {{ $label }}
                    <textarea id="terminal-{{ str_replace('.', '-', $field) }}" name="{{ str_contains($field, '.') ? 'terminal_data['.substr($field, 14).']' : $field }}" data-semantic-editor data-semantic-editor-size="large" rows="9" maxlength="100000" @required($field !== 'objectives') class="{{ $input }}">{{ $value($field) }}</textarea>
                </label>
            @endforeach
        </section>

        <x-terminal-report-tables :tables="$value('terminal_data.tables', [])" />

        <section id="terminal-figures" class="scroll-mt-24 space-y-5" x-data="{ figureCount: @js($initialFigureCount), evidence: @js($evidence) }" aria-labelledby="terminal-figures-heading">
            <div class="flex flex-col gap-4 border-b border-slate-200 pb-4 sm:flex-row sm:items-end sm:justify-between dark:border-white">
                <div>
                    <p class="font-semibold uppercase tracking-[0.16em] text-red-700 dark:text-red-300">Visual evidence</p>
                    <h3 id="terminal-figures-heading" class="text-2xl font-bold text-gray-950 dark:text-white">Figures and inserted images</h3>
                    <p class="mt-2 max-w-3xl text-gray-600 dark:text-slate-300">Upload a JPG or PNG, or reuse evidence from an earlier report. Position 0 places the figure at the end of its section.</p>
                </div>
                <button type="button" @click="if (figureCount < 30) figureCount++" :disabled="figureCount >= 30" class="min-h-12 shrink-0 rounded-xl bg-red-700 px-5 py-3 font-bold text-white transition hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 disabled:opacity-40">＋ Add figure</button>
            </div>

            @foreach (range(1, 30) as $index)
                @php
                    $selectedFigure = $value('reuse_photo_'.$index);
                    $figurePreview = $evidence[$selectedFigure]['preview_url'] ?? '';
                @endphp
                <article
                    x-show="figureCount >= {{ $index }}"
                    x-cloak
                    class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900"
                    x-data="{ caption: @js($value('photo_caption_'.$index)), section: @js($value('photo_section_'.$index, 'results_discussion')), selected: @js($selectedFigure), previewUrl: @js($figurePreview) }"
                >
                    <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-5 py-4 dark:border-slate-700 dark:bg-slate-800">
                        <h4 class="text-xl font-bold text-gray-950 dark:text-white">Figure {{ $index }}</h4>
                        <span class="rounded-full bg-white px-3 py-1 font-semibold text-gray-600 ring-1 ring-gray-200 dark:bg-slate-900 dark:text-slate-300 dark:ring-slate-700">JPG / PNG</span>
                    </div>
                    <div class="grid gap-5 p-5 lg:grid-cols-[220px_1fr]">
                        <div class="flex min-h-48 items-center justify-center overflow-hidden rounded-xl border border-dashed border-gray-300 bg-gray-50 p-3 dark:border-slate-600 dark:bg-slate-800">
                            <img x-show="previewUrl" :src="previewUrl" :alt="caption || 'Figure preview'" class="max-h-52 w-full object-contain">
                            <p x-show="!previewUrl" class="text-center font-semibold text-gray-500 dark:text-slate-400">Image preview appears here</p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block font-bold">
                                Upload a new image
                                <input type="file" name="photo_{{ $index }}" accept=".jpg,.jpeg,.png" class="{{ $input }} cursor-pointer file:mr-4 file:rounded-lg file:border-0 file:bg-red-700 file:px-4 file:py-2 file:font-bold file:text-white hover:file:bg-red-800" @change="if ($event.target.files[0]) { previewUrl = URL.createObjectURL($event.target.files[0]); selected = ''; }">
                            </label>
                            <label class="block font-bold">
                                Or reuse earlier evidence
                                <select x-model="selected" name="reuse_photo_{{ $index }}" class="{{ $input }}" @change="if (evidence[selected]) { caption = evidence[selected].caption || ''; section = ['methodology','results_discussion'].includes(evidence[selected].section) ? evidence[selected].section : 'results_discussion'; previewUrl = evidence[selected].preview_url; } else { previewUrl = ''; }">
                                    <option value="">No earlier image selected</option>
                                    @foreach ($evidence as $key => $photo)<option value="{{ $key }}">{{ $photo['label'] }}</option>@endforeach
                                </select>
                            </label>
                            <label class="block font-bold sm:col-span-2">Caption and image description<input name="photo_caption_{{ $index }}" x-model="caption" maxlength="200" class="{{ $input }}" placeholder="Explain what this figure shows"></label>
                            <label class="block font-bold">Insert in section<select name="photo_section_{{ $index }}" x-model="section" class="{{ $input }}"><option value="methodology">Methodology</option><option value="results_discussion">Results and Discussion</option></select></label>
                            <label class="block font-bold">After paragraph<input type="number" min="0" max="1000" name="photo_after_paragraph_{{ $index }}" value="{{ $value('photo_after_paragraph_'.$index, 0) }}" class="{{ $input }}"><span class="mt-1 block font-normal text-gray-500">Use 0 to place it at the section end.</span></label>
                        </div>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="space-y-5" aria-labelledby="terminal-signatories">
            <div class="border-b border-slate-200 pb-3 dark:border-white">
                <p class="font-semibold uppercase tracking-[0.16em] text-red-700 dark:text-red-300">Final approval</p>
                <h3 id="terminal-signatories" class="text-2xl font-bold text-gray-950 dark:text-white">Review and approval signatories</h3>
                <p class="mt-2 text-gray-600 dark:text-slate-300">Confirm the name for each role. Selecting a name does not apply a signature or approve the report.</p>
            </div>
            @foreach (\App\Support\TerminalReportRules::SIGNATORY_ROLES as $key => [$group, $role])
                <div class="grid gap-4 rounded-2xl border border-gray-200 p-5 sm:grid-cols-2 dark:border-slate-700">
                    <label class="font-bold">{{ $group }} — {{ $role }}<input name="terminal_data[signatories][{{ $key }}][name]" value="{{ $value('terminal_data.signatories.'.$key.'.name') }}" list="terminal-signatories" maxlength="255" required class="{{ $input }}"></label>
                    <label class="font-bold">Date signed <span class="font-normal text-gray-500">(optional)</span><input type="date" name="terminal_data[signatories][{{ $key }}][date_signed]" value="{{ $value('terminal_data.signatories.'.$key.'.date_signed') }}" max="{{ now()->toDateString() }}" class="{{ $input }}"></label>
                </div>
            @endforeach
            <datalist id="terminal-signatories">@foreach ($defaults['signatory_options'] ?? [] as $name)<option value="{{ $name }}">@endforeach</datalist>
        </section>

        <x-monitoring-action-dock :fixed="$standalone">
            @if ($standalone)
                <x-back-link data-paper-cancel-exit href="{{ route('research.show', $topic) }}#project-monitoring">Exit monitoring</x-back-link>
            @endif
            <button type="button" @click="generatePreview" :disabled="previewLoading || submitting" class="min-h-12 rounded-xl border border-gray-300 px-6 py-3 font-bold text-gray-900 transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 disabled:opacity-50 dark:border-slate-600 dark:text-white dark:hover:bg-slate-800">Preview terminal report</button>
            <button type="submit" :disabled="previewLoading || submitting" class="min-h-12 rounded-xl bg-red-700 px-6 py-3 font-bold text-white transition hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 disabled:opacity-50">Prepare official PDF</button>
        </x-monitoring-action-dock>
        <p x-show="previewError" x-text="previewError" role="alert" class="rounded-xl bg-red-50 p-4 font-semibold text-red-700"></p>
        <section x-show="previewHtml" x-cloak x-ref="previewSection" class="space-y-3">
            <p>Review this draft preview. Prepare the official copy to confirm final pagination before submission.</p>
            <iframe x-ref="previewFrame" :srcdoc="previewHtml" @load="hydratePreview" title="Terminal report preview" class="h-[75vh] w-full rounded-2xl border border-gray-300 bg-white shadow-lg"></iframe>
        </section>
    </form>
</section>
