<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <x-back-link href="{{ route('faculty.proposal-drafts.index') }}">Back to saved drafts</x-back-link>
                <h2 class="mt-2 break-words text-2xl font-black tracking-tight text-gray-900">{{ $proposalDraft->project_title }}</h2>
                <p class="mt-1 text-xs text-gray-500">{{ $proposalDraft->researchCall->title }} &middot; Last saved {{ $proposalDraft->updated_at->diffForHumans() }}</p>
                <p class="mt-1 text-xs font-bold text-blue-700">{{ $proposalDraft->user_id === auth()->id() ? 'You own this workspace' : 'Shared with you by '.$proposalDraft->owner->name }}</p>
            </div>
            <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                <a href="{{ route('faculty.proposal-drafts.history.index', $proposalDraft) }}" class="inline-flex w-full shrink-0 items-center justify-center rounded-xl border border-gray-300 bg-white px-5 py-3 text-sm font-bold text-gray-800 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-700 focus:ring-offset-2 sm:w-auto">History{{ $historyCount > 0 ? ' ('.$historyCount.')' : '' }}</a>
                <button type="button" x-on:click="$dispatch('open-modal', 'proposal-review')" class="inline-flex w-full shrink-0 items-center justify-center rounded-xl bg-gray-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2 sm:w-auto">Review &amp; turn in</button>
            </div>
        </div>
    </x-slot>

    @php
        $completedPaperCount = $checklist->where('complete', true)->count();
        $paperCount = $checklist->count();
        $initialProposalTab = in_array(session('proposal_tab'), ['details', 'attachments', 'collaborators'], true)
            ? session('proposal_tab')
            : null;
        $memberInvitationHasErrors = $errors->hasAny(['email', 'name']);
    @endphp

    <div
        class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8"
        x-data="{
            activeProposalTab: @js($memberInvitationHasErrors ? 'collaborators' : $initialProposalTab) || (
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

        @if ($errors->any())
            <x-proposal-alert type="error">
                <p class="font-bold">Some information still needs attention.</p>
                <ul class="mt-1 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-proposal-alert>
        @endif

        @if (isset($readinessErrors['research_call']))
            <div role="alert" class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                <p class="font-black">Submission is currently unavailable</p>
                <p class="mt-1">{{ $readinessErrors['research_call'] }}</p>
            </div>
        @endif

        <x-budget-consistency-warning :comparison="$budgetConsistency" :proposal-draft="$proposalDraft" />

        <div class="overflow-x-auto border-b border-gray-200" role="tablist" aria-label="Proposal workspace sections">
            <nav class="flex min-w-max gap-6">
                <button id="project-details-tab-button" type="button" role="tab" aria-controls="project-details-tab" :aria-selected="activeProposalTab === 'details'" @click="window.location.hash = 'project-details'" :class="activeProposalTab === 'details' ? 'border-red-600 text-red-600' : 'border-transparent text-gray-500 hover:border-red-300 hover:text-red-600'" class="flex items-center gap-2 border-b-2 px-1 pb-3 text-xs font-bold transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5.25A2.25 2.25 0 0 1 6.25 3h11.5A2.25 2.25 0 0 1 20 5.25v13.5A2.25 2.25 0 0 1 17.75 21H6.25A2.25 2.25 0 0 1 4 18.75V5.25Z" /><path stroke-linecap="round" d="M8 8h8M8 12h8M8 16h5" /></svg>
                    Project Details
                </button>
                <button id="required-pdf-attachments-tab-button" type="button" role="tab" aria-controls="required-pdf-attachments-tab" :aria-selected="activeProposalTab === 'attachments'" @click="window.location.hash = 'required-pdf-attachments'" :class="activeProposalTab === 'attachments' ? 'border-red-600 text-red-600' : 'border-transparent text-gray-500 hover:border-red-300 hover:text-red-600'" class="flex items-center gap-2 border-b-2 px-1 pb-3 text-xs font-bold transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h10.5A2.25 2.25 0 0 1 19.5 6v14.25H4.5V6a2.25 2.25 0 0 1 2.25-2.25Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 8.25h7.5M8.25 12h7.5M8.25 15.75h4.5" /></svg>
                    Required PDF attachments
                </button>
                <button id="proposal-collaborators-tab-button" type="button" role="tab" aria-controls="proposal-collaborators-tab" :aria-selected="activeProposalTab === 'collaborators'" @click="window.location.hash = 'proposal-collaborators'" :class="activeProposalTab === 'collaborators' ? 'border-red-600 text-red-600' : 'border-transparent text-gray-500 hover:border-red-300 hover:text-red-600'" class="flex items-center gap-2 border-b-2 px-1 pb-3 text-xs font-bold transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9A2.25 2.25 0 0 1 5.25 16.5v-9A2.25 2.25 0 0 1 7.5 5.25h9a2.25 2.25 0 0 1 2.25 2.25v9a2.25 2.25 0 0 1-2.25 2.25Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6M12 9v6" /></svg>
                    Proposal collaborators
                </button>
            </nav>
        </div>

        <section id="project-details-tab" x-show="activeProposalTab === 'details'" x-cloak role="tabpanel" aria-labelledby="project-details-tab-button">
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <div data-paper-editor data-paper-dirty="{{ $errors->any() ? 'true' : 'false' }}" data-paper-edit-url="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" data-paper-exit-url="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" x-data="proposalDraftProjectDetails({ initialDuration: @js($initialDuration), initialStart: @js($initialPlannedStart), initialEnd: @js($initialPlannedEnd) })" class="space-y-4">
                    <x-paper-editor-submit-status />
                    <x-proposal-collaboration-monitor
                        :loaded-version="(int) old('draft_version', $proposalDraft->lock_version)"
                        :state-url="route('faculty.proposal-drafts.edit-state', [$proposalDraft, 'details', 0])"
                        :reload-url="route('faculty.proposal-drafts.show', $proposalDraft)"
                        label="project details"
                    />

                    <section aria-labelledby="project-details-heading" class="rounded-2xl border {{ $projectDetailsComplete ? 'border-green-200' : 'border-amber-200' }} bg-white p-5 shadow-sm sm:p-6">
                        <div class="border-b border-gray-100 pb-5">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 id="project-details-heading" class="text-lg font-black text-gray-900">Project Details</h3>
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $projectDetailsComplete ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">{{ $projectDetailsComplete ? 'Complete' : 'Incomplete' }}</span>
                            </div>
                            <p class="mt-2 text-sm leading-6 text-gray-500">Shared information used automatically in Attachment A and the submitted proposal record.</p>
                        </div>

                        <div class="mt-5 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                            <p class="font-black">Research call</p>
                            <p class="mt-1">{{ $proposalDraft->researchCall->title }}</p>
                        </div>

                        <form data-paper-form action="{{ route('faculty.proposal-drafts.details.update', $proposalDraft) }}" method="POST" class="mt-5 space-y-6">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="draft_version" value="{{ old('draft_version', $proposalDraft->lock_version) }}">

                            <div>
                                <label for="project_title" class="block text-xs font-black uppercase tracking-wider text-gray-600">Project Title <span class="text-red-600">Required</span></label>
                                <input id="project_title" name="project_title" type="text" value="{{ old('project_title', $proposalDraft->project_title) }}" maxlength="255" required autofocus class="mt-2 block w-full rounded-xl border-gray-300 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600">
                                @error('project_title')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div class="grid gap-5 md:grid-cols-3">
                                <div>
                                    <label for="duration_months" class="block text-xs font-black uppercase tracking-wider text-gray-600">Total Duration <span class="text-red-600">Required</span></label>
                                    <div class="relative mt-2"><input id="duration_months" name="duration_months" type="number" min="1" max="{{ config('work_plan.max_duration_months') }}" x-model.number="durationMonths" required class="block w-full rounded-xl border-gray-300 pr-20 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600"><span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-xs font-bold text-gray-500">months</span></div>
                                    <p class="mt-2 text-[11px] text-gray-500">Attachment A adds one M1-M12 sheet for each 12-month project period.</p>
                                    @error('duration_months')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="planned_start" class="block text-xs font-black uppercase tracking-wider text-gray-600">Planned Start <span class="text-red-600">Required</span></label>
                                    <x-date-picker id="planned_start" name="planned_start" model="plannedStart" :min="$minimumProjectDate" required class="mt-2" />
                                    <p class="mt-2 text-[11px] text-gray-500">Only today and future dates can be selected.</p>
                                    @error('planned_start')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="planned_end" class="block text-xs font-black uppercase tracking-wider text-gray-600">Planned End <span class="text-red-600">Required</span></label>
                                    <x-date-picker id="planned_end" name="planned_end" model="plannedEnd" :min="$minimumProjectDate" required class="mt-2" />
                                    <p class="mt-2 text-[11px] text-gray-500">Automatically calculated from the total duration and planned start.</p>
                                    @error('planned_end')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <div>
                                <label for="project_leader" class="block text-xs font-black uppercase tracking-wider text-gray-600">Project Leader <span class="text-red-600">Required</span></label>
                                <input id="project_leader" name="project_leader" type="text" list="proposal-workspace-people" value="{{ old('project_leader', $proposalDraft->project_leader ?: $proposalDraft->owner->name) }}" maxlength="120" required class="mt-2 block w-full rounded-xl border-gray-300 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600">
                                <datalist id="proposal-workspace-people">@foreach ($workspacePeople as $workspacePerson)<option value="{{ $workspacePerson['name'] }}">{{ $workspacePerson['email'] }}</option>@endforeach</datalist>
                                <p class="mt-2 text-[11px] text-gray-500">Choose a workspace member or type a name. This appears under "Prepared by" in the official Work Plan.</p>
                                @error('project_leader')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div class="flex justify-end border-t border-gray-100 pt-5">
                                <button data-paper-save-exit type="submit" name="exit_after_save" value="1" class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">Update Project Details</button>
                            </div>
                        </form>
                    </section>
                </div>

                <aside class="space-y-4">
                    <section aria-labelledby="package-progress-heading" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 id="package-progress-heading" class="text-sm font-black text-gray-900">Proposal package progress</h3>
                                <p class="mt-1 text-xs leading-5 text-gray-500">{{ $completedPaperCount }} of {{ $paperCount }} required PDF attachments ready</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2 py-1 text-[9px] font-black {{ $completedPaperCount === $paperCount && $projectDetailsComplete ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">{{ $completedPaperCount === $paperCount && $projectDetailsComplete ? 'Ready' : 'In progress' }}</span>
                        </div>
                        <div class="mt-4 h-2 overflow-hidden rounded-full bg-gray-100" aria-hidden="true">
                            <div class="h-full rounded-full bg-red-600" style="width: {{ $paperCount === 0 ? 0 : ($completedPaperCount / $paperCount) * 100 }}%"></div>
                        </div>
                    </section>

                    <section aria-labelledby="recent-activity-heading" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-2">
                            <h3 id="recent-activity-heading" class="text-sm font-black text-gray-900">Recent activity</h3>
                            <a href="{{ route('faculty.proposal-drafts.history.index', $proposalDraft) }}" class="shrink-0 text-[10px] font-black text-red-600 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600">View history</a>
                        </div>
                        <div class="mt-3 divide-y divide-gray-100 border-y border-gray-100">
                            @forelse ($recentActivity as $activity)
                                <article class="py-3">
                                    <p class="text-xs font-black leading-5 text-gray-900">{{ $activity->displaySummary() }}</p>
                                    <p class="mt-1 text-[10px] text-gray-500">{{ $activity->creator?->name ?? 'ATHENA' }} &middot; {{ $activity->created_at->diffForHumans() }}</p>
                                </article>
                            @empty
                                <p class="py-5 text-center text-xs text-gray-500">Paper activity will appear after the first save or upload.</p>
                            @endforelse
                        </div>
                    </section>
                </aside>
            </div>
        </section>

        <section id="required-pdf-attachments-tab" x-show="activeProposalTab === 'attachments'" x-cloak role="tabpanel" aria-labelledby="required-pdf-attachments-tab-button">
            <div class="mb-4">
                <h3 id="required-papers-heading" class="text-lg font-black text-gray-900">Required PDF attachments</h3>
                <p class="mt-1 text-sm text-gray-500">Complete each paper here. ATHENA prepares generated forms as PDFs when the owner turns in the package.</p>
            </div>

            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($checklist as $item)
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
                        $submissionExtension = Str::upper(pathinfo($item['submission_filename'], PATHINFO_EXTENSION));
                        $submissionFormat = $submissionExtension === 'XLSX' ? 'Excel workbook' : 'PDF';
                    @endphp
                    <article class="flex min-h-80 flex-col rounded-2xl border {{ $item['complete'] ? 'border-green-200' : 'border-gray-200' }} bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <span class="inline-flex rounded-lg bg-gray-100 px-2 py-1 text-[10px] font-black uppercase tracking-wider text-gray-600">Paper {{ $paper['order'] }}</span>
                            <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $item['complete'] ? 'bg-green-100 text-green-800' : ($item['status'] === 'In progress' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600') }}">{{ $item['status'] }}</span>
                        </div>
                        <h4 class="mt-4 text-base font-black leading-6 text-gray-900">{{ $paper['label'] }}</h4>
                        <p class="mt-2 text-xs leading-5 text-gray-500">{{ $paper['description'] }}</p>

                        <div class="mt-4 min-h-10 text-xs text-gray-600">
                            @if ($paper['mode'] === 'automatic')
                                <p class="font-semibold">PDF prepared automatically from Project Details when the package is turned in.</p>
                            @elseif ($item['documents']->isNotEmpty())
                                @if ($paper['mode'] === 'generated')
                                    <p class="font-semibold">{{ $item['submission_filename'] }}</p>
                                    <p class="mt-1 text-[11px] text-gray-500">{{ $submissionFormat }} ready to generate &middot; Saved {{ $item['documents']->first()->updated_at->diffForHumans() }}</p>
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
                            <div class="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-4">
                                @if ($template)<a href="{{ route('proposal-templates.download', $template) }}" class="text-xs font-bold text-red-600 underline decoration-red-200 underline-offset-4 hover:text-red-700">Download template</a>@endif
                                @if ($sampleAvailable)<a href="{{ route('proposal-samples.show', $paper['sample_slug']) }}" target="_blank" rel="noopener" class="text-xs font-bold text-red-600 underline decoration-red-200 underline-offset-4 hover:text-red-700">View sample</a>@endif
                            </div>
                        @endif

                        <a href="{{ $paperRoute }}" class="mt-auto inline-flex w-full items-center justify-center rounded-xl bg-gray-900 px-4 py-3 text-xs font-bold text-white transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2" aria-label="{{ $paperAction }}">{{ $paperAction }}</a>
                    </article>
                @endforeach
            </div>

            <div class="mt-6 flex flex-col gap-3 rounded-2xl bg-gray-900 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                <div><p class="font-black text-white">Ready to turn in the proposal?</p><p class="mt-1 text-xs text-gray-300">Review the six PDFs and Excel Expense Breakdown before sending the immutable package.</p></div>
                <button type="button" x-on:click="$dispatch('open-modal', 'proposal-review')" class="inline-flex w-full shrink-0 items-center justify-center rounded-xl bg-white px-5 py-3 text-sm font-bold text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-gray-900 sm:w-auto">Review &amp; turn in</button>
            </div>
        </section>

        <section id="proposal-collaborators-tab" x-show="activeProposalTab === 'collaborators'" x-cloak role="tabpanel" aria-labelledby="proposal-collaborators-tab-button" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 id="workspace-members-heading" class="text-lg font-black text-gray-900 dark:text-white">Proposal collaborators</h3>
                    <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-500 dark:text-slate-400">Invite a teammate using their BatStateU Google email. Existing ATHENA accounts receive an in-app invitation to accept; new accounts are connected automatically on their first verified sign-in.</p>
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    <span class="inline-flex w-fit rounded-full bg-gray-100 px-3 py-1.5 text-xs font-black text-gray-700 dark:bg-slate-800 dark:text-slate-200">{{ 1 + $proposalDraft->members->count() }} {{ Str::plural('member', 1 + $proposalDraft->members->count()) }}</span>
                    @can('manageMembers', $proposalDraft)
                        <button type="button" data-open-collaborator-modal x-on:click="$dispatch('open-modal', 'proposal-collaborator-invitation')" class="inline-flex items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-xs font-bold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" /></svg>
                            Add collaborator
                        </button>
                    @endcan
                </div>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <article class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-950 dark:bg-red-950/30">
                    <div class="flex items-start justify-between gap-3"><p class="font-black text-gray-900 dark:text-white">{{ $proposalDraft->owner->name }}</p><span class="rounded-full bg-red-600 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-white">Owner</span></div>
                    <p class="mt-1 break-all text-xs text-gray-600 dark:text-slate-300">{{ $proposalDraft->owner->email }}</p>
                    <p class="mt-3 text-[11px] font-semibold text-red-800 dark:text-red-200">Full workspace, invitation, submission, and deletion control</p>
                </article>
                @foreach ($proposalDraft->members as $member)
                    <article class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-slate-700 dark:bg-slate-800/70">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0"><p class="truncate font-black text-gray-900 dark:text-white">{{ $member->user?->name ?? $member->name }}</p><p class="mt-1 break-all text-xs text-gray-600 dark:text-slate-300">{{ $member->user?->email ?? $member->email }}</p></div>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-wider {{ $member->isAccepted() ? 'bg-gray-200 text-gray-700 dark:bg-slate-700 dark:text-slate-200' : 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-200' }}">{{ $member->isAccepted() ? 'Joined' : ($member->isLinked() ? 'Invitation pending' : 'Pending sign-in') }}</span>
                        </div>
                        <p class="mt-3 text-[11px] font-semibold {{ $member->isAccepted() ? 'text-gray-600 dark:text-slate-300' : 'text-red-700 dark:text-red-200' }}">{{ $member->isAccepted() ? 'Can open and edit every draft paper.' : ($member->isLinked() ? 'Waiting for the collaborator to accept the invitation.' : 'Waiting for this exact email to sign in to ATHENA.') }}</p>
                        @can('manageMembers', $proposalDraft)
                            <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-gray-200 pt-3 dark:border-slate-700">
                                <form action="{{ route('faculty.proposal-drafts.members.invitation', [$proposalDraft, $member]) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="text-xs font-bold text-red-700 hover:text-red-800 focus:outline-none focus:ring-2 focus:ring-red-600">Resend invitation</button>
                                </form>
                                <form action="{{ route('faculty.proposal-drafts.members.destroy', [$proposalDraft, $member]) }}" method="POST" data-proposal-confirm data-confirm-title="Remove collaborator?" data-confirm-text="This collaborator will immediately lose access to the proposal workspace." data-confirm-button="Remove collaborator">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-bold text-red-700 hover:text-red-800 focus:outline-none focus:ring-2 focus:ring-red-600">Remove</button>
                                </form>
                            </div>
                        @endcan
                    </article>
                @endforeach
            </div>

        </section>
    </div>

    @can('manageMembers', $proposalDraft)
        <x-modal name="proposal-collaborator-invitation" :show="$memberInvitationHasErrors" maxWidth="xl" focusable>
            <div
                x-data="proposalDraftMembers({ candidates: @js($memberCandidates) })"
                data-collaborator-invitation-modal
                data-opened-from-validation="{{ $memberInvitationHasErrors ? 'true' : 'false' }}"
                class="bg-white dark:bg-slate-900"
            >
                <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-slate-700 sm:px-6">
                    <div>
                        <h2 class="text-lg font-black text-gray-900 dark:text-white">Add collaborator</h2>
                        <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-slate-400">Invite a teammate using the exact BatStateU Google account they use for ATHENA.</p>
                    </div>
                    <button type="button" x-on:click="$dispatch('close-modal', 'proposal-collaborator-invitation')" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Close collaborator invitation" title="Close">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18" /></svg>
                    </button>
                </div>

                <form action="{{ route('faculty.proposal-drafts.members.store', $proposalDraft) }}" method="POST">
                    @csrf
                    <div class="space-y-5 px-5 py-5 sm:px-6">
                        <div>
                            <label for="workspace-member-email" class="block text-xs font-black uppercase tracking-wider text-gray-700 dark:text-slate-300">BatStateU Google email</label>
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
                                    class="block w-full rounded-xl border-gray-300 py-2.5 pl-3 pr-11 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
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
                                            <span class="relative flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-red-100 text-xs font-black text-red-700 ring-1 ring-red-200 dark:bg-red-950 dark:text-red-200 dark:ring-red-900">
                                                <img x-show="candidate.avatar" x-bind:src="candidate.avatar" x-bind:alt="candidate.name" x-on:error="candidate.avatar = ''" class="h-full w-full object-cover">
                                                <span x-show="!candidate.avatar" x-text="candidateInitials(candidate.name)"></span>
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-sm font-bold text-gray-900 dark:text-white" x-text="candidate.name"></span>
                                                <span class="block truncate text-xs text-gray-500 dark:text-slate-400" x-text="candidate.email"></span>
                                            </span>
                                            <svg class="h-4 w-4 shrink-0 text-red-600 opacity-0" x-bind:class="{ 'opacity-100': matchedAccount?.email === candidate.email }" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>
                                        </button>
                                    </template>

                                    <div x-show="filteredCandidates.length === 0" class="px-3 py-4 text-center">
                                        <p class="text-sm font-bold text-gray-700 dark:text-slate-200">No matching ATHENA account</p>
                                        <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-slate-400">You can still invite the exact BatStateU Google email they will use to sign in.</p>
                                    </div>
                                </div>
                            </div>
                            <p class="mt-2 text-xs leading-5 text-gray-500 dark:text-slate-400">Choose an ATHENA account or enter the exact institutional email they will use to sign in.</p>
                            @error('email')<p class="mt-2 text-xs font-semibold text-red-700 dark:text-red-300">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="workspace-member-name" class="block text-xs font-black uppercase tracking-wider text-gray-700 dark:text-slate-300">Teammate name</label>
                            <input id="workspace-member-name" name="name" type="text" x-model="name" value="{{ old('name') }}" maxlength="255" required placeholder="Full name" class="mt-2 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-800 dark:text-white">
                            <p class="mt-2 text-xs leading-5 text-gray-500 dark:text-slate-400" x-text="matchedAccount ? 'Pulled from the linked ATHENA account.' : 'Used until their ATHENA account is linked.'"></p>
                            @error('name')<p class="mt-2 text-xs font-semibold text-red-700 dark:text-red-300">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-2 border-t border-gray-200 bg-gray-50 px-5 py-4 dark:border-slate-700 dark:bg-slate-800/70 sm:flex-row sm:justify-end sm:px-6">
                        <button type="button" x-on:click="$dispatch('close-modal', 'proposal-collaborator-invitation')" class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-bold text-gray-700 transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">Cancel</button>
                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-red-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">Send invitation</button>
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
                    <p class="mt-1 text-xs text-gray-500">Review the proposal package from top to bottom before submitting.</p>
                </div>
                <button type="button" x-on:click="$dispatch('close-modal', 'proposal-review')" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-600" aria-label="Close review and turn in" title="Close review and turn in">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18" /></svg>
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto bg-gray-50 px-4 py-5 sm:px-6 sm:py-6">
                @if ($errors->any())
                    <x-proposal-alert type="error">
                        <p class="font-black">This proposal package cannot be turned in yet.</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </x-proposal-alert>
                @elseif (! $readyToSubmit)
                    <div role="alert" class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                        <p class="font-black">Complete the items below before submitting.</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($readinessErrors as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                <div class="space-y-6">
                    @include('faculty.proposal-drafts._review-package', ['inModal' => true])
                </div>
            </div>
        </div>
    </x-modal>
</x-app-layout>
