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
        $statusLabel = match ($topic->status) {
            'approved' => 'Approved',
            'ready_for_signature' => 'Ready for signature',
            'rejected' => 'Rejected',
            'revision_requested' => 'Revision required',
            'resubmitted' => 'Revision awaiting review',
            'expert_review', 'for_final_decision' => 'Awaiting Research Head',
            default => 'Awaiting Research Head',
        };
        if ($topic->isAwaitingNoticeToProceed()) {
            $statusClass = 'bg-amber-100 text-amber-800';
            $statusLabel = 'Approved - awaiting notice';
        } elseif ($topic->isCompletedProject()) {
            $statusClass = 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200';
            $statusLabel = 'Completed - archived';
        }
        $backRoute = Auth::user()->isUsingWorkspace('research_head')
            ? route('research_head.dashboard')
            : (Auth::user()->isUsingWorkspace('faculty_researcher') ? route('research.index') : route('faculty.dashboard'));
        $canDecide = Auth::user()->isUsingWorkspace('research_head') && in_array($topic->status, ['pending', 'resubmitted', 'expert_review', 'for_final_decision'], true);
        $isResearchHead = Auth::user()->isUsingWorkspace('research_head');
        $canReturnToRevision = $isResearchHead && $topic->status === \App\Models\TopicProposal::STATUS_READY_FOR_SIGNATURE;
        $isFacultyWorkspace = Auth::user()->isUsingWorkspace('faculty');
        $isFacultyRevision = $isFacultyWorkspace && $topic->status === 'revision_requested' && $topic->user_id === Auth::id();
          $hasProjectAccess = $isResearchHead || $topic->isAccessibleTo(Auth::user());
          $canViewNoticeToProceed = ($topic->isAwaitingNoticeToProceed() || $topic->hasIssuedNoticeToProceed())
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
            || $errors->hasAny(['status', 'revision_file_ids', 'revision_file_notes.*'])
            || ($isFacultyWorkspace && $topic->status === 'revision_requested')
            ? 'review'
            : ($noticeToProceedErrors
                ? 'notice'
                : (in_array(session('topic_tab'), ['details', 'review', 'notice', 'history', 'monitoring'], true) ? session('topic_tab') : null));
    @endphp

    <x-slot name="header">
        <div class="space-y-3">
            <x-back-link href="{{ $backRoute }}">Back to dashboard</x-back-link>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <h2 class="text-2xl font-black tracking-tight text-gray-900">{{ $topic->title }}</h2>
                    <p class="mt-1 text-sm text-gray-600">Proposal #{{ $topic->id }} &middot; {{ $topic->user->name }} &middot; {{ $topic->researchCall?->title ?? 'Research proposal' }}</p>
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-2">
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
    </x-slot>

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
                        : 'details'
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
                        Notice to Proceed
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
                            <p class="text-xs font-black uppercase tracking-wider text-red-600">Received package</p>
                            <h3 id="submitted-files-heading" class="mt-1 text-lg font-black text-gray-900">Submitted proposal files</h3>
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
                        </div>
                    </div>

                    @if ($isResearchHead)
                        <div class="p-5 sm:p-6" data-latest-package-summary="{{ $latestVersion?->id }}">
                            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5">
                                <h4 class="text-sm font-black text-gray-950">Latest submitted package</h4>
                                <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-700">
                                    @if ($latestVersion)
                                        Version {{ $latestVersion->version_number }} is the package currently awaiting your decision. File review and decision actions are available under the <span class="font-black">Review &amp; decision</span> tab.
                                    @else
                                        No submitted package is available for review yet.
                                    @endif
                                </p>
                            </div>
                        </div>
                    @else
                    <div class="divide-y divide-gray-100">
                        @forelse ($submittedFiles as $file)
                            @php
                                $fileAvailable = $availableSubmittedFileIds->contains($file->id);
                                $fileViewable = $viewableSubmittedFileIds->contains($file->id);
                            @endphp
                            <article class="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                <div class="flex min-w-0 items-start gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $fileAvailable ? 'bg-red-50 text-red-700' : 'bg-gray-100 text-gray-400' }} text-xs font-black">FILE</span>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h4 class="text-sm font-black text-gray-900">{{ $file->label() }}</h4>
                                            @if (! $fileAvailable)
                                                <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-black uppercase tracking-wider text-red-700">Unavailable</span>
                                            @endif
                                        </div>
                                        <p class="mt-1 break-all text-sm font-semibold text-gray-600">{{ $file->original_filename }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ $file->file_size ? \Illuminate\Support\Number::fileSize($file->file_size) : 'Size unavailable' }}@if ($file->is_carried_forward) &middot; Carried forward from an earlier version @endif</p>
                                    </div>
                                </div>

                                <div class="flex w-full shrink-0 gap-2 sm:w-auto">
                                    @if ($fileViewable)
                                        <a href="{{ route('topics.versions.files.view', [$topic, $latestVersion, $file]) }}" target="_blank" rel="noopener" class="inline-flex flex-1 items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-bold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-700 focus:ring-offset-2 sm:flex-none">View</a>
                                    @endif
                                    @if ($fileAvailable)
                                        <a href="{{ route('topics.versions.files.download', [$topic, $latestVersion, $file]) }}" class="inline-flex flex-1 items-center justify-center rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2 sm:flex-none">Download</a>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="p-8 text-center">
                                <p class="text-sm font-black text-gray-800">No individual submitted files are available</p>
                                <p class="mt-1 text-xs text-gray-500">Legacy proposals may only provide a combined proposal download.</p>
                            </div>
                        @endforelse
                    </div>
                    @endif
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
                                        <span class="mt-0.5 block text-xs font-semibold {{ $collaborator->accepted_at ? 'text-emerald-700' : 'text-amber-700' }}">{{ $collaborator->accepted_at ? 'Accepted collaborator' : 'Invitation pending' }}</span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>
        </section>

        <section id="proposal-review-tab" x-show="activeTopicTab === 'review'" x-cloak role="tabpanel" aria-labelledby="proposal-review-tab-button" class="space-y-4">
            @if ($isFacultyRevision)
                <x-proposal-revision-form
                    :topic="$topic"
                    :pending-file-revisions="$pendingFileRevisions"
                    :staged-revision-files="$stagedRevisionFiles"
                    :display-project-cost="$displayProjectCost"
                />
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
                            The review is complete. Only the papers with official signature blocks are waiting for their signed final PDFs.
                        @elseif ($topic->status === 'rejected')
                            This proposal received a final rejection.
                        @else
                            The proposal is with the Research Head. You will be notified when a decision or revision request is shared.
                        @endif
                    </p>
                </div>
            @endif

            @if ($isResearchHead && $headUploadWorkspace && (! $canDecide || $headUploadWorkspace['supplementalHeadUploads']->isNotEmpty()))
                <x-research-head-file-workspace :topic="$topic" :workspace="$headUploadWorkspace" :show-faculty-files="! $canDecide && $topic->status !== \App\Models\TopicProposal::STATUS_READY_FOR_SIGNATURE" />
            @endif

            @if ($reviewDocuments->isNotEmpty())
                <details class="group rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <summary class="flex cursor-pointer items-center justify-between gap-4 px-5 py-4 sm:px-6 hover:bg-gray-50 transition">
                        <div class="flex items-center gap-4">
                            <svg class="h-5 w-5 shrink-0 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                            <div>
                                <h3 class="text-base font-black text-gray-900">Research Head documents</h3>
                                <p class="mt-0.5 text-sm text-gray-600">{{ $reviewDocuments->count() }} document(s) shared by the Research Head.</p>
                            </div>
                        </div>
                        <svg class="h-5 w-5 shrink-0 text-gray-400 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" /></svg>
                    </summary>
                    <div class="border-t border-gray-100">
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
                                        <h4 class="text-base font-black text-gray-900">{{ $reviewDocument->label() }}</h4>
                                        @if ($documentDecision)
                                            <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-black text-red-700">{{ str($documentDecision)->replace('_', ' ')->title() }}</span>
                                        @endif
                                        @if ($documentPurpose)
                                            <span class="rounded-full px-2.5 py-1 text-xs font-black {{ $documentPurpose === \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED ? 'bg-gray-950 text-white dark:bg-white dark:text-gray-950' : ($documentPurpose === \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_REVISION ? 'bg-red-50 text-red-800 dark:bg-red-950/40 dark:text-red-200' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200') }}">{{ $reviewDocument->headUploadPurposeLabel() }}</span>
                                        @endif
                                    </div>
                                    <p class="mt-1 break-all text-sm font-semibold text-gray-600">{{ $reviewDocument->original_filename }}</p>
                                    <p class="mt-1 text-xs text-gray-500">Shared by {{ $reviewDocument->uploadedBy?->name ?? 'Research Head' }} on {{ $reviewDocument->created_at->format('M j, Y g:i A') }}</p>
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
                </details>
            @endif

            @php
                $decisionReviews = $topic->reviews
                    ->where('decision', '!=', 'head_upload')
                    ->sortByDesc('created_at')
                    ->values();
                $latestDecisionReview = $decisionReviews->first();
            @endphp
            <details data-decision-history class="group overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-950">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 transition hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-red-700 dark:hover:bg-gray-900 sm:px-6 [&::-webkit-details-marker]:hidden">
                    <div class="flex min-w-0 items-start gap-3 sm:items-center">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gray-100 text-gray-500 dark:bg-gray-900 dark:text-gray-400" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                        </span>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-base font-black text-gray-900 dark:text-white">Decision history</h3>
                                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $decisionReviews->count() }} {{ str('decision')->plural($decisionReviews->count()) }}</span>
                            </div>
                            <p class="mt-1 truncate text-sm text-gray-600 dark:text-gray-400">
                                @if ($latestDecisionReview)
                                    Latest: <span class="font-bold text-gray-800 dark:text-gray-200">{{ str($latestDecisionReview->decision)->replace('_', ' ')->title() }}</span>
                                    <span aria-hidden="true">&middot;</span>
                                    <time datetime="{{ $latestDecisionReview->created_at->toIso8601String() }}">{{ $latestDecisionReview->created_at->format('M j, Y') }}</time>
                                @else
                                    No Research Head decisions recorded yet.
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-2 text-gray-500 dark:text-gray-400">
                        <span class="hidden text-xs font-bold sm:inline"><span class="group-open:hidden">View history</span><span class="hidden group-open:inline">Hide history</span></span>
                        <svg class="h-5 w-5 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" /></svg>
                    </div>
                </summary>
                <div class="max-h-[42rem] overflow-y-auto overscroll-contain border-t border-gray-100 dark:border-gray-800" data-decision-history-list>
                    @if ($decisionReviews->isNotEmpty())
                        <ol class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($decisionReviews as $review)
                            <li class="p-5 sm:p-6">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-black text-gray-800 dark:text-gray-100">{{ str($review->decision)->replace('_', ' ')->title() }}</p>
                                        @if ($loop->first)
                                            <span class="rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-red-700 dark:bg-red-950/40 dark:text-red-300">Latest</span>
                                        @endif
                                    </div>
                                    <time datetime="{{ $review->created_at->toIso8601String() }}" class="text-xs text-gray-500 dark:text-gray-400">{{ $review->created_at->format('M d, Y h:i A') }}</time>
                                </div>
                                <p class="mt-1 text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $review->reviewer?->name ?? 'Former Research Head' }}</p>
                                @if ($review->comment)
                                    <div class="mt-3 rounded-xl bg-gray-50 p-4 text-sm leading-6 text-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                        @if ($review->decision === 'rejected')
                                            <p class="text-xs font-black uppercase tracking-wider text-red-700 dark:text-red-300">Rejection reason</p>
                                        @endif
                                        <p @class(['whitespace-pre-line', 'mt-1' => $review->decision === 'rejected'])>{{ $review->comment }}</p>
                                    </div>
                                @endif
                                @if ($review->fileRevisions->isNotEmpty())
                                    <div class="mt-3 space-y-2">
                                        @foreach ($review->fileRevisions as $fileRevision)
                                            @php
                                                $annotationVersion = $topic->versions->firstWhere('id', $fileRevision->file?->proposal_version_id);
                                            @endphp
                                            <div class="rounded-xl border px-4 py-3 text-sm {{ $fileRevision->resolved_at ? 'border-gray-300 bg-gray-100 text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200' : 'border-red-300 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200' }}">
                                                <div class="flex flex-wrap items-center justify-between gap-2"><span class="font-black">{{ $fileRevision->file?->label() ?? str($fileRevision->document_type)->replace('_', ' ')->title() }}</span><span class="text-xs font-black">{{ ! $fileRevision->resolved_at ? 'Revision required' : ($fileRevision->resolution_type === 'no_file_change' ? 'Resolved — no file change' : 'Resolved — revised file') }}</span></div>
                                                <p class="mt-1 text-xs opacity-75">{{ $fileRevision->original_filename }}</p>
                                                @if ($fileRevision->revision_note)<p class="mt-2 leading-6">{{ $fileRevision->revision_note }}</p>@endif
                                                @if ($fileRevision->resolved_at && $fileRevision->faculty_response)<div class="mt-3 rounded-lg border border-blue-200 bg-blue-50 p-3 text-blue-900 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-200"><p class="text-xs font-black uppercase tracking-wide">Faculty response</p><p class="mt-1 whitespace-pre-line leading-6">{{ $fileRevision->faculty_response }}</p></div>@endif
                                                @if ($fileRevision->annotations->isNotEmpty() && $annotationVersion && $fileRevision->file)
                                                    @php
                                                        $firstAnnotation = $fileRevision->annotations->sortBy([['page_number', 'asc'], ['id', 'asc']])->first();
                                                        $annotationDeepLink = route('topics.versions.files.annotations.index', [$topic, $annotationVersion, $fileRevision->file]).'?annotation='.$firstAnnotation->id.'#proposal-review';
                                                    @endphp
                                                    <a href="{{ $annotationDeepLink }}" class="mt-3 inline-flex rounded-lg bg-red-700 px-3 py-2 text-xs font-black text-white hover:bg-red-800">View {{ $fileRevision->annotations->count() }} highlighted comment(s)</a>
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
            </details>

            @if ($canDecide)
                @php
                    $initialResearchHeadDecision = old(
                        'status',
                        request()->query('decision') === 'revision_requested' ? 'revision_requested' : '',
                    );
                @endphp
                <details class="group rounded-2xl border-2 border-red-300 shadow-lg overflow-hidden" open data-latest-review-version="{{ $latestVersion?->version_number }}" data-latest-review-version-id="{{ $latestVersion?->id }}">
                    <summary class="flex cursor-pointer items-center justify-between gap-4 bg-red-50 px-5 py-4 sm:px-6 hover:bg-red-100 transition">
                        <div class="flex items-center gap-4">
                            <svg class="h-5 w-5 shrink-0 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                            <div>
                                <p class="text-xs font-black uppercase tracking-wider text-red-700">Action required</p>
                                <h3 class="text-base font-black text-gray-900">Record the Research Head decision</h3>
                                @if ($latestVersion)
                                    <p class="mt-1 text-xs font-bold text-red-800">Reviewing Version {{ $latestVersion->version_number }} &mdash; latest submitted package</p>
                                @endif
                            </div>
                        </div>
                        <svg class="h-5 w-5 shrink-0 text-red-400 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" /></svg>
                    </summary>
                    <div class="border-t border-red-200 bg-white">
                        <form
                            id="research-head-decision-form"
                            action="{{ route('research_head.topics.updateStatus', $topic) }}"
                            method="POST"
                            x-data="{
                                decision: @js($initialResearchHeadDecision),
                                signingDecision: @js(\App\Models\TopicProposal::STATUS_READY_FOR_SIGNATURE),
                                submitting: false,
                                async submitDecision(event) {
                                    const form = event.currentTarget;
                                    if (this.submitting || !form.reportValidity()) return;

                                    const confirmation = {
                                        [this.signingDecision]: {
                                            title: 'Continue to final signing?',
                                            text: 'This starts the signing stage. You will upload a signed PDF for every selected paper before approval can be finalized.',
                                            confirmButtonText: 'Continue to signing',
                                            confirmButtonColor: '#dc2626',
                                        },
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
                            <input type="hidden" name="redirect_to" value="topic">
                            <p class="text-sm leading-6 text-gray-600">Choose the decision below. A proposal must complete final signing before it can be approved. Revision requests use the file checklist and highlighted comments to tell the faculty member exactly what to change.</p>

                            <fieldset aria-describedby="decision-help">
                                <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                                    <div>
                                        <legend class="text-lg font-black text-gray-950 dark:text-white">Choose the next step <span class="text-red-600">Required</span></legend>
                                        <p id="decision-help" class="text-sm leading-6 text-gray-600 dark:text-gray-300">Each option opens only the work needed for that decision. Approval remains unavailable until final signing is complete.</p>
                                    </div>
                                    <span x-show="decision" x-cloak class="w-fit rounded-full bg-gray-950 px-3 py-1 text-xs font-black text-white dark:bg-white dark:text-gray-950">Decision selected</span>
                                </div>

                                <div class="mt-4 grid gap-3 lg:grid-cols-3">
                                    <label
                                        :class="decision === 'revision_requested' ? 'border-red-700 bg-red-50 shadow-md shadow-red-100 dark:border-red-500 dark:bg-red-950/30 dark:shadow-none' : 'border-gray-200 bg-white hover:border-red-300 hover:bg-red-50/40 dark:border-gray-700 dark:bg-gray-950 dark:hover:border-red-800 dark:hover:bg-red-950/20'"
                                        class="group relative flex min-h-52 cursor-pointer flex-col rounded-2xl border-2 p-4 transition focus-within:outline-none focus-within:ring-2 focus-within:ring-red-700 focus-within:ring-offset-2 dark:focus-within:ring-offset-gray-950"
                                    >
                                        <input class="sr-only" type="radio" name="status" value="revision_requested" x-model="decision" required>
                                        <div class="flex items-start justify-between gap-3">
                                            <span :class="decision === 'revision_requested' ? 'bg-red-700 text-white' : 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300'" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl transition">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5Z" /></svg>
                                            </span>
                                            <span x-show="decision === 'revision_requested'" x-cloak class="rounded-full bg-red-700 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-white">Selected</span>
                                        </div>
                                        <div class="mt-4">
                                            <p class="text-base font-black text-gray-950 dark:text-white">Request revisions</p>
                                            <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">Send specific file feedback so the faculty member can correct and resubmit the proposal.</p>
                                        </div>
                                        <p class="mt-auto pt-4 text-xs font-bold leading-5 text-red-800 dark:text-red-300">Mark files → add highlights/comments → request revision</p>
                                    </label>

                                    <label
                                        :class="decision === signingDecision ? 'border-red-700 bg-red-50 shadow-md shadow-red-100 dark:border-red-500 dark:bg-red-950/30 dark:shadow-none' : 'border-gray-200 bg-white hover:border-red-300 hover:bg-red-50/40 dark:border-gray-700 dark:bg-gray-950 dark:hover:border-red-800 dark:hover:bg-red-950/20'"
                                        class="group relative flex min-h-52 cursor-pointer flex-col rounded-2xl border-2 p-4 transition focus-within:outline-none focus-within:ring-2 focus-within:ring-red-700 focus-within:ring-offset-2 dark:focus-within:ring-offset-gray-950"
                                    >
                                        <input class="sr-only" type="radio" name="status" value="{{ \App\Models\TopicProposal::STATUS_READY_FOR_SIGNATURE }}" x-model="decision" required>
                                        <div class="flex items-start justify-between gap-3">
                                            <span :class="decision === signingDecision ? 'bg-red-700 text-white' : 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300'" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl transition">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3.75H6a2.25 2.25 0 0 0-2.25 2.25v12A2.25 2.25 0 0 0 6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75h-2.25M8.25 3.75A2.25 2.25 0 0 0 10.5 6h3a2.25 2.25 0 0 0 2.25-2.25M8.25 3.75A2.25 2.25 0 0 1 10.5 1.5h3a2.25 2.25 0 0 1 2.25 2.25M8.25 12l2.25 2.25 4.5-4.5" /></svg>
                                            </span>
                                            <span x-show="decision === signingDecision" x-cloak class="rounded-full bg-red-700 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-white">Selected</span>
                                        </div>
                                        <div class="mt-4">
                                            <p class="text-base font-black text-gray-950 dark:text-white">Continue to final signing</p>
                                            <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">Choose the papers that need signed final copies before approval can be unlocked.</p>
                                        </div>
                                        <p class="mt-auto pt-4 text-xs font-bold leading-5 text-red-800 dark:text-red-300">Select papers → upload signed PDFs → approval unlocks</p>
                                    </label>

                                    <label
                                        :class="decision === 'rejected' ? 'border-red-700 bg-red-50 shadow-md shadow-red-100 dark:border-red-500 dark:bg-red-950/30 dark:shadow-none' : 'border-gray-200 bg-white hover:border-red-300 hover:bg-red-50/40 dark:border-gray-700 dark:bg-gray-950 dark:hover:border-red-800 dark:hover:bg-red-950/20'"
                                        class="group relative flex min-h-52 cursor-pointer flex-col rounded-2xl border-2 p-4 transition focus-within:outline-none focus-within:ring-2 focus-within:ring-red-700 focus-within:ring-offset-2 dark:focus-within:ring-offset-gray-950"
                                    >
                                        <input class="sr-only" type="radio" name="status" value="rejected" x-model="decision" required>
                                        <div class="flex items-start justify-between gap-3">
                                            <span :class="decision === 'rejected' ? 'bg-red-700 text-white' : 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300'" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl transition">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3h.008v.008H12v-.008Zm0-13.5a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z" /></svg>
                                            </span>
                                            <span x-show="decision === 'rejected'" x-cloak class="rounded-full bg-red-700 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-white">Selected</span>
                                        </div>
                                        <div class="mt-4">
                                            <p class="text-base font-black text-gray-950 dark:text-white">Reject proposal</p>
                                            <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">Close this submission with a professional, documented final reason.</p>
                                        </div>
                                        <p class="mt-auto pt-4 text-xs font-bold leading-5 text-red-800 dark:text-red-300">Give reason → confirm final decision → proposal closes</p>
                                    </label>
                                </div>
                                @error('status')<p class="mt-3 text-sm font-semibold text-red-600">{{ $message }}</p>@enderror
                            </fieldset>

                            <section
                                x-show="decision === 'rejected'"
                                x-cloak
                                x-transition
                                class="overflow-hidden rounded-2xl border-2 border-red-300 bg-red-50 dark:border-red-900/80 dark:bg-red-950/30"
                                aria-labelledby="rejection-reason-heading"
                            >
                                <div class="border-b border-red-200 bg-red-100/70 px-4 py-4 dark:border-red-900/80 dark:bg-red-950/60 sm:px-5">
                                    <div class="flex items-start gap-3">
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-700 text-white shadow-sm" aria-hidden="true">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                                        </span>
                                        <div>
                                            <p class="text-xs font-black uppercase tracking-wider text-red-700 dark:text-red-300">Final decision</p>
                                            <h4 id="rejection-reason-heading" class="mt-1 text-lg font-black text-red-950 dark:text-white">Why is this proposal being rejected?</h4>
                                            <p class="mt-1 text-sm leading-6 text-red-800 dark:text-red-200">Give the faculty member a clear, professional reason. It will be saved in the decision history and the proposal cannot move to signing.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-4 p-4 sm:p-5">
                                    <label class="block text-sm font-bold text-red-950 dark:text-red-100" for="rejection_reason">
                                        Rejection reason <span class="text-red-700 dark:text-red-300">Required</span>
                                        <textarea id="rejection_reason" name="rejection_reason" rows="5" maxlength="2000" x-bind:required="decision === 'rejected'" aria-describedby="rejection-reason-help" class="mt-2 block w-full rounded-xl border-red-300 bg-white text-sm leading-6 text-gray-900 placeholder:text-gray-400 focus:border-red-700 focus:ring-red-700 dark:border-red-900 dark:bg-gray-950 dark:text-white dark:placeholder:text-gray-500" placeholder="Explain the reason for the final rejection in a way the faculty member can understand.">{{ old('rejection_reason') }}</textarea>
                                    </label>
                                    <p id="rejection-reason-help" class="text-xs leading-5 text-red-800 dark:text-red-200">Be specific about the issue or decision basis. Maximum 2,000 characters.</p>
                                    @error('rejection_reason')<p class="text-sm font-semibold text-red-700 dark:text-red-300">{{ $message }}</p>@enderror

                                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-red-300 bg-white/80 p-4 text-sm text-red-950 transition hover:border-red-500 dark:border-red-900 dark:bg-gray-950/70 dark:text-red-100">
                                        <input id="rejection_confirmed" name="rejection_confirmed" type="checkbox" value="1" x-bind:required="decision === 'rejected'" @checked(old('rejection_confirmed')) class="mt-0.5 rounded border-red-400 text-red-700 focus:ring-red-700">
                                        <span>
                                            <span class="block font-black">I confirm that this rejection is final.</span>
                                            <span class="mt-1 block text-xs leading-5 text-red-800 dark:text-red-200">The proposal will be closed, will not proceed to signing, and cannot be approved from this submission.</span>
                                        </span>
                                    </label>
                                    @error('rejection_confirmed')<p class="text-sm font-semibold text-red-700 dark:text-red-300">{{ $message }}</p>@enderror
                                </div>
                            </section>

                            @php
                                $oldSignatureFileIds = collect(old('signature_file_ids', []))->map(fn ($fileId) => (int) $fileId);
                            @endphp
                            <section
                                x-show="decision === @js(\App\Models\TopicProposal::STATUS_READY_FOR_SIGNATURE)"
                                x-cloak
                                class="rounded-2xl border border-red-300 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950/30 sm:p-5"
                                aria-labelledby="signature-file-selection-heading"
                            >
                                <h4 id="signature-file-selection-heading" class="text-lg font-black text-gray-950 dark:text-white">Which papers need a signed final PDF?</h4>
                                <p class="mt-1 text-sm leading-6 text-gray-700 dark:text-gray-300">Nothing is selected automatically. Choose only the papers that actually require a signature.</p>
                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                    @foreach ($submittedFiles as $signatureCandidate)
                                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-300 bg-white p-3 text-sm font-bold text-gray-800 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200">
                                            <input
                                                type="checkbox"
                                                name="signature_file_ids[]"
                                                value="{{ $signatureCandidate->id }}"
                                                @checked($oldSignatureFileIds->contains($signatureCandidate->id))
                                                class="mt-0.5 rounded border-gray-400 text-red-700 focus:ring-red-700"
                                            >
                                            <span>
                                                <span class="block">{{ $signatureCandidate->label() }}</span>
                                                <span class="mt-1 block break-all text-xs font-normal text-gray-500 dark:text-gray-400">{{ $signatureCandidate->original_filename }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </section>

                            <section
                                class="rounded-2xl border border-gray-300 bg-white p-4 dark:border-gray-700 dark:bg-gray-950 sm:p-5"
                                aria-labelledby="file-review-checklist-heading"
                            >
                                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <h4 id="file-review-checklist-heading" class="text-lg font-black text-gray-900">Review latest submitted files</h4>
                                        <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">These are the files from Version {{ $latestVersion?->version_number ?? 1 }}. Preview and review them here. To request changes, choose <span class="font-black">Request revisions</span>, then mark only the affected files. Every selected PDF requires at least one saved highlight and comment.</p>
                                    </div>
                                </div>
                                @include('topics.partials.revision-file-selector', ['files' => $submittedFiles, 'disableUnlessRevision' => true])
                                @error('revision_file_ids')<p class="mt-4 text-sm font-semibold text-red-600">{{ $message }}</p>@enderror
                                <p x-show="decision === 'revision_requested'" x-cloak class="mt-4 rounded-xl bg-gray-950 px-4 py-3 text-sm font-semibold text-white dark:border dark:border-gray-800">This request applies to Version {{ $latestVersion?->version_number ?? 1 }}. The faculty member's next resubmission will create a newer version for a new review round.</p>
                            </section>

                            <button type="submit" :disabled="submitting" class="w-full rounded-xl bg-red-600 px-5 py-3.5 text-base font-black text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-600">
                                <span x-text="submitting ? 'Saving decision…' : (decision === signingDecision ? 'Continue to final signing' : (decision === 'rejected' ? 'Reject proposal' : 'Send revision request'))">Save decision and share with faculty</span>
                            </button>
                        </form>
                    </div>
                </details>
            @elseif ($canReturnToRevision)
                <details class="group overflow-hidden rounded-2xl border-2 border-amber-300 shadow-lg">
                    <summary class="flex cursor-pointer items-center justify-between gap-4 bg-amber-50 px-5 py-4 transition hover:bg-amber-100 sm:px-6">
                        <div>
                            <p class="text-xs font-black uppercase tracking-wider text-amber-800">Signing correction</p>
                            <h3 class="mt-1 text-base font-black text-gray-900">Return to revision</h3>
                        </div>
                        <svg class="h-5 w-5 shrink-0 text-amber-600 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" /></svg>
                    </summary>
                    <div class="border-t border-amber-200 bg-white p-5 sm:p-6">
                        <form action="{{ route('research_head.topics.updateStatus', $topic) }}" method="POST" class="space-y-5">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="revision_requested">
                            <input type="hidden" name="redirect_to" value="topic">
                            <p class="text-sm leading-6 text-gray-700">Use this only when a paper must change after signing has started. Select every affected paper and provide the same file-specific feedback required for a normal revision. Current signed uploads will be retained as superseded audit copies and cannot be reused for the new version.</p>
                            <section class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4 dark:border-amber-900/60 dark:bg-amber-950/20 sm:p-5">
                                <h4 class="text-base font-black text-gray-900 dark:text-white">Papers that must be corrected</h4>
                                <p class="mt-1 text-sm leading-6 text-gray-700 dark:text-gray-300">For PDFs, save at least one highlight and comment before selecting the paper. For non-PDF files, give exact instructions.</p>
                                <div class="mt-4">
                                    @include('topics.partials.revision-file-selector', ['files' => $submittedFiles])
                                </div>
                                @error('revision_file_ids')<p class="mt-4 text-sm font-semibold text-red-600">{{ $message }}</p>@enderror
                            </section>
                            <button class="w-full rounded-xl bg-amber-700 px-5 py-3.5 text-base font-black text-white transition hover:bg-amber-800 focus:outline-none focus:ring-2 focus:ring-amber-700 focus:ring-offset-2">Return selected papers to revision</button>
                        </form>
                    </div>
                </details>
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
            <section id="notice-to-proceed-tab" x-show="activeTopicTab === 'notice'" x-cloak role="tabpanel" aria-labelledby="notice-to-proceed-tab-button">
                @include('topics.partials.notice-to-proceed')
            </section>
        @endif

        <section id="version-history-tab" x-show="activeTopicTab === 'history'" x-cloak role="tabpanel" aria-labelledby="version-history-tab-button" class="space-y-5">
            @if ($topic->status === 'revision_requested')
                <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100" data-working-revision-status>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-base font-black">{{ $isFacultyRevision ? 'Revision in progress' : 'Faculty revision in progress' }}</h3>
                                <span class="rounded-full bg-amber-200/70 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-amber-900 dark:bg-amber-900 dark:text-amber-100">Working draft</span>
                            </div>
                            @if ($isFacultyRevision)
                                <p class="mt-2 text-sm leading-6">Your editor changes, including added images, stay in this private working revision. They are not part of Version {{ $latestVersion?->version_number ?? 1 }}.</p>
                                <p class="mt-1 text-sm font-semibold">Submitting the revision creates Version {{ ($latestVersion?->version_number ?? 1) + 1 }}, sends it to the Research Head, and enables the comparison below.</p>
                            @else
                                <p class="mt-2 text-sm leading-6">Version {{ $latestVersion?->version_number ?? 1 }} remains the latest submitted package while the faculty member works. The Research Head receives the changes only after the faculty submits the revision.</p>
                            @endif
                        </div>
                        @if ($isFacultyRevision)
                            <button type="button" @click="setTopicTab('review', '#submit-revision')" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-amber-950 px-4 py-2.5 text-sm font-bold text-white hover:bg-black focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-950 dark:bg-amber-100 dark:text-amber-950">Continue revision</button>
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
