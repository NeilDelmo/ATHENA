<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['topic', 'library', 'floating' => true, 'inline' => false]));

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

foreach (array_filter((['topic', 'library', 'floating' => true, 'inline' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $projectDocumentErrors = $errors->getBag('projectDocuments');
    $initialOpen = (bool) session('project_documents_open') || $projectDocumentErrors->any();
    $categories = $library['categories'];
    $documents = $library['documents'];
    $canUpload = $library['canUpload'];
?>

<div
    x-data="projectDocumentDrawer({ topicId: <?php echo \Illuminate\Support\Js::from($topic->id)->toHtml() ?>, documents: <?php echo \Illuminate\Support\Js::from($documents->map(fn (array $document) => \Illuminate\Support\Arr::only($document, ['key', 'filename', 'view_url', 'download_url']))->values())->toHtml() ?>, inline: <?php echo \Illuminate\Support\Js::from($inline)->toHtml() ?>, initialOpen: <?php echo \Illuminate\Support\Js::from($initialOpen)->toHtml() ?>, uploadOpen: <?php echo \Illuminate\Support\Js::from($projectDocumentErrors->any())->toHtml() ?>, displayStorageKey: <?php echo \Illuminate\Support\Js::from('athena-report-library:'.auth()->id())->toHtml() ?> })"
    data-workspace-sizing data-size="medium" :data-size="size"
    @open-project-documents.window="openDrawer($event.detail?.category || 'all')"
    @keydown.escape.window="if (open && !document.querySelector('[data-project-document-preview-content]')) closeDrawer()"
>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($inline)): ?>
    <button
        x-ref="trigger"
        type="button"
        @click="openDrawer()"
        class="<?php echo e($floating ? 'fixed top-[7.625rem] right-4 z-40 rounded-full shadow-lg shadow-red-950/20 sm:right-5' : 'rounded-xl whitespace-nowrap'); ?> inline-flex min-h-12 items-center gap-2 bg-red-700 px-3.5 py-2.5 text-base font-black text-white transition hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 motion-reduce:transition-none"
        aria-label="Open project files"
        title="Open project files"
        <?php if($floating): ?> data-project-documents-floating-trigger <?php else: ?> data-project-documents-inline-trigger <?php endif; ?>
    >
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75A2.25 2.25 0 0 1 6 4.5h3.19c.597 0 1.17.237 1.591.659l1.06 1.06c.422.422.994.659 1.591.659H18A2.25 2.25 0 0 1 20.25 9.13v7.62A2.25 2.25 0 0 1 18 19H6a2.25 2.25 0 0 1-2.25-2.25v-10Z" />
        </svg>
        <span class="<?php echo e($floating ? 'hidden sm:inline' : ''); ?>"><?php echo e($floating ? 'Folders' : 'Open project folders'); ?></span>
        <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-white/20 px-1.5 py-0.5 text-base tabular-nums"><?php echo e($categories->count()); ?></span>
    </button>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div x-cloak x-show="open" class="<?php echo e($inline ? 'overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900' : 'fixed inset-0 z-[70]'); ?>" <?php if (! ($inline)): ?> role="presentation" <?php endif; ?> data-project-document-drawer data-project-document-browser>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($inline)): ?>
        <div
            x-show="open"
            x-transition:enter="transition-opacity duration-200 ease-out motion-reduce:transition-none"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity duration-150 ease-in motion-reduce:transition-none"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute inset-0 bg-gray-950/45 backdrop-blur-[1px]"
            @click="closeDrawer()"
            aria-hidden="true"
        ></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <aside
            x-show="open"
            <?php if (! ($inline)): ?>
            x-transition:enter="transition-transform duration-200 ease-out motion-reduce:transition-none"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition-transform duration-150 ease-in motion-reduce:transition-none"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            <?php endif; ?>
            class="<?php echo e($inline ? 'flex w-full flex-col' : 'absolute inset-y-0 right-0 flex w-full max-w-4xl flex-col border-l border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-slate-900'); ?>"
            <?php if (! ($inline)): ?>
            role="dialog"
            aria-modal="true"
            <?php endif; ?>
            aria-labelledby="project-files-heading"
        >
            <header class="shrink-0 border-b border-slate-200 bg-white px-5 py-5 dark:border-slate-700 dark:bg-slate-900 sm:px-6">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex min-w-0 items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75A2.25 2.25 0 0 1 6 4.5h3.19c.597 0 1.17.237 1.591.659l1.06 1.06c.422.422.994.659 1.591.659H18A2.25 2.25 0 0 1 20.25 9.13v7.62A2.25 2.25 0 0 1 18 19H6a2.25 2.25 0 0 1-2.25-2.25v-10Z" /></svg>
                        </span>
                        <div class="min-w-0">
                            <h2 id="project-files-heading" class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">Project folders</h2>
                            <p class="mt-2 max-w-[65ch] text-base leading-7 text-slate-600 dark:text-slate-300"><?php echo e($categories->count()); ?> <?php echo e(\Illuminate\Support\Str::plural('folder', $categories->count())); ?> · <?php echo e($library['total']); ?> <?php echo e(\Illuminate\Support\Str::plural('document', $library['total'])); ?></p>
                        </div>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($inline)): ?>
                    <button x-ref="closeButton" type="button" @click="closeDrawer()" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600 transition hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800" aria-label="Close project files">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                    </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <nav class="mt-4 flex min-w-0 flex-wrap items-center gap-2" aria-label="Project file location">
                    <button type="button" class="workspace-secondary" :disabled="activeCategory === 'all'" @click="openCategory()" aria-label="Up to project folders">↑ Up</button>
                    <button type="button" class="workspace-secondary" @click="openCategory()">Project folders</button>
                    <span x-show="activeCategory !== 'all'" class="min-w-0 text-base text-slate-600 dark:text-slate-300" x-text="' / ' + ((<?php echo \Illuminate\Support\Js::from($categories->pluck('label', 'key'))->toHtml() ?>)[activeCategory] || '')"></span>
                </nav>
                <?php if (isset($component)) { $__componentOriginal10c6cd96f1f6fea5c28e00737f6b6532 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal10c6cd96f1f6fea5c28e00737f6b6532 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.report-library-view-controls','data' => ['class' => 'mt-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('report-library-view-controls'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'mt-4']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal10c6cd96f1f6fea5c28e00737f6b6532)): ?>
<?php $attributes = $__attributesOriginal10c6cd96f1f6fea5c28e00737f6b6532; ?>
<?php unset($__attributesOriginal10c6cd96f1f6fea5c28e00737f6b6532); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal10c6cd96f1f6fea5c28e00737f6b6532)): ?>
<?php $component = $__componentOriginal10c6cd96f1f6fea5c28e00737f6b6532; ?>
<?php unset($__componentOriginal10c6cd96f1f6fea5c28e00737f6b6532); ?>
<?php endif; ?>
            </header>

            <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['px-4 py-5 sm:px-6', 'min-h-0 flex-1 overflow-y-auto overscroll-contain' => ! $inline]); ?>">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canUpload): ?>
                    <section class="mb-5 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900" aria-labelledby="add-project-files-heading">
                        <button type="button" @click="uploadOpen = !uploadOpen" :aria-expanded="uploadOpen" aria-controls="project-file-upload" class="flex min-h-14 w-full items-center justify-between gap-4 px-4 py-3 text-left transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-red-700 dark:hover:bg-gray-800 sm:px-5">
                            <span>
                                <span id="add-project-files-heading" class="block text-base font-semibold text-gray-950 dark:text-white">Add PDF files</span>
                                <span class="mt-0.5 block text-base text-gray-500 dark:text-gray-400">For corrected forms, supporting papers, and project records.</span>
                            </span>
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300" aria-hidden="true">
                                <svg :class="uploadOpen ? 'rotate-45' : ''" class="h-4 w-4 transition-transform motion-reduce:transition-none" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                            </span>
                        </button>

                        <form id="project-file-upload" x-show="uploadOpen" x-cloak action="<?php echo e(route('topics.documents.store', $topic)); ?>" method="POST" enctype="multipart/form-data" class="space-y-4 border-t border-gray-100 p-4 dark:border-gray-800 sm:p-5">
                            <?php echo csrf_field(); ?>
                            <div>
                                <label for="project_document_category" class="text-base font-semibold text-gray-700 dark:text-gray-200">Category</label>
                                <select id="project_document_category" name="category" required class="mt-1.5 block w-full rounded-xl border-gray-300 text-base shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $library['uploadCategories']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <option value="<?php echo e($value); ?>" <?php if(old('category', \App\Models\ProjectDocument::CATEGORY_SUPPORTING_FILES) === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </select>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['category', 'projectDocuments'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-1 text-base font-semibold text-red-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>

                            <label
                                class="block cursor-pointer rounded-2xl border-2 border-dashed p-5 text-center transition focus-within:ring-2 focus-within:ring-red-700 focus-within:ring-offset-2"
                                :class="isDragging ? 'border-red-600 bg-red-50 dark:bg-red-950/30' : 'border-gray-300 bg-gray-50 hover:border-red-300 hover:bg-red-50/40 dark:border-gray-700 dark:bg-gray-950 dark:hover:border-red-800'"
                                @dragenter.prevent="isDragging = true"
                                @dragover.prevent="isDragging = true"
                                @dragleave.prevent="isDragging = false"
                                @drop.prevent="dropFiles($event)"
                            >
                                <input x-ref="fileInput" type="file" name="documents[]" accept="application/pdf,.pdf" multiple required class="sr-only" @change="chooseFiles($event)">
                                <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-white text-red-700 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:text-red-300 dark:ring-gray-700" aria-hidden="true">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V6.75m0 0-3.75 3.75M12 6.75l3.75 3.75M6.75 18.75h10.5A2.25 2.25 0 0 0 19.5 16.5v-9a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                                </span>
                                <span class="mt-3 block text-base font-semibold text-gray-900 dark:text-white">Drop PDFs here or browse</span>
                                <span class="mt-1 block text-base text-gray-500 dark:text-gray-400">Up to 10 PDFs, 25 MB each</span>
                            </label>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['documents', 'projectDocuments'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-base font-semibold text-red-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['documents.*', 'projectDocuments'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="text-base font-semibold text-red-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <p x-show="fileError" x-text="fileError" class="text-base font-semibold text-red-600" role="alert"></p>

                            <div x-show="selectedFiles.length > 0" x-cloak class="space-y-2">
                                <template x-for="(file, index) in selectedFiles" :key="`${file.name}-${file.size}-${index}`">
                                    <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 dark:border-gray-700 dark:bg-gray-950">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-red-50 text-base font-black text-red-700 dark:bg-red-950/50 dark:text-red-300">PDF</span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-base font-semibold text-gray-900 dark:text-white" x-text="file.name"></span>
                                            <span class="block text-base text-gray-500" x-text="formatSize(file.size)"></span>
                                        </span>
                                        <button type="button" @click="removeFile(index)" class="rounded-lg p-2 text-gray-400 transition hover:bg-white hover:text-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 dark:hover:bg-gray-800" :aria-label="`Remove ${file.name}`">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                                        </button>
                                    </div>
                                </template>
                            </div>

                            <div>
                                <label for="project_document_note" class="text-base font-semibold text-gray-700 dark:text-gray-200">Note <span class="font-normal text-gray-400">(optional)</span></label>
                                <textarea id="project_document_note" name="note" rows="2" maxlength="1000" class="mt-1.5 block w-full rounded-xl border-gray-300 text-base shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-gray-700 dark:bg-gray-950 dark:text-white" placeholder="Why these files are being kept with the project"><?php echo e(old('note')); ?></textarea>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['note', 'projectDocuments'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-1 text-base font-semibold text-red-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>

                            <button type="submit" :disabled="selectedFiles.length === 0" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-red-700 px-4 py-2.5 text-base font-semibold text-white transition hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-gray-300 dark:disabled:bg-gray-700" x-text="uploadButtonLabel()"></button>
                        </form>
                    </section>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($documents->isEmpty()): ?>
                    <div x-ref="browserHeading" data-library-view-surface tabindex="0" aria-label="Project folders and files" @wheel="resizeWithWheel($event)" @contextmenu="openViewMenu($event)" class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center dark:border-gray-700 dark:bg-gray-900">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-300" aria-hidden="true">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75A2.25 2.25 0 0 1 6 4.5h3.19c.597 0 1.17.237 1.591.659l1.06 1.06c.422.422.994.659 1.591.659H18A2.25 2.25 0 0 1 20.25 9.13v7.62A2.25 2.25 0 0 1 18 19H6a2.25 2.25 0 0 1-2.25-2.25v-10Z" /></svg>
                        </span>
                        <p class="mt-4 text-base font-black text-gray-900 dark:text-white">No project files yet</p>
                        <p class="mt-1 text-base leading-5 text-gray-500 dark:text-gray-400">Generated and released documents and terminal report posters will appear here automatically.</p>
                    </div>
                <?php else: ?>
                    <svg class="hidden" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <symbol id="project-files-folder-<?php echo e($topic->id); ?>" viewBox="0 0 80 64">
                                <path d="M5 13a5 5 0 0 1 5-5h20l7 7h33a5 5 0 0 1 5 5v32a5 5 0 0 1-5 5H10a5 5 0 0 1-5-5Z" fill="#D99417" />
                                <path d="M9 23h62v28H9Z" fill="#FFF4C7" />
                                <path d="M4 25a4 4 0 0 1 4-4h64a4 4 0 0 1 4 4l-4 29a4 4 0 0 1-4 4H12a4 4 0 0 1-4-4Z" fill="#F5C451" stroke="#D99417" stroke-width="1.5" />
                                <path d="M9 27h61" stroke="#FFE8A0" stroke-width="2" />
                            </symbol>
                            <symbol id="project-files-file-<?php echo e($topic->id); ?>" viewBox="0 0 56 64">
                                <path d="M8 2h27l13 13v45a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z" fill="#FFF" stroke="#94A3B8" stroke-width="1.5" />
                                <path d="M35 2v13h13M15 24h25M15 30h25M15 36h18" fill="none" stroke="#CBD5E1" stroke-width="2" />
                            </symbol>
                        </defs>
                    </svg>
                    <div x-ref="browserHeading" data-library-view-surface tabindex="0" aria-label="Project folders and files" @wheel="resizeWithWheel($event)" @contextmenu="openViewMenu($event)" class="outline-none">
                        <div x-show="activeCategory === 'all'" :class="view === 'grid' ? 'workspace-items-grid' : 'workspace-items-list'" aria-label="Project file categories">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <button type="button" data-project-file-category="<?php echo e($category['key']); ?>" aria-label="Select folder <?php echo e($category['label']); ?>" :aria-pressed="selectedItem === 'category:<?php echo e($category['key']); ?>'" class="workspace-item" :class="selectedItem === 'category:<?php echo e($category['key']); ?>' && 'workspace-selected'" @click="selectItem('category:<?php echo e($category['key']); ?>')" @dblclick="openCategory('<?php echo e($category['key']); ?>')" @keydown.enter.prevent="openCategory('<?php echo e($category['key']); ?>')">
                                    <svg class="workspace-item-graphic" aria-hidden="true"><use href="#project-files-folder-<?php echo e($topic->id); ?>" /></svg>
                                    <span class="workspace-item-name"><?php echo e($category['label']); ?></span>
                                    <span class="workspace-item-meta"><?php echo e($category['count']); ?> <?php echo e(\Illuminate\Support\Str::plural('file', $category['count'])); ?></span>
                                </button>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <section x-show="activeCategory === '<?php echo e($category['key']); ?>'" aria-labelledby="project-file-category-<?php echo e($category['key']); ?>">
                                <h3 id="project-file-category-<?php echo e($category['key']); ?>" class="text-lg font-semibold text-slate-950 dark:text-white"><?php echo e($category['label']); ?></h3>
                                <p class="mb-4 mt-1 text-base text-slate-500 dark:text-slate-400"><?php echo e($category['description']); ?></p>
                                <div :class="view === 'grid' ? 'workspace-items-grid' : 'workspace-items-list'">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $documents->where('category', $category['key']); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $document): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <?php ($isDocx = strtolower(pathinfo($document['filename'], PATHINFO_EXTENSION)) === 'docx'); ?>
                                        <article x-data="{ thumbnailFailed: false }" data-project-document-key="<?php echo e($document['key']); ?>" role="button" tabindex="0" aria-label="Select file <?php echo e($document['title']); ?>" :aria-pressed="selectedItem === '<?php echo e($document['key']); ?>'" class="workspace-item" :class="selectedItem === '<?php echo e($document['key']); ?>' && 'workspace-selected'" @click="selectItem('<?php echo e($document['key']); ?>')" @keydown.space.self.prevent="selectItem('<?php echo e($document['key']); ?>')"
                                            <?php if($document['view_url']): ?>
                                                @dblclick="$dispatch('open-modal', 'project-<?php echo e($topic->id); ?>-<?php echo e($document['key']); ?>')" @keydown.enter.self.prevent="$dispatch('open-modal', 'project-<?php echo e($topic->id); ?>-<?php echo e($document['key']); ?>')"
                                            <?php else: ?>
                                                @dblclick="window.location.assign(<?php echo \Illuminate\Support\Js::from($document['download_url'])->toHtml() ?>)" @keydown.enter.self.prevent="window.location.assign(<?php echo \Illuminate\Support\Js::from($document['download_url'])->toHtml() ?>)"
                                            <?php endif; ?>
                                        >
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($document['preview_kind'] ?? null) === 'image'): ?>
                                                <span x-show="!thumbnailFailed" class="workspace-image-graphic"><img src="<?php echo e($document['thumbnail_url']); ?>" alt="" loading="lazy" draggable="false" x-on:error="thumbnailFailed = true"></span>
                                                <span x-show="thumbnailFailed" class="workspace-file-graphic"><svg aria-hidden="true"><use href="#project-files-file-<?php echo e($topic->id); ?>" /></svg><span class="workspace-file-type">IMG</span></span>
                                            <?php else: ?>
                                                <span class="workspace-file-graphic"><svg aria-hidden="true"><use href="#project-files-file-<?php echo e($topic->id); ?>" /></svg><span class="workspace-file-type <?php echo e($isDocx ? '!bg-blue-700' : ''); ?>"><?php echo e($isDocx ? 'DOCX' : 'PDF'); ?></span></span>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            <h4 class="workspace-item-name" title="<?php echo e($document['filename']); ?>"><?php echo e($document['filename']); ?></h4>
                                            <span class="workspace-item-meta"><?php echo e($document['title']); ?></span>
                                            <span class="workspace-item-meta"><?php echo e($document['category'] === \App\Models\ProjectDocument::CATEGORY_SIGNED_PAPERS ? 'Signed' : ($document['official'] ? 'Official' : 'Project file')); ?></span>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($document['generated_comment_response'] ?? false): ?>
                                                <span class="col-start-2 text-base font-medium text-red-700 dark:text-red-300"><?php echo e($document['draft'] ? 'Draft · Research Head only' : 'Generated'); ?></span>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </article>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </div>
                            </section>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <section x-show="selectedItem === 'category:<?php echo e($category['key']); ?>'" class="mt-5 space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950">
                                <h3 class="text-base font-semibold text-slate-950 dark:text-white"><?php echo e($category['label']); ?></h3>
                                <p class="text-base text-slate-600 dark:text-slate-300"><?php echo e($category['description']); ?></p>
                                <button type="button" class="workspace-primary" @click="openCategory('<?php echo e($category['key']); ?>')">Open folder</button>
                            </section>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $documents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $document): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <section x-show="selectedItem === '<?php echo e($document['key']); ?>'" data-project-file-selection="<?php echo e($document['key']); ?>" class="mt-5 min-w-0 space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-4 text-left dark:border-slate-700 dark:bg-slate-950">
                                <h3 class="break-words text-base font-semibold text-slate-950 dark:text-white"><?php echo e($document['filename']); ?></h3>
                                <p class="text-base text-slate-700 dark:text-slate-200"><?php echo e($document['title']); ?></p>
                                <p class="text-base leading-6 text-slate-500 dark:text-slate-400"><?php echo e($document['source']); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($document['uploaded_by']): ?> · <?php echo e($document['uploaded_by']); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($document['uploaded_at']): ?> · <?php echo e($document['uploaded_at']->format(($document['generated_comment_response'] ?? false) ? 'M j, Y g:i A' : 'M j, Y')); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($document['file_size']): ?> · <?php echo e(\Illuminate\Support\Number::fileSize($document['file_size'])); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($document['note']): ?> <p class="text-base leading-6 text-slate-600 dark:text-slate-300"><?php echo e($document['note']); ?></p> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <div class="flex flex-wrap gap-2">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($document['view_url']): ?>
                                        <button type="button" data-project-document-preview-button aria-haspopup="dialog" @click="$dispatch('open-modal', 'project-<?php echo e($topic->id); ?>-<?php echo e($document['key']); ?>')" class="workspace-secondary">Preview</button>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($document['editable_docx_url'] ?? null): ?>
                                        <a href="<?php echo e($document['editable_docx_url']); ?>" class="workspace-secondary">Editable DOCX</a>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <a href="<?php echo e($document['download_url']); ?>" <?php if($document['generated_comment_response'] ?? false): ?> download="<?php echo e($document['filename']); ?>" <?php endif; ?> class="workspace-primary">Download</a>
                                </div>
                            </section>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <p class="mt-5 text-base leading-6 text-slate-500 dark:text-slate-400">Click to select. Double-click or press Enter to open a folder or document.</p>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </aside>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $documents->whereNotNull('view_url'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $document): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
        <?php if (isset($component)) { $__componentOriginal9f64f32e90b9102968f2bc548315018c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9f64f32e90b9102968f2bc548315018c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.modal','data' => ['name' => 'project-'.e($topic->id).'-'.e($document['key']).'','maxWidth' => '4xl','focusable' => true,'class' => '!z-[140]','dataProjectDocumentPreviewModal' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'project-'.e($topic->id).'-'.e($document['key']).'','maxWidth' => '4xl','focusable' => true,'class' => '!z-[140]','data-project-document-preview-modal' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

            <template x-if="show">
                <section data-project-document-preview-content role="dialog" aria-modal="true" aria-labelledby="project-<?php echo e($topic->id); ?>-<?php echo e($document['key']); ?>-heading">
                    <header class="flex items-center justify-between gap-4 border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                        <div>
                            <h3 id="project-<?php echo e($topic->id); ?>-<?php echo e($document['key']); ?>-heading" class="break-words text-lg font-bold text-gray-950 dark:text-white"><?php echo e($document['title']); ?></h3>
                            <p class="mt-1 break-words text-base text-gray-700 dark:text-gray-200"><?php echo e($document['filename']); ?></p>
                            <p class="mt-1 text-base text-gray-600 dark:text-gray-300"><?php echo e($document['source']); ?></p>
                        </div>
                        <button type="button" @click="$dispatch('close')" class="inline-flex min-h-11 shrink-0 items-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-base font-semibold text-gray-800 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:hover:bg-gray-800">Close</button>
                    </header>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($document['preview_kind'] ?? null) === 'image'): ?>
                        <div x-data="{ imageFailed: false }" class="flex min-h-64 items-center justify-center bg-gray-100 p-4 dark:bg-gray-950">
                            <img x-show="!imageFailed" x-on:error="imageFailed = true" src="<?php echo e($document['view_url']); ?>" alt="<?php echo e($document['note'] ?: $document['title']); ?>" class="max-h-[75dvh] max-w-full object-contain" data-project-document-image-preview>
                            <p x-show="imageFailed" role="alert" class="text-base text-slate-600 dark:text-slate-300">The image could not be loaded. Close the preview and use Download to open the original file.</p>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($document['note']): ?> <p class="border-t border-slate-200 px-4 py-3 text-base text-slate-600 dark:border-slate-700 dark:text-slate-300"><?php echo e($document['note']); ?></p> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php else: ?>
                        <?php if (isset($component)) { $__componentOriginal13c1f2a64fc7e3eb9a4f045c0d853353 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal13c1f2a64fc7e3eb9a4f045c0d853353 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-revision-pdf','data' => ['configuration' => ['pdfUrl' => $document['view_url'], 'annotations' => [], 'canAnnotate' => false],'loadingLabel' => 'Loading PDF…','viewerLabel' => $document['title'],'class' => '!h-[75dvh]']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-revision-pdf'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['configuration' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['pdfUrl' => $document['view_url'], 'annotations' => [], 'canAnnotate' => false]),'loading-label' => 'Loading PDF…','viewer-label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($document['title']),'class' => '!h-[75dvh]']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal13c1f2a64fc7e3eb9a4f045c0d853353)): ?>
<?php $attributes = $__attributesOriginal13c1f2a64fc7e3eb9a4f045c0d853353; ?>
<?php unset($__attributesOriginal13c1f2a64fc7e3eb9a4f045c0d853353); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal13c1f2a64fc7e3eb9a4f045c0d853353)): ?>
<?php $component = $__componentOriginal13c1f2a64fc7e3eb9a4f045c0d853353; ?>
<?php unset($__componentOriginal13c1f2a64fc7e3eb9a4f045c0d853353); ?>
<?php endif; ?>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </section>
            </template>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9f64f32e90b9102968f2bc548315018c)): ?>
<?php $attributes = $__attributesOriginal9f64f32e90b9102968f2bc548315018c; ?>
<?php unset($__attributesOriginal9f64f32e90b9102968f2bc548315018c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9f64f32e90b9102968f2bc548315018c)): ?>
<?php $component = $__componentOriginal9f64f32e90b9102968f2bc548315018c; ?>
<?php unset($__componentOriginal9f64f32e90b9102968f2bc548315018c); ?>
<?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\project-document-drawer.blade.php ENDPATH**/ ?>