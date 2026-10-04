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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => 'Quarterly monitoring report','subtitle' => $topic->title]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Quarterly monitoring report','subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($topic->title)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

             <?php $__env->slot('actions', null, []); ?> 
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($selectedReportingDate || $preparedReport)): ?>
                <?php if (isset($component)) { $__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.back-link','data' => ['fixed' => true,'href' => ''.e(route('research.show', $topic)).'#project-monitoring']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('back-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['fixed' => true,'href' => ''.e(route('research.show', $topic)).'#project-monitoring']); ?>
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

    <div data-monitoring-workspace class="w-full space-y-5">
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-l-4 border-red-600 px-5 py-5 sm:px-6">
                <h3 class="text-lg font-black text-gray-950 dark:text-white"><?php echo e($revisionReport ? 'Correct '.$revisionReport->quarter_label.' Monitoring Tool' : 'Submit monitoring tool'); ?></h3>
                <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-600 dark:text-slate-300">Record your activities, accomplishments, and spending for this quarter. Your draft saves automatically and you can preview it now. Official PDF preparation and submission open after the quarter ends.</p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($revisionReport?->research_head_remarks): ?>
                    <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs leading-5 text-red-800 dark:border-red-950 dark:bg-red-950/40 dark:text-red-100">
                        <p class="font-black">Research Head revision remarks</p>
                        <p class="mt-1"><?php echo e($revisionReport->research_head_remarks); ?></p>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div class="border-t border-gray-200 dark:border-slate-800">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedReportingDate || $preparedReport): ?>
                <?php if (isset($component)) { $__componentOriginale196f318cb882bbe449e85915607f587 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale196f318cb882bbe449e85915607f587 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.monitoring-tool-form','data' => ['quarterOptions' => $quarterOptions,'selectedReportingDate' => $selectedReportingDate,'topic' => $topic,'preparedReport' => $preparedReport,'revisionReport' => $revisionReport,'monitoringDraft' => $monitoringDraft,'approvedWorkPlanByPeriod' => $approvedWorkPlanByPeriod,'approvedWorkPlanAvailable' => $approvedWorkPlanAvailable,'selectedPeriodKey' => $selectedPeriodKey,'selectedReportNumber' => $selectedReportNumber,'monitoringReportCount' => $monitoringReportCount,'initialWorkPlanRows' => $initialWorkPlanRows,'standalone' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('monitoring-tool-form'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['quarter-options' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($quarterOptions),'selected-reporting-date' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($selectedReportingDate),'topic' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($topic),'prepared-report' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($preparedReport),'revision-report' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($revisionReport),'monitoring-draft' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($monitoringDraft),'approved-work-plan-by-period' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($approvedWorkPlanByPeriod),'approved-work-plan-available' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($approvedWorkPlanAvailable),'selected-period-key' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($selectedPeriodKey),'selected-report-number' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($selectedReportNumber),'monitoring-report-count' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($monitoringReportCount),'initial-work-plan-rows' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($initialWorkPlanRows),'standalone' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale196f318cb882bbe449e85915607f587)): ?>
<?php $attributes = $__attributesOriginale196f318cb882bbe449e85915607f587; ?>
<?php unset($__attributesOriginale196f318cb882bbe449e85915607f587); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale196f318cb882bbe449e85915607f587)): ?>
<?php $component = $__componentOriginale196f318cb882bbe449e85915607f587; ?>
<?php unset($__componentOriginale196f318cb882bbe449e85915607f587); ?>
<?php endif; ?>
                <?php else: ?>
                    <p class="p-5 text-sm text-gray-600 dark:text-slate-300">There is no new quarter open for reporting. Check the quarter list for submitted reports or wait until the approved project period starts.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/faculty/monitoring-tools/create.blade.php ENDPATH**/ ?>