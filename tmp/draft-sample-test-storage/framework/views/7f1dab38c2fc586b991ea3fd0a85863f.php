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
        $projects = $awaitingProjects->concat($activeProjects)->concat($completedProjects)->sortByDesc('updated_at')->values();

        $filterableProjects = $projects->map(function ($topic): array {
            $category = match (true) {
                $topic->isAwaitingNoticeToProceed() => 'waiting',
                $topic->isCompletedProject() => 'completed',
                default => 'active',
            };

            return [
                'category' => $category,
                'search' => Illuminate\Support\Str::lower(implode(' ', array_filter([
                    $topic->title,
                    $topic->description,
                    $topic->researchCall?->academic_year,
                ]))),
            ];
        })->values();

        $filters = [
            ['key' => 'all', 'label' => 'All projects', 'count' => $projects->count()],
            ['key' => 'waiting', 'label' => 'Waiting', 'count' => $awaitingProjects->count()],
            ['key' => 'active', 'label' => 'Active', 'count' => $activeProjects->count()],
            ['key' => 'completed', 'label' => 'Completed', 'count' => $completedProjects->count()],
        ];
    ?>

     <?php $__env->slot('header', null, []); ?> 
        <?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['dataFacultyResearcherDashboard' => true,'title' => 'Approved Research Projects','subtitle' => 'Track deliverables, monitor progress, and complete pending work.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data-faculty-researcher-dashboard' => true,'title' => 'Approved Research Projects','subtitle' => 'Track deliverables, monitor progress, and complete pending work.']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

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

    <div
        class="-mx-4 -my-6 min-h-[calc(100vh-12rem)] bg-[#FAFBFD] px-4 py-8 text-slate-900 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8"
        data-dashboard-layout="project-list"
        x-data="{
            filter: <?php echo \Illuminate\Support\Js::from(in_array(request('status'), ['active', 'waiting', 'completed'], true) ? request('status') : 'all')->toHtml() ?>,
            query: <?php echo e(Illuminate\Support\Js::from($search)); ?>,
            items: <?php echo e(Illuminate\Support\Js::from($filterableProjects)); ?>,
            matches(category, haystack) {
                const matchesFilter = this.filter === 'all' || this.filter === category;
                const matchesSearch = haystack.includes(this.query.toLowerCase().trim());

                return matchesFilter && matchesSearch;
            },
            get visibleCount() {
                return this.items.filter((item) => this.matches(item.category, item.search)).length;
            },
        }"
    >
        <div class="mx-auto max-w-6xl space-y-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="relative w-full sm:w-80">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m2.1-5.4a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" />
                    </svg>
                    <label for="research_search" class="sr-only">Search approved projects</label>
                    <input
                        id="research_search"
                        x-model.debounce.150ms="query"
                        type="search"
                        placeholder="Search title or academic year"
                        class="block w-full rounded-lg border-slate-200 bg-white py-3 pl-9 pr-3 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-[#7A0019] focus:ring-[#7A0019]"
                    >
                </div>

                <nav class="inline-flex w-full items-center gap-1 rounded-xl border border-slate-200 bg-slate-100 p-1 sm:w-auto" aria-label="Project status filter">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $filters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $filter): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <button
                            type="button"
                            @click="filter = '<?php echo e($filter['key']); ?>'"
                            :class="filter === '<?php echo e($filter['key']); ?>' ? 'bg-gray-900 text-white shadow-sm' : 'text-gray-900 hover:bg-white'"
                            class="inline-flex min-w-0 flex-1 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg px-2.5 py-2 text-xs font-bold transition sm:flex-none"
                            :aria-pressed="filter === '<?php echo e($filter['key']); ?>'"
                        >
                            <span><?php echo e($filter['label']); ?></span>
                            <span :class="filter === '<?php echo e($filter['key']); ?>' ? 'border-white/20 bg-white/10 text-white' : 'border-slate-200 bg-slate-50 text-slate-500'" class="hidden min-w-5 items-center justify-center rounded-full border px-1.5 py-0.5 text-[9px] font-black sm:inline-flex"><?php echo e($filter['count']); ?></span>
                        </button>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </nav>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($projects->isEmpty()): ?>
                <div class="rounded-xl border border-slate-200 bg-white px-6 py-14 text-center shadow-sm">
                    <svg class="mx-auto h-9 w-9 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625A1.125 1.125 0 0 0 4.5 3.375v17.25c0 .621.504 1.125 1.125 1.125h12.75a1.125 1.125 0 0 0 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    <p class="mt-3 text-sm font-bold text-slate-700">No approved research projects yet</p>
                    <p class="mt-1 text-xs text-slate-400">Projects appear here after approval.</p>
                </div>
            <?php else: ?>
                <div class="space-y-4" aria-label="Approved research projects">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $projects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $topic): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php
                            $isWaiting = $topic->isAwaitingNoticeToProceed();
                            $isCompleted = $topic->isCompletedProject();
                            $completion = min(100, max(0, (int) ($topic->latestProgressReport?->progress_percentage ?? 0)));
                            $monitoringStatus = $topic->monitoringStatusForProgress($topic->latestProgressReport?->progress_percentage);
                            $isCompletionPending = $monitoringStatus === App\Models\TopicProposal::PROJECT_STATUS_COMPLETION_PENDING;
                            $isDelayed = $monitoringStatus === App\Models\TopicProposal::PROJECT_STATUS_DELAYED;
                            $category = $isWaiting ? 'waiting' : ($isCompleted ? 'completed' : 'active');
                            $searchableText = Illuminate\Support\Str::lower(implode(' ', array_filter([
                                $topic->title,
                                $topic->description,
                                $topic->researchCall?->academic_year,
                            ])));
                            $projectUrl = route('research.show', $topic).($isWaiting ? '#notice-to-proceed' : '#project-monitoring');

                            [$statusLabel, $statusClass, $dotClass] = match (true) {
                                $isWaiting => ['Awaiting NTP', 'border-amber-200 bg-amber-50 text-amber-800', 'bg-amber-500'],
                                $isCompleted => ['Completed', 'border-emerald-200 bg-emerald-50 text-emerald-800', 'bg-emerald-600'],
                                $isCompletionPending => ['Ready to close out', 'border-rose-200 bg-rose-50 text-[#7A0019]', 'bg-[#7A0019]'],
                                $isDelayed => ['Delayed', 'border-amber-200 bg-amber-50 text-amber-800', 'bg-amber-500'],
                                default => ['Ongoing monitoring', 'border-slate-200 bg-slate-100 text-slate-700', 'bg-emerald-500'],
                            };

                            $summary = match (true) {
                                $isWaiting => 'Approved and waiting for the Notice to Proceed. Monitoring opens after release.',
                                $isCompleted => 'Project completed. Final reports and monitoring records remain available.',
                                $isCompletionPending => 'Monitoring reached 100%. A signed terminal report is required before completion.',
                                $isDelayed => "Monitoring is delayed at {$completion}%. Open the project record to document the delay and accomplishment.",
                                $topic->latestProgressReport !== null => 'Latest monitoring report is available. Open the project to review its milestones.',
                                default => 'Monitoring is open. No progress report has been recorded yet.',
                            };

                            $actionLabel = match (true) {
                                $isWaiting => 'View status',
                                $isCompleted => 'View archive',
                                $isCompletionPending => 'Submit terminal report',
                                default => 'View milestones',
                            };
                        ?>

                        <article
                            x-show="matches('<?php echo e($category); ?>', <?php echo e(Illuminate\Support\Js::from($searchableText)); ?>)"
                            x-transition.opacity.duration.150ms
                            data-project-status="<?php echo e($topic->project_status); ?>"
                            class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition duration-150 hover:border-rose-200 hover:shadow-md sm:px-6 sm:py-5"
                        >
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between sm:gap-6">
                                <div class="min-w-0 flex-1 space-y-3">
                                    <div data-project-metadata class="flex flex-wrap items-center gap-x-3 gap-y-3">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="whitespace-nowrap text-xs font-bold tabular-nums text-slate-500">Project #<?php echo e($topic->id); ?></span>
                                            <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-md border px-2.5 py-1 text-xs font-bold <?php echo e($statusClass); ?>">
                                                <span class="h-1.5 w-1.5 rounded-full <?php echo e($dotClass); ?>" aria-hidden="true"></span>
                                                <?php echo e($statusLabel); ?>

                                            </span>
                                            <span class="whitespace-nowrap text-xs font-semibold text-slate-500">A.Y. <?php echo e($topic->researchCall?->academic_year ?: 'Not provided'); ?></span>
                                        </div>
                                        <span class="whitespace-nowrap text-sm font-bold tabular-nums text-slate-700"><?php echo e($topic->estimated_budget !== null ? 'PHP '.number_format((float) $topic->estimated_budget, 2) : 'Budget not provided'); ?></span>
                                    </div>

                                    <h3 class="text-lg font-bold leading-snug text-slate-950 transition-colors group-hover:text-[#7A0019] sm:text-xl">
                                        <a href="<?php echo e($projectUrl); ?>" class="focus:outline-none focus-visible:underline focus-visible:underline-offset-4"><?php echo e($topic->title); ?></a>
                                    </h3>

                                    <div class="flex flex-col gap-2">
                                        <div data-project-progress-summary class="flex items-start justify-between gap-3">
                                            <p class="min-w-0 flex-1 text-sm leading-5 text-slate-500"><?php echo e($summary); ?></p>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $isWaiting && ! $isCompleted): ?>
                                                <span data-project-progress-percent class="shrink-0 text-sm font-bold leading-5 tabular-nums text-gray-900"><?php echo e($completion); ?>%</span>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $isWaiting && ! $isCompleted): ?>
                                            <div data-project-progress class="w-full overflow-hidden rounded-full bg-red-100 dark:bg-red-950/50" role="progressbar" aria-label="Reported project completion" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo e($completion); ?>">
                                                <div class="h-2.5 rounded-full bg-gradient-to-r from-red-300 via-red-500 to-red-700 dark:from-red-800 dark:via-red-600 dark:to-red-400" style="width: <?php echo e($completion); ?>%"></div>
                                            </div>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                </div>

                                <div data-project-action class="flex shrink-0 items-center justify-center border-t border-slate-100 pt-3 sm:min-w-40 sm:border-0 sm:pt-0">
                                    <a href="<?php echo e($projectUrl); ?>" class="inline-flex min-h-11 items-center justify-center whitespace-nowrap rounded-lg border border-[#7A0019] bg-[#7A0019] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#650015] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#7A0019] focus-visible:ring-offset-2">
                                        <?php echo e($actionLabel); ?>

                                        <span class="ml-1" aria-hidden="true">&rarr;</span>
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                    <div x-cloak x-show="visibleCount === 0" class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center shadow-sm">
                        <svg class="mx-auto h-8 w-8 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625A1.125 1.125 0 0 0 4.5 3.375v17.25c0 .621.504 1.125 1.125 1.125h12.75a1.125 1.125 0 0 0 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        <p class="mt-2 text-xs font-bold text-slate-700">No matching research projects</p>
                        <button type="button" @click="query = ''; filter = 'all'" class="mt-2 text-xs font-bold text-[#7A0019] hover:underline hover:underline-offset-4">Clear filters</button>
                    </div>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/research/index.blade.php ENDPATH**/ ?>