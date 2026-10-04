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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => $preparedReport?->report_label ?? (request('report_type') === 'terminal' ? 'Terminal report' : 'Progress report'),'subtitle' => $topic->title]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($preparedReport?->report_label ?? (request('report_type') === 'terminal' ? 'Terminal report' : 'Progress report')),'subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($topic->title)]); ?>
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

    <div class="mx-auto max-w-[90rem] space-y-5 py-6 sm:px-6 lg:px-8">
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-l-4 border-red-600 px-5 py-5 sm:px-6">
                <h3 class="text-2xl font-black tracking-tight text-gray-950 dark:text-white"><?php echo e(request('report_type') === 'terminal' ? 'Summarize the completed project' : 'Report project accomplishments'); ?></h3>
                <p class="mt-2 text-base leading-7 text-gray-600 dark:text-slate-300"><?php echo e(request('report_type') === 'terminal' ? 'BatStateU-REC-RES-04' : 'BatStateU-REC-RES-02'); ?> · Revision 02. Fill and save your work privately. PDF preparation and submission open <?php echo e(request('report_type') === 'terminal' ? 'after the project ends' : 'after the reporting quarter ends'); ?>.</p>
            </div>

            <div class="border-t border-gray-200 dark:border-slate-800">
                <?php if (isset($component)) { $__componentOriginal08cd3a6c48eb775224458b277966ff53 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal08cd3a6c48eb775224458b277966ff53 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.progress-report-form','data' => ['topic' => $topic,'preparedReport' => $preparedReport,'narrativeReportDraft' => $narrativeReportDraft,'progressDefaults' => $progressDefaults,'terminalDefaults' => $terminalDefaults,'terminalEvidence' => $terminalEvidence,'quarterOptions' => $quarterOptions,'selectedReportingDate' => $selectedReportingDate,'standalone' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('progress-report-form'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['topic' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($topic),'prepared-report' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($preparedReport),'narrative-report-draft' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($narrativeReportDraft),'progress-defaults' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($progressDefaults),'terminal-defaults' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($terminalDefaults),'terminal-evidence' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($terminalEvidence),'quarter-options' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($quarterOptions),'selected-reporting-date' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($selectedReportingDate),'standalone' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal08cd3a6c48eb775224458b277966ff53)): ?>
<?php $attributes = $__attributesOriginal08cd3a6c48eb775224458b277966ff53; ?>
<?php unset($__attributesOriginal08cd3a6c48eb775224458b277966ff53); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal08cd3a6c48eb775224458b277966ff53)): ?>
<?php $component = $__componentOriginal08cd3a6c48eb775224458b277966ff53; ?>
<?php unset($__componentOriginal08cd3a6c48eb775224458b277966ff53); ?>
<?php endif; ?>
            </div>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/faculty/progress-reports/create.blade.php ENDPATH**/ ?>