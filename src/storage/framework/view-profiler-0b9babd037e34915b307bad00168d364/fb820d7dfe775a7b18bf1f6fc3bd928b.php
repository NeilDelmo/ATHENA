<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['researchCall' => null, 'field', 'minimumDate']));

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

foreach (array_filter((['researchCall' => null, 'field', 'minimumDate']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $isStart = $field === 'start';
    $name = $isStart ? 'planned_start' : 'planned_end';
    $model = $isStart ? 'plannedStart' : 'plannedEnd';
    $call = $researchCall?->status !== 'draft' ? $researchCall : null;
    $start = $call?->implementation_start_date?->toDateString();
    $end = $call?->implementation_end_date?->toDateString();
    $referenceDates = [];
    if ($start) {
        $referenceDates[] = ['date' => $start, 'label' => 'Implementation begins'];
    }
    if ($end) {
        $referenceDates[] = ['date' => $end, 'label' => 'Implementation ends'];
    }
?>

<div x-data="proposalDateGuidance({ start: <?php echo \Illuminate\Support\Js::from($start)->toHtml() ?>, end: <?php echo \Illuminate\Support\Js::from($end)->toHtml() ?>, label: <?php echo \Illuminate\Support\Js::from($isStart ? 'planned start' : 'planned end')->toHtml() ?> })">
    <label for="<?php echo e($name); ?>" class="block text-xs font-black uppercase tracking-wider text-gray-600 dark:text-slate-300"><?php echo e($isStart ? 'Planned Start' : 'Planned End'); ?> <?php if (isset($component)) { $__componentOriginal4f074808deca33fb39dbf7707c9b77e2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.required-mark','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('required-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $attributes = $__attributesOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__attributesOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2)): ?>
<?php $component = $__componentOriginal4f074808deca33fb39dbf7707c9b77e2; ?>
<?php unset($__componentOriginal4f074808deca33fb39dbf7707c9b77e2); ?>
<?php endif; ?></label>
    <?php if (isset($component)) { $__componentOriginal37e12294b28f0bd91a733acab9bb06c5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal37e12294b28f0bd91a733acab9bb06c5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.date-picker','data' => ['id' => $name,'name' => $name,'model' => $model,'min' => $minimumDate,'referenceDates' => $referenceDates,'ariaDescribedby' => ''.e($name).'-hint '.e($name).'-date-warning','required' => true,'class' => 'mt-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('date-picker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($name),'name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($name),'model' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($model),'min' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($minimumDate),'reference-dates' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($referenceDates),'aria-describedby' => ''.e($name).'-hint '.e($name).'-date-warning','required' => true,'class' => 'mt-2']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal37e12294b28f0bd91a733acab9bb06c5)): ?>
<?php $attributes = $__attributesOriginal37e12294b28f0bd91a733acab9bb06c5; ?>
<?php unset($__attributesOriginal37e12294b28f0bd91a733acab9bb06c5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal37e12294b28f0bd91a733acab9bb06c5)): ?>
<?php $component = $__componentOriginal37e12294b28f0bd91a733acab9bb06c5; ?>
<?php unset($__componentOriginal37e12294b28f0bd91a733acab9bb06c5); ?>
<?php endif; ?>
    <p id="<?php echo e($name); ?>-hint" class="mt-2 text-[11px] text-gray-500 dark:text-slate-400"><?php echo e($isStart ? 'Only today and future dates can be selected.' : 'Automatically calculated from the total duration and planned start.'); ?></p>
    <p id="<?php echo e($name); ?>-date-warning" x-cloak x-show="dateWarning(<?php echo e($model); ?>)" x-text="dateWarning(<?php echo e($model); ?>)" role="status" aria-live="polite" class="mt-2 text-xs leading-5 text-amber-800 dark:text-amber-300"></p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = [$name];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-2 text-xs font-semibold text-red-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\proposal-planned-date.blade.php ENDPATH**/ ?>