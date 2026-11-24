<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['section', 'title', 'period', 'projects' => []]));

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

foreach (array_filter((['section', 'title', 'period', 'projects' => []]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $fields = ['event' => $section === 'publications' ? 'Journal Name/Conference' : 'Event/Activity'];
    if ($section !== 'seminars') {
        $fields['research_title'] = 'Research Title';
    }
    $fields['date'] = 'Date';
    if ($section !== 'publications') {
        $fields['place'] = 'Place';
    }
    $fields['personnel'] = 'Personnel(s) involved';
    $inputClass = 'mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-950 dark:text-white';
?>

<section <?php echo e($attributes->class(['dashboard-panel overflow-hidden'])); ?> aria-labelledby="<?php echo e($section); ?>-heading">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-5 dark:border-slate-800">
        <div>
            <h3 id="<?php echo e($section); ?>-heading" class="font-bold text-gray-950 dark:text-white"><?php echo e($title); ?></h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Enter this quarter’s activities and attach proof to each row. Leave empty if none.</p>
        </div>
        <button type="button" class="dashboard-action" @click="addActivity('<?php echo e($section); ?>')" :disabled="<?php echo e($section); ?>.length >= 100">Add <?php echo e($section === 'seminars' ? 'seminar or training' : str($section)->singular()); ?></button>
    </div>
    <p x-show="!<?php echo e($section); ?>.length" class="p-5 text-sm text-slate-500 dark:text-slate-400">No activities added.</p>
    <template x-for="(activity, index) in <?php echo e($section); ?>" :key="activity.id">
        <fieldset class="space-y-4 border-b border-gray-100 p-5 dark:border-slate-800" data-quarterly-activity="<?php echo e($section); ?>">
            <legend class="sr-only" x-text="`<?php echo e($title); ?> row ${index + 1}`"></legend>
            <input type="hidden" :name="`<?php echo e($section); ?>[${index}][id]`" :value="activity.id">
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-semibold text-gray-700 dark:text-slate-300" x-text="`Row ${index + 1}`"></p>
                <button type="button" @click="<?php echo e($section); ?>.splice(index, 1)" class="dashboard-action" :aria-label="`Remove <?php echo e($title); ?> row ${index + 1}`">Remove activity</button>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $fields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['sm:col-span-2' => $field === 'research_title']); ?>">
                        <label :for="`<?php echo e($section); ?>-<?php echo e($field); ?>-${activity.id}`" class="block text-sm font-medium text-gray-700 dark:text-slate-300"><?php echo e($label); ?></label>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($field === 'personnel'): ?>
                            <textarea :id="`<?php echo e($section); ?>-<?php echo e($field); ?>-${activity.id}`" :name="`<?php echo e($section); ?>[${index}][<?php echo e($field); ?>]`" x-model="activity.<?php echo e($field); ?>" maxlength="2000" rows="2" required class="<?php echo e($inputClass); ?>"></textarea>
                        <?php else: ?>
                            <input :id="`<?php echo e($section); ?>-<?php echo e($field); ?>-${activity.id}`" :name="`<?php echo e($section); ?>[${index}][<?php echo e($field); ?>]`" x-model="activity.<?php echo e($field); ?>"
                                <?php if($field === 'date'): ?> type="date" min="<?php echo e($period['start']->toDateString()); ?>" max="<?php echo e($period['end']->toDateString()); ?>" <?php else: ?> maxlength="1000" <?php endif; ?>
                                required class="<?php echo e($inputClass); ?>">
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <div class="sm:col-span-2">
                    <label :for="`<?php echo e($section); ?>-project-${activity.id}`" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Project folder (optional)</label>
                    <select :id="`<?php echo e($section); ?>-project-${activity.id}`" :name="`<?php echo e($section); ?>[${index}][project_id]`" x-model="activity.project_id" class="<?php echo e($inputClass); ?>">
                        <option value="">Create evidence folder using the research or activity title</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $projects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <option value="<?php echo e($project->id); ?>"><?php echo e($project->title); ?></option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </select>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Linked evidence goes into the project’s files. Other evidence stays in this report’s title folder, organised by year and quarter.</p>
                </div>
            </div>
            <div class="space-y-3 border-t border-gray-100 pt-4 dark:border-slate-800">
                <label :for="`<?php echo e($section); ?>-proof-${activity.id}`" class="block text-sm font-semibold text-gray-700 dark:text-slate-300">Proof for this row</label>
                <p class="text-xs text-slate-500 dark:text-slate-400" x-text="`Evidence folder: ${activity.research_title || activity.event || 'Research or activity title'} / <?php echo e($period['year']); ?> / Q<?php echo e($period['quarter']); ?>`"></p>
                <p :id="`<?php echo e($section); ?>-proof-help-${activity.id}`" class="text-xs text-slate-500 dark:text-slate-400">JPEG or PNG, up to 5 MB each; up to 5 images per row. At least one is required when submitting. Files must be selected again if validation fails.</p>
                <input :id="`<?php echo e($section); ?>-proof-${activity.id}`" :name="`row_evidence[<?php echo e($section); ?>][${activity.id}][]`" type="file" accept="image/jpeg,image/png" multiple :aria-describedby="`<?php echo e($section); ?>-proof-help-${activity.id}`" class="block max-w-full text-sm text-gray-700 dark:text-slate-300">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <template x-for="proof in rowProofs.filter(image => image.section === '<?php echo e($section); ?>' && image.row_id === activity.id)" :key="proof.token">
                        <figure class="space-y-2">
                            <a :href="proof.url" target="_blank" rel="noopener" class="block rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand"><img :src="proof.preview_url" :alt="proof.filename" loading="lazy" decoding="async" class="h-36 w-full rounded-lg object-contain"></a>
                            <figcaption class="break-words text-xs text-slate-500 dark:text-slate-400" x-text="proof.filename"></figcaption>
                            <div>
                                <label :for="`saved-evidence-caption-${proof.token}`" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Caption</label>
                                <textarea :id="`saved-evidence-caption-${proof.token}`" :name="`saved_evidence_captions[${proof.token}]`" x-model="proof.caption" rows="2" maxlength="1000" class="<?php echo e($inputClass); ?>"></textarea>
                            </div>
                            <label class="inline-flex items-center gap-2 text-sm text-red-700 dark:text-red-300"><input type="checkbox" name="remove_evidence[]" :value="proof.token" x-model="removedProofs" class="rounded border-gray-300 text-brand focus:ring-brand"> Remove proof when saving</label>
                        </figure>
                    </template>
                </div>
            </div>
        </fieldset>
    </template>
</section>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\college-quarterly-report-activities.blade.php ENDPATH**/ ?>