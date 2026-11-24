<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['topic', 'submission' => null, 'returnToFinder' => false]));

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

foreach (array_filter((['topic', 'submission' => null, 'returnToFinder' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $validationContext = session('dissemination_validation_context', []);
    $restore = $errors->any() && data_get($validationContext, 'topic_id') === $topic->id && data_get($validationContext, 'kind') === 'journal'
        && data_get($validationContext, 'record_id') === $submission?->id;
    $value = fn ($field, $default = '') => $restore ? old($field, $default) : $default;
    $prefix = 'journal-entry-'.($submission?->id ?? 'new');
    $stage = $value('status', $submission?->status ?? 'shortlisted');
?>
<form
    method="POST"
    action="<?php echo e($submission ? route('research.dissemination.journal-submissions.update', [$topic, $submission]) : route('research.dissemination.journal-submissions.store', $topic)); ?>"
    class="space-y-4"
    x-data="{
        stage: <?php echo \Illuminate\Support\Js::from($stage)->toHtml() ?>,
        journalName: <?php echo \Illuminate\Support\Js::from($value('journal_name', $submission?->journal_name ?? ''))->toHtml() ?>,
        issn: <?php echo \Illuminate\Support\Js::from($value('issn', $submission?->issn ?? ''))->toHtml() ?>,
        journalUrl: <?php echo \Illuminate\Support\Js::from($value('journal_url', $submission?->journal_url ?? ''))->toHtml() ?>,
        get needsSubmission() { return ['submitted', 'under_review', 'revision_requested', 'accepted', 'published', 'rejected'].includes(this.stage) },
        get needsAcceptance() { return ['accepted', 'published'].includes(this.stage) }
    }"
    <?php if(!$submission): ?>
        @journal-selected.window="journalName = $event.detail.name; issn = $event.detail.issn || ''; journalUrl = $event.detail.homepage_url || ''; $nextTick(() => $refs.journalName.focus())"
    <?php endif; ?>
>
    <?php echo csrf_field(); ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($returnToFinder): ?><input type="hidden" name="return_to_finder" value="1"><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($submission): ?> <?php echo method_field('PATCH'); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <input type="hidden" name="journal_submission_id" value="<?php echo e($submission?->id); ?>">
    <div class="grid gap-4 sm:grid-cols-2">
        <label for="<?php echo e($prefix); ?>-name" class="text-sm font-semibold text-gray-800 dark:text-slate-100">Journal name
            <input id="<?php echo e($prefix); ?>-name" x-ref="journalName" name="journal_name" x-model="journalName" required maxlength="255" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
        </label>
        <label for="<?php echo e($prefix); ?>-title" class="text-sm font-semibold text-gray-800 dark:text-slate-100">Manuscript title
            <input id="<?php echo e($prefix); ?>-title" name="manuscript_title" value="<?php echo e($value('manuscript_title', $submission?->manuscript_title ?? $topic->title)); ?>" required maxlength="500" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
        </label>
        <label for="<?php echo e($prefix); ?>-url" class="text-sm font-semibold text-gray-800 dark:text-slate-100">Official journal website <span class="font-normal text-gray-500">(optional)</span>
            <input id="<?php echo e($prefix); ?>-url" type="url" name="journal_url" x-model="journalUrl" maxlength="2000" placeholder="https://" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
        </label>
        <label for="<?php echo e($prefix); ?>-issn" class="text-sm font-semibold text-gray-800 dark:text-slate-100">ISSN <span class="font-normal text-gray-500">(optional)</span>
            <input id="<?php echo e($prefix); ?>-issn" name="issn" x-model="issn" maxlength="9" placeholder="1234-5678" pattern="[0-9]{4}-[0-9]{3}[0-9Xx]" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
        </label>
        <label for="<?php echo e($prefix); ?>-status" class="text-sm font-semibold text-gray-800 dark:text-slate-100">Current stage
            <select id="<?php echo e($prefix); ?>-status" name="status" x-model="stage" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = \App\Models\ProjectJournalSubmission::STATUSES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <option value="<?php echo e($key); ?>" <?php if($stage === $key): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </select>
        </label>
        <label for="<?php echo e($prefix); ?>-reference" class="text-sm font-semibold text-gray-800 dark:text-slate-100">Journal submission ID <span class="font-normal text-gray-500">(optional)</span>
            <input id="<?php echo e($prefix); ?>-reference" name="submission_reference" value="<?php echo e($value('submission_reference', $submission?->submission_reference)); ?>" maxlength="255" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
        </label>
    </div>
    <div class="grid gap-4 sm:grid-cols-3">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['submitted_on' => ['Submitted on', 'needsSubmission'], 'accepted_on' => ['Accepted on', 'needsAcceptance'], 'published_on' => ['Published on', "stage === 'published'"]]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => [$label, $required]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <label for="<?php echo e($prefix); ?>-<?php echo e($field); ?>" class="text-sm font-semibold text-gray-800 dark:text-slate-100"><?php echo e($label); ?>

                <span x-show="<?php echo e($required); ?>" class="font-normal text-red-700 dark:text-red-300">(required)</span>
                <input id="<?php echo e($prefix); ?>-<?php echo e($field); ?>" type="date" name="<?php echo e($field); ?>" value="<?php echo e($value($field, $submission?->{$field}?->toDateString())); ?>" max="<?php echo e(now()->toDateString()); ?>" :required="<?php echo e($required); ?>" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
            </label>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>
    <label for="<?php echo e($prefix); ?>-publication" class="block text-sm font-semibold text-gray-800 dark:text-slate-100">Published article or DOI link
        <span x-show="stage === 'published'" class="font-normal text-red-700 dark:text-red-300">(required)</span>
        <input id="<?php echo e($prefix); ?>-publication" type="url" name="publication_url" value="<?php echo e($value('publication_url', $submission?->publication_url)); ?>" :required="stage === 'published'" maxlength="2000" placeholder="https://doi.org/… or the published article URL" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
    </label>
    <label for="<?php echo e($prefix); ?>-notes" class="block text-sm font-semibold text-gray-800 dark:text-slate-100">Notes <span class="font-normal text-gray-500">(optional)</span>
        <textarea id="<?php echo e($prefix); ?>-notes" name="notes" rows="2" maxlength="5000" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white"><?php echo e($value('notes', $submission?->notes)); ?></textarea>
    </label>
    <p class="text-xs leading-5 text-gray-500 dark:text-slate-400">Record updates from the journal here. Submit the actual manuscript through the journal’s official website.</p>
    <button type="submit" class="rounded-lg bg-red-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-800 focus:ring-2 focus:ring-red-700 focus:ring-offset-2"><?php echo e($submission ? 'Save changes' : 'Add journal to tracker'); ?></button>
</form>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\journal-submission-form.blade.php ENDPATH**/ ?>