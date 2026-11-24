<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['items', 'statuses', 'filter' => '', 'scope' => 'All academic years']));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['items', 'statuses', 'filter' => '', 'scope' => 'All academic years']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $title = match ($filter) {
        'active' => 'Active projects',
        'delayed' => 'Delayed / overdue projects',
        'completed' => 'Completed projects',
        'ongoing' => 'Ongoing projects',
        'awaiting' => 'Projects awaiting required report / review',
        default => 'All issued projects',
    };
?>
<section id="active-projects" tabindex="-1" class="rh-panel scroll-mt-40 p-5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand" aria-labelledby="projects-heading" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'analytics-project-results'; ?>wire:key="analytics-project-results">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 id="projects-heading" class="rh-title"><?php echo e($title); ?></h3>
            <p class="mt-1 text-sm rh-muted" role="status"><?php echo e($items->total()); ?> matching <?php echo e(str('project')->plural($items->total())); ?> · <?php echo e($scope); ?></p>
        </div>
        <div class="flex w-full min-w-0 flex-wrap gap-2 sm:w-auto">
            <label for="project-status" class="sr-only">Project status filter</label>
            <select id="project-status" wire:model.live="projectStatus" class="rh-control w-full sm:w-auto sm:max-w-xs">
                <option value="">All issued projects</option><option value="active">Active projects</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $statuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stage): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($stage['key']); ?>"><?php echo e($stage['label']); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </select>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($filter): ?><button type="button" wire:click="showProjects('')" class="rh-button-secondary">Clear project filter</button><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
    <p class="mt-3 text-sm rh-muted"><?php echo e($filter === 'active' ? 'Issued projects that have not been marked completed, including delayed projects and projects awaiting reports or review.' : ($filter === 'delayed' ? 'Unfinished projects with a recorded delay or a passed project deadline or incomplete reported milestone.' : 'Current monitoring health for issued projects in the selected submission cohort.')); ?></p>
    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <article class="flex min-w-0 flex-col gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-800" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'analytics-project-'.e($project['id']).''; ?>wire:key="analytics-project-<?php echo e($project['id']); ?>">
                <h4 class="break-words font-semibold"><?php echo e($project['title']); ?></h4>
                <p class="break-words text-sm rh-muted">Lead: <?php echo e($project['lead']); ?></p>
                <strong class="text-sm <?php echo e($project['status'] === 'delayed' ? 'text-red-700 dark:text-red-300' : 'text-brand dark:text-rose-300'); ?>"><?php echo e($statuses->firstWhere('key', $project['status'])['label'] ?? str($project['status'])->headline()); ?></strong>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($project['delay_reason']): ?>
                    <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 dark:bg-red-950/30 dark:text-red-200"><?php echo e($project['delay_reason']); ?><span class="mt-1 block font-semibold"><?php echo e($project['delay_days'] !== null ? $project['delay_days'].' '.str('day')->plural($project['delay_days']).' overdue' : 'Delay start date not recorded'); ?></span></p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $project['has_schedule']): ?><p class="text-sm text-amber-800 dark:text-amber-300">Schedule not recorded; deadline cannot be checked.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="rh-muted">Project ends</dt><dd class="mt-1 font-medium"><?php echo e($project['deadline'] ?? 'Not recorded'); ?></dd></div>
                    <div><dt class="rh-muted">Reported completion</dt><dd class="mt-1 font-medium tabular-nums"><?php echo e($project['progress'] !== null ? $project['progress'].'%' : 'Not reported'); ?></dd></div>
                </dl>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($project['progress'] !== null): ?>
                    <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" role="progressbar" aria-label="<?php echo e($project['title']); ?> reported completion" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo e(min(100, max(0, $project['progress']))); ?>"><div class="h-full rounded-full bg-brand dark:bg-rose-400" style="width: <?php echo e(min(100, max(0, $project['progress']))); ?>%"></div></div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $project['issues']->reject(fn ($issue) => $issue['type'] === 'delayed')->take(2); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $issue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <a href="<?php echo e($issue['url']); ?>" class="rh-button-secondary !h-auto !justify-start whitespace-normal break-words !text-left"><?php echo e($issue['issue']); ?></a>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <p class="text-sm rh-muted"><?php echo e($project['report_date'] ? 'Latest Monitoring Tool: '.$project['report_date'] : 'No monitoring tool submitted'); ?></p>
                <a href="<?php echo e(route('topics.show', $project['id'])); ?>#project-monitoring" aria-label="<?php echo e($project['status'] === 'completed' ? 'Open project archive' : 'Inspect monitoring'); ?>: <?php echo e($project['title']); ?>" class="rh-button-secondary mt-auto self-start"><?php echo e($project['status'] === 'completed' ? 'Open project archive' : 'Inspect monitoring'); ?> <span aria-hidden="true">&rarr;</span></a>
            </article>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <p class="py-6 text-center rh-muted md:col-span-2 xl:col-span-3">No projects match this status in the selected cohort. Choose another status or adjust the academic year and submission dates.</p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($items->hasPages()): ?>
        <div class="mt-4"><p class="mb-2 text-sm rh-muted"><?php echo e($items->firstItem()); ?>–<?php echo e($items->lastItem()); ?> of <?php echo e($items->total()); ?> projects</p><?php echo e($items->links('livewire::simple-tailwind', ['scrollTo' => '#active-projects'])); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</section>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\research-head-project-results.blade.php ENDPATH**/ ?>