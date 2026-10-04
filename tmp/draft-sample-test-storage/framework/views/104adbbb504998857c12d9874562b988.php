<section id="project-monitoring" class="space-y-5">
    <?php
        $storedProjectStatus = $topic->project_status ?: 'ongoing';
        $latestProgressPercentage = $topic->progressReports->first()?->progress_percentage;
        $projectStatus = $topic->monitoringStatusForProgress($latestProgressPercentage);
        $projectStatusLabel = $topic->monitoringStatusLabelForProgress($latestProgressPercentage);
        $schedule = app(\App\Services\MonitoringQuarterService::class);
        $window = $schedule->reportingWindow($topic);
        $terminalDate = $schedule->terminalOpensAt($topic);
        $terminalOpen = $schedule->canSubmitTerminal($topic);
        $openPeriod = $monitoringQuarterRows->first(fn ($row) => $row['reporting_date'] !== null);
        $nextPeriod = $monitoringQuarterRows->first(fn ($row) => $row['reporting_date'] === null);
        $canReport = ! Auth::user()->isUsingWorkspace('research_head') && $topic->isMonitoringAvailable() && $topic->isAccessibleTo(Auth::user());
        $canAssignProjectSecretary = Auth::id() === $topic->user_id
            && ! Auth::user()->isUsingWorkspace('research_head')
            && $topic->isMonitoringAvailable();
        $projectSecretaryCandidates = $topic->collaborators
            ->filter(fn ($collaborator) => $collaborator->accepted_at !== null && $collaborator->user !== null)
            ->pluck('user')
            ->unique('id')
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'college' => $user->college,
            ])
            ->values();
        $latestTerminalReportId = $topic->narrativeReports
            ->where('report_type', 'terminal')
            ->sortByDesc('id')
            ->first()?->id;
        $displayedNarrativeReports = $topic->narrativeReports->filter(function ($report) use ($topic, $latestTerminalReportId, $progressQuarterRows) {
            if ($topic->isCompletedProject()) {
                return false;
            }

            if ($report->report_type === 'terminal') {
                return $report->id === $latestTerminalReportId
                    && ($report->review_status !== \App\Models\ProjectNarrativeReport::STATUS_REVIEWED || ! $report->hasSignedCopy());
            }

            return $report->reporting_quarter === null || $progressQuarterRows->contains(fn ($row) => $row['report']?->id === $report->id);
        });
        $narrativeReportHistory = $topic->narrativeReports->diff($displayedNarrativeReports);
    ?>
    <header class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-slate-700 dark:bg-slate-900">
        <div data-project-monitoring-heading class="flex flex-wrap items-start justify-between gap-3 bg-red-700 p-5 text-white dark:bg-red-950 sm:p-6">
            <div>
                <h3 class="text-2xl font-bold text-white">Project monitoring</h3>
                <p class="mt-2 text-base leading-7 text-red-100">Quarterly Monitoring Tools and Progress Reports, followed by a Terminal Report when the project ends.</p>
            </div>
            <span class="rounded-full px-4 py-1.5 text-sm font-semibold <?php echo e($projectStatus === 'completion_pending' ? 'bg-violet-50 text-violet-700 dark:bg-violet-950 dark:text-violet-200' : 'bg-white text-red-800 dark:bg-red-100 dark:text-red-900'); ?>"><?php echo e($projectStatusLabel); ?></span>
        </div>
        <div data-project-monitoring-details class="border-t border-gray-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900 sm:p-6">
            <dl class="grid gap-5 sm:grid-cols-3">
                <div><dt class="text-sm font-medium text-gray-600 dark:text-slate-400">Monitoring starts</dt><dd class="mt-1 text-xl font-bold text-gray-950 dark:text-white"><?php echo e($window['start']->format('M j, Y')); ?></dd></div>
                <div><dt class="text-sm font-medium text-gray-600 dark:text-slate-400">Project ends</dt><dd class="mt-1 text-xl font-bold text-gray-950 dark:text-white"><?php echo e($window['end']->format('M j, Y')); ?></dd></div>
                <div><dt class="text-sm font-medium text-gray-600 dark:text-slate-400">Next period opens</dt><dd class="mt-1 text-xl font-bold text-gray-950 dark:text-white"><?php echo e($nextPeriod ? $nextPeriod['opens_at']->format('M j, Y') : 'All periods have ended'); ?></dd></div>
            </dl>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topic->hasIssuedNoticeToProceed()): ?>
                <a href="<?php echo e(route('topics.show', $topic)); ?>#notice-to-proceed" class="mt-5 inline-flex min-h-11 items-center rounded-lg border border-red-200 px-4 py-2 text-base font-semibold text-brand hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-950 dark:focus-visible:ring-offset-slate-900">Notice to Proceed &amp; signed papers</a>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topic->isCompletedProject()): ?>
                <p class="mt-4 text-base leading-7 text-gray-600 dark:text-slate-300">Project completed. This status is final and cannot be changed. Monitoring reports are read-only. Authorized Faculty Researchers can continue journal search and publication tracking.</p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </header>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Auth::user()->isUsingWorkspace('research_head') && ! $topic->isCompletedProject()): ?>
        <?php
            $floatingStatusClasses = match ($projectStatus) {
                'delayed' => 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-200',
                'completion_pending' => 'bg-violet-50 text-violet-700 dark:bg-violet-950/50 dark:text-violet-200',
                default => 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-200',
            };
        ?>
        <div
            x-data="projectStatusManager(<?php echo \Illuminate\Support\Js::from(old('project_status', $storedProjectStatus))->toHtml() ?>, <?php echo \Illuminate\Support\Js::from($errors->has('project_status') || $errors->has('completion_confirmed'))->toHtml() ?>)"
            x-cloak
            x-show="!$store.researchAssistant.drawerOpen && !$store.researchAssistant.workspaceOpen"
            x-on:keydown.escape.window="if (statusManagerOpen) { statusManagerOpen = false; $refs.statusTrigger.focus() }"
            class="fixed bottom-[calc(1rem+env(safe-area-inset-bottom))] right-[calc(5.25rem+env(safe-area-inset-right))] z-[60] w-fit max-w-[calc(100vw-6.25rem)] sm:bottom-[calc(1.5rem+env(safe-area-inset-bottom))] sm:right-[calc(5.75rem+env(safe-area-inset-right))] print:hidden"
            data-project-status-manager
        >
            <section
                id="project-status-manager-<?php echo e($topic->id); ?>"
                x-cloak
                x-show="statusManagerOpen"
                x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none"
                x-transition:enter-start="translate-y-3 opacity-0"
                x-transition:enter-end="translate-y-0 opacity-100"
                x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none"
                x-transition:leave-start="translate-y-0 opacity-100"
                x-transition:leave-end="translate-y-3 opacity-0"
                x-on:click.outside="statusManagerOpen = false"
                class="absolute bottom-full right-0 mb-3 w-full max-h-[calc(100dvh-6.25rem-env(safe-area-inset-top)-env(safe-area-inset-bottom))] origin-bottom-right overflow-y-auto overscroll-contain rounded-2xl border border-gray-200 bg-white p-3 shadow-2xl shadow-gray-900/15 dark:border-slate-700 dark:bg-slate-900 sm:p-4"
                role="dialog"
                aria-labelledby="project-status-manager-heading-<?php echo e($topic->id); ?>"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h4 id="project-status-manager-heading-<?php echo e($topic->id); ?>" class="text-lg font-semibold text-gray-950 dark:text-white">Manage project status</h4>
                        <p class="mt-1 text-base leading-6 text-gray-500 dark:text-slate-400">Completion requires 100% progress, a reviewed Terminal Report, and its fully signed PDF.</p>
                    </div>
                    <button type="button" x-on:click="statusManagerOpen = false; $refs.statusTrigger.focus()" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Close status manager">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['project_status'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-3 rounded-xl bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 dark:bg-red-950/50 dark:text-red-200"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <form method="POST" action="<?php echo e(route('research_head.projects.update-status', $topic)); ?>" @submit.prevent="submitStatus($event)" class="mt-4 space-y-3" data-project-status-form>
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('PATCH'); ?>
                    <input type="hidden" name="completion_confirmed" value="0">
                    <label for="project-status-<?php echo e($topic->id); ?>" class="block text-base font-semibold text-gray-700 dark:text-slate-200">Status</label>
                    <div class="flex flex-col gap-2">
                        <select id="project-status-<?php echo e($topic->id); ?>" name="project_status" x-model="selectedStatus" class="h-11 w-full min-w-0 rounded-xl border-gray-300 bg-white text-base font-semibold text-gray-900 focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-800 dark:text-white">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['ongoing', 'delayed', 'completed']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <option value="<?php echo e($value); ?>" <?php if($storedProjectStatus === $value): echo 'selected'; endif; ?>><?php echo e(ucfirst($value)); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                        <button :disabled="submitting" class="inline-flex h-11 shrink-0 items-center justify-center rounded-xl bg-red-700 px-4 text-base font-semibold text-white transition hover:bg-red-800 disabled:opacity-50">Save status</button>
                    </div>
                    <p x-show="selectedStatus === 'completed'" x-cloak class="rounded-lg border border-red-200 bg-red-50 p-3 text-base leading-6 text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200">Completion is final. The status cannot be changed back, and monitoring reports become read-only.</p>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['completion_confirmed'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p role="alert" class="text-sm text-red-700 dark:text-red-200"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </form>
            </section>

            <div class="flex min-h-14 items-center justify-between gap-2 rounded-full border border-gray-200 bg-white p-1 shadow-lg shadow-gray-900/15 dark:border-slate-700 dark:bg-slate-900">
                <span class="hidden min-w-0 items-center gap-2 text-sm font-black <?php echo e($floatingStatusClasses); ?> rounded-full px-3 py-2 sm:inline-flex">
                    <span class="h-2 w-2 shrink-0 rounded-full bg-current"></span>
                    <span class="truncate"><?php echo e($projectStatusLabel); ?></span>
                </span>
                <button
                    type="button"
                    x-ref="statusTrigger"
                    x-on:click="statusManagerOpen = ! statusManagerOpen"
                    x-bind:aria-expanded="statusManagerOpen"
                    aria-controls="project-status-manager-<?php echo e($topic->id); ?>"
                    class="inline-flex min-h-12 shrink-0 min-w-0 max-w-full items-center gap-2 rounded-full bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-gray-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 dark:bg-white dark:text-slate-950 dark:focus-visible:ring-offset-slate-900"
                >
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M4 7h16M4 17h16" stroke-linecap="round"/><circle cx="9" cy="7" r="3" fill="currentColor"/><circle cx="15" cy="17" r="3" fill="currentColor"/></svg>
                    <span class="min-w-0 break-words">Manage status</span>
                </button>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <section class="rounded-xl border border-red-200 border-l-4 border-l-red-700 bg-white p-5 dark:border-red-900 dark:border-l-red-500 dark:bg-slate-900 sm:p-6" aria-labelledby="project-secretary-heading">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
            <div class="max-w-xl">
                <h4 id="project-secretary-heading" class="text-xl font-bold text-red-800 dark:text-red-300">Project Secretary</h4>
                <p class="mt-2 text-base leading-7 text-gray-600 dark:text-slate-300">Receives budget reminders. The leader or any accepted team member can also complete the budget.</p>
            </div>

            <div class="w-full lg:max-w-md">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topic->researchSecretary): ?>
                    <div class="flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950/30">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white text-xs font-black text-red-800 ring-1 ring-red-200 dark:bg-slate-900">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topic->researchSecretary->avatar): ?>
                                <img src="<?php echo e($topic->researchSecretary->avatar); ?>" alt="" class="h-full w-full object-cover">
                            <?php else: ?>
                                <?php echo e(collect(explode(' ', $topic->researchSecretary->name))->filter()->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('')); ?>

                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </span>
                        <span class="min-w-0"><span class="block truncate text-base font-bold text-gray-950 dark:text-white"><?php echo e($topic->researchSecretary->name); ?></span><span class="block break-all text-sm text-gray-600 dark:text-slate-300"><?php echo e($topic->researchSecretary->email); ?></span></span>
                    </div>
                <?php else: ?>
                    <div class="rounded-xl border border-dashed border-amber-300 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">No project secretary has been selected yet.</div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canAssignProjectSecretary): ?>
                    <div x-data="researchSecretaryPicker({ candidates: <?php echo \Illuminate\Support\Js::from($projectSecretaryCandidates)->toHtml() ?>, selectedId: <?php echo \Illuminate\Support\Js::from($topic->research_secretary_id)->toHtml() ?> })" class="relative mt-3" data-project-secretary-picker>
                        <form x-ref="form" method="POST" action="<?php echo e(route('project-secretary.assign', $topic)); ?>">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('PATCH'); ?>
                            <input type="hidden" name="research_secretary_id" :value="selectedId || ''">
                        </form>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" @click="open = !open; if (open) $nextTick(() => $refs.search.focus())" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-red-700 px-4 py-2 text-sm font-bold text-white hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900"><?php echo e($topic->researchSecretary ? 'Change secretary' : 'Select team member'); ?></button>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topic->researchSecretary): ?>
                                <button type="button" @click="clearSelection" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-gray-300 px-3 py-2 text-sm font-bold text-gray-700 hover:bg-gray-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Remove</button>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['research_secretary_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-2 text-xs font-semibold text-red-700 dark:text-red-300"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <div x-show="open" x-transition.origin.top x-cloak @click.outside="open = false" class="absolute right-0 z-30 mt-2 w-full min-w-72 rounded-2xl border border-gray-200 bg-white p-3 shadow-2xl shadow-gray-900/15 dark:border-slate-700 dark:bg-slate-900">
                            <label class="sr-only" for="project-secretary-search-<?php echo e($topic->id); ?>">Search accepted team members</label>
                            <input x-ref="search" id="project-secretary-search-<?php echo e($topic->id); ?>" x-model="query" type="search" autocomplete="off" placeholder="Search accepted team members" class="block w-full rounded-xl border-gray-200 text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white">
                            <div class="mt-2 max-h-64 space-y-1 overflow-y-auto" role="listbox">
                                <template x-for="candidate in filteredCandidates()" :key="candidate.id">
                                    <button type="button" role="option" @click="select(candidate.id)" class="flex w-full items-center gap-3 rounded-xl px-2.5 py-2 text-left hover:bg-red-50 focus:bg-red-50 focus:outline-none dark:hover:bg-slate-800 dark:focus:bg-slate-800">
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-red-100 text-xs font-black text-red-700 ring-1 ring-red-200"><img x-show="candidate.avatar" :src="candidate.avatar" alt="" x-on:error="candidate.avatar = ''" class="h-full w-full object-cover"><span x-show="!candidate.avatar" x-text="initials(candidate.name)"></span></span>
                                        <span class="min-w-0 flex-1"><span class="block truncate text-sm font-bold text-gray-900 dark:text-white" x-text="candidate.name"></span><span class="block truncate text-xs text-gray-500 dark:text-slate-400" x-text="candidate.email"></span><span x-show="candidate.college" class="mt-0.5 block truncate text-[10px] font-bold uppercase tracking-wide text-gray-400" x-text="candidate.college"></span></span>
                                    </button>
                                </template>
                                <p x-show="filteredCandidates().length === 0" class="px-3 py-5 text-center text-xs font-semibold text-gray-500">No accepted team member matches this search.</p>
                            </div>
                        </div>
                    </div>
                <?php elseif(! Auth::user()->isUsingWorkspace('research_head') && Auth::id() !== $topic->research_secretary_id): ?>
                    <p class="mt-2 text-sm text-gray-600 dark:text-slate-300">Only the project leader can change this assignment.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canReport && $topic->preparedProgressReports->isNotEmpty()): ?>
            <?php
                $isPriorityProjectSecretary = Auth::id() === $topic->research_secretary_id;
            ?>
            <div class="mt-5 border-t border-gray-100 pt-4 dark:border-slate-800">
                <p class="text-xs font-black uppercase tracking-wide text-gray-500 dark:text-slate-400"><?php echo e($isPriorityProjectSecretary ? 'Priority budget queue' : 'Budget utilization available to the project team'); ?></p>
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $topic->preparedProgressReports; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $preparedReport): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <a href="<?php echo e(route('project-budget.edit', [$topic, $preparedReport])); ?>" class="flex items-center justify-between gap-3 rounded-xl border px-4 py-3 text-sm font-bold <?php echo e($isPriorityProjectSecretary ? 'border-amber-200 bg-amber-50 text-amber-950 hover:border-amber-300 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100' : 'border-gray-200 bg-gray-50 text-gray-900 hover:border-red-200 hover:bg-red-50 dark:border-slate-700 dark:bg-slate-950 dark:text-white'); ?>">
                            <span><?php echo e($preparedReport->quarter_label); ?> · <?php echo e($preparedReport->version_label); ?></span>
                            <span class="text-xs"><?php echo e($preparedReport->hasPreparedBudget() ? 'Review budget' : 'Complete budget'); ?></span>
                        </a>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>

    <?php if (isset($component)) { $__componentOriginal047e0ccf9f4fdb5609feda51e641661a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal047e0ccf9f4fdb5609feda51e641661a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.monitoring-quarter-overview','data' => ['quarterRows' => $monitoringQuarterRows,'topic' => $topic]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('monitoring-quarter-overview'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['quarter-rows' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($monitoringQuarterRows),'topic' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($topic)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal047e0ccf9f4fdb5609feda51e641661a)): ?>
<?php $attributes = $__attributesOriginal047e0ccf9f4fdb5609feda51e641661a; ?>
<?php unset($__attributesOriginal047e0ccf9f4fdb5609feda51e641661a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal047e0ccf9f4fdb5609feda51e641661a)): ?>
<?php $component = $__componentOriginal047e0ccf9f4fdb5609feda51e641661a; ?>
<?php unset($__componentOriginal047e0ccf9f4fdb5609feda51e641661a); ?>
<?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $topic->isCompletedProject()): ?>
        <?php if (isset($component)) { $__componentOriginal4a58cc92d7e8b55dfe454b3573297232 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4a58cc92d7e8b55dfe454b3573297232 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.progress-quarter-overview','data' => ['quarterRows' => $progressQuarterRows,'topic' => $topic,'canReport' => $canReport,'legacyReports' => $displayedNarrativeReports->where('report_type', 'progress')->whereNull('reporting_quarter')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('progress-quarter-overview'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['quarter-rows' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($progressQuarterRows),'topic' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($topic),'can-report' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($canReport),'legacy-reports' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($displayedNarrativeReports->where('report_type', 'progress')->whereNull('reporting_quarter'))]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4a58cc92d7e8b55dfe454b3573297232)): ?>
<?php $attributes = $__attributesOriginal4a58cc92d7e8b55dfe454b3573297232; ?>
<?php unset($__attributesOriginal4a58cc92d7e8b55dfe454b3573297232); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4a58cc92d7e8b55dfe454b3573297232)): ?>
<?php $component = $__componentOriginal4a58cc92d7e8b55dfe454b3573297232; ?>
<?php unset($__componentOriginal4a58cc92d7e8b55dfe454b3573297232); ?>
<?php endif; ?>
        <div class="space-y-8">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['terminal' => 'Terminal reports']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reportType => $reportLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php
                    $available = $reportType === 'terminal' || $schedule->narrativeProgressPeriods($topic)->contains(fn ($period) => $period['drafting_date'] !== null);
                    $submissionOpen = $reportType === 'terminal' ? $terminalOpen : $schedule->narrativeProgressPeriods($topic)->contains(fn ($period) => $period['reporting_date'] !== null);
                    $sectionReports = $displayedNarrativeReports->where('report_type', $reportType);
                ?>
                <section id="<?php echo e($reportType); ?>-reports" aria-labelledby="<?php echo e($reportType); ?>-reports-heading" class="space-y-4">
                    <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 id="<?php echo e($reportType); ?>-reports-heading" class="text-xl font-bold text-gray-950 dark:text-white"><?php echo e($reportLabel); ?></h3>
                            <p class="mt-1 text-base leading-7 text-gray-600 dark:text-slate-300"><?php echo e($reportType === 'terminal' ? 'Final reports awaiting review or a signed PDF.' : 'One Progress Report for each Monitoring Tool quarter. Open a submission to read its content, figures, and review.'); ?></p>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canReport && ! $submissionOpen && ($reportType === 'terminal' || $nextPeriod !== null)): ?>
                                <p class="mt-2 text-sm font-medium text-gray-600 dark:text-slate-300">Fill and save a private draft now. Submission opens <?php echo e(($reportType === 'terminal' ? $terminalDate : ($nextPeriod['opens_at'] ?? $terminalDate))->format('M j, Y')); ?>.</p>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canReport && $available): ?>
                            <a href="<?php echo e(route('project-narrative-reports.create', ['topic' => $topic, 'report_type' => $reportType])); ?>" aria-label="Open <?php echo e($reportType); ?> report" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-brand hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 dark:border-slate-600 dark:bg-slate-900 dark:text-red-300 dark:focus-visible:ring-offset-slate-900">Open form</a>
                        <?php elseif($canReport): ?>
                            <button type="button" disabled class="min-h-11 shrink-0 rounded-lg bg-gray-100 px-4 py-2.5 text-sm text-gray-500 dark:bg-slate-800 dark:text-slate-400"><?php echo e($reportType === 'progress' && $openPeriod !== null ? 'Ended quarters submitted' : 'Not open yet'); ?></button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </header>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $sectionReports; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $report): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php if (isset($component)) { $__componentOriginalde63ddbd7b1a4c9a5204bf6b1c30a15a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalde63ddbd7b1a4c9a5204bf6b1c30a15a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.narrative-report-summary','data' => ['report' => $report]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('narrative-report-summary'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['report' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalde63ddbd7b1a4c9a5204bf6b1c30a15a)): ?>
<?php $attributes = $__attributesOriginalde63ddbd7b1a4c9a5204bf6b1c30a15a; ?>
<?php unset($__attributesOriginalde63ddbd7b1a4c9a5204bf6b1c30a15a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalde63ddbd7b1a4c9a5204bf6b1c30a15a)): ?>
<?php $component = $__componentOriginalde63ddbd7b1a4c9a5204bf6b1c30a15a; ?>
<?php unset($__componentOriginalde63ddbd7b1a4c9a5204bf6b1c30a15a); ?>
<?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <p class="rounded-xl border border-dashed border-red-200 bg-white px-5 py-6 text-base text-gray-600 dark:border-red-900 dark:bg-slate-900 dark:text-slate-300"><?php echo e($reportType === 'terminal' ? 'No terminal reports awaiting review or a signed PDF.' : 'No progress reports submitted yet.'); ?></p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </section>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="space-y-5">

        <div class="hidden" aria-hidden="true">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $topic->progressReports; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $report): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php
                    $isCurrentVersion = $report->nextVersion === null;
                    $reviewStatusClass = match ($report->review_status) {
                        'reviewed' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-200',
                        'revision_requested' => 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-200',
                        default => 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-200',
                    };
                    $canManageCurrentReport = Auth::user()->isUsingWorkspace('research_head') && ! $topic->isCompletedProject() && $isCurrentVersion;
                ?>
                <article
                    id="monitoring-tool-<?php echo e($report->id); ?>"
                    x-data="{ open: window.location.hash === '#monitoring-tool-<?php echo e($report->id); ?>' || <?php echo \Illuminate\Support\Js::from($errors->has('research_head_remarks'))->toHtml() ?>, remarksExpanded: false, remarksText: <?php echo \Illuminate\Support\Js::from(old('research_head_remarks', $report->research_head_remarks))->toHtml() ?> }"
                    x-init="if (open) { $dispatch('monitoring-tool-toggled', { reportId: <?php echo e($report->id); ?>, open: true }) }"
                    x-on:open-monitoring-tool.window="if ($event.detail.reportId === <?php echo e($report->id); ?>) { open = ! open; $dispatch('monitoring-tool-toggled', { reportId: <?php echo e($report->id); ?>, open }); if (open) { $nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'start' })) } }"
                    x-cloak
                    x-show="open"
                    x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none"
                    x-transition:enter-start="-translate-y-3 opacity-0"
                    x-transition:enter-end="translate-y-0 opacity-100"
                    x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none"
                    x-transition:leave-start="translate-y-0 opacity-100"
                    x-transition:leave-end="-translate-y-3 opacity-0"
                    class="scroll-mt-32 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900"
                >
                    <div class="p-5 sm:p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-base font-black text-gray-950 dark:text-white"><?php echo e($report->quarter_label); ?> Monitoring Tool · <?php echo e($report->version_label); ?></p>
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase <?php echo e($isCurrentVersion ? 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-200' : 'bg-gray-100 text-gray-500 dark:bg-slate-800 dark:text-slate-300'); ?>"><?php echo e($isCurrentVersion ? 'Current submission' : 'Historical version'); ?></span>
                            </div>
                            <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-slate-400"><?php echo e($report->reporting_period_label); ?> · Submitted <?php echo e($report->submitted_at?->format('M d, Y g:i A') ?? $report->created_at->format('M d, Y g:i A')); ?> by <?php echo e($report->submitter->name); ?></p>
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            <span class="rounded-full bg-gray-100 px-2.5 py-1.5 text-[10px] font-black uppercase text-gray-700 dark:bg-slate-800 dark:text-slate-200"><?php echo e($report->progress_percentage); ?>% complete</span>
                            <span class="rounded-full px-2.5 py-1.5 text-[10px] font-black uppercase <?php echo e($reviewStatusClass); ?>"><?php echo e($report->review_status_label); ?></span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(is_array($report->work_plan) && is_array($report->budget_utilization)): ?>
                                <a href="<?php echo e(route('project-progress.monitoring-tool', $report)); ?>" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-red-900 dark:hover:bg-red-950/30 dark:hover:text-red-300 dark:focus-visible:ring-offset-slate-900" aria-label="Download monitoring tool" title="Download monitoring tool">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M4.5 15.75v2.625A1.125 1.125 0 0 0 5.625 19.5h12.75a1.125 1.125 0 0 0 1.125-1.125V15.75" /></svg>
                                </a>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                    </div>
                    <div
                        id="monitoring-tool-details-<?php echo e($report->id); ?>"
                        x-cloak
                        x-show="open"
                        x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none"
                        x-transition:enter-start="-translate-y-2 opacity-0"
                        x-transition:enter-end="translate-y-0 opacity-100"
                        x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none"
                        x-transition:leave-start="translate-y-0 opacity-100"
                        x-transition:leave-end="-translate-y-2 opacity-0"
                        class="border-t border-gray-100 p-5 dark:border-slate-800 sm:p-6"
                    >
                    <div class="overflow-hidden rounded-full bg-red-100 dark:bg-red-950/50" aria-label="<?php echo e($report->progress_percentage); ?> percent complete"><div class="h-2.5 rounded-full bg-gradient-to-r from-red-300 via-red-500 to-red-700 dark:from-red-800 dark:via-red-600 dark:to-red-400" style="width: <?php echo e($report->progress_percentage); ?>%"></div></div>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <section class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-slate-700 dark:bg-slate-800/60">
                            <p class="text-[10px] font-black uppercase tracking-wider text-gray-700 dark:text-slate-200">Accomplishments</p>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-slate-300"><?php echo e($report->accomplishments); ?></p>
                        </section>
                        <section class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-slate-700 dark:bg-slate-800/60">
                            <p class="text-[10px] font-black uppercase tracking-wider text-gray-700 dark:text-slate-200">Issues or delays</p>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-slate-300"><?php echo e($report->issues ?: 'None reported.'); ?></p>
                        </section>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report->attachment_path || (! Auth::user()->isUsingWorkspace('research_head') && $isCurrentVersion && $report->review_status === 'revision_requested')): ?>
                    <div class="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-4 dark:border-slate-800">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report->attachment_path): ?><a href="<?php echo e(route('project-progress.download', $report)); ?>" class="inline-flex min-h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-bold text-gray-700 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-red-900 dark:hover:bg-red-950/30 dark:hover:text-red-300 dark:focus-visible:ring-offset-slate-900">Download attachment</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! Auth::user()->isUsingWorkspace('research_head') && $isCurrentVersion && $report->review_status === 'revision_requested'): ?>
                            <a href="<?php echo e(route('project-progress.create', ['topic' => $topic, 'revise_monitoring_report' => $report->id])); ?>" class="inline-flex min-h-9 items-center justify-center rounded-lg bg-red-700 px-3 py-2 text-xs font-bold text-white transition hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900">Correct <?php echo e($report->quarter_label); ?> submission</a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report->research_head_remarks && ! $canManageCurrentReport): ?><div class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-slate-700 dark:bg-slate-800/60"><p class="text-[10px] font-black uppercase tracking-wider text-gray-700 dark:text-slate-200">Research Head remarks <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report->reviewer): ?> · <?php echo e($report->reviewer->name); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p><p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-slate-300"><?php echo e($report->research_head_remarks); ?></p></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canManageCurrentReport): ?>
                        <form method="POST" action="<?php echo e(route('research_head.progress-reports.review', $report)); ?>" class="mt-4">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('PATCH'); ?>
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                                <label for="research-head-remarks-<?php echo e($report->id); ?>" class="block min-w-0 flex-1">
                                    <span class="text-[10px] font-black uppercase tracking-wider text-gray-700 dark:text-slate-200">Research Head remarks</span>
                                    <textarea id="research-head-remarks-<?php echo e($report->id); ?>" name="research_head_remarks" x-model="remarksText" x-bind:rows="remarksExpanded ? Math.max(3, Math.ceil(remarksText.length / 75)) : 1" maxlength="5000" class="mt-2 block min-h-11 w-full resize-none rounded-xl border-gray-200 bg-white py-2.5 text-sm leading-6 text-gray-700 placeholder:text-gray-400 focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200" placeholder="Add review notes or correction instructions"><?php echo e(old('research_head_remarks', $report->research_head_remarks)); ?></textarea>
                                    <button type="button" x-show="remarksText.length > 120" x-on:click="remarksExpanded = ! remarksExpanded" class="mt-1 text-xs font-bold text-red-700 hover:text-red-800 dark:text-red-300 dark:hover:text-red-200" x-text="remarksExpanded ? 'Show less' : 'See more…'"></button>
                                </label>
                                <div class="flex shrink-0 gap-2 sm:w-auto">
                                    <label class="sr-only" for="review-status-<?php echo e($report->id); ?>">Review status</label>
                                    <button class="inline-flex h-11 flex-1 items-center justify-center rounded-xl bg-red-700 px-4 text-xs font-black text-white transition hover:bg-red-800 sm:flex-none">Save review</button>
                                    <select id="review-status-<?php echo e($report->id); ?>" name="review_status" class="h-11 min-w-0 flex-1 rounded-xl border-gray-200 bg-white text-xs font-black text-gray-700 focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 sm:w-44 sm:flex-none"><option value="reviewed" <?php if($report->review_status === 'reviewed'): echo 'selected'; endif; ?>>Mark reviewed</option><option value="revision_requested" <?php if($report->review_status === 'revision_requested'): echo 'selected'; endif; ?>>Request report corrections</option></select>
                                </div>
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['research_head_remarks'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-2 text-xs font-semibold text-red-700 dark:text-red-300"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </form>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </article>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <div class="rounded-xl bg-gray-50 py-8 text-center"><p class="text-sm font-bold text-gray-700">No monitoring tools yet</p><p class="mt-1 text-xs text-gray-400">The first faculty submission will appear here.</p></div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($narrativeReportHistory->isNotEmpty()): ?>
            <section id="narrative-report-history" aria-labelledby="narrative-report-history-heading" class="space-y-4 border-t border-gray-200 pt-6 dark:border-slate-700">
                <h3 id="narrative-report-history-heading" class="text-xl font-bold text-gray-950 dark:text-white">Report history</h3>
                <p class="text-base text-gray-600 dark:text-slate-300">Previous reports and submissions from completed projects.</p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $narrativeReportHistory->sortBy('submission_date'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $report): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php if (isset($component)) { $__componentOriginalde63ddbd7b1a4c9a5204bf6b1c30a15a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalde63ddbd7b1a4c9a5204bf6b1c30a15a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.narrative-report-summary','data' => ['report' => $report]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('narrative-report-summary'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['report' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalde63ddbd7b1a4c9a5204bf6b1c30a15a)): ?>
<?php $attributes = $__attributesOriginalde63ddbd7b1a4c9a5204bf6b1c30a15a; ?>
<?php unset($__attributesOriginalde63ddbd7b1a4c9a5204bf6b1c30a15a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalde63ddbd7b1a4c9a5204bf6b1c30a15a)): ?>
<?php $component = $__componentOriginalde63ddbd7b1a4c9a5204bf6b1c30a15a; ?>
<?php unset($__componentOriginalde63ddbd7b1a4c9a5204bf6b1c30a15a); ?>
<?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </section>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</section>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/topics/partials/project-monitoring.blade.php ENDPATH**/ ?>