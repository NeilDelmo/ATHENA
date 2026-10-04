<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['topic', 'draft' => null, 'defaults' => [], 'evidence' => [], 'standalone' => false]));

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

foreach (array_filter((['topic', 'draft' => null, 'defaults' => [], 'evidence' => [], 'standalone' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $data = app(\App\Support\TerminalReportData::class)->normalize($topic, [...array_replace($defaults, $draft?->source_data ?? []), 'accomplishments' => old('accomplishments', $draft?->source_data['accomplishments'] ?? $defaults['accomplishments'] ?? [])]);
    $terminal = $data['terminal_data'] ?? [];
    $value = fn ($key, $fallback = '') => old($key, data_get($data, $key, $fallback));
    $input = 'mt-2 block min-h-12 w-full rounded-xl border-gray-300 bg-white px-3 py-2.5 text-base text-gray-950 shadow-sm transition placeholder:text-gray-400 focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-800 dark:text-white';
    $authors = $value('terminal_data.authors', []);
    $accomplishments = $data['accomplishments'] ?: [['objective' => '', 'target' => '', 'actual' => '']];
    $selectedCover = $value('reuse_cover_image');
    $coverPreview = $evidence[$selectedCover]['preview_url'] ?? '';
    $usedFigureSlots = collect(range(1, 30))
        ->filter(fn (int $index): bool => filled($value('reuse_photo_'.$index)) || filled($value('photo_caption_'.$index)))
        ->max();
    $initialFigureCount = max(1, (int) ($usedFigureSlots ?: 1));
    $monitoringSources = collect($defaults['monitoring_reference'] ?? []);
    $missingPeriods = collect($defaults['missing_report_periods'] ?? []);
    $objectivesFromWorkPlan = (bool) ($defaults['objectives_from_work_plan'] ?? false);
    $objectiveCount = count($accomplishments);
    $evidenceCount = count($evidence);
    $schedule = app(\App\Services\MonitoringQuarterService::class);
    $submissionOpen = $schedule->canSubmitTerminal($topic);
    $submissionOpensAt = $schedule->terminalOpensAt($topic)->format('M j, Y');
?>

<section
    class="bg-slate-50/70 p-4 sm:p-6 lg:p-8"
    data-narrative-progress-autosave="true"
    x-data="narrativeProgressReportForm({previewUrl: <?php echo \Illuminate\Support\Js::from(route('project-narrative-reports.preview', $topic))->toHtml() ?>, draftSaveUrl: <?php echo \Illuminate\Support\Js::from(route('project-narrative-reports.draft', $topic))->toHtml() ?>, initialDraftVersion: <?php echo \Illuminate\Support\Js::from((int) ($draft?->lock_version ?? 0))->toHtml() ?>, csrfToken: <?php echo \Illuminate\Support\Js::from(csrf_token())->toHtml() ?>, submissionOpen: <?php echo \Illuminate\Support\Js::from($submissionOpen)->toHtml() ?>, submissionOpensAt: <?php echo \Illuminate\Support\Js::from($submissionOpensAt)->toHtml() ?>})"
>
    <form
        x-ref="form"
        data-narrative-progress-autosave-form
        method="POST"
        action="<?php echo e(route('project-narrative-reports.prepare', $topic)); ?>"
        enctype="multipart/form-data"
        class="mx-auto max-w-6xl space-y-8 text-base leading-7 text-gray-800 dark:text-slate-200 <?php echo e($standalone ? 'pb-44 sm:pb-32' : ''); ?>"
        @submit="if (!submissionOpen) { $event.preventDefault() } else { submitting = true }"
    >
        <?php echo csrf_field(); ?>
        <input type="hidden" name="report_type" value="terminal">
        <input type="hidden" name="draft_version" value="<?php echo e($draft?->lock_version ?? 0); ?>">
        <?php if (isset($component)) { $__componentOriginale42ee907cd5bebce7126d7c9b69e1d93 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale42ee907cd5bebce7126d7c9b69e1d93 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-autosave-status','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-autosave-status'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale42ee907cd5bebce7126d7c9b69e1d93)): ?>
<?php $attributes = $__attributesOriginale42ee907cd5bebce7126d7c9b69e1d93; ?>
<?php unset($__attributesOriginale42ee907cd5bebce7126d7c9b69e1d93); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale42ee907cd5bebce7126d7c9b69e1d93)): ?>
<?php $component = $__componentOriginale42ee907cd5bebce7126d7c9b69e1d93; ?>
<?php unset($__componentOriginale42ee907cd5bebce7126d7c9b69e1d93); ?>
<?php endif; ?>
        <p data-report-submission-lock x-show="!submissionOpen" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-base font-semibold text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">Fill and save this draft now. PDF preparation and submission open <?php echo e($submissionOpensAt); ?>.</p>

        <header class="overflow-hidden rounded-3xl border border-red-100 bg-gradient-to-br from-red-50 via-white to-amber-50 shadow-sm dark:border-red-950 dark:from-slate-900 dark:via-slate-900 dark:to-red-950/30">
            <div class="grid gap-6 px-6 py-7 sm:px-8 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-red-700 dark:text-red-300">Terminal report workspace</p>
                    <h2 class="mt-2 max-w-3xl text-3xl font-black leading-tight tracking-tight text-slate-950 dark:text-white sm:text-4xl">Assemble the final project record</h2>
                    <p class="mt-3 max-w-3xl text-base leading-7 text-slate-600 dark:text-slate-300">Approved objectives stay connected to the Work Plan. Quarterly accomplishments, report evidence, final findings, and the project poster are brought together here.</p>
                </div>
                <div class="rounded-2xl border border-white bg-white/90 px-5 py-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <p class="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">Official form</p>
                    <p class="mt-1 text-lg font-black text-slate-950 dark:text-white">BatStateU-REC-RES-04</p>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Revision 02</p>
                </div>
            </div>
            <nav aria-label="Terminal report sections" class="grid border-t border-red-100 bg-white/70 sm:grid-cols-3 dark:border-red-950 dark:bg-slate-900/70">
                <a href="#terminal-accomplishments" class="flex min-h-14 items-center justify-center gap-2 border-b border-red-100 px-4 py-3 text-sm font-bold text-slate-700 transition hover:bg-red-50 hover:text-red-800 sm:border-b-0 sm:border-r dark:border-red-950 dark:text-slate-200 dark:hover:bg-red-950/40">1. Confirm outcomes</a>
                <a href="#terminal-cover" class="flex min-h-14 items-center justify-center gap-2 border-b border-red-100 px-4 py-3 text-sm font-bold text-slate-700 transition hover:bg-red-50 hover:text-red-800 sm:border-b-0 sm:border-r dark:border-red-950 dark:text-slate-200 dark:hover:bg-red-950/40">2. Add project poster</a>
                <a href="#terminal-figures" class="flex min-h-14 items-center justify-center gap-2 px-4 py-3 text-sm font-bold text-slate-700 transition hover:bg-red-50 hover:text-red-800 dark:text-slate-200 dark:hover:bg-red-950/40">3. Attach evidence</a>
            </nav>
        </header>

        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" aria-label="Terminal report source summary">
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs font-black uppercase tracking-wider text-slate-400">Approved objectives</p>
                <p class="mt-2 text-2xl font-black tabular-nums text-slate-950 dark:text-white"><?php echo e($objectiveCount); ?></p>
                <p class="mt-1 text-xs text-slate-500">Carried from the approved Work Plan</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs font-black uppercase tracking-wider text-slate-400">Monitoring sources</p>
                <p class="mt-2 text-2xl font-black tabular-nums text-slate-950 dark:text-white"><?php echo e($monitoringSources->count()); ?></p>
                <p class="mt-1 text-xs text-slate-500">Quarterly records combined below</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs font-black uppercase tracking-wider text-slate-400">Reusable images</p>
                <p class="mt-2 text-2xl font-black tabular-nums text-slate-950 dark:text-white"><?php echo e($evidenceCount); ?></p>
                <p class="mt-1 text-xs text-slate-500">From earlier progress reports</p>
            </article>
            <article class="rounded-2xl border p-4 shadow-sm <?php echo e($missingPeriods->isEmpty() ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/30' : 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/30'); ?>">
                <p class="text-xs font-black uppercase tracking-wider <?php echo e($missingPeriods->isEmpty() ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300'); ?>">Reporting readiness</p>
                <p class="mt-2 text-lg font-black text-slate-950 dark:text-white"><?php echo e($missingPeriods->isEmpty() ? 'Ready to prepare' : $missingPeriods->count().' period(s) missing'); ?></p>
                <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">Drafting remains available at any time</p>
            </article>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900" aria-labelledby="terminal-data-flow-heading">
            <h3 id="terminal-data-flow-heading" class="text-sm font-black text-slate-950 dark:text-white">How the final report is assembled</h3>
            <div class="mt-4 grid gap-3 md:grid-cols-4">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [['1', 'Approved proposal', 'Introduction, rationale, methods, objectives'], ['2', 'Work Plan', 'Locked objectives and target outputs'], ['3', 'Monitoring records', 'Quarterly accomplishments and evidence'], ['4', 'Terminal report', 'Final results, conclusions, poster, and signatures']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$number, $title, $description]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="flex gap-3 rounded-xl bg-slate-50 p-3 dark:bg-slate-800">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-red-700 text-xs font-black text-white"><?php echo e($number); ?></span>
                        <div><p class="text-sm font-black text-slate-900 dark:text-white"><?php echo e($title); ?></p><p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"><?php echo e($description); ?></p></div>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        </section>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->narrativeProgress->any()): ?>
            <div role="alert" class="rounded-2xl border border-red-200 bg-red-50 p-5 text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-100">
                <p class="font-bold">Please review these fields:</p>
                <ul class="mt-2 list-disc space-y-1 pl-6"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->narrativeProgress->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><li><?php echo e($error); ?></li><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></ul>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5 text-blue-950 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-100">
            Review the carried-over proposal content and confirm the final dates, spending, and findings. Image files are not autosaved, so choose new uploads again if you leave before preparing the report.
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($missingPeriods->isNotEmpty()): ?>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-950 dark:border-amber-900 dark:bg-amber-950">
                Before preparing the official copy, complete <?php echo e($missingPeriods->implode(', ')); ?>. You can still save and preview this draft.
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <section class="rounded-2xl border border-gray-200 bg-gray-50 dark:border-slate-700 dark:bg-slate-800/50" x-data="{ open: false }">
            <button
                type="button"
                class="flex min-h-14 w-full items-center justify-between gap-4 rounded-2xl px-5 py-4 text-left font-bold text-gray-950 transition hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 dark:text-white dark:hover:bg-slate-800"
                @click="open = !open"
                :aria-expanded="open.toString()"
                aria-controls="terminal-monitoring-reference"
            >
                <span>
                    Earlier monitoring information
                    <span class="mt-1 block font-normal text-gray-600 dark:text-slate-300">Quarterly accomplishments are prefilled into the matching Work Plan objectives. Open this only when you need the detailed source records.</span>
                </span>
                <svg aria-hidden="true" class="h-6 w-6 shrink-0 transition-transform" :class="open && 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div id="terminal-monitoring-reference" x-show="open" x-cloak class="border-t border-gray-200 px-5 pb-5 dark:border-slate-700">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $defaults['monitoring_reference'] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $source): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <article class="mt-5 space-y-3 border-b border-gray-200 pb-5 last:border-0 dark:border-slate-700">
                        <h4 class="text-xl font-bold text-gray-950 dark:text-white"><?php echo e($source['period']); ?></h4>
                        <p class="whitespace-pre-line"><?php echo e($source['accomplishments']); ?></p>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $source['work_plan'] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $activity): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <p><strong><?php echo e($activity['activity'] ?? ''); ?>:</strong> <?php echo e($activity['actual_accomplishment'] ?? ''); ?> <?php echo e($activity['findings'] ?? ''); ?></p>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $source['budget_utilization'] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $budget): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <p class="text-gray-600 dark:text-slate-300"><?php echo e($budget['type'] ?? ''); ?> — <?php echo e($budget['details'] ?? ''); ?>: ₱<?php echo e(number_format((float) ($budget['actual_amount'] ?? 0), 2)); ?></p>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </article>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <p class="pt-5">No submitted monitoring information is available yet.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </section>

        <section class="space-y-6" aria-labelledby="terminal-project-details">
            <div class="border-b border-slate-200 pb-3 dark:border-white">
                <p class="font-semibold uppercase tracking-[0.16em] text-red-700 dark:text-red-300">Sections I–II</p>
                <h3 id="terminal-project-details" class="text-2xl font-bold text-gray-950 dark:text-white">Cover and project details</h3>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-slate-700 dark:bg-slate-800/50">
                <p class="text-2xl font-bold text-gray-950 dark:text-white"><?php echo e($terminal['project_title'] ?? $topic->title); ?></p>
                <div class="mt-3 grid gap-2 text-gray-600 sm:grid-cols-2 dark:text-slate-300">
                    <p>Approved period: <?php echo e($terminal['approved_start'] ?? 'Not recorded'); ?> – <?php echo e($terminal['approved_end'] ?? 'Not recorded'); ?> (<?php echo e($terminal['approved_duration_months'] ?? $topic->estimated_duration_months); ?> months)</p>
                    <p>Approved budget: ₱<?php echo e(number_format((float) ($terminal['approved_budget'] ?? $topic->estimated_budget), 2)); ?></p>
                </div>
            </div>

            <section
                id="terminal-cover"
                data-terminal-cover-image
                class="scroll-mt-24 overflow-hidden rounded-3xl border border-gray-300 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900"
                x-data="{ previewUrl: <?php echo \Illuminate\Support\Js::from($coverPreview)->toHtml() ?>, selected: <?php echo \Illuminate\Support\Js::from($selectedCover)->toHtml() ?>, evidence: <?php echo \Illuminate\Support\Js::from($evidence)->toHtml() ?> }"
            >
                <div class="grid lg:grid-cols-[minmax(0,1.1fr)_minmax(320px,.9fr)]">
                    <div class="relative flex min-h-80 items-center justify-center overflow-hidden bg-slate-100 p-6 dark:bg-slate-950">
                        <img x-show="previewUrl" :src="previewUrl" :alt="$refs.coverCaption?.value || 'Terminal report cover poster preview'" class="max-h-[28rem] w-full rounded-xl object-contain shadow-2xl">
                        <div x-show="!previewUrl" class="max-w-sm text-center text-slate-500 dark:text-slate-300">
                            <svg aria-hidden="true" class="mx-auto h-14 w-14 text-red-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                            <p class="mt-4 text-2xl font-black tracking-tight text-slate-900 dark:text-white">Front-cover poster</p>
                            <p class="mt-2">Add the project poster, featured output, or strongest visual from the completed study.</p>
                        </div>
                    </div>
                    <div class="space-y-5 p-5 sm:p-6">
                        <div>
                            <h4 class="text-2xl font-bold text-gray-950 dark:text-white">Cover image</h4>
                            <p class="mt-1 text-gray-600 dark:text-slate-300">JPG or PNG, up to 10 MB. Landscape images work best on the cover.</p>
                        </div>
                        <label class="block font-bold">
                            Upload a new image
                            <input
                                type="file"
                                name="cover_image"
                                accept=".jpg,.jpeg,.png"
                                class="<?php echo e($input); ?> cursor-pointer file:mr-4 file:rounded-lg file:border-0 file:bg-red-700 file:px-4 file:py-2 file:font-bold file:text-white hover:file:bg-red-800"
                                @change="if ($event.target.files[0]) { previewUrl = URL.createObjectURL($event.target.files[0]); selected = ''; }"
                            >
                        </label>
                        <label class="block font-bold">
                            Or reuse earlier evidence
                            <select
                                x-model="selected"
                                name="reuse_cover_image"
                                class="<?php echo e($input); ?>"
                                @change="if (evidence[selected]) { previewUrl = evidence[selected].preview_url; $refs.coverCaption.value = evidence[selected].caption || $refs.coverCaption.value; $refs.coverCaption.dispatchEvent(new Event('input', { bubbles: true })); } else { previewUrl = ''; }"
                            >
                                <option value="">No earlier image selected</option>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $evidence; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $photo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($key); ?>"><?php echo e($photo['label']); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </select>
                        </label>
                        <label class="block font-bold">
                            Cover caption and image description
                            <input x-ref="coverCaption" name="cover_image_caption" value="<?php echo e($value('cover_image_caption')); ?>" maxlength="200" class="<?php echo e($input); ?>" placeholder="Describe what the image shows">
                        </label>
                    </div>
                </div>
            </section>

            <div class="grid gap-5 sm:grid-cols-2">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['submission_date' => 'Submission date', 'implementation_start' => 'Actual start date', 'implementation_end' => 'Actual completion date']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <label class="font-bold"><?php echo e($label); ?><input type="date" name="<?php echo e($field); ?>" value="<?php echo e($value($field)); ?>" max="<?php echo e(now()->toDateString()); ?>" required class="<?php echo e($input); ?>"></label>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <label class="font-bold">Tracking number <span class="font-normal text-gray-500">(optional)</span><input name="tracking_number" value="<?php echo e($value('tracking_number')); ?>" maxlength="100" class="<?php echo e($input); ?>"></label>
                <div x-data="{ spent: <?php echo \Illuminate\Support\Js::from($value('terminal_data.total_expenditure'))->toHtml() ?>, budget: <?php echo \Illuminate\Support\Js::from((float) ($terminal['approved_budget'] ?? $topic->estimated_budget))->toHtml() ?> }">
                    <label class="font-bold">Final total expenditure (₱)<input type="number" min="0" max="<?php echo e((float) ($terminal['approved_budget'] ?? $topic->estimated_budget ?? 0)); ?>" step="0.01" name="terminal_data[total_expenditure]" x-model="spent" required class="<?php echo e($input); ?>"><span class="mt-1 block text-xs font-normal text-gray-500 dark:text-slate-400">Must not exceed the approved project budget of ₱<?php echo e(number_format((float) ($terminal['approved_budget'] ?? $topic->estimated_budget ?? 0), 2)); ?>.</span></label>
                    <p class="mt-2 font-semibold text-red-700 dark:text-red-300" x-text="budget > 0 && spent !== '' ? 'Budget utilization: ' + (Number(spent) / budget * 100).toFixed(2) + '%' : 'Budget utilization: N/A'"></p>
                    <p class="mt-1 text-gray-500 dark:text-slate-400">Confirm the final total; do not add repeated cumulative monitoring amounts together.</p>
                </div>
                <label class="font-bold">Collaborating agency <span class="font-normal text-gray-500">(if any)</span><input name="terminal_data[collaborating_agency]" value="<?php echo e($value('terminal_data.collaborating_agency')); ?>" maxlength="1000" placeholder="None" class="<?php echo e($input); ?>"></label>
            </div>

            <div class="space-y-4" x-data="{ authors: <?php echo \Illuminate\Support\Js::from($authors)->toHtml() ?> }">
                <div>
                    <h4 class="text-xl font-bold text-gray-950 dark:text-white">Authors and prepared-by signatures</h4>
                    <p class="mt-1 text-gray-600 dark:text-slate-300">Names populate the cover, author list, and signature blocks. Leave signing dates blank until signed.</p>
                </div>
                <template x-for="(author, index) in authors" :key="index">
                    <article class="grid gap-4 rounded-2xl border border-gray-200 p-5 sm:grid-cols-2 lg:grid-cols-3 dark:border-slate-700">
                        <template x-for="field in ['name', 'rank', 'campus', 'college']" :key="field">
                            <label class="font-bold"><span x-text="field === 'rank' ? 'Academic rank' : field.charAt(0).toUpperCase() + field.slice(1)"></span><input :name="`terminal_data[authors][${index}][${field}]`" x-model="author[field]" :required="field === 'name'" maxlength="255" class="<?php echo e($input); ?>"></label>
                        </template>
                        <label class="font-bold">Project role<select :name="`terminal_data[authors][${index}][role]`" x-model="author.role" class="<?php echo e($input); ?>"><option>Project Leader</option><option>Project Staff</option></select></label>
                        <label class="font-bold">Date signed <span class="font-normal text-gray-500">(optional)</span><input type="date" :name="`terminal_data[authors][${index}][date_signed]`" x-model="author.date_signed" max="<?php echo e(now()->toDateString()); ?>" class="<?php echo e($input); ?>"></label>
                        <button type="button" @click="authors.splice(index, 1); $dispatch('input')" :disabled="authors.length === 1" class="min-h-11 justify-self-start rounded-xl border border-red-200 px-4 py-2 font-bold text-red-700 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950">Remove author</button>
                    </article>
                </template>
                <button type="button" @click="authors.push({name:'',rank:'',campus:'',college:'',role:'Project Staff',date_signed:''}); $dispatch('input')" :disabled="authors.length >= 30" class="min-h-12 rounded-xl bg-red-700 px-5 py-3 font-bold text-white transition hover:bg-red-800 disabled:opacity-40">Add another author</button>
            </div>
        </section>

        <section id="terminal-accomplishments" data-approved-work-plan-objectives="<?php echo e($objectivesFromWorkPlan ? 'true' : 'false'); ?>" class="scroll-mt-24 space-y-5" x-data="{ rows: <?php echo \Illuminate\Support\Js::from($accomplishments)->toHtml() ?> }" aria-labelledby="terminal-accomplishments-heading">
            <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-end sm:justify-between dark:border-slate-700">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.16em] text-red-700 dark:text-red-300">Section III</p>
                    <h3 id="terminal-accomplishments-heading" class="mt-1 text-2xl font-black tracking-tight text-slate-950 dark:text-white">Approved objectives and final outcomes</h3>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-300"><?php echo e($objectivesFromWorkPlan ? 'Objectives and targets are locked to the approved Work Plan. Quarterly accomplishments are combined automatically; review and refine only the final outcome.' : 'No structured approved Work Plan was found, so objectives can be entered manually.'); ?></p>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($objectivesFromWorkPlan): ?>
                    <span class="inline-flex w-fit items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-black text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span> Synced with Work Plan
                    </span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <template x-for="(row, index) in rows" :key="index">
                <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-5 py-3 dark:border-slate-700 dark:bg-slate-800">
                        <p class="text-sm font-black text-slate-950 dark:text-white" x-text="'Objective ' + (index + 1)"></p>
                        <span x-show="<?php echo \Illuminate\Support\Js::from($objectivesFromWorkPlan)->toHtml() ?>" class="text-xs font-bold text-emerald-700 dark:text-emerald-300">Approved source</span>
                    </div>
                    <div class="grid gap-4 p-5 lg:grid-cols-[1fr_1fr_1.25fr]">
                        <template x-for="field in ['objective','target','actual']" :key="field">
                            <label class="text-sm font-bold text-slate-700 dark:text-slate-200">
                                <span x-text="field === 'objective' ? 'Approved objective' : (field === 'target' ? 'Target output' : 'Final actual accomplishment')"></span>
                                <textarea :name="`accomplishments[${index}][${field}]`" x-model="row[field]" :maxlength="field === 'objective' ? 1000 : 2000" :readonly="<?php echo \Illuminate\Support\Js::from($objectivesFromWorkPlan)->toHtml() ?> && field !== 'actual'" required rows="6" class="<?php echo e($input); ?>" :class="<?php echo \Illuminate\Support\Js::from($objectivesFromWorkPlan)->toHtml() ?> && field !== 'actual' ? 'cursor-not-allowed border-slate-200 bg-slate-50 text-slate-600 shadow-none dark:bg-slate-800' : ''"></textarea>
                                <span x-show="field === 'actual'" class="mt-1 block text-xs font-normal text-slate-500">Quarterly entries are prefilled with their reporting period. Edit this into the final concise result.</span>
                            </label>
                        </template>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($objectivesFromWorkPlan)): ?>
                        <div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800"><button type="button" @click="rows.splice(index, 1); $dispatch('input')" :disabled="rows.length === 1" class="min-h-10 rounded-xl border border-red-200 px-4 py-2 text-sm font-bold text-red-700 hover:bg-red-50 disabled:opacity-40 dark:border-red-900 dark:text-red-300">Remove objective</button></div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </article>
            </template>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($objectivesFromWorkPlan)): ?>
                <button type="button" @click="rows.push({objective:'',target:'',actual:''}); $dispatch('input')" :disabled="rows.length >= 30" class="min-h-12 rounded-xl bg-red-700 px-5 py-3 font-bold text-white transition hover:bg-red-800 disabled:opacity-40">Add another objective</button>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </section>

        <section class="space-y-6" aria-labelledby="terminal-narrative">
            <div class="border-b border-slate-200 pb-3 dark:border-white">
                <p class="font-semibold uppercase tracking-[0.16em] text-red-700 dark:text-red-300">Sections IV–VII</p>
                <h3 id="terminal-narrative" class="text-2xl font-bold text-gray-950 dark:text-white">Research narrative</h3>
                <p class="mt-2 text-gray-600 dark:text-slate-300">Use the larger editor to format headings, emphasis, and lists. Figures and tables can be positioned after a paragraph in Methodology or Results and Discussion.</p>
            </div>
            <div x-data="{ abstract: <?php echo \Illuminate\Support\Js::from(app(\App\Support\TerminalReportData::class)->plain($value('terminal_data.abstract')))->toHtml() ?> }">
                <label class="block text-xl font-bold text-gray-950 dark:text-white">IV. Abstract <span class="font-sans text-base font-normal text-gray-500">(200–250 words)</span><textarea name="terminal_data[abstract]" x-model="abstract" required rows="8" class="<?php echo e($input); ?>"></textarea></label>
                <p class="mt-2 font-semibold" :class="(abstract.trim() ? abstract.trim().split(/\s+/).length : 0) >= 200 && (abstract.trim() ? abstract.trim().split(/\s+/).length : 0) <= 250 ? 'text-emerald-700' : 'text-amber-700'" x-text="(abstract.trim() ? abstract.trim().split(/\s+/).length : 0) + ' words'"></p>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['introduction' => 'Introduction', 'rationale' => 'Rationale', 'terminal_data.literature_review' => 'Review of Literature', 'objectives' => 'General objective (optional)', 'methodology' => 'VI. Materials and Methods / Methodology', 'results_discussion' => 'VII. Results and Discussion', 'terminal_data.conclusions' => 'Conclusions', 'terminal_data.recommendations' => 'Recommendations', 'terminal_data.bibliography' => 'Bibliography']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <label class="block text-xl font-bold text-gray-950 dark:text-white">
                    <?php echo e($label); ?>

                    <textarea id="terminal-<?php echo e(str_replace('.', '-', $field)); ?>" name="<?php echo e(str_contains($field, '.') ? 'terminal_data['.substr($field, 14).']' : $field); ?>" <?php if (! ($field === 'objectives' && ($defaults['objectives_from_proposal'] ?? false))): ?> data-semantic-editor data-semantic-editor-size="large" <?php endif; ?> <?php if($field === 'objectives' && ($defaults['objectives_from_proposal'] ?? false)): echo 'readonly'; endif; ?> rows="9" maxlength="100000" <?php if($field !== 'objectives'): echo 'required'; endif; ?> class="<?php echo e($input); ?>"><?php echo e($field === 'objectives' && ($defaults['objectives_from_proposal'] ?? false) ? $defaults['objectives'] : $value($field)); ?></textarea>
                </label>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </section>

        <?php if (isset($component)) { $__componentOriginalcd164a0619efbb55f04982c0da8013fe = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalcd164a0619efbb55f04982c0da8013fe = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.terminal-report-tables','data' => ['tables' => $value('terminal_data.tables', [])]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('terminal-report-tables'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['tables' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($value('terminal_data.tables', []))]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalcd164a0619efbb55f04982c0da8013fe)): ?>
<?php $attributes = $__attributesOriginalcd164a0619efbb55f04982c0da8013fe; ?>
<?php unset($__attributesOriginalcd164a0619efbb55f04982c0da8013fe); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalcd164a0619efbb55f04982c0da8013fe)): ?>
<?php $component = $__componentOriginalcd164a0619efbb55f04982c0da8013fe; ?>
<?php unset($__componentOriginalcd164a0619efbb55f04982c0da8013fe); ?>
<?php endif; ?>

        <section id="terminal-figures" class="scroll-mt-24 space-y-5" x-data="{ figureCount: <?php echo \Illuminate\Support\Js::from($initialFigureCount)->toHtml() ?>, evidence: <?php echo \Illuminate\Support\Js::from($evidence)->toHtml() ?> }" aria-labelledby="terminal-figures-heading">
            <div class="flex flex-col gap-4 border-b border-slate-200 pb-4 sm:flex-row sm:items-end sm:justify-between dark:border-white">
                <div>
                    <p class="font-semibold uppercase tracking-[0.16em] text-red-700 dark:text-red-300">Visual evidence</p>
                    <h3 id="terminal-figures-heading" class="text-2xl font-bold text-gray-950 dark:text-white">Figures and inserted images</h3>
                    <p class="mt-2 max-w-3xl text-gray-600 dark:text-slate-300">Upload a JPG or PNG, or reuse evidence from an earlier report. Position 0 places the figure at the end of its section.</p>
                </div>
                <button type="button" @click="if (figureCount < 30) figureCount++" :disabled="figureCount >= 30" class="min-h-12 shrink-0 rounded-xl bg-red-700 px-5 py-3 font-bold text-white transition hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 disabled:opacity-40">＋ Add figure</button>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = range(1, 30); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php
                    $selectedFigure = $value('reuse_photo_'.$index);
                    $figurePreview = $evidence[$selectedFigure]['preview_url'] ?? '';
                ?>
                <article
                    x-show="figureCount >= <?php echo e($index); ?>"
                    x-cloak
                    class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900"
                    x-data="{ caption: <?php echo \Illuminate\Support\Js::from($value('photo_caption_'.$index))->toHtml() ?>, section: <?php echo \Illuminate\Support\Js::from($value('photo_section_'.$index, 'results_discussion'))->toHtml() ?>, selected: <?php echo \Illuminate\Support\Js::from($selectedFigure)->toHtml() ?>, previewUrl: <?php echo \Illuminate\Support\Js::from($figurePreview)->toHtml() ?> }"
                >
                    <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-5 py-4 dark:border-slate-700 dark:bg-slate-800">
                        <h4 class="text-xl font-bold text-gray-950 dark:text-white">Figure <?php echo e($index); ?></h4>
                        <span class="rounded-full bg-white px-3 py-1 font-semibold text-gray-600 ring-1 ring-gray-200 dark:bg-slate-900 dark:text-slate-300 dark:ring-slate-700">JPG / PNG</span>
                    </div>
                    <div class="grid gap-5 p-5 lg:grid-cols-[220px_1fr]">
                        <div class="flex min-h-48 items-center justify-center overflow-hidden rounded-xl border border-dashed border-gray-300 bg-gray-50 p-3 dark:border-slate-600 dark:bg-slate-800">
                            <img x-show="previewUrl" :src="previewUrl" :alt="caption || 'Figure preview'" class="max-h-52 w-full object-contain">
                            <p x-show="!previewUrl" class="text-center font-semibold text-gray-500 dark:text-slate-400">Image preview appears here</p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block font-bold">
                                Upload a new image
                                <input type="file" name="photo_<?php echo e($index); ?>" accept=".jpg,.jpeg,.png" class="<?php echo e($input); ?> cursor-pointer file:mr-4 file:rounded-lg file:border-0 file:bg-red-700 file:px-4 file:py-2 file:font-bold file:text-white hover:file:bg-red-800" @change="if ($event.target.files[0]) { previewUrl = URL.createObjectURL($event.target.files[0]); selected = ''; }">
                            </label>
                            <label class="block font-bold">
                                Or reuse earlier evidence
                                <select x-model="selected" name="reuse_photo_<?php echo e($index); ?>" class="<?php echo e($input); ?>" @change="if (evidence[selected]) { caption = evidence[selected].caption || ''; section = ['methodology','results_discussion'].includes(evidence[selected].section) ? evidence[selected].section : 'results_discussion'; previewUrl = evidence[selected].preview_url; } else { previewUrl = ''; }">
                                    <option value="">No earlier image selected</option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $evidence; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $photo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($key); ?>"><?php echo e($photo['label']); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </select>
                            </label>
                            <label class="block font-bold sm:col-span-2">Caption and image description<input name="photo_caption_<?php echo e($index); ?>" x-model="caption" maxlength="200" class="<?php echo e($input); ?>" placeholder="Explain what this figure shows"></label>
                            <label class="block font-bold">Insert in section<select name="photo_section_<?php echo e($index); ?>" x-model="section" class="<?php echo e($input); ?>"><option value="methodology">Methodology</option><option value="results_discussion">Results and Discussion</option></select></label>
                            <label class="block font-bold">After paragraph<input type="number" min="0" max="1000" name="photo_after_paragraph_<?php echo e($index); ?>" value="<?php echo e($value('photo_after_paragraph_'.$index, 0)); ?>" class="<?php echo e($input); ?>"><span class="mt-1 block font-normal text-gray-500">Use 0 to place it at the section end.</span></label>
                        </div>
                    </div>
                </article>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </section>

        <section class="space-y-5" aria-labelledby="terminal-signatories">
            <div class="border-b border-slate-200 pb-3 dark:border-white">
                <p class="font-semibold uppercase tracking-[0.16em] text-red-700 dark:text-red-300">Final approval</p>
                <h3 id="terminal-signatories" class="text-2xl font-bold text-gray-950 dark:text-white">Review and approval signatories</h3>
                <p class="mt-2 text-gray-600 dark:text-slate-300">Confirm the name for each role. Selecting a name does not apply a signature or approve the report.</p>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = \App\Support\TerminalReportRules::SIGNATORY_ROLES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => [$group, $role]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <div class="grid gap-4 rounded-2xl border border-gray-200 p-5 sm:grid-cols-2 dark:border-slate-700">
                    <?php ($defaultName = \App\Support\TerminalReportData::defaultSignatoryNames()[$key] ?? null); ?>
                    <label class="font-bold"><?php echo e($group); ?> — <?php echo e($role); ?><input name="terminal_data[signatories][<?php echo e($key); ?>][name]" value="<?php echo e($defaultName ?? $value('terminal_data.signatories.'.$key.'.name')); ?>" <?php if($defaultName): ?> readonly <?php else: ?> list="terminal-signatories" <?php endif; ?> maxlength="255" required class="<?php echo e($input); ?>"></label>
                    <label class="font-bold">Date signed <span class="font-normal text-gray-500">(optional)</span><input type="date" name="terminal_data[signatories][<?php echo e($key); ?>][date_signed]" value="<?php echo e($value('terminal_data.signatories.'.$key.'.date_signed')); ?>" max="<?php echo e(now()->toDateString()); ?>" class="<?php echo e($input); ?>"></label>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <datalist id="terminal-signatories"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $defaults['signatory_options'] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($name); ?>"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></datalist>
        </section>

        <?php if (isset($component)) { $__componentOriginalec45bcd7a9ee78c80c834ee90004f9d9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalec45bcd7a9ee78c80c834ee90004f9d9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.monitoring-action-dock','data' => ['fixed' => $standalone]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('monitoring-action-dock'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['fixed' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($standalone)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($standalone): ?>
                <?php if (isset($component)) { $__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.back-link','data' => ['dataPaperCancelExit' => true,'href' => ''.e(route('research.show', $topic)).'#project-monitoring']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('back-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data-paper-cancel-exit' => true,'href' => ''.e(route('research.show', $topic)).'#project-monitoring']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
Exit monitoring <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01)): ?>
<?php $attributes = $__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01; ?>
<?php unset($__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01)): ?>
<?php $component = $__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01; ?>
<?php unset($__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01); ?>
<?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <button type="button" @click="saveNarrativeDraft" :disabled="autoSaveInFlight || autoSaveBlocked" class="min-h-12 rounded-xl border border-gray-300 bg-white px-5 py-3 text-sm font-bold text-gray-900 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-900 dark:text-white">Save draft</button>
            <button type="button" @click="generatePreview" :disabled="!submissionOpen || previewLoading || submitting" class="min-h-12 rounded-xl border border-gray-300 px-6 py-3 font-bold text-gray-900 transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 disabled:opacity-50 dark:border-slate-600 dark:text-white dark:hover:bg-slate-800">Preview terminal report</button>
            <button type="submit" :disabled="!submissionOpen || previewLoading || submitting" class="min-h-12 rounded-xl bg-red-700 px-6 py-3 font-bold text-white transition hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 disabled:opacity-50">Prepare official PDF</button>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalec45bcd7a9ee78c80c834ee90004f9d9)): ?>
<?php $attributes = $__attributesOriginalec45bcd7a9ee78c80c834ee90004f9d9; ?>
<?php unset($__attributesOriginalec45bcd7a9ee78c80c834ee90004f9d9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalec45bcd7a9ee78c80c834ee90004f9d9)): ?>
<?php $component = $__componentOriginalec45bcd7a9ee78c80c834ee90004f9d9; ?>
<?php unset($__componentOriginalec45bcd7a9ee78c80c834ee90004f9d9); ?>
<?php endif; ?>
        <p x-show="previewError" x-text="previewError" role="alert" class="rounded-xl bg-red-50 p-4 font-semibold text-red-700"></p>
        <section x-show="previewHtml" x-cloak x-ref="previewSection" class="space-y-3">
            <p>Review this draft preview. Prepare the official copy to confirm final pagination before submission.</p>
            <iframe x-ref="previewFrame" :srcdoc="previewHtml" @load="hydratePreview" title="Terminal report preview" class="h-[75vh] w-full rounded-2xl border border-gray-300 bg-white shadow-lg"></iframe>
        </section>
    </form>
</section>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/components/terminal-report-form.blade.php ENDPATH**/ ?>