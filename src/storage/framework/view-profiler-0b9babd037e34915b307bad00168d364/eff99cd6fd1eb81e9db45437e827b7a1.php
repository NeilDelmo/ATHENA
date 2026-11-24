<?php
    $projectRoute = ($completedOnly ?? false) ? 'research_head.completed-projects.index' : 'research_head.projects.index';
?>
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

     <?php $__env->slot('header', null, []); ?> 
        <?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['class' => 'rh-review-page-header [&_h1]:text-3xl [&_p]:text-base [&_p]:leading-6','title' => ($completedOnly ?? false) ? 'Completed projects' : 'Research projects','subtitle' => ($completedOnly ?? false) ? 'Browse completed research, final reports, and project records.' : 'Organize approved research and review project progress.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'rh-review-page-header [&_h1]:text-3xl [&_p]:text-base [&_p]:leading-6','title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(($completedOnly ?? false) ? 'Completed projects' : 'Research projects'),'subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(($completedOnly ?? false) ? 'Browse completed research, final reports, and project records.' : 'Organize approved research and review project progress.')]); ?>
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

    <div class="rh-review-page space-y-5" x-data="projectRecordsPage({initialView: <?php echo \Illuminate\Support\Js::from($initialRecordsView)->toHtml() ?>})" data-project-monitoring-list data-project-records-page>
        <div x-cloak role="tablist" aria-label="Project views" class="flex min-h-14 flex-wrap items-center gap-1 rounded-xl border border-slate-200 bg-white p-1.5 dark:border-slate-700 dark:bg-slate-900"
            @keydown.arrow-right.prevent="recordsView = recordsView === 'files' ? 'monitoring' : 'files'; $nextTick(() => $el.querySelector('[aria-selected=true]').focus())"
            @keydown.arrow-left.prevent="recordsView = recordsView === 'files' ? 'monitoring' : 'files'; $nextTick(() => $el.querySelector('[aria-selected=true]').focus())"
            @keydown.home.prevent="recordsView = 'monitoring'; $nextTick(() => $el.querySelector('[aria-selected=true]').focus())"
            @keydown.end.prevent="recordsView = 'files'; $nextTick(() => $el.querySelector('[aria-selected=true]').focus())">
            <button id="project-monitoring-tab" type="button" role="tab" aria-controls="project-monitoring-panel" :aria-selected="recordsView === 'monitoring'" :tabindex="recordsView === 'monitoring' ? 0 : -1" @click="recordsView = 'monitoring'" :class="recordsView === 'monitoring' ? 'bg-gray-950 text-white shadow-sm dark:bg-white dark:text-gray-950' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-950 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white'" class="border border-slate-400 dark:border-slate-500 inline-flex min-h-11 shrink-0 items-center rounded-lg px-4 py-2.5 text-base font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">Monitoring</button>
            <button id="project-files-tab" type="button" role="tab" aria-controls="project-files-panel" :aria-selected="recordsView === 'files'" :tabindex="recordsView === 'files' ? 0 : -1" @click="recordsView = 'files'" :class="recordsView === 'files' ? 'bg-gray-950 text-white shadow-sm dark:bg-white dark:text-gray-950' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-950 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white'" class="border border-slate-400 dark:border-slate-500 inline-flex min-h-11 shrink-0 items-center rounded-lg px-4 py-2.5 text-base font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">Files</button>
        </div>

        <div x-cloak x-show="recordsView === 'monitoring'" class="space-y-4" data-project-monitoring-controls>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($completedOnly)): ?>
                <?php if (isset($component)) { $__componentOriginal629ab6ff68dfa7c57a7ddb83c9ae9302 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal629ab6ff68dfa7c57a7ddb83c9ae9302 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.kpi-strip','data' => ['class' => '[&_dt]:text-sm [&_dt]:leading-5 [&_dd]:!text-[28px] [&_svg]:h-4 [&_svg]:w-4','items' => [
                    ['label' => 'Ongoing', 'value' => $summary['ongoing'], 'icon' => 'play'],
                    ['label' => 'Delayed', 'value' => $summary['delayed'], 'icon' => 'alert-triangle'],
                    ['label' => 'Completion pending', 'value' => $summary['completion_pending'], 'icon' => 'hourglass'],
                    ['label' => 'Completed', 'value' => $summary['completed'], 'icon' => 'circle-check'],
                    ['label' => 'Reports awaiting review', 'value' => $summary['pending_reports'], 'icon' => 'file-text'],
                ]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('kpi-strip'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => '[&_dt]:text-sm [&_dt]:leading-5 [&_dd]:!text-[28px] [&_svg]:h-4 [&_svg]:w-4','items' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute([
                    ['label' => 'Ongoing', 'value' => $summary['ongoing'], 'icon' => 'play'],
                    ['label' => 'Delayed', 'value' => $summary['delayed'], 'icon' => 'alert-triangle'],
                    ['label' => 'Completion pending', 'value' => $summary['completion_pending'], 'icon' => 'hourglass'],
                    ['label' => 'Completed', 'value' => $summary['completed'], 'icon' => 'circle-check'],
                    ['label' => 'Reports awaiting review', 'value' => $summary['pending_reports'], 'icon' => 'file-text'],
                ])]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal629ab6ff68dfa7c57a7ddb83c9ae9302)): ?>
<?php $attributes = $__attributesOriginal629ab6ff68dfa7c57a7ddb83c9ae9302; ?>
<?php unset($__attributesOriginal629ab6ff68dfa7c57a7ddb83c9ae9302); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal629ab6ff68dfa7c57a7ddb83c9ae9302)): ?>
<?php $component = $__componentOriginal629ab6ff68dfa7c57a7ddb83c9ae9302; ?>
<?php unset($__componentOriginal629ab6ff68dfa7c57a7ddb83c9ae9302); ?>
<?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <form method="GET" action="<?php echo e(route($projectRoute)); ?>" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['grid gap-2 sm:grid-cols-2', 'xl:grid-cols-[minmax(0,1fr)_260px_auto]' => $completedOnly, 'xl:grid-cols-[minmax(0,1fr)_240px_260px_auto]' => ! $completedOnly]); ?>">
                <input type="hidden" name="view" value="monitoring">
                <label class="sr-only" for="project-search">Search project or researcher</label>
                <input id="project-search" name="search" type="search" value="<?php echo e($search); ?>" placeholder="Search project or researcher..." class="block w-full rounded-xl border-gray-200 text-base focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($completedOnly)): ?>
                    <label class="sr-only" for="project-status">Project status</label>
                    <select id="project-status" name="status" class="block w-full rounded-xl border-gray-200 text-base focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-900 dark:text-white"><option value="">All project statuses</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['ongoing' => 'Ongoing', 'delayed' => 'Delayed', 'completion_pending' => 'Completion pending', 'completed' => 'Completed']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($value); ?>" <?php if($status === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></select>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <label class="sr-only" for="project-attention">Report state</label>
                <select id="project-attention" name="attention" class="block w-full rounded-xl border-gray-200 text-base focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-900 dark:text-white"><option value="">All report states</option><option value="needs_attention" <?php if($attention === 'needs_attention'): echo 'selected'; endif; ?>>Needs attention</option><option value="pending_reports" <?php if($attention === 'pending_reports'): echo 'selected'; endif; ?>>Reports awaiting review</option></select>
                <div class="flex gap-2">
                    <button type="submit" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">Filter</button>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($search !== '' || (! $completedOnly && $status !== '') || $attention !== ''): ?>
                        <a href="<?php echo e(route($projectRoute, ['view' => 'monitoring'])); ?>" class="inline-flex min-h-11 items-center rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Clear</a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </form>
        </div>

        <div id="project-files-panel" role="tabpanel" aria-labelledby="project-files-tab" x-cloak x-show="recordsView === 'files'">
            <?php if (isset($component)) { $__componentOriginal38bfd9fcca72624e1ae91e607eb146d2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal38bfd9fcca72624e1ae91e607eb146d2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.project-records-workspace','data' => ['projects' => $workspaceProjects,'preferences' => $workspacePreferences,'completedOnly' => $completedOnly]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('project-records-workspace'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['projects' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($workspaceProjects),'preferences' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($workspacePreferences),'completed-only' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($completedOnly)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal38bfd9fcca72624e1ae91e607eb146d2)): ?>
<?php $attributes = $__attributesOriginal38bfd9fcca72624e1ae91e607eb146d2; ?>
<?php unset($__attributesOriginal38bfd9fcca72624e1ae91e607eb146d2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal38bfd9fcca72624e1ae91e607eb146d2)): ?>
<?php $component = $__componentOriginal38bfd9fcca72624e1ae91e607eb146d2; ?>
<?php unset($__componentOriginal38bfd9fcca72624e1ae91e607eb146d2); ?>
<?php endif; ?>
        </div>

        <section id="project-monitoring-panel" role="tabpanel" aria-labelledby="project-monitoring-tab" x-show="recordsView === 'monitoring'" data-project-monitoring class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-slate-800"><h3 id="project-records-heading" class="text-xl font-black text-gray-900 dark:text-white"><?php echo e(($completedOnly ?? false) ? 'Completed project records' : 'Research projects under monitoring'); ?></h3><p class="mt-1 text-sm text-gray-500 dark:text-slate-400">Review implementation status and submitted reports. Delayed projects and projects with reports awaiting review appear first.</p></div>
            <div data-project-list-layout="rows" class="overflow-hidden rounded-b-xl border-x border-b border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                <div aria-hidden="true" class="hidden grid-cols-[minmax(0,1fr)_160px_170px_220px_160px_196px] items-center gap-4 border-b border-brand bg-brand px-4 py-3 text-sm font-semibold text-white dark:border-red-900 2xl:grid">
                    <span>Project and faculty</span><span>Status</span><span>Latest progress</span><span>Project secretary</span><span>Reports</span><span class="text-right">Action</span>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-slate-800">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $projects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php
                            $latestProgressPercentage = $project->latestProgressReport?->progress_percentage;
                            $projectStatus = $project->monitoringStatusForProgress($latestProgressPercentage);
                            $projectStatusLabel = $project->monitoringStatusLabelForProgress($latestProgressPercentage);
                            $pendingReportCount = $project->pending_reports_count + $project->pending_narrative_reports_count;
                        ?>
                        <article data-project-id="<?php echo e($project->id); ?>" class="grid min-w-0 gap-3 bg-white px-4 py-3 hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800/50 sm:grid-cols-2 2xl:grid-cols-[minmax(0,1fr)_160px_170px_220px_160px_196px] 2xl:items-center 2xl:gap-4">
                            <div class="min-w-0 sm:col-span-2 2xl:col-span-1">
                                <h4 class="break-words text-base font-semibold leading-6 text-gray-900 dark:text-white"><?php echo e($project->title); ?></h4>
                                <p class="mt-1 text-sm text-gray-500 dark:text-slate-400"><?php echo e($project->user->name); ?></p>
                                <p class="mt-1 text-sm text-gray-500 dark:text-slate-400"><?php echo e($project->researchCall?->title ?? 'Independent submission'); ?></p>
                            </div>
                            <div class="min-w-0">
                                <span class="sr-only">Status:</span>
                                <span class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                                    'inline-flex max-w-full rounded-md px-2 py-1 text-sm font-medium leading-5',
                                    'bg-green-50 text-green-700 dark:bg-green-950/40 dark:text-green-300' => $projectStatus === 'completed',
                                    'bg-violet-50 text-violet-700 dark:bg-violet-950/40 dark:text-violet-300' => $projectStatus === 'completion_pending',
                                    'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300' => $projectStatus === 'delayed',
                                    'border border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200' => ! in_array($projectStatus, ['completed', 'completion_pending', 'delayed'], true),
                                ]); ?>"><?php echo e($projectStatusLabel); ?></span>
                            </div>
                            <div class="min-w-0 text-sm text-gray-500 dark:text-slate-400">
                                <span class="sr-only">Latest progress:</span>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($project->latestProgressReport): ?>
                                    <p class="text-base font-semibold text-gray-900 dark:text-white"><?php echo e($project->latestProgressReport->progress_percentage); ?>%</p>
                                    <p class="mt-1">Tool · <?php echo e($project->latestProgressReport->reporting_date->format('M d, Y')); ?></p>
                                <?php elseif($project->latestNarrativeReport): ?>
                                    <p class="font-medium text-gray-700 dark:text-slate-200">Progress report</p>
                                    <p class="mt-1"><?php echo e($project->latestNarrativeReport->submission_date->format('M d, Y')); ?></p>
                                <?php else: ?>
                                    <span>No report yet</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <div class="min-w-0">
                                <span class="sr-only">Project secretary:</span>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($project->researchSecretary): ?>
                                    <div class="flex min-w-0 items-center gap-2">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-slate-100 text-sm font-semibold text-gray-700 dark:bg-slate-800 dark:text-slate-200">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($project->researchSecretary->avatar): ?>
                                                <img src="<?php echo e($project->researchSecretary->avatar); ?>" alt="" class="h-full w-full object-cover">
                                            <?php else: ?>
                                                <?php echo e(collect(explode(' ', $project->researchSecretary->name))->filter()->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('')); ?>

                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </span>
                                        <span class="min-w-0 flex-1"><span class="block break-words text-sm font-medium text-gray-900 dark:text-white"><?php echo e($project->researchSecretary->name); ?></span><span class="mt-1 block text-sm leading-5 text-gray-500 dark:text-slate-400">Selected by project group</span></span>
                                    </div>
                                <?php else: ?>
                                    <p class="text-sm leading-5 text-gray-500 dark:text-slate-400">Awaiting selection by the project group.</p>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <div class="min-w-0 text-sm text-gray-500 dark:text-slate-400">
                                <span class="sr-only">Reports:</span>
                                <p class="font-medium text-gray-700 dark:text-slate-200"><?php echo e($project->progress_reports_count + $project->narrative_reports_count); ?> total</p>
                                <p class="<?php echo \Illuminate\Support\Arr::toCssClasses(['mt-1', 'font-medium text-red-700 dark:text-red-300' => $pendingReportCount > 0]); ?>"><?php echo e($pendingReportCount); ?> awaiting review</p>
                            </div>
                            <div class="text-right sm:col-span-2 2xl:col-span-1">
                                <a href="<?php echo e(route('topics.show', $project)); ?><?php echo e($projectStatus === 'completed' ? '' : '#project-monitoring'); ?>" aria-label="<?php echo e($projectStatus === 'completed' ? 'View record' : 'Monitor project'); ?>: <?php echo e($project->title); ?>" class="inline-flex min-h-11 w-48 whitespace-nowrap items-center justify-center gap-2 rounded-lg bg-brand px-4 text-base font-semibold text-white hover:bg-brand-soft focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:focus-visible:outline-red-400"><?php echo e($projectStatus === 'completed' ? 'View record' : 'Monitor project'); ?></a>
                            </div>
                        </article>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <div class="px-4 py-8 text-center"><h4 class="text-base font-semibold text-gray-900 dark:text-white">No projects found</h4><p class="mt-1 text-sm text-gray-500 dark:text-slate-400">Projects appear here after the final papers are completed and the Notice to Proceed is issued.</p></div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($projects->hasPages()): ?><div class="border-t border-gray-100 px-5 py-4 dark:border-slate-800"><?php echo e($projects->links()); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </section>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\research_head\projects\index.blade.php ENDPATH**/ ?>