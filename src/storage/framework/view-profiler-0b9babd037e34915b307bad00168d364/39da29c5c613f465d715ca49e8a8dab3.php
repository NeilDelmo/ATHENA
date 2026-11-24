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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => 'Budget utilization','subtitle' => $topic->title]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Budget utilization','subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($topic->title)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

             <?php $__env->slot('actions', null, []); ?> 
                <?php if (isset($component)) { $__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.back-link','data' => ['href' => ''.e(request()->routeIs('project-budget.edit') ? route('research.show', $topic).'#project-monitoring' : route('research_secretary.dashboard')).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('back-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => ''.e(request()->routeIs('project-budget.edit') ? route('research.show', $topic).'#project-monitoring' : route('research_secretary.dashboard')).'']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
Back to project monitoring <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01)): ?>
<?php $attributes = $__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01; ?>
<?php unset($__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01)): ?>
<?php $component = $__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01; ?>
<?php unset($__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01); ?>
<?php endif; ?>
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

    <?php
        $defaultRows = collect(['Purchase Request', 'Cash Advance', 'Request of Payment'])->map(fn ($type) => [
            'type' => $type,
            'details' => '',
            'amount_requested' => 0,
            'actual_amount' => 0,
            'remarks' => '',
        ])->all();
        $restoreBudget = $errors->any() && session('budget_validation_report_id') === $report->id;
        $budgetInput = $restoreBudget ? old('budget_utilization', []) : $report->budget_utilization;
        $budgetRows = collect($defaultRows)->map(function (array $default) use ($budgetInput): array {
            $saved = collect(is_array($budgetInput) ? $budgetInput : [])->first(
                fn ($row) => is_array($row) && ($row['type'] ?? null) === $default['type'],
                [],
            );

            return array_replace($default, array_filter($saved, 'is_scalar'));
        })->all();
        $budgetToken = $restoreBudget && is_string(old('budget_token')) ? old('budget_token') : $report->budgetToken();
    ?>

    <div class="mx-auto max-w-5xl space-y-5 py-6 sm:px-6 lg:px-8">
        <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="grid gap-px overflow-hidden rounded-t-2xl bg-gray-200 dark:bg-slate-800 md:grid-cols-3">
                <div class="bg-slate-950 p-5 text-white md:col-span-2">
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Prepared monitoring report</p>
                    <p class="mt-2 text-base font-black"><?php echo e($report->quarter_label); ?> · <?php echo e($report->reporting_period_label); ?></p>
                    <p class="mt-1 text-xs text-slate-400">Researcher · <?php echo e($report->submitter->name); ?></p>
                </div>
                <div class="bg-red-700 p-5 text-white">
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-red-100">Approved project budget</p>
                    <p class="mt-2 text-2xl font-black tabular-nums">₱<?php echo e(number_format((float) $topic->estimated_budget, 2)); ?></p>
                </div>
            </div>

            <div class="border-b border-gray-200 bg-amber-50 px-5 py-3 text-xs leading-5 text-amber-900 dark:border-slate-800 dark:bg-amber-950/30 dark:text-amber-100 sm:px-6">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Auth::id() === $topic->research_secretary_id): ?>
                    You are the priority project secretary for this section. Other authorized project members may also complete it when needed.
                <?php else: ?>
                    <?php echo e($topic->researchSecretary?->name ?? 'The assigned project secretary'); ?> has priority for this section, but you may complete it as an authorized project member.
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <form id="budget-utilization-form-<?php echo e($report->id); ?>" data-proposal-paper-workspace method="POST" action="<?php echo e(request()->routeIs('project-budget.edit') ? route('project-budget.update', [$topic, $report]) : route('research_secretary.projects.budget.update', [$topic, $report])); ?>" x-data="budgetUtilizationForm({ rows: <?php echo \Illuminate\Support\Js::from($budgetRows)->toHtml() ?>, approvedBudget: <?php echo \Illuminate\Support\Js::from((float) $topic->estimated_budget)->toHtml() ?> })" class="space-y-6 p-5 sm:p-6">
                <?php echo csrf_field(); ?>
                <?php echo method_field('PUT'); ?>
                <input type="hidden" name="budget_token" value="<?php echo e($budgetToken); ?>" />

                <?php if (isset($component)) { $__componentOriginal90e243942d22f6a4722cd614e5732fed = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal90e243942d22f6a4722cd614e5732fed = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-writing-toolbar','data' => ['toolbarId' => 'budget-utilization-tools-'.$report->id,'dataBudgetUtilizationWritingToolbar' => true,'ariaLabel' => 'Budget utilization tools']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-writing-toolbar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['toolbar-id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('budget-utilization-tools-'.$report->id),'data-budget-utilization-writing-toolbar' => true,'aria-label' => 'Budget utilization tools']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                     <?php $__env->slot('context', null, []); ?> <p class="proposal-writing-target">Editing: <span>Budget utilization</span></p> <?php $__env->endSlot(); ?>
                    <div class="proposal-writing-toolbar-row" role="toolbar" aria-label="Budget utilization editing and preview">
                        <div class="proposal-writing-buttons">
                            <a href="<?php echo e(route('project-progress.monitoring-tool', $report)); ?>" class="proposal-preview-download inline-flex items-center">Review PDF</a>
                            <button type="submit" form="budget-utilization-form-<?php echo e($report->id); ?>" class="!border-red-700 !bg-red-700 !text-white hover:!bg-red-800">Confirm budget utilization</button>
                        </div>
                    </div>
                 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal90e243942d22f6a4722cd614e5732fed)): ?>
<?php $attributes = $__attributesOriginal90e243942d22f6a4722cd614e5732fed; ?>
<?php unset($__attributesOriginal90e243942d22f6a4722cd614e5732fed); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal90e243942d22f6a4722cd614e5732fed)): ?>
<?php $component = $__componentOriginal90e243942d22f6a4722cd614e5732fed; ?>
<?php unset($__componentOriginal90e243942d22f6a4722cd614e5732fed); ?>
<?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($restoreBudget): ?>
                    <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-xs text-red-700">
                        <p class="font-black">Please correct the budget entries.</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><li><?php echo e($error); ?></li><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></ul>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->has('budget_token')): ?><a href="<?php echo e(url()->current()); ?>" class="mt-2 secondary-action">Reload current budget</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950"><p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Requested</p><p class="mt-2 text-xl font-black tabular-nums text-slate-950 dark:text-white" x-text="currency(requestedTotal())"></p></div>
                    <div class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950/30"><p class="text-[10px] font-black uppercase tracking-wider text-red-500">Utilized</p><p class="mt-2 text-xl font-black tabular-nums text-red-900 dark:text-red-100" x-text="currency(spentTotal())"></p><p class="mt-1 text-[11px] font-bold text-red-600" x-text="`${utilizationPercentage().toFixed(1)}% utilized`"></p></div>
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900 dark:bg-emerald-950/30"><p class="text-[10px] font-black uppercase tracking-wider text-emerald-600">Remaining</p><p class="mt-2 text-xl font-black tabular-nums text-emerald-900 dark:text-emerald-100" x-text="currency(remainingBudget())"></p></div>
                </div>

                <div class="space-y-4">
                    <template x-for="(row, index) in rows" :key="row.type">
                        <article class="overflow-hidden rounded-2xl border border-gray-200 dark:border-slate-700">
                            <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-950">
                                <p class="text-sm font-black text-gray-950 dark:text-white" x-text="row.type"></p>
                                <span class="rounded-full bg-white px-2.5 py-1 text-[10px] font-black text-gray-500 ring-1 ring-gray-200 dark:bg-slate-900 dark:ring-slate-700" x-text="currency(row.actual_amount)"></span>
                            </div>
                            <input type="hidden" :name="`budget_utilization[${index}][type]`" :value="row.type">
                            <div class="grid gap-4 p-4 sm:grid-cols-2">
                                <label class="text-xs font-bold text-gray-700 dark:text-slate-200 sm:col-span-2">Details of request<textarea :name="`budget_utilization[${index}][details]`" x-model="row.details" rows="2" maxlength="300" autocomplete="off" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white"></textarea></label>
                                <label class="text-xs font-bold text-gray-700 dark:text-slate-200">Amount requested (PHP)<input :name="`budget_utilization[${index}][amount_requested]`" x-model.number="row.amount_requested" type="number" inputmode="decimal" min="0" step="0.01" required class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm tabular-nums focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white"></label>
                                <label class="text-xs font-bold text-gray-700 dark:text-slate-200">Amount spent (PHP)<input :name="`budget_utilization[${index}][actual_amount]`" x-model.number="row.actual_amount" type="number" inputmode="decimal" min="0" step="0.01" required class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm tabular-nums focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white"></label>
                                <label class="text-xs font-bold text-gray-700 dark:text-slate-200 sm:col-span-2">Remarks or challenges <span class="font-normal text-gray-400">(optional)</span><textarea :name="`budget_utilization[${index}][remarks]`" x-model="row.remarks" rows="2" maxlength="300" autocomplete="off" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white"></textarea></label>
                            </div>
                        </article>
                    </template>
                </div>

                <div class="border-t border-gray-100 pt-5 dark:border-slate-800">
                    <p class="max-w-xl text-xs leading-5 text-gray-500 dark:text-slate-400">Saving refreshes the official Monitoring Tool PDF and records your account and completion time. The researcher can then submit it to the Research Head.</p>
                </div>
            </form>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\research_secretary\projects\budget.blade.php ENDPATH**/ ?>