<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['topic', 'journalSubmissions', 'canTrackJournals' => false, 'returnToFinder' => false]));

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

foreach (array_filter((['topic', 'journalSubmissions', 'canTrackJournals' => false, 'returnToFinder' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $validationContext = session('dissemination_validation_context', []);
    $hasJournalErrors = data_get($validationContext, 'topic_id') === $topic->id && data_get($validationContext, 'kind') === 'journal' && $errors->any();
?>

<section id="journal-submissions" class="space-y-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-950 sm:p-6"
    x-data="{ adding: <?php echo \Illuminate\Support\Js::from($hasJournalErrors && data_get($validationContext, 'record_id') === null)->toHtml() ?> }"
    @journal-selected.window="adding = true; $nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'start' }))">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="text-lg font-bold text-gray-950 dark:text-white">Journal submission tracker</h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-slate-300">Follow your manuscript from shortlist to publication.</p>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canTrackJournals): ?>
            <button type="button" @click="adding = !adding" :aria-expanded="adding" class="rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-300" x-text="adding ? 'Close form' : 'Add a journal'"></button>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('status')): ?>
        <p role="status" class="rounded-lg bg-gray-50 p-3 text-sm text-gray-700 dark:bg-slate-900 dark:text-slate-200"><?php echo e(session('status')); ?></p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasJournalErrors): ?>
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
            <p class="font-semibold">Please correct these details:</p>
            <ul class="mt-1 list-inside list-disc"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><li><?php echo e($error); ?></li><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></ul>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canTrackJournals): ?>
        <div x-show="adding" x-cloak class="border-t border-gray-200 pt-4 dark:border-slate-700">
            <?php if (isset($component)) { $__componentOriginal4c5717a6ef7c2f0ed642559b855d3f1e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4c5717a6ef7c2f0ed642559b855d3f1e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.journal-submission-form','data' => ['topic' => $topic,'returnToFinder' => $returnToFinder]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('journal-submission-form'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['topic' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($topic),'return-to-finder' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($returnToFinder)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4c5717a6ef7c2f0ed642559b855d3f1e)): ?>
<?php $attributes = $__attributesOriginal4c5717a6ef7c2f0ed642559b855d3f1e; ?>
<?php unset($__attributesOriginal4c5717a6ef7c2f0ed642559b855d3f1e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4c5717a6ef7c2f0ed642559b855d3f1e)): ?>
<?php $component = $__componentOriginal4c5717a6ef7c2f0ed642559b855d3f1e; ?>
<?php unset($__componentOriginal4c5717a6ef7c2f0ed642559b855d3f1e); ?>
<?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="divide-y divide-gray-200 dark:divide-slate-700">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $journalSubmissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $submission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <article class="py-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h4 class="break-words text-sm font-bold text-gray-950 dark:text-white"><?php echo e($submission->journal_name); ?></h4>
                        <p class="mt-1 break-words text-sm text-gray-600 dark:text-slate-300"><?php echo e($submission->manuscript_title); ?></p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-slate-400"><?php echo e($submission->issn ? 'ISSN '.$submission->issn.' · ' : ''); ?>Tracked by <?php echo e($submission->addedBy?->name); ?></p>
                    </div>
                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 dark:bg-slate-800 dark:text-slate-200"><?php echo e(\App\Models\ProjectJournalSubmission::STATUSES[$submission->status]); ?></span>
                </div>
                <dl class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-xs text-gray-600 dark:text-slate-300">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['submitted_on' => 'Submitted', 'accepted_on' => 'Accepted', 'published_on' => 'Published']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($submission->{$field}): ?>
                            <div class="flex gap-1"><dt class="font-semibold"><?php echo e($label); ?>:</dt><dd><?php echo e($submission->{$field}->format('M j, Y')); ?></dd></div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($submission->submission_reference): ?><div class="flex gap-1"><dt class="font-semibold">Journal ID:</dt><dd><?php echo e($submission->submission_reference); ?></dd></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </dl>
                <div class="mt-3 flex flex-wrap gap-4 text-sm font-semibold text-red-700 dark:text-red-300">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($submission->journal_url): ?><a href="<?php echo e($submission->journal_url); ?>" target="_blank" rel="noopener noreferrer" class="secondary-action">Journal website ↗</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($submission->status === 'published' && $submission->publication_url): ?>
                        <a href="<?php echo e($submission->publication_url); ?>" target="_blank" rel="noopener noreferrer" class="secondary-action">Read published article ↗</a>
                        <a href="https://scholar.google.com/scholar?<?php echo e(http_build_query(['q' => '"'.$submission->manuscript_title.'"'])); ?>" target="_blank" rel="noopener noreferrer" class="secondary-action">Check Google Scholar ↗</a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($submission->notes): ?><p class="mt-2 whitespace-pre-line text-sm text-gray-600 dark:text-slate-300"><?php echo e($submission->notes); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canTrackJournals): ?>
                    <details class="mt-3" <?php if($hasJournalErrors && data_get($validationContext, 'record_id') === $submission->id): ?> open <?php endif; ?>>
                        <summary class="disclosure-summary secondary-action w-full justify-start text-left">Update stage or details</summary>
                        <div class="mt-4"><?php if (isset($component)) { $__componentOriginal4c5717a6ef7c2f0ed642559b855d3f1e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4c5717a6ef7c2f0ed642559b855d3f1e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.journal-submission-form','data' => ['topic' => $topic,'submission' => $submission,'returnToFinder' => $returnToFinder]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('journal-submission-form'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['topic' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($topic),'submission' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($submission),'return-to-finder' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($returnToFinder)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4c5717a6ef7c2f0ed642559b855d3f1e)): ?>
<?php $attributes = $__attributesOriginal4c5717a6ef7c2f0ed642559b855d3f1e; ?>
<?php unset($__attributesOriginal4c5717a6ef7c2f0ed642559b855d3f1e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4c5717a6ef7c2f0ed642559b855d3f1e)): ?>
<?php $component = $__componentOriginal4c5717a6ef7c2f0ed642559b855d3f1e; ?>
<?php unset($__componentOriginal4c5717a6ef7c2f0ed642559b855d3f1e); ?>
<?php endif; ?></div>
                    </details>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </article>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <p class="py-5 text-center text-sm text-gray-500 dark:text-slate-400">No journal submissions tracked yet. Choose “Track this journal” from a recommendation or add a journal here.</p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</section>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\journal-submission-tracker.blade.php ENDPATH**/ ?>