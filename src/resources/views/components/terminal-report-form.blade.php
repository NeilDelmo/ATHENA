@props(['topic', 'draft' => null, 'defaults' => [], 'evidence' => [], 'standalone' => false, 'draftRecoveryState' => null])
@php
    $restoreTerminalInput = old('report_type') === 'terminal';
    $old = fn ($key, $fallback = null) => $restoreTerminalInput ? old($key, $fallback) : $fallback;
    $draftVersion = (int) $old('draft_version', $draft?->lock_version ?? 0);
    $draftId = (int) $old('draft_id', $draft?->id ?? 0);
    $data = app(\App\Support\TerminalReportData::class)->normalize($topic, [...array_replace($defaults, $draft?->source_data ?? []), 'accomplishments' => $old('accomplishments', $draft?->source_data['accomplishments'] ?? $defaults['accomplishments'] ?? [])]);
    $terminal = $data['terminal_data'] ?? [];
    $value = fn ($key, $fallback = '') => $old($key, data_get($data, $key, $fallback));
    $input = 'mt-2 block min-h-12 w-full rounded-xl border-gray-300 bg-white px-3 py-2.5 text-base text-gray-950 font-normal shadow-sm transition placeholder:text-gray-400 focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-800 dark:text-white';
    $authors = $value('terminal_data.authors', []);
    $accomplishments = $data['accomplishments'] ?: [['objective' => '', 'target' => '', 'actual' => '']];
    $selectedCover = $value('reuse_cover_image');
    $savedImages = app(\App\Services\ReportFigureDraftService::class)->displayImages($topic, $data);
    $coverPreview = $savedImages['cover_image']['preview_url'] ?? $evidence[$selectedCover]['preview_url'] ?? '';
    $usedFigureSlots = collect(range(1, 30))
        ->filter(fn (int $index): bool => filled($value('saved_photo_'.$index)) || filled($value('reuse_photo_'.$index)) || filled($value('photo_caption_'.$index)))
        ->max();
    $initialFigureCount = max(1, (int) ($usedFigureSlots ?: 1));
    $missingPeriods = collect($defaults['missing_report_periods'] ?? []);
    $objectivesFromWorkPlan = (bool) ($defaults['objectives_from_work_plan'] ?? false);
    $schedule = app(\App\Services\MonitoringQuarterService::class);
    $submissionOpen = $schedule->canSubmitTerminal($topic);
    $submissionOpensAt = $schedule->terminalOpensAt($topic)->format('M j, Y');
@endphp

<section
    class="bg-slate-50/70 p-4 dark:bg-slate-950 sm:p-6 lg:p-8"
    data-proposal-paper-workspace
    data-monitoring-paper-workspace
    data-narrative-progress-autosave="true"
    data-document-writing-workspace
    @focusin="focusWritingField($event)"
    x-data="narrativeProgressReportForm({writingTools: true, previewUrl: @js(route('project-narrative-reports.preview', $topic)), previewTitle: 'Terminal Report preview', draftSaveUrl: @js(route('project-narrative-reports.draft', $topic)), initialDraftVersion: @js($draftVersion), csrfToken: @js(csrf_token()), submissionOpen: @js($submissionOpen), submissionOpensAt: @js($submissionOpensAt)})"
>
    <x-proposal-collaboration-monitor
        :loaded-version="$draftVersion"
        :state-url="route('project-report-drafts.edit-state', ['topic' => $topic, 'kind' => 'terminal', 'reportingDate' => 'terminal', 'source' => 0])"
        :state-scope="$draftRecoveryState['scope'] ?? null"
        :reload-url="route('project-narrative-reports.create', ['topic' => $topic, 'report_type' => 'terminal'])"
        label="Terminal Report"
        report-recovery
    />

    <x-monitoring-writing-toolbar
        :topic="$topic"
        report-label="Terminal Report"
        figures-id="terminal-figures"
        tables-id="terminal-tables"
        :formatting="true"
        panel-id="terminal-preview-{{ $topic->id }}"
        form-id="terminal-form-{{ $topic->id }}"
        save-method="saveNarrativeDraft"
        :standalone="$standalone"
        class="mb-4"
    />

    <div class="proposal-preview-workspace proposal-writing-columns" :class="{ 'proposal-writing-preview-hidden': !previewPaneOpen }" @resize.window.debounce.150ms="resizeProposalPaperPreview()">
    <div class="proposal-edit-pane min-w-0" :inert="previewFullscreen">
    <form
        id="terminal-form-{{ $topic->id }}"
        x-ref="form"
        data-narrative-progress-autosave-form
        data-assistant-report-type="terminal-report"
        data-assistant-report-key="terminal-report:topic:{{ $topic->id }}"
        method="POST"
        action="{{ route('project-narrative-reports.prepare', $topic) }}"
        enctype="multipart/form-data"
        class="mx-auto max-w-6xl space-y-8 text-base leading-7 text-gray-800 dark:text-slate-200"
        @submit="submitNarrativeReport($event)"
    >
        @csrf
        <input type="hidden" name="report_type" value="terminal">
        <input type="hidden" name="draft_version" value="{{ $draftVersion }}">
        <input type="hidden" name="draft_id" value="{{ $draftId }}">
        <input type="hidden" name="recovery_scope" value="{{ $draftRecoveryState['scope'] ?? '' }}">
        <x-assistant-report-editor :context="['report_type' => 'terminal-report', 'topic_id' => $topic->id]" />
        <p data-report-submission-lock x-show="!submissionOpen" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-base font-semibold text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">Fill and save this draft now. PDF preparation and submission open {{ $submissionOpensAt }}.</p>

        @if ($errors->narrativeProgress->any())
            <div role="alert" class="rounded-2xl border border-red-200 bg-red-50 p-5 text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-100">
                <p class="font-semibold">Please review these fields:</p>
                <ul class="mt-2 list-disc space-y-1 pl-6">@foreach ($errors->narrativeProgress->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        @if ($missingPeriods->isNotEmpty())
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-950 dark:border-amber-900 dark:bg-amber-950">
                Before preparing the official copy, complete {{ $missingPeriods->implode(', ') }}. You can still save and preview this draft.
            </div>
        @endif

        <section class="rounded-2xl border border-gray-200 bg-gray-50 dark:border-slate-700 dark:bg-slate-800/50" x-data="{ open: false }">
            <button
                type="button"
                class="flex min-h-14 w-full items-center justify-between gap-4 rounded-2xl px-5 py-4 text-left font-semibold text-gray-950 transition hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 dark:text-white dark:hover:bg-slate-800"
                @click="open = !open"
                :aria-expanded="open.toString()"
                aria-controls="terminal-monitoring-reference"
            >
                <span>Earlier monitoring reports</span>
                <svg aria-hidden="true" class="h-6 w-6 shrink-0 transition-transform" :class="open && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div id="terminal-monitoring-reference" x-show="open" x-cloak class="border-t border-gray-200 px-5 pb-5 dark:border-slate-700">
                @forelse ($defaults['monitoring_reference'] ?? [] as $source)
                    <article class="mt-5 space-y-3 border-b border-gray-200 pb-5 last:border-0 dark:border-slate-700">
                        <h4 class="text-base font-semibold text-gray-950 dark:text-white">{{ $source['period'] }}</h4>
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
            <div class="border-b border-slate-200 pb-3 dark:border-slate-700">
                <h3 id="terminal-project-details" class="text-lg font-semibold text-gray-950 dark:text-white">Cover and project details</h3>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-slate-700 dark:bg-slate-800/50">
                <p class="text-lg font-semibold text-gray-950 dark:text-white">{{ $terminal['project_title'] ?? $topic->title }}</p>
                <div class="mt-3 grid gap-2 text-gray-600 sm:grid-cols-2 dark:text-slate-300">
                    <p>Approved period: {{ $terminal['approved_start'] ?? 'Not recorded' }} – {{ $terminal['approved_end'] ?? 'Not recorded' }} ({{ $terminal['approved_duration_months'] ?? $topic->estimated_duration_months }} months)</p>
                    <p>Approved budget: ₱{{ number_format((float) ($terminal['approved_budget'] ?? $topic->estimated_budget), 2) }}</p>
                </div>
            </div>

            <section
                id="terminal-cover"
                data-terminal-cover-image
                data-report-image
                class="scroll-mt-24 overflow-hidden rounded-3xl border border-gray-300 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900"
                x-data="{ previewUrl: @js($coverPreview), selected: @js($selectedCover), evidence: @js($evidence), imageId: @js($value('saved_cover_image')), imageName: @js($savedImages['cover_image']['name'] ?? '') }"
                @report-image-saved="if (previewUrl.startsWith('blob:')) URL.revokeObjectURL(previewUrl); imageId = $event.detail.id; imageName = $event.detail.name; previewUrl = $event.detail.preview_url"
            >
                <input type="hidden" name="saved_cover_image" x-model="imageId">
                <div class="grid" :class="{ 'lg:grid-cols-[minmax(0,1.1fr)_minmax(320px,.9fr)]': !previewPaneOpen }">
                    <div class="relative flex min-h-80 items-center justify-center overflow-hidden bg-slate-100 p-6 dark:bg-slate-950">
                        <img x-show="previewUrl" :src="previewUrl" :alt="$refs.coverCaption?.value || 'Terminal report cover poster preview'" class="max-h-[28rem] w-full rounded-xl object-contain">
                        <div x-show="!previewUrl" class="max-w-sm text-center text-slate-500 dark:text-slate-300">
                            <svg aria-hidden="true" class="mx-auto h-14 w-14 text-red-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                            <p class="mt-4 text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Front-cover poster</p>
                            <p class="mt-2">Add the project poster, featured output, or strongest visual from the completed study.</p>
                        </div>
                    </div>
                    <div class="space-y-5 p-5 sm:p-6">
                        <div>
                            <h4 class="text-lg font-semibold text-gray-950 dark:text-white">Cover image</h4>
                            <p class="mt-1 text-gray-600 dark:text-slate-300">JPG or PNG, up to 10 MB. Landscape images work best on the cover.</p>
                        </div>
                        <label class="block min-w-0 font-semibold">
                            Upload a new image
                            <input
                                type="file"
                                name="cover_image"
                                accept=".jpg,.jpeg,.png"
                                class="{{ $input }} cursor-pointer file:mr-4 file:rounded-lg file:border-0 file:bg-red-700 file:px-4 file:py-2 file:font-semibold file:text-white hover:file:bg-red-800"
                                @change="if ($event.target.files[0]) { if (previewUrl.startsWith('blob:')) URL.revokeObjectURL(previewUrl); previewUrl = URL.createObjectURL($event.target.files[0]); selected = ''; imageId = ''; }"
                            >
                            <span role="status" class="mt-2 block text-sm font-normal text-gray-600 [overflow-wrap:anywhere] dark:text-slate-300" x-text="imageId ? 'Saved with draft: ' + imageName : (previewUrl && !selected ? 'Image waiting to save…' : '')"></span>
                        </label>
                        <button type="button" x-show="previewUrl" @click="if (previewUrl.startsWith('blob:')) URL.revokeObjectURL(previewUrl); previewUrl = ''; imageId = ''; selected = ''; $el.closest('[data-report-image]').querySelector('input[type=file]').value = ''; $nextTick(() => $dispatch('input'))" class="secondary-action">Remove image</button>
                        <label class="block font-semibold">
                            Or reuse earlier evidence
                            <select
                                x-model="selected"
                                name="reuse_cover_image"
                                class="{{ $input }}"
                                @change="imageId = ''; $el.closest('[data-report-image]').querySelector('input[type=file]').value = ''; if (evidence[selected]) { previewUrl = evidence[selected].preview_url; $refs.coverCaption.value = evidence[selected].caption || $refs.coverCaption.value; $refs.coverCaption.dispatchEvent(new Event('input', { bubbles: true })); } else { previewUrl = ''; }"
                            >
                                <option value="">No earlier image selected</option>
                                @foreach ($evidence as $key => $photo)<option value="{{ $key }}">{{ $photo['label'] }}</option>@endforeach
                            </select>
                        </label>
                        <label class="block font-semibold">
                            Cover caption and image description
                            <input x-ref="coverCaption" name="cover_image_caption" value="{{ $value('cover_image_caption') }}" maxlength="200" class="{{ $input }}" placeholder="Describe what the image shows">
                        </label>
                    </div>
                </div>
            </section>

            <div class="grid gap-5 sm:grid-cols-2">
                @foreach (['submission_date' => 'Submission date', 'implementation_start' => 'Actual start date', 'implementation_end' => 'Actual completion date'] as $field => $label)
                    <label class="font-semibold">{{ $label }}<input type="date" name="{{ $field }}" value="{{ $value($field) }}" max="{{ now()->toDateString() }}" required class="{{ $input }}"></label>
                @endforeach
                <label class="font-semibold">Tracking number <span class="font-normal text-gray-500">(optional)</span><input name="tracking_number" value="{{ $value('tracking_number') }}" maxlength="100" class="{{ $input }}"></label>
                <div x-data="{ spent: @js($value('terminal_data.total_expenditure')), budget: @js((float) ($terminal['approved_budget'] ?? $topic->estimated_budget)) }">
                    <label class="font-semibold">Final total expenditure (₱)<input type="number" min="0" max="{{ (float) ($terminal['approved_budget'] ?? $topic->estimated_budget ?? 0) }}" step="0.01" name="terminal_data[total_expenditure]" x-model="spent" required class="{{ $input }}"><span class="mt-1 block text-xs font-normal text-gray-500 dark:text-slate-400">Must not exceed the approved project budget of ₱{{ number_format((float) ($terminal['approved_budget'] ?? $topic->estimated_budget ?? 0), 2) }}.</span></label>
                    <p class="mt-2 font-semibold text-red-700 dark:text-red-300" x-text="budget > 0 && spent !== '' ? 'Budget utilization: ' + (Number(spent) / budget * 100).toFixed(2) + '%' : 'Budget utilization: N/A'"></p>
                    <p class="mt-1 text-gray-500 dark:text-slate-400">Confirm the final total; do not add repeated cumulative monitoring amounts together.</p>
                </div>
                <label class="font-semibold">Collaborating agency <span class="font-normal text-gray-500">(if any)</span><input name="terminal_data[collaborating_agency]" value="{{ $value('terminal_data.collaborating_agency') }}" maxlength="1000" placeholder="None" class="{{ $input }}"></label>
            </div>

            <div class="space-y-4" x-data="{ authors: @js($authors) }">
                <div>
                    <h4 class="text-base font-semibold text-gray-950 dark:text-white">Authors and prepared-by signatures</h4>
                    <p class="mt-1 text-gray-600 dark:text-slate-300">Names populate the cover, author list, and signature blocks. Leave signing dates blank until signed.</p>
                </div>
                <template x-for="(author, index) in authors" :key="index">
                    <article class="grid gap-4 rounded-2xl border border-gray-200 p-5 sm:grid-cols-2 lg:grid-cols-3 dark:border-slate-700">
                        <template x-for="field in ['name', 'rank', 'campus', 'college']" :key="field">
                            <label class="font-semibold"><span x-text="field === 'rank' ? 'Academic rank' : field.charAt(0).toUpperCase() + field.slice(1)"></span><input :name="`terminal_data[authors][${index}][${field}]`" x-model="author[field]" :required="field === 'name'" maxlength="255" class="{{ $input }}"></label>
                        </template>
                        <label class="font-semibold">Project role<select :name="`terminal_data[authors][${index}][role]`" x-model="author.role" class="{{ $input }}"><option>Project Leader</option><option>Project Staff</option></select></label>
                        <label class="font-semibold">Date signed <span class="font-normal text-gray-500">(optional)</span><input type="date" :name="`terminal_data[authors][${index}][date_signed]`" x-model="author.date_signed" max="{{ now()->toDateString() }}" class="{{ $input }}"></label>
                        <button type="button" @click="authors.splice(index, 1); $dispatch('input')" :disabled="authors.length === 1" class="min-h-11 justify-self-start rounded-xl border border-red-200 px-4 py-2 font-semibold text-red-700 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950">Remove author</button>
                    </article>
                </template>
                <button type="button" @click="authors.push({name:'',rank:'',campus:'',college:'',role:'Project Staff',date_signed:''}); $dispatch('input')" :disabled="authors.length >= 30" class="min-h-12 rounded-xl bg-red-700 px-5 py-3 font-semibold text-white transition hover:bg-red-800 disabled:opacity-40">Add another author</button>
            </div>
        </section>

        <section id="terminal-accomplishments" data-approved-work-plan-objectives="{{ $objectivesFromWorkPlan ? 'true' : 'false' }}" class="scroll-mt-24 space-y-5" x-data="{ rows: @js($accomplishments) }" aria-labelledby="terminal-accomplishments-heading">
            <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-end sm:justify-between dark:border-slate-700">
                <div>
                    <h3 id="terminal-accomplishments-heading" class="mt-1 text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">Approved objectives and final outcomes</h3>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $objectivesFromWorkPlan ? 'Objectives and targets are locked to the approved Work Plan. Quarterly accomplishments are combined automatically; review and refine only the final outcome.' : 'No structured approved Work Plan was found, so objectives can be entered manually.' }}</p>
                </div>
            </div>
            <template x-for="(row, index) in rows" :key="index">
                <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-5 py-3 dark:border-slate-700 dark:bg-slate-800">
                        <p class="text-sm font-semibold text-slate-950 dark:text-white" x-text="'Objective ' + (index + 1)"></p>
                    </div>
                    <div class="grid gap-4 p-5" :class="{ 'lg:grid-cols-[1fr_1fr_1.25fr]': !previewPaneOpen }">
                        <template x-for="field in ['objective','target','actual']" :key="field">
                            <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                <span x-text="field === 'objective' ? 'Approved objective' : (field === 'target' ? 'Target output' : 'Final actual accomplishment')"></span>
                                <textarea :name="`accomplishments[${index}][${field}]`" x-model="row[field]" :maxlength="field === 'objective' ? 1000 : 2000" :readonly="@js($objectivesFromWorkPlan) && field !== 'actual'" required rows="6" class="{{ $input }}" :class="@js($objectivesFromWorkPlan) && field !== 'actual' ? 'cursor-not-allowed border-slate-200 bg-slate-50 text-slate-600 shadow-none dark:bg-slate-800' : ''"></textarea>
                                <span x-show="field === 'actual'" class="mt-1 block text-xs font-normal text-slate-500">Quarterly entries are prefilled with their reporting period. Edit this into the final concise result.</span>
                            </label>
                        </template>
                    </div>
                    @unless ($objectivesFromWorkPlan)
                        <div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800"><button type="button" @click="rows.splice(index, 1); $dispatch('input')" :disabled="rows.length === 1" class="min-h-10 rounded-xl border border-red-200 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:opacity-40 dark:border-red-900 dark:text-red-300">Remove objective</button></div>
                    @endunless
                </article>
            </template>
            @unless ($objectivesFromWorkPlan)
                <button type="button" @click="rows.push({objective:'',target:'',actual:''}); $dispatch('input')" :disabled="rows.length >= 30" class="min-h-12 rounded-xl bg-red-700 px-5 py-3 font-semibold text-white transition hover:bg-red-800 disabled:opacity-40">Add another objective</button>
            @endunless
        </section>

        <section class="space-y-6" aria-labelledby="terminal-narrative">
            <div class="border-b border-slate-200 pb-3 dark:border-slate-700">
                <h3 id="terminal-narrative" class="text-lg font-semibold text-gray-950 dark:text-white">Research narrative</h3>
            </div>
            <div x-data="{ abstract: @js(app(\App\Support\TerminalReportData::class)->plain($value('terminal_data.abstract'))) }">
                <label class="block text-base font-semibold text-gray-950 dark:text-white">IV. Abstract <span class="font-sans text-base font-normal text-gray-500">(200–250 words)</span><textarea name="terminal_data[abstract]" x-model="abstract" required rows="8" class="{{ $input }}"></textarea></label>
                <p class="mt-2 font-semibold" :class="(abstract.trim() ? abstract.trim().split(/\s+/).length : 0) >= 200 && (abstract.trim() ? abstract.trim().split(/\s+/).length : 0) <= 250 ? 'text-gray-600 dark:text-slate-300' : 'text-amber-700'" x-text="(abstract.trim() ? abstract.trim().split(/\s+/).length : 0) + ' words'"></p>
            </div>
            @foreach (['introduction' => 'Introduction', 'rationale' => 'Rationale', 'terminal_data.literature_review' => 'Review of Literature', 'objectives' => 'General objective (optional)', 'methodology' => 'VI. Materials and Methods / Methodology', 'results_discussion' => 'VII. Results and Discussion', 'terminal_data.conclusions' => 'Conclusions', 'terminal_data.recommendations' => 'Recommendations', 'terminal_data.bibliography' => 'Bibliography'] as $field => $label)
                <label class="block text-base font-semibold text-gray-950 dark:text-white">
                    {{ $label }}
                    <textarea aria-label="{{ $label }}" id="terminal-{{ str_replace('.', '-', $field) }}" name="{{ str_contains($field, '.') ? 'terminal_data['.substr($field, 14).']' : $field }}" @unless ($field === 'objectives' && ($defaults['objectives_from_proposal'] ?? false)) data-semantic-editor data-semantic-editor-size="large" @endunless @readonly($field === 'objectives' && ($defaults['objectives_from_proposal'] ?? false)) rows="9" maxlength="100000" @required($field !== 'objectives') class="{{ $input }}">{{ $field === 'objectives' && ($defaults['objectives_from_proposal'] ?? false) ? $defaults['objectives'] : $value($field) }}</textarea>
                </label>
            @endforeach
        </section>

        <x-terminal-report-tables :tables="$value('terminal_data.tables', [])" />

        <section id="terminal-figures" class="scroll-mt-24 space-y-5" x-data="{ figureCount: @js($initialFigureCount), evidence: @js($evidence) }" aria-labelledby="terminal-figures-heading">
            <div class="flex flex-col gap-4 border-b border-slate-200 pb-4 sm:flex-row sm:items-end sm:justify-between dark:border-slate-700">
                <div>
                    <h3 id="terminal-figures-heading" class="text-lg font-semibold text-gray-950 dark:text-white">Figures and inserted images</h3>
                    <p class="mt-2 max-w-3xl text-gray-600 dark:text-slate-300">Upload a JPG or PNG, or reuse evidence from an earlier report. Position 0 places the figure at the end of its section.</p>
                </div>
                <button type="button" @click="if (figureCount < 30) figureCount++" :disabled="figureCount >= 30" class="min-h-12 shrink-0 rounded-xl bg-red-700 px-5 py-3 font-semibold text-white transition hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 disabled:opacity-40">Add figure</button>
            </div>

            @foreach (range(1, 30) as $index)
                @php
                    $selectedFigure = $value('reuse_photo_'.$index);
                    $figurePreview = $savedImages['photo_'.$index]['preview_url'] ?? $evidence[$selectedFigure]['preview_url'] ?? '';
                @endphp
                <article
                    data-report-image
                    x-show="figureCount >= {{ $index }}"
                    x-cloak
                    class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900"
                    x-data="{ caption: @js($value('photo_caption_'.$index)), section: @js($value('photo_section_'.$index, 'results_discussion')), selected: @js($selectedFigure), previewUrl: @js($figurePreview), imageId: @js($value('saved_photo_'.$index)), imageName: @js($savedImages['photo_'.$index]['name'] ?? '') }"
                    @report-image-saved="if (previewUrl.startsWith('blob:')) URL.revokeObjectURL(previewUrl); imageId = $event.detail.id; imageName = $event.detail.name; previewUrl = $event.detail.preview_url"
                >
                    <input type="hidden" name="saved_photo_{{ $index }}" x-model="imageId">
                    <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-5 py-4 dark:border-slate-700 dark:bg-slate-800">
                        <h4 class="text-base font-semibold text-gray-950 dark:text-white">Figure {{ $index }}</h4>
                        <span class="rounded-full bg-white px-3 py-1 font-semibold text-gray-600 ring-1 ring-gray-200 dark:bg-slate-900 dark:text-slate-300 dark:ring-slate-700">JPG / PNG</span>
                    </div>
                    <div class="grid gap-5 p-5" :class="{ 'lg:grid-cols-[220px_1fr]': !previewPaneOpen }">
                        <div class="flex min-h-48 items-center justify-center overflow-hidden rounded-xl border border-dashed border-gray-300 bg-gray-50 p-3 dark:border-slate-600 dark:bg-slate-800">
                            <img x-show="previewUrl" :src="previewUrl" :alt="caption || 'Figure preview'" class="max-h-52 w-full object-contain">
                            <p x-show="!previewUrl" class="text-center font-semibold text-gray-500 dark:text-slate-400">Image preview appears here</p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block min-w-0 font-semibold">
                                Upload a new image
                                <input type="file" name="photo_{{ $index }}" accept=".jpg,.jpeg,.png" class="{{ $input }} cursor-pointer file:mr-4 file:rounded-lg file:border-0 file:bg-red-700 file:px-4 file:py-2 file:font-semibold file:text-white hover:file:bg-red-800" @change="if ($event.target.files[0]) { if (previewUrl.startsWith('blob:')) URL.revokeObjectURL(previewUrl); previewUrl = URL.createObjectURL($event.target.files[0]); selected = ''; imageId = ''; }">
                                <span role="status" class="mt-2 block text-sm font-normal text-gray-600 [overflow-wrap:anywhere] dark:text-slate-300" x-text="imageId ? 'Saved with draft: ' + imageName : (previewUrl && !selected ? 'Image waiting to save…' : '')"></span>
                                <button type="button" x-show="previewUrl" @click="if (previewUrl.startsWith('blob:')) URL.revokeObjectURL(previewUrl); previewUrl = ''; imageId = ''; selected = ''; $el.closest('[data-report-image]').querySelector('input[type=file]').value = ''; $nextTick(() => $dispatch('input'))" class="secondary-action mt-2">Remove image</button>
                            </label>
                            <label class="block font-semibold">
                                Or reuse earlier evidence
                                <select x-model="selected" name="reuse_photo_{{ $index }}" class="{{ $input }}" @change="imageId = ''; $el.closest('[data-report-image]').querySelector('input[type=file]').value = ''; if (evidence[selected]) { caption = evidence[selected].caption || ''; section = ['methodology','results_discussion'].includes(evidence[selected].section) ? evidence[selected].section : 'results_discussion'; previewUrl = evidence[selected].preview_url; } else { previewUrl = ''; }">
                                    <option value="">No earlier image selected</option>
                                    @foreach ($evidence as $key => $photo)<option value="{{ $key }}">{{ $photo['label'] }}</option>@endforeach
                                </select>
                            </label>
                            <label class="block font-semibold sm:col-span-2">Caption and image description<input name="photo_caption_{{ $index }}" x-model="caption" maxlength="200" class="{{ $input }}" placeholder="Explain what this figure shows"></label>
                            <label class="block font-semibold">Insert in section<select name="photo_section_{{ $index }}" x-model="section" class="{{ $input }}"><option value="methodology">Methodology</option><option value="results_discussion">Results and Discussion</option></select></label>
                            <label class="block font-semibold">After paragraph<input type="number" min="0" max="1000" name="photo_after_paragraph_{{ $index }}" value="{{ $value('photo_after_paragraph_'.$index, 0) }}" class="{{ $input }}"><span class="mt-1 block font-normal text-gray-500">Use 0 to place it at the section end.</span></label>
                        </div>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="space-y-5" aria-labelledby="terminal-signatories-heading">
            <div class="border-b border-slate-200 pb-3 dark:border-slate-700">
                <h3 id="terminal-signatories-heading" class="text-lg font-semibold text-gray-950 dark:text-white">Review and approval signatories</h3>
                <p class="mt-2 text-gray-600 dark:text-slate-300">Confirm the name for each role. Selecting a name does not apply a signature or approve the report.</p>
            </div>
            @foreach (\App\Support\TerminalReportRules::SIGNATORY_ROLES as $key => [$group, $role])
                <div class="grid gap-4 rounded-2xl border border-gray-200 p-5 sm:grid-cols-2 dark:border-slate-700">
                    @php($defaultName = \App\Support\TerminalReportData::defaultSignatoryNames()[$key] ?? null)
                    <label class="font-semibold">{{ $group }} — {{ $role }}<input name="terminal_data[signatories][{{ $key }}][name]" value="{{ $defaultName ?? $value('terminal_data.signatories.'.$key.'.name') }}" @if ($defaultName) readonly @else list="terminal-signatories" @endif maxlength="255" required class="{{ $input }}"></label>
                    <label class="font-semibold">Date signed <span class="font-normal text-gray-500">(optional)</span><input type="date" name="terminal_data[signatories][{{ $key }}][date_signed]" value="{{ $value('terminal_data.signatories.'.$key.'.date_signed') }}" max="{{ now()->toDateString() }}" class="{{ $input }}"></label>
                </div>
            @endforeach
            <datalist id="terminal-signatories">@foreach ($defaults['signatory_options'] ?? [] as $name)<option value="{{ $name }}">@endforeach</datalist>
        </section>

    </form>
    </div>
    <x-proposal-paper-preview panel-id="terminal-preview-{{ $topic->id }}" preview-label="Terminal Report preview" frame-title="Terminal report preview" />
    </div>
</section>
