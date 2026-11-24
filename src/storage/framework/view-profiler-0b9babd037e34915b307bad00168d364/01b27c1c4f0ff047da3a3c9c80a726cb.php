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
    $acceptedTerminal = ! $isMonitoringTool && $report->report_type === 'terminal' && $report->review_status === 'reviewed';
    $defaultDecision = $acceptedTerminal ? 'revision_requested' : 'reviewed';
    $canReview = Auth::user()->isUsingWorkspace('research_head')
        && ! $topic->isCompletedProject()
        && $report->isSubmitted()
        && $report->isAvailableToResearchHead()
        && ($report->review_status === 'pending' || $acceptedTerminal)
        && $report->isLatestSubmittedVersion();
    $reviewId = 'head-review-'.($isMonitoringTool ? 'monitoring-' : 'progress-').$report->id;
?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canReview): ?>
    <section class="rounded-xl border border-red-100 bg-red-50/50 p-5 dark:border-red-900 dark:bg-red-950/20" aria-labelledby="<?php echo e($reviewId); ?>-heading" data-head-report-review>
        <div class="flex items-center gap-3 text-red-800 dark:text-red-200">
            <svg class="h-6 w-6 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H5v16h14V5h-4M9 3h6v4H9V3Zm-1 12 3 3 5-6"/></svg>
            <h4 id="<?php echo e($reviewId); ?>-heading" class="text-lg font-semibold">Research Head decision</h4>
        </div>
        <p class="mt-2 text-base leading-7 text-slate-600 dark:text-slate-300"><?php echo e($report->forwarded_at ? 'The Research Office Secretary has checked and forwarded this report.' : 'Preview the submitted report before recording your decision.'); ?></p>
        <form method="POST" action="<?php echo e(route($isMonitoringTool ? 'research_head.progress-reports.review' : 'research_head.narrative-progress-reports.review', $report)); ?>" class="mt-5 space-y-5" x-data="{ decision: <?php echo \Illuminate\Support\Js::from(old('review_status', $defaultDecision))->toHtml() ?> }" <?php if(! $isMonitoringTool): ?> data-narrative-report-review <?php endif; ?>>
            <?php echo csrf_field(); ?>
            <?php echo method_field('PATCH'); ?>
            <fieldset class="space-y-3">
                <legend class="sr-only">Review decision</legend>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($acceptedTerminal)): ?>
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <input type="radio" name="review_status" value="reviewed" x-model="decision" <?php if(old('review_status', 'reviewed') === 'reviewed'): echo 'checked'; endif; ?> class="mt-1 h-5 w-5 shrink-0 border-slate-300 text-red-700 focus:ring-red-600">
                    <span><span class="block text-base font-semibold text-slate-950 dark:text-white">Accept report</span><span class="mt-1 block text-base leading-6 text-slate-600 dark:text-slate-300">The report is satisfactory. Complete its review.</span></span>
                </label>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                    <input type="radio" name="review_status" value="revision_requested" x-model="decision" <?php if(old('review_status', $defaultDecision) === 'revision_requested'): echo 'checked'; endif; ?> class="mt-1 h-5 w-5 shrink-0 border-slate-300 text-red-700 focus:ring-red-600">
                    <span><span class="block text-base font-semibold text-slate-950 dark:text-white">Send concerns to Research Office Secretary</span><span class="mt-1 block text-base leading-6 text-slate-600 dark:text-slate-300">The secretary coordinates any corrections with the Faculty Researcher.</span></span>
                </label>
            </fieldset>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($acceptedTerminal && $report->hasSignedCopy()): ?>
                <p class="text-sm text-slate-600 dark:text-slate-300">Returning concerns removes the signed copy. A new fully signed PDF is required after the corrected report is accepted.</p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <label for="<?php echo e($reviewId); ?>-notes" class="block text-base font-semibold text-slate-800 dark:text-slate-100">
                <span x-text="decision === 'revision_requested' ? 'Concerns for the secretary' : 'Review notes (optional)'">Review notes (optional)</span>
                <textarea id="<?php echo e($reviewId); ?>-notes" name="research_head_remarks" rows="3" maxlength="5000" x-bind:required="decision === 'revision_requested'" class="mt-2 block w-full rounded-lg border-slate-300 bg-white text-base font-normal leading-7 focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white" placeholder="Explain your decision or the concern that needs attention."><?php echo e(old('research_head_remarks', $report->research_head_remarks)); ?></textarea>
            </label>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['research_head_remarks'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p role="alert" class="text-base text-red-700 dark:text-red-300"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['review_status'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p role="alert" class="text-base text-red-700 dark:text-red-300"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <button type="submit" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg bg-red-700 px-5 py-3 text-base font-semibold text-white hover:bg-red-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/></svg>
                <span x-text="decision === 'revision_requested' ? 'Return concerns to secretary' : 'Accept report'">Accept report</span>
            </button>
        </form>
    </section>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\implementation-report-head-review.blade.php ENDPATH**/ ?>