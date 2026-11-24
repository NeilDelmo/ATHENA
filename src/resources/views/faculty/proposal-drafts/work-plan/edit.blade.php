<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$paper['label']" :subtitle="$proposalDraft->project_title">
            <x-slot name="actions">
                <span data-work-plan-completion-status role="status" class="text-base font-semibold text-slate-700 dark:text-slate-200">{{ $workPlanComplete ? 'Complete' : ($workPlanDocument ? 'In progress' : 'Not started') }}</span>
                <x-back-link fixed data-paper-cancel-exit href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}#required-pdf-attachments">Back to attachments</x-back-link>
            </x-slot>
        </x-page-header>
    </x-slot>

    @php
        $initialEntries = $sourceData['entries'] ?? [];
        $sampleDefinition = config('proposal_samples.'.$paper['sample_slug']);
        $sampleAvailable = is_array($sampleDefinition)
            && isset($sampleDefinition['path'])
            && \Illuminate\Support\Facades\Storage::disk('local')->exists($sampleDefinition['path']);
    @endphp

    <div
        class="work-plan-writing-workspace mx-auto w-full space-y-6 px-4 py-8 sm:px-6 lg:px-8"
        data-proposal-paper-workspace
        data-work-plan-workspace
        data-proposal-draft-id="{{ $proposalDraft->id }}"
        data-proposal-paper-slug="work-plan"
        @athena-apply-proposal-change.window="handleAssistantPaperChange($event)"
        @focusin="focusWorkPlanEntry($event)"
        data-paper-editor
        data-proposal-paper="work-plan"
        data-proposal-readiness-url="{{ route('faculty.proposal-drafts.review', $proposalDraft) }}"
        data-paper-draft-save="true"
        data-work-plan-autosave="true"
        data-paper-project-details-complete="{{ $projectDetailsComplete ? 'true' : 'false' }}"
        data-paper-dirty="{{ $errors->any() ? 'true' : 'false' }}"
        data-paper-edit-url="{{ route('faculty.proposal-drafts.work-plan.edit', $proposalDraft) }}"
        data-paper-exit-url="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}#required-pdf-attachments"
        x-data="proposalDraftWorkPlan({
            proposalDraftId: @js($proposalDraft->id),
            draftVersion: @js($proposalDraft->lock_version),
            initialEntries: @js($initialEntries),
            proposalActivities: @js($proposalActivities),
            objectivesLinked: true,
            recheckCompletion: @js($workPlanDocument !== null && ($workPlanDocument->completed_at === null || ! $workPlanComplete) && $errors->isEmpty()),
            maxEntries: @js(config('work_plan.max_objectives')),
            durationMonths: @js($proposalDraft->duration_months ?: 12),
            previewUrl: @js(route('faculty.proposal-drafts.work-plan.preview', $proposalDraft)),
            downloadUrl: @js(route('faculty.proposal-drafts.work-plan.download', $proposalDraft)),
            updateUrl: @js(route('faculty.proposal-drafts.work-plan.update', $proposalDraft)),
            csrfToken: @js(csrf_token()),
            revisionUploadUrl: @js($proposalDraft->topic_id ? route('faculty.proposal-drafts.revision-files.store', $proposalDraft) : null),
            revisionDocumentType: @js($paper['document_type']),
            revisionAttachmentLabel: @js($paper['label']),
            revisionReviewUrl: @js($proposalDraft->topic_id ? route('faculty.topics.revision', $proposalDraft->topic_id).'#review-and-submit' : null),
            revisionTarget: @js(request()->query('revision_target')),
        })"
    >
        <x-proposal-pdf-preparation :proposal-draft="$proposalDraft" />
        @if (request()->has('proposal_requirement'))
            <x-proposal-submission-readiness :url="route('faculty.proposal-drafts.review', $proposalDraft)" />
        @endif

        @if (session('success'))
            <x-proposal-alert>{{ session('success') }}</x-proposal-alert>
        @endif

        @if ($errors->any())
            <x-proposal-alert type="error">
                <p class="font-bold">The Work Plan could not be saved.</p>
                <ul class="mt-1 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-proposal-alert>
        @endif

        <div x-show="validationMessage" x-cloak role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800" x-text="validationMessage"></div>
        <div x-show="proposalChangeNotice || proposalChangeError" x-cloak class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-900" aria-live="polite" data-proposal-change-status>
            <p x-show="proposalChangeNotice" class="text-slate-700 dark:text-slate-200" x-text="proposalChangeNotice"></p>
            <p x-show="proposalChangeError" role="alert" class="text-red-800 dark:text-red-200" x-text="proposalChangeError"></p>
        </div>

        <x-proposal-revision-context :proposal-draft="$proposalDraft" :document-type="$paper['document_type']" />
        <x-proposal-autosave-status />
        <x-proposal-collaboration-monitor
            :loaded-version="(int) old('document_version', $workPlanDocument?->lock_version ?? 0)"
            :state-url="route('faculty.proposal-drafts.edit-state', [$proposalDraft, $paper['document_type'], 0])"
            :reload-url="route('faculty.proposal-drafts.work-plan.edit', $proposalDraft)"
            :label="$paper['label']"
        />

        <x-work-plan-writing-toolbar />
        <div class="proposal-preview-workspace proposal-writing-columns" :class="{ 'proposal-writing-preview-hidden': !previewPaneOpen }" @resize.window.debounce.150ms="resizeProposalPaperPreview()">
        <div class="proposal-edit-pane space-y-6" :inert="previewFullscreen" aria-label="Work Plan editing form">
            <p data-proposal-required-legend class="text-xs text-gray-600 dark:text-slate-400"><span class="font-bold text-red-700 dark:text-red-400" aria-hidden="true">*</span> Required for submission.</p>

        @unless ($projectDetailsComplete)
            <div role="alert" class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                <p class="font-black">Complete Project Details first</p>
                <p class="mt-1 leading-6">Project title, duration, planned dates, and project leader are required before Attachment A can be previewed or generated. You can still save your progress as a draft.</p>
                <a href="{{ route('faculty.proposal-drafts.details.edit', $proposalDraft) }}" class="mt-3 inline-flex rounded-xl bg-amber-900 px-4 py-2.5 text-xs font-bold text-white focus:outline-none focus:ring-2 focus:ring-amber-900 focus:ring-offset-2">Complete Project Details</a>
            </div>
        @endunless

        <section data-revision-section="section-project-information" data-revision-shared-summary class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div><h3 class="text-base font-black text-gray-900">Shared project information</h3><p class="mt-1 text-xs text-gray-500">Edit these values from Project Details; they are applied automatically to the paper.</p></div>
                <div class="flex gap-2">
                    @if ($sampleAvailable)<a href="{{ route('proposal-samples.show', $paper['sample_slug']) }}" target="_blank" rel="noopener" class="inline-flex rounded-xl border border-gray-300 px-3 py-2 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:ring-offset-2">View sample</a>@endif
                    <a href="{{ route('faculty.proposal-drafts.details.edit', $proposalDraft) }}" class="inline-flex rounded-xl border border-red-200 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">Edit details</a>
                </div>
            </div>
            <dl class="mt-5 grid gap-4 border-t border-gray-100 pt-5 sm:grid-cols-2">
                <div class="sm:col-span-2"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Title <x-required-mark /></dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->project_title }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Duration <x-required-mark /></dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->duration_months ? $proposalDraft->duration_months.' months' : 'Not provided' }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Planned Start <x-required-mark /></dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->planned_start?->format('M j, Y') ?? 'Not provided' }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Planned End <x-required-mark /></dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->planned_end?->format('M j, Y') ?? 'Not provided' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Leader <x-required-mark /></dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->project_leader ?: 'Not provided' }}</dd></div>
            </dl>
        </section>

        <form data-paper-form data-work-plan-autosave-form x-ref="form" action="{{ route('faculty.proposal-drafts.work-plan.update', $proposalDraft) }}" method="POST" class="space-y-6" novalidate>
            @csrf
            @method('PUT')
            <input type="hidden" name="document_version" value="{{ old('document_version', $workPlanDocument?->lock_version ?? 0) }}">
            <input type="hidden" name="save_as_draft" value="0" data-paper-save-mode>

            <section data-revision-section="section-schedule" aria-labelledby="work-plan-objectives-heading" class="space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                    <div class="min-w-0 flex-1 basis-72">
                        <h3 id="work-plan-objectives-heading" class="text-xl font-black text-gray-900 dark:text-white">Objectives and Gantt schedule</h3>
                        <p class="mt-1 text-base text-gray-600 dark:text-slate-400">For each objective, list its outputs and activities, then select the active months.</p>
                    </div>
                    <a href="{{ route('faculty.proposal-drafts.detailed-proposal.edit', $proposalDraft) }}" class="secondary-action text-base" aria-label="Edit objectives in Detailed Proposal">Edit objectives</a>
                </div>

                @if ($proposalOutputs !== [])
                    <details class="group">
                        <summary class="secondary-action w-full cursor-pointer list-none justify-start gap-3 px-4 text-left text-base [&::-webkit-details-marker]:hidden">
                            <svg class="h-5 w-5 shrink-0 group-open:rotate-90" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="m9 6 6 6-6 6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <span>Proposal outputs</span>
                        </summary>
                        <dl class="mt-2 space-y-3 rounded-xl border border-gray-200 bg-white p-4 text-base dark:border-slate-700 dark:bg-slate-800">
                            @foreach ($proposalOutputs as $category => $outputs)
                                <div>
                                    <dt class="font-semibold text-gray-900 dark:text-white">{{ config('detailed_proposal.expected_outputs.'.$category) }}</dt>
                                    @foreach ($outputs as $output)
                                        <dd class="mt-1 whitespace-pre-line text-gray-600 dark:text-slate-300">{{ $output['description'] }}</dd>
                                    @endforeach
                                </div>
                            @endforeach
                        </dl>
                    </details>
                @endif

                @if ($linkedObjectives === [])
                    <p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">Save your specific objectives in the Detailed Proposal first. They will appear here automatically.</p>
                @endif

                <template x-for="(entry, index) in entries" :key="entry.id">
                    <article x-bind:data-repeatable-entry="`work-plan-entry-${entry.id}`" :data-work-plan-entry-id="entry.id" x-bind:class="isEntryExpanded(entry) ? 'border-red-200 bg-white' : 'border-gray-200 bg-gray-50'" class="work-plan-writing-entry rounded-xl border p-4 transition-colors sm:p-5">
                        <template x-if="objectivesLinked">
                            <input type="hidden" x-bind:name="`entries[${index}][objective]`" x-bind:value="entry.objective">
                        </template>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <p class="flex flex-wrap items-baseline gap-x-2 text-sm text-gray-600 dark:text-slate-400">
                                    <span class="font-semibold">Objective <span x-text="index + 1"></span></span>
                                    <span x-show="objectivesLinked" class="border-l border-gray-300 pl-2 dark:border-slate-600">From Detailed Proposal</span>
                                </p>
                                <h4 x-bind:id="objectivesLinked ? `objective-${entry.id}` : null" tabindex="-1" class="mt-1 whitespace-pre-line break-words text-lg font-semibold text-gray-900 dark:text-white" x-text="entrySummary(entry)"></h4>
                                <p x-show="!isEntryExpanded(entry)" x-cloak class="mt-1 text-xs text-gray-500" x-text="entryScheduleSummary(entry)"></p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <button type="button" x-on:click="toggleEntry(entry)" x-bind:aria-expanded="isEntryExpanded(entry)" x-bind:aria-controls="`work-plan-editor-${entry.id}`" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-bold text-gray-700 hover:border-red-300 hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600">
                                    <span x-show="isEntryExpanded(entry)">Collapse</span>
                                    <span x-show="!isEntryExpanded(entry)" x-cloak>Edit</span>
                                </button>
                            </div>
                        </div>

                        <div x-bind:id="`work-plan-editor-${entry.id}`" x-show="isEntryExpanded(entry)" x-cloak x-transition class="mt-5">
                            <div class="work-plan-writing-fields grid gap-4">
                                <template x-if="!objectivesLinked">
                                    <div>
                                        <label class="block text-base font-semibold text-gray-900 dark:text-white" x-bind:for="`objective-${entry.id}`">Objective <x-required-mark /></label>
                                        <textarea x-bind:id="`objective-${entry.id}`" x-bind:name="`entries[${index}][objective]`" x-bind:data-work-plan-objective-input="entry.id" x-model="entry.objective" rows="3" required class="mt-2 block w-full rounded-lg border-gray-300 text-base text-gray-900 focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-800 dark:text-white"></textarea>
                                    </div>
                                </template>
                                <div class="min-w-0">
                                    <label class="block text-base font-semibold text-gray-900 dark:text-white" x-bind:for="`output-${entry.id}`">Expected outputs <x-required-mark /></label>
                                    <p x-bind:id="`output-help-${entry.id}`" class="text-sm text-gray-600 dark:text-slate-400">Enter this objective’s deliverables, one per line.</p>
                                    <textarea x-bind:id="`output-${entry.id}`" x-bind:name="`entries[${index}][expected_output]`" x-bind:aria-describedby="`output-help-${entry.id}`" x-model="entry.expectedOutput" rows="3" maxlength="500" required class="mt-2 block w-full rounded-lg border-gray-300 text-base text-gray-900 focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-800 dark:text-white"></textarea>
                                </div>
                                <div class="min-w-0">
                                    <label class="block text-base font-semibold text-gray-900 dark:text-white" x-bind:for="`activity-${entry.id}`">Activities <x-required-mark /></label>
                                    <p x-bind:id="`activity-help-${entry.id}`" class="text-sm text-gray-600 dark:text-slate-400">
                                        <span x-show="entry.proposalActivity && entry.activity === entry.proposalActivity" x-cloak>Matches the Detailed Proposal methods. You can edit these here.</span>
                                        <span x-show="!entry.proposalActivity || entry.activity !== entry.proposalActivity">Enter one activity per line.</span>
                                    </p>
                                    <textarea x-bind:id="`activity-${entry.id}`" x-bind:name="`entries[${index}][activity]`" x-bind:aria-describedby="`activity-help-${entry.id}`" x-model="entry.activity" rows="3" maxlength="{{ config('detailed_proposal.maximum_narrative_length') }}" required class="mt-2 block w-full rounded-lg border-gray-300 text-base text-gray-900 focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-800 dark:text-white"></textarea>
                                    <button type="button" x-show="entry.proposalActivity && entry.activity !== entry.proposalActivity" x-cloak x-on:click="useProposalMethods(entry)" class="mt-2 secondary-action">Replace activities with proposal methods</button>
                                </div>
                            </div>

                            <fieldset x-bind:data-work-plan-schedule="entry.id" x-bind:aria-describedby="`schedule-help-${entry.id}`" class="mt-5">
                                <legend class="text-base font-semibold text-gray-900 dark:text-white">Gantt Schedule <x-required-mark /></legend>
                                <p x-bind:id="`schedule-help-${entry.id}`" class="mt-2 text-base text-gray-600 dark:text-slate-400">Click a month to set the end. The range starts at M1 unless you choose a different start month.</p>
                                <template x-for="month in entry.months" :key="month">
                                    <input type="hidden" x-bind:name="`entries[${index}][months][]`" x-bind:value="month">
                                </template>
                                <template x-if="isEntryExpanded(entry)">
                                    <div class="mt-3 grid gap-4">
                                        <div class="grid grid-cols-2 gap-3">
                                            <label class="grid min-w-0 gap-1.5 text-base font-semibold text-gray-700 dark:text-slate-300">
                                                <span>Start month</span>
                                                <select data-work-plan-range-start x-model.number="entry.rangeStart" x-init="$nextTick(() => { $el.value = entry.rangeStart })" x-on:input.stop x-on:change.stop="updateMonthRange(index)" class="block min-h-11 w-full min-w-0 rounded-lg border-gray-300 bg-white text-base font-normal text-gray-900 focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                                                    <option value="" disabled>Select</option>
                                                    <template x-for="month in months" :key="month">
                                                        <option x-bind:value="month" x-text="monthRangeLabel(month)"></option>
                                                    </template>
                                                </select>
                                            </label>
                                            <label class="grid min-w-0 gap-1.5 text-base font-semibold text-gray-700 dark:text-slate-300">
                                                <span>End month</span>
                                                <select x-model.number="entry.rangeEnd" x-init="$nextTick(() => { $el.value = entry.rangeEnd })" x-on:input.stop x-on:change.stop="selectScheduleEnd(index, Number($event.target.value))" class="block min-h-11 w-full min-w-0 rounded-lg border-gray-300 bg-white text-base font-normal text-gray-900 focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                                                    <option value="" disabled>Select</option>
                                                    <template x-for="month in months" :key="month">
                                                        <option x-bind:value="month" x-bind:disabled="month < entry.rangeStart" x-text="monthRangeLabel(month)"></option>
                                                    </template>
                                                </select>
                                            </label>
                                        </div>
                                        <div x-show="scheduleHasGaps(entry)" x-cloak class="flex flex-wrap items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                                            <p>This saved schedule has gaps. Choose a continuous range before preparing Attachment A.</p>
                                            <button type="button" class="secondary-action" x-on:click="fillScheduleGaps(index)">Fill gaps</button>
                                        </div>
                                        <template x-for="yearGroup in yearGroups" :key="yearGroup.year">
                                            <section class="rounded-xl border border-gray-200 bg-gray-50 p-3" x-bind:aria-label="`Year ${yearGroup.year} schedule`">
                                                <div class="flex flex-wrap items-center justify-between gap-2">
                                                    <p class="text-xs font-black uppercase tracking-wider text-gray-700" x-text="`Y${yearGroup.year}`"></p>
                                                    <p class="text-[10px] font-semibold text-gray-500" x-text="`Project months ${yearGroup.months[0]}-${yearGroup.months[yearGroup.months.length - 1]}`"></p>
                                                </div>
                                                <div class="work-plan-writing-months mt-2 grid gap-2">
                                                    <template x-for="month in yearGroup.months" :key="month">
                                                        <button
                                                            type="button"
                                                            class="relative flex flex-col items-center justify-center rounded-xl border px-2 py-2.5 text-xs font-black transition focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                                                            x-bind:class="entry.months.includes(month) ? 'border-red-600 bg-red-50 text-red-700' : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'"
                                                            x-bind:data-work-plan-month="month"
                                                            x-bind:aria-pressed="entry.months.includes(month)"
                                                            x-bind:disabled="!isMonthSelectable(index, month)"
                                                            x-bind:title="monthSelectionTitle(index, month)"
                                                            x-on:click="selectScheduleEnd(index, month)"
                                                        >
                                                            <span x-text="`M${localMonthNumber(month)}`"></span>
                                                            <span x-show="yearGroup.year > 1" class="mt-0.5 text-[9px] font-semibold text-gray-500" x-text="`Project M${month}`"></span>
                                                        </button>
                                                    </template>
                                                </div>
                                            </section>
                                        </template>
                                        <button type="button" x-show="entry.rangeStart || entry.rangeEnd" x-cloak class="secondary-action justify-self-start" x-on:click="clearSchedule(index)">Clear schedule</button>
                                    </div>
                                </template>
                                <p x-show="monthErrorIndexes.includes(index) && entry.months.length === 0" x-cloak class="mt-2 text-sm font-semibold text-red-600">Select at least one month for this objective.</p>
                            </fieldset>
                        </div>
                    </article>
                </template>

            </section>

            <x-proposal-signatory-summary :proposal-draft="$proposalDraft" paper="work_plan" />

            <noscript>
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">Save Work Plan</button>
            </noscript>
        </form>

        <div x-show="downloadError" x-cloak role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" x-text="downloadError"></div>

        </div>
        <x-proposal-paper-preview
            panel-id="work-plan-preview-panel"
            preview-label="Work Plan preview"
            frame-title="Attachment A Work Plan preview"
        />
        </div>
    </div>
</x-app-layout>
