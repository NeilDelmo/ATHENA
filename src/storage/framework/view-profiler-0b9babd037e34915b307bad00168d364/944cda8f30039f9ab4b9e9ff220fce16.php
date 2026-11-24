<div class="space-y-5 text-slate-900 dark:text-slate-100" data-research-head-overview>
    <?php
        $filterParams = array_filter(['academicYear' => $academicYear, 'fromDate' => $fromDate, 'toDate' => $toDate]);
        $analyticsUrl = route('research_head.analytics', $filterParams);
        $selectedStage = $pipeline === 'awaiting_review' ? 'Awaiting your review' : ($analytics['pipeline']->firstWhere('key', $pipeline)['label'] ?? 'All review stages');
    ?>

    <section class="grid grid-cols-2 gap-3 md:grid-cols-4" aria-label="Priority summary" data-dashboard-priority-kpis>
        <a href="#received-proposals" wire:click="showReviewQueue" class="flex flex-col items-start justify-between gap-4 rounded-xl bg-brand px-5 py-5 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
            <span><span class="block text-sm font-semibold">Proposals awaiting review</span><span class="mt-1 block text-sm text-rose-100">Oldest submissions first</span></span>
            <strong class="text-4xl font-bold tracking-tight tabular-nums"><?php echo e($analytics['kpis']['review']); ?></strong>
        </a>
        <a href="#dashboard-report-reviews" class="flex flex-col items-start justify-between gap-4 rounded-xl border border-red-200 bg-white px-5 py-5 hover:border-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:border-red-900 dark:bg-slate-900">
            <span><span class="block text-sm font-semibold">Reports awaiting review</span><span class="mt-1 block text-sm rh-muted">Monitoring, progress, terminal</span></span>
            <strong class="text-4xl font-bold tracking-tight tabular-nums text-brand dark:text-rose-300"><?php echo e($reportItems->total()); ?></strong>
        </a>
        <a wire:navigate href="<?php echo e(route('research_head.analytics', [...$filterParams, 'projectStatus' => 'delayed'])); ?>#active-projects" class="flex flex-col items-start justify-between gap-4 rounded-xl border border-slate-200 bg-white px-5 py-5 hover:border-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:border-slate-800 dark:bg-slate-900">
            <span><span class="block text-sm font-semibold">Delayed / overdue</span><span class="mt-1 block text-sm rh-muted">Schedule or milestone passed</span></span>
            <strong class="text-4xl font-bold tracking-tight tabular-nums <?php echo e($analytics['kpis']['delayed'] ? 'text-brand dark:text-rose-300' : ''); ?>"><?php echo e($analytics['kpis']['delayed']); ?></strong>
        </a>
        <a wire:navigate href="<?php echo e(route('research_head.analytics', [...$filterParams, 'projectStatus' => 'active'])); ?>#active-projects" class="flex flex-col items-start justify-between gap-4 rounded-xl border border-slate-200 bg-white px-5 py-5 hover:border-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:border-slate-800 dark:bg-slate-900">
            <span><span class="block text-sm font-semibold">Active projects</span><span class="mt-1 block text-sm rh-muted">Notice to Proceed issued</span></span>
            <strong class="text-4xl font-bold tracking-tight tabular-nums"><?php echo e($analytics['kpis']['active']); ?></strong>
        </a>
    </section>

    <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('research-target-board', []);

$__keyOuter = $__key ?? null;

$__key = null;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-474529443-0', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>

    <section aria-label="Overview filters" class="border-b border-slate-200 pb-5 dark:border-slate-800">
        <form wire:submit="applyFilters" class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex min-w-0 flex-wrap items-center gap-3">
                <label for="overview-year" class="text-sm font-semibold">Academic year</label>
                <select id="overview-year" wire:model.live="academicYear" class="rh-control w-full sm:w-52">
                    <option value="">All academic years</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $academicYears; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $year): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($year); ?>"><?php echo e($year); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </select>
            </div>
            <details class="group w-full sm:w-auto" data-dashboard-date-filters <?php if($fromDate || $toDate): ?> open <?php endif; ?>>
                <summary class="rh-button-secondary flex cursor-pointer list-none items-center gap-2 [&::-webkit-details-marker]:hidden">
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M6.75 3v2.25M17.25 3v2.25M3.75 18.75V7.5A2.25 2.25 0 016 5.25h12a2.25 2.25 0 012.25 2.25v11.25M3.75 18.75A2.25 2.25 0 006 21h12a2.25 2.25 0 002.25-2.25M3.75 18.75v-7.5h16.5v7.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    <span><?php echo e($fromDate || $toDate ? 'Date range applied' : 'Filter by submission date'); ?></span>
                    <svg class="ml-auto h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
                        <path d="M5 12h14" />
                        <path d="M12 5v14" class="group-open:hidden" />
                    </svg>
                </summary>
                <div class="mt-2 flex flex-wrap items-end gap-3">
                    <div class="min-w-0 flex-1 basis-36 sm:basis-auto sm:flex-none"><label for="overview-from" class="block text-sm font-semibold rh-muted">First submitted from</label><input id="overview-from" type="date" wire:model="fromDate" class="rh-control mt-1.5 w-full"></div>
                    <div class="min-w-0 flex-1 basis-36 sm:basis-auto sm:flex-none"><label for="overview-to" class="block text-sm font-semibold rh-muted">Through</label><input id="overview-to" type="date" wire:model="toDate" class="rh-control mt-1.5 w-full"></div>
                    <button class="rh-button" wire:loading.attr="disabled">Apply</button>
                    <button type="button" wire:click="resetAnalyticsFilters" class="rh-button-secondary">Reset</button>
                </div>
            </details>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($academicYear && ! $fromDate && ! $toDate): ?>
                <button type="button" wire:click="resetAnalyticsFilters" class="rh-button-secondary">Reset filters</button>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </form>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($academicYear || $fromDate || $toDate): ?><p class="mt-3 text-xs rh-muted" data-dashboard-scope>Counts follow first-submission filters. Calendar deadlines remain institution-wide.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['academicYear', 'fromDate', 'toDate', 'submissionMonth']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = [$field];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p role="alert" class="mt-2 text-sm text-red-700 dark:text-red-300"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </section>

    <div class="grid items-start gap-5 xl:grid-cols-2" data-dashboard-compact-queues>
        <div class="min-w-0" data-dashboard-review-workspace>
<section id="received-proposals" class="rh-panel !rounded-xl !shadow-none scroll-mt-40" aria-labelledby="inbox-heading">
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 pb-4 pt-5">
                    <div><h2 id="inbox-heading" class="text-xl font-bold tracking-tight">Proposal reviews</h2><p class="mt-1 text-sm rh-muted"><?php echo e($topics->total()); ?> <?php echo e(str('proposal')->plural($topics->total())); ?> · <?php echo e($selectedStage); ?></p></div>
                    <a wire:navigate href="<?php echo e(route('research_head.proposal-submissions.index')); ?>" class="rh-button whitespace-nowrap">View all proposals</a>
                </div>
                <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 pb-4 dark:border-slate-800">
                    <label for="overview-search" class="sr-only">Search proposals</label><input id="overview-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Search proposals or faculty" class="rh-control min-w-0 flex-1 !rounded-lg !bg-slate-50 dark:!bg-slate-950">
                    <label for="overview-stage" class="sr-only">Review stage</label>
                    <select id="overview-stage" wire:model.live="pipeline" class="rh-control w-full !rounded-lg sm:w-48">
                        <option value="" <?php if($pipeline === ''): echo 'selected'; endif; ?>>All review stages</option>
                        <option value="awaiting_review" <?php if($pipeline === 'awaiting_review'): echo 'selected'; endif; ?>>Awaiting your review</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $analytics['pipeline']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stage): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($stage['key']); ?>" <?php if($pipeline === $stage['key']): echo 'selected'; endif; ?>><?php echo e($stage['label']); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </select>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pipeline): ?><button type="button" wire:click="clearPipeline" class="rh-button-secondary !rounded-lg">All stages</button><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <p class="px-5 pb-3 text-sm rh-muted">Oldest waiting submissions first.</p>
                <div class="divide-y divide-slate-100 dark:divide-slate-800" data-dashboard-queue="proposals">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $topics; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $topic): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php
                            $waitingSince = $topic->status_started_at ?? $topic->latestVersion?->created_at;
                            $waitingDays = $waitingSince ? (int) max(0, \Carbon\CarbonImmutable::parse($waitingSince)->diffInDays(now(), false)) : null;
                        ?>
                        <a href="<?php echo e(route('topics.show', $topic)); ?><?php echo e($topic->hasIssuedNoticeToProceed() ? '#project-monitoring' : '#proposal-review'); ?>" aria-label="<?php echo e(match ($topic->status) { 'resubmitted' => 'Review revision', 'ready_for_signature', 'approved' => 'View signing status', default => 'Review proposal' }); ?>: <?php echo e($topic->title); ?>" class="group flex items-start gap-3 px-5 py-5 transition-colors hover:bg-rose-50/60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-brand dark:hover:bg-slate-800/60" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'overview-proposal-'.e($topic->id).''; ?>wire:key="overview-proposal-<?php echo e($topic->id); ?>">
                            <span class="min-w-0 flex-1">
                                <strong class="block break-words text-base font-semibold leading-6 group-hover:text-brand dark:group-hover:text-rose-300"><?php echo e($topic->title); ?></strong>
                                <span class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm rh-muted"><span><?php echo e($topic->user?->name); ?></span><span class="rounded-md bg-slate-100 px-2 py-1 font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300"><?php echo e($topic->researchHeadQueueStatusLabel($topic->latestVersion)); ?></span></span>
                                <span class="mt-2 block text-sm font-medium text-brand dark:text-rose-300"><?php echo e($waitingDays !== null ? $waitingDays.' '.str('day')->plural($waitingDays).($topic->status_started_at ? ' in this stage' : ' since submission') : 'Stage start date unavailable'); ?></span>
                            </span>
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-brand transition-colors group-hover:bg-rose-100 dark:bg-rose-950/40 dark:text-rose-300 dark:group-hover:bg-rose-950/70" aria-hidden="true"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M15 5.5 18.5 9M4 20l4.5-1L20 7.5a2.5 2.5 0 0 0-3.5-3.5L5 15.5 4 20Z" stroke-linecap="round" stroke-linejoin="round" /></svg></span>
                        </a>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <div class="px-5 py-12 text-center"><p class="font-semibold">No proposals found.</p><p class="mt-2 text-sm rh-muted">Try changing the search or filters.</p></div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topics->hasPages()): ?><div class="border-t border-slate-100 px-5 py-4 dark:border-slate-800"><?php echo e($topics->links('livewire::simple-tailwind', ['scrollTo' => '#received-proposals'])); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </section>
        </div>
<section id="dashboard-report-reviews" class="rh-panel !rounded-xl !shadow-none scroll-mt-40" aria-labelledby="dashboard-report-heading">
    <div class="flex flex-wrap items-center justify-between gap-3 px-5 pb-4 pt-5">
        <div><h2 id="dashboard-report-heading" class="text-xl font-bold">Report reviews</h2><p class="mt-1 text-sm rh-muted"><?php echo e($reportItems->total()); ?> awaiting review</p></div>
        <a wire:navigate href="<?php echo e(route('research_head.report-reviews.index')); ?>" class="rh-button whitespace-nowrap">View all reports</a>
    </div>
    <p class="px-5 pb-3 text-sm rh-muted">Oldest submitted reports first.</p>
    <div class="divide-y divide-slate-100 dark:divide-slate-800" data-dashboard-queue="reports">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $reportItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $report): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php
                $reportAnchor = $report->report_type === 'quarterly' ? 'monitoring-tool-'.$report->id : 'narrative-report-'.$report->id;
                $receivedAt = $report->received_at ? \Carbon\CarbonImmutable::parse($report->received_at) : null;
                $waitingDays = $receivedAt ? (int) max(0, $receivedAt->diffInDays(now(), false)) : null;
                $reportLabel = ['quarterly' => 'Monitoring tool', 'progress' => 'Progress report', 'terminal' => 'Terminal report'][$report->report_type];
            ?>
            <a href="<?php echo e(route('topics.show', $report->topic_id)); ?>#<?php echo e($reportAnchor); ?>" aria-label="Review <?php echo e(strtolower($reportLabel)); ?>: <?php echo e($report->title); ?>" class="group flex items-start gap-3 px-5 py-5 transition-colors hover:bg-rose-50/60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-brand dark:hover:bg-red-950/30" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'overview-report-'.e($report->report_type).'-'.e($report->id).''; ?>wire:key="overview-report-<?php echo e($report->report_type); ?>-<?php echo e($report->id); ?>">
                <span class="min-w-0 flex-1">
                <strong class="block break-words text-base font-semibold leading-6"><?php echo e($report->title); ?></strong>
                <span class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm rh-muted"><span class="rounded-md bg-red-50 px-2 py-1 font-medium text-brand dark:bg-red-950/40 dark:text-rose-200"><?php echo e($reportLabel); ?></span><span><?php echo e($report->faculty_name); ?></span></span>
                <span class="mt-2 block text-sm font-medium text-brand dark:text-rose-300"><?php echo e($waitingDays !== null ? $waitingDays.' '.str('day')->plural($waitingDays).' waiting' : 'Submission date unavailable'); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report->report_date): ?> <span class="font-normal rh-muted">· Report dated <?php echo e(\Carbon\CarbonImmutable::parse($report->report_date)->format('M j, Y')); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></span>
                </span>
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-brand transition-colors group-hover:bg-rose-100 dark:bg-rose-950/40 dark:text-rose-300 dark:group-hover:bg-rose-950/70" aria-hidden="true"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Zm0 0v6h6M8 13h8m-8 4h5" stroke-linecap="round" stroke-linejoin="round" /></svg></span>
            </a>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <div class="px-5 py-10 text-center"><p class="text-base font-semibold">No reports awaiting review.</p><p class="mt-2 text-sm rh-muted">Submitted monitoring, progress, and terminal reports appear here.</p></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reportItems->hasPages()): ?><div class="border-t border-slate-100 px-5 py-4 dark:border-slate-800"><?php echo e($reportItems->links('livewire::simple-tailwind', ['scrollTo' => '#dashboard-report-reviews'])); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</section>
    </div>
    <aside class="grid min-w-0 items-start gap-5 lg:grid-cols-2" aria-label="Research priorities">
<section id="needs-attention" class="rh-panel !rounded-xl !shadow-none scroll-mt-40" aria-labelledby="attention-heading">
                <div class="rh-panel-heading !gap-2"><h2 id="attention-heading" class="text-base font-bold">Needs attention</h2><span class="rounded-md bg-red-50 px-2 py-1 text-sm font-semibold text-brand dark:bg-red-950/40 dark:text-rose-200"><?php echo e($attentionItems->total()); ?> <?php echo e(str('issue')->plural($attentionItems->total())); ?></span></div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800" data-dashboard-queue="attention">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $attentionItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <a href="<?php echo e($item['url']); ?>" class="flex items-start justify-between gap-3 px-5 py-4 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-brand dark:hover:bg-slate-800/60" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'overview-issue-'.e($item['id']).'-'.e($item['type']).'-'.e($item['report_id'] ?? 0).''; ?>wire:key="overview-issue-<?php echo e($item['id']); ?>-<?php echo e($item['type']); ?>-<?php echo e($item['report_id'] ?? 0); ?>">
                            <span class="min-w-0"><strong class="block break-words text-base font-semibold leading-6"><?php echo e($item['title']); ?></strong><span class="mt-1.5 block text-sm text-brand dark:text-rose-200"><?php echo e($item['type'] === 'head_review' ? 'Ready for your review' : $item['issue']); ?></span></span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item['days'] !== null): ?><span class="shrink-0 text-right"><strong class="block text-sm font-semibold tabular-nums"><?php echo e($item['days']); ?> <?php echo e(str('day')->plural($item['days'])); ?></strong><span class="text-sm rh-muted"><?php echo e(match ($item['basis']) { 'overdue' => 'overdue', 'since reporting opened' => 'since open', 'since report' => 'since report', default => 'waiting' }); ?></span></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </a>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <div class="px-5 py-8"><p class="text-sm font-semibold">No items need attention.</p><p class="mt-1 text-sm rh-muted">Follow-up items will appear here when recorded.</p></div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($attentionItems->hasPages()): ?><div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800"><?php echo e($attentionItems->links('livewire::simple-tailwind', ['scrollTo' => '#needs-attention'])); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </section>
<section class="rh-panel !rounded-xl !shadow-none" aria-labelledby="deadlines-heading">
                <div class="rh-panel-heading"><h2 id="deadlines-heading" class="text-base font-bold">Upcoming deadlines</h2><a wire:navigate href="<?php echo e(route('research_head.calendar')); ?>" class="rh-button-secondary whitespace-nowrap">Calendar</a></div>
                <p class="px-5 pt-4 text-sm rh-muted">Institution-wide deadlines for the next 14 days.</p>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $deadlines->take(3); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <a href="<?php echo e($event['url']); ?>" class="flex items-center gap-3 px-5 py-4 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-brand dark:hover:bg-slate-800/60">
                            <span class="min-w-0"><strong class="block text-sm font-semibold"><?php echo e($event['title']); ?></strong><span class="mt-1 block break-words text-sm rh-muted"><?php echo e($event['context']); ?></span><?php if (isset($component)) { $__componentOriginala2b55bd53737b58ae21d3ddce75d52a2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala2b55bd53737b58ae21d3ddce75d52a2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.dashboard-deadline','data' => ['date' => $event['at'],'class' => 'mt-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dashboard-deadline'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['date' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($event['at']),'class' => 'mt-2']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala2b55bd53737b58ae21d3ddce75d52a2)): ?>
<?php $attributes = $__attributesOriginala2b55bd53737b58ae21d3ddce75d52a2; ?>
<?php unset($__attributesOriginala2b55bd53737b58ae21d3ddce75d52a2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala2b55bd53737b58ae21d3ddce75d52a2)): ?>
<?php $component = $__componentOriginala2b55bd53737b58ae21d3ddce75d52a2; ?>
<?php unset($__componentOriginala2b55bd53737b58ae21d3ddce75d52a2); ?>
<?php endif; ?></span>
                        </a>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <p class="px-5 py-6 text-sm rh-muted">No official deadlines in the next 14 days.</p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </section>
    </aside>

    <section aria-labelledby="dashboard-reporting-heading" class="border-t border-slate-200 pt-5 dark:border-slate-800" data-dashboard-reporting-links>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="dashboard-reporting-heading" class="text-xl font-bold">Planning & reporting</h2>
        </div>
        <div class="mt-4 grid gap-px overflow-hidden rounded-xl border border-slate-200 bg-slate-200 sm:grid-cols-2 dark:border-slate-800 dark:bg-slate-800">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
                ['Research targets', route('research_head.targets.index'), 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Zm0 4a5 5 0 1 0 0 10 5 5 0 0 0 0-10Zm0 4a1 1 0 1 0 0 2 1 1 0 0 0 0-2Z'],
                ['College reports', route('research_head.quarterly-reports.index'), 'M9 5H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-3M9 3h6v4H9V3Zm-1 9h8m-8 4h5'],
                ['Quarterly monitoring', route('research_head.rms-quarterly-reports.index'), 'M6 3v3m12-3v3M3 10h18M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm2 9h3m4 0h3m-10 3h3'],
                ['Analytics', $analyticsUrl, 'M3 3v18h18M7 13v4m5-8v8m5-12v12'],
            ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $url, $iconPath]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <a wire:navigate href="<?php echo e($url); ?>" class="group flex min-w-0 items-center gap-3 bg-white p-5 transition-colors hover:bg-rose-50/60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-brand dark:bg-slate-900 dark:hover:bg-slate-800">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-brand transition-colors group-hover:bg-rose-100 dark:bg-rose-950/40 dark:text-rose-300 dark:group-hover:bg-rose-950/70" aria-hidden="true"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="<?php echo e($iconPath); ?>" stroke-linecap="round" stroke-linejoin="round" /></svg></span>
                    <span class="text-sm font-semibold text-slate-900 dark:text-slate-100"><?php echo e($label); ?></span>
                </a>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
        <div class="mt-4 grid gap-4 sm:grid-cols-2" aria-label="Research summary">
        <a wire:navigate href="<?php echo e(route('research_head.analytics', [...$filterParams, 'projectStatus' => 'completed'])); ?>#active-projects" class="group flex items-center gap-3 rounded-xl bg-white px-5 py-4 transition-colors hover:bg-rose-50/60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:bg-slate-900 dark:hover:bg-slate-800">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-brand dark:bg-rose-950/40 dark:text-rose-300" aria-hidden="true"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><circle cx="12" cy="12" r="9" /><path d="m8 12 3 3 5-6" stroke-linecap="round" stroke-linejoin="round" /></svg></span>
            <span class="min-w-0 flex-1 text-sm font-semibold">Completed projects</span><strong class="text-xl font-bold tabular-nums"><?php echo e($analytics['kpis']['completed']); ?></strong>
        </a>
        <a wire:navigate href="<?php echo e(route('research_head.faculty-directory.index')); ?>" class="group flex items-center gap-3 rounded-xl bg-white px-5 py-4 transition-colors hover:bg-rose-50/60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:bg-slate-900 dark:hover:bg-slate-800">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-brand dark:bg-rose-950/40 dark:text-rose-300" aria-hidden="true"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke-linecap="round" stroke-linejoin="round" /><circle cx="9" cy="7" r="4" /></svg></span>
            <span class="min-w-0 flex-1 text-sm font-semibold">Faculty in active research</span><strong class="text-xl font-bold tabular-nums"><?php echo e($analytics['kpis']['faculty']); ?></strong>
        </a>
        </div>
    </section>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\livewire\research-head-overview.blade.php ENDPATH**/ ?>