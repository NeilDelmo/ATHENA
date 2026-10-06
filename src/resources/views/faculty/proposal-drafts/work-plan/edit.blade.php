<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$paper['label']" subtitle="Build the official BatStateU-FO-RES-02 Work Plan from structured inputs.">
            <x-slot name="actions">
                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $workPlanDocument?->completed_at ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">{{ $workPlanDocument?->completed_at ? 'Complete' : ($workPlanDocument ? 'In progress' : 'Not started') }}</span>
                <x-back-link fixed data-paper-cancel-exit href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}#required-pdf-attachments">Exit editor</x-back-link>
            </x-slot>
        </x-page-header>
    </x-slot>

    @php
        $projectDetailsComplete = app(\App\Support\ProposalDraftReadiness::class)->projectDetailsAreComplete($proposalDraft);
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
        @focusin="focusWorkPlanEntry($event)"
        data-paper-editor
        data-paper-draft-save="true"
        data-work-plan-autosave="true"
        data-paper-project-details-complete="{{ $projectDetailsComplete ? 'true' : 'false' }}"
        data-paper-dirty="{{ $errors->any() ? 'true' : 'false' }}"
        data-paper-edit-url="{{ route('faculty.proposal-drafts.work-plan.edit', $proposalDraft) }}"
        data-paper-exit-url="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}#required-pdf-attachments"
        x-data="proposalDraftWorkPlan({
            initialEntries: @js($initialEntries),
            objectivesLinked: true,
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
                <div class="sm:col-span-2"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Title</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->project_title }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Duration</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->duration_months ? $proposalDraft->duration_months.' months' : 'Not provided' }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Planned Start</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->planned_start?->format('M j, Y') ?? 'Not provided' }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Planned End</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->planned_end?->format('M j, Y') ?? 'Not provided' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Leader / Prepared by</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->project_leader ?: 'Not provided' }}</dd></div>
            </dl>
        </section>

        <form data-paper-form data-work-plan-autosave-form x-ref="form" action="{{ route('faculty.proposal-drafts.work-plan.update', $proposalDraft) }}" method="POST" class="space-y-6" novalidate>
            @csrf
            @method('PUT')
            <input type="hidden" name="document_version" value="{{ old('document_version', $workPlanDocument?->lock_version ?? 0) }}">
            <input type="hidden" name="save_as_draft" value="0" data-paper-save-mode>

            <section data-revision-section="section-schedule" aria-labelledby="work-plan-objectives-heading" class="space-y-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h3 id="work-plan-objectives-heading" class="text-lg font-black text-gray-900">Objectives and Gantt schedule</h3>
                        <p class="mt-1 text-sm text-gray-500">Specific objectives come from your Detailed Proposal. Add activities, expected outputs, and active months for each one. Each month can belong to only one objective.</p>
                        <a href="{{ route('faculty.proposal-drafts.detailed-proposal.edit', $proposalDraft) }}" class="mt-2 inline-flex min-h-10 items-center text-sm font-semibold text-red-700 underline underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600">Edit objectives in Detailed Proposal</a>
                        <p class="mt-1 text-xs text-gray-400">The generated paper automatically expands each row to fit the longest objective, output, or activity text.</p>
                    </div>
                </div>

                @if ($linkedObjectives === [])
                    <p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">Save your specific objectives in the Detailed Proposal first. They will appear here automatically.</p>
                @endif

                <template x-for="(entry, index) in entries" :key="entry.id">
                    <article x-bind:data-repeatable-entry="`work-plan-entry-${entry.id}`" :data-work-plan-entry-id="entry.id" x-bind:class="isEntryExpanded(entry) ? 'border-red-200 bg-white' : 'border-gray-200 bg-gray-50'" class="work-plan-writing-entry rounded-xl border p-4 transition-colors sm:p-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-xs font-black uppercase tracking-wider text-gray-500">Objective <span x-text="index + 1"></span></p>
                                <h4 class="mt-1 truncate text-sm font-black text-gray-900" x-text="entrySummary(entry)"></h4>
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
                                <div class="work-plan-writing-objective">
                                    <label class="block text-xs font-black uppercase tracking-wider text-gray-600" x-bind:for="`objective-${entry.id}`">Objective from Detailed Proposal</label>
                                    <textarea x-bind:id="`objective-${entry.id}`" x-bind:name="`entries[${index}][objective]`" x-bind:data-work-plan-objective-input="entry.id" x-model="entry.objective" rows="4" readonly required class="mt-2 block w-full rounded-xl border-gray-200 bg-gray-50 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-800 dark:text-white"></textarea>
                                </div>
                                <div>
                                    <label class="block text-xs font-black uppercase tracking-wider text-gray-600" x-bind:for="`output-${entry.id}`">Expected Output <span class="text-red-600">Required</span></label>
                                    <textarea x-bind:id="`output-${entry.id}`" x-bind:name="`entries[${index}][expected_output]`" x-model="entry.expectedOutput" rows="4" maxlength="500" required class="mt-2 block w-full rounded-xl border-gray-300 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                                </div>
                                <div>
                                    <label class="block text-xs font-black uppercase tracking-wider text-gray-600" x-bind:for="`activity-${entry.id}`">Activities or Workplan <span class="text-red-600">Required</span></label>
                                    <textarea x-bind:id="`activity-${entry.id}`" x-bind:name="`entries[${index}][activity]`" x-model="entry.activity" rows="4" maxlength="1500" required class="mt-2 block w-full rounded-xl border-gray-300 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600"></textarea>
                                </div>
                            </div>

                            <fieldset x-bind:data-work-plan-schedule="entry.id" class="mt-5">
                                <legend class="text-xs font-black uppercase tracking-wider text-gray-600">Gantt Schedule <span class="text-red-600">Required</span></legend>
                                <p class="mt-2 text-xs leading-5 text-gray-500">Each 12-month block becomes a matching Attachment A year sheet. Months assigned to another objective are locked until they are removed from that objective.</p>
                                <div class="mt-3 grid gap-4">
                                    <template x-for="yearGroup in yearGroups" :key="yearGroup.year">
                                        <section class="rounded-xl border border-gray-200 bg-gray-50 p-3" x-bind:aria-label="`Year ${yearGroup.year} schedule`">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <p class="text-xs font-black uppercase tracking-wider text-gray-700" x-text="`Y${yearGroup.year}`"></p>
                                                <p class="text-[10px] font-semibold text-gray-500" x-text="`Project months ${yearGroup.months[0]}-${yearGroup.months[yearGroup.months.length - 1]}`"></p>
                                            </div>
                                            <div class="work-plan-writing-months mt-2 grid gap-2">
                                                <template x-for="month in yearGroup.months" :key="month">
                                                    <label
                                                        class="relative flex cursor-pointer flex-col items-center justify-center rounded-xl border px-2 py-2.5 text-xs font-black transition focus-within:ring-2 focus-within:ring-red-600 focus-within:ring-offset-2"
                                                        x-bind:class="entry.months.includes(month) ? 'border-red-600 bg-red-50 text-red-700' : (isMonthSelectable(index, month) ? 'border-gray-200 bg-white text-gray-600 hover:border-gray-300' : 'cursor-not-allowed border-amber-200 bg-amber-50 text-amber-700')"
                                                        x-bind:title="monthSelectionTitle(index, month)"
                                                    >
                                                        <input type="checkbox" class="sr-only" x-bind:name="`entries[${index}][months][]`" x-bind:value="month" x-model.number="entry.months" x-bind:disabled="!isMonthSelectable(index, month)" x-on:change="clearMonthError(index)">
                                                        <span x-text="`M${localMonthNumber(month)}`"></span>
                                                        <span x-show="yearGroup.year > 1" class="mt-0.5 text-[9px] font-semibold text-gray-500" x-text="`Project M${month}`"></span>
                                                        <span x-show="monthOwnerLabel(index, month)" x-text="monthOwnerLabel(index, month)" class="mt-0.5 text-[9px] font-bold uppercase tracking-wide"></span>
                                                    </label>
                                                </template>
                                            </div>
                                        </section>
                                    </template>
                                </div>
                                <p x-show="monthErrorIndexes.includes(index)" x-cloak class="mt-2 text-xs font-semibold text-red-600">Select at least one month for this objective.</p>
                                <p x-show="monthConflictIndexes.includes(index)" x-cloak class="mt-2 text-xs font-semibold text-red-600">This objective shares a month with an earlier objective. Remove the duplicate month.</p>
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

        <div x-show="previewError || downloadError" x-cloak role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><span x-text="previewError || downloadError"></span></div>

        </div>
        <x-proposal-paper-preview
            panel-id="work-plan-preview-panel"
            preview-label="Work Plan preview"
            frame-title="Attachment A Work Plan preview"
        />
        </div>
        <button type="button" x-show="!previewPaneOpen" x-cloak @click="showProposalPreview()" aria-controls="work-plan-preview-panel" :aria-expanded="previewPaneOpen" class="proposal-writing-preview-launcher">Preview paper</button>
    </div>
</x-app-layout>
