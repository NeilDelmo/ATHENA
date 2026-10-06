<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['inputName', 'accept', 'multiple', 'required', 'stagedFile', 'label', 'documentType', 'fileErrors']));

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

foreach (array_filter((['inputName', 'accept', 'multiple', 'required', 'stagedFile', 'label', 'documentType', 'fileErrors']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div class="space-y-2 p-4">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($stagedFile): ?>
            <p x-show="files.length === 0" class="break-all text-xs font-medium text-emerald-800 dark:text-emerald-200">Automatically uploaded: <?php echo e($stagedFile->original_filename); ?></p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <label
            for="revision_<?php echo e($inputName); ?>"
            data-file-dropzone
            tabindex="0"
            @dragenter.prevent="dragEnter()"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragLeave()"
            @drop.prevent="drop($event)"
            @keydown.enter.prevent="browse()"
            @keydown.space.prevent="browse()"
            :class="dragging ? 'border-red-500 bg-red-50 dark:bg-red-950/40' : 'border-gray-200 bg-gray-50 dark:border-slate-700 dark:bg-slate-950'"
            class="flex min-h-20 cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border border-dashed p-3 text-center transition hover:border-red-400 focus:outline-none focus:ring-2 focus:ring-red-500"
        >
            <span class="sr-only">Replace <?php echo e($label); ?></span>
            <input
                id="revision_<?php echo e($inputName); ?>"
                x-ref="input"
                name="<?php echo e($inputName); ?><?php echo e($multiple ? '[]' : ''); ?>"
                type="file"
                accept="<?php echo e($accept); ?>"
                <?php if($multiple): ?> multiple <?php endif; ?>
                <?php if($required && ! $stagedFile): echo 'required'; endif; ?>
                @change="syncFiles(true)"
                class="sr-only"
            >
            <span x-show="files.length === 0" class="text-xs font-semibold text-gray-700 dark:text-slate-200">Choose or drop replacement <?php echo e($multiple ? 'files' : 'file'); ?></span>
            <span x-show="files.length > 0" x-cloak class="text-xs font-semibold text-gray-700 dark:text-slate-200"><?php echo e($multiple ? 'Add replacement files' : 'Choose a different file'); ?></span>
            <span class="text-[11px] text-gray-500 dark:text-slate-400"><?php echo e($documentType === 'expense_breakdown' ? 'PDF' : 'DOC, DOCX or PDF'); ?> · Up to 25 MB<?php echo e($multiple ? ' each' : ''); ?></span>
        </label>
        <ul x-show="files.length > 0" x-cloak class="space-y-2" aria-label="Selected replacement files">
            <template x-for="(file, index) in files" :key="file.name + '-' + file.size + '-' + file.lastModified">
                <li class="flex items-center justify-between gap-3 text-xs">
                    <span class="min-w-0 truncate text-gray-600 dark:text-slate-300" x-text="file.name"></span>
                    <button type="button" @click="remove(index)" :aria-label="'Remove ' + file.name" class="shrink-0 font-semibold text-red-700 hover:underline dark:text-red-300">Remove</button>
                </li>
            </template>
        </ul>
        <p x-show="message" x-cloak role="alert" class="text-xs font-semibold text-red-700 dark:text-red-300" x-text="message"></p>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = \Illuminate\Support\Arr::flatten($fileErrors); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fileError): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <p role="alert" class="text-xs font-semibold text-red-700 dark:text-red-300"><?php echo e($fileError); ?></p>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/components/proposal-revision-upload.blade.php ENDPATH**/ ?>