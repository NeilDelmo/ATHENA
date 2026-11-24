<?php
    $deletionRequest = $deletionRequest ?? null;
    $title = $proposalDraft->project_title ?: 'Untitled proposal';
    $draftsUrl = route('faculty.proposal-drafts.index', $proposalDraft->archived_at ? ['view' => 'archived'] : []);
    $retentionDays = \App\Models\ProposalDraft::TRASH_RETENTION_DAYS;
    $buttonClasses = 'inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-4 font-semibold text-slate-800 transition hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800';
?>
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

     <?php $__env->slot('header', null, []); ?> 
        <?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => $deletionRequest ? 'Deletion request' : 'Request draft deletion','subtitle' => $title]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($deletionRequest ? 'Deletion request' : 'Request draft deletion'),'subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($title)]); ?>
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

    <section class="max-w-3xl space-y-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-7 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="deletion-heading">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($deletionRequest === null): ?>
            <div class="space-y-2">
                <h2 id="deletion-heading" class="text-xl font-bold text-slate-950 dark:text-white">Ask your team to approve</h2>
                <p class="leading-6 text-slate-700 dark:text-slate-300">ATHENA will notify your accepted teammates. The draft stays accessible until everyone approves. If anyone declines, the request closes.</p>
            </div>
            <p class="rounded-xl bg-slate-50 p-4 leading-6 text-slate-700 dark:bg-slate-800 dark:text-slate-200">Once approved, the draft goes to Trash. Any accepted teammate can restore it for <?php echo e($retentionDays); ?> days. <?php echo e($proposalDraft->isLinkedToSubmission() ? 'Official records linked to this draft are retained.' : 'After that, ATHENA permanently removes it.'); ?></p>
            <form method="POST" action="<?php echo e(route('faculty.proposal-drafts.deletion.store', $proposalDraft)); ?>" class="space-y-4">
                <?php echo csrf_field(); ?>
                <div>
                    <label for="deletion-reason" class="block font-semibold text-slate-900 dark:text-white">Reason for deletion</label>
                    <textarea id="deletion-reason" name="reason" rows="4" maxlength="1000" required <?php $__errorArgs = ['reason'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-invalid="true" aria-describedby="reason-error" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> class="mt-2 block w-full rounded-lg border-slate-300 bg-white text-slate-900 focus:border-rose-700 focus:ring-rose-700 dark:border-slate-600 dark:bg-slate-950 dark:text-white"><?php echo e(old('reason')); ?></textarea>
                    <?php if (isset($component)) { $__componentOriginalf94ed9c5393ef72725d159fe01139746 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf94ed9c5393ef72725d159fe01139746 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.input-error','data' => ['id' => 'reason-error','messages' => $errors->get('reason'),'class' => 'mt-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input-error'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'reason-error','messages' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($errors->get('reason')),'class' => 'mt-2']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf94ed9c5393ef72725d159fe01139746)): ?>
<?php $attributes = $__attributesOriginalf94ed9c5393ef72725d159fe01139746; ?>
<?php unset($__attributesOriginalf94ed9c5393ef72725d159fe01139746); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf94ed9c5393ef72725d159fe01139746)): ?>
<?php $component = $__componentOriginalf94ed9c5393ef72725d159fe01139746; ?>
<?php unset($__componentOriginalf94ed9c5393ef72725d159fe01139746); ?>
<?php endif; ?>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="dashboard-action text-base">Send deletion request</button>
                    <a wire:navigate href="<?php echo e($draftsUrl); ?>" class="<?php echo e($buttonClasses); ?>">Cancel</a>
                </div>
            </form>
        <?php else: ?>
            <?php
                $status = match ($deletionRequest->status) {
                    \App\Models\ProposalDraftDeletionRequest::STATUS_APPROVED => 'Approved by the team',
                    \App\Models\ProposalDraftDeletionRequest::STATUS_DECLINED => 'Request declined',
                    \App\Models\ProposalDraftDeletionRequest::STATUS_CANCELLED => 'Request cancelled',
                    default => 'Waiting for team approval',
                };
                $responseUrl = route('faculty.proposal-drafts.deletion.update', [$proposalDraft, $deletionRequest]);
            ?>
            <div class="space-y-1">
                <h2 id="deletion-heading" class="text-xl font-bold text-slate-950 dark:text-white"><?php echo e($status); ?></h2>
                <p class="text-slate-600 dark:text-slate-300"><?php echo e(count($deletionRequest->approved_member_ids)); ?> of <?php echo e(count($deletionRequest->member_ids)); ?> teammate approvals</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800">
                <h3 class="font-semibold text-slate-900 dark:text-white">Reason from <?php echo e($proposalDraft->owner->name); ?></h3>
                <p class="mt-1 whitespace-pre-wrap break-words text-slate-700 dark:text-slate-200"><?php echo e($deletionRequest->reason); ?></p>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($teamChanged): ?>
                <p role="status" class="rounded-lg bg-amber-50 p-3 text-amber-900 dark:bg-amber-950 dark:text-amber-200">The accepted team changed. The project leader can cancel this request and start a new one.</p>
            <?php elseif($deletionRequest->isPending()): ?>
                <p class="leading-6 text-slate-700 dark:text-slate-300">The draft stays accessible until every accepted teammate approves. After approval, anyone on the accepted team can restore it from Trash for <?php echo e($retentionDays); ?> days. <?php echo e($proposalDraft->isLinkedToSubmission() ? 'Official records linked to this draft are retained.' : 'After that, ATHENA permanently removes it.'); ?></p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canRespond && $hasApproved): ?>
                <p role="status" class="font-semibold text-emerald-800 dark:text-emerald-300">You approved. You can still decline while the request is open.</p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if (isset($component)) { $__componentOriginalf94ed9c5393ef72725d159fe01139746 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf94ed9c5393ef72725d159fe01139746 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.input-error','data' => ['messages' => $errors->get('decision')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('input-error'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['messages' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($errors->get('decision'))]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf94ed9c5393ef72725d159fe01139746)): ?>
<?php $attributes = $__attributesOriginalf94ed9c5393ef72725d159fe01139746; ?>
<?php unset($__attributesOriginalf94ed9c5393ef72725d159fe01139746); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf94ed9c5393ef72725d159fe01139746)): ?>
<?php $component = $__componentOriginalf94ed9c5393ef72725d159fe01139746; ?>
<?php unset($__componentOriginalf94ed9c5393ef72725d159fe01139746); ?>
<?php endif; ?>
            <div class="flex flex-wrap items-center gap-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canRespond): ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($hasApproved)): ?>
                        <form method="POST" action="<?php echo e($responseUrl); ?>" data-proposal-confirm data-confirm-icon="question" data-confirm-title="Approve draft deletion?" data-confirm-text="If every accepted teammate approves, this draft goes to Trash and can be restored for <?php echo e($retentionDays); ?> days." data-confirm-button="Approve">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('PATCH'); ?>
                            <input type="hidden" name="decision" value="approve">
                            <button type="submit" class="dashboard-action text-base">Approve</button>
                        </form>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <form method="POST" action="<?php echo e($responseUrl); ?>">
                        <?php echo csrf_field(); ?>
                        <?php echo method_field('PATCH'); ?>
                        <input type="hidden" name="decision" value="decline">
                        <button type="submit" class="<?php echo e($buttonClasses); ?>">Decline</button>
                    </form>
                <?php elseif($deletionRequest->isPending() && auth()->user()->can('organize', $proposalDraft)): ?>
                    <form method="POST" action="<?php echo e($responseUrl); ?>">
                        <?php echo csrf_field(); ?>
                        <?php echo method_field('PATCH'); ?>
                        <input type="hidden" name="decision" value="cancel">
                        <button type="submit" class="<?php echo e($buttonClasses); ?>">Cancel request</button>
                    </form>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($proposalDraft->trashed()): ?>
                    <a wire:navigate href="<?php echo e(route('faculty.proposal-drafts.trash')); ?>" class="<?php echo e($buttonClasses); ?>">Open Trash</a>
                <?php else: ?>
                    <a wire:navigate href="<?php echo e(route('faculty.proposal-drafts.show', $proposalDraft)); ?>" class="<?php echo e($buttonClasses); ?>">Open draft</a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <a wire:navigate href="<?php echo e($draftsUrl); ?>" class="<?php echo e($buttonClasses); ?>">Back to drafts</a>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\faculty\proposal-drafts\deletion-request.blade.php ENDPATH**/ ?>