<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

    <?php
        $statusClass = match ($topic->status) {
            'approved' => 'bg-gray-950 text-white dark:bg-white dark:text-gray-950',
            'ready_for_signature' => 'bg-red-50 text-red-800',
            'rejected' => 'bg-red-50 text-red-700',
            'revision_requested' => 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300',
            'resubmitted', 'expert_review', 'for_final_decision' => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
            default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200',
        };
        $statusLabel = $topic->workflowStatusLabel($latestVersion);
        $decisionReviews = $topic->reviews
            ->where('decision', '!=', 'head_upload')
            ->sortByDesc('created_at')
            ->values();
        if ($topic->isAwaitingNoticeToProceed()) {
            $statusClass = 'bg-amber-100 text-amber-800';
            $statusLabel = 'Final signing';
        } elseif ($topic->isCompletedProject()) {
            $statusClass = 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200';
            $statusLabel = 'Completed - archived';
        }
        $isResearchOffice = Auth::user()->isUsingWorkspace(\App\Models\User::WORKSPACE_RESEARCH_OFFICE);
        $canDecide = Auth::user()->isUsingWorkspace('research_head') && in_array($topic->status, ['pending', 'resubmitted', 'expert_review', 'for_final_decision', \App\Models\TopicProposal::STATUS_GAD_REVIEW, 'lrec_review'], true);
        $isResearchHead = Auth::user()->isUsingWorkspace('research_head');
        $isAssessmentStage = $isResearchHead && $topic->status === \App\Models\TopicProposal::STATUS_GAD_REVIEW;
        $canManageNoticeToProceed = Auth::user()->can('manageNoticeToProceed', $topic);
        $canViewSigningDocuments = Auth::user()->can('viewSigningDocuments', $topic);
        $canReturnToRevision = $isResearchHead && $topic->status === \App\Models\TopicProposal::STATUS_READY_FOR_SIGNATURE;
        $isFacultyWorkspace = Auth::user()->isUsingWorkspace('faculty');
        $isFacultyRevision = $isFacultyWorkspace && $topic->status === 'revision_requested' && $topic->user_id === Auth::id();
        $proposalPaperCount = $submittedFiles->reject(fn (\App\Models\ProposalVersionFile $file): bool => $file->isGeneratedAssessmentForm())->count();
        $assessmentFormCount = $submittedFiles->count() - $proposalPaperCount;
          $hasProjectAccess = $isResearchHead || $canViewSigningDocuments || $topic->isAccessibleTo(Auth::user());
          $canViewNoticeToProceed = ($topic->isAwaitingNoticeToProceed() || $topic->hasIssuedNoticeToProceed() || (($isResearchHead || $canViewSigningDocuments) && $topic->status === 'ready_for_signature'))
              && $hasProjectAccess && (! $topic->hasIssuedNoticeToProceed() || $isResearchHead || $canViewSigningDocuments);
          $canViewMonitoring = ($topic->hasIssuedNoticeToProceed() || $topic->isCompletedProject())
              && $hasProjectAccess && ! $isFacultyWorkspace;
        [$workspaceBackRoute, $workspaceBackLabel] = match (true) {
            $isResearchHead && $topic->isCompletedProject() => ['research_head.completed-projects.index', 'Back to projects'],
            $isResearchHead && $canViewMonitoring => ['research_head.projects.index', 'Back to projects'],
            $isResearchHead => ['research_head.proposal-submissions.index', 'Back to proposal reviews'],
            Auth::user()->isUsingWorkspace('faculty_researcher') => ['research.index', 'Back to projects'],
            $isFacultyWorkspace => ['faculty.submissions', 'Back to submitted proposals'],
            default => ['dashboard', 'Back to dashboard'],
        };
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
            || $errors->hasAny(['status', 'revision_file_ids', 'revision_file_notes.*', 'committee_comments.*', 'research_head_clearance_confirmed', 'initial_clearance_confirmed', 'lrec_clearance_confirmed', 'signature_file_ids'])
            || ($isFacultyWorkspace && $topic->status === 'revision_requested')
            ? 'review'
            : (($noticeToProceedErrors || ($canViewNoticeToProceed && $errors->getBag('headUpload')->any()))
                ? 'notice'
                : (in_array(session('topic_tab'), ['details', 'review', 'notice', 'history', 'monitoring'], true) ? session('topic_tab') : null));
        if (($initialTopicTab === 'monitoring' && ! $canViewMonitoring) || ($initialTopicTab === 'notice' && ! $canViewNoticeToProceed)) {
            $initialTopicTab = 'details';
        }

    ?>

     <?php $__env->slot('header', null, []); ?> 
        <?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => $topic->title,'subtitle' => 'Proposal #'.$topic->id.' · '.$topic->user->name.' · '.($topic->researchCall?->title ?? 'Research proposal')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($topic->title),'subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Proposal #'.$topic->id.' · '.$topic->user->name.' · '.($topic->researchCall?->title ?? 'Research proposal'))]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

             <?php $__env->slot('actions', null, []); ?> 
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sampleProposalDraft): ?>
                        <a href="<?php echo e(route('faculty.proposal-drafts.show', $sampleProposalDraft)); ?>" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-gray-300 bg-white px-5 py-3 text-base font-bold text-gray-900 transition hover:bg-gray-50 dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:hover:bg-slate-800">Open sample proposal draft</a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sampleReportProject): ?>
                        <a href="<?php echo e(route('research.show', $sampleReportProject)); ?>#project-monitoring" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-red-700 px-5 py-3 text-base font-bold text-white transition hover:bg-red-800">Open editable report sample</a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topic->isDisseminationAvailable() && Auth::user()->isUsingWorkspace('faculty_researcher') && $topic->isAccessibleTo(Auth::user())): ?>
                        <a data-find-journals href="<?php echo e(route('research.dissemination.show', $topic)); ?>" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-red-700 px-5 py-3 text-base font-bold text-white shadow-sm transition hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path stroke-linecap="round" d="m16 16 4 4"/></svg>
                            Find journals
                        </a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draftHistoryCount > 0 && ($isFacultyWorkspace || $isResearchHead)): ?>
                        <a href="<?php echo e(route('topics.draft-history.index', $topic)); ?>" class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50">Draft history (<?php echo e($draftHistoryCount); ?>)</a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canAskAthenaAboutProposal): ?>
                        <button type="button" @click="$store.researchAssistant.openWithContext(<?php echo e($topic->id); ?>)" class="inline-flex items-center justify-center gap-2 rounded-xl border border-red-200 bg-white px-3 py-2 text-sm font-bold text-red-700 shadow-sm transition hover:bg-red-50">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.8 4.8 11 2l1.2 2.8L15 6l-2.8 1.2L11 10 9.8 7.2 7 6l2.8-1.2ZM16.9 13.9 18 11l1.1 2.9L22 15l-2.9 1.1L18 19l-1.1-2.9L14 15l2.9-1.1Z" />
                            </svg>
                            Ask Athena about this proposal
                        </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($isFacultyRevision || ($topic->status === 'approved' && $topic->hasIssuedNoticeToProceed() && ! $topic->isCompletedProject()))): ?>
                        <span class="rounded-full px-3 py-1.5 text-sm font-black <?php echo e($statusClass); ?>"><?php echo e($statusLabel); ?></span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
             <?php $__env->endSlot(); ?>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $attributes = $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $component = $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?>
     <?php $__env->endSlot(); ?>

    <?php if (isset($component)) { $__componentOriginale1e1bf01479913963d5a10c1ced84ce1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale1e1bf01479913963d5a10c1ced84ce1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.project-document-drawer','data' => ['topic' => $topic,'library' => $projectDocumentLibrary]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('project-document-drawer'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['topic' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($topic),'library' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($projectDocumentLibrary)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale1e1bf01479913963d5a10c1ced84ce1)): ?>
<?php $attributes = $__attributesOriginale1e1bf01479913963d5a10c1ced84ce1; ?>
<?php unset($__attributesOriginale1e1bf01479913963d5a10c1ced84ce1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale1e1bf01479913963d5a10c1ced84ce1)): ?>
<?php $component = $__componentOriginale1e1bf01479913963d5a10c1ced84ce1; ?>
<?php unset($__componentOriginale1e1bf01479913963d5a10c1ced84ce1); ?>
<?php endif; ?>

    <div
        class="mx-auto max-w-7xl space-y-6"
        data-topic-workspace
        x-data="{
            floatingBackLinkObserver: null,
            activeTopicTab: <?php echo \Illuminate\Support\Js::from($initialTopicTab)->toHtml() ?> || (
                ['#proposal-review', '#submit-revision', '#review-and-submit', '#initial-review-workflow'].includes(window.location.hash) || window.location.hash.startsWith('#file-review-card-')
                    ? 'review'
                    : window.location.hash === '#notice-to-proceed'
                        ? (<?php echo \Illuminate\Support\Js::from($canViewNoticeToProceed)->toHtml() ?> ? 'notice' : 'details')
                    : (<?php echo \Illuminate\Support\Js::from($canViewMonitoring)->toHtml() ?> && (window.location.hash === '#project-monitoring' || (window.location.hash.startsWith('#monitoring-tool-') || window.location.hash.startsWith('#narrative-report-'))))
                        ? 'monitoring'
                        : window.location.hash === '#version-history'
                        ? 'history'
                        : <?php echo \Illuminate\Support\Js::from($isResearchOffice || $canDecide || ($isResearchHead && $topic->status === 'revision_requested') ? 'review' : 'details')->toHtml() ?>
            ),
            decisionHistoryOpen: false,
            setTopicTab(tab, hash) {
                this.activeTopicTab = tab;
                window.location.hash = hash;
            },
            syncTopicTab() {
                if (['#proposal-review', '#submit-revision', '#review-and-submit', '#initial-review-workflow'].includes(window.location.hash) || window.location.hash.startsWith('#file-review-card-')) {
                    this.activeTopicTab = 'review';
                } else if (window.location.hash === '#notice-to-proceed') {
                    this.activeTopicTab = <?php echo \Illuminate\Support\Js::from($canViewNoticeToProceed)->toHtml() ?> ? 'notice' : 'details';
                } else if (<?php echo \Illuminate\Support\Js::from($canViewMonitoring)->toHtml() ?> && (window.location.hash === '#project-monitoring' || (window.location.hash.startsWith('#monitoring-tool-') || window.location.hash.startsWith('#narrative-report-')))) {
                    this.activeTopicTab = 'monitoring';
                } else if (window.location.hash === '#version-history') {
                    this.activeTopicTab = 'history';
                } else {
                    this.activeTopicTab = 'details';
                }
                this.scrollToTopicHash();
            },
            init() {
                this.$nextTick(() => {
                    this.floatingBackLinkObserver = new ResizeObserver(() => {
                        this.$el.style.setProperty('--athena-topic-back-link-height', `${this.$refs.workspaceBackLink.getBoundingClientRect().height}px`);
                        const statusManager = this.$el.querySelector('[data-project-status-manager]');
                        this.$el.style.setProperty('--athena-topic-status-height', `${statusManager?.getBoundingClientRect().height ?? 0}px`);
                    });
                    this.floatingBackLinkObserver.observe(this.$refs.workspaceBackLink);
                    const statusManager = this.$el.querySelector('[data-project-status-manager]');
                    if (statusManager) {
                        this.floatingBackLinkObserver.observe(statusManager);
                    }
                });
                this.scrollToTopicHash();
            },
            destroy() {
                this.floatingBackLinkObserver?.disconnect();
            },
            scrollToTopicHash() {
                if (window.location.hash === '#notice-to-proceed') {
                    this.$nextTick(() => document.getElementById('notice-to-proceed')?.scrollIntoView({ block: 'start' }));

                    return;
                }
                if (['#submit-revision', '#review-and-submit', '#initial-review-workflow'].includes(window.location.hash) || window.location.hash.startsWith('#file-review-card-')) {
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
                if (window.location.hash !== '#project-monitoring' && ! (window.location.hash.startsWith('#monitoring-tool-') || window.location.hash.startsWith('#narrative-report-'))) {
                    return;
                }
                this.$nextTick(() => {
                    const targetId = (window.location.hash.startsWith('#monitoring-tool-') || window.location.hash.startsWith('#narrative-report-'))
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
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('revision_submitted')): ?>
            <div id="revision-submitted" data-revision-submission-success role="status" class="rounded-2xl border border-green-200 bg-green-50 p-5 text-green-950 shadow-sm dark:border-green-900 dark:bg-green-950/40 dark:text-green-100">
                <div class="flex items-start gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-green-700 text-white" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4 4L19 7" /></svg>
                    </span>
                    <div>
                        <p class="font-black">Revision sent successfully</p>
                        <p class="mt-1 text-sm leading-6"><?php echo e(session('success')); ?> It is now waiting for the Research Head’s review.</p>
                    </div>
                </div>
            </div>
        <?php elseif(session('success')): ?>
            <div data-topic-success role="status" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-100"><?php echo e(session('success')); ?></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any() || $resubmissionErrors->any()): ?>
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <p class="font-bold">The action could not be completed.</p>
                <p class="mt-1 text-sm"><?php echo e($resubmissionErrors->first() ?: $errors->first()); ?></p>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isFacultyWorkspace && ($topic->hasIssuedNoticeToProceed() || $topic->isCompletedProject()) && $hasProjectAccess): ?>
            <section data-research-workspace-handoff class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900 sm:flex-row sm:items-center sm:justify-between">
                <div class="max-w-xl">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white"><?php echo e($topic->isCompletedProject() ? 'Research completed' : 'In monitoring'); ?></h2>
                    <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">The signed proposal papers and Notice to Proceed are in Files. Use Switch Workspace in your account menu to manage monitoring and reports as Faculty Researcher.</p>
                </div>
            </section>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="space-y-3">
            <nav class="flex items-center justify-between gap-4 overflow-x-auto rounded-2xl border border-gray-200 bg-white p-2 shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-label="Proposal workspace sections">
                <div class="flex shrink-0 gap-1" role="tablist" aria-label="Proposal workspace sections">
                <button id="proposal-details-tab-button" type="button" role="tab" aria-controls="proposal-details-tab" :aria-selected="activeTopicTab === 'details'" @click="setTopicTab('details', 'proposal-details')" :class="activeTopicTab === 'details' ? 'bg-gray-900 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'" class="flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5.25A2.25 2.25 0 0 1 6.25 3h11.5A2.25 2.25 0 0 1 20 5.25v13.5A2.25 2.25 0 0 1 17.75 21H6.25A2.25 2.25 0 0 1 4 18.75V5.25Z" /><path stroke-linecap="round" d="M8 8h8M8 12h8M8 16h5" /></svg>
                    Proposal
                </button>
                <button id="proposal-review-tab-button" type="button" role="tab" aria-controls="proposal-review-tab" :aria-selected="activeTopicTab === 'review'" @click="setTopicTab('review', '<?php echo e($reviewTabHash); ?>')" :class="activeTopicTab === 'review' ? 'bg-gray-900 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'" class="flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h10.5A2.25 2.25 0 0 1 19.5 6v14.25H4.5V6a2.25 2.25 0 0 1 2.25-2.25Z" /><path stroke-linecap="round" d="M8.25 9.5h7.5M8.25 13h5.25" /></svg>
                    <?php echo e($isResearchHead ? (($canReturnToRevision || $topic->status === 'approved') ? 'Review history' : 'Review & decision') : ($isResearchOffice ? 'LREC comments' : 'Review status')); ?>

                </button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canViewNoticeToProceed): ?>
                    <button id="notice-to-proceed-tab-button" type="button" role="tab" aria-controls="notice-to-proceed-tab" :aria-selected="activeTopicTab === 'notice'" @click="setTopicTab('notice', 'notice-to-proceed')" :class="activeTopicTab === 'notice' ? 'bg-gray-900 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'" class="flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /><path stroke-linecap="round" d="M9 13.5l2 2 4-4" /></svg>
                        <?php echo e($topic->hasIssuedNoticeToProceed() ? 'Released documents' : 'Signing & release'); ?>

                    </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canViewMonitoring): ?>
                    <button id="project-monitoring-tab-button" type="button" role="tab" aria-controls="project-monitoring-tab" :aria-selected="activeTopicTab === 'monitoring'" @click="setTopicTab('monitoring', 'project-monitoring')" :class="activeTopicTab === 'monitoring' ? 'bg-gray-900 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'" class="flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 19.5V10m5.25 9.5V4.5m5.25 15v-7m5.25 7V7" /></svg>
                        Monitoring
                    </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <button id="version-history-tab-button" type="button" role="tab" aria-controls="version-history-tab" :aria-selected="activeTopicTab === 'history'" @click="setTopicTab('history', 'version-history')" :class="activeTopicTab === 'history' ? 'bg-gray-900 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'" class="flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    Versions
                </button>
                </div>
                <div x-show="activeTopicTab === 'history'" x-cloak class="flex shrink-0 items-center justify-end gap-2 border-l border-slate-200 pl-3 dark:border-slate-700">
                    <button
                        type="button"
                        x-show="activeTopicTab === 'history'"
                        x-cloak
                        @click="decisionHistoryOpen = ! decisionHistoryOpen"
                        data-decision-history-toggle
                        :aria-expanded="decisionHistoryOpen.toString()"
                        aria-expanded="false"
                        aria-controls="decision-history-list"
                        class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:text-slate-300 dark:hover:bg-slate-800"
                    >
                        View Decision History (<?php echo e($decisionReviews->count()); ?>)
                    </button>
                </div>
            </nav>
        </div>

        <div class="mb-5" data-visible-proposal-workflow>
            <?php if (isset($component)) { $__componentOriginal30d528a6ab0933c570934791a85cd659 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal30d528a6ab0933c570934791a85cd659 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-workflow','data' => ['topic' => $topic,'version' => $latestVersion,'reviews' => $topic->reviews]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-workflow'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['topic' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($topic),'version' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($latestVersion),'reviews' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($topic->reviews)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal30d528a6ab0933c570934791a85cd659)): ?>
<?php $attributes = $__attributesOriginal30d528a6ab0933c570934791a85cd659; ?>
<?php unset($__attributesOriginal30d528a6ab0933c570934791a85cd659); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal30d528a6ab0933c570934791a85cd659)): ?>
<?php $component = $__componentOriginal30d528a6ab0933c570934791a85cd659; ?>
<?php unset($__componentOriginal30d528a6ab0933c570934791a85cd659); ?>
<?php endif; ?>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $isFacultyRevision && Auth::user()->can('updatePackage', $topic)): ?>
            <section data-proposal-package-update class="rounded-xl border border-blue-200 bg-blue-50 p-5 dark:border-blue-900 dark:bg-blue-950/30">
                <h3 class="font-bold text-gray-950 dark:text-white">Update before review</h3>
                <p class="mt-2 text-sm leading-6 text-gray-700 dark:text-slate-200">The Research Head has not opened this proposal. Edit the papers or replace PDFs, then submit Version <?php echo e(($latestVersion?->version_number ?? 1) + 1); ?>. Version <?php echo e($latestVersion?->version_number ?? 1); ?> stays in Versions. Editing closes when the Research Head opens the submission.</p>
                <a href="<?php echo e(route('faculty.topics.edit-package', $topic)); ?>" class="mt-4 inline-flex min-h-11 items-center rounded-lg bg-red-700 px-4 py-2 font-semibold text-white hover:bg-red-800"><?php echo e($topic->revisionDraft ? 'Continue proposal update' : 'Update submitted proposal'); ?></a>
            </section>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <section id="proposal-details-tab" x-show="activeTopicTab === 'details'" x-cloak role="tabpanel" aria-labelledby="proposal-details-tab-button">
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <section id="submitted-files" aria-labelledby="submitted-files-heading" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                        <div>
                            <p class="text-xs font-black uppercase tracking-wider text-red-600">Latest faculty submission</p>
                            <h3 id="submitted-files-heading" class="mt-1 text-lg font-black text-gray-900">Project</h3>
                            <p class="mt-1 text-sm text-gray-600">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($latestVersion): ?>
                                    Version <?php echo e($latestVersion->version_number); ?> submitted by <?php echo e($latestVersion->submitter?->name ?? $topic->user->name); ?> on <?php echo e($latestVersion->created_at->format('M j, Y g:i A')); ?>.
                                <?php else: ?>
                                    No submitted version is available.
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex w-fit rounded-full px-3 py-1.5 text-xs font-black uppercase tracking-wider <?php echo e($availableSubmittedFileIds->count() === $submittedFiles->count() && $submittedFiles->isNotEmpty() ? 'bg-gray-950 text-white dark:bg-white dark:text-gray-950' : 'bg-red-50 text-red-800 dark:bg-red-950/40 dark:text-red-200'); ?>">
                                <?php echo e($availableSubmittedFileIds->count()); ?>/<?php echo e($submittedFiles->count()); ?> files available
                            </span>
                        </div>
                    </div>

                    <div class="p-5 sm:p-6" data-latest-package-summary="<?php echo e($latestVersion?->id); ?>">
                        <div class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-900 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h4 class="text-sm font-black text-gray-950 dark:text-white">Latest submission</h4>
                                <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-600 dark:text-gray-300">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($latestVersion): ?>
                                        Version <?php echo e($latestVersion->version_number); ?> contains <?php echo e($proposalPaperCount); ?> proposal <?php echo e(\Illuminate\Support\Str::plural('paper', $proposalPaperCount)); ?><?php echo e($assessmentFormCount > 0 ? ' and '.$assessmentFormCount.' automatically generated assessment '.\Illuminate\Support\Str::plural('form', $assessmentFormCount) : ''); ?>. Open the project folder to view them in separate categories alongside signed papers, review responses, and later project records.
                                    <?php else: ?>
                                        No submission is available yet. Generated and uploaded PDFs will appear in the project folder.
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </p>
                            </div>
                            <button type="button" @click="$dispatch('open-project-documents')" class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-gray-950 px-4 py-2.5 text-sm font-black text-white transition hover:bg-gray-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-900 focus-visible:ring-offset-2 dark:bg-white dark:text-gray-950">
                                Browse <?php echo e($projectDocumentLibrary['total']); ?> <?php echo e(\Illuminate\Support\Str::plural('file', $projectDocumentLibrary['total'])); ?>

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
                            <dd class="mt-1 text-lg font-black text-gray-900">PHP <?php echo e(number_format($displayProjectCost, 2)); ?></dd>
                        </div>
                        <div class="border-t border-gray-100 pt-3">
                            <dt class="text-xs font-bold uppercase text-gray-500">Duration</dt>
                            <dd class="mt-1 text-sm font-bold text-gray-700"><?php echo e($topic->estimated_duration_months); ?> months</dd>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topic->hasIssuedNoticeToProceed() && ! $canViewNoticeToProceed): ?>
                            <div id="notice-to-proceed" data-released-notice-summary class="scroll-mt-32 border-t border-gray-100 pt-3 dark:border-slate-800">
                                <dt class="text-xs font-semibold text-gray-500 dark:text-slate-400">Notice to Proceed issued</dt>
                                <dd class="mt-1 text-sm font-semibold text-gray-700 dark:text-slate-200"><?php echo e($topic->notice_to_proceed_issued_at->format('M j, Y g:i A')); ?></dd>
                                <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-slate-400">The signed copy and released proposal papers are available in Files.</p>
                            </div>
                            <div class="border-t border-gray-100 pt-3 dark:border-slate-800">
                                <dt class="text-xs font-semibold text-gray-500 dark:text-slate-400">Approved period</dt>
                                <dd class="mt-1 text-sm font-semibold text-gray-700 dark:text-slate-200"><?php echo e(data_get($topic->notice_to_proceed_data, 'approved_start_date', '—')); ?> to <?php echo e(data_get($topic->notice_to_proceed_data, 'approved_end_date', '—')); ?></dd>
                            </div>
                            <div class="border-t border-gray-100 pt-3 dark:border-slate-800">
                                <dt class="text-xs font-semibold text-gray-500 dark:text-slate-400">Approved budget</dt>
                                <dd class="mt-1 text-sm font-semibold text-gray-700 dark:text-slate-200">PHP <?php echo e(number_format((float) data_get($topic->notice_to_proceed_data, 'approved_budget', $topic->estimated_budget), 2)); ?></dd>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topic->category): ?>
                            <div class="border-t border-gray-100 pt-3">
                                <dt class="text-xs font-bold uppercase text-gray-500">Category</dt>
                                <dd class="mt-1 text-sm font-bold text-gray-700"><?php echo e($topic->category->name); ?></dd>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </dl>
                    <p class="mt-4 whitespace-pre-line border-t border-gray-100 pt-4 text-sm leading-6 text-gray-600"><?php echo e($topic->description ?: 'No proposal summary provided.'); ?></p>
                </section>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topic->collaborators->isNotEmpty()): ?>
                    <section aria-labelledby="project-team-heading" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-black uppercase tracking-wider text-red-600">Shared workspace</p>
                        <h3 id="project-team-heading" class="mt-1 text-sm font-black text-gray-900">Project team</h3>
                        <p class="mt-1 text-xs leading-5 text-gray-500">The same team remains attached through review, final signing, and project monitoring.</p>
                        <ul class="mt-4 space-y-3">
                            <li class="flex items-start gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-950 text-xs font-black text-white">PL</span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-bold text-gray-900"><?php echo e($topic->user->name); ?></span>
                                    <span class="block text-xs text-gray-500">Project leader</span>
                                </span>
                            </li>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $topic->collaborators; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $collaborator): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <li class="flex items-start gap-3">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-red-50 text-xs font-black text-red-700">TM</span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-bold text-gray-900"><?php echo e($collaborator->name); ?></span>
                                        <span class="block truncate text-xs text-gray-500"><?php echo e($collaborator->email); ?></span>
                                        <span class="mt-0.5 block text-xs font-semibold <?php echo e($collaborator->accepted_at ? 'text-emerald-700' : 'text-amber-700'); ?>"><?php echo e($collaborator->accepted_at ? 'Accepted team member' : 'Invitation pending'); ?></span>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($collaborator->isProjectSecretary() || $collaborator->user_id === $topic->research_secretary_id): ?>
                                            <span class="mt-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-amber-800">Project Secretary</span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </span>
                                </li>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </ul>
                    </section>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </section>

        <?php
            $initialResearchHeadDecision = old('status', request()->query('decision') === 'revision_requested' ? 'revision_requested' : '');
            if (! array_key_exists($initialResearchHeadDecision, $researchHeadDecisionOptions)) {
                $initialResearchHeadDecision = '';
            }
        ?>
        <section id="proposal-review-tab" x-data="{ decision: <?php echo \Illuminate\Support\Js::from($initialResearchHeadDecision)->toHtml() ?> }" x-show="activeTopicTab === 'review'" x-cloak role="tabpanel" aria-labelledby="proposal-review-tab-button" class="space-y-4">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isResearchHead && $latestVersion && $topic->review_stage !== 'lrec' && in_array($topic->status, ['pending', 'resubmitted', 'expert_review', 'for_final_decision'], true)): ?>
                <div class="flex flex-wrap justify-end gap-2">
                    <a data-research-head-screening-form href="<?php echo e(route('research_head.topics.initial-screening-form.edit', [$topic, $latestVersion])); ?>" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-50 focus-visible:ring-2 focus-visible:ring-red-700 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200 dark:hover:bg-gray-800"><?php echo e(Auth::user()->can('fillInitialScreeningForm', [$topic, $latestVersion]) ? 'Fill Initial Screening Form' : 'View Initial Screening Form'); ?></a>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canDecide || ($isResearchHead && $topic->status === 'revision_requested')): ?>
                <section <?php if($isAssessmentStage): ?> data-assessment-revision-papers x-show="decision === 'revision_requested'" x-cloak <?php else: ?> data-active-paper-review <?php endif; ?> class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900 sm:p-6" aria-labelledby="file-review-checklist-heading">
                    <h3 id="file-review-checklist-heading" class="text-base font-semibold text-gray-900 dark:text-gray-100"><?php echo e($isAssessmentStage ? 'Select papers for revision' : 'Proposal papers for review'); ?> <span class="ml-2 text-sm font-normal text-gray-500">Version <?php echo e($latestVersion?->version_number ?? 1); ?></span></h3>
                    <div data-review-feedback-preview class="my-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="max-w-2xl text-sm leading-6 text-gray-600 dark:text-gray-300"><?php echo e($isAssessmentStage ? 'Select the papers affected by this assessment and add any instructions for the faculty revision. Recorded co-evaluator comments are included in the Comment Response paper.' : ($canDecide ? ($topic->review_stage === 'lrec' ? 'Committee comments and saved highlights are included in the Comment Response paper.' : 'Highlight sections that require revision and add a comment. Saved comments are included in the Comment Response paper.') : 'Revision request sent. Open a paper to view its saved comments while the faculty member prepares the next version.')); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($isAssessmentStage)): ?> The generated GAD and screening forms are in the project folder; their completed copies are handled in the separate assessment steps. <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canDecide && $latestVersion): ?>
                            <button type="button" data-comment-response-preview-button aria-haspopup="dialog" @click="$dispatch('open-modal', 'review-comment-response-<?php echo e($topic->id); ?>')" class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200 dark:hover:bg-gray-800 dark:focus-visible:ring-offset-gray-950"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7S2 12 2 12Z" /><circle cx="12" cy="12" r="3" /></svg>Preview Comment Response Paper</button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <?php echo $__env->make('topics.partials.revision-file-selector', ['files' => $submittedFiles, 'disableUnlessRevision' => $canDecide, 'decisionFormId' => $canDecide ? 'research-head-decision-form' : null, 'prioritizeRevisedFiles' => true, 'showGuidance' => false, 'showReviewChecks' => ! $isAssessmentStage, 'readOnlyReview' => ! $canDecide], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['revision_file_ids'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-4 text-sm font-semibold text-red-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </section>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isResearchOffice): ?>
                <section data-lrec-office-feedback class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-black uppercase tracking-wider text-red-700 dark:text-red-300">Research Office · LREC</p>
                            <h3 class="mt-1 text-xl font-black text-gray-950 dark:text-white">Committee feedback</h3>
                        </div>
                        <button type="button" @click="$dispatch('open-project-documents')" class="inline-flex min-h-11 items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-bold text-gray-700 hover:bg-gray-50 dark:border-slate-700 dark:text-slate-200">Open project folder</button>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topic->status === \App\Models\TopicProposal::STATUS_LREC_REVIEW): ?>
                        <p class="mt-3 max-w-3xl text-sm leading-6 text-gray-600 dark:text-slate-300">Record each LREC comment below. Sending feedback opens a faculty revision round and places these comments in the Comment Response paper. Only the Research Head can clear, reject, or sign the proposal.</p>
                        <form method="POST" action="<?php echo e(route('research_coordinator.topics.lrec-feedback.store', $topic)); ?>" x-data="{ decision: 'revision_requested' }" class="mt-5 space-y-5">
                            <?php echo csrf_field(); ?>
                            <?php echo $__env->make('topics.partials.lrec-comments', ['initialCommitteeComments' => [['reviewer' => 'LREC committee', 'location' => '', 'comment' => '']]], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-red-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-red-800">Send LREC comments to faculty</button>
                        </form>
                    <?php elseif($topic->status === \App\Models\TopicProposal::STATUS_LREC_QUEUED): ?>
                        <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-slate-300">This proposal is awaiting its LREC presentation. After the presentation, open the review stage to record committee comments.</p>
                        <form method="POST" action="<?php echo e(route('research_coordinator.topics.lrec-review.start', $topic)); ?>" class="mt-4">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-red-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-red-800">Presentation complete — record comments</button>
                        </form>
                    <?php elseif($topic->status === 'revision_requested'): ?>
                        <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-slate-300">The LREC comments have been sent. The faculty member is preparing a revised submission; the Comment Response paper is available in the project folder and decision history.</p>
                    <?php else: ?>
                        <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-slate-300">The faculty revision has been submitted. The Research Head will review the response and record the next LREC outcome.</p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </section>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isFacultyRevision): ?>
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
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($latestRevisionReview?->comment): ?>
                                        <blockquote class="mt-5 border-l-4 border-red-200 pl-4 text-sm leading-6 text-gray-800 dark:border-red-900 dark:text-slate-100"><?php echo e($latestRevisionReview->comment); ?></blockquote>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col justify-between gap-6 bg-red-50/60 p-6 dark:bg-red-950/20 lg:p-8">
                            <dl class="grid grid-cols-2 gap-5">
                                <div><dt class="text-xs font-semibold text-gray-500 dark:text-slate-400">Requested papers</dt><dd class="mt-1 text-2xl font-black tabular-nums text-gray-950 dark:text-white"><?php echo e($pendingFileRevisions->groupBy('document_type')->count()); ?></dd></div>
                                <div><dt class="text-xs font-semibold text-gray-500 dark:text-slate-400">Review comments</dt><dd class="mt-1 text-2xl font-black tabular-nums text-gray-950 dark:text-white"><?php echo e(count($commentResponseRows)); ?></dd></div>
                            </dl>
                            <a href="<?php echo e(route('faculty.topics.revision', $topic)); ?>" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-red-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-950">
                                Open revision workspace
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" /></svg>
                            </a>
                        </div>
                    </div>
                </section>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $isResearchHead && ! $isResearchOffice && ! $isFacultyRevision): ?>
                <div class="rounded-2xl border border-red-200 bg-red-50 p-5 sm:p-6">
                    <h3 class="text-lg font-black text-gray-900">What happens next</h3>
                    <p class="mt-2 max-w-4xl text-sm leading-6 text-gray-700">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topic->status === 'revision_requested'): ?>
                            The Research Head requested changes. Review the highlighted comments and file-specific instructions, then replace only the files marked for revision.
                        <?php elseif($topic->isAwaitingNoticeToProceed()): ?>
                            Final signing is complete. Wait for the Research Head to issue the Notice to Proceed before beginning the project or entering monitoring.
                        <?php elseif($topic->isCompletedProject()): ?>
                            This project is complete and archived. Its approved papers, Notice to Proceed, and previous monitoring records remain available as read-only records.
                        <?php elseif($topic->status === 'approved'): ?>
                            Your Notice to Proceed has been issued. The proposal is now an active research project and monitoring is open.
                        <?php elseif($topic->status === \App\Models\TopicProposal::STATUS_READY_FOR_SIGNATURE): ?>
                            Reviews are complete. The research office is collecting signed papers and the signed Notice to Proceed. They will be released together.
                        <?php elseif($topic->status === 'lrec_queued'): ?>
                            Initial review is complete. Await the LREC presentation schedule from the research office.
                        <?php elseif($topic->status === 'lrec_review'): ?>
                            The proposal is under LREC review. The research office will share committee revisions or move it to signing.
                        <?php elseif($topic->status === 'rejected'): ?>
                            This proposal received a final rejection.
                        <?php else: ?>
                            The proposal is with the Research Head. You will be notified when a decision or revision request is shared.
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </p>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isResearchHead && $topic->status === \App\Models\TopicProposal::STATUS_GAD_REVIEW && $headUploadWorkspace): ?>
                <?php if (isset($component)) { $__componentOriginalbf0c2fa2d41eba61d5c53ef20c632192 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbf0c2fa2d41eba61d5c53ef20c632192 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.research-head-file-workspace','data' => ['topic' => $topic,'workspace' => $headUploadWorkspace,'returnToReview' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('research-head-file-workspace'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['topic' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($topic),'workspace' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($headUploadWorkspace),'return-to-review' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbf0c2fa2d41eba61d5c53ef20c632192)): ?>
<?php $attributes = $__attributesOriginalbf0c2fa2d41eba61d5c53ef20c632192; ?>
<?php unset($__attributesOriginalbf0c2fa2d41eba61d5c53ef20c632192); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbf0c2fa2d41eba61d5c53ef20c632192)): ?>
<?php $component = $__componentOriginalbf0c2fa2d41eba61d5c53ef20c632192; ?>
<?php unset($__componentOriginalbf0c2fa2d41eba61d5c53ef20c632192); ?>
<?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isResearchHead && $topic->status === 'lrec_queued'): ?>
                <section data-lrec-waiting-workspace aria-labelledby="lrec-waiting-heading" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-950">
                    <div class="grid lg:grid-cols-[minmax(0,1fr)_21rem]">
                        <div class="p-5 sm:p-7">
                            <div class="flex items-start gap-4">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300" aria-hidden="true">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="15" rx="2" /><path stroke-linecap="round" d="M8 3v4m8-4v4M4 10h16m-11 5h6" /></svg>
                                </span>
                                <div class="min-w-0">
                                    <h3 id="lrec-waiting-heading" class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">Awaiting LREC presentation</h3>
                                    <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-600 dark:text-gray-300">Initial review is complete and this proposal has been sent to LREC. The next decision can be recorded after the presentation.</p>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($latestVersion): ?>
                                        <p class="mt-3 text-xs font-semibold text-gray-500 dark:text-gray-400">Project · Version <?php echo e($latestVersion->version_number); ?> · <?php echo e($proposalPaperCount); ?> proposal <?php echo e(\Illuminate\Support\Str::plural('paper', $proposalPaperCount)); ?><?php echo e($assessmentFormCount > 0 ? ' + '.$assessmentFormCount.' assessment '.\Illuminate\Support\Str::plural('form', $assessmentFormCount) : ''); ?></p>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            </div>

                            <ol aria-label="LREC review progress" class="mt-6 grid gap-3 border-t border-gray-100 pt-5 dark:border-gray-800 sm:grid-cols-3 sm:gap-4">
                                <li class="flex items-center gap-3 text-sm">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300" aria-hidden="true"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg></span>
                                    <span><span class="block font-semibold text-gray-900 dark:text-white">Initial review</span><span class="block text-xs text-gray-500 dark:text-gray-400">Complete</span></span>
                                </li>
                                <li class="flex items-center gap-3 text-sm" aria-current="step">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 border-red-700 bg-red-50 text-red-700 dark:border-red-400 dark:bg-red-950/40 dark:text-red-300" aria-hidden="true"><span class="h-2 w-2 rounded-full bg-current"></span></span>
                                    <span><span class="block font-semibold text-gray-900 dark:text-white">Presentation</span><span class="block text-xs font-semibold text-red-700 dark:text-red-300">Awaiting LREC</span></span>
                                </li>
                                <li class="flex items-center gap-3 text-sm">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-gray-300 text-xs font-bold text-gray-500 dark:border-gray-700 dark:text-gray-400" aria-hidden="true">3</span>
                                    <span><span class="block font-semibold text-gray-900 dark:text-white">Committee outcome</span><span class="block text-xs text-gray-500 dark:text-gray-400">Next</span></span>
                                </li>
                            </ol>
                        </div>

                        <div class="flex flex-col justify-between gap-5 border-t border-gray-200 bg-gray-50 p-5 dark:border-gray-800 dark:bg-gray-900 lg:border-l lg:border-t-0 sm:p-7">
                            <div>
                                <h4 class="text-base font-bold text-gray-950 dark:text-white">After the presentation</h4>
                                <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">Continue here to record the committee’s comments or its clearance for signing.</p>
                            </div>
                            <div class="grid gap-2">
                                <form action="<?php echo e(route('research_head.topics.updateStatus', $topic)); ?>" method="POST">
                                    <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                                    <input type="hidden" name="status" value="lrec_review">
                                    <input type="hidden" name="redirect_to" value="topic">
                                    <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-red-700 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">Presentation complete — record outcome</button>
                                </form>
                                <button type="button" @click="$dispatch('open-project-documents')" class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-bold text-gray-800 transition hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:hover:bg-gray-800 dark:focus-visible:ring-offset-gray-900">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75A2.25 2.25 0 0 1 6 4.5h3.19c.597 0 1.17.237 1.591.659l1.06 1.06c.422.422.994.659 1.591.659H18A2.25 2.25 0 0 1 20.25 9.13v7.62A2.25 2.25 0 0 1 18 19H6a2.25 2.25 0 0 1-2.25-2.25v-10Z" /></svg>
                                    Open project folder
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canDecide && $researchHeadDecisionOptions !== []): ?>
                <section x-data="{ open: true }" data-review-decision-disclosure data-initially-open="true" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-950" data-latest-review-version="<?php echo e($latestVersion?->version_number); ?>" data-latest-review-version-id="<?php echo e($latestVersion?->id); ?>">
                    <button type="button" @click="open = !open" :aria-expanded="open" aria-controls="review-decision-content" class="flex min-h-12 w-full items-center justify-between gap-4 px-5 py-3 text-left hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700 dark:hover:bg-gray-900 sm:px-6">
                        <span class="text-base font-bold text-gray-950 dark:text-white"><?php echo e($topic->review_stage === 'lrec' ? 'Record the LREC outcome' : ($topic->status === \App\Models\TopicProposal::STATUS_GAD_REVIEW ? 'Record the review outcome' : 'Record the Research Head decision')); ?></span>
                        <svg :class="open ? 'rotate-180' : ''" class="h-5 w-5 text-gray-500 transition-transform motion-reduce:transition-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
                    </button>
                    <div id="review-decision-content" x-show="open" x-cloak x-transition class="border-t border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-950">
                        <form
                            id="research-head-decision-form"
                            action="<?php echo e(route('research_head.topics.updateStatus', $topic)); ?>"
                            method="POST"
                            x-data="{
                                submitting: false,
                                researchHeadClearanceConfirmed: <?php echo \Illuminate\Support\Js::from((bool) old('research_head_clearance_confirmed'))->toHtml() ?>,
                                initialClearanceConfirmed: <?php echo \Illuminate\Support\Js::from((bool) old('initial_clearance_confirmed'))->toHtml() ?>,
                                lrecClearanceConfirmed: <?php echo \Illuminate\Support\Js::from((bool) old('lrec_clearance_confirmed'))->toHtml() ?>,
                                rejectionConfirmed: <?php echo \Illuminate\Support\Js::from((bool) old('rejection_confirmed'))->toHtml() ?>,
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
                            <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
                                <div role="alert" class="text-sm text-red-700 dark:text-red-300"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><p><?php echo e($error); ?></p><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <input type="hidden" name="redirect_to" value="topic">
                            <fieldset>
                                <legend class="sr-only">Review decision</legend>
                                <div class="flex flex-wrap gap-2" data-review-decision-options>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $researchHeadDecisionOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $decisionValue => $decisionLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <label
                                            class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border px-4 py-2.5 text-base font-semibold transition focus-within:ring-2 focus-within:ring-red-600 focus-within:ring-offset-2 dark:focus-within:ring-offset-gray-900"
                                            :class="decision === <?php echo \Illuminate\Support\Js::from($decisionValue)->toHtml() ?> ? 'border-red-700 bg-red-50 text-red-800 dark:border-red-500 dark:bg-red-950/30 dark:text-red-200' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800'"
                                        >
                                            <input class="h-4 w-4 border-gray-400 text-red-700 focus:ring-red-600" type="radio" name="status" value="<?php echo e($decisionValue); ?>" x-model="decision" required>
                                            <?php echo e($decisionLabel); ?>

                                        </label>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['status'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-2 text-sm text-red-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </fieldset>

                            <section x-show="decision === 'gad_review'" x-cloak>
                                <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-slate-200">
                                    <input type="checkbox" name="research_head_clearance_confirmed" value="1" x-model="researchHeadClearanceConfirmed" :disabled="decision !== 'gad_review'" :required="decision === 'gad_review'" class="mt-1 rounded border-gray-300 text-red-700">
                                    <span>I reviewed the latest proposal version and confirm that all Research Head comments have been addressed. This version may proceed to the GAD Office.</span>
                                </label>
                            </section>
                            <section x-show="decision === 'lrec_queued'" x-cloak>
                                <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-slate-200">
                                    <input type="checkbox" name="initial_clearance_confirmed" value="1" x-model="initialClearanceConfirmed" :disabled="decision !== 'lrec_queued'" :required="decision === 'lrec_queued'" class="mt-1 rounded border-gray-300 text-red-700">
                                    <span>A passing GAD Office assessment and the co-evaluator’s Narrative Evaluation are recorded for this version.</span>
                                </label>
                            </section>
                            <section x-show="decision === 'ready_for_signature'" x-cloak class="space-y-3">
                                <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-slate-200">
                                    <input type="checkbox" name="lrec_clearance_confirmed" value="1" x-model="lrecClearanceConfirmed" :disabled="decision !== 'ready_for_signature'" :required="decision === 'ready_for_signature'" class="mt-1 rounded border-gray-300 text-red-700">
                                    <span>LREC has cleared this version and all committee comments have been addressed.</span>
                                </label>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">Required signed proposal papers</p>
                                        <ul class="mt-2 space-y-1 text-sm text-gray-700 dark:text-slate-200">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $submittedFiles->whereIn('document_type', \App\Services\ProposalSignatureWorkflow::REQUIRED_DOCUMENT_TYPES)->reject(fn (\App\Models\ProposalVersionFile $file): bool => $file->isGeneratedAssessmentForm()); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $signatureFile): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <li><?php echo e($signatureFile->label()); ?></li>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </ul>
                                    </div>
                                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">Required signed assessment forms</p>
                                        <ul class="mt-2 space-y-1 text-sm text-gray-700 dark:text-slate-200">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $submittedFiles->whereIn('document_type', \App\Services\ProposalSignatureWorkflow::REQUIRED_DOCUMENT_TYPES)->filter(fn (\App\Models\ProposalVersionFile $file): bool => $file->isGeneratedAssessmentForm()); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $signatureFile): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <li><?php echo e($signatureFile->label()); ?></li>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                                <p class="text-sm leading-6 text-gray-500 dark:text-gray-400">Attachment C and Estimated Expense Breakdown do not require signatures. All five listed documents require signed PDFs before final release.</p>
                            </section>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topic->review_stage === 'lrec'): ?>
                                <?php echo $__env->make('topics.partials.lrec-comments', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <section x-show="decision === 'rejected'" x-cloak class="space-y-3" aria-labelledby="rejection-reason-heading">
                                <label id="rejection-reason-heading" class="block text-base font-semibold text-gray-900 dark:text-gray-100" for="rejection_reason">Reason for rejection</label>
                                <textarea id="rejection_reason" name="rejection_reason" rows="4" maxlength="2000" x-bind:required="decision === 'rejected'" aria-describedby="rejection-reason-help" class="block w-full rounded-xl border-gray-300 text-base leading-7 focus:border-red-600 focus:ring-red-600 dark:border-gray-700 dark:bg-gray-900 dark:text-white" placeholder="Explain why this proposal cannot proceed."><?php echo e(old('rejection_reason')); ?></textarea>
                                <p id="rejection-reason-help" class="text-sm text-gray-500 dark:text-gray-400">Shared with the faculty member. Maximum 2,000 characters.</p>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['rejection_reason'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-sm text-red-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <input id="rejection_confirmed" name="rejection_confirmed" type="checkbox" value="1" x-model="rejectionConfirmed" x-bind:required="decision === 'rejected'" class="mt-0.5 rounded border-gray-400 text-red-700 focus:ring-red-600">
                                    <span>I understand this closes the submission and the rejection is final.</span>
                                </label>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['rejection_confirmed'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-sm text-red-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </section>

                            <p x-show="decision === 'revision_requested'" x-cloak class="text-sm text-gray-600 dark:text-gray-300">Select the papers that need further changes in the document list above.</p>

                            <div data-review-revision-actions class="flex flex-wrap items-center gap-3">
                                <button type="submit" :disabled="submitting || !decision || (decision === 'gad_review' && !researchHeadClearanceConfirmed) || (decision === 'lrec_queued' && !initialClearanceConfirmed) || (decision === 'ready_for_signature' && !lrecClearanceConfirmed) || (decision === 'rejected' && !rejectionConfirmed)" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-red-700 px-6 py-3 text-base font-bold text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                                    <span x-text="submitting ? 'Saving decision…' : ({ rejected: 'Reject proposal', revision_requested: 'Send revision request', gad_review: 'Clear for GAD assessment', lrec_queued: 'Route to LREC', ready_for_signature: 'Proceed to signing' }[decision] || 'Save decision')">Send revision request</span>
                                </button>
                            </div>
                        </form>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($latestVersion): ?>
                            <?php if (isset($component)) { $__componentOriginal9f64f32e90b9102968f2bc548315018c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9f64f32e90b9102968f2bc548315018c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.modal','data' => ['name' => 'review-comment-response-'.e($topic->id).'','maxWidth' => '6xl','focusable' => true,'class' => '!z-[140]','dataCommentResponsePreviewModal' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'review-comment-response-'.e($topic->id).'','maxWidth' => '6xl','focusable' => true,'class' => '!z-[140]','data-comment-response-preview-modal' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                                <template x-if="show">
                                    <section data-comment-response-preview-content role="dialog" aria-modal="true" aria-labelledby="review-comment-response-heading-<?php echo e($topic->id); ?>">
                                        <header class="flex items-center justify-between gap-4 border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                                            <h3 id="review-comment-response-heading-<?php echo e($topic->id); ?>" class="text-base font-bold text-gray-950 dark:text-white">Comment Response paper preview</h3>
                                            <button type="button" @click="$dispatch('close')" class="inline-flex min-h-11 shrink-0 items-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-bold text-gray-800 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:hover:bg-gray-800">Close preview</button>
                                        </header>
                                        <?php if (isset($component)) { $__componentOriginal13c1f2a64fc7e3eb9a4f045c0d853353 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal13c1f2a64fc7e3eb9a4f045c0d853353 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-revision-pdf','data' => ['configuration' => ['pdfUrl' => route('research_head.topics.comment-response-form.pdf', ['topic' => $topic, 'draft_version' => $latestVersion->id]), 'annotations' => [], 'canAnnotate' => false],'loadingLabel' => 'Loading Comment Response paper…','viewerLabel' => 'Comment Response paper','class' => '!h-[75dvh]']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-revision-pdf'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['configuration' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['pdfUrl' => route('research_head.topics.comment-response-form.pdf', ['topic' => $topic, 'draft_version' => $latestVersion->id]), 'annotations' => [], 'canAnnotate' => false]),'loading-label' => 'Loading Comment Response paper…','viewer-label' => 'Comment Response paper','class' => '!h-[75dvh]']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal13c1f2a64fc7e3eb9a4f045c0d853353)): ?>
<?php $attributes = $__attributesOriginal13c1f2a64fc7e3eb9a4f045c0d853353; ?>
<?php unset($__attributesOriginal13c1f2a64fc7e3eb9a4f045c0d853353); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal13c1f2a64fc7e3eb9a4f045c0d853353)): ?>
<?php $component = $__componentOriginal13c1f2a64fc7e3eb9a4f045c0d853353; ?>
<?php unset($__componentOriginal13c1f2a64fc7e3eb9a4f045c0d853353); ?>
<?php endif; ?>
                                    </section>
                                </template>
                             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9f64f32e90b9102968f2bc548315018c)): ?>
<?php $attributes = $__attributesOriginal9f64f32e90b9102968f2bc548315018c; ?>
<?php unset($__attributesOriginal9f64f32e90b9102968f2bc548315018c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9f64f32e90b9102968f2bc548315018c)): ?>
<?php $component = $__componentOriginal9f64f32e90b9102968f2bc548315018c; ?>
<?php unset($__componentOriginal9f64f32e90b9102968f2bc548315018c); ?>
<?php endif; ?>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </section>
            <?php elseif(Auth::user()->isUsingWorkspace('research_head')): ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canReturnToRevision || $topic->status === 'approved'): ?>
                    <?php if (isset($component)) { $__componentOriginald4e58852d241004aa4963ab6c133e254 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald4e58852d241004aa4963ab6c133e254 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-review-summary','data' => ['topic' => $topic,'version' => $latestVersion,'workspace' => $headUploadWorkspace]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-review-summary'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['topic' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($topic),'version' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($latestVersion),'workspace' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($headUploadWorkspace)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald4e58852d241004aa4963ab6c133e254)): ?>
<?php $attributes = $__attributesOriginald4e58852d241004aa4963ab6c133e254; ?>
<?php unset($__attributesOriginald4e58852d241004aa4963ab6c133e254); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald4e58852d241004aa4963ab6c133e254)): ?>
<?php $component = $__componentOriginald4e58852d241004aa4963ab6c133e254; ?>
<?php unset($__componentOriginald4e58852d241004aa4963ab6c133e254); ?>
<?php endif; ?>
                <?php elseif($topic->status === 'revision_requested'): ?>
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
                        <p class="font-bold">Waiting for the faculty revision</p>
                        <p class="mt-1 leading-6">This revision request is locked while the faculty member works. Review the resubmitted version before requesting another round of changes.</p>
                    </div>
                <?php elseif($topic->status !== 'lrec_queued'): ?>
                    <div class="rounded-2xl bg-gray-100 p-5 text-center text-sm font-bold text-gray-600">This proposal is already <?php echo e($statusLabel); ?>. No further decision is available.</div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


        </section>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canViewNoticeToProceed): ?>
            <section id="notice-to-proceed-tab" x-show="activeTopicTab === 'notice'" x-cloak role="tabpanel" aria-labelledby="notice-to-proceed-tab-button" class="space-y-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white"><?php echo e($topic->hasIssuedNoticeToProceed() ? 'Released documents' : 'Signing & release'); ?></h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-slate-300"><?php echo e($topic->hasIssuedNoticeToProceed() ? 'The signed proposal papers and Notice to Proceed are ready for faculty.' : ($canManageNoticeToProceed ? 'Upload the signed proposal papers, then prepare and upload the signed Notice to Proceed below to release the project.' : 'Review is complete. Research office staff or the secretary will prepare the Notice to Proceed and upload the signed documents for release.')); ?></p>
                    </div>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($isResearchHead || $canViewSigningDocuments) && $headUploadWorkspace): ?>
                    <?php if (isset($component)) { $__componentOriginalbf0c2fa2d41eba61d5c53ef20c632192 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbf0c2fa2d41eba61d5c53ef20c632192 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.research-head-file-workspace','data' => ['topic' => $topic,'workspace' => $headUploadWorkspace]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('research-head-file-workspace'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['topic' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($topic),'workspace' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($headUploadWorkspace)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbf0c2fa2d41eba61d5c53ef20c632192)): ?>
<?php $attributes = $__attributesOriginalbf0c2fa2d41eba61d5c53ef20c632192; ?>
<?php unset($__attributesOriginalbf0c2fa2d41eba61d5c53ef20c632192); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbf0c2fa2d41eba61d5c53ef20c632192)): ?>
<?php $component = $__componentOriginalbf0c2fa2d41eba61d5c53ef20c632192; ?>
<?php unset($__componentOriginalbf0c2fa2d41eba61d5c53ef20c632192); ?>
<?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php echo $__env->make('topics.partials.notice-to-proceed', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

            </section>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <section id="version-history-tab" x-show="activeTopicTab === 'history'" x-cloak role="tabpanel" aria-labelledby="version-history-tab-button" class="space-y-5">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reviewDocuments->isNotEmpty()): ?>
                <section x-data="{ open: false }" data-review-documents-disclosure data-initially-open="false" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-950">
                    <button type="button" @click="open = !open" :aria-expanded="open" aria-controls="review-documents-content" class="flex min-h-11 w-full items-center justify-between gap-4 px-5 py-3 text-left transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-red-700 dark:hover:bg-gray-900 sm:px-6">
                        <span class="text-sm font-semibold text-gray-950 dark:text-white">Research Head documents <span class="ml-2 font-normal text-gray-500 dark:text-gray-400"><?php echo e($reviewDocuments->count()); ?> documents</span></span>
                    <svg :class="open ? 'rotate-180' : ''" class="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
                </button>
                    <div id="review-documents-content" x-show="open" x-cloak x-transition class="border-t border-gray-100 dark:border-gray-800">
                        <div class="divide-y divide-gray-100">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $reviewDocuments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reviewDocument): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php
                                $reviewDocumentAvailable = $availableReviewDocumentIds->contains($reviewDocument->id);
                                $reviewDocumentViewable = $viewableReviewDocumentIds->contains($reviewDocument->id);
                                $documentDecision = $reviewDocument->source_data['decision'] ?? null;
                                $documentPurpose = $reviewDocument->source_data['purpose'] ?? null;
                            ?>
                            <article class="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="text-lg font-bold text-gray-950 dark:text-white"><?php echo e($reviewDocument->label()); ?></h4>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($documentDecision): ?>
                                            <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-black text-red-700"><?php echo e(str($documentDecision)->replace('_', ' ')->title()); ?></span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($documentPurpose): ?>
                                            <span class="rounded-full px-2.5 py-1 text-xs font-black <?php echo e($documentPurpose === \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED ? 'bg-gray-950 text-white dark:bg-white dark:text-gray-950' : ($documentPurpose === \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_REVISION ? 'bg-red-50 text-red-800 dark:bg-red-950/40 dark:text-red-200' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200')); ?>"><?php echo e($reviewDocument->headUploadPurposeLabel()); ?></span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                    <p class="mt-1 break-all text-sm font-semibold text-gray-600"><?php echo e($reviewDocument->original_filename); ?></p>
                                    <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">Shared by <?php echo e($reviewDocument->uploadedBy?->name ?? 'Research Head'); ?> on <?php echo e($reviewDocument->created_at->format('M j, Y g:i A')); ?></p>
                                </div>
                                <div class="flex w-full shrink-0 gap-2 sm:w-auto">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reviewDocumentViewable): ?>
                                        <a href="<?php echo e(route('topics.versions.files.view', [$topic, $latestVersion, $reviewDocument])); ?>" target="_blank" rel="noopener" class="inline-flex flex-1 items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-50 sm:flex-none">View</a>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reviewDocumentAvailable): ?>
                                        <a href="<?php echo e(route('topics.versions.files.download', [$topic, $latestVersion, $reviewDocument])); ?>" class="inline-flex flex-1 items-center justify-center rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-gray-800 sm:flex-none">Download</a>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            </article>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <section x-show="decisionHistoryOpen" x-cloak x-transition data-decision-history data-initially-open="false" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-950">
                <h3 class="px-5 py-3 text-sm font-semibold text-gray-950 dark:text-white sm:px-6">Decision history <span class="ml-2 font-normal text-gray-500 dark:text-gray-400"><?php echo e($decisionReviews->count()); ?> <?php echo e(str('decision')->plural($decisionReviews->count())); ?></span></h3>
                <div id="decision-history-list" class="max-h-[42rem] overflow-y-auto overscroll-contain border-t border-gray-100 dark:border-gray-800" data-decision-history-list>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($decisionReviews->isNotEmpty()): ?>
                        <ol class="divide-y divide-gray-100 dark:divide-gray-800">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $decisionReviews; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $review): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <li class="p-5 sm:p-6">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-lg font-bold text-gray-900 dark:text-gray-100"><?php echo e(str($review->decision)->replace('_', ' ')->title()); ?></p>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($loop->first): ?>
                                            <span class="rounded-full bg-red-50 px-2.5 py-1 text-sm font-bold text-red-700 dark:bg-red-950/40 dark:text-red-300">Latest</span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                    <time datetime="<?php echo e($review->created_at->toIso8601String()); ?>" class="text-sm text-gray-500 dark:text-gray-400"><?php echo e($review->created_at->format('M d, Y h:i A')); ?></time>
                                </div>
                                <p class="mt-1 text-sm font-semibold text-gray-500 dark:text-gray-400"><?php echo e($review->reviewer?->name ?? 'Former Research Head'); ?></p>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($review->comment): ?>
                                    <div class="mt-3 rounded-xl bg-gray-50 p-4 text-base leading-7 text-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($review->decision === 'rejected'): ?>
                                            <p class="text-sm font-bold text-red-700 dark:text-red-300">Rejection reason</p>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <p class="<?php echo \Illuminate\Support\Arr::toCssClasses(['whitespace-pre-line', 'mt-1' => $review->decision === 'rejected']); ?>"><?php echo e($review->comment); ?></p>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <p class="mt-2 text-sm text-gray-500"><?php echo e(match ($review->review_stage) { 'lrec' => 'LREC review', 'gad' => 'GAD / Co-evaluator review', default => 'Research Head review' }); ?></p>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($review->decision === 'revision_requested'): ?>
                                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('generateCommentResponseForm', $topic)): ?>
                                        <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-gray-300">This review’s comments and submitted Faculty responses are collected in the Comment Response paper.</p>
                                        <button type="button" @click="$dispatch('open-modal', 'history-comment-response-<?php echo e($review->id); ?>')" aria-haspopup="dialog" class="mt-2 inline-flex min-h-11 items-center justify-center rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-bold text-red-800 hover:bg-red-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200 dark:hover:bg-red-950/70">View this review’s Comment Response Paper</button>
                                        <?php if (isset($component)) { $__componentOriginal9f64f32e90b9102968f2bc548315018c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9f64f32e90b9102968f2bc548315018c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.modal','data' => ['name' => 'history-comment-response-'.e($review->id).'','maxWidth' => '6xl','focusable' => true,'class' => '!z-[140]','dataHistoryCommentResponsePreview' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'history-comment-response-'.e($review->id).'','maxWidth' => '6xl','focusable' => true,'class' => '!z-[140]','data-history-comment-response-preview' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                                            <template x-if="show">
                                                <section role="dialog" aria-modal="true" aria-labelledby="history-comment-response-heading-<?php echo e($review->id); ?>">
                                                    <header class="flex items-center justify-between gap-4 border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                                                        <h3 id="history-comment-response-heading-<?php echo e($review->id); ?>" class="text-base font-bold text-gray-950 dark:text-white">Comment Response paper · <?php echo e($review->created_at->format('M j, Y')); ?></h3>
                                                        <button type="button" @click="$dispatch('close')" class="inline-flex min-h-11 items-center rounded-xl border border-gray-300 px-4 py-2 text-sm font-bold text-gray-800 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700 dark:border-gray-700 dark:text-gray-100 dark:hover:bg-gray-800">Close preview</button>
                                                    </header>
                                                     <?php if (isset($component)) { $__componentOriginal13c1f2a64fc7e3eb9a4f045c0d853353 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal13c1f2a64fc7e3eb9a4f045c0d853353 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-revision-pdf','data' => ['configuration' => ['pdfUrl' => route(($isResearchHead ? 'research_head' : ($isResearchOffice ? 'research_coordinator' : (Auth::user()->isUsingWorkspace('research_secretary') ? 'research_secretary' : 'faculty'))).'.topics.comment-response-form.pdf', ['topic' => $topic, 'review' => $review->id]), 'annotations' => [], 'canAnnotate' => false],'loadingLabel' => 'Loading Comment Response paper…','viewerLabel' => 'Comment Response paper','class' => '!h-[75dvh]']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-revision-pdf'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['configuration' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['pdfUrl' => route(($isResearchHead ? 'research_head' : ($isResearchOffice ? 'research_coordinator' : (Auth::user()->isUsingWorkspace('research_secretary') ? 'research_secretary' : 'faculty'))).'.topics.comment-response-form.pdf', ['topic' => $topic, 'review' => $review->id]), 'annotations' => [], 'canAnnotate' => false]),'loading-label' => 'Loading Comment Response paper…','viewer-label' => 'Comment Response paper','class' => '!h-[75dvh]']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal13c1f2a64fc7e3eb9a4f045c0d853353)): ?>
<?php $attributes = $__attributesOriginal13c1f2a64fc7e3eb9a4f045c0d853353; ?>
<?php unset($__attributesOriginal13c1f2a64fc7e3eb9a4f045c0d853353); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal13c1f2a64fc7e3eb9a4f045c0d853353)): ?>
<?php $component = $__componentOriginal13c1f2a64fc7e3eb9a4f045c0d853353; ?>
<?php unset($__componentOriginal13c1f2a64fc7e3eb9a4f045c0d853353); ?>
<?php endif; ?>
                                                </section>
                                            </template>
                                         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9f64f32e90b9102968f2bc548315018c)): ?>
<?php $attributes = $__attributesOriginal9f64f32e90b9102968f2bc548315018c; ?>
<?php unset($__attributesOriginal9f64f32e90b9102968f2bc548315018c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9f64f32e90b9102968f2bc548315018c)): ?>
<?php $component = $__componentOriginal9f64f32e90b9102968f2bc548315018c; ?>
<?php unset($__componentOriginal9f64f32e90b9102968f2bc548315018c); ?>
<?php endif; ?>
                                    <?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $review->committee_comments ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $commentIndex => $committeeComment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <div class="mt-3 rounded-xl border border-gray-200 p-4 text-base leading-7 dark:border-slate-700">
                                            <p class="text-sm text-gray-500"><?php echo e($committeeComment['location'] ?? ''); ?></p>
                                            <p class="mt-2 whitespace-pre-line"><?php echo e($committeeComment['comment']); ?></p>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($answer = ($review->feedback_responses['committee_'.$commentIndex] ?? null)): ?>
                                                <p class="mt-2 font-semibold">Faculty response</p><p class="whitespace-pre-line"><?php echo e($answer['response']); ?></p><p class="text-sm text-gray-500"><?php echo e($answer['remarks'] ?? ''); ?></p>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($review->fileRevisions->isNotEmpty()): ?>
                                    <div class="mt-3 space-y-2">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $review->fileRevisions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fileRevision): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <?php
                                                $annotationVersion = $topic->versions->firstWhere('id', $fileRevision->file?->proposal_version_id);
                                            ?>
                                            <div class="rounded-xl border px-4 py-4 text-base <?php echo e($fileRevision->resolved_at ? 'border-gray-300 bg-gray-100 text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200' : 'border-red-300 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200'); ?>">
                                                <div class="flex flex-wrap items-center justify-between gap-2"><span class="font-bold"><?php echo e($fileRevision->file?->label() ?? str($fileRevision->document_type)->replace('_', ' ')->title()); ?></span><span class="text-sm font-bold"><?php echo e(! $fileRevision->resolved_at ? 'Revision required' : ($fileRevision->resolution_type === 'no_file_change' ? 'Resolved — no file change' : 'Resolved — revised file')); ?></span></div>
                                                <p class="mt-1 text-sm opacity-75"><?php echo e($fileRevision->original_filename); ?></p>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fileRevision->revision_note): ?><p class="mt-2 leading-6"><?php echo e($fileRevision->revision_note); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fileRevision->resolved_at && $fileRevision->faculty_response): ?><div class="mt-3 rounded-lg border border-blue-200 bg-blue-50 p-3 text-blue-900 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-200"><p class="text-sm font-bold">Faculty response</p><p class="mt-1 whitespace-pre-line leading-7"><?php echo e($fileRevision->faculty_response); ?></p></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fileRevision->annotations->isNotEmpty() && $annotationVersion && $fileRevision->file): ?>
                                                    <?php
                                                        $firstAnnotation = $fileRevision->annotations->sortBy([['page_number', 'asc'], ['id', 'asc']])->first();
                                                        $annotationDeepLink = route('topics.versions.files.annotations.index', [$topic, $annotationVersion, $fileRevision->file]).'?annotation='.$firstAnnotation->id.'#proposal-review';
                                                    ?>
                                                    <a href="<?php echo e($annotationDeepLink); ?>" class="mt-3 inline-flex min-h-11 items-center rounded-xl bg-red-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-red-800">View <?php echo e($fileRevision->annotations->count()); ?> highlighted comment(s)</a>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </li>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </ol>
                    <?php else: ?>
                        <p class="p-6 text-center text-sm text-gray-500 dark:text-gray-400">No Research Head decision has been recorded.</p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </section>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topic->status === 'revision_requested'): ?>
                <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100" data-working-revision-status>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-xl font-bold"><?php echo e($isFacultyRevision ? 'Revision in progress' : 'Faculty revision in progress'); ?></h3>
                                <span class="rounded-full bg-amber-200/70 px-2.5 py-1 text-sm font-bold text-amber-900 dark:bg-amber-900 dark:text-amber-100">Working draft</span>
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isFacultyRevision): ?>
                                <p class="mt-2 text-base leading-7">Your editor changes, including added images, stay in this private working revision. They are not part of Version <?php echo e($latestVersion?->version_number ?? 1); ?>.</p>
                                <p class="mt-1 text-base font-semibold leading-7">Submitting the revision creates Version <?php echo e(($latestVersion?->version_number ?? 1) + 1); ?>, sends it to the Research Head, and enables the comparison below.</p>
                            <?php else: ?>
                                <p class="mt-2 text-base leading-7">Version <?php echo e($latestVersion?->version_number ?? 1); ?> remains the latest submission while the faculty member works. The Research Head receives the changes only after the faculty submits the revision.</p>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isFacultyRevision): ?>
                            <a href="<?php echo e(route('faculty.topics.revision', $topic)); ?>" class="inline-flex shrink-0 items-center justify-center rounded-xl bg-amber-950 px-4 py-2.5 text-sm font-bold text-white hover:bg-black focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-950 dark:bg-amber-100 dark:text-amber-950">Continue revision</a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </section>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h3 class="text-lg font-black text-gray-900">Submitted version comparison</h3>
                    <p class="mt-1 text-sm text-gray-600">Compares submission details and identifies replaced files. It does not inspect document content to describe individual text or image edits.</p>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($previousVersion && $latestVersion): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-left text-sm">
                            <thead class="bg-gray-50 text-xs font-black uppercase tracking-wider text-gray-500"><tr><th class="px-5 py-3">Field</th><th class="px-5 py-3">Version <?php echo e($previousVersion->version_number); ?></th><th class="px-5 py-3">Version <?php echo e($latestVersion->version_number); ?></th></tr></thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $comparisonRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <tr class="<?php echo e($row['changed'] ? 'bg-red-50/60 dark:bg-red-950/20' : ''); ?>"><th class="px-5 py-3 font-black text-gray-700"><?php echo e($row['label']); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($row['changed']): ?><span class="ml-1 text-xs uppercase text-red-700 dark:text-red-400">Changed</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></th><td class="max-w-xs px-5 py-3 text-gray-500"><?php echo e($row['previous']); ?></td><td class="max-w-xs px-5 py-3 font-semibold text-gray-700"><?php echo e($row['latest']); ?></td></tr>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-gray-100 p-5">
                        <p class="text-sm font-black text-gray-700">Files changed in version <?php echo e($latestVersion->version_number); ?></p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $latestVersion->files->where('is_carried_forward', false)->whereNotIn('document_type', [\App\Models\ProposalVersionFile::TYPE_COMMENT_RESPONSE, \App\Models\ProposalVersionFile::TYPE_HEAD_UPLOAD]); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $file): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-bold text-red-700 dark:bg-red-950/40 dark:text-red-300"><?php echo e($file->label()); ?></span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <span class="text-xs text-gray-400">No submitted documents were replaced.</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="p-8 text-center">
                        <p class="text-base font-bold text-gray-700">Only one submitted version</p>
                        <p class="mt-1 text-sm text-gray-500">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topic->status === 'revision_requested'): ?>
                                The current revision is still a working draft. This comparison appears after the faculty submits it as Version <?php echo e(($latestVersion?->version_number ?? 1) + 1); ?>.
                            <?php else: ?>
                                A comparison appears after the first revision is submitted.
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </section>

            <?php echo $__env->make('topics.partials.version-history', ['topic' => $topic, 'expanded' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </section>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canViewMonitoring): ?>
            <section id="project-monitoring-tab" x-show="activeTopicTab === 'monitoring'" x-cloak role="tabpanel" aria-labelledby="project-monitoring-tab-button">
                <?php echo $__env->make('topics.partials.project-monitoring', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </section>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topic->signed_approval_path): ?>
            <a href="<?php echo e(route('topics.approval', $topic)); ?>" class="flex justify-center rounded-xl bg-gray-950 px-4 py-3 text-sm font-bold text-white hover:bg-black dark:bg-white dark:text-gray-950 dark:hover:bg-gray-200">Download previous signed approval</a>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if (isset($component)) { $__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.back-link','data' => ['fixed' => true,'xRef' => 'workspaceBackLink','xShow' => '!$store.researchAssistant.drawerOpen && !$store.researchAssistant.workspaceOpen','xCloak' => true,'dataTopicWorkspaceBackLink' => true,'href' => ''.e(route($workspaceBackRoute)).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('back-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['fixed' => true,'x-ref' => 'workspaceBackLink','x-show' => '!$store.researchAssistant.drawerOpen && !$store.researchAssistant.workspaceOpen','x-cloak' => true,'data-topic-workspace-back-link' => true,'href' => ''.e(route($workspaceBackRoute)).'']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
<?php echo e($workspaceBackLabel); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01)): ?>
<?php $attributes = $__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01; ?>
<?php unset($__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01)): ?>
<?php $component = $__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01; ?>
<?php unset($__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01); ?>
<?php endif; ?>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/topics/show.blade.php ENDPATH**/ ?>