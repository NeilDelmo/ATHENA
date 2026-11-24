<button
    id="research-assistant-launcher"
    x-cloak
    x-show="!$store.researchAssistant.workspaceOpen"
    type="button"
    @click="$store.researchAssistant.toggleDrawer($event.currentTarget)"
    :aria-expanded="$store.researchAssistant.drawerOpen"
    :aria-label="$store.researchAssistant.drawerOpen ? 'Close Athena AI research assistant' : 'Open Athena AI research assistant'"
    aria-controls="research-assistant-panel"
    aria-label="Open Athena AI research assistant"
    class="group fixed bottom-[calc(1rem+env(safe-area-inset-bottom))] right-[calc(1rem+env(safe-area-inset-right))] z-[71] inline-flex h-14 w-14 items-center justify-center rounded-full bg-gray-900 dark:bg-slate-200 dark:text-slate-900 text-white shadow-lg shadow-gray-950/20 transition hover:bg-gray-700 dark:hover:bg-white hover:shadow-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-4 dark:focus-visible:ring-offset-slate-950 sm:bottom-[calc(1.5rem+env(safe-area-inset-bottom))] sm:right-[calc(1.5rem+env(safe-area-inset-right))] print:hidden"
>
    <span x-show="!$store.researchAssistant.drawerOpen" class="pointer-events-none absolute right-full mr-3 whitespace-nowrap rounded-lg bg-gray-900 px-3 py-2 text-xs font-semibold text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100 dark:bg-slate-700" aria-hidden="true">Ask ATHENA</span>
    <?php if (isset($component)) { $__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.assistant-icon','data' => ['class' => 'h-6 w-6','xShow' => '!$store.researchAssistant.drawerOpen']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('assistant-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'h-6 w-6','x-show' => '!$store.researchAssistant.drawerOpen']); ?>
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
<?php endif; ?>
    <svg x-show="$store.researchAssistant.drawerOpen" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18" />
    </svg>
</button>

<div
    x-cloak
    x-show="$store.researchAssistant.drawerOpen"
    @keydown.escape.window="if ($store.researchAssistant.drawerOpen && !$store.researchAssistant.sourcePreviewOpen && !$event.defaultPrevented) $store.researchAssistant.closeDrawer()"
    class="athena-readable pointer-events-none fixed inset-0 z-[70] print:hidden"
>
    <aside
        id="research-assistant-panel"
        x-show="$store.researchAssistant.drawerOpen"
        x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none"
        x-transition:enter-start="opacity-0 translate-y-4 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 scale-95"
        role="dialog"
        aria-labelledby="research-assistant-drawer-title"
        class="athena-chat-panel pointer-events-auto absolute bottom-[calc(5.25rem+env(safe-area-inset-bottom))] left-[calc(1rem+env(safe-area-inset-left))] right-[calc(1rem+env(safe-area-inset-right))] flex h-[42rem] max-h-[calc(100dvh-6.25rem-env(safe-area-inset-top)-env(safe-area-inset-bottom))] origin-bottom-right flex-col overflow-hidden rounded-[1.75rem] border border-gray-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900 sm:bottom-[calc(5.75rem+env(safe-area-inset-bottom))] sm:left-auto sm:right-[calc(1.5rem+env(safe-area-inset-right))] sm:max-h-[calc(100dvh-7.25rem-env(safe-area-inset-top)-env(safe-area-inset-bottom))] sm:w-[32rem]"
    >
        <header class="relative flex min-h-20 shrink-0 items-center justify-between border-b border-gray-100 px-4 dark:border-slate-800 sm:px-5">
            <div class="flex min-w-0 items-center gap-3">
                <div class="hidden h-9 w-9 shrink-0 items-center justify-center rounded-xl sm:flex bg-gray-900 text-white dark:bg-slate-200 dark:text-slate-900 shadow-sm">
                    <?php if (isset($component)) { $__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.assistant-icon','data' => ['class' => 'h-4 w-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('assistant-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'h-4 w-4']); ?>
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
<?php endif; ?>
                </div>
                <div class="min-w-0">
                    <h2 id="research-assistant-drawer-title" class="truncate text-lg font-medium text-gray-900 dark:text-white">Athena</h2>
                    <p class="hidden items-center gap-1.5 text-xs sm:flex text-gray-500 dark:text-slate-400" aria-live="polite"><span :class="$store.researchAssistant.isLoading ? 'animate-pulse bg-amber-400' : 'bg-emerald-500'" class="h-1.5 w-1.5 rounded-full"></span><span x-text="$store.researchAssistant.isLoading ? 'Thinking…' : 'Research assistant'"></span></p>
                </div>
            </div>
            <div class="flex items-center gap-1">
                <?php if (isset($component)) { $__componentOriginal3e4d603ea34c6bd9bc2c9c1522a704dd = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3e4d603ea34c6bd9bc2c9c1522a704dd = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.research-assistant-menu','data' => ['id' => 'drawer']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('research-assistant-menu'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'drawer']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal3e4d603ea34c6bd9bc2c9c1522a704dd)): ?>
<?php $attributes = $__attributesOriginal3e4d603ea34c6bd9bc2c9c1522a704dd; ?>
<?php unset($__attributesOriginal3e4d603ea34c6bd9bc2c9c1522a704dd); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal3e4d603ea34c6bd9bc2c9c1522a704dd)): ?>
<?php $component = $__componentOriginal3e4d603ea34c6bd9bc2c9c1522a704dd; ?>
<?php unset($__componentOriginal3e4d603ea34c6bd9bc2c9c1522a704dd); ?>
<?php endif; ?>

                <button x-show="!$store.researchAssistant.workspaceOpen" type="button" @click="$store.researchAssistant.openWorkspace()" class="inline-flex h-11 w-11 items-center justify-center rounded-xl text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-500 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Expand to full workspace" title="Expand to full workspace">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3m8 0h3a2 2 0 0 0 2-2v-3" /></svg>
                </button>

                <button type="button" @click="$store.researchAssistant.closeDrawer()" class="inline-flex h-11 w-11 items-center justify-center rounded-xl text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-500 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Close research assistant">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18" /></svg>
                </button>
            </div>
        </header>

        <div data-assistant-messages class="min-h-0 flex-1 overflow-y-auto overscroll-contain scroll-smooth" aria-live="polite" :aria-busy="$store.researchAssistant.isLoading">
            <?php if (isset($component)) { $__componentOriginal39eed6ba46356fe98254b5a51d29fe05 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal39eed6ba46356fe98254b5a51d29fe05 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.research-assistant-proposal-help','data' => ['compact' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('research-assistant-proposal-help'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['compact' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal39eed6ba46356fe98254b5a51d29fe05)): ?>
<?php $attributes = $__attributesOriginal39eed6ba46356fe98254b5a51d29fe05; ?>
<?php unset($__attributesOriginal39eed6ba46356fe98254b5a51d29fe05); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal39eed6ba46356fe98254b5a51d29fe05)): ?>
<?php $component = $__componentOriginal39eed6ba46356fe98254b5a51d29fe05; ?>
<?php unset($__componentOriginal39eed6ba46356fe98254b5a51d29fe05); ?>
<?php endif; ?>
            <div x-show="!$store.researchAssistant.proposalHelpOpen"><?php if (isset($component)) { $__componentOriginal33b9b1936de8b83e24d4080f7f664c88 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal33b9b1936de8b83e24d4080f7f664c88 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.research-assistant-welcome','data' => ['compact' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('research-assistant-welcome'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['compact' => true]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal33b9b1936de8b83e24d4080f7f664c88)): ?>
<?php $attributes = $__attributesOriginal33b9b1936de8b83e24d4080f7f664c88; ?>
<?php unset($__attributesOriginal33b9b1936de8b83e24d4080f7f664c88); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal33b9b1936de8b83e24d4080f7f664c88)): ?>
<?php $component = $__componentOriginal33b9b1936de8b83e24d4080f7f664c88; ?>
<?php unset($__componentOriginal33b9b1936de8b83e24d4080f7f664c88); ?>
<?php endif; ?></div>

            <div x-show="$store.researchAssistant.hasConversation()" x-cloak class="px-4 py-6 sm:px-6">
                <template x-for="message in $store.researchAssistant.messages" :key="message.id">
                    <article :class="message.role === 'user' ? 'justify-end' : 'justify-start'" class="mb-6 flex">
                        <div data-assistant-user-message x-show="message.role === 'user'" class="max-w-[85%] rounded-3xl bg-gray-100 px-4 py-3 text-sm leading-6 text-gray-800 dark:bg-slate-800 dark:text-slate-100"><p class="whitespace-pre-wrap" x-text="message.content"></p></div>
                        <div x-show="message.role === 'assistant'" class="flex w-full gap-3">
                            <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-900 text-white dark:bg-slate-200 dark:text-slate-900"><?php if (isset($component)) { $__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.assistant-icon','data' => ['class' => 'h-4 w-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('assistant-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'h-4 w-4']); ?>
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
<?php endif; ?></div>
                            <div data-assistant-response x-data="researchAssistantCitations" @click="openCitation($event, message)" class="min-w-0 flex-1 text-sm leading-7 text-gray-700 dark:text-slate-200">
                                <p class="mb-1 text-xs font-semibold text-gray-900 dark:text-white">Athena</p>
                                <?php if (isset($component)) { $__componentOriginalcd0c6190aee60e376faae6c945b7aa58 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalcd0c6190aee60e376faae6c945b7aa58 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.research-assistant-evidence','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('research-assistant-evidence'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalcd0c6190aee60e376faae6c945b7aa58)): ?>
<?php $attributes = $__attributesOriginalcd0c6190aee60e376faae6c945b7aa58; ?>
<?php unset($__attributesOriginalcd0c6190aee60e376faae6c945b7aa58); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalcd0c6190aee60e376faae6c945b7aa58)): ?>
<?php $component = $__componentOriginalcd0c6190aee60e376faae6c945b7aa58; ?>
<?php unset($__componentOriginalcd0c6190aee60e376faae6c945b7aa58); ?>
<?php endif; ?>
                                <?php if (isset($component)) { $__componentOriginal1b7e2f753aaa147a4f1397a63f6de52e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal1b7e2f753aaa147a4f1397a63f6de52e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.research-assistant-document-reading','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('research-assistant-document-reading'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal1b7e2f753aaa147a4f1397a63f6de52e)): ?>
<?php $attributes = $__attributesOriginal1b7e2f753aaa147a4f1397a63f6de52e; ?>
<?php unset($__attributesOriginal1b7e2f753aaa147a4f1397a63f6de52e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal1b7e2f753aaa147a4f1397a63f6de52e)): ?>
<?php $component = $__componentOriginal1b7e2f753aaa147a4f1397a63f6de52e; ?>
<?php unset($__componentOriginal1b7e2f753aaa147a4f1397a63f6de52e); ?>
<?php endif; ?>
                                <div x-html="$store.researchAssistant.renderMessage(message)"></div>
                                <?php if (isset($component)) { $__componentOriginal805531979dab6522425f9e48e41646c1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal805531979dab6522425f9e48e41646c1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.research-assistant-reply-notice','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('research-assistant-reply-notice'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal805531979dab6522425f9e48e41646c1)): ?>
<?php $attributes = $__attributesOriginal805531979dab6522425f9e48e41646c1; ?>
<?php unset($__attributesOriginal805531979dab6522425f9e48e41646c1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal805531979dab6522425f9e48e41646c1)): ?>
<?php $component = $__componentOriginal805531979dab6522425f9e48e41646c1; ?>
<?php unset($__componentOriginal805531979dab6522425f9e48e41646c1); ?>
<?php endif; ?>
                                <?php if (isset($component)) { $__componentOriginal0572655a6d09b3ae4092cf92a9b4c0c9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal0572655a6d09b3ae4092cf92a9b4c0c9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.research-assistant-citation','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('research-assistant-citation'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal0572655a6d09b3ae4092cf92a9b4c0c9)): ?>
<?php $attributes = $__attributesOriginal0572655a6d09b3ae4092cf92a9b4c0c9; ?>
<?php unset($__attributesOriginal0572655a6d09b3ae4092cf92a9b4c0c9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal0572655a6d09b3ae4092cf92a9b4c0c9)): ?>
<?php $component = $__componentOriginal0572655a6d09b3ae4092cf92a9b4c0c9; ?>
<?php unset($__componentOriginal0572655a6d09b3ae4092cf92a9b4c0c9); ?>
<?php endif; ?>
                                <?php if (isset($component)) { $__componentOriginal88d881980055068d6981fc790e92ea96 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal88d881980055068d6981fc790e92ea96 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.research-assistant-literature','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('research-assistant-literature'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal88d881980055068d6981fc790e92ea96)): ?>
<?php $attributes = $__attributesOriginal88d881980055068d6981fc790e92ea96; ?>
<?php unset($__attributesOriginal88d881980055068d6981fc790e92ea96); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal88d881980055068d6981fc790e92ea96)): ?>
<?php $component = $__componentOriginal88d881980055068d6981fc790e92ea96; ?>
<?php unset($__componentOriginal88d881980055068d6981fc790e92ea96); ?>
<?php endif; ?>
                                <?php if (isset($component)) { $__componentOriginal8a25bef9bc4597bfd2e772acab756247 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8a25bef9bc4597bfd2e772acab756247 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.research-assistant-proposal-changes','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('research-assistant-proposal-changes'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8a25bef9bc4597bfd2e772acab756247)): ?>
<?php $attributes = $__attributesOriginal8a25bef9bc4597bfd2e772acab756247; ?>
<?php unset($__attributesOriginal8a25bef9bc4597bfd2e772acab756247); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8a25bef9bc4597bfd2e772acab756247)): ?>
<?php $component = $__componentOriginal8a25bef9bc4597bfd2e772acab756247; ?>
<?php unset($__componentOriginal8a25bef9bc4597bfd2e772acab756247); ?>
<?php endif; ?>
                                <?php if (isset($component)) { $__componentOriginal8e64206db76327cd98ee6910a65c6f0f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8e64206db76327cd98ee6910a65c6f0f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.research-assistant-report-changes','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('research-assistant-report-changes'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8e64206db76327cd98ee6910a65c6f0f)): ?>
<?php $attributes = $__attributesOriginal8e64206db76327cd98ee6910a65c6f0f; ?>
<?php unset($__attributesOriginal8e64206db76327cd98ee6910a65c6f0f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8e64206db76327cd98ee6910a65c6f0f)): ?>
<?php $component = $__componentOriginal8e64206db76327cd98ee6910a65c6f0f; ?>
<?php unset($__componentOriginal8e64206db76327cd98ee6910a65c6f0f); ?>
<?php endif; ?>
                                <?php if (isset($component)) { $__componentOriginaledfb0471942b52516ffb8a9187f720a4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaledfb0471942b52516ffb8a9187f720a4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.research-assistant-dissemination','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('research-assistant-dissemination'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaledfb0471942b52516ffb8a9187f720a4)): ?>
<?php $attributes = $__attributesOriginaledfb0471942b52516ffb8a9187f720a4; ?>
<?php unset($__attributesOriginaledfb0471942b52516ffb8a9187f720a4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaledfb0471942b52516ffb8a9187f720a4)): ?>
<?php $component = $__componentOriginaledfb0471942b52516ffb8a9187f720a4; ?>
<?php unset($__componentOriginaledfb0471942b52516ffb8a9187f720a4); ?>
<?php endif; ?>
                                <?php if (isset($component)) { $__componentOriginal9e856a6e600661fe9669c4e16ef452b4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9e856a6e600661fe9669c4e16ef452b4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.research-assistant-sources','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('research-assistant-sources'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9e856a6e600661fe9669c4e16ef452b4)): ?>
<?php $attributes = $__attributesOriginal9e856a6e600661fe9669c4e16ef452b4; ?>
<?php unset($__attributesOriginal9e856a6e600661fe9669c4e16ef452b4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9e856a6e600661fe9669c4e16ef452b4)): ?>
<?php $component = $__componentOriginal9e856a6e600661fe9669c4e16ef452b4; ?>
<?php unset($__componentOriginal9e856a6e600661fe9669c4e16ef452b4); ?>
<?php endif; ?>
                                <div x-show="message.literature?.kind !== 'draft'" class="mt-2"><?php if (isset($component)) { $__componentOriginalad233732b811c1c03f21728ccecc6c3e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalad233732b811c1c03f21728ccecc6c3e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.research-assistant-copy','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('research-assistant-copy'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalad233732b811c1c03f21728ccecc6c3e)): ?>
<?php $attributes = $__attributesOriginalad233732b811c1c03f21728ccecc6c3e; ?>
<?php unset($__attributesOriginalad233732b811c1c03f21728ccecc6c3e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalad233732b811c1c03f21728ccecc6c3e)): ?>
<?php $component = $__componentOriginalad233732b811c1c03f21728ccecc6c3e; ?>
<?php unset($__componentOriginalad233732b811c1c03f21728ccecc6c3e); ?>
<?php endif; ?></div>
                            </div>
                        </div>
                    </article>
                </template>
                <div x-show="$store.researchAssistant.isLoading" x-cloak class="flex items-center gap-3" role="status" aria-label="Athena is preparing a response">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gray-900 text-white dark:bg-slate-200 dark:text-slate-900"><?php if (isset($component)) { $__componentOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal846fbd08f18f9e2d3bcd1a7a7e8e9d1b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.assistant-icon','data' => ['class' => 'h-4 w-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('assistant-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'h-4 w-4']); ?>
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
<?php endif; ?></div>
                    <div class="flex gap-1.5"><span class="h-2 w-2 animate-bounce motion-reduce:animate-none rounded-full bg-gray-400 [animation-delay:-0.3s]"></span><span class="h-2 w-2 animate-bounce motion-reduce:animate-none rounded-full bg-gray-400 [animation-delay:-0.15s]"></span><span class="h-2 w-2 animate-bounce motion-reduce:animate-none rounded-full bg-gray-400"></span></div>
                </div>
            </div>
        </div>

        <footer class="shrink-0 bg-white px-3 pb-[max(.75rem,env(safe-area-inset-bottom))] pt-3 dark:bg-slate-900 sm:px-5 sm:pb-4">
            <div x-show="$store.researchAssistant.error" x-cloak role="alert" class="mb-3 flex flex-wrap items-start justify-between gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                <div><p class="font-semibold" x-text="$store.researchAssistant.errorTitle || 'Athena needs attention'"></p><p class="mt-1 leading-5" x-text="$store.researchAssistant.error"></p></div>
                <button type="button" @click="$store.researchAssistant.retry()" :disabled="$store.researchAssistant.isLoading || $store.researchAssistant.retryAfter > 0" class="secondary-action" x-text="$store.researchAssistant.errorTitle === 'Chat is full' ? 'New chat' : ($store.researchAssistant.retryAfter > 0 ? `Retry in ${$store.researchAssistant.retryAfter}s` : 'Retry')"></button>
            </div>

            <div x-show="$store.researchAssistant.hasPaperContext()" x-cloak class="mb-2 px-1 text-sm leading-6 text-gray-600 dark:text-slate-300">
                <span class="break-words" x-text="$store.researchAssistant.paperContextLabel()"></span>
            </div>

            <?php if (isset($component)) { $__componentOriginal6f5fa8a03843c7bd1642e828865e244c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6f5fa8a03843c7bd1642e828865e244c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.research-assistant-composer','data' => ['id' => 'drawer','inputId' => 'research-assistant-drawer-message']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('research-assistant-composer'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'drawer','input-id' => 'research-assistant-drawer-message']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6f5fa8a03843c7bd1642e828865e244c)): ?>
<?php $attributes = $__attributesOriginal6f5fa8a03843c7bd1642e828865e244c; ?>
<?php unset($__attributesOriginal6f5fa8a03843c7bd1642e828865e244c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6f5fa8a03843c7bd1642e828865e244c)): ?>
<?php $component = $__componentOriginal6f5fa8a03843c7bd1642e828865e244c; ?>
<?php unset($__componentOriginal6f5fa8a03843c7bd1642e828865e244c); ?>
<?php endif; ?>
            <p class="mt-3 text-center text-xs text-gray-500 dark:text-slate-400">Athena can make mistakes. Check important answers.</p>
        </footer>
    </aside>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\research-assistant-drawer.blade.php ENDPATH**/ ?>