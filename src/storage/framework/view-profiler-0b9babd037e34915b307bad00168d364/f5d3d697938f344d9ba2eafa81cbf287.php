<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['reader', 'openOnLoad' => false]));

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

foreach (array_filter((['reader', 'openOnLoad' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $downloadUrl = $reader['pdf_url'].(str_contains($reader['pdf_url'], '?') ? '&' : '?').'download=1';
    $modalName = 'research-manual-preview-'.$reader['version'];
    $hasSummary = isset($summary);
?>

<div <?php echo e($attributes->merge(['class' => 'space-y-5'])); ?> x-data="researchManualReader(<?php echo \Illuminate\Support\Js::from($reader)->toHtml() ?>, <?php echo \Illuminate\Support\Js::from($openOnLoad)->toHtml() ?>)" x-id="['manual-question']" data-research-manual-reader>
    <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['grid grid-cols-[5rem_minmax(0,1fr)] items-start gap-4 lg:grid-cols-[9rem_minmax(0,1fr)] lg:gap-x-6' => $hasSummary, 'flex flex-col items-start gap-5 sm:flex-row' => ! $hasSummary]); ?>">
        <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['w-full sm:row-span-3' => $hasSummary, 'contents' => ! $hasSummary]); ?>">
            <?php if (isset($component)) { $__componentOriginal705afa55b28306e08e85a5bbc410f198 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal705afa55b28306e08e85a5bbc410f198 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.submitted-report-document','data' => ['name' => 'manual-paper-'.$reader['version'],'modalName' => $modalName,'title' => $reader['title'],'previewUrl' => $reader['pdf_url'],'downloadUrl' => $downloadUrl,'viewerLabel' => 'Research manual PDF','loadingLabel' => 'Loading manual…']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('submitted-report-document'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('manual-paper-'.$reader['version']),'modal-name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($modalName),'title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($reader['title']),'preview-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($reader['pdf_url']),'download-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($downloadUrl),'viewer-label' => 'Research manual PDF','loading-label' => 'Loading manual…']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal705afa55b28306e08e85a5bbc410f198)): ?>
<?php $attributes = $__attributesOriginal705afa55b28306e08e85a5bbc410f198; ?>
<?php unset($__attributesOriginal705afa55b28306e08e85a5bbc410f198); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal705afa55b28306e08e85a5bbc410f198)): ?>
<?php $component = $__componentOriginal705afa55b28306e08e85a5bbc410f198; ?>
<?php unset($__componentOriginal705afa55b28306e08e85a5bbc410f198); ?>
<?php endif; ?>
        </div>
        <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['contents' => $hasSummary, 'space-y-3 sm:pt-2' => ! $hasSummary]); ?>">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($summary)): ?>
                <div class="min-w-0"><?php echo e($summary); ?></div>
            <?php else: ?>
                <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">Open the paper to read and search the complete manual.</p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['flex flex-wrap gap-2', 'col-span-2 sm:col-span-1 sm:col-start-2' => $hasSummary]); ?>">
                <button type="button" @click="openPreview()" data-manual-preview-button aria-haspopup="dialog" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['workspace-primary' => $hasSummary, 'workspace-secondary' => ! $hasSummary]); ?>">
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.5-6.75 9.75-6.75 9.75 6.75 9.75 6.75-3.5 6.75-9.75 6.75S2.25 12 2.25 12Z" /><circle cx="12" cy="12" r="3" /></svg>
                    Preview manual
                </button>
                <a href="<?php echo e($downloadUrl); ?>" data-manual-download class="workspace-secondary">
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m-4-4 4 4 4-4M5 16v4h14v-4" /></svg>
                    Download PDF
                </a>
                <?php echo e($actions ?? ''); ?>

            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($note)): ?>
                <div class="col-span-2 sm:col-span-1 sm:col-start-2"><?php echo e($note); ?></div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

    <?php if (isset($component)) { $__componentOriginal66cb43065a62ebef588707752044cfef = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal66cb43065a62ebef588707752044cfef = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.submitted-report-preview','data' => ['name' => $modalName,'title' => $reader['title'],'description' => $reader['edition'],'previewUrl' => $reader['pdf_url'],'downloadUrl' => $downloadUrl,'viewerLabel' => 'Research manual PDF','loadingLabel' => 'Loading manual…','searchable' => true,'initialPageExpression' => 'manualPage','dataResearchManualPreviewModal' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('submitted-report-preview'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($modalName),'title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($reader['title']),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($reader['edition']),'preview-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($reader['pdf_url']),'download-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($downloadUrl),'viewer-label' => 'Research manual PDF','loading-label' => 'Loading manual…','searchable' => true,'initial-page-expression' => 'manualPage','data-research-manual-preview-modal' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal66cb43065a62ebef588707752044cfef)): ?>
<?php $attributes = $__attributesOriginal66cb43065a62ebef588707752044cfef; ?>
<?php unset($__attributesOriginal66cb43065a62ebef588707752044cfef); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal66cb43065a62ebef588707752044cfef)): ?>
<?php $component = $__componentOriginal66cb43065a62ebef588707752044cfef; ?>
<?php unset($__componentOriginal66cb43065a62ebef588707752044cfef); ?>
<?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reader['guidance_url']): ?>
        <details class="border-t border-slate-200 dark:border-slate-800" data-manual-guidance>
            <summary class="disclosure-summary px-5 py-5 text-sm font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-red-600 dark:text-white sm:px-6">Check guidance from this edition</summary>
            <div class="space-y-4 px-5 pb-6 sm:px-6">
                <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">Test chatbot answers using only this edition’s indexed passages. Review each AI answer and its citations; completing a check does not approve the answer or activate the manual. The latest ten checks remain on this page until you leave.</p>
                <div class="flex flex-wrap gap-2" aria-label="Sample guidance questions">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $reader['guidance_questions'] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $question): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <button type="button" x-on:click="askGuidance(<?php echo \Illuminate\Support\Js::from($question)->toHtml() ?>)" x-bind:disabled="guidanceLoading || suiteRunning" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-left text-xs font-semibold text-slate-600 hover:border-red-300 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:opacity-50 dark:border-slate-700 dark:text-slate-300 dark:hover:text-red-300"><?php if (isset($component)) { $__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.assistant-icon','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('assistant-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b)): ?>
<?php $attributes = $__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b; ?>
<?php unset($__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b)): ?>
<?php $component = $__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b; ?>
<?php unset($__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b); ?>
<?php endif; ?><?php echo e($question); ?></button>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($reader['guidance_questions'] ?? []) !== []): ?>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" x-on:click="runGuidanceChecks()" x-bind:disabled="guidanceLoading || suiteRunning" class="workspace-secondary gap-2 disabled:opacity-50" data-manual-run-checks><?php if (isset($component)) { $__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.assistant-icon','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('assistant-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b)): ?>
<?php $attributes = $__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b; ?>
<?php unset($__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b)): ?>
<?php $component = $__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b; ?>
<?php unset($__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b); ?>
<?php endif; ?>Run <?php echo e(count($reader['guidance_questions'])); ?> suggested checks</button>
                        <button type="button" x-show="suiteRunning" x-cloak x-on:click="stopGuidanceChecks()" class="workspace-secondary">Stop checks</button>
                        <p x-show="suiteProgress" x-text="suiteProgress" class="text-sm text-slate-500 dark:text-slate-400" role="status"></p>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <form x-on:submit.prevent="if (!suiteRunning) askGuidance()" class="space-y-2" data-manual-guidance-form>
                    <label x-bind:for="$id('manual-question')" class="block text-sm font-semibold text-slate-800 dark:text-slate-200">Ask about this edition</label>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <input x-bind:id="$id('manual-question')" data-manual-question x-model="question" x-bind:disabled="suiteRunning" type="text" maxlength="1500" required placeholder="Enter a research policy question" class="min-h-11 min-w-0 flex-1 rounded-lg border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-red-600 focus:ring-red-600 disabled:opacity-60 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <button type="submit" x-bind:disabled="guidanceLoading || suiteRunning || !question.trim()" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-slate-900"><?php if (isset($component)) { $__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.assistant-icon','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('assistant-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b)): ?>
<?php $attributes = $__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b; ?>
<?php unset($__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b)): ?>
<?php $component = $__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b; ?>
<?php unset($__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b); ?>
<?php endif; ?><span x-text="guidanceLoading ? 'Checking…' : 'Check guidance'">Check guidance</span></button>
                    </div>
                </form>
                <p x-show="guidanceLoading" x-cloak role="status" class="text-sm text-slate-500 dark:text-slate-400">Checking this edition’s guidance…</p>
                <p x-show="guidanceError" x-cloak x-text="guidanceError" role="alert" class="text-sm text-red-700 dark:text-red-300"></p>
                <div class="space-y-4" data-manual-guidance-result aria-live="polite">
                    <template x-for="check in guidanceChecks" x-bind:key="check.question">
                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950/50">
                            <h3 x-text="check.question" class="text-sm font-bold text-slate-900 dark:text-white"></h3>
                            <p class="mt-1 text-xs font-bold text-slate-500 dark:text-slate-400">Edition <span x-text="check.edition"></span> · <span x-text="check.type === 'ai_response' ? 'AI answer — review cited passages' : (check.type === 'no_evidence' ? 'Insufficient indexed evidence' : 'Check unavailable')"></span></p>
                            <p x-show="check.error" x-text="check.error" class="mt-2 text-sm text-red-700 dark:text-red-300"></p>
                            <p x-text="check.reply" class="mt-2 whitespace-pre-wrap break-words text-sm leading-7 text-slate-700 dark:text-slate-200"></p>
                            <ul x-show="check.sources.length" class="mt-4 space-y-2" aria-label="Manual sources">
                                <template x-for="(source, index) in check.sources" x-bind:key="index">
                                    <li class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
                                        <a x-bind:href="source.url" x-on:click.prevent="openPreview(source.page_start)" class="secondary-action"><span x-text="source.reference || ('ATHENA ' + (index + 1))"></span><span class="mx-1" aria-hidden="true">·</span><span x-text="sourceLabel(source)"></span></a>
                                        <p x-text="source.content || source.excerpt || 'Open the cited PDF page to inspect this passage.'" class="whitespace-pre-wrap break-words text-sm leading-6 text-slate-600 dark:text-slate-300"></p>
                                    </li>
                                </template>
                            </ul>
                        </article>
                    </template>
                </div>
            </div>
        </details>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\research-manual-reader.blade.php ENDPATH**/ ?>