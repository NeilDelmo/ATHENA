<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['topic', 'report']));

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

foreach (array_filter((['topic', 'report']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $isMonitoringTool = $report instanceof \App\Models\ProjectProgressReport;
    $reviewId = ($isMonitoringTool ? 'monitoring' : 'progress').'-secretary-review-'.$report->id;
    $canReview = $topic->canReviewImplementationReports(Auth::user())
        && $topic->isMonitoringAvailable()
        && $report->isAwaitingSecretaryReview()
        && $report->isLatestSubmittedVersion();
?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['review'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-base font-medium text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report->secretary_remarks): ?>
    <section class="rounded-xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-700 dark:bg-slate-950/50" aria-label="Research Office Secretary remarks">
        <h4 class="flex items-center gap-2 text-base font-semibold text-slate-950 dark:text-white"><svg class="h-5 w-5 shrink-0 text-red-700 dark:text-red-300" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3h6v4H9V3ZM9 5H5v16h14V5h-4M8 14l3 3 5-6"/></svg>Research Office Secretary <?php echo e($report->review_status === 'revision_requested' ? 'correction instructions' : 'review notes'); ?></h4>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report->secretaryReviewer): ?><p class="mt-2 text-base text-slate-500 dark:text-slate-400"><?php echo e($report->secretaryReviewer->name); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report->secretary_reviewed_at): ?> · <?php echo e($report->secretary_reviewed_at->format('M j, Y')); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <p class="mt-3 max-w-[65ch] whitespace-pre-line break-words text-base leading-7 text-slate-600 dark:text-slate-300"><?php echo e($report->secretary_remarks); ?></p>
    </section>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canReview): ?>
    <form method="POST" action="<?php echo e(route($isMonitoringTool ? 'research_secretary.monitoring-reports.review' : 'research_secretary.narrative-reports.review', $report)); ?>" class="space-y-4 border-t border-slate-200 pt-4 dark:border-slate-700" x-data="{ decision: <?php echo \Illuminate\Support\Js::from(old('decision', 'forward'))->toHtml() ?> }" data-secretary-report-review>
        <?php echo csrf_field(); ?>
        <?php echo method_field('PATCH'); ?>
        <h4 class="text-base font-semibold text-slate-950 dark:text-white">Research Office Secretary review</h4>
        <fieldset class="flex flex-wrap gap-3">
            <legend class="sr-only">Review decision</legend>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['forward' => 'Forward to Research Head', 'revision_requested' => 'Return for corrections']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-base text-slate-700 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">
                    <input type="radio" name="decision" value="<?php echo e($value); ?>" x-model="decision" required <?php if(old('decision', 'forward') === $value): echo 'checked'; endif; ?> class="h-4 w-4 border-slate-300 text-brand focus:ring-brand dark:border-slate-600">
                    <span><?php echo e($label); ?></span>
                </label>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </fieldset>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['decision'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-base font-medium text-red-700 dark:text-red-300"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <div x-cloak x-show="decision === 'revision_requested'" class="max-w-2xl">
            <label for="<?php echo e($reviewId); ?>-remarks" class="block text-base font-semibold text-slate-700 dark:text-slate-200">Correction instructions</label>
            <textarea id="<?php echo e($reviewId); ?>-remarks" name="secretary_remarks" rows="2" maxlength="5000" x-bind:required="decision === 'revision_requested'" x-bind:disabled="decision !== 'revision_requested'" placeholder="Describe what the project leader needs to correct." class="mt-2 block w-full resize-y rounded-lg border-slate-300 bg-white text-base font-normal leading-6 text-slate-900 focus:border-brand focus:ring-brand dark:border-slate-600 dark:bg-slate-900 dark:text-white"><?php echo e(old('secretary_remarks', $report->secretary_remarks)); ?></textarea>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['secretary_remarks'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-2 text-base font-medium text-red-700 dark:text-red-300"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand px-4 py-2 text-base font-semibold text-white hover:bg-brand-soft focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand" x-text="decision === 'revision_requested' ? 'Return for corrections' : 'Forward to Research Head'"><?php echo e(old('decision', 'forward') === 'revision_requested' ? 'Return for corrections' : 'Forward to Research Head'); ?></button>
    </form>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\implementation-report-secretary-review.blade.php ENDPATH**/ ?>