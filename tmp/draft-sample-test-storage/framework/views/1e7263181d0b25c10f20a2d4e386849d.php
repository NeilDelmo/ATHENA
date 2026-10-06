<div class="space-y-5 text-slate-900 dark:text-slate-100" data-research-head-overview>
    <?php
        $filterParams = array_filter(['academicYear' => $academicYear, 'fromDate' => $fromDate, 'toDate' => $toDate]);
        $analyticsUrl = route('research_head.analytics', $filterParams);
        $selectedStage = $pipeline === 'awaiting_review' ? 'Awaiting your review' : ($analytics['pipeline']->firstWhere('key', $pipeline)['label'] ?? 'All review stages');
    ?>

    <section class="grid grid-cols-2 gap-3 md:grid-cols-4" aria-label="Priority KPIs" data-dashboard-priority-kpis>
        <a href="#received-proposals" wire:click="showReviewQueue" class="flex flex-col items-start justify-between gap-4 rounded-xl bg-brand px-5 py-5 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
            <span><span class="block text-sm font-semibold">Proposals awaiting review</span><span class="mt-1 block text-sm text-rose-100">Open the review queue <span aria-hidden="true">&rarr;</span></span></span>
            <strong class="text-4xl font-bold tracking-tight tabular-nums"><?php echo e($analytics['kpis']['review']); ?></strong>
        </a>
        <a href="#dashboard-report-reviews" class="flex flex-col items-start justify-between gap-4 rounded-xl border border-red-200 bg-white px-5 py-5 hover:border-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:border-red-900 dark:bg-slate-900">
            <span><span class="block text-sm font-semibold">Reports awaiting review</span><span class="mt-1 block text-sm rh-muted">Open report reviews <span aria-hidden="true">&rarr;</span></span></span>
            <strong class="text-4xl font-bold tracking-tight tabular-nums text-brand dark:text-rose-300"><?php echo e($reportItems->total()); ?></strong>
        </a>
        <a wire:navigate href="<?php echo e(route('research_head.analytics', [...$filterParams, 'projectStatus' => 'delayed'])); ?>#active-projects" class="flex flex-col items-start justify-between gap-4 rounded-xl border border-slate-200 bg-white px-5 py-5 hover:border-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:border-slate-800 dark:bg-slate-900">
            <span><span class="block text-sm font-semibold">Delayed / overdue</span><span class="mt-1 block text-sm rh-muted">View projects needing follow-up <span aria-hidden="true">&rarr;</span></span></span>
            <strong class="text-4xl font-bold tracking-tight tabular-nums <?php echo e($analytics['kpis']['delayed'] ? 'text-brand dark:text-rose-300' : ''); ?>"><?php echo e($analytics['kpis']['delayed']); ?></strong>
        </a>
        <a wire:navigate href="<?php echo e(route('research_head.analytics', [...$filterParams, 'projectStatus' => 'active'])); ?>#active-projects" class="flex flex-col items-start justify-between gap-4 rounded-xl border border-slate-200 bg-white px-5 py-5 hover:border-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:border-slate-800 dark:bg-slate-900">
            <span><span class="block text-sm font-semibold">Active projects</span><span class="mt-1 block text-sm rh-muted">View projects in progress <span aria-hidden="true">&rarr;</span></span></span>
            <strong class="text-4xl font-bold tracking-tight tabular-nums"><?php echo e($analytics['kpis']['active']); ?></strong>
        </a>
    </section>

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
                    <span class="ml-auto text-lg leading-none group-open:hidden" aria-hidden="true">+</span>
                    <span class="ml-auto hidden text-lg leading-none group-open:inline" aria-hidden="true">&minus;</span>
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
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pipeline): ?><button type="button" wire:click="clearPipeline" class="rh-button-secondary !rounded-lg">All stages</button><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <p class="px-5 pb-3 text-sm rh-muted">Oldest waiting submissions first.</p>
                <div class="divide-y divide-slate-100 dark:divide-slate-800" data-dashboard-queue="proposals">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $topics; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $topic): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php
                            $waitingSince = $topic->status_started_at ?? $topic->latestVersion?->created_at;
                            $waitingDays = $waitingSince ? (int) max(0, \Carbon\CarbonImmutable::parse($waitingSince)->diffInDays(now(), false)) : null;
                        ?>
                        <a href="<?php echo e(route('topics.show', $topic)); ?><?php echo e($topic->hasIssuedNoticeToProceed() ? '#project-monitoring' : '#proposal-review'); ?>" class="group flex items-start gap-3 px-5 py-5 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-brand dark:hover:bg-slate-800/60" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'overview-proposal-'.e($topic->id).''; ?>wire:key="overview-proposal-<?php echo e($topic->id); ?>">
                            <span class="min-w-0 flex-1">
                                <strong class="block break-words text-base font-semibold leading-6 group-hover:text-brand dark:group-hover:text-rose-300"><?php echo e($topic->title); ?></strong>
                                <span class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm rh-muted"><span><?php echo e($topic->user?->name); ?></span><span class="rounded-md bg-slate-100 px-2 py-1 font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300"><?php echo e($topic->researchHeadQueueStatusLabel($topic->latestVersion)); ?></span></span>
                                <span class="mt-2 block text-sm font-medium text-brand dark:text-rose-300"><?php echo e($waitingDays !== null ? $waitingDays.' '.str('day')->plural($waitingDays).($topic->status_started_at ? ' in this stage' : ' since submission') : 'Stage start date unavailable'); ?></span>
                            </span>
                            <svg class="mt-1 h-5 w-5 shrink-0 text-slate-400 group-hover:text-brand dark:group-hover:text-rose-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m9 5 7 7-7 7" stroke-linecap="round" stroke-linejoin="round" /></svg>
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
            <a href="<?php echo e(route('topics.show', $report->topic_id)); ?>#<?php echo e($reportAnchor); ?>" class="block px-5 py-5 hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-brand dark:hover:bg-red-950/30" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'overview-report-'.e($report->report_type).'-'.e($report->id).''; ?>wire:key="overview-report-<?php echo e($report->report_type); ?>-<?php echo e($report->id); ?>">
                <strong class="block break-words text-base font-semibold leading-6"><?php echo e($report->title); ?></strong>
                <span class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm rh-muted"><span class="rounded-md bg-red-50 px-2 py-1 font-medium text-brand dark:bg-red-950/40 dark:text-rose-200"><?php echo e($reportLabel); ?></span><span><?php echo e($report->faculty_name); ?></span></span>
                <span class="mt-2 block text-sm font-medium text-brand dark:text-rose-300"><?php echo e($waitingDays !== null ? $waitingDays.' '.str('day')->plural($waitingDays).' waiting' : 'Submission date unavailable'); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report->report_date): ?> <span class="font-normal rh-muted">· Report dated <?php echo e(\Carbon\CarbonImmutable::parse($report->report_date)->format('M j, Y')); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></span>
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
                            <?php if (isset($component)) { $__componentOriginal22ec3e17b5d8a0cb17e0472dbac40c07 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal22ec3e17b5d8a0cb17e0472dbac40c07 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.date-chip','data' => ['date' => \Illuminate\Support\Carbon::parse($event['at']),'compact' => true,'weekday' => false,'relative' => false,'tone' => 'rose']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('date-chip'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['date' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(\Illuminate\Support\Carbon::parse($event['at'])),'compact' => true,'weekday' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(false),'relative' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(false),'tone' => 'rose']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal22ec3e17b5d8a0cb17e0472dbac40c07)): ?>
<?php $attributes = $__attributesOriginal22ec3e17b5d8a0cb17e0472dbac40c07; ?>
<?php unset($__attributesOriginal22ec3e17b5d8a0cb17e0472dbac40c07); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal22ec3e17b5d8a0cb17e0472dbac40c07)): ?>
<?php $component = $__componentOriginal22ec3e17b5d8a0cb17e0472dbac40c07; ?>
<?php unset($__componentOriginal22ec3e17b5d8a0cb17e0472dbac40c07); ?>
<?php endif; ?>
                            <span class="min-w-0"><strong class="block text-sm font-semibold"><?php echo e($event['title']); ?></strong><span class="mt-1 block break-words text-sm rh-muted"><?php echo e($event['context']); ?></span></span>
                        </a>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <p class="px-5 py-6 text-sm rh-muted">No official deadlines in the next 14 days.</p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </section>
    </aside>

    <section class="grid min-w-0 gap-5 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]" aria-label="Research activity and project health" data-dashboard-visual-summary>
        <section class="rh-panel !rounded-xl !shadow-none p-5" aria-labelledby="overview-activity-heading">
            <?php
                $recentMonths = $analytics['trend']->take(-6)->values();
                $activityMax = max(1, $recentMonths->max(fn ($month) => max($month['new'], $month['revision'])));
                $activityTotal = $recentMonths->sum('new') + $recentMonths->sum('revision');
            ?>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><h2 id="overview-activity-heading" class="text-base font-bold">Submission activity</h2><p class="mt-1 text-sm rh-muted">New proposals and revision submissions · recent months in the selected period</p></div>
                <a wire:navigate href="<?php echo e($analyticsUrl); ?>" class="rh-button-secondary whitespace-nowrap">View analytics</a>
            </div>
            <div class="mt-4 flex flex-wrap gap-4 text-sm rh-muted"><span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-sm bg-brand dark:bg-rose-400" aria-hidden="true"></span>New proposals</span><span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-sm bg-slate-400" aria-hidden="true"></span>Revision submissions</span></div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activityTotal > 0): ?>
                <div class="mt-5 flex min-w-0 items-end gap-2" data-dashboard-activity-chart>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $recentMonths; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $month): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <a wire:navigate href="<?php echo e(route('research_head.analytics', [...$filterParams, 'submissionMonth' => $month['key']])); ?>#received-proposals" class="min-w-0 flex-1 rounded-lg px-1 pb-2 pt-1 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:hover:bg-slate-800" aria-label="<?php echo e($month['label']); ?>: <?php echo e($month['new']); ?> new proposals and <?php echo e($month['revision']); ?> revision submissions" title="<?php echo e($month['label']); ?>: <?php echo e($month['new']); ?> new, <?php echo e($month['revision']); ?> revisions">
                            <span class="flex h-36 items-end justify-center gap-1 border-b border-slate-200 dark:border-slate-700" aria-hidden="true"><span class="w-4 rounded-t bg-brand dark:bg-rose-400" style="height: <?php echo e(100 * $month['new'] / $activityMax); ?>%"></span><span class="w-4 rounded-t bg-slate-400" style="height: <?php echo e(100 * $month['revision'] / $activityMax); ?>%"></span></span>
                            <span class="mt-2 block text-center text-sm font-medium rh-muted"><?php echo e(\Illuminate\Support\Carbon::parse($month['key'].'-01')->format('M')); ?></span><span class="mt-1 block text-center text-sm font-semibold tabular-nums"><?php echo e($month['new']); ?> / <?php echo e($month['revision']); ?></span>
                        </a>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
                <p class="mt-3 text-sm rh-muted"><?php echo e($activityTotal); ?> submissions. Counts show new / revised. Select a month to inspect proposals.</p>
            <?php else: ?>
                <div class="mt-5 flex min-h-36 items-center justify-center rounded-lg border border-dashed border-slate-200 p-4 dark:border-slate-700"><p class="text-center text-sm rh-muted"><?php echo e($analytics['periodAvailable'] ? 'No submission activity recorded in these months.' : 'Choose academic-year dates to see submission activity.'); ?></p></div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </section>
        <section class="rh-panel !rounded-xl !shadow-none p-5" aria-labelledby="overview-health-heading">
            <?php
                $projectTotal = $analytics['projectStatuses']->sum('count');
                $projectColors = ['ongoing' => '#bf6a7b', 'delayed' => '#7a0019', 'awaiting' => '#e8a9b5', 'completed' => '#64748b'];
                $ringOffset = 0;
            ?>
            <h2 id="overview-health-heading" class="text-base font-bold">Project health</h2>
            <p class="mt-1 text-sm rh-muted">Issued projects in the selected scope</p>
            <div class="mt-5 flex flex-col items-stretch gap-5 sm:flex-row sm:items-center">
                <div class="relative h-36 w-36 shrink-0 self-center sm:self-auto" role="img" aria-label="<?php echo e($projectTotal); ?> issued projects. <?php echo e($analytics['projectStatuses']->map(fn ($state) => $state['label'].': '.$state['count'])->join('; ')); ?>">
                    <svg viewBox="0 0 100 100" class="h-full w-full -rotate-90" fill="none" aria-hidden="true">
                        <circle cx="50" cy="50" r="40" stroke-width="10" class="stroke-slate-100 dark:stroke-slate-800" />
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $analytics['projectStatuses']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $state): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php ($share = 100 * $state['count'] / max(1, $projectTotal)); ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($share > 0): ?><circle cx="50" cy="50" r="40" stroke-width="10" pathLength="100" stroke="<?php echo e($projectColors[$state['key']]); ?>" stroke-dasharray="<?php echo e($share); ?> <?php echo e(100 - $share); ?>" stroke-dashoffset="<?php echo e(-$ringOffset); ?>" /><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php ($ringOffset += $share); ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center"><strong class="text-3xl font-bold tabular-nums"><?php echo e($projectTotal); ?></strong><span class="text-sm rh-muted">projects</span></div>
                </div>
                <div class="min-w-0 flex-1 space-y-2">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $analytics['projectStatuses']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $state): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <a wire:navigate href="<?php echo e(route('research_head.analytics', [...$filterParams, 'projectStatus' => $state['key']])); ?>#active-projects" class="flex min-h-10 items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:border-slate-700 dark:hover:bg-slate-800">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-sm" style="background-color: <?php echo e($projectColors[$state['key']]); ?>" aria-hidden="true"></span><span class="min-w-0 flex-1"><?php echo e($state['label']); ?></span><strong class="tabular-nums"><?php echo e($state['count']); ?></strong>
                        </a>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($projectTotal === 0): ?><p class="mt-4 text-sm rh-muted">Projects appear here once their signed Notice to Proceed is issued.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </section>
    </section>

    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
<section class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="pipeline-heading">
                <div class="flex items-center justify-between gap-3"><h2 id="pipeline-heading" class="text-base font-bold">Proposal pipeline</h2><span class="text-sm rh-muted"><?php echo e($analytics['pipeline']->sum('count')); ?> total</span></div>
                <p class="mt-1 text-sm rh-muted">Compare proposal counts by stage. Click a row to filter the review queue.</p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2" data-dashboard-stage-filters data-dashboard-pipeline-chart>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $analytics['pipeline']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stage): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <button type="button" wire:click="setPipeline('<?php echo e($stage['key']); ?>')" aria-pressed="<?php echo e($pipeline === $stage['key'] ? 'true' : 'false'); ?>" aria-label="Show <?php echo e($stage['label']); ?> proposals: <?php echo e($stage['count']); ?>"
                            class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                                'grid min-h-[56px] w-full grid-cols-[minmax(0,1fr)_2rem] items-center gap-x-3 gap-y-2 rounded-lg border px-3 py-3 text-left focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand',
                                'border-brand bg-brand-wash text-brand dark:border-rose-400 dark:bg-rose-950/40 dark:text-rose-200' => $pipeline === $stage['key'],
                                'border-slate-200 hover:border-slate-400 dark:border-slate-700 dark:hover:border-slate-500' => $pipeline !== $stage['key'],
                            ]); ?>">
                            <span class="text-sm font-semibold leading-5"><?php echo e($stage['label']); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pipeline === $stage['key']): ?><span class="ml-1">Selected</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></span><strong class="text-lg font-bold tabular-nums text-right"><?php echo e($stage['count']); ?></strong>
                            <span class="col-span-2 block h-2 overflow-hidden rounded bg-slate-100 dark:bg-slate-800" aria-hidden="true"><span class="block h-full rounded bg-brand dark:bg-rose-400" style="width: <?php echo e(100 * $stage['count'] / max(1, $analytics['pipeline']->max('count'))); ?>%"></span></span>
                        </button>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($analytics['pipeline']->sum('count') === 0): ?><p class="mt-3 text-sm rh-muted">No data yet.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </section>
<section class="px-1" aria-labelledby="annual-plan-heading">
                <div class="flex items-center justify-between gap-3"><h2 id="annual-plan-heading" class="text-base font-bold">Annual targets</h2><a wire:navigate href="<?php echo e($analyticsUrl); ?>#annual-targets" class="inline-flex min-h-[44px] items-center text-sm font-semibold text-brand hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:text-rose-300">Manage targets</a></div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($academicYear && $analytics['targetMatches']): ?>
                    <dl class="mt-2 divide-y divide-slate-200 dark:divide-slate-800">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $analytics['targets']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $metric): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <div class="flex items-start justify-between gap-3 py-3 text-sm"><dt class="rh-muted"><?php echo e($metric['label']); ?></dt><dd class="shrink-0 font-semibold tabular-nums"><?php echo e($metric['actual'] ?? '—'); ?> / <?php echo e($metric['target'] ?? 'Not set'); ?></dd></div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </dl>
                <?php else: ?>
                    <p class="mt-1 text-sm leading-5 rh-muted"><?php echo e($academicYear ? 'Set year dates and targets, or select the full academic year.' : 'Choose an academic year to see its targets.'); ?></p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </section>
    </div>
    <section class="grid gap-4 border-t border-slate-200 pt-5 dark:border-slate-800 sm:grid-cols-3" aria-label="Research summary">
        <a wire:navigate href="<?php echo e(route('research_head.analytics', [...$filterParams, 'projectStatus' => 'completed'])); ?>#active-projects" class="flex items-center justify-between gap-4 rounded-lg p-2 hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:hover:bg-slate-900">
            <span><span class="block text-sm font-semibold rh-muted">Completed projects</span><span class="mt-1 block text-sm font-semibold text-brand dark:text-rose-300">View completed projects &rarr;</span></span><strong class="text-xl font-bold tabular-nums"><?php echo e($analytics['kpis']['completed']); ?></strong>
        </a>
        <a wire:navigate href="<?php echo e(route('research_head.faculty-directory.index')); ?>" class="flex items-center justify-between gap-4 rounded-lg p-2 hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:hover:bg-slate-900">
            <span><span class="block text-sm font-semibold rh-muted">Faculty in research</span><span class="mt-1 block text-sm font-semibold text-brand dark:text-rose-300">View faculty directory &rarr;</span></span><strong class="text-xl font-bold tabular-nums"><?php echo e($analytics['kpis']['faculty']); ?></strong>
        </a>
        <a wire:navigate href="<?php echo e($analyticsUrl); ?>#reported-budget" class="flex items-center justify-between gap-4 rounded-lg p-2 hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:hover:bg-slate-900">
            <span><span class="block text-sm font-semibold rh-muted">Reported budget utilization</span><span class="mt-1 block text-sm font-semibold text-brand dark:text-rose-300">View budget breakdown &rarr;</span></span><strong class="text-xl font-bold tabular-nums"><?php echo e($analytics['budget']['percentage'] !== null ? number_format($analytics['budget']['percentage'], 1).'%' : '—'); ?></strong>
        </a>
    </section>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/livewire/research-head-overview.blade.php ENDPATH**/ ?>