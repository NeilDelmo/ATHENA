<?php if (isset($component)) { $__componentOriginal2acb12939f316454119c3a7d057bf1aa = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2acb12939f316454119c3a7d057bf1aa = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-assessment-form','data' => ['proposalDraft' => $proposalDraft,'paper' => $paper,'projectDetailsComplete' => $projectDetailsComplete]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-assessment-form'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['proposal-draft' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($proposalDraft),'paper' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($paper),'project-details-complete' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($projectDetailsComplete)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2acb12939f316454119c3a7d057bf1aa)): ?>
<?php $attributes = $__attributesOriginal2acb12939f316454119c3a7d057bf1aa; ?>
<?php unset($__attributesOriginal2acb12939f316454119c3a7d057bf1aa); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2acb12939f316454119c3a7d057bf1aa)): ?>
<?php $component = $__componentOriginal2acb12939f316454119c3a7d057bf1aa; ?>
<?php unset($__componentOriginal2acb12939f316454119c3a7d057bf1aa); ?>
<?php endif; ?>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\faculty\proposal-drafts\gad-checklist\show.blade.php ENDPATH**/ ?>