<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

    <?php ($canUseResearcherTools = Auth::user()->isUsingWorkspace('faculty_researcher')); ?>

     <?php $__env->slot('header', null, []); ?> 
        <?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['class' => 'athena-readable','title' => 'Research Support','subtitle' => $canUseResearcherTools ? 'Find literature, review similarity, and discover suitable journals for your research.' : 'Find and save literature while preparing your research proposal.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'athena-readable','title' => 'Research Support','subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($canUseResearcherTools ? 'Find literature, review similarity, and discover suitable journals for your research.' : 'Find and save literature while preparing your research proposal.')]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $attributes = $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $component = $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?>
     <?php $__env->endSlot(); ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Auth::user()->isUsingWorkspace(['faculty', 'faculty_researcher'])): ?>
        <div
            x-data="{
                activeResearchTool: 'rrl',
                syncActiveResearchTool() {
                    const researcherTools = {
                        '#turnitin': 'turnitin',
                        '#journal-finder': 'journal',
                    };

                    this.activeResearchTool = <?php echo \Illuminate\Support\Js::from($canUseResearcherTools)->toHtml() ?>
                        ? (researcherTools[window.location.hash] ?? 'rrl')
                        : (window.location.hash === '#turnitin' ? 'turnitin' : 'rrl');
                },
            }"
            x-init="syncActiveResearchTool()"
            @hashchange.window="syncActiveResearchTool()"
        >
            <div class="athena-readable mb-6 mt-6 overflow-x-auto rounded-xl border border-gray-200 bg-white p-1.5 dark:border-slate-800 dark:bg-slate-900">
                <nav class="flex min-w-max gap-1" aria-label="Research help tools" role="tablist">
                    <button
                        type="button"
                        role="tab"
                        aria-controls="rrl-finder"
                        :aria-selected="activeResearchTool === 'rrl'"
                        @click="window.location.hash = 'rrl-finder'"
                        :class="activeResearchTool === 'rrl' ? 'bg-gray-950 text-white shadow-sm dark:bg-white dark:text-gray-950' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-950 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white'"
                        class="flex items-center gap-2 rounded-lg px-4 py-2.5 text-xs font-bold transition"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.8 4.8 11 2l1.2 2.8L15 6l-2.8 1.2L11 10 9.8 7.2 7 6l2.8-1.2ZM16.9 13.9 18 11l1.1 2.9L22 15l-2.9 1.1L18 19l-1.1-2.9L14 15l2.9-1.1Z"></path></svg>
                        Literature search
                    </button>
                    <button
                        type="button"
                        role="tab"
                        aria-controls="turnitin"
                        :aria-selected="activeResearchTool === 'turnitin'"
                        @click="window.location.hash = 'turnitin'"
                        :class="activeResearchTool === 'turnitin' ? 'bg-gray-950 text-white shadow-sm dark:bg-white dark:text-gray-950' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-950 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white'"
                        class="flex items-center gap-2 rounded-lg px-4 py-2.5 text-xs font-bold transition"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 12.8 2.3 2.2L15 9.8M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"></path></svg>
                        Turnitin
                    </button>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canUseResearcherTools): ?>
                        <button
                            type="button"
                            role="tab"
                            aria-controls="journal-finder"
                            :aria-selected="activeResearchTool === 'journal'"
                            @click="window.location.hash = 'journal-finder'"
                            :class="activeResearchTool === 'journal' ? 'bg-gray-950 text-white shadow-sm dark:bg-white dark:text-gray-950' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-950 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white'"
                            class="flex items-center gap-2 rounded-lg px-4 py-2.5 text-xs font-bold transition"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 4.75A1.75 1.75 0 0 1 6.75 3h10.5A1.75 1.75 0 0 1 19 4.75v14.5A1.75 1.75 0 0 1 17.25 21H6.75A1.75 1.75 0 0 1 5 19.25V4.75Z"></path><path stroke-linecap="round" d="M8.5 7.5h7M8.5 11h7M8.5 14.5H13"></path></svg>
                            Journal Finder
                        </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </nav>
            </div>
            <div x-show="activeResearchTool === 'rrl'" x-cloak>
                <?php if (isset($component)) { $__componentOriginalf76a3fc183fafc77f87886634fb438e4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf76a3fc183fafc77f87886634fb438e4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.rrl-finder','data' => ['proposalDrafts' => $proposalDrafts,'literatureCollections' => $literatureCollections,'sharedLiteratureSources' => $sharedLiteratureSources]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('rrl-finder'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['proposal-drafts' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($proposalDrafts),'literature-collections' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($literatureCollections),'shared-literature-sources' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($sharedLiteratureSources)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf76a3fc183fafc77f87886634fb438e4)): ?>
<?php $attributes = $__attributesOriginalf76a3fc183fafc77f87886634fb438e4; ?>
<?php unset($__attributesOriginalf76a3fc183fafc77f87886634fb438e4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf76a3fc183fafc77f87886634fb438e4)): ?>
<?php $component = $__componentOriginalf76a3fc183fafc77f87886634fb438e4; ?>
<?php unset($__componentOriginalf76a3fc183fafc77f87886634fb438e4); ?>
<?php endif; ?>
            </div>

            <div x-show="activeResearchTool === 'turnitin'" x-cloak>
                <?php if (isset($component)) { $__componentOriginal2800249f814cf2eab178130d5c31d64a = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2800249f814cf2eab178130d5c31d64a = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.turnitin-resource','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('turnitin-resource'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2800249f814cf2eab178130d5c31d64a)): ?>
<?php $attributes = $__attributesOriginal2800249f814cf2eab178130d5c31d64a; ?>
<?php unset($__attributesOriginal2800249f814cf2eab178130d5c31d64a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2800249f814cf2eab178130d5c31d64a)): ?>
<?php $component = $__componentOriginal2800249f814cf2eab178130d5c31d64a; ?>
<?php unset($__componentOriginal2800249f814cf2eab178130d5c31d64a); ?>
<?php endif; ?>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canUseResearcherTools): ?>
                <div x-show="activeResearchTool === 'journal'" x-cloak class="athena-readable mb-6">
                    <?php if (isset($component)) { $__componentOriginalecd07a72f49f9557dc5b16fe7dc8b9c5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalecd07a72f49f9557dc5b16fe7dc8b9c5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.journal-finder','data' => ['endpoint' => route('research-support.journal-search'),'heading' => 'Find a journal for your paper','description' => 'Search by manuscript title, topic, or abstract. ATHENA recommends journals using related indexed articles and shows the evidence behind each match.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('journal-finder'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['endpoint' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('research-support.journal-search')),'heading' => 'Find a journal for your paper','description' => 'Search by manuscript title, topic, or abstract. ATHENA recommends journals using related indexed articles and shows the evidence behind each match.']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalecd07a72f49f9557dc5b16fe7dc8b9c5)): ?>
<?php $attributes = $__attributesOriginalecd07a72f49f9557dc5b16fe7dc8b9c5; ?>
<?php unset($__attributesOriginalecd07a72f49f9557dc5b16fe7dc8b9c5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalecd07a72f49f9557dc5b16fe7dc8b9c5)): ?>
<?php $component = $__componentOriginalecd07a72f49f9557dc5b16fe7dc8b9c5; ?>
<?php unset($__componentOriginalecd07a72f49f9557dc5b16fe7dc8b9c5); ?>
<?php endif; ?>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/faculty/research_support/index.blade.php ENDPATH**/ ?>