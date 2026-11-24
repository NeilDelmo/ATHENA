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

    <?php
        $statusClass = match ($topic->status) {
            'approved' => 'bg-green-50 text-green-700',
            'ready_for_signature' => 'bg-red-50 text-red-800',
            'rejected' => 'bg-red-50 text-red-700',
            'revision_requested' => 'bg-blue-50 text-blue-700',
            'resubmitted' => 'bg-purple-50 text-purple-700',
            default => 'bg-amber-50 text-amber-700',
        };

        $statusDescription = match ($topic->status) {
            'approved' => 'This research proposal has been approved.',
            'ready_for_signature' => 'Review is complete. The Research Secretary will prepare the Notice to Proceed and upload the required signed documents.',
            'rejected' => 'This proposal received a final rejection decision.',
            'revision_requested' => 'The Research Head requested changes before another review.',
            'resubmitted' => 'The revised proposal is waiting for the Research Head.',
            'gad_review' => 'The Research Head cleared this version. GAD assessment comes next, followed by co-evaluator review.',
            'expert_review', 'for_final_decision' => 'This proposal is waiting for the Research Head’s decision.',
            default => 'This proposal is waiting for the Research Head’s review.',
        };
    ?>

     <?php $__env->slot('header', null, []); ?> 
        <?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => $topic->title,'subtitle' => 'Submitted by '.$topic->user->name]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($topic->title),'subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Submitted by '.$topic->user->name)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

             <?php $__env->slot('actions', null, []); ?> 
                <?php if (isset($component)) { $__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.back-link','data' => ['fixed' => true,'href' => ''.e(route('research.index')).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('back-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['fixed' => true,'href' => ''.e(route('research.index')).'']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
Back to research list <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01)): ?>
<?php $attributes = $__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01; ?>
<?php unset($__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01)): ?>
<?php $component = $__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01; ?>
<?php unset($__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01); ?>
<?php endif; ?>
                <span class="self-start rounded-full px-3 py-1.5 text-[11px] font-black uppercase tracking-wider <?php echo e($statusClass); ?>"><?php echo e(str_replace('_', ' ', $topic->status)); ?></span>
             <?php $__env->endSlot(); ?>
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

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="space-y-6">
            <section class="rounded-2xl border border-gray-200/60 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-bold text-gray-900">Proposal overview</h3>
                <p class="mt-3 whitespace-pre-line text-sm leading-6 text-gray-600"><?php echo e($topic->description ?: 'No description was provided for this proposal.'); ?></p>
            </section>

            <?php echo $__env->make('topics.partials.version-history', ['topic' => $topic, 'expanded' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

            <section class="rounded-2xl border border-gray-200/60 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h3 class="text-sm font-bold text-gray-900">Review history</h3>
                    <p class="mt-0.5 text-xs text-gray-400">Decisions and feedback from the Research Head.</p>
                </div>
                <div class="p-6">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $topic->reviews; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $review): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div class="relative border-l-2 border-gray-100 pb-6 pl-5 last:pb-0">
                            <span class="absolute -left-[5px] top-1 h-2 w-2 rounded-full bg-red-500 ring-4 ring-white"></span>
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-xs font-black uppercase tracking-wider text-gray-700"><?php echo e(str_replace('_', ' ', $review->decision)); ?></p>
                                <time class="text-[11px] text-gray-400"><?php echo e($review->created_at->format('M d, Y h:i A')); ?></time>
                            </div>
                            <p class="mt-1 text-[11px] font-semibold text-gray-400"><?php echo e($review->reviewer?->name ?? 'Former Research Head'); ?></p>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($review->comment): ?>
                                <p class="mt-2 whitespace-pre-line rounded-xl bg-gray-50 p-3 text-sm leading-6 text-gray-600"><?php echo e($review->comment); ?></p>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <div class="py-6 text-center">
                            <p class="text-sm font-bold text-gray-700">No review yet</p>
                            <p class="mt-1 text-xs text-gray-400">Feedback and decisions will appear here.</p>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </section>
        </div>

        <aside class="space-y-5">
            <section class="rounded-2xl border border-gray-200/60 bg-white p-5 shadow-sm">
                <h3 class="text-xs font-black uppercase tracking-wider text-gray-400">Current status</h3>
                <span class="mt-3 inline-flex rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider <?php echo e($statusClass); ?>"><?php echo e(str_replace('_', ' ', $topic->status)); ?></span>
                <p class="mt-3 text-xs leading-5 text-gray-500"><?php echo e($statusDescription); ?></p>
            </section>

            <section class="rounded-2xl border border-gray-200/60 bg-white p-5 shadow-sm">
                <h3 class="text-xs font-black uppercase tracking-wider text-gray-400">Research details</h3>
                <dl class="mt-4 space-y-4">
                    <div>
                        <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Total project cost</dt>
                        <dd class="mt-1 text-lg font-black text-gray-900"><?php echo e($topic->estimated_budget !== null ? 'PHP '.number_format((float) $topic->estimated_budget, 2) : 'Not provided'); ?></dd>
                    </div>
                    <div class="border-t border-gray-100 pt-4">
                        <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Research call and category</dt>
                        <dd class="mt-1 text-sm font-bold text-gray-700"><?php echo e($topic->researchCall->title); ?></dd>
                        <dd class="mt-0.5 text-xs text-gray-400"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topic->category): ?><?php echo e($topic->category->name); ?> · <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php echo e($topic->estimated_duration_months); ?> months</dd>
                    </div>
                    <div class="border-t border-gray-100 pt-4">
                        <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Researcher</dt>
                        <dd class="mt-1 text-sm font-bold text-gray-700"><?php echo e($topic->user->name); ?></dd>
                        <dd class="mt-0.5 text-xs text-gray-400"><?php echo e($topic->user->email); ?></dd>
                    </div>
                    <div class="grid grid-cols-2 gap-3 border-t border-gray-100 pt-4">
                        <div>
                            <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Submitted</dt>
                            <dd class="mt-1 text-xs font-semibold text-gray-600"><?php echo e($topic->created_at->format('M d, Y')); ?></dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Updated</dt>
                            <dd class="mt-1 text-xs font-semibold text-gray-600"><?php echo e($topic->updated_at->format('M d, Y')); ?></dd>
                        </div>
                    </div>
                </dl>
            </section>

            <section class="rounded-2xl border border-gray-200/60 bg-white p-5 shadow-sm">
                <h3 class="text-xs font-black uppercase tracking-wider text-gray-400">Document</h3>
                <p class="mt-2 text-xs leading-5 text-gray-500">Downloads the latest submitted version of this proposal.</p>
                <a href="<?php echo e(route('topics.download', $topic)); ?>" class="mt-4 inline-flex w-full items-center justify-center rounded-xl bg-gray-900 px-4 py-2.5 text-xs font-bold text-white transition hover:bg-gray-800">Download latest document</a>
            </section>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topic->signed_approval_path): ?>
                <section class="rounded-2xl border border-green-200 bg-green-50 p-5">
                    <h3 class="text-xs font-black uppercase tracking-wider text-green-700">Signed approval</h3>
                    <p class="mt-2 text-xs leading-5 text-green-800">The Research Secretary has released the signed authorization to proceed.</p>
                    <a href="<?php echo e(route('topics.approval', $topic)); ?>" class="mt-4 inline-flex w-full items-center justify-center rounded-xl bg-green-700 px-4 py-2.5 text-xs font-bold text-white">Download signed approval</a>
                </section>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topic->status === 'revision_requested' && $topic->user_id === Auth::id()): ?>
                <section class="rounded-2xl border border-blue-200 bg-blue-50 p-5">
                    <h3 class="text-sm font-bold text-blue-900">Revision required</h3>
                    <p class="mt-1 text-xs leading-5 text-blue-700">Review the feedback above, then upload your revised proposal from the Faculty Dashboard.</p>
                    <a href="<?php echo e(route('faculty.dashboard')); ?>" class="mt-3 inline-flex rounded-xl bg-blue-700 px-4 py-2.5 text-xs font-bold text-white transition hover:bg-blue-800">Go to revision form</a>
                </section>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </aside>
    </div>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\research\show.blade.php ENDPATH**/ ?>