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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['variant' => 'hero','eyebrow' => 'Research office','title' => 'Coordinator dashboard','subtitle' => ($coordinator->college ?: 'Your college').' · '.now()->format('l, F j, Y')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'hero','eyebrow' => 'Research office','title' => 'Coordinator dashboard','subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(($coordinator->college ?: 'Your college').' · '.now()->format('l, F j, Y'))]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

             <?php $__env->slot('actions', null, []); ?> <a href="<?php echo e(route('research_coordinator.proposals.index')); ?>" class="secretary-button-primary">Open LREC queue <span aria-hidden="true">→</span></a> <?php $__env->endSlot(); ?>
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
    <?php ($attentionTotal = $lrecReviewCount); ?>
    <div class="secretary-workspace" data-coordinator-workspace="dashboard" data-dashboard-layout="office-overview">
        <section class="secretary-overview" aria-label="College workload">
            <div class="secretary-overview-main">
                <div class="secretary-overview-label"><span class="secretary-status-dot" aria-hidden="true"></span> Your college at a glance</div>
                <div class="secretary-attention-number"><?php echo e($attentionTotal); ?><span>items<br>need attention</span></div>
                <p>Record committee feedback, follow up on proposals, and keep your college’s research moving.</p>
                <a href="<?php echo e(route('research_coordinator.proposals.index', ['status' => 'review'])); ?>" class="secretary-button-primary"><?php echo e($lrecReviewCount); ?> ready for committee comments <span aria-hidden="true">→</span></a>
            </div>
            <dl class="secretary-metrics">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [['LREC proposals', $lrecProposals->total(), 'Committee reviews and follow-up'], ['Ready for comments', $lrecReviewCount, 'Committee reviews needing your input'], ['College members', $memberCount, 'Faculty in your college directory']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $count, $description]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div><dt><?php echo e($label); ?></dt><dd><?php echo e($count); ?></dd><p><?php echo e($description); ?></p></div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </dl>
        </section>
        <div class="secretary-dashboard-columns" data-dashboard-columns>
            <?php if (isset($component)) { $__componentOriginal943d9967d757e5bd3d81fa46e14c78da = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal943d9967d757e5bd3d81fa46e14c78da = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.coordinator-lrec-queue','data' => ['proposals' => $lrecProposals,'coordinator' => $coordinator,'compact' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('coordinator-lrec-queue'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['proposals' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($lrecProposals),'coordinator' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($coordinator),'compact' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal943d9967d757e5bd3d81fa46e14c78da)): ?>
<?php $attributes = $__attributesOriginal943d9967d757e5bd3d81fa46e14c78da; ?>
<?php unset($__attributesOriginal943d9967d757e5bd3d81fa46e14c78da); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal943d9967d757e5bd3d81fa46e14c78da)): ?>
<?php $component = $__componentOriginal943d9967d757e5bd3d81fa46e14c78da; ?>
<?php unset($__componentOriginal943d9967d757e5bd3d81fa46e14c78da); ?>
<?php endif; ?>
            <aside class="secretary-office-links" aria-label="College work">
                <a href="<?php echo e(route('research_coordinator.reports.index')); ?>" class="secretary-portfolio-link"><span><span class="secretary-eyebrow">College accomplishment reporting</span><strong>Quarterly report</strong><small>Prepare activities, attach evidence, and submit your college report.</small></span><b>Q<?php echo e(now()->quarter); ?></b></a>
            </aside>
        </div>
        <div class="grid gap-5 lg:grid-cols-2">
            <a href="<?php echo e(route('research_coordinator.targets.index')); ?>" class="secretary-target-link"><span class="secretary-file-mark" aria-hidden="true"><?php if (isset($component)) { $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-library-icon','data' => ['name' => 'calendar','class' => 'h-5 w-5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-library-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'calendar','class' => 'h-5 w-5']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $attributes = $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $component = $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?></span><span><strong>Research targets</strong><small>Track your college’s annual goals and accomplishments.</small></span><span aria-hidden="true">→</span></a>
            <a href="<?php echo e(route('research_coordinator.members.index')); ?>" class="secretary-target-link"><span class="secretary-file-mark" aria-hidden="true"><?php if (isset($component)) { $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-library-icon','data' => ['name' => 'college','class' => 'h-5 w-5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-library-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'college','class' => 'h-5 w-5']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $attributes = $__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__attributesOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad)): ?>
<?php $component = $__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad; ?>
<?php unset($__componentOriginal2cc340f7d439e305c6ca7bbe0688c9ad); ?>
<?php endif; ?></span><span><strong>Faculty directory</strong><small><?php echo e($memberCount); ?> <?php echo e(str('member')->plural($memberCount)); ?> in your college. Find a colleague’s contact details.</small></span><span aria-hidden="true">→</span></a>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\research_coordinator\dashboard.blade.php ENDPATH**/ ?>