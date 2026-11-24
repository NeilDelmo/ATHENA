<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['conference' => null, 'reactive' => false]));

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

foreach (array_filter((['conference' => null, 'reactive' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $recordId = $conference?->id ?? 'new';
    $validationContext = session('dissemination_validation_context', []);
    $restore = $errors->any() && data_get($validationContext, 'topic_id') === request()->route('topic')?->id && data_get($validationContext, 'kind') === 'conference'
        && data_get($validationContext, 'record_id') === $conference?->id;
    $value = fn ($field) => $restore
        ? old($field) : ($conference?->$field instanceof \DateTimeInterface ? $conference->$field->format('Y-m-d') : ($conference?->$field ?? ''));
    $inputClass = 'mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white';
?>
<input type="hidden" name="conference_id" value="<?php echo e($recordId); ?>">
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reactive): ?> <input type="hidden" name="candidate_key" x-model="conferenceDraft.candidate_key"> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<div class="grid gap-4 sm:grid-cols-2">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['title' => ['Conference name', 'text', true], 'url' => ['Listing or official website URL', 'url', true], 'official_url' => ['Official conference website (if different)', 'url', false], 'location' => ['Location', 'text', false], 'submission_deadline' => ['Submission deadline', 'date', false], 'event_date' => ['Event start date', 'date', false], 'fees' => ['Fees and currency, if confirmed', 'text', false]]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => [$label, $type, $required]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
        <label for="conference-<?php echo e($recordId); ?>-<?php echo e($field); ?>" class="text-sm font-medium">
            <?php echo e($label); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($required): ?><span aria-hidden="true">*</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <input id="conference-<?php echo e($recordId); ?>-<?php echo e($field); ?>" name="<?php echo e($field); ?>" type="<?php echo e($type); ?>" value="<?php echo e($value($field)); ?>"
                <?php if($reactive): ?> x-model="conferenceDraft.<?php echo e($field); ?>" <?php endif; ?>
                <?php if($required): echo 'required'; endif; ?> <?php if($conference && $field === 'url'): echo 'readonly'; endif; ?>
                <?php if($type !== 'date'): ?> maxlength="<?php echo e($type === 'url' ? 2000 : 500); ?>" <?php endif; ?> class="<?php echo e($inputClass); ?>">
        </label>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    <label class="text-sm font-medium">Attendance
        <select name="attendance_mode" <?php if($reactive): ?> x-model="conferenceDraft.attendance_mode" <?php endif; ?> class="<?php echo e($inputClass); ?>">
            <option value="">Not confirmed</option>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['in_person' => 'In person', 'online' => 'Online', 'hybrid' => 'Hybrid']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($key); ?>" <?php if($value('attendance_mode') === $key): echo 'selected'; endif; ?>><?php echo e($label); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </select>
    </label>
    <label class="text-sm font-medium sm:col-span-2">Publication arrangements
        <textarea name="publication_details" rows="2" maxlength="5000" <?php if($reactive): ?> x-model="conferenceDraft.publication_details" <?php endif; ?> placeholder="Proceedings publisher, journal opportunity, or other confirmed details. Leave blank when unknown." class="<?php echo e($inputClass); ?>"><?php echo e($value('publication_details')); ?></textarea>
    </label>
    <label class="text-sm font-medium">Submission progress
        <select name="status" required class="<?php echo e($inputClass); ?>">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = \App\Models\ProjectConference::STATUSES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($status); ?>" <?php if(($value('status') ?: 'shortlisted') === $status): echo 'selected'; endif; ?>><?php echo e(ucfirst($status)); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </select>
    </label>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['submitted_on' => 'Submitted on', 'accepted_on' => 'Accepted on', 'presented_on' => 'Presented on']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
        <label class="text-sm font-medium"><?php echo e($label); ?><input type="date" name="<?php echo e($field); ?>" value="<?php echo e($value($field)); ?>" max="<?php echo e(now()->toDateString()); ?>" class="<?php echo e($inputClass); ?>"></label>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    <p class="text-xs text-gray-500 dark:text-slate-400 sm:col-span-2">Record the corresponding dates when marking a submission submitted, accepted, or presented. Acceptance and presentation do not automatically establish publication.</p>
    <label class="text-sm font-medium sm:col-span-2">Supporting evidence URL
        <input type="url" name="evidence_url" value="<?php echo e($value('evidence_url')); ?>" maxlength="2000" placeholder="Official acceptance, presentation, or proceedings page" class="<?php echo e($inputClass); ?>">
    </label>
    <label class="text-sm font-medium sm:col-span-2">Notes
        <textarea name="notes" rows="2" maxlength="5000" class="<?php echo e($inputClass); ?>"><?php echo e($value('notes')); ?></textarea>
    </label>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\project-conference-fields.blade.php ENDPATH**/ ?>