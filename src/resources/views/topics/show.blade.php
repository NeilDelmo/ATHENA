<x-app-layout>
    @php
        $statusClass = match ($topic->status) {
            'approved' => 'bg-gray-950 text-white dark:bg-white dark:text-gray-950',
            'ready_for_signature' => 'bg-red-50 text-red-800',
            'rejected' => 'bg-red-50 text-red-700',
            'revision_requested' => 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300',
            'resubmitted', 'expert_review', 'for_final_decision' => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
            default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200',
        };
        $statusLabel = $topic->workflowStatusLabel();
        if ($topic->isAwaitingNoticeToProceed()) {
            $statusClass = 'bg-amber-100 text-amber-800';
            $statusLabel = 'Preparing final release';
        } elseif ($topic->isCompletedProject()) {
            $statusClass = 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200';
            $statusLabel = 'Completed - archived';
        }
        $backRoute = Auth::user()->isUsingWorkspace('research_head')
            ? route('research_head.dashboard')
            : (Auth::user()->isUsingWorkspace('faculty_researcher') ? route('research.index') : route('faculty.dashboard'));
        $canDecide = Auth::user()->isUsingWorkspace('research_head') && in_array($topic->status, ['pending', 'resubmitted', 'expert_review', 'for_final_decision', \App\Models\TopicProposal::STATUS_GAD_REVIEW, 'lrec_review'], true);
        $isResearchHead = Auth::user()->isUsingWorkspace('research_head');
        $canReturnToRevision = $isResearchHead && $topic->status === \App\Models\TopicProposal::STATUS_READY_FOR_SIGNATURE;
        $isFacultyWorkspace = Auth::user()->isUsingWorkspace('faculty');
        $isFacultyRevision = $isFacultyWorkspace && $topic->status === 'revision_requested' && $topic->user_id === Auth::id();
          $hasProjectAccess = $isResearchHead || $topic->isAccessibleTo(Auth::user());
          $canViewNoticeToProceed = ($topic->isAwaitingNoticeToProceed() || $topic->hasIssuedNoticeToProceed() || ($isResearchHead && $topic->status === 'ready_for_signature'))
              && $hasProjectAccess;
          $canViewMonitoring = ($topic->hasIssuedNoticeToProceed() || $topic->isCompletedProject())
              && $hasProjectAccess;
        $canAskAthenaAboutProposal = $topic->user_id === Auth::id() && Auth::user()->isUsingWorkspace(['faculty', 'faculty_researcher']);
        $resubmissionErrors = $errors->getBag('resubmission');
        $noticeToProceedErrors = $errors->hasAny([
            'notice_to_proceed',
            'signed_notice_to_proceed',
            'notice_date',
            'researcher_names',
            'researcher_names.*',
            'campus_line',
            'project_title',
            'resolution_number',
            'resolution_year',
            'approved_start_date',
            'approved_end_date',
            'approved_duration_months',
            'approved_budget',
            'issuing_officer_name',
            'issuing_officer_title',
            'issuing_officer_committee_role',
            'verifying_officer_name',
            'verifying_officer_title',
            'verifying_officer_committee_role',
        ]);
        $reviewTabHash = 'proposal-review';
        $initialTopicTab = $resubmissionErrors->any()
            || $errors->hasAny(['status', 'revision_file_ids', 'revision_file_notes.*', 'committee_comments.*', 'initial_clearance_confirmed', 'lrec_clearance_confirmed', 'signature_file_ids'])
            || ($isFacultyWorkspace && $topic->status === 'revision_requested')
            ? 'review'
            : (($noticeToProceedErrors || ($canViewNoticeToProceed && $errors->getBag('headUpload')->any()))
                ? 'notice'
                : (in_array(session('topic_tab'), ['details', 'review', 'notice', 'history', 'monitoring'], true) ? session('topic_tab') : null));
    @endphp

    <x-slot name="header">
        <div class="space-y-3">
            <x-back-link :fixed="$isFacultyWorkspace" href="{{ $backRoute }}">Back to dashboard</x-back-link>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <h2 class="text-2xl font-black tracking-tight text-gray-900">{{ $topic->title }}</h2>
                    <p class="mt-1 text-sm text-gray-600">Proposal #{{ $topic->id }} &middot; {{ $topic->user->name }} &middot; {{ $topic->researchCall?->title ?? 'Research proposal' }}</p>
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    @if ($topic->isDisseminationAvailable() && Auth::user()->isUsingWorkspace(['faculty_researcher', 'research_head']) && $hasProjectAccess)
                        <a href="{{ route('research.dissemination.show', $topic) }}" class="inline-flex items-center justify-center rounded-xl border border-red-200 px-3 py-2 text-sm font-bold text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-300">Find journals</a>
                    @endif
                    @if ($draftHistoryCount > 0 && ($isFacultyWorkspace || $isResearchHead))
                        <a href="{{ route('topics.draft-history.index', $topic) }}" class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50">Draft history ({{ $draftHistoryCount }})</a>
                    @endif
                    @if ($canAskAthenaAboutProposal)
                        <button type="button" @click="$store.researchAssistant.openWithContext({{ $topic->id }})" class="inline-flex items-center justify-center gap-2 rounded-xl border border-red-200 bg-white px-3 py-2 text-sm font-bold text-red-700 shadow-sm transition hover:bg-red-50">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.8 4.8 11 2l1.2 2.8L15 6l-2.8 1.2L11 10 9.8 7.2 7 6l2.8-1.2ZM16.9 13.9 18 11l1.1 2.9L22 15l-2.9 1.1L18 19l-1.1-2.9L14 15l2.9-1.1Z" />
                            </svg>
                            Ask Athena about this proposal
                        </button>
                    @endif
                    @unless ($isFacultyRevision)
                        <span class="rounded-full px-3 py-1.5 text-sm font-black {{ $statusClass }}">{{ $statusLabel }}</span>
                    @endunless
                </div>
            </div>
        </div>
            @unless ($canViewMonitoring)
                <x-proposal-workflow :topic="$topic" :version="$latestVersion" />
            @endunless
    </x-slot>

    <x-project-document-drawer :topic="$topic" :library="$projectDocumentLibrary" />

    <div
        class="mx-auto max-w-7xl space-y-6"
        x-data="{
            activeTopicTab: @js($initialTopicTab) || (
                ['#proposal-review', '#submit-revision', '#review-and-submit'].includes(window.location.hash) || window.location.hash.startsWith('#file-review-card-')
                    ? 'review'
                    : window.location.hash === '#notice-to-proceed'
                        ? 'notice'
                    : (window.location.hash === '#project-monitoring' || window.location.hash.startsWith('#monitoring-tool-'))
                        ? 'monitoring'
                        : window.location.hash === '#version-history'
                        ? 'history'
                        : @js($canDecide ? 'review' : 'details')
            ),
            setTopicTab(tab, hash) {
                this.activeTopicTab = tab;
                window.location.hash = hash;
            },
            syncTopicTab() {
                if (['#proposal-review', '#submit-revision', '#review-and-submit'].includes(window.location.hash) || window.location.hash.startsWith('#file-review-card-')) {
                    this.activeTopicTab = 'review';
                } else if (window.location.hash === '#notice-to-proceed') {
                    this.activeTopicTab = 'notice';
                } else if (window.location.hash === '#project-monitoring' || window.location.hash.startsWith('#monitoring-tool-')) {
                    this.activeTopicTab = 'monitoring';
                } else if (window.location.hash === '#version-history') {
                    this.activeTopicTab = 'history';
                } else {
                    this.activeTopicTab = 'details';
                }
                this.scrollToTopicHash();
            },
            init() {
                this.scrollToTopicHash();
            },
            scrollToTopicHash() {
                if (['#submit-revision', '#review-and-submit'].includes(window.location.hash) || window.location.hash.startsWith('#file-review-card-')) {
                    this.$nextTick(() => {
                        const card = document.getElementById(window.location.hash.slice(1));
                        if (!card) {
                            return;
                        }
                        const top = card.getBoundingClientRect().top + window.scrollY - 130;
                        window.scrollTo({ top: Math.max(top, 0), behavior: 'smooth' });
                    });

                    return;
                }

                this.scrollToProjectMonitoring();
            },
            scrollToProjectMonitoring() {
                if (window.location.hash !== '#project-monitoring' && ! window.location.hash.startsWith('#monitoring-tool-')) {
                    return;
                }
                this.$nextTick(() => {
                    const targetId = window.location.hash.startsWith('#monitoring-tool-')
                        ? window.location.hash.slice(1)
                        : 'project-monitoring';
                    const section = document.getElementById(targetId) || document.getElementById('project-monitoring');
                    if (!section) {
                        return;
                    }
                    const top = section.getBoundingClientRect().top + window.scrollY - 130;
                    window.scrollTo({ top: Math.max(top, 0), behavior: 'smooth' });
                });
            },
        }"
        @hashchange.window="syncTopicTab()"
    >
        @if (session('revision_submitted'))
            <div id="revision-submitted" data-revision-submission-success role="status" class="rounded-2xl border border-green-200 bg-green-50 p-5 text-green-950 shadow-sm dark:border-green-900 dark:bg-green-950/40 dark:text-green-100">
                <div class="flex items-start gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-green-700 text-white" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4 4L19 7" /></svg>
                    </span>
                    <div>
                        <p class="font-black">Revision sent successfully</p>
                        <p class="mt-1 text-sm leading-6">{{ session('success') }} It is now waiting for the Research Head’s review.</p>
                    </div>
                </div>
            </div>
        @elseif (session('success'))
            <div class="rounded-2xl border border-gray-950 bg-gray-950 px-4 py-3 text-sm font-semibold text-white dark:border-gray-700 dark:bg-white dark:text-gray-950">{{ session('success') }}</div>
        @endif
        @if ($errors->any() || $resubmissionErrors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <p class="font-bold">The action could not be completed.</p>
                <p class="mt-1 text-sm">{{ $resubmissionErrors->first() ?: $errors->first() }}</p>
            </div>
        @endif

        <div class="overflow-x-auto border-b border-gray-200" role="tablist" aria-label="Proposal workspace sections">
            <nav class="flex min-w-max gap-6">
                <button id="proposal-details-tab-button" type="button" role="tab" aria-controls="proposal-details-tab" :aria-selected="activeTopicTab === 'details'" @click="setTopicTab('details', 'proposal-details')" :class="activeTopicTab === 'details' ? 'border-red-600 text-red-600' : 'border-transparent text-gray-600 hover:border-red-300 hover:text-red-600'" class="flex items-center gap-2 border-b-2 px-1 pb-3 text-sm font-bold transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5.25A2.25 2.25 0 0 1 6.25 3h11.5A2.25 2.25 0 0 1 20 5.25v13.5A2.25 2.25 0 0 1 17.75 21H6.25A2.25 2.25 0 0 1 4 18.75V5.25Z" /><path stroke-linecap="round" d="M8 8h8M8 12h8M8 16h5" /></svg>
                    Proposal
                </button>
                <button id="proposal-review-tab-button" type="button" role="tab" aria-controls="proposal-review-tab" :aria-selected="activeTopicTab === 'review'" @click="setTopicTab('review', '{{ $reviewTabHash }}')" :class="activeTopicTab === 'review' ? 'border-red-600 text-red-600' : 'border-transparent text-gray-600 hover:border-red-300 hover:text-red-600'" class="flex items-center gap-2 border-b-2 px-1 pb-3 text-sm font-bold transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h10.5A2.25 2.25 0 0 1 19.5 6v14.25H4.5V6a2.25 2.25 0 0 1 2.25-2.25Z" /><path stroke-linecap="round" d="M8.25 9.5h7.5M8.25 13h5.25" /></svg>
                    {{ $isResearchHead ? 'Review & decision' : 'Review status' }}
                </button>
                @if ($canViewNoticeToProceed)
                    <button id="notice-to-proceed-tab-button" type="button" role="tab" aria-controls="notice-to-proceed-tab" :aria-selected="activeTopicTab === 'notice'" @click="setTopicTab('notice', 'notice-to-proceed')" :class="activeTopicTab === 'notice' ? 'border-red-600 text-red-600' : 'border-transparent text-gray-600 hover:border-red-300 hover:text-red-600'" class="flex items-center gap-2 border-b-2 px-1 pb-3 text-sm font-bold transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /><path stroke-linecap="round" d="M9 13.5l2 2 4-4" /></svg>
                        {{ $topic->hasIssuedNoticeToProceed() ? 'Released documents' : 'Signing & release' }}
                    </button>
                @endif
                <button id="version-history-tab-button" type="button" role="tab" aria-controls="version-history-tab" :aria-selected="activeTopicTab === 'history'" @click="setTopicTab('history', 'version-history')" :class="activeTopicTab === 'history' ? 'border-red-600 text-red-600' : 'border-transparent text-gray-600 hover:border-red-300 hover:text-red-600'" class="flex items-center gap-2 border-b-2 px-1 pb-3 text-sm font-bold transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    Versions
                </button>
                @if ($canViewMonitoring)
                    <button id="project-monitoring-tab-button" type="button" role="tab" aria-controls="project-monitoring-tab" :aria-selected="activeTopicTab === 'monitoring'" @click="setTopicTab('monitoring', 'project-monitoring')" :class="activeTopicTab === 'monitoring' ? 'border-red-600 text-red-600' : 'border-transparent text-gray-600 hover:border-red-300 hover:text-red-600'" class="flex items-center gap-2 border-b-2 px-1 pb-3 text-sm font-bold transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 19.5V10m5.25 9.5V4.5m5.25 15v-7m5.25 7V7" /></svg>
                        Monitoring
                    </button>
                @endif
            </nav>
        </div>

        <section id="proposal-details-tab" x-show="activeTopicTab === 'details'" x-cloak role="tabpanel" aria-labelledby="proposal-details-tab-button">
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <section id="submitted-files" aria-labelledby="submitted-files-heading" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                        <div>
                            <p class="text-xs font-black uppercase tracking-wider text-red-600">Latest faculty submission</p>
                            <h3 id="submitted-files-heading" class="mt-1 text-lg font-black text-gray-900">Proposal package</h3>
                            <p class="mt-1 text-sm text-gray-600">
                                @if ($latestVersion)
                                    Version {{ $latestVersion->version_number }} submitted by {{ $latestVersion->submitter?->name ?? $topic->user->name }} on {{ $latestVersion->created_at->format('M j, Y g:i A') }}.
                                @else
                                    No submitted version is available.
                                @endif
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex w-fit rounded-full px-3 py-1.5 text-xs font-black uppercase tracking-wider {{ $availableSubmittedFileIds->count() === $submittedFiles->count() && $submittedFiles->isNotEmpty() ? 'bg-gray-950 text-white dark:bg-white dark:text-gray-950' : 'bg-red-50 text-red-800 dark:bg-red-950/40 dark:text-red-200' }}">
                                {{ $availableSubmittedFileIds->count() }}/{{ $submittedFiles->count() }} files available
                            </span>
                            <button type="button" @click="$dispatch('open-project-documents')" class="inline-flex min-h-9 items-center gap-2 rounded-xl border border-gray-300 bg-white px-3 py-1.5 text-xs font-black text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75A2.25 2.25 0 0 1 6 4.5h3.19c.597 0 1.17.237 1.591.659l1.06 1.06c.422.422.994.659 1.591.659H18A2.25 2.25 0 0 1 20.25 9.13v7.62A2.25 2.25 0 0 1 18 19H6a2.25 2.25 0 0 1-2.25-2.25v-10Z" /></svg>
                                Open files
                            </button>
                        </div>
                    </div>

                    <div class="p-5 sm:p-6" data-latest-package-summary="{{ $latestVersion?->id }}">
                        <div class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-900 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h4 class="text-sm font-black text-gray-950 dark:text-white">Latest submitted package</h4>
                                <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-600 dark:text-gray-300">
                                    @if ($latestVersion)
                                        Version {{ $latestVersion->version_number }} contains {{ $submittedFiles->count() }} required {{ \Illuminate\Support\Str::plural('paper', $submittedFiles->count()) }}. Open the project folder to view these files together with signed papers, review responses, the Notice to Proceed, and later project records.
                                    @else
                                        No submitted package is available yet. Generated and uploaded PDFs will appear in the project folder.
                                    @endif
                                </p>
                            </div>
                            <button type="button" @click="$dispatch('open-project-documents')" class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-gray-950 px-4 py-2.5 text-sm font-black text-white transition hover:bg-gray-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-900 focus-visible:ring-offset-2 dark:bg-white dark:text-gray-950">
                                Browse {{ $projectDocumentLibrary['total'] }} {{ \Illuminate\Support\Str::plural('file', $projectDocumentLibrary['total']) }}
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" /></svg>
                            </button>
                        </div>
                    </div>
                </section>

                <section aria-labelledby="research-details-heading" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h3 id="research-details-heading" class="text-sm font-black text-gray-900">Research details</h3>
                    <dl class="mt-4 space-y-4">
                        <div>
                            <dt class="text-xs font-bold uppercase text-gray-500">Total project cost</dt>
                            <dd class="mt-1 text-lg font-black text-gray-900">PHP {{ number_format($displayProjectCost, 2) }}</dd>
                        </div>
                        <div class="border-t border-gray-100 pt-3">
                            <dt class="text-xs font-bold uppercase text-gray-500">Duration</dt>
                            <dd class="mt-1 text-sm font-bold text-gray-700">{{ $topic->estimated_duration_months }} months</dd>
                        </div>
                        @if ($topic->category)
                            <div class="border-t border-gray-100 pt-3">
                                <dt class="text-xs font-bold uppercase text-gray-500">Category</dt>
                                <dd class="mt-1 text-sm font-bold text-gray-700">{{ $topic->category->name }}</dd>
                            </div>
                        @endif
                    </dl>
                    <p class="mt-4 whitespace-pre-line border-t border-gray-100 pt-4 text-sm leading-6 text-gray-600">{{ $topic->description ?: 'No proposal summary provided.' }}</p>
                </section>

                @if ($topic->collaborators->isNotEmpty())
                    <section aria-labelledby="project-team-heading" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-black uppercase tracking-wider text-red-600">Shared workspace</p>
                        <h3 id="project-team-heading" class="mt-1 text-sm font-black text-gray-900">Project team</h3>
                        <p class="mt-1 text-xs leading-5 text-gray-500">The same team remains attached through review, approval, and project monitoring.</p>
                        <ul class="mt-4 space-y-3">
                            <li class="flex items-start gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-950 text-xs font-black text-white">PL</span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-bold text-gray-900">{{ $topic->user->name }}</span>
                                    <span class="block text-xs text-gray-500">Project leader</span>
                                </span>
                            </li>
                            @foreach ($topic->collaborators as $collaborator)
                                <li class="flex items-start gap-3">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-red-50 text-xs font-black text-red-700">TM</span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-bold text-gray-900">{{ $collaborator->name }}</span>
                                        <span class="block truncate text-xs text-gray-500">{{ $collaborator->email }}</span>
                                        <span class="mt-0.5 block text-xs font-semibold {{ $collaborator->accepted_at ? 'text-emerald-700' : 'text-amber-700' }}">{{ $collaborator->accepted_at ? 'Accepted team member' : 'Invitation pending' }}</span>
                                        @if ($collaborator->isProjectSecretary() || $collaborator->user_id === $topic->research_secretary_id)
                                            <span class="mt-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-amber-800">Project Secretary</span>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>
        </section>

        @php
            $initialResearchHeadDecision = old('status', request()->query('decision') === 'revision_requested' ? 'revision_requested' : '');
            if (! array_key_exists($initialResearchHeadDecision, $researchHeadDecisionOptions)) {
                $initialResearchHeadDecision = '';
            }
        @endphp
        <section id="proposal-review-tab" x-data="{ decision: @js($initialResearchHeadDecision) }" x-show="activeTopicTab === 'review'" x-cloak role="tabpanel" aria-labelledby="proposal-review-tab-button" class="space-y-4">
            @if ($canDecide)
                <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900 sm:p-6" aria-labelledby="file-review-checklist-heading">
                    <h3 id="file-review-checklist-heading" class="text-base font-semibold text-gray-900 dark:text-gray-100">Review submitted papers <span class="ml-2 text-sm font-normal text-gray-500">Version {{ $latestVersion?->version_number ?? 1 }}</span></h3>
                    @include('topics.partials.revision-file-selector', ['files' => $submittedFiles, 'disableUnlessRevision' => true, 'decisionFormId' => 'research-head-decision-form', 'prioritizeRevisedFiles' => true])
                    @error('revision_file_ids')<p class="mt-4 text-sm font-semibold text-red-600">{{ $message }}</p>@enderror
                </section>
            @endif
            @if ($isFacultyRevision)
                <section data-faculty-revision-summary class="overflow-hidden rounded-2xl border border-red-200 bg-white shadow-sm dark:border-red-950 dark:bg-slate-950">
                    <div class="grid lg:grid-cols-[minmax(0,1fr)_18rem]">
                        <div class="border-b border-red-100 p-6 dark:border-red-950 lg:border-b-0 lg:border-r lg:p-8">
                            <div class="flex items-start gap-4">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-red-700 text-white" aria-hidden="true">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m8.25-.75a8.25 8.25 0 1 1-16.5 0 8.25 8.25 0 0 1 16.5 0ZM12 16.5h.008v.008H12V16.5Z" /></svg>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-red-700 dark:text-red-300">Your action is required</p>
                                    <h3 class="mt-1 text-2xl font-black tracking-tight text-gray-950 dark:text-white">Revise and resubmit this proposal</h3>
                                    <p class="mt-3 max-w-3xl text-base leading-7 text-gray-600 dark:text-slate-300">The correction tools now have their own workspace, so the feedback, document editor, responses, and final submission stay together without crowding this project record.</p>
                                    @if ($latestRevisionReview?->comment)
                                        <blockquote class="mt-5 border-l-4 border-red-200 pl-4 text-sm leading-6 text-gray-800 dark:border-red-900 dark:text-slate-100">{{ $latestRevisionReview->comment }}</blockquote>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col justify-between gap-6 bg-red-50/60 p-6 dark:bg-red-950/20 lg:p-8">
                            <dl class="grid grid-cols-2 gap-5">
                                <div><dt class="text-xs font-semibold text-gray-500 dark:text-slate-400">Requested papers</dt><dd class="mt-1 text-2xl font-black tabular-nums text-gray-950 dark:text-white">{{ $pendingFileRevisions->groupBy('document_type')->count() }}</dd></div>
                                <div><dt class="text-xs font-semibold text-gray-500 dark:text-slate-400">Review comments</dt><dd class="mt-1 text-2xl font-black tabular-nums text-gray-950 dark:text-white">{{ count($commentResponseRows) }}</dd></div>
                            </dl>
                            <a href="{{ route('faculty.topics.revision', $topic) }}" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-red-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-950">
                                Open revision workspace
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" /></svg>
                            </a>
                        </div>
                    </div>
                </section>
            @endif

            @if (! $isResearchHead && ! $isFacultyRevision)
                <div class="rounded-2xl border border-red-200 bg-red-50 p-5 sm:p-6">
                    <h3 class="text-lg font-black text-gray-900">What happens next</h3>
                    <p class="mt-2 max-w-4xl text-sm leading-6 text-gray-700">
                        @if ($topic->status === 'revision_requested')
                            The Research Head requested changes. Review the highlighted comments and file-specific instructions, then replace only the files marked for revision.
                        @elseif ($topic->isAwaitingNoticeToProceed())
                            Your proposal papers are approved. Wait for the Research Head to issue the Notice to Proceed before beginning the project or entering monitoring.
                        @elseif ($topic->isCompletedProject())
                            This project is complete and archived. Its approved papers, Notice to Proceed, and previous monitoring records remain available as read-only records.
                        @elseif ($topic->status === 'approved')
                            Your Notice to Proceed has been issued. The proposal is now an active research project and monitoring is open.
                        @elseif ($topic->status === \App\Models\TopicProposal::STATUS_READY_FOR_SIGNATURE)
                            Reviews are complete. The research office is collecting signed papers and the signed Notice to Proceed. They will be released together.
                        @elseif ($topic->status === 'lrec_queued')
                            Initial review is complete. Await the LREC presentation schedule from the research office.
                        @elseif ($topic->status === 'lrec_review')
                            The proposal is under LREC review. The research office will share committee revisions or move it to signing.
                        @elseif ($topic->status === 'rejected')
                            This proposal received a final rejection.
                        @else
                            The proposal is with the Research Head. You will be notified when a decision or revision request is shared.
                        @endif
                    </p>
                </div>
            @endif

            @if ($isResearchHead && ! $canViewNoticeToProceed && $headUploadWorkspace && $topic->status !== \App\Models\TopicProposal::STATUS_READY_FOR_SIGNATURE)
                <x-research-head-file-workspace :topic="$topic" :workspace="$headUploadWorkspace" :show-faculty-files="! $canDecide" />
            @endif

            @if ($reviewDocuments->isNotEmpty())
                <section x-data="{ open: false }" data-review-documents-disclosure data-initially-open="false" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-950">
                    <button type="button" @click="open = !open" :aria-expanded="open" aria-controls="review-documents-content" class="flex min-h-16 w-full items-center justify-between gap-4 px-5 py-4 text-left transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-red-700 dark:hover:bg-gray-900 sm:px-6">
                        <span class="flex items-start gap-4 sm:items-center">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-600 dark:bg-gray-900 dark:text-gray-300" aria-hidden="true">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                            </span>
                            <span>
                                <span class="block text-xl font-bold text-gray-950 dark:text-white">Research Head documents</span>
                                <span class="mt-1 block text-base font-normal leading-6 text-gray-600 dark:text-gray-300">{{ $reviewDocuments->count() }} document(s) shared by the Research Head.</span>
                            </span>
                        </span>
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600 shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-300" aria-hidden="true"><svg :class="open ? 'rotate-180' : ''" class="h-5 w-5 transition-transform duration-200 motion-reduce:transition-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg></span>
                    </button>
                    <div id="review-documents-content" x-show="open" x-cloak x-transition class="border-t border-gray-100 dark:border-gray-800">
                        <div class="divide-y divide-gray-100">
                            @foreach ($reviewDocuments as $reviewDocument)
                            @php
                                $reviewDocumentAvailable = $availableReviewDocumentIds->contains($reviewDocument->id);
                                $reviewDocumentViewable = $viewableReviewDocumentIds->contains($reviewDocument->id);
                                $documentDecision = $reviewDocument->source_data['decision'] ?? null;
                                $documentPurpose = $reviewDocument->source_data['purpose'] ?? null;
                            @endphp
                            <article class="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="text-lg font-bold text-gray-950 dark:text-white">{{ $reviewDocument->label() }}</h4>
                                        @if ($documentDecision)
                                            <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-black text-red-700">{{ str($documentDecision)->replace('_', ' ')->title() }}</span>
                                        @endif
                                        @if ($documentPurpose)
                                            <span class="rounded-full px-2.5 py-1 text-xs font-black {{ $documentPurpose === \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED ? 'bg-gray-950 text-white dark:bg-white dark:text-gray-950' : ($documentPurpose === \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_REVISION ? 'bg-red-50 text-red-800 dark:bg-red-950/40 dark:text-red-200' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200') }}">{{ $reviewDocument->headUploadPurposeLabel() }}</span>
                                        @endif
                                    </div>
                                    <p class="mt-1 break-all text-sm font-semibold text-gray-600">{{ $reviewDocument->original_filename }}</p>
                                    <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">Shared by {{ $reviewDocument->uploadedBy?->name ?? 'Research Head' }} on {{ $reviewDocument->created_at->format('M j, Y g:i A') }}</p>
                                </div>
                                <div class="flex w-full shrink-0 gap-2 sm:w-auto">
                                    @if ($reviewDocumentViewable)
                                        <a href="{{ route('topics.versions.files.view', [$topic, $latestVersion, $reviewDocument]) }}" target="_blank" rel="noopener" class="inline-flex flex-1 items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-50 sm:flex-none">View</a>
                                    @endif
                                    @if ($reviewDocumentAvailable)
                                        <a href="{{ route('topics.versions.files.download', [$topic, $latestVersion, $reviewDocument]) }}" class="inline-flex flex-1 items-center justify-center rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-gray-800 sm:flex-none">Download</a>
                                    @endif
                                </div>
                            </article>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endif

            @php
                $decisionReviews = $topic->reviews
                    ->where('decision', '!=', 'head_upload')
                    ->sortByDesc('created_at')
                    ->values();
                $latestDecisionReview = $decisionReviews->first();
            @endphp
            <section x-data="{ open: false }" data-decision-history data-initially-open="false" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-950">
                <button type="button" @click="open = !open" :aria-expanded="open" aria-controls="decision-history-list" class="flex min-h-16 w-full items-center justify-between gap-4 px-5 py-4 text-left transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-red-700 dark:hover:bg-gray-900 sm:px-6">
                    <span class="flex min-w-0 items-start gap-4 sm:items-center">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-500 dark:bg-gray-900 dark:text-gray-400" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                        </span>
                        <span class="min-w-0">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="text-xl font-bold text-gray-950 dark:text-white">Decision history</span>
                                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-sm font-bold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $decisionReviews->count() }} {{ str('decision')->plural($decisionReviews->count()) }}</span>
                            </span>
                            <span class="mt-1 block truncate text-base font-normal text-gray-600 dark:text-gray-400">
                                @if ($latestDecisionReview)
                                    Latest: <span class="font-bold text-gray-800 dark:text-gray-200">{{ str($latestDecisionReview->decision)->replace('_', ' ')->title() }}</span>
                                    <span aria-hidden="true">&middot;</span>
                                    <time datetime="{{ $latestDecisionReview->created_at->toIso8601String() }}">{{ $latestDecisionReview->created_at->format('M j, Y') }}</time>
                                @else
                                    No Research Head decisions recorded yet.
                                @endif
                            </span>
                        </span>
                    </span>
                    <span class="flex shrink-0 items-center gap-3 text-gray-500 dark:text-gray-400">
                        <span class="hidden text-sm font-bold sm:inline" x-text="open ? 'Hide history' : 'View history'">View history</span>
                        <span class="flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-950" aria-hidden="true"><svg :class="open ? 'rotate-180' : ''" class="h-5 w-5 transition-transform duration-200 motion-reduce:transition-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg></span>
                    </span>
                </button>
                <div id="decision-history-list" x-show="open" x-cloak x-transition class="max-h-[42rem] overflow-y-auto overscroll-contain border-t border-gray-100 dark:border-gray-800" data-decision-history-list>
                    @if ($decisionReviews->isNotEmpty())
                        <ol class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($decisionReviews as $review)
                            <li class="p-5 sm:p-6">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ str($review->decision)->replace('_', ' ')->title() }}</p>
                                        @if ($loop->first)
                                            <span class="rounded-full bg-red-50 px-2.5 py-1 text-sm font-bold text-red-700 dark:bg-red-950/40 dark:text-red-300">Latest</span>
                                        @endif
                                    </div>
                                    <time datetime="{{ $review->created_at->toIso8601String() }}" class="text-sm text-gray-500 dark:text-gray-400">{{ $review->created_at->format('M d, Y h:i A') }}</time>
                                </div>
                                <p class="mt-1 text-sm font-semibold text-gray-500 dark:text-gray-400">{{ $review->reviewer?->name ?? 'Former Research Head' }}</p>
                                @if ($review->comment)
                                    <div class="mt-3 rounded-xl bg-gray-50 p-4 text-base leading-7 text-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                        @if ($review->decision === 'rejected')
                                            <p class="text-sm font-bold text-red-700 dark:text-red-300">Rejection reason</p>
                                        @endif
                                        <p @class(['whitespace-pre-line', 'mt-1' => $review->decision === 'rejected'])>{{ $review->comment }}</p>
                                    </div>
                                @endif
                                <p class="mt-2 text-sm text-gray-500">{{ $review->review_stage === 'lrec' ? 'LREC review' : 'Initial review' }}</p>
                                @if ($review->decision === 'revision_requested')
                                    @can('generateCommentResponseForm', $topic)
                                        <a href="{{ route(($isResearchHead ? 'research_head' : 'faculty').'.topics.comment-response-form.pdf', ['topic' => $topic, 'review' => $review->id]) }}" class="mt-2 inline-block text-sm font-semibold text-red-700 dark:text-red-300">Download this round’s Comment-Response PDF</a>
                                    @endcan
                                    @foreach ($review->committee_comments ?? [] as $commentIndex => $committeeComment)
                                        <div class="mt-3 rounded-xl border border-gray-200 p-4 text-base leading-7 dark:border-slate-700">
                                            <p class="font-semibold">LREC · {{ $committeeComment['reviewer'] }}</p>
                                            <p class="text-sm text-gray-500">{{ $committeeComment['location'] ?? '' }}</p>
                                            <p class="mt-2 whitespace-pre-line">{{ $committeeComment['comment'] }}</p>
                                            @if ($answer = ($review->feedback_responses['committee_'.$commentIndex] ?? null))
                                                <p class="mt-2 font-semibold">Faculty response</p><p class="whitespace-pre-line">{{ $answer['response'] }}</p><p class="text-sm text-gray-500">{{ $answer['remarks'] ?? '' }}</p>
                                            @endif
                                        </div>
                                    @endforeach
                                @endif
                                @if ($review->fileRevisions->isNotEmpty())
                                    <div class="mt-3 space-y-2">
                                        @foreach ($review->fileRevisions as $fileRevision)
                                            @php
                                                $annotationVersion = $topic->versions->firstWhere('id', $fileRevision->file?->proposal_version_id);
                                            @endphp
                                            <div class="rounded-xl border px-4 py-4 text-base {{ $fileRevision->resolved_at ? 'border-gray-300 bg-gray-100 text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200' : 'border-red-300 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200' }}">
                                                <div class="flex flex-wrap items-center justify-between gap-2"><span class="font-bold">{{ $fileRevision->file?->label() ?? str($fileRevision->document_type)->replace('_', ' ')->title() }}</span><span class="text-sm font-bold">{{ ! $fileRevision->resolved_at ? 'Revision required' : ($fileRevision->resolution_type === 'no_file_change' ? 'Resolved — no file change' : 'Resolved — revised file') }}</span></div>
                                                <p class="mt-1 text-sm opacity-75">{{ $fileRevision->original_filename }}</p>
                                                @if ($fileRevision->revision_note)<p class="mt-2 leading-6">{{ $fileRevision->revision_note }}</p>@endif
                                                @if ($fileRevision->resolved_at && $fileRevision->faculty_response)<div class="mt-3 rounded-lg border border-blue-200 bg-blue-50 p-3 text-blue-900 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-200"><p class="text-sm font-bold">Faculty response</p><p class="mt-1 whitespace-pre-line leading-7">{{ $fileRevision->faculty_response }}</p></div>@endif
                                                @if ($fileRevision->annotations->isNotEmpty() && $annotationVersion && $fileRevision->file)
                                                    @php
                                                        $firstAnnotation = $fileRevision->annotations->sortBy([['page_number', 'asc'], ['id', 'asc']])->first();
                                                        $annotationDeepLink = route('topics.versions.files.annotations.index', [$topic, $annotationVersion, $fileRevision->file]).'?annotation='.$firstAnnotation->id.'#proposal-review';
                                                    @endphp
                                                    <a href="{{ $annotationDeepLink }}" class="mt-3 inline-flex min-h-11 items-center rounded-xl bg-red-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-red-800">View {{ $fileRevision->annotations->count() }} highlighted comment(s)</a>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </li>
                        @endforeach
                        </ol>
                    @else
                        <p class="p-6 text-center text-sm text-gray-500 dark:text-gray-400">No Research Head decision has been recorded.</p>
                    @endif
                </div>
            </section>

            @if ($isResearchHead && $topic->status === 'lrec_queued')
                <form action="{{ route('research_head.topics.updateStatus', $topic) }}" method="POST" class="space-y-3 rounded-xl border border-gray-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="lrec_review">
                    <input type="hidden" name="redirect_to" value="topic">
                    <h3 class="font-semibold text-gray-950 dark:text-white">Awaiting LREC presentation</h3>
                    <p class="text-sm text-gray-600 dark:text-slate-300">After the presentation, record the committee comments or its clearance for signing.</p>
                    <button type="submit" class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">Presentation complete — record outcome</button>
                </form>
            @endif

            @if ($canDecide)
                <section x-data="{ open: true }" data-review-decision-disclosure data-initially-open="true" class="overflow-hidden rounded-2xl border-2 border-red-200 bg-white shadow-lg dark:border-red-900 dark:bg-gray-950" data-latest-review-version="{{ $latestVersion?->version_number }}" data-latest-review-version-id="{{ $latestVersion?->id }}">
                    <button type="button" @click="open = !open" :aria-expanded="open" aria-controls="review-decision-content" class="flex min-h-16 w-full items-center justify-between gap-4 bg-red-50 px-5 py-4 text-left transition hover:bg-red-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-red-700 dark:bg-red-950/30 dark:hover:bg-red-950/50 sm:px-6">
                        <span class="flex items-start gap-4 sm:items-center">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-red-700 text-white" aria-hidden="true"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg></span>
                            <span>
                                <span class="block text-sm font-bold text-red-700 dark:text-red-300">Action required</span>
                                <span class="mt-0.5 block text-xl font-bold text-gray-950 dark:text-white">{{ $topic->review_stage === 'lrec' ? 'Record the LREC outcome' : 'Complete the ordered review route' }}</span>
                                @if ($latestVersion)
                                    <span class="mt-1 block text-sm font-semibold text-red-800 dark:text-red-200">Reviewing Version {{ $latestVersion->version_number }} &mdash; latest submitted package</span>
                                @endif
                            </span>
                        </span>
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-red-200 bg-white text-red-700 shadow-sm dark:border-red-900 dark:bg-gray-950 dark:text-red-300" aria-hidden="true"><svg :class="open ? 'rotate-180' : ''" class="h-5 w-5 transition-transform duration-200 motion-reduce:transition-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg></span>
                    </button>
                    <div id="review-decision-content" x-show="open" x-cloak x-transition class="border-t border-red-200 bg-white dark:border-red-900 dark:bg-gray-950">
                        <form
                            id="research-head-decision-form"
                            action="{{ route('research_head.topics.updateStatus', $topic) }}"
                            method="POST"
                            x-data="{
                                submitting: false,
                                async submitDecision(event) {
                                    const form = event.currentTarget;
                                    if (this.submitting || !form.reportValidity()) return;

                                    const confirmation = {
                                        rejected: {
                                            title: 'Reject this proposal?',
                                            text: 'This closes the submission and shares the rejection reason with the faculty member. It cannot continue to signing.',
                                            confirmButtonText: 'Reject proposal',
                                            confirmButtonColor: '#dc2626',
                                        },
                                    }[this.decision];

                                    if (confirmation) {
                                        const result = await window.Swal.fire({
                                            icon: 'warning',
                                            ...confirmation,
                                            showCancelButton: true,
                                            cancelButtonText: 'Go back',
                                            reverseButtons: true,
                                            focusCancel: true,
                                        });
                                        if (!result.isConfirmed) return;
                                    }

                                    this.submitting = true;
                                    HTMLFormElement.prototype.submit.call(form);
                                },
                            }"
                            @submit.prevent="submitDecision"
                            class="space-y-5 p-5 sm:p-6"
                        >
                            @csrf @method('PATCH')
                            @if ($errors->any())
                                <div role="alert" class="text-sm text-red-700 dark:text-red-300">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
                            @endif
                            <input type="hidden" name="redirect_to" value="topic">
                            <fieldset>
                                <legend class="mb-3 text-lg font-bold text-gray-950 dark:text-white">Review decision</legend>
                                <div class="flex flex-wrap gap-2" data-review-decision-options>
                                    @foreach ($researchHeadDecisionOptions as $decisionValue => $decisionLabel)
                                        <label
                                            class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border px-4 py-2.5 text-base font-semibold transition focus-within:ring-2 focus-within:ring-red-600 focus-within:ring-offset-2 dark:focus-within:ring-offset-gray-900"
                                            :class="decision === @js($decisionValue) ? 'border-red-700 bg-red-50 text-red-800 dark:border-red-500 dark:bg-red-950/30 dark:text-red-200' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800'"
                                        >
                                            <input class="h-4 w-4 border-gray-400 text-red-700 focus:ring-red-600" type="radio" name="status" value="{{ $decisionValue }}" x-model="decision" required>
                                            {{ $decisionLabel }}
                                        </label>
                                    @endforeach
                                </div>
                                @error('status')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            </fieldset>

                            <section x-show="decision === 'gad_review'" x-cloak>
                                <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-slate-200">
                                    <input type="checkbox" name="research_head_clearance_confirmed" value="1" :disabled="decision !== 'gad_review'" :required="decision === 'gad_review'" class="mt-1 rounded border-gray-300 text-red-700">
                                    <span>I reviewed the latest proposal version and confirm that all Research Head comments have been addressed. This version may proceed to the GAD Office.</span>
                                </label>
                            </section>
                            <section x-show="decision === 'lrec_queued'" x-cloak>
                                <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-slate-200">
                                    <input type="checkbox" name="initial_clearance_confirmed" value="1" :disabled="decision !== 'lrec_queued'" :required="decision === 'lrec_queued'" class="mt-1 rounded border-gray-300 text-red-700">
                                    <span>A passing GAD Office assessment and the central evaluator’s Narrative Evaluation are recorded for this version.</span>
                                </label>
                            </section>
                            <section x-show="decision === 'ready_for_signature'" x-cloak class="space-y-3">
                                <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-slate-200">
                                    <input type="checkbox" name="lrec_clearance_confirmed" value="1" :disabled="decision !== 'ready_for_signature'" :required="decision === 'ready_for_signature'" class="mt-1 rounded border-gray-300 text-red-700">
                                    <span>LREC has cleared this version and all committee comments have been addressed.</span>
                                </label>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Required signed papers</p>
                                <div class="space-y-2">
                                    @foreach ($submittedFiles->whereIn('document_type', \App\Services\ProposalSignatureWorkflow::REQUIRED_DOCUMENT_TYPES) as $signatureFile)
                                        <p class="text-sm text-gray-700 dark:text-slate-200">
                                            {{ $signatureFile->label() }}
                                        </p>
                                    @endforeach
                                </div>
                                <p class="text-sm leading-6 text-gray-500 dark:text-gray-400">Attachment C and Estimated Expense Breakdown do not require signatures. All five listed papers require signed PDFs before final release.</p>
                            </section>
                            @if ($topic->review_stage === 'lrec')
                                @include('topics.partials.lrec-comments')
                            @endif

                            <section x-show="decision === 'rejected'" x-cloak class="space-y-3" aria-labelledby="rejection-reason-heading">
                                <label id="rejection-reason-heading" class="block text-base font-semibold text-gray-900 dark:text-gray-100" for="rejection_reason">Reason for rejection</label>
                                <textarea id="rejection_reason" name="rejection_reason" rows="4" maxlength="2000" x-bind:required="decision === 'rejected'" aria-describedby="rejection-reason-help" class="block w-full rounded-xl border-gray-300 text-base leading-7 focus:border-red-600 focus:ring-red-600 dark:border-gray-700 dark:bg-gray-900 dark:text-white" placeholder="Explain why this proposal cannot proceed.">{{ old('rejection_reason') }}</textarea>
                                <p id="rejection-reason-help" class="text-sm text-gray-500 dark:text-gray-400">Shared with the faculty member. Maximum 2,000 characters.</p>
                                @error('rejection_reason')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                                <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <input id="rejection_confirmed" name="rejection_confirmed" type="checkbox" value="1" x-bind:required="decision === 'rejected'" @checked(old('rejection_confirmed')) class="mt-0.5 rounded border-gray-400 text-red-700 focus:ring-red-600">
                                    <span>I understand this closes the submission and the rejection is final.</span>
                                </label>
                                @error('rejection_confirmed')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                            </section>

                            <p x-show="decision === 'revision_requested'" x-cloak class="text-sm text-gray-600 dark:text-gray-300">Select the papers that need further changes in the document list above.</p>

                            <button type="submit" :disabled="submitting || !decision" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-red-700 px-6 py-3 text-base font-bold text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                                <span x-text="submitting ? 'Saving decision…' : ({ rejected: 'Reject proposal', revision_requested: 'Send revision request', gad_review: 'Clear for GAD review', lrec_queued: 'Route to LREC', ready_for_signature: 'Proceed to signing' }[decision] || 'Save decision')">Send revision request</span>
                            </button>
                        </form>
                    </div>
                </section>
            @elseif ($canReturnToRevision)
                <section x-data="{ open: false }" data-signing-correction-disclosure data-initially-open="false" class="overflow-hidden rounded-2xl border-2 border-amber-300 bg-white shadow-lg">
                    <button type="button" @click="open = !open" :aria-expanded="open" aria-controls="signing-correction-content" class="flex min-h-16 w-full items-center justify-between gap-4 bg-amber-50 px-5 py-4 text-left transition hover:bg-amber-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-amber-700 sm:px-6">
                        <span class="flex items-center gap-4">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-700 text-white" aria-hidden="true"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15.75 5.25 12m0 0L9 8.25M5.25 12h9a4.5 4.5 0 0 1 4.5 4.5v.75" /></svg></span>
                            <span><span class="block text-sm font-bold text-amber-800">Signing correction</span><span class="mt-0.5 block text-xl font-bold text-gray-950">Return to revision</span></span>
                        </span>
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-amber-200 bg-white text-amber-700 shadow-sm" aria-hidden="true"><svg :class="open ? 'rotate-180' : ''" class="h-5 w-5 transition-transform duration-200 motion-reduce:transition-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg></span>
                    </button>
                    <div id="signing-correction-content" x-show="open" x-cloak x-transition class="border-t border-amber-200 bg-white p-5 sm:p-6">
                        <form action="{{ route('research_head.topics.updateStatus', $topic) }}" method="POST" class="space-y-5">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="revision_requested">
                            <input type="hidden" name="redirect_to" value="topic">
                            <p class="text-base leading-7 text-gray-700">Use this only when a paper must change after signing has started. Select every affected paper and provide the same file-specific feedback required for a normal revision. Current signed uploads will be retained as superseded audit copies and cannot be reused for the new version.</p>
                            <section class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4 dark:border-amber-900/60 dark:bg-amber-950/20 sm:p-5">
                                <h4 class="text-lg font-bold text-gray-900 dark:text-white">Papers that must be corrected</h4>
                                <div class="mt-4">
                                    @include('topics.partials.revision-file-selector', ['files' => $submittedFiles])
                                </div>
                                @error('revision_file_ids')<p class="mt-4 text-sm font-semibold text-red-600">{{ $message }}</p>@enderror
                            </section>
                            <button class="w-full rounded-xl bg-amber-700 px-5 py-3.5 text-base font-black text-white transition hover:bg-amber-800 focus:outline-none focus:ring-2 focus:ring-amber-700 focus:ring-offset-2">Return selected papers to revision</button>
                        </form>
                    </div>
                </section>
            @elseif (Auth::user()->isUsingWorkspace('research_head'))
                @if ($topic->status === 'revision_requested')
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
                        <p class="font-bold">Waiting for the faculty revision</p>
                        <p class="mt-1 leading-6">This revision request is locked while the faculty member works. Review the resubmitted version before requesting another round of changes.</p>
                    </div>
                @else
                    <div class="rounded-2xl bg-gray-100 p-5 text-center text-sm font-bold text-gray-600">This proposal is already {{ $statusLabel }}. No further decision is available.</div>
                @endif
            @endif


        </section>

        @if ($canViewNoticeToProceed)
            <section id="notice-to-proceed-tab" x-show="activeTopicTab === 'notice'" x-cloak role="tabpanel" aria-labelledby="notice-to-proceed-tab-button" class="space-y-5">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $topic->hasIssuedNoticeToProceed() ? 'Released documents' : 'Signing & release' }}</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-slate-300">{{ $topic->hasIssuedNoticeToProceed() ? 'The signed proposal papers and Notice to Proceed are ready for faculty.' : 'Upload the signed proposal papers, then prepare and upload the signed Notice to Proceed below to release the package.' }}</p>
                </div>
                @if ($isResearchHead && $headUploadWorkspace)
                    <x-research-head-file-workspace :topic="$topic" :workspace="$headUploadWorkspace" :show-faculty-files="false" />
                @endif
                @include('topics.partials.notice-to-proceed')
            </section>
        @endif

        <section id="version-history-tab" x-show="activeTopicTab === 'history'" x-cloak role="tabpanel" aria-labelledby="version-history-tab-button" class="space-y-5">
            @if ($topic->status === 'revision_requested')
                <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100" data-working-revision-status>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-xl font-bold">{{ $isFacultyRevision ? 'Revision in progress' : 'Faculty revision in progress' }}</h3>
                                <span class="rounded-full bg-amber-200/70 px-2.5 py-1 text-sm font-bold text-amber-900 dark:bg-amber-900 dark:text-amber-100">Working draft</span>
                            </div>
                            @if ($isFacultyRevision)
                                <p class="mt-2 text-base leading-7">Your editor changes, including added images, stay in this private working revision. They are not part of Version {{ $latestVersion?->version_number ?? 1 }}.</p>
                                <p class="mt-1 text-base font-semibold leading-7">Submitting the revision creates Version {{ ($latestVersion?->version_number ?? 1) + 1 }}, sends it to the Research Head, and enables the comparison below.</p>
                            @else
                                <p class="mt-2 text-base leading-7">Version {{ $latestVersion?->version_number ?? 1 }} remains the latest submitted package while the faculty member works. The Research Head receives the changes only after the faculty submits the revision.</p>
                            @endif
                        </div>
                        @if ($isFacultyRevision)
                            <a href="{{ route('faculty.topics.revision', $topic) }}" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-amber-950 px-4 py-2.5 text-sm font-bold text-white hover:bg-black focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-950 dark:bg-amber-100 dark:text-amber-950">Continue revision</a>
                        @endif
                    </div>
                </section>
            @endif

            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h3 class="text-lg font-black text-gray-900">Submitted version comparison</h3>
                    <p class="mt-1 text-sm text-gray-600">Compares submission details and identifies replaced files. It does not inspect document content to describe individual text or image edits.</p>
                </div>
                @if ($previousVersion && $latestVersion)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-left text-sm">
                            <thead class="bg-gray-50 text-xs font-black uppercase tracking-wider text-gray-500"><tr><th class="px-5 py-3">Field</th><th class="px-5 py-3">Version {{ $previousVersion->version_number }}</th><th class="px-5 py-3">Version {{ $latestVersion->version_number }}</th></tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($comparisonRows as $row)
                                    <tr class="{{ $row['changed'] ? 'bg-red-50/60 dark:bg-red-950/20' : '' }}"><th class="px-5 py-3 font-black text-gray-700">{{ $row['label'] }} @if ($row['changed'])<span class="ml-1 text-xs uppercase text-red-700 dark:text-red-400">Changed</span>@endif</th><td class="max-w-xs px-5 py-3 text-gray-500">{{ $row['previous'] }}</td><td class="max-w-xs px-5 py-3 font-semibold text-gray-700">{{ $row['latest'] }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-gray-100 p-5">
                        <p class="text-sm font-black text-gray-700">Files changed in version {{ $latestVersion->version_number }}</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @forelse ($latestVersion->files->where('is_carried_forward', false)->whereNotIn('document_type', [\App\Models\ProposalVersionFile::TYPE_COMMENT_RESPONSE, \App\Models\ProposalVersionFile::TYPE_HEAD_UPLOAD]) as $file)
                                <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-bold text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ $file->label() }}</span>
                            @empty
                                <span class="text-xs text-gray-400">No package files were replaced.</span>
                            @endforelse
                        </div>
                    </div>
                @else
                    <div class="p-8 text-center">
                        <p class="text-base font-bold text-gray-700">Only one submitted version</p>
                        <p class="mt-1 text-sm text-gray-500">
                            @if ($topic->status === 'revision_requested')
                                The current revision is still a working draft. This comparison appears after the faculty submits it as Version {{ ($latestVersion?->version_number ?? 1) + 1 }}.
                            @else
                                A comparison appears after the first revision is submitted.
                            @endif
                        </p>
                    </div>
                @endif
            </section>

            @include('topics.partials.version-history', ['topic' => $topic, 'expanded' => true])
        </section>

        @if ($canViewMonitoring)
            <section id="project-monitoring-tab" x-show="activeTopicTab === 'monitoring'" x-cloak role="tabpanel" aria-labelledby="project-monitoring-tab-button">
                @include('topics.partials.project-monitoring')
            </section>
        @endif

        @if ($topic->signed_approval_path)
            <a href="{{ route('topics.approval', $topic) }}" class="flex justify-center rounded-xl bg-gray-950 px-4 py-3 text-sm font-bold text-white hover:bg-black dark:bg-white dark:text-gray-950 dark:hover:bg-gray-200">Download previous signed approval</a>
        @endif
    </div>
</x-app-layout>
