<div class="rh-analytics flex flex-col gap-6 text-base text-slate-900 dark:text-slate-100" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'research-head-dashboard'; ?>wire:key="research-head-dashboard" data-analytics-layout="workbench" x-data x-on:analytics-drilldown.window="$nextTick(() => { const target = document.getElementById($event.detail.target); if (!target || !$el.contains(target)) return; target.focus({ preventScroll: true }); target.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' }); })">
    <?php
        $chartAxisMax = max(4, (int) ceil($analytics['trendMax'] / 4) * 4);
        $pipelineMax = max(1, (int) $analytics['pipeline']->max('count'));
        $projectTotal = $analytics['projects']->count();
        $cohortScope = ($academicYear ?: 'All academic years').($fromDate || $toDate ? ' · first submitted '.($fromDate ?: 'any date').' through '.($toDate ?: 'any date') : ' · all submission dates');
        $proposalHeading = $submissionMonth ? 'Proposals with submissions in '.($analytics['trend']->firstWhere('key', $submissionMonth)['label'] ?? $submissionMonth) : ($pipeline === 'awaiting_review' ? 'Proposals awaiting your review' : ($pipeline ? (\App\Services\ResearchHeadAnalytics::PIPELINE[$pipeline] ?? 'Selected stage').' proposals' : ($attention === 'repeat' ? 'Proposals with repeated revisions' : 'Received proposal inbox')));
    ?>

    <section class="rh-panel rh-analytics-filters p-5 sm:p-6" aria-label="Analytics filters">
        <form wire:submit="applyFilters" class="flex flex-wrap items-end gap-3">
            <div class="min-w-40 flex-1">
                <label for="analytics-year" class="block text-sm font-semibold">Academic year</label>
                <select id="analytics-year" wire:model.live="academicYear" class="rh-control mt-1.5 w-full">
                    <option value="">All academic years</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $academicYears; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $year): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($year); ?>"><?php echo e($year); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </select>
            </div>
            <div class="min-w-40 flex-1"><label for="analytics-from" class="block text-sm font-semibold">First submitted from</label><input id="analytics-from" type="date" wire:model="fromDate" class="rh-control mt-1.5 w-full"></div>
            <div class="min-w-40 flex-1"><label for="analytics-to" class="block text-sm font-semibold">First submitted through</label><input id="analytics-to" type="date" wire:model="toDate" class="rh-control mt-1.5 w-full"></div>
            <button class="rh-button bg-red-600 hover:bg-red-700 focus-visible:outline-red-600" wire:loading.attr="disabled"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M7 12h10M10 17h4"/></svg>Apply filters</button>
            <button type="button" wire:click="resetAnalyticsFilters" class="rh-button-secondary"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10a9 9 0 1 1 2 8M3 4v6h6"/></svg>Reset</button>
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
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($academicYear && ! $analytics['target']): ?><p class="mt-3 text-sm text-amber-800 dark:text-amber-300">Set this academic year's dates and targets below to complete the annual comparison.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <p class="mt-3 text-sm rh-muted">Current status of the selected submission cohort · <?php echo e($cohortScope); ?></p>
    </section>

    <section class="rh-panel rh-analytics-kpis grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5" aria-label="Research summary" data-dashboard-kpi-band>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [['review', 'Awaiting your review', 'showReviewQueue', 'Review proposals'], ['active', 'Active projects', "showProjects('active')", 'Inspect active projects'], ['delayed', 'Delayed / overdue', "showProjects('delayed')", 'Inspect delays'], ['completed', 'Completed projects', "showProjects('completed')", 'Open completed projects'], ['faculty', 'Faculty in research', 'showFacultyResearchers', 'See researchers and projects']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$key, $label, $action, $actionLabel]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php
                $selected = $key === 'review' ? $pipeline === 'awaiting_review' && $projectStatus === '' && ! $showFaculty : ($key === 'faculty' ? $showFaculty : $projectStatus === $key);
            ?>
            <button type="button" x-ref="metric_<?php echo e($key); ?>" wire:click="<?php echo e($action); ?>" wire:loading.attr="disabled" aria-pressed="<?php echo e($selected ? 'true' : 'false'); ?>" class="flex flex-col justify-between gap-3 p-5 text-left hover:bg-brand-wash focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:hover:bg-slate-800 <?php echo e($key === 'review' ? 'rh-analytics-review' : ''); ?> <?php echo e($selected ? 'bg-brand-wash dark:bg-rose-950/40' : ''); ?>">
                <span class="text-sm font-semibold rh-muted"><?php echo e($label); ?></span>
                <strong class="text-4xl font-bold tracking-tight tabular-nums <?php echo e($key === 'review' ? 'text-brand dark:text-rose-300' : ($key === 'delayed' && $analytics['kpis'][$key] ? 'text-red-700 dark:text-red-300' : '')); ?>"><?php echo e($analytics['kpis'][$key]); ?></strong>
                <span class="inline-flex items-center gap-2 text-xs font-semibold text-brand dark:text-rose-300"><?php echo e($actionLabel); ?> <span aria-hidden="true">&rarr;</span></span>
            </button>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </section>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($projectStatus !== ''): ?>
        <?php if (isset($component)) { $__componentOriginal9754f437c85a92113ab47ab5936d5d71 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9754f437c85a92113ab47ab5936d5d71 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.research-head-project-results','data' => ['items' => $projectItems,'statuses' => $analytics['projectStatuses'],'filter' => $projectStatus,'scope' => $cohortScope]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('research-head-project-results'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['items' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($projectItems),'statuses' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($analytics['projectStatuses']),'filter' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($projectStatus),'scope' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($cohortScope)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9754f437c85a92113ab47ab5936d5d71)): ?>
<?php $attributes = $__attributesOriginal9754f437c85a92113ab47ab5936d5d71; ?>
<?php unset($__attributesOriginal9754f437c85a92113ab47ab5936d5d71); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9754f437c85a92113ab47ab5936d5d71)): ?>
<?php $component = $__componentOriginal9754f437c85a92113ab47ab5936d5d71; ?>
<?php unset($__componentOriginal9754f437c85a92113ab47ab5936d5d71); ?>
<?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showFaculty): ?>
        <section id="faculty-researchers" tabindex="-1" class="rh-panel scroll-mt-40 p-5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand" aria-labelledby="faculty-heading" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'analytics-faculty-results'; ?>wire:key="analytics-faculty-results">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div><h3 id="faculty-heading" class="rh-title">Faculty in active research</h3><p role="status" class="mt-1 text-sm rh-muted"><?php echo e($facultyItems->total()); ?> matching <?php echo e(str('researcher')->plural($facultyItems->total())); ?> · <?php echo e($cohortScope); ?></p></div>
                <button type="button" wire:click="$set('showFaculty', false)" x-on:click="$refs.metric_faculty.focus()" class="rh-button-secondary">Close researcher list</button>
            </div>
            <p class="mt-3 text-sm rh-muted">Registered faculty leads and accepted research staff on active issued projects. Each person is counted once; completed projects and secretary roles are excluded.</p>
            <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $facultyItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $person): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <article class="min-w-0 rounded-xl border border-slate-200 p-4 dark:border-slate-800" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'analytics-faculty-'.e($person['id']).''; ?>wire:key="analytics-faculty-<?php echo e($person['id']); ?>">
                        <h4 class="break-words font-semibold"><?php echo e($person['name']); ?></h4><p class="mt-1 text-sm rh-muted"><?php echo e($person['college'] ?: 'College not recorded'); ?></p>
                        <ul class="mt-3 flex flex-col gap-3">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $person['projects']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <li><a href="<?php echo e(route('topics.show', $project['id'])); ?>#project-monitoring" class="break-words secondary-action"><?php echo e($project['title']); ?></a><span class="block text-xs rh-muted"><?php echo e($project['role'] === 'lead' ? 'Project lead' : 'Research staff'); ?></span></li>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </ul>
                    </article>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <p class="py-6 text-sm rh-muted">No registered faculty participate in active issued projects in this cohort.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($facultyItems->hasPages()): ?><div class="mt-4"><?php echo e($facultyItems->links('livewire::simple-tailwind', ['scrollTo' => '#faculty-researchers'])); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </section>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <section class="rh-panel p-5" aria-labelledby="monthly-trend-heading">
            <h3 id="monthly-trend-heading" class="rh-title">Monthly submission trend</h3>
            <p class="mt-1 text-xs font-medium text-brand dark:text-rose-300">Click a month to inspect submitted proposals and revisions.</p>
            <p class="mt-1 text-sm rh-muted"><?php echo e($analytics['trendStart']); ?> — <?php echo e($analytics['trendEnd']); ?></p>
            <p class="mt-1 text-xs rh-muted">Recorded submission activity for <?php echo e($academicYear ?: 'all academic years'); ?>; older proposals may have revisions in this period.</p>
            <div class="mt-5 flex flex-wrap gap-x-6 gap-y-2 text-base">
                <span class="flex items-center gap-2"><span class="h-3 w-3 rounded-sm bg-brand" aria-hidden="true"></span> New proposals <strong class="tabular-nums"><?php echo e($analytics['trend']->sum('new')); ?></strong></span>
                <span class="flex items-center gap-2"><span class="h-3 w-3 rounded-sm bg-slate-400" aria-hidden="true"></span> Revisions <strong class="tabular-nums"><?php echo e($analytics['trend']->sum('revision')); ?></strong></span>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($analytics['trend']->sum('new') + $analytics['trend']->sum('revision') > 0): ?>
                <div class="mt-6 flex gap-3" data-submission-chart>
                    <div class="relative h-52 w-9 shrink-0 text-right text-sm tabular-nums rh-muted" aria-label="Proposal count axis">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = range(0, 4); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tick): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><span class="absolute right-0 -translate-y-1/2" style="top: <?php echo e($tick * 25); ?>%"><?php echo e((int) ($chartAxisMax * (4 - $tick) / 4)); ?></span><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                    <div class="min-w-0 flex-1 overflow-x-auto pb-2">
                        <div class="relative" style="min-width: <?php echo e(max(280, $analytics['trend']->count() * 52)); ?>px">
                            <div class="pointer-events-none absolute inset-x-0 top-0 h-52" aria-hidden="true">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = range(0, 4); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tick): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><div class="absolute inset-x-0 border-t border-slate-100 dark:border-slate-800" style="top: <?php echo e($tick * 25); ?>%"></div><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </div>
                            <div class="relative flex gap-2">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $analytics['trend']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $month): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <button type="button" wire:click="showSubmissionMonth('<?php echo e($month['key']); ?>')" class="min-w-12 flex-1 rounded-md px-1 text-center hover:bg-brand-wash dark:hover:bg-rose-950/40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand <?php echo e($submissionMonth === $month['key'] ? 'bg-brand-wash dark:bg-rose-950/40 ring-1 ring-brand' : ''); ?>" aria-label="<?php echo e($month['label']); ?>: <?php echo e($month['new']); ?> new proposals and <?php echo e($month['revision']); ?> revisions. Filter inbox." aria-pressed="<?php echo e($submissionMonth === $month['key'] ? 'true' : 'false'); ?>" title="<?php echo e($month['label']); ?>: <?php echo e($month['new']); ?> new · <?php echo e($month['revision']); ?> revisions">
                                        <span class="flex h-52 items-end justify-center gap-1.5" aria-hidden="true">
                                            <span class="w-4 rounded-t-md bg-brand dark:bg-rose-400 sm:w-5" style="height: <?php echo e(100 * $month['new'] / $chartAxisMax); ?>%"></span>
                                            <span class="w-4 rounded-t-md bg-slate-400 sm:w-5" style="height: <?php echo e(100 * $month['revision'] / $chartAxisMax); ?>%"></span>
                                        </span>
                                        <span class="mt-3 block text-sm font-medium rh-muted"><?php echo e($month['label']); ?></span>
                                    </button>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($submissionMonth): ?>
                    <?php
                        $selectedMonth = $analytics['trend']->firstWhere('key', $submissionMonth);
                    ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedMonth): ?><p role="status" class="mt-4 text-sm font-semibold text-brand dark:text-rose-300"><?php echo e($selectedMonth['label']); ?>: <?php echo e($selectedMonth['new']); ?> new proposals · <?php echo e($selectedMonth['revision']); ?> revisions</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php else: ?>
                    <p class="mt-4 text-sm rh-muted">Select a month to filter the proposal inbox.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php else: ?>
                <div class="mt-6 flex min-h-52 items-center justify-center rounded-xl bg-slate-50 px-6 dark:bg-slate-950/50 text-center"><p class="text-base rh-muted">No data yet<?php echo e($analytics['periodAvailable'] ? ' for this period.' : ' — set academic-year dates.'); ?></p></div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </section>
        <section class="rh-panel p-5" aria-labelledby="pipeline-heading">
            <div class="flex items-center justify-between gap-3"><h3 id="pipeline-heading" class="rh-title">Proposal pipeline</h3><span class="rh-badge"><?php echo e($analytics['pipeline']->sum('count')); ?> total</span></div>
            <p class="mt-2 text-xs rh-muted">Current stages in the selected cohort. Click a stage to inspect its proposals.</p>
            <div class="mt-4 flex flex-col gap-1">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $analytics['pipeline']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stage): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <button type="button" wire:click="setPipeline('<?php echo e($stage['key']); ?>')" aria-pressed="<?php echo e($pipeline === $stage['key'] ? 'true' : 'false'); ?>" class="rounded-xl px-2 py-1.5 text-left hover:bg-brand-wash dark:hover:bg-rose-950/40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand <?php echo e($pipeline === $stage['key'] ? 'bg-brand-wash dark:bg-rose-950/40' : ''); ?>">
                        <span class="flex items-center justify-between gap-3 text-sm font-semibold"><span><?php echo e($stage['label']); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pipeline === $stage['key']): ?><span class="ml-1 text-xs text-brand dark:text-rose-300">Selected</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></span><strong class="text-lg tabular-nums"><?php echo e($stage['count']); ?></strong></span>
                        <span class="mt-2 block h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" aria-hidden="true"><span class="block h-full rounded-full bg-brand dark:bg-rose-400" style="width: <?php echo e(100 * $stage['count'] / $pipelineMax); ?>%"></span></span>
                    </button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($analytics['pipeline']->sum('count') === 0): ?><p class="mt-3 text-sm rh-muted">No data yet.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </section>
    </div>


    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]" data-dashboard-performance-grid>
    <section id="annual-targets" class="rh-panel scroll-mt-40" aria-labelledby="targets-heading">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
            <div><h3 id="targets-heading" class="rh-title">Annual research targets</h3><p class="mt-1 text-sm rh-muted"><?php echo e($academicYear ?: 'Select an academic year to compare achievement with its targets.'); ?></p><p class="mt-1 text-xs rh-muted">Institution-wide achievements dated <?php echo e($analytics['trendStart']); ?> — <?php echo e($analytics['trendEnd']); ?>, including projects from earlier calls.</p></div>
            <button type="button" wire:click="editTargets" aria-expanded="<?php echo e($editingTargets ? 'true' : 'false'); ?>" aria-controls="annual-target-editor" class="rh-button-secondary"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="3" fill="currentColor"/><circle cx="15" cy="17" r="3" fill="currentColor"/></svg><?php echo e($editingTargets ? 'Close target settings' : ($analytics['target'] ? 'Edit annual targets' : 'Set annual targets')); ?></button>
        </div>
        <div class="grid divide-y divide-slate-100 dark:divide-slate-800 sm:grid-cols-3 sm:divide-x sm:divide-y-0" data-annual-achievements>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $analytics['targets']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $metric): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php
                    $achievementPercent = $metric['target'] > 0 && $metric['actual'] !== null ? round(100 * $metric['actual'] / $metric['target'], 1) : null;
                    $targetLabel = $metric['label'];
                ?>
                <div class="flex flex-col gap-3 p-5">
                    <h4 class="text-base font-semibold"><?php echo e($targetLabel); ?></h4>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($achievementPercent !== null): ?>
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="relative h-20 w-20 shrink-0" role="progressbar" aria-label="<?php echo e($targetLabel); ?> annual target achievement" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo e(min(100, $achievementPercent)); ?>" aria-valuetext="<?php echo e($metric['actual']); ?> achieved against a target of <?php echo e($metric['target']); ?>">
                                <svg class="h-full w-full -rotate-90" viewBox="0 0 100 100" fill="none" aria-hidden="true">
                                    <circle cx="50" cy="50" r="42" stroke-width="7" class="stroke-slate-100 dark:stroke-slate-800" />
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($achievementPercent > 0): ?>
                                        <circle cx="50" cy="50" r="42" stroke-width="7" stroke-linecap="round" pathLength="100" stroke-dasharray="100" stroke-dashoffset="<?php echo e(100 - min(100, $achievementPercent)); ?>" class="stroke-brand dark:stroke-rose-400" />
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </svg>
                                <strong class="absolute inset-0 flex items-center justify-center text-3xl font-bold tabular-nums"><?php echo e($metric['actual']); ?></strong>
                            </div>
                            <div class="flex min-w-0 flex-col gap-1 text-sm">
                                <span class="text-base font-semibold">of <?php echo e($metric['target']); ?> target</span>
                                <span class="font-semibold text-brand dark:text-rose-300"><?php echo e($achievementPercent); ?>% achieved</span>
                                <span class="rh-muted"><?php echo e($metric['actual'] >= $metric['target'] ? 'Target met' : ($metric['target'] - $metric['actual']).' to go'); ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="flex flex-wrap items-baseline gap-2 tabular-nums">
                            <strong class="text-4xl font-bold tracking-tight"><?php echo e($metric['actual'] ?? '—'); ?></strong>
                            <span class="text-base rh-muted"><?php echo e($metric['target'] !== null ? 'of '.$metric['target'].' target' : ($metric['actual'] === null ? 'No data yet' : 'achieved')); ?></span>
                        </div>
                        <p class="text-sm rh-muted"><?php echo e($metric['target'] === 0 ? 'Zero target set; percentage not applicable.' : ($metric['actual'] === null ? 'Year dates needed' : ($academicYear && $analytics['target'] && ! $analytics['targetMatches'] ? 'Annual target hidden for this date range' : 'No target set'))); ?></p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($academicYear && $analytics['target'] && ! $analytics['targetMatches']): ?><p class="border-t border-slate-100 dark:border-slate-800 px-6 py-3 text-sm text-amber-800 dark:text-amber-300">Select the full academic year to compare with annual targets.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($editingTargets): ?>
            <div id="annual-target-editor" class="border-t border-slate-100 dark:border-slate-800 bg-slate-50 p-5 dark:bg-slate-950/50">
                <h4 class="text-lg font-semibold">Set the year's goals</h4>
                <form wire:submit="saveTargets" class="mt-4 grid gap-4 sm:grid-cols-3">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['academic_year' => ['Academic year', 'text'], 'starts_on' => ['Year starts', 'date'], 'ends_on' => ['Year ends', 'date'], 'projects_target' => ['Research projects target', 'number'], 'publications_target' => ['Publications target', 'number'], 'faculty_target' => ['Faculty participation target', 'number']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => [$label, $type]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div>
                            <label for="target-<?php echo e($field); ?>" class="block text-sm font-semibold"><?php echo e($label); ?></label>
                            <input id="target-<?php echo e($field); ?>" type="<?php echo e($type); ?>" wire:model="targetForm.<?php echo e($field); ?>" <?php if($type === 'number'): ?> min="0" max="1000000" step="1" <?php endif; ?> <?php if($field === 'academic_year'): ?> placeholder="e.g. 2026-2027" <?php endif; ?> class="rh-control mt-2 w-full">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['targetForm.'.$field];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p role="alert" class="mt-2 text-sm text-red-700 dark:text-red-300"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <div class="flex flex-wrap items-center gap-4 sm:col-span-3">
                        <button wire:loading.attr="disabled" class="rh-button"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3h12l4 4v14H3V3h2Zm2 0v6h10V3M7 21v-8h10v8"/></svg>Save year and targets</button>
                        <p class="text-sm rh-muted">Leave unknown targets blank. Use 0 for an intentional zero target.</p>
                    </div>
                </form>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('targets_saved')): ?><p role="status" class="border-t border-slate-100 dark:border-slate-800 px-6 py-3 text-sm font-semibold text-emerald-700 dark:text-emerald-300"><?php echo e(session('targets_saved')); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>

        <section id="reported-budget" class="rh-panel min-w-0 scroll-mt-40 flex flex-col gap-5 p-5">
            <div><h3 class="rh-title">Reported budget utilization</h3><p class="mt-1 text-sm rh-muted">Per project · latest submitted Monitoring Tool</p></div>
            <div class="max-h-96 space-y-4 overflow-y-auto pr-1" role="region" aria-label="Project budget reports" tabindex="0">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $analytics['projects']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <article class="space-y-3 rounded-xl border border-slate-200 p-4 dark:border-slate-800" data-project-budget="<?php echo e($project['id']); ?>" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'project-budget-'.e($project['id']).''; ?>wire:key="project-budget-<?php echo e($project['id']); ?>">
                        <a href="<?php echo e(route('topics.show', $project['id'])); ?>#<?php echo e($project['report_id'] ? 'monitoring-tool-'.$project['report_id'] : 'project-monitoring'); ?>" class="secondary-action justify-start text-left"><?php echo e($project['title']); ?></a>
                        <p class="break-words text-sm rh-muted"><?php echo e($project['lead']); ?></p>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($project['percentage'] !== null): ?>
                            <strong class="block text-2xl font-bold tabular-nums text-brand dark:text-rose-300"><?php echo e(number_format($project['percentage'], 1)); ?>%</strong>
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" role="progressbar" aria-label="<?php echo e($project['title']); ?> budget utilization" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo e(min(100, max(0, $project['percentage']))); ?>" aria-valuetext="<?php echo e($project['percentage']); ?> percent reported utilization"><div class="h-full rounded-full bg-brand dark:bg-rose-400" style="width: <?php echo e(min(100, max(0, $project['percentage']))); ?>%"></div></div>
                        <?php else: ?>
                            <p class="text-sm rh-muted"><?php echo e($project['utilized'] !== null ? ($project['budget_availability'] === 'zero' ? 'Zero budget recorded; utilization unavailable' : 'Budget not recorded; utilization unavailable') : 'No utilization reported'); ?></p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <dl class="grid grid-cols-2 gap-3 text-sm">
                            <div class="min-w-0"><dt class="rh-muted">Reported disbursements</dt><dd class="mt-1 break-words font-semibold tabular-nums"><?php echo e($project['utilized'] !== null ? '₱'.number_format($project['utilized'], 2) : '—'); ?></dd></div>
                            <div class="min-w-0"><dt class="rh-muted"><?php echo e($project['budget_source'] === 'approved' ? 'Approved budget' : ($project['budget_source'] === 'estimated' ? 'Estimated budget (fallback)' : 'Budget not recorded')); ?></dt><dd class="mt-1 break-words font-semibold tabular-nums"><?php echo e($project['budget'] !== null ? '₱'.number_format($project['budget'], 2) : '—'); ?></dd></div>
                        </dl>
                        <p class="text-xs rh-muted"><?php echo e($project['report_date'] ? 'Report date: '.$project['report_date'] : 'No submitted Monitoring Tool'); ?></p>
                    </article>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <p class="py-6 text-base rh-muted">No issued projects in this selection.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </section>
    </div>

    <section class="rh-panel p-5" aria-labelledby="project-status-heading">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 id="project-status-heading" class="rh-title">Project status analytics</h3>
            <span class="rh-badge"><?php echo e($projectTotal); ?> issued projects</span>
        </div>
        <p class="mt-2 text-sm rh-muted">Share of issued projects in each status. Click a row to view the matching projects below.</p>
        <div class="mt-5 space-y-2" data-project-distribution-chart>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $analytics['projectStatuses']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stage): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php
                    $share = $projectTotal ? round(100 * $stage['count'] / $projectTotal, 1) : 0;
                ?>
                <button type="button" wire:click="showProjects('<?php echo e($projectStatus === $stage['key'] ? '' : $stage['key']); ?>')"
                    aria-pressed="<?php echo e($projectStatus === $stage['key'] ? 'true' : 'false'); ?>"
                    aria-label="Filter <?php echo e($stage['label']); ?> projects: <?php echo e($stage['count']); ?> of <?php echo e($projectTotal); ?>"
                    class="grid w-full gap-2 rounded-lg border p-3 text-left hover:border-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand sm:grid-cols-[15rem_minmax(0,1fr)_5rem] sm:items-center <?php echo e($projectStatus === $stage['key'] ? 'border-brand bg-brand-wash dark:border-rose-400 dark:bg-rose-950/40' : 'border-transparent hover:bg-slate-50 dark:hover:bg-slate-800'); ?>">
                    <span class="text-sm font-medium"><?php echo e($stage['label']); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($projectStatus === $stage['key']): ?><span class="block text-xs font-semibold text-brand dark:text-rose-300">Selected filter</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></span>
                    <span class="block h-5 overflow-hidden rounded bg-slate-100 dark:bg-slate-800" aria-hidden="true">
                        <span class="block h-full rounded <?php echo e($stage['key'] === 'delayed' ? 'bg-red-600 dark:bg-red-400' : ($stage['key'] === 'completed' ? 'bg-emerald-600 dark:bg-emerald-400' : ($stage['key'] === 'awaiting' ? 'bg-slate-500 dark:bg-slate-400' : 'bg-brand dark:bg-rose-400'))); ?>" style="width: <?php echo e($share); ?>%"></span>
                    </span>
                    <span class="text-sm font-semibold tabular-nums sm:text-right"><?php echo e($stage['count']); ?> <span class="text-xs font-normal rh-muted">(<?php echo e($share); ?>%)</span></span>
                </button>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($projectTotal === 0): ?><p class="mt-3 text-sm rh-muted">No issued projects in this selection. Shares will appear when a Notice to Proceed is issued.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($analytics['unknownSchedules']): ?><p class="mt-3 text-sm text-amber-800 dark:text-amber-300"><?php echo e($analytics['unknownSchedules']); ?> active projects have no recorded schedule.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>

    <div class="grid items-start gap-5 xl:grid-cols-2" data-dashboard-compact-queues>
        <section id="needs-attention" class="rh-panel scroll-mt-40" aria-labelledby="attention-heading">
            <div class="rh-panel-heading">
                <h3 id="attention-heading" class="rh-title">Needs attention</h3>
                <span class="rh-badge"><?php echo e($analytics['attention']->count()); ?> issues</span>
            </div>
            <div class="overflow-x-auto">
                <table class="rh-table" data-dashboard-queue="attention">
                    <thead><tr><th scope="col" class="w-[72%]">Research / issue</th><th scope="col">Waiting</th></tr></thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $attentionItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr>
                                <td>
                                    <a href="<?php echo e($item['url']); ?>" aria-label="Open <?php echo e($item['title']); ?>: <?php echo e($item['issue']); ?>" class="line-clamp-2 break-words font-semibold hover:text-brand dark:hover:text-rose-300" title="<?php echo e($item['title']); ?>"><?php echo e($item['title']); ?></a>
                                    <span class="mt-1 block text-sm text-brand dark:text-rose-300"><?php echo e($item['issue']); ?></span>
                                    <span class="rh-muted block text-sm"><?php echo e($item['status']); ?></span>
                                </td>
                                <td>
                                    <strong class="tabular-nums"><?php echo e($item['days'] !== null ? $item['days'].' days' : '—'); ?></strong>
                                    <span class="rh-muted block text-sm"><?php echo e($item['days'] !== null ? $item['basis'] : 'No recorded date'); ?></span>
                                </td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <tr><td colspan="2"><p class="rh-muted py-7 text-center">No data yet or no items need attention in this cohort.</p></td></tr>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($attentionItems->hasPages()): ?>
                <div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800">
                    <p class="rh-muted mb-2 text-sm"><?php echo e($attentionItems->firstItem()); ?>–<?php echo e($attentionItems->lastItem()); ?> of <?php echo e($attentionItems->total()); ?> issues</p>
                    <?php echo e($attentionItems->links('livewire::simple-tailwind', ['scrollTo' => '#needs-attention'])); ?>

                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </section>

        <section id="received-proposals" tabindex="-1" class="rh-panel scroll-mt-40 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand" aria-labelledby="inbox-heading">
            <div class="rh-panel-heading">
                <h3 id="inbox-heading" class="rh-title"><?php echo e($proposalHeading); ?></h3>
                <span class="rh-badge"><?php echo e($topics->total()); ?> proposals</span>
            </div>
            <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-3 dark:border-slate-800" aria-label="Inbox controls">
                <p class="text-sm rh-muted"><?php echo e($submissionMonth ? 'Recorded submission activity for '.($academicYear ?: 'all academic years').' within the selected period.' : $cohortScope); ?></p>
                <div class="grid gap-2 sm:grid-cols-2">
                    <label for="proposal-search" class="sr-only">Search proposals</label>
                    <input id="proposal-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Search proposal or faculty" class="rh-control w-full">
                    <label for="proposal-status" class="sr-only">Review status</label>
                    <select id="proposal-status" wire:model.live="status" class="rh-control w-full">
                        <option value="">All active review stages</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $stageLabels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($value); ?>"><?php echo e($label); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </select>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pipeline || $status || $submissionMonth || $attention || $search): ?>
                    <div class="flex flex-wrap items-center gap-2">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pipeline): ?><span class="text-sm font-medium text-brand dark:text-rose-300"><?php echo e($pipeline === 'awaiting_review' ? 'Awaiting your review' : (\App\Services\ResearchHeadAnalytics::PIPELINE[$pipeline] ?? 'Selected stage')); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($attention === 'repeat'): ?><span class="text-sm font-medium text-brand dark:text-rose-300">Repeated revisions</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pipeline || $status): ?><button type="button" wire:click="clearPipeline" class="rh-button-secondary">Clear stage</button><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($submissionMonth): ?><button type="button" wire:click="showSubmissionMonth('')" class="rh-button-secondary">Clear month: <?php echo e($submissionMonth); ?></button><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <button type="button" wire:click="clearProposalFilters" class="rh-button-secondary">Clear inbox filters</button>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="overflow-x-auto">
                <table class="rh-table" data-dashboard-queue="proposals">
                    <thead><tr><th scope="col" class="w-[68%]">Proposal and lead</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $topics; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $topic): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php
                                $version = $topic->latestVersion;
                                $proposalUrl = route('topics.show', $topic).($topic->hasIssuedNoticeToProceed() ? '#project-monitoring' : '#proposal-review');
                            ?>
                            <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'proposal-row-'.e($topic->id).''; ?>wire:key="proposal-row-<?php echo e($topic->id); ?>">
                                <td>
                                    <a href="<?php echo e($proposalUrl); ?>" class="line-clamp-2 break-words font-semibold hover:text-brand dark:hover:text-rose-300" title="<?php echo e($topic->title); ?>"><?php echo e($topic->title); ?></a>
                                    <span class="rh-muted block truncate"><?php echo e($topic->user?->name); ?></span>
                                    <span class="rh-muted mt-1 block truncate text-sm" title="<?php echo e($topic->researchCall?->title ?? 'Independent submission'); ?>"><?php echo e($version ? 'v'.$version->version_number.' · '.$version->files_count.' files' : 'No submitted version'); ?><?php echo e($version?->created_at ? ' · '.$version->created_at->format('M d, Y') : ''); ?></span>
                                </td>
                                <td>
                                    <span class="rh-muted block text-sm"><?php echo e($topic->researchHeadQueueStatusLabel($version)); ?></span>
                                    <a href="<?php echo e($proposalUrl); ?>" aria-label="Open <?php echo e($topic->title); ?>" class="mt-2 inline-flex min-h-[44px] items-center gap-2 rounded-lg bg-brand-wash px-3 text-sm font-semibold text-brand hover:bg-rose-100 dark:bg-rose-950/50 dark:text-rose-300"><?php echo e($topic->status === 'ready_for_signature' ? 'Sign' : 'Open'); ?><svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-5-5 5 5-5 5"/></svg></a>
                                </td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <tr><td colspan="2"><p class="rh-muted py-7 text-center">No proposals found. Try changing the search or filters.</p></td></tr>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topics->hasPages()): ?>
                <div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800">
                    <p class="rh-muted mb-2 text-sm"><?php echo e($topics->firstItem()); ?>–<?php echo e($topics->lastItem()); ?> of <?php echo e($topics->total()); ?> proposals</p>
                    <?php echo e($topics->links('livewire::simple-tailwind', ['scrollTo' => '#received-proposals'])); ?>

                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </section>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($projectStatus === ''): ?>
        <?php if (isset($component)) { $__componentOriginal9754f437c85a92113ab47ab5936d5d71 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9754f437c85a92113ab47ab5936d5d71 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.research-head-project-results','data' => ['items' => $projectItems,'statuses' => $analytics['projectStatuses'],'scope' => $cohortScope]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('research-head-project-results'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['items' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($projectItems),'statuses' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($analytics['projectStatuses']),'scope' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($cohortScope)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9754f437c85a92113ab47ab5936d5d71)): ?>
<?php $attributes = $__attributesOriginal9754f437c85a92113ab47ab5936d5d71; ?>
<?php unset($__attributesOriginal9754f437c85a92113ab47ab5936d5d71); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9754f437c85a92113ab47ab5936d5d71)): ?>
<?php $component = $__componentOriginal9754f437c85a92113ab47ab5936d5d71; ?>
<?php unset($__componentOriginal9754f437c85a92113ab47ab5936d5d71); ?>
<?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <details data-analytics-methodology class="rh-panel p-5 text-sm leading-6 rh-muted">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold [&::-webkit-details-marker]:hidden">How these numbers are calculated<svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 11v6m0-10h.01"/></svg></summary>
        <div class="mt-4 grid gap-5 md:grid-cols-2">
            <div><h4 class="font-semibold text-slate-900 dark:text-slate-100">Filters and submissions</h4><p>KPIs and the pipeline show current status of the first-submission cohort. Revisions do not start a new cohort. Academic year uses the research call or saved year dates for independent submissions. Monthly charts count recorded events, including revisions of older proposals; missing legacy events are not invented. Calendar deadlines remain institution-wide.</p></div>
            <div><h4 class="font-semibold text-slate-900 dark:text-slate-100">Workflow and completion</h4><p>Submitted means unopened pending proposals. Approval without an issued Notice to Proceed stays in final signing. Rejected proposals are excluded from the pipeline. Completed projects stay completed; reported 100% awaits a completion decision. Other projects are checked for recorded delays or incomplete weighted milestones, missing reports/review, then ongoing status.</p></div>
            <div><h4 class="font-semibold text-slate-900 dark:text-slate-100">Annual achievement</h4><p>Institution-wide achievements include projects from earlier research calls. Projects count Notices to Proceed issued in the period. Publications count dated published journal outputs, deduplicated by normalized title; acceptance is not publication. Participation uses currently recorded faculty leads and confirmed project staff on projects issued or scheduled to overlap the period. Historical departures and completion dates are not logged, so this is recorded participation, not historical attendance.</p></div>
            <div><h4 class="font-semibold text-slate-900 dark:text-slate-100">Reported budgets</h4><p><?php echo e($analytics['budget']['reported']); ?> of <?php echo e($analytics['budget']['total']); ?> projects have usable reported figures. Utilization divides reported disbursements by the approved Notice to Proceed budget, falling back to the proposal estimate when approval has no usable budget. Only that project's latest submitted monitoring snapshot is included. Reports do not distinguish period-only from cumulative spending, so quarters are not summed. Missing or zero budgets cannot produce a percentage. Remaining-budget alerts are snapshot checks for ended/completed projects, not verified unspent funds.</p></div>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($analytics['undatedPublications']): ?><p class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-amber-900 dark:bg-amber-950/30 dark:text-amber-300"><?php echo e($analytics['undatedPublications']); ?> publication records have no exact publication date and are excluded from annual achievements.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </details>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\livewire\research-head-dashboard.blade.php ENDPATH**/ ?>