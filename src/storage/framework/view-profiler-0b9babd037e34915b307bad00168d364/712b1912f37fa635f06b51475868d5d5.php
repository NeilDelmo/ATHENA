<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['period']));

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

foreach (array_filter((['period']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $fields = ['title' => 'Title of IP asset', 'type' => 'Type of IP asset', 'inventors' => 'Inventors / authors', 'date' => 'Date Submitted', 'remarks' => 'Remarks (optional)', 'evidence_url' => 'Evidence link (optional)'];
    $inputClass = 'mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-950 dark:text-white';
?>

<section class="dashboard-panel overflow-hidden" aria-labelledby="ip-assets-heading">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-5 dark:border-slate-800">
        <div>
            <h2 id="ip-assets-heading" class="text-lg font-bold text-gray-950 dark:text-white">Intellectual Property Assets</h2>
            <p class="mt-1 max-w-3xl text-sm text-slate-500 dark:text-slate-400">Add IP assets submitted during this quarter for the Research Head’s monitoring report. Leave empty if none.</p>
        </div>
        <button type="button" class="dashboard-action" @click="addActivity('ip_assets')" :disabled="ip_assets.length >= 100">Add IP asset</button>
    </div>
    <p x-show="!ip_assets.length" class="p-5 text-sm text-slate-500 dark:text-slate-400">No IP assets added.</p>
    <template x-for="(asset, index) in ip_assets" :key="asset.id">
        <fieldset class="space-y-4 border-b border-gray-100 p-5 dark:border-slate-800" data-quarterly-ip-asset>
            <legend class="sr-only" x-text="`IP asset ${index + 1}`"></legend>
            <input type="hidden" :name="`ip_assets[${index}][id]`" :value="asset.id">
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-semibold text-gray-700 dark:text-slate-300" x-text="`IP asset ${index + 1}`"></p>
                <button type="button" @click="ip_assets.splice(index, 1)" class="dashboard-action" :aria-label="`Remove IP asset ${index + 1}`">Remove IP asset</button>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $fields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['sm:col-span-2' => in_array($field, ['title', 'remarks', 'evidence_url'], true)]); ?>">
                        <label :for="`ip-<?php echo e($field); ?>-${asset.id}`" class="block text-sm font-medium text-gray-700 dark:text-slate-300"><?php echo e($label); ?></label>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($field === 'type'): ?>
                            <select :id="`ip-<?php echo e($field); ?>-${asset.id}`" :name="`ip_assets[${index}][<?php echo e($field); ?>]`" x-model="asset.<?php echo e($field); ?>" required class="<?php echo e($inputClass); ?>">
                                <option value="">Select IP type</option>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = \App\Models\CollegeQuarterlyReport::IP_ASSET_TYPES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <option value="<?php echo e($type); ?>"><?php echo e($type); ?></option>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </select>
                        <?php elseif(in_array($field, ['inventors', 'remarks'], true)): ?>
                            <textarea :id="`ip-<?php echo e($field); ?>-${asset.id}`" :name="`ip_assets[${index}][<?php echo e($field); ?>]`" x-model="asset.<?php echo e($field); ?>" rows="2" maxlength="2000" <?php if($field === 'inventors'): echo 'required'; endif; ?> class="<?php echo e($inputClass); ?>"></textarea>
                        <?php else: ?>
                            <input :id="`ip-<?php echo e($field); ?>-${asset.id}`" :name="`ip_assets[${index}][<?php echo e($field); ?>]`" x-model="asset.<?php echo e($field); ?>"
                                <?php if($field === 'date'): ?> type="date" min="<?php echo e($period['start']->toDateString()); ?>" max="<?php echo e($period['end']->toDateString()); ?>"
                                <?php elseif($field === 'evidence_url'): ?> type="url" maxlength="2048" placeholder="https://..."
                                <?php else: ?> maxlength="1000" <?php endif; ?>
                                <?php if($field !== 'evidence_url'): echo 'required'; endif; ?> class="<?php echo e($inputClass); ?>">
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        </fieldset>
    </template>
</section>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\college-quarterly-report-ip-assets.blade.php ENDPATH**/ ?>