<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['attentionCounts' => []]));

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

foreach (array_filter((['attentionCounts' => []]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $isResearcher = Auth::user()->isUsingWorkspace('faculty_researcher');
    $icons = [
        'dashboard' => 'M3 3h7v7H3V3Zm11 0h7v7h-7V3ZM3 14h7v7H3v-7Zm11 0h7v7h-7v-7Z',
        'calendar' => 'M8 3v4m8-4v4M4 10h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z',
        'plus' => 'M12 4.5v15m7.5-7.5h-15',
        'document' => 'M4 3h10l6 6v12H4V3Zm10 0v6h6M8 13h8m-8 4h5',
        'projects' => 'M3.75 13.5h4.5v6.75h-4.5V13.5Zm6-4.5h4.5v11.25h-4.5V9Zm6-5.25h4.5v16.5h-4.5V3.75Z',
        'clock' => 'M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'check' => 'm8 12 3 3 5-6M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'search' => 'm21 21-5.2-5.2M18 10.5a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z',
        'shield' => 'M12 3 4 6v6c0 4.4 3.4 7.4 8 9 4.6-1.6 8-4.6 8-9V6l-8-3Zm-4 9 3 3 5-6',
        'library' => 'M4 4h4v16H4V4Zm7 0h4v16h-4V4Zm7 0 4 1-4 15-4-1 4-15Z',
    ];
    $sections = [
        'Overview' => [
            ['Dashboard', 'faculty.dashboard', [], 'dashboard', null],
            ['Calendar', 'faculty.calendar', [], 'calendar', null],
        ],
        ...($isResearcher ? [
            'Monitoring' => [
                ['My Projects', 'research.index', [], 'projects', 'my_projects'],
                ['Active projects', 'research.index', ['status' => 'active'], 'projects', null],
                ['Awaiting release', 'research.index', ['status' => 'waiting'], 'clock', null],
            ],
            'Completion' => [
                ['Completed projects', 'research.index', ['status' => 'completed'], 'check', null],
            ],
        ] : [
            'Submission' => [
                ['Research calls', 'research-calls.index', [], 'calendar', null],
                ['New proposal', 'faculty.proposal-drafts.create', [], 'plus', null],
                ['Draft proposals', 'faculty.proposal-drafts.index', [], 'document', 'proposal_workspace'],
            ],
            'Review' => [
                ['Submitted proposals', 'faculty.submissions', [], 'check', 'submitted_proposals'],
            ],
        ]),
        'Resources' => [
            ['Saved literature', 'research-support.index', [], 'library', null],
            ['Literature search', 'research-support.index', [], 'search', null],
            ['Turnitin', 'research-support.index', [], 'shield', null],
            ...($isResearcher ? [['Journal Finder', 'research-support.index', [], 'document', null]] : []),
        ],
    ];
    $resourceAnchors = [
        'Saved literature' => '#shared-literature-library',
        'Literature search' => '#rrl-finder',
        'Turnitin' => '#turnitin',
        'Journal Finder' => '#journal-finder',
    ];
    $linkClasses = 'relative flex min-h-[44px] w-full items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-white';
    $activeClasses = '!bg-brand-wash !text-brand !font-semibold before:absolute before:left-0 before:top-1/2 before:h-5 before:w-1 before:-translate-y-1/2 before:rounded-r-full before:bg-brand dark:!bg-red-950/30 dark:!text-red-200';
?>

<nav aria-label="<?php echo e($isResearcher ? 'Faculty Researcher' : 'Faculty'); ?> navigation" class="space-y-4" x-data="{ activeHash: window.location.hash || '#rrl-finder' }" @hashchange.window="activeHash = window.location.hash || '#rrl-finder'" x-on:livewire:navigated.window="activeHash = window.location.hash || '#rrl-finder'" data-faculty-navigation>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $sections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $heading => $links): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
        <section aria-label="<?php echo e($heading); ?>" <?php if($heading === 'Resources'): ?> data-research-help-menu <?php endif; ?>>
            <h2 x-show="$store.sidebar.open" class="mb-2 flex items-center gap-3 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400">
                <?php echo e($heading); ?><span class="h-px flex-1 bg-slate-200 dark:bg-slate-800" aria-hidden="true"></span>
            </h2>
            <div class="space-y-1">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $links; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $routeName, $parameters, $icon, $attentionArea]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php
                        $resourceAnchor = $resourceAnchors[$label] ?? '';
                        $url = route($routeName, $parameters).$resourceAnchor;
                        $isProjectLink = $routeName === 'research.index';
                    ?>
                    <a wire:navigate href="<?php echo e($url); ?>"
                       <?php if($isProjectLink): ?>
                           :class="[
                               $store.sidebar.open ? '' : '!justify-center !gap-0 !px-0',
                               $store.sidebar.currentPath === <?php echo \Illuminate\Support\Js::from(parse_url(route('research.index'), PHP_URL_PATH))->toHtml() ?> && (new URLSearchParams(window.location.search).get('status') || 'all') === <?php echo \Illuminate\Support\Js::from($parameters['status'] ?? 'all')->toHtml() ?> ? <?php echo \Illuminate\Support\Js::from($activeClasses)->toHtml() ?> : '',
                           ]"
                       <?php elseif($resourceAnchor): ?>
                           :class="[
                               $store.sidebar.open ? '' : '!justify-center !gap-0 !px-0',
                               $store.sidebar.currentPath === <?php echo \Illuminate\Support\Js::from(parse_url(route('research-support.index'), PHP_URL_PATH))->toHtml() ?> && activeHash === <?php echo \Illuminate\Support\Js::from($resourceAnchor)->toHtml() ?> ? <?php echo \Illuminate\Support\Js::from($activeClasses)->toHtml() ?> : '',
                           ]"
                       <?php else: ?>
                           wire:current.exact="<?php echo e($activeClasses); ?>"
                           :class="$store.sidebar.open ? '' : '!justify-center !gap-0 !px-0'"
                       <?php endif; ?>
                       <?php if($attentionArea): ?> data-sidebar-attention-url="<?php echo e(route('sidebar-attention.open', $attentionArea)); ?>" <?php endif; ?>
                       @click="if (window.innerWidth < 640) $store.sidebar.setOpen(false)"
                       aria-label="<?php echo e($label === 'Dashboard' ? ($isResearcher ? 'Faculty Researcher Dashboard' : 'Faculty Dashboard') : $label); ?>" title="<?php echo e($label); ?>" class="<?php echo e($linkClasses); ?>">
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="<?php echo e($icons[$icon]); ?>" />
                        </svg>
                        <span x-show="$store.sidebar.open" class="whitespace-nowrap"><?php echo e($label); ?></span>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($attentionArea): ?><?php if (isset($component)) { $__componentOriginal062ce1d70d70f82019a083841a7f4bf2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal062ce1d70d70f82019a083841a7f4bf2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.sidebar-attention-badge','data' => ['count' => $attentionCounts[$attentionArea] ?? 0]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('sidebar-attention-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['count' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($attentionCounts[$attentionArea] ?? 0)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal062ce1d70d70f82019a083841a7f4bf2)): ?>
<?php $attributes = $__attributesOriginal062ce1d70d70f82019a083841a7f4bf2; ?>
<?php unset($__attributesOriginal062ce1d70d70f82019a083841a7f4bf2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal062ce1d70d70f82019a083841a7f4bf2)): ?>
<?php $component = $__componentOriginal062ce1d70d70f82019a083841a7f4bf2; ?>
<?php unset($__componentOriginal062ce1d70d70f82019a083841a7f4bf2); ?>
<?php endif; ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </a>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        </section>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
</nav><?php /**PATH C:\laragon\www\athena-app\src\resources\views/components/faculty-navigation.blade.php ENDPATH**/ ?>