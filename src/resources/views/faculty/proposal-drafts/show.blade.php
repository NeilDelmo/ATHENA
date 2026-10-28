<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$proposalDraft->project_title" :subtitle="'Last saved '.$proposalDraft->updated_at->diffForHumans().($proposalDraft->researchManual ? ' · Research manual: '.$proposalDraft->researchManual->title.' ('.$proposalDraft->researchManual->edition.')' : '')">
            <x-slot name="actions">
                <x-back-link fixed href="{{ route('faculty.proposal-drafts.index') }}">Back to saved drafts</x-back-link>
                <button type="button" data-proposal-readiness-continue data-url="{{ route('faculty.proposal-drafts.review', $proposalDraft) }}" class="inline-flex min-h-11 w-full shrink-0 items-center justify-center rounded-lg bg-red-600 px-4 py-2.5 text-base font-bold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:hover:bg-red-500 sm:w-auto">{{ auth()->user()->can('submit', $proposalDraft) ? 'Review & turn in' : 'Review working draft' }}</button>
            </x-slot>
        </x-page-header>
    </x-slot>

    @php
        $submittedTopic = $proposalDraft->topic;
        $canEditDraft = auth()->user()->can('update', $proposalDraft);
        $editableChecklist = $checklist->reject(fn (array $item): bool => $item['paper']['mode'] === 'automatic');
        $automaticChecklist = $checklist->filter(fn (array $item): bool => $item['paper']['mode'] === 'automatic');
        $completedPaperCount = $editableChecklist
            ->filter(fn (array $item): bool => $item['complete'] && ! $item['needs_attention'])
            ->count();
        $paperCount = $editableChecklist->count();
        $initialProposalTab = in_array(session('proposal_tab'), ['details', 'attachments', 'collaborators'], true)
            ? session('proposal_tab')
            : null;
        $memberInvitationHasErrors = $errors->hasAny(['email', 'name']);
        $teamRoleHasErrors = $errors->hasAny(['member_id', 'project_role']);
    @endphp

    @if ($submittedTopic && $submittedTopic->status !== 'revision_requested')
        <section data-submitted-package-workspace class="rounded-xl border border-blue-200 bg-blue-50 p-5 dark:border-blue-900 dark:bg-blue-950/30">
            <h2 class="font-bold text-gray-950 dark:text-white">{{ $submittedTopic->canUpdateBeforeReview() ? 'Update submitted proposal' : 'Proposal under review' }}</h2>
            <p class="mt-2 text-sm leading-6 text-gray-700 dark:text-slate-200">{{ $submittedTopic->canUpdateBeforeReview() ? 'These changes are private until you submit the next version. Your earlier submitted PDFs stay in Versions. Editing closes when the Research Head opens the submission.' : 'The Research Head has opened this proposal. Editing and submission are locked until revisions are requested. Your saved working copy is preserved.' }}</p>
            <a href="{{ route('topics.show', $submittedTopic) }}" class="mt-3 inline-flex min-h-11 items-center font-semibold text-red-700 hover:underline dark:text-red-300">View submitted versions</a>
        </section>
    @endif

    <div
        data-workspace-palette="red-black-white"
        class="mx-auto max-w-7xl space-y-5 px-4 py-6 sm:px-6 lg:px-8"
        x-data="{
            activeProposalTab: @js(($memberInvitationHasErrors || $teamRoleHasErrors) ? 'collaborators' : $initialProposalTab) || (
                window.location.hash === '#required-pdf-attachments'
                    ? 'attachments'
                    : window.location.hash === '#proposal-collaborators'
                        ? 'collaborators'
                        : 'details'
            ),
        }"
        @hashchange.window="activeProposalTab = window.location.hash === '#required-pdf-attachments' ? 'attachments' : window.location.hash === '#proposal-collaborators' ? 'collaborators' : 'details'"
    >
        @if (session('success'))
            <x-proposal-alert>{{ session('success') }}</x-proposal-alert>
        @endif

        @if (session('warning'))
            <x-proposal-alert type="warning">{{ session('warning') }}</x-proposal-alert>
        @endif

        @if (session('workload_warning'))
            <x-proposal-alert type="warning">{{ session('workload_warning') }}</x-proposal-alert>
        @endif

        @if ($errors->any())
            <x-proposal-alert type="error">
                <p class="font-bold">Some information still needs attention.</p>
                <ul class="mt-1 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-proposal-alert>
        @endif


        <x-proposal-submission-readiness :url="route('faculty.proposal-drafts.review', $proposalDraft)" :blockers="$readinessBlockers" :ready-to-prepare="$readyToPrepare" :ready-to-submit="$readyToSubmit" />
        <x-proposal-pdf-preparation :proposal-draft="$proposalDraft" />

        <x-budget-consistency-warning :comparison="$budgetConsistency" :proposal-draft="$proposalDraft" />

        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white p-1.5 shadow-sm dark:border-slate-800 dark:bg-slate-900" role="tablist" aria-label="Proposal workspace sections">
            <nav class="flex min-w-max gap-1">
                <button id="project-details-tab-button" type="button" role="tab" aria-controls="project-details-tab" :aria-selected="activeProposalTab === 'details'" @click="window.location.hash = 'project-details'" :class="activeProposalTab === 'details' ? 'bg-gray-950 text-white shadow-sm dark:bg-white dark:text-gray-950' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-950 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white'" class="flex min-h-11 items-center gap-2 rounded-lg px-4 py-2.5 text-base font-bold transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5.25A2.25 2.25 0 0 1 6.25 3h11.5A2.25 2.25 0 0 1 20 5.25v13.5A2.25 2.25 0 0 1 17.75 21H6.25A2.25 2.25 0 0 1 4 18.75V5.25Z" /><path stroke-linecap="round" d="M8 8h8M8 12h8M8 16h5" /></svg>
                    Project Details
                </button>
                <button id="required-pdf-attachments-tab-button" type="button" role="tab" aria-controls="required-pdf-attachments-tab" :aria-selected="activeProposalTab === 'attachments'" @click="window.location.hash = 'required-pdf-attachments'" :class="activeProposalTab === 'attachments' ? 'bg-gray-950 text-white shadow-sm dark:bg-white dark:text-gray-950' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-950 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white'" class="flex min-h-11 items-center gap-2 rounded-lg px-4 py-2.5 text-base font-bold transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h10.5A2.25 2.25 0 0 1 19.5 6v14.25H4.5V6a2.25 2.25 0 0 1 2.25-2.25Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 8.25h7.5M8.25 12h7.5M8.25 15.75h4.5" /></svg>
                    Required PDF attachments
                </button>
                <button id="proposal-collaborators-tab-button" type="button" role="tab" aria-controls="proposal-collaborators-tab" :aria-selected="activeProposalTab === 'collaborators'" @click="window.location.hash = 'proposal-collaborators'" :class="activeProposalTab === 'collaborators' ? 'bg-gray-950 text-white shadow-sm dark:bg-white dark:text-gray-950' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-950 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white'" class="flex min-h-11 items-center gap-2 rounded-lg px-4 py-2.5 text-base font-bold transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9A2.25 2.25 0 0 1 5.25 16.5v-9A2.25 2.25 0 0 1 7.5 5.25h9a2.25 2.25 0 0 1 2.25 2.25v9a2.25 2.25 0 0 1-2.25 2.25Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6M12 9v6" /></svg>
                    Project team
                </button>
            </nav>
        </div>

        <section id="project-details-tab" x-show="activeProposalTab === 'details'" x-cloak role="tabpanel" aria-labelledby="project-details-tab-button">
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <div data-paper-editor data-proposal-paper="details" data-proposal-readiness-url="{{ route('faculty.proposal-drafts.review', $proposalDraft) }}" data-paper-dirty="{{ $errors->any() ? 'true' : 'false' }}" data-project-details-autosave="true" data-paper-edit-url="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" data-paper-exit-url="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" x-data="proposalDraftProjectDetails({ initialDuration: @js($initialDuration), initialStart: @js($initialPlannedStart), initialEnd: @js($initialPlannedEnd), initialComplete: @js($projectDetailsComplete), autoSave: true })" class="space-y-4">
                    <x-paper-editor-submit-status />
                    <x-proposal-autosave-status />
                    <x-proposal-collaboration-monitor
                        :loaded-version="(int) old('draft_version', $proposalDraft->lock_version)"
                        :state-url="route('faculty.proposal-drafts.edit-state', [$proposalDraft, 'details', 0])"
                        :reload-url="route('faculty.proposal-drafts.show', $proposalDraft)"
                        label="project details"
                    />

                    <section aria-labelledby="project-details-heading" class="overflow-visible rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="border-b border-gray-200 px-5 py-5 dark:border-slate-800 sm:px-6">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 id="project-details-heading" class="text-lg font-black text-gray-950 dark:text-white">Project Details</h3>
                                <span role="status" aria-live="polite" :class="projectDetailsComplete ? 'border-gray-300 bg-gray-100 text-gray-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200' : 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200'" x-text="projectDetailsComplete ? 'Complete' : 'Incomplete'" class="rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-wider">{{ $projectDetailsComplete ? 'Complete' : 'Incomplete' }}</span>
                            </div>
                            <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-slate-400">Shared information used automatically in Attachment A and the submitted proposal record.</p>
                        </div>


                        <form data-paper-form @if ($canEditDraft) data-project-details-autosave-form @endif action="{{ route('faculty.proposal-drafts.details.update', $proposalDraft) }}" method="POST" class="px-5 pb-5 pt-6 sm:px-6 sm:pb-6">
                            @csrf
                            @method('PUT')
                            <fieldset @disabled(! $canEditDraft) @if (! $canEditDraft) inert data-proposal-editing-locked @endif class="space-y-6">
                            <input type="hidden" name="draft_version" value="{{ old('draft_version', $proposalDraft->lock_version) }}">

                            <div>
                                <label for="project_title" class="block text-xs font-black uppercase tracking-wider text-gray-600 dark:text-slate-300">Project Title <span class="text-red-600">Required</span></label>
                                <input id="project_title" name="project_title" type="text" value="{{ old('project_title', $proposalDraft->project_title) }}" maxlength="255" required autofocus class="mt-2 block w-full rounded-lg border-gray-300 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-800 dark:text-white">
                                @error('project_title')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div class="grid gap-5 md:grid-cols-3">
                                <div>
                                    <label for="duration_months" class="block text-xs font-black uppercase tracking-wider text-gray-600 dark:text-slate-300">Total Duration <span class="text-red-600">Required</span></label>
                                    <div class="relative mt-2"><input id="duration_months" name="duration_months" type="number" min="1" max="{{ config('work_plan.max_duration_months') }}" x-model.number="durationMonths" required class="block w-full rounded-lg border-gray-300 pr-20 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-800 dark:text-white"><span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-xs font-bold text-gray-500 dark:text-slate-400">months</span></div>
                                    <p class="mt-2 text-[11px] text-gray-500 dark:text-slate-400">Attachment A adds one M1-M12 sheet for each 12-month project period.</p>
                                    @error('duration_months')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <x-proposal-planned-date field="start" :research-call="$proposalDraft->researchCall" :minimum-date="$minimumProjectDate" />
                                <x-proposal-planned-date field="end" :research-call="$proposalDraft->researchCall" :minimum-date="$minimumProjectDate" />
                            </div>

                            <div>
                                <label for="project_leader" class="block text-xs font-black uppercase tracking-wider text-gray-600 dark:text-slate-300">Project Leader <span class="text-red-600">Required</span></label>
                                <input id="project_leader" name="project_leader" type="text" list="proposal-workspace-people" value="{{ old('project_leader', $proposalDraft->project_leader ?: $proposalDraft->owner->name) }}" maxlength="120" required class="mt-2 block w-full rounded-lg border-gray-300 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-800 dark:text-white">
                                <datalist id="proposal-workspace-people">@foreach ($workspacePeople as $workspacePerson)<option value="{{ $workspacePerson['name'] }}">{{ $workspacePerson['email'] }}</option>@endforeach</datalist>
                                @error('project_leader')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <noscript>
                                <div class="flex justify-end border-t border-gray-200 pt-5 dark:border-slate-800">
                                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">Save project details</button>
                                </div>
                            </noscript>
                            </fieldset>
                        </form>
                    </section>
                </div>

                <aside>
                    <section aria-labelledby="workspace-overview-heading" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="border-b border-gray-200 px-4 py-4 dark:border-slate-800">
                            <h3 id="workspace-overview-heading" class="text-sm font-black text-gray-950 dark:text-white">Proposal summary</h3>
                        </div>
                        <div aria-labelledby="package-progress-heading" class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h4 id="package-progress-heading" class="text-sm font-black text-gray-950 dark:text-white">Project progress</h4>
                                <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-slate-400">{{ $completedPaperCount }} of {{ $paperCount }} proposal papers ready</p>
                            </div>
                            <span class="shrink-0 rounded-full border px-2 py-1 text-[9px] font-black {{ $completedPaperCount === $paperCount && $projectDetailsComplete ? 'border-gray-300 bg-gray-100 text-gray-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200' : 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200' }}">{{ $completedPaperCount === $paperCount && $projectDetailsComplete ? 'Ready' : 'In progress' }}</span>
                        </div>
                        <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-slate-800" aria-hidden="true">
                            <div class="h-full rounded-full bg-red-600" style="width: {{ $paperCount === 0 ? 0 : ($completedPaperCount / $paperCount) * 100 }}%"></div>
                        </div>
                        </div>

                        <div aria-labelledby="recent-activity-heading" class="border-t border-gray-200 p-4 dark:border-slate-800">
                        <div class="flex items-start justify-between gap-2">
                            <h4 id="recent-activity-heading" class="text-sm font-black text-gray-950 dark:text-white">Recent activity</h4>
                        </div>
                        <div class="mt-3 divide-y divide-gray-100 border-y border-gray-100 dark:divide-slate-800 dark:border-slate-800">
                            @forelse ($recentActivity as $activity)
                                <article class="py-3">
                                    <p class="text-xs font-black leading-5 text-gray-900 dark:text-white">{{ $activity->displaySummary() }}</p>
                                    <p class="mt-1 text-[10px] text-gray-500 dark:text-slate-400">{{ $activity->creator?->name ?? 'ATHENA' }} &middot; {{ $activity->created_at->diffForHumans() }}</p>
                                </article>
                            @empty
                                <p class="py-5 text-center text-xs text-gray-500 dark:text-slate-400">Paper activity will appear after the first save or upload.</p>
                            @endforelse
                        </div>
                        </div>
                    </section>
                </aside>
            </div>
        </section>

        <section id="required-pdf-attachments-tab" x-show="activeProposalTab === 'attachments'" x-cloak role="tabpanel" aria-labelledby="required-pdf-attachments-tab-button">
            <div class="mb-4">
                <h3 id="required-papers-heading" class="text-lg font-black text-gray-950 dark:text-white">Required PDF attachments</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">Complete the five proposal papers here. The GAD Checklist and Initial Screening Form are added automatically from Project Details.</p>
            </div>

            <div data-editable-proposal-papers x-data="proposalAssessmentPreview()" @resize.window.debounce.150ms="resizeProposalPaperPreview()" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div :inert="previewFullscreen">
                @foreach ($editableChecklist as $item)
                    @php
                        $paper = $item['paper'];
                        $template = filled($paper['template_slug']) ? $templates->get($paper['template_slug']) : null;
                        $sampleDefinition = filled($paper['sample_slug']) ? config('proposal_samples.'.$paper['sample_slug']) : null;
                        $sampleAvailable = is_array($sampleDefinition)
                            && isset($sampleDefinition['path'])
                            && \Illuminate\Support\Facades\Storage::disk('local')->exists($sampleDefinition['path']);
                        $paperRoute = match ($paper['slug']) {
                            'detailed-proposal' => route('faculty.proposal-drafts.detailed-proposal.edit', $proposalDraft),
                            'work-plan' => route('faculty.proposal-drafts.work-plan.edit', $proposalDraft),
                            'line-item-budget' => route('faculty.proposal-drafts.line-item-budget.edit', $proposalDraft),
                            'expense-breakdown' => route('faculty.proposal-drafts.expense-breakdown.edit', $proposalDraft),
                            'curriculum-vitae' => route('faculty.proposal-drafts.curriculum-vitae.edit', $proposalDraft),
                            'gad-checklist' => route('faculty.proposal-drafts.gad-checklist.show', $proposalDraft),
                            'initial-screening-form' => route('faculty.proposal-drafts.initial-screening-form.show', $proposalDraft),
                            default => route('faculty.proposal-drafts.papers.edit', [$proposalDraft, $paper['slug']]),
                        };
                        $paperAction = $paper['workspace_button_label'] ?? 'Open '.$paper['label'];
                        $paperPreview = $submissionFilesPrepared ? [
                            'label' => $paper['label'],
                            'previewUrl' => route('faculty.proposal-drafts.submission-files.download', [$proposalDraft, $paper['slug']]),
                        ] : null;
                    @endphp
                    <article data-proposal-paper="{{ $paper['slug'] }}" class="grid min-w-0 grid-cols-[3rem_minmax(0,1fr)] gap-4 border-b border-gray-200 p-5 last:border-b-0 dark:border-slate-800 sm:px-6 lg:grid-cols-[3rem_minmax(0,1fr)_auto] lg:items-center">
                        <x-document-file-icon label="PDF" class="shrink-0" />
                        <div class="min-w-0">
                            <p class="mb-1 text-sm text-gray-500 dark:text-slate-400">Paper {{ str_pad((string) $paper['order'], 2, '0', STR_PAD_LEFT) }}</p>
                            <div class="flex flex-wrap items-center gap-2">
                                <h4 class="break-words text-base font-bold leading-6 text-gray-950 dark:text-white">{{ $paper['label'] }}</h4>
                                <span class="rounded-full border px-2.5 py-1 text-sm font-semibold {{ $item['needs_attention'] ? 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200' : ($item['complete'] ? 'border-gray-300 bg-gray-100 text-gray-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200' : ($item['status'] === 'In progress' ? 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200' : 'border-gray-200 bg-white text-gray-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400')) }}">{{ $item['status'] }}</span>
                            </div>
                            <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-slate-400">{{ $paper['description'] }}</p>

                            <div class="mt-2 text-sm text-gray-600 dark:text-slate-300">
                                @if ($paper['mode'] === 'automatic')
                                    <p class="font-semibold">PDF prepared automatically from Project Details when the proposal is turned in.</p>
                                @elseif ($item['documents']->isNotEmpty())
                                    @if ($paper['mode'] === 'generated')
                                        <p class="break-all font-semibold">{{ $item['submission_filename'] }}</p>
                                        <p class="mt-0.5 text-sm leading-6 text-gray-500 dark:text-slate-400">{{ $submissionFilesPrepared ? 'Prepared PDF' : 'Saved form · Submission PDFs not fully prepared' }} &middot; Saved {{ $item['documents']->first()->updated_at->diffForHumans() }}</p>
                                    @elseif ($paper['multiple'])
                                        <p class="font-semibold">{{ $item['count'] }} {{ Str::plural('file', $item['count']) }} staged</p>
                                    @else
                                        <p class="break-all font-semibold">{{ $item['documents']->first()->original_filename }}</p>
                                    @endif
                                @else
                                    <p>No file or form data saved yet.</p>
                                @endif
                            </div>

                            @if ($template || $sampleAvailable)
                                <div class="mt-3 flex flex-wrap gap-3">
                                    @if ($template)<a href="{{ route('proposal-templates.download', $template) }}" class="inline-flex min-h-11 items-center text-sm font-bold text-red-600 underline decoration-red-200 underline-offset-4 hover:text-red-700 dark:text-red-300 dark:decoration-red-900 dark:hover:text-red-200">Download template</a>@endif
                                    @if ($sampleAvailable)<a href="{{ route('proposal-samples.show', $paper['sample_slug']) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center text-sm font-bold text-red-600 underline decoration-red-200 underline-offset-4 hover:text-red-700 dark:text-red-300 dark:decoration-red-900 dark:hover:text-red-200">View sample</a>@endif
                                </div>
                            @endif
                        </div>
                        <div class="col-span-2 flex min-w-0 flex-col gap-2 sm:flex-row sm:flex-wrap lg:col-span-1 lg:flex-col">
                            @if ($paperPreview)
                                <button type="button" data-proposal-paper-preview-trigger @click="openAssessmentPreview(@js($paperPreview))" aria-label="Preview {{ $paper['label'] }}" aria-haspopup="dialog" aria-controls="draft-paper-preview-panel" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-red-200 px-4 py-2.5 text-base font-semibold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/40">Preview PDF</button>
                            @endif
                            @if ($canEditDraft)
                                <a href="{{ $paperRoute }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-base font-bold text-gray-900 transition hover:border-red-600 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:hover:border-red-600 dark:hover:text-red-300" aria-label="{{ $paperAction }}">{{ $paperAction }}</a>
                            @else
                                <span class="text-sm font-semibold text-gray-500 dark:text-slate-400">Editing locked</span>
                            @endif
                        </div>
                    </article>
                @endforeach
                </div>
                <x-proposal-paper-preview panel-id="draft-paper-preview-panel" preview-label="Prepared proposal paper preview" frame-title="Prepared proposal paper preview" />
            </div>

            <section data-automatic-assessment-forms data-automatic-assessment-preview x-data="proposalAssessmentPreview()" @resize.window.debounce.150ms="resizeProposalPaperPreview()" class="mt-5 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-labelledby="automatic-assessment-forms-heading">
                <div :inert="previewFullscreen">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-slate-800 sm:px-6">
                    <h4 id="automatic-assessment-forms-heading" class="text-base font-bold text-gray-950 dark:text-white">Assessment forms added automatically</h4>
                    <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-slate-400">No faculty answers or file uploads are needed. These two blank forms are generated from Project Details and included when the proposal is turned in.</p>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-slate-800">
                    @foreach ($automaticChecklist as $item)
                        @php
                            $formRoutes = 'faculty.proposal-drafts.'.$item['paper']['slug'];
                            $formPreview = [
                                'label' => $item['paper']['label'],
                                'previewUrl' => route($formRoutes.'.preview', $proposalDraft),
                                'downloadUrl' => route($formRoutes.'.download', $proposalDraft),
                            ];
                        @endphp
                        <div class="grid min-w-0 grid-cols-[3rem_minmax(0,1fr)] gap-4 px-5 py-4 sm:px-6 lg:grid-cols-[3rem_minmax(0,1fr)_auto] lg:items-center">
                            <x-document-file-icon label="PDF" class="shrink-0" />
                            <div class="min-w-0">
                                <p class="break-words text-base font-bold text-gray-900 dark:text-white">{{ $item['paper']['label'] }}</p>
                                <p class="mt-0.5 text-sm leading-6 text-gray-500 dark:text-slate-400">{{ $item['complete'] ? 'Added automatically to your submission' : 'Complete Project Details to prepare this form' }}</p>
                            </div>
                            <button type="button" data-assessment-preview-trigger @click="openAssessmentPreview(@js($formPreview))" aria-label="Preview {{ $item['paper']['label'] }}" aria-haspopup="dialog" aria-controls="assessment-form-preview-panel" class="col-span-2 inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 px-4 py-2.5 text-base font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-600 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 lg:col-span-1">Preview form</button>
                        </div>
                    @endforeach
                </div>
                </div>
                <x-proposal-paper-preview panel-id="assessment-form-preview-panel" preview-label="Assessment form preview" frame-title="Assessment form preview" />
            </section>

        </section>

        <section id="proposal-collaborators-tab" x-show="activeProposalTab === 'collaborators'" x-cloak role="tabpanel" aria-labelledby="proposal-collaborators-tab-button" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-4 border-b border-gray-200 p-5 dark:border-slate-800 sm:p-6 xl:flex-row xl:items-start xl:justify-between">
                <div class="flex min-w-0 items-start gap-3">
                    <span data-team-section-icon aria-hidden="true" class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.87M15 3.13a4 4 0 0 1 0 7.75" /><circle cx="9" cy="7" r="4" /></svg>
                    </span>
                    <div class="min-w-0">
                        <h3 id="workspace-members-heading" class="text-2xl font-bold text-gray-900 dark:text-white">Project team</h3>
                        <p class="mt-1 max-w-3xl text-base leading-7 text-gray-600 dark:text-slate-300">The project leader and project staff form the research team from proposal preparation through project completion. Only the project leader can submit the proposal.</p>
                    </div>
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    <span class="inline-flex w-fit rounded-full bg-gray-100 px-3 py-1.5 text-sm font-semibold text-gray-700 dark:bg-slate-800 dark:text-slate-200">{{ 1 + $proposalDraft->members->count() }} {{ Str::plural('member', 1 + $proposalDraft->members->count()) }}</span>
                    @can('manageMembers', $proposalDraft)
                        <button type="button" data-open-collaborator-modal x-on:click="$dispatch('open-modal', 'proposal-collaborator-invitation')" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-red-600 px-4 py-2.5 text-base font-semibold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m18-11v6m-3-3h6" /><circle cx="9" cy="7" r="4" /></svg>
                            Add project staff
                        </button>
                    @endcan
                </div>
            </div>

            <div data-project-team-roster class="divide-y divide-gray-200 dark:divide-slate-800">
                <article data-project-team-member="owner" class="grid min-w-0 gap-3 p-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:p-6">
                    <div class="flex min-w-0 items-start gap-3 sm:gap-4">
                        <x-user-avatar :user="$proposalDraft->owner" class="h-12 w-12 rounded-full bg-red-700 text-lg" />
                        <div class="min-w-0">
                            <h4 data-team-member-name class="break-words text-lg font-bold leading-7 text-gray-900 dark:text-white">{{ $proposalDraft->owner->name }}</h4>
                            <p data-team-member-email class="mt-1 break-all text-base leading-6 text-gray-600 dark:text-slate-300">{{ $proposalDraft->owner->email }}</p>
                            <p class="mt-2 text-base leading-6 text-gray-600 dark:text-slate-300">Project leader responsible for the team and proposal submission.</p>
                        </div>
                    </div>
                    <span data-team-member-status class="inline-flex h-fit w-fit items-center gap-1.5 rounded-full bg-red-50 px-3 py-1 text-sm font-semibold text-red-700 dark:bg-red-950/40 dark:text-red-200">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m12 3 8 4v5c0 4-3 7-8 9-5-2-8-5-8-9V7l8-4Zm-3 9 2 2 4-4" /></svg>
                        Project leader
                    </span>
                </article>
                @foreach ($proposalDraft->members as $member)
                    <article data-project-team-member="{{ $member->id }}" class="grid min-w-0 gap-3 p-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:p-6">
                        <div class="flex min-w-0 items-start gap-3 sm:gap-4">
                            @if ($member->user)
                                <x-user-avatar :user="$member->user" class="h-12 w-12 rounded-full bg-slate-600 text-lg dark:bg-slate-700" />
                            @else
                                <span aria-hidden="true" data-team-pending-avatar class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4" /><path stroke-linecap="round" d="M4 21v-2a8 8 0 0 1 16 0v2" /></svg>
                                </span>
                            @endif
                            <div class="min-w-0">
                                <h4 data-team-member-name class="break-words text-lg font-bold leading-7 text-gray-900 dark:text-white">{{ $member->user?->name ?? $member->name }}</h4>
                                <p data-team-member-email class="mt-1 break-all text-base leading-6 text-gray-600 dark:text-slate-300">{{ $member->user?->email ?? $member->email }}</p>
                                <p class="mt-2 text-base leading-6 {{ $member->isAccepted() ? 'text-gray-600 dark:text-slate-300' : 'text-amber-800 dark:text-amber-200' }}">{{ $member->isAccepted() ? 'Project staff for this research project through completion. Only the project leader can submit.' : ($member->isLinked() ? 'Waiting for the invited staff member to accept the invitation.' : 'Waiting for this exact email to sign in to ATHENA.') }}</p>
                            </div>
                        </div>
                        <div class="flex h-fit flex-wrap items-center gap-2 sm:max-w-56 sm:justify-end">
                                @if ($member->isProjectSecretary())
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-sm font-semibold text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="4" width="12" height="17" rx="2" /><path stroke-linecap="round" stroke-linejoin="round" d="M9 3h6v4H9V3Zm0 10h6m-6 4h4" /></svg>
                                        Project Secretary
                                    </span>
                                @elseif ($member->isAccepted())
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-sm font-semibold text-gray-700 dark:bg-slate-800 dark:text-slate-200">Project staff</span>
                                @endif
                                <span data-team-member-status class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-semibold {{ $member->isAccepted() ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200' : 'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200' }}">
                                    @if ($member->isAccepted())
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" stroke-linejoin="round" d="m8 12 3 3 5-6" /></svg>
                                    @else
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" d="M12 7v5l3 2" /></svg>
                                    @endif
                                    {{ $member->isAccepted() ? 'Confirmed' : ($member->isLinked() ? 'Invitation pending' : 'Pending sign-in') }}
                                </span>
                        </div>
                        @can('manageMembers', $proposalDraft)
                        @if (! $member->isAccepted())
                            <div class="flex flex-wrap items-center gap-2 sm:col-span-2 sm:pl-16">
                                <form action="{{ route('faculty.proposal-drafts.members.invitation', [$proposalDraft, $member]) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 px-3 py-2 text-base font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-600 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Resend invitation</button>
                                </form>
                                <form action="{{ route('faculty.proposal-drafts.members.destroy', [$proposalDraft, $member]) }}" method="POST" data-proposal-confirm data-confirm-title="Remove project staff?" data-confirm-text="This invited staff member will immediately lose access to the proposal workspace." data-confirm-button="Remove project staff">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg px-3 py-2 text-base font-semibold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-red-300 dark:hover:bg-red-950/40">Remove</button>
                                </form>
                            </div>
                        @endif
                        @endcan
                    </article>
                @endforeach
            </div>

            <div class="border-t border-gray-200 bg-gray-50 p-5 dark:border-slate-800 dark:bg-slate-950/40 sm:p-6">
                <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,28rem)] lg:items-start">
                    <div class="flex min-w-0 items-start gap-3">
                        <span data-secretary-section-icon aria-hidden="true" class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><rect x="6" y="4" width="12" height="17" rx="2" /><path stroke-linecap="round" stroke-linejoin="round" d="M9 3h6v4H9V3Zm0 10h6m-6 4h4" /></svg>
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-600 dark:text-slate-400">Project roles</p>
                            <h4 class="mt-1 text-xl font-bold text-gray-950 dark:text-white">Project Secretary</h4>
                            <p class="mt-2 max-w-2xl text-base leading-7 text-gray-600 dark:text-slate-300">The secretary monitors budget utilization and receives related reminders. Other authorized project staff can also complete this work.</p>
                        </div>
                    </div>

                    <div>
                        @if ($projectSecretaryMember)
                            <div class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-900 dark:bg-amber-950/30">
                                @if ($projectSecretaryMember->user)
                                    <x-user-avatar :user="$projectSecretaryMember->user" class="h-12 w-12 rounded-full bg-amber-700 text-lg" />
                                @else
                                    <span aria-hidden="true" class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-amber-700 text-lg font-bold text-white">{{ mb_substr($projectSecretaryMember->name, 0, 1) }}</span>
                                @endif
                                <span class="min-w-0"><span class="block break-words text-base font-bold text-gray-950 dark:text-white">{{ $projectSecretaryMember->user?->name ?? $projectSecretaryMember->name }}</span><span class="mt-1 block break-all text-base text-gray-600 dark:text-slate-300">{{ $projectSecretaryMember->user?->email ?? $projectSecretaryMember->email }}</span></span>
                            </div>
                        @else
                            <div class="rounded-xl border border-dashed border-amber-300 bg-amber-50 px-4 py-3 text-base font-semibold leading-6 text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">No Project Secretary has been assigned.</div>
                        @endif

                        @can('manageMembers', $proposalDraft)
                            <div x-data="researchSecretaryPicker({ candidates: @js($projectRoleCandidates), selectedId: @js(old('member_id', $projectSecretaryMember?->id)) })" class="relative mt-3" data-project-role-picker>
                                <form x-ref="form" method="POST" action="{{ route('faculty.proposal-drafts.member-roles.update', $proposalDraft) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="project_role" value="secretary">
                                    <input type="hidden" name="member_id" :value="selectedId || ''">
                                </form>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" x-ref="trigger" :aria-expanded="open.toString()" aria-expanded="false" aria-controls="draft-project-secretary-options-{{ $proposalDraft->id }}" aria-haspopup="listbox" @click="open = !open; if (open) $nextTick(() => $refs.search.focus())" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-gray-950 px-4 py-2 text-base font-semibold text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:bg-white dark:text-slate-950 dark:focus:ring-offset-slate-900">{{ $projectSecretaryMember ? 'Change secretary' : 'Select project staff' }}</button>
                                    @if ($projectSecretaryMember)
                                        <button type="button" @click="clearSelection" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-base font-semibold text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-900">Remove role</button>
                                    @endif
                                </div>
                                @error('member_id')<p class="mt-2 text-base font-semibold text-red-700 dark:text-red-300">{{ $message }}</p>@enderror
                                @error('project_role')<p class="mt-2 text-base font-semibold text-red-700 dark:text-red-300">{{ $message }}</p>@enderror

                                <div id="draft-project-secretary-options-{{ $proposalDraft->id }}" x-show="open" x-transition.origin.top x-cloak @click.outside="open = false" @keydown.escape.stop="open = false; $refs.trigger.focus()" class="mt-3 w-full rounded-xl border border-gray-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                                    <label class="sr-only" for="draft-project-secretary-search-{{ $proposalDraft->id }}">Search confirmed project staff</label>
                                    <input x-ref="search" id="draft-project-secretary-search-{{ $proposalDraft->id }}" x-model="query" type="search" autocomplete="off" placeholder="Search confirmed project staff" class="block w-full min-h-11 rounded-lg border-gray-200 text-base focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white">
                                    <div class="mt-2 max-h-64 space-y-1 overflow-y-auto" role="listbox" aria-label="Confirmed project staff">
                                        <template x-for="candidate in filteredCandidates()" :key="candidate.id">
                                            <button type="button" role="option" :aria-selected="selectedId == candidate.id ? 'true' : 'false'" @click="select(candidate.id)" class="flex min-h-11 w-full items-center gap-3 rounded-lg px-2.5 py-2 text-left hover:bg-red-50 focus:bg-red-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-red-600 dark:hover:bg-slate-800 dark:focus:bg-slate-800">
                                                <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-red-100 text-base font-bold text-red-700 dark:bg-red-950/40 dark:text-red-200"><img x-show="candidate.avatar" :src="candidate.avatar" alt="" x-on:error="candidate.avatar = ''" class="h-full w-full object-cover"><span x-show="!candidate.avatar" x-text="initials(candidate.name)"></span></span>
                                                <span class="min-w-0 flex-1"><span class="block break-words text-base font-semibold text-gray-900 dark:text-white" x-text="candidate.name"></span><span class="mt-0.5 block break-all text-base text-gray-600 dark:text-slate-300" x-text="candidate.email"></span><span x-show="candidate.college" class="mt-0.5 block text-sm text-gray-500 dark:text-slate-400" x-text="candidate.college"></span></span>
                                            </button>
                                        </template>
                                        <p x-show="filteredCandidates().length === 0" class="px-3 py-5 text-center text-base leading-6 text-gray-600 dark:text-slate-300">Staff must accept the project invitation before receiving a project role.</p>
                                    </div>
                                </div>
                            </div>
                        @else
                            <p class="mt-3 text-base leading-6 text-gray-600 dark:text-slate-300">Only the project leader can assign team roles.</p>
                        @endcan
                    </div>
                </div>
            </div>

        </section>
    </div>

    @can('manageMembers', $proposalDraft)
        <x-modal name="proposal-collaborator-invitation" :show="$memberInvitationHasErrors" maxWidth="xl" focusable role="dialog" aria-modal="true" aria-labelledby="team-invitation-heading">
            <div
                x-data="proposalDraftMembers({ candidates: @js($memberCandidates) })"
                data-collaborator-invitation-modal
                data-opened-from-validation="{{ $memberInvitationHasErrors ? 'true' : 'false' }}"
                class="bg-white dark:bg-slate-900"
            >
                <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-slate-700 sm:px-6">
                    <div>
                        <h2 id="team-invitation-heading" class="flex items-center gap-2 text-xl font-bold text-gray-900 dark:text-white">
                            <svg class="h-6 w-6 shrink-0 text-red-700 dark:text-red-300" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m18-11v6m-3-3h6" /><circle cx="9" cy="7" r="4" /></svg>
                            Add project staff
                        </h2>
                        <p class="mt-2 text-base leading-6 text-gray-600 dark:text-slate-300">Invite project staff using the exact BatStateU Google account they use for ATHENA. Assign their project role after they accept.</p>
                    </div>
                    <button type="button" x-on:click="$dispatch('close-modal', 'proposal-collaborator-invitation')" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Close project staff invitation" title="Close">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18" /></svg>
                    </button>
                </div>

                <form action="{{ route('faculty.proposal-drafts.members.store', $proposalDraft) }}" method="POST">
                    @csrf
                    <div class="space-y-5 px-5 py-5 sm:px-6">
                        <div>
                            <label for="workspace-member-email" class="block text-base font-semibold text-gray-700 dark:text-slate-300">BatStateU Google email</label>
                            <div class="relative mt-2" data-collaborator-account-picker x-on:click.outside="closePicker()">
                                <input
                                    id="workspace-member-email"
                                    name="email"
                                    type="email"
                                    x-model="email"
                                    x-on:focus="openPicker()"
                                    x-on:input="handleEmailInput()"
                                    x-on:keydown="handleEmailKeydown($event)"
                                    x-bind:aria-expanded="pickerOpen"
                                    x-bind:aria-activedescendant="pickerOpen && highlightedIndex >= 0 ? `workspace-member-option-${highlightedIndex}` : null"
                                    aria-autocomplete="list"
                                    aria-controls="workspace-member-account-options"
                                    role="combobox"
                                    value="{{ old('email') }}"
                                    maxlength="255"
                                    required
                                    autocomplete="off"
                                    placeholder="name@g.batstate-u.edu.ph"
                                    class="block w-full min-h-11 rounded-lg border-gray-300 py-2.5 pl-3 pr-11 text-base shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                                >
                                <button type="button" x-on:click="pickerOpen ? closePicker() : openPicker()" class="absolute inset-y-0 right-0 inline-flex w-11 items-center justify-center rounded-r-xl text-gray-400 transition hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-red-600 dark:text-slate-400 dark:hover:text-white" aria-label="Show ATHENA accounts" x-bind:aria-expanded="pickerOpen" aria-controls="workspace-member-account-options">
                                    <svg class="h-4 w-4 transition" x-bind:class="{ 'rotate-180': pickerOpen }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
                                </button>

                                <div
                                    id="workspace-member-account-options"
                                    x-show="pickerOpen"
                                    x-cloak
                                    x-transition.origin.top
                                    role="listbox"
                                    aria-label="ATHENA accounts"
                                    class="absolute z-30 mt-2 max-h-64 w-full overflow-y-auto rounded-2xl border border-gray-200 bg-white p-1.5 shadow-xl shadow-gray-900/10 dark:border-slate-700 dark:bg-slate-800"
                                >
                                    <template x-for="(candidate, index) in filteredCandidates" x-bind:key="candidate.email">
                                        <button
                                            type="button"
                                            x-bind:id="`workspace-member-option-${index}`"
                                            role="option"
                                            x-bind:aria-selected="highlightedIndex === index"
                                            x-on:mouseenter="highlightedIndex = index"
                                            x-on:click="selectCandidate(candidate)"
                                            x-bind:class="highlightedIndex === index ? 'bg-red-50 dark:bg-red-950/40' : 'hover:bg-gray-50 dark:hover:bg-slate-700/70'"
                                            class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition"
                                        >
                                            <span class="relative flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-red-100 text-base font-bold text-red-700 ring-1 ring-red-200 dark:bg-red-950 dark:text-red-200 dark:ring-red-900">
                                                <img x-show="candidate.avatar" x-bind:src="candidate.avatar" x-bind:alt="candidate.name" x-on:error="candidate.avatar = ''" class="h-full w-full object-cover">
                                                <span x-show="!candidate.avatar" x-text="candidateInitials(candidate.name)"></span>
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block break-words text-base font-semibold text-gray-900 dark:text-white" x-text="candidate.name"></span>
                                                <span class="block break-all text-sm text-gray-600 dark:text-slate-300" x-text="candidate.email"></span>
                                            </span>
                                            <svg class="h-4 w-4 shrink-0 text-red-600 opacity-0" x-bind:class="{ 'opacity-100': matchedAccount?.email === candidate.email }" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>
                                        </button>
                                    </template>

                                    <div x-show="filteredCandidates.length === 0" class="px-3 py-4 text-center">
                                        <p class="text-base font-semibold text-gray-700 dark:text-slate-200">No matching ATHENA account</p>
                                        <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-slate-300">You can still invite the exact BatStateU Google email they will use to sign in.</p>
                                    </div>
                                </div>
                            </div>
                            <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-slate-300">Choose an ATHENA account or enter the exact institutional email they will use to sign in.</p>
                            @error('email')<p class="mt-2 text-base font-semibold text-red-700 dark:text-red-300">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="workspace-member-name" class="block text-base font-semibold text-gray-700 dark:text-slate-300">Staff member name</label>
                            <input id="workspace-member-name" name="name" type="text" x-model="name" value="{{ old('name') }}" maxlength="255" required placeholder="Full name" class="mt-2 block w-full min-h-11 rounded-lg border-gray-300 text-base shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-800 dark:text-white">
                            <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-slate-300" x-text="matchedAccount ? 'Pulled from the linked ATHENA account.' : 'Used until their ATHENA account is linked.'"></p>
                            @error('name')<p class="mt-2 text-base font-semibold text-red-700 dark:text-red-300">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-2 border-t border-gray-200 bg-gray-50 px-5 py-4 dark:border-slate-700 dark:bg-slate-800/70 sm:flex-row sm:justify-end sm:px-6">
                        <button type="button" x-on:click="$dispatch('close-modal', 'proposal-collaborator-invitation')" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-base font-semibold text-gray-700 transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">Cancel</button>
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-red-600 px-5 py-2.5 text-base font-semibold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">Send invitation</button>
                    </div>
                </form>
            </div>
        </x-modal>
    @endcan

    <x-modal name="proposal-review" maxWidth="6xl" focusable>
        <div class="flex max-h-[calc(100vh-3rem)] flex-col">
            <div class="flex shrink-0 items-start justify-between gap-4 border-b border-gray-200 bg-white px-5 py-4 sm:px-6">
                <div>
                    <h2 class="text-lg font-black text-gray-900">Review and Turn In</h2>
                    <p class="mt-1 text-xs text-gray-500">Review your proposal papers before submitting. The assessment forms are included automatically.</p>
                </div>
                <button type="button" x-on:click="$dispatch('close-modal', 'proposal-review')" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-600" aria-label="Close review and turn in" title="Close review and turn in">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18" /></svg>
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto bg-gray-50 px-4 py-5 sm:px-6 sm:py-6">
                @if ($errors->any())
                    <x-proposal-alert type="error">
                        <p class="font-black">This project cannot be turned in yet.</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </x-proposal-alert>
                @endif

                <div class="space-y-6">
                    <livewire:proposal-draft-review-package :proposal-draft="$proposalDraft" :in-modal="true" lazy />
                </div>
            </div>
        </div>
    </x-modal>
</x-app-layout>
