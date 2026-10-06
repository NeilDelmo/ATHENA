<?php ($noticePreparedForSigning = $topic->hasPreparedNoticeToProceed()); ?>

<section id="notice-to-proceed" class="ntp-workspace scroll-mt-32 rounded-2xl border border-slate-200">
    <div class="rounded-t-2xl border-b border-t-4 border-slate-200 border-t-brand bg-slate-50 px-5 py-6 dark:border-b-slate-700 dark:bg-slate-900 sm:px-7">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h3 tabindex="-1" class="text-2xl font-semibold tracking-tight text-gray-950 focus:outline-none">Notice to Proceed</h3>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topic->hasIssuedNoticeToProceed()): ?>
                    <p class="mt-2 max-w-3xl text-base leading-6 text-gray-600">
                        Signed copy issued <?php echo e($topic->notice_to_proceed_issued_at->format('M j, Y g:i A')); ?>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topic->noticeIssuer): ?>
                            by <?php echo e($topic->noticeIssuer->name); ?>

                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>.
                        <?php echo e($topic->isCompletedProject() ? 'This notice remains part of the completed project archive.' : 'Project monitoring is now open.'); ?>

                    </p>
                <?php elseif($noticePreparedForSigning && $canManageNoticeToProceed): ?>
                    <p class="mt-2 max-w-3xl text-base leading-6 text-gray-600">Review the saved details below, then download the unsigned PDF for signatures and upload the signed copy at the bottom.</p>
                <?php else: ?>
                    <p class="mt-2 max-w-3xl text-base leading-6 text-gray-600">Research office staff or the secretary prepare the unsigned PDF, obtain signatures, and upload the signed copy.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topic->hasIssuedNoticeToProceed()): ?>
                <div class="relative flex shrink-0 flex-col gap-2 sm:flex-row">
                    <a href="<?php echo e(route('topics.notice-to-proceed.download', $topic)); ?>" class="rh-button !min-h-12 !text-base gap-2">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M12 3v12m-4-4 4 4 4-4M5 15v5h14v-5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        <span>Download signed PDF</span>
                    </a>
                </div>
            <?php elseif($noticePreparedForSigning && $canManageNoticeToProceed): ?>
                <a href="<?php echo e(route('topics.notice-to-proceed.download-unsigned', $topic)); ?>" class="rh-button-secondary !min-h-12 !text-base shrink-0 gap-2">
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M12 3v12m-4-4 4 4 4-4M5 15v5h14v-5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    <span>Download unsigned PDF</span>
                </a>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topic->hasIssuedNoticeToProceed()): ?>
        <div class="grid gap-px border-b border-gray-200 bg-gray-200 sm:grid-cols-3">
            <div class="bg-white px-5 py-4">
                <p class="text-base font-black uppercase tracking-wider text-gray-500">Document</p>
                <p class="mt-1 truncate text-base font-bold text-gray-900"><?php echo e($topic->notice_to_proceed_original_filename); ?></p>
            </div>
            <div class="bg-white px-5 py-4">
                <p class="text-base font-black uppercase tracking-wider text-gray-500">Approved period</p>
                <p class="mt-1 text-base font-bold text-gray-900">
                    <?php echo e(data_get($topic->notice_to_proceed_data, 'approved_start_date', '—')); ?> to <?php echo e(data_get($topic->notice_to_proceed_data, 'approved_end_date', '—')); ?>

                </p>
            </div>
            <div class="bg-white px-5 py-4">
                <p class="text-base font-black uppercase tracking-wider text-gray-500">Approved budget</p>
                <p class="mt-1 text-base font-bold text-gray-900">Php <?php echo e(number_format((float) data_get($topic->notice_to_proceed_data, 'approved_budget', 0), 2)); ?></p>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canManageNoticeToProceed && $noticeToProceedForm && ! $topic->isCompletedProject() && ! $topic->hasIssuedNoticeToProceed()): ?>
        <div
            class="p-5 sm:p-7"
            x-data="noticeToProceedForm({
                previewUrl: <?php echo \Illuminate\Support\Js::from(route('topics.notice-to-proceed.preview', $topic))->toHtml() ?>,
                csrfToken: <?php echo \Illuminate\Support\Js::from(csrf_token())->toHtml() ?>,
            })"
            data-notice-to-proceed-autosave="true"
        >
            <form x-ref="form" data-notice-to-proceed-autosave-form method="POST" action="<?php echo e(route('topics.notice-to-proceed.store', $topic)); ?>" class="space-y-6" @submit="submitNoticeDetails">
                <?php echo csrf_field(); ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['notice_to_proceed'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-base font-bold text-red-800"><?php echo e($message); ?></div>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <div class="space-y-6">
                    <div class="ntp-form-section">
                        <h4 class="text-lg font-semibold text-gray-950">Project details</h4>

                        <div class="mt-5 grid gap-6 md:grid-cols-2" x-data="{ projectStaff: <?php echo \Illuminate\Support\Js::from(old('researcher_names', $noticeToProceedForm['researcher_names']))->toHtml() ?> }">
                            <div>
                                <span class="block text-base font-semibold leading-6 text-slate-800 dark:text-slate-200">Project staff</span>
                                <div class="mt-2 space-y-2">
                                    <template x-for="(staffMember, index) in projectStaff" :key="index">
                                        <div class="flex min-w-0 items-center gap-2">
                                            <input data-project-staff-input type="text" :name="`researcher_names[${index}]`" x-model="projectStaff[index]" required maxlength="255" placeholder="Full name" class="block min-w-0 w-full" :aria-label="`Project staff member ${index + 1}`">
                                            <button type="button" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-slate-400 hover:bg-red-50 hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:hover:bg-red-950/40 dark:hover:text-red-300" x-show="projectStaff.length > 1" @click="projectStaff.splice(index, 1)" :aria-label="`Remove project staff member ${index + 1}`"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6" stroke-linecap="round" /></svg></button>
                                        </div>
                                    </template>
                                </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['researcher_names'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-2 text-base font-semibold text-red-700"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['researcher_names.*'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-2 text-base font-semibold text-red-700"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <div class="mt-3 space-y-3">
                                    <button data-add-project-staff type="button" class="flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-dashed border-brand/30 bg-brand-wash/60 px-4 py-2.5 text-base font-semibold text-brand hover:border-brand/60 hover:bg-brand-wash focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:border-rose-800 dark:bg-rose-950/30 dark:text-rose-200 dark:hover:bg-rose-950/60" @click="projectStaff.push(''); $nextTick(() => $el.closest('[x-data]').querySelectorAll('[data-project-staff-input]').item(projectStaff.length - 1).focus())">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                                        <span>Add project staff</span>
                                    </button>
                                    <p class="text-base leading-5 text-slate-500 dark:text-slate-400">Names from the proposal. Add or update project staff as needed.</p>
                                </div>
                            </div>

                            <label class="block text-base font-bold text-gray-800">
                                <span class="block">Institution / campus</span>
                                <input name="campus_line" type="text" value="<?php echo e(old('campus_line', $noticeToProceedForm['campus_line'])); ?>" required maxlength="255" class="mt-2 block w-full rounded-xl border-gray-300 text-base shadow-sm focus:border-red-600 focus:ring-red-600">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['campus_line'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="mt-2 block text-base font-semibold text-red-700"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </label>

                            <label class="block text-base font-bold text-gray-800 md:col-span-2">
                                Approved project title
                                <textarea name="project_title" rows="2" required maxlength="500" class="mt-2 block w-full rounded-xl border-gray-300 text-base shadow-sm focus:border-red-600 focus:ring-red-600"><?php echo e(old('project_title', $noticeToProceedForm['project_title'])); ?></textarea>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['project_title'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="mt-2 block text-base font-semibold text-red-700"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </label>
                        </div>
                    </div>

                    <div class="ntp-form-section">
                        <h4 class="text-lg font-semibold text-gray-950">Approval record</h4>

                        <div class="mt-4 grid gap-5 md:grid-cols-2">
                            <label class="block text-base font-bold text-gray-800">
                                Notice date
                                <?php if (isset($component)) { $__componentOriginal37e12294b28f0bd91a733acab9bb06c5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal37e12294b28f0bd91a733acab9bb06c5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.date-picker','data' => ['id' => 'notice-date','name' => 'notice_date','value' => old('notice_date', $noticeToProceedForm['notice_date']),'required' => true,'class' => 'mt-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('date-picker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'notice-date','name' => 'notice_date','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(old('notice_date', $noticeToProceedForm['notice_date'])),'required' => true,'class' => 'mt-2']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal37e12294b28f0bd91a733acab9bb06c5)): ?>
<?php $attributes = $__attributesOriginal37e12294b28f0bd91a733acab9bb06c5; ?>
<?php unset($__attributesOriginal37e12294b28f0bd91a733acab9bb06c5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal37e12294b28f0bd91a733acab9bb06c5)): ?>
<?php $component = $__componentOriginal37e12294b28f0bd91a733acab9bb06c5; ?>
<?php unset($__componentOriginal37e12294b28f0bd91a733acab9bb06c5); ?>
<?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['notice_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="mt-2 block text-base font-semibold text-red-700"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </label>

                            <div class="grid grid-cols-[minmax(0,1fr)_110px] gap-4">
                                <label class="block text-base font-bold text-gray-800">
                                    LREC Resolution No.
                                    <input name="resolution_number" type="text" value="<?php echo e(old('resolution_number', $noticeToProceedForm['resolution_number'])); ?>" required maxlength="50" placeholder="01" class="mt-2 block w-full rounded-xl border-gray-300 text-base shadow-sm focus:border-red-600 focus:ring-red-600">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['resolution_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="mt-2 block text-base font-semibold text-red-700"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </label>
                                <label class="block text-base font-bold text-gray-800">
                                    Series
                                    <input name="resolution_year" type="number" min="2000" max="2100" value="<?php echo e(old('resolution_year', $noticeToProceedForm['resolution_year'])); ?>" required class="mt-2 block w-full rounded-xl border-gray-300 text-base shadow-sm focus:border-red-600 focus:ring-red-600">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['resolution_year'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="mt-2 block text-base font-semibold text-red-700"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ntp-form-section">
                    <h4 class="text-lg font-semibold text-gray-950">Schedule and budget</h4>
                    <p class="mt-1 text-base leading-5 text-gray-500">Update the proposed values if the final approval changed them.</p>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <label class="block text-base font-bold text-gray-800">
                            Approved start date
                                <?php if (isset($component)) { $__componentOriginal37e12294b28f0bd91a733acab9bb06c5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal37e12294b28f0bd91a733acab9bb06c5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.date-picker','data' => ['id' => 'approved-start-date','name' => 'approved_start_date','value' => old('approved_start_date', $noticeToProceedForm['approved_start_date']),'required' => true,'class' => 'mt-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('date-picker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'approved-start-date','name' => 'approved_start_date','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(old('approved_start_date', $noticeToProceedForm['approved_start_date'])),'required' => true,'class' => 'mt-2']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal37e12294b28f0bd91a733acab9bb06c5)): ?>
<?php $attributes = $__attributesOriginal37e12294b28f0bd91a733acab9bb06c5; ?>
<?php unset($__attributesOriginal37e12294b28f0bd91a733acab9bb06c5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal37e12294b28f0bd91a733acab9bb06c5)): ?>
<?php $component = $__componentOriginal37e12294b28f0bd91a733acab9bb06c5; ?>
<?php unset($__componentOriginal37e12294b28f0bd91a733acab9bb06c5); ?>
<?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['approved_start_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="mt-2 block text-base font-semibold text-red-700"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </label>
                        <label class="block text-base font-bold text-gray-800">
                            Approved end date
                                <?php if (isset($component)) { $__componentOriginal37e12294b28f0bd91a733acab9bb06c5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal37e12294b28f0bd91a733acab9bb06c5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.date-picker','data' => ['id' => 'approved-end-date','name' => 'approved_end_date','value' => old('approved_end_date', $noticeToProceedForm['approved_end_date']),'required' => true,'class' => 'mt-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('date-picker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'approved-end-date','name' => 'approved_end_date','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(old('approved_end_date', $noticeToProceedForm['approved_end_date'])),'required' => true,'class' => 'mt-2']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal37e12294b28f0bd91a733acab9bb06c5)): ?>
<?php $attributes = $__attributesOriginal37e12294b28f0bd91a733acab9bb06c5; ?>
<?php unset($__attributesOriginal37e12294b28f0bd91a733acab9bb06c5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal37e12294b28f0bd91a733acab9bb06c5)): ?>
<?php $component = $__componentOriginal37e12294b28f0bd91a733acab9bb06c5; ?>
<?php unset($__componentOriginal37e12294b28f0bd91a733acab9bb06c5); ?>
<?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['approved_end_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="mt-2 block text-base font-semibold text-red-700"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </label>
                        <label class="block text-base font-bold text-gray-800">
                            Duration (months)
                            <input name="approved_duration_months" type="number" min="1" max="120" value="<?php echo e(old('approved_duration_months', $noticeToProceedForm['approved_duration_months'])); ?>" required class="mt-2 block w-full rounded-xl border-gray-300 text-base shadow-sm focus:border-red-600 focus:ring-red-600">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['approved_duration_months'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="mt-2 block text-base font-semibold text-red-700"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </label>
                        <label class="block text-base font-bold text-gray-800">
                            Approved budget (Php)
                            <input name="approved_budget" type="number" min="0" max="999999999.99" step="0.01" value="<?php echo e(old('approved_budget', $noticeToProceedForm['approved_budget'])); ?>" required class="mt-2 block w-full rounded-xl border-gray-300 text-base shadow-sm focus:border-red-600 focus:ring-red-600">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['approved_budget'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="mt-2 block text-base font-semibold text-red-700"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </label>
                    </div>
                </div>

                <section data-notice-signatories class="ntp-form-section">
                    <h4 class="text-lg font-semibold text-gray-950">Authorized signatories</h4>
                    <p class="mt-1 text-base text-slate-500 dark:text-slate-400">Review the names and positions before preparing the notice.</p>
                    <div class="grid gap-5 border-t border-gray-200 bg-white p-5 lg:grid-cols-2">
                        <div class="space-y-4">
                            <p class="text-base font-black uppercase tracking-wider text-red-700">Issuing officer</p>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
                                'issuing_officer_name' => 'Name',
                                'issuing_officer_title' => 'Position',
                                'issuing_officer_committee_role' => 'Committee role',
                            ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <label class="block text-base font-bold text-gray-800">
                                    <?php echo e($label); ?>

                                    <input name="<?php echo e($field); ?>" type="text" value="<?php echo e(old($field, $noticeToProceedForm[$field])); ?>" required maxlength="255" class="mt-2 block w-full rounded-xl border-gray-300 text-base shadow-sm focus:border-red-600 focus:ring-red-600">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = [$field];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="mt-2 block text-base font-semibold text-red-700"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </label>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                        <div class="space-y-4">
                            <p class="text-base font-black uppercase tracking-wider text-red-700">Checking and verifying officer</p>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
                                'verifying_officer_name' => 'Name',
                                'verifying_officer_title' => 'Position',
                                'verifying_officer_committee_role' => 'Committee role',
                            ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <label class="block text-base font-bold text-gray-800">
                                    <?php echo e($label); ?>

                                    <input name="<?php echo e($field); ?>" type="text" value="<?php echo e(old($field, $noticeToProceedForm[$field])); ?>" required maxlength="255" class="mt-2 block w-full rounded-xl border-gray-300 text-base shadow-sm focus:border-red-600 focus:ring-red-600">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = [$field];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="mt-2 block text-base font-semibold text-red-700"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </label>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                    </div>
                </section>

                <div class="flex flex-col gap-4 border-t border-slate-300 pt-6 dark:border-slate-700 2xl:flex-row 2xl:items-center 2xl:justify-between">
                    <div>
                        <p class="text-base font-black text-gray-950">Preview the unsigned PDF before preparing it for signatures.</p>
                        <p class="mt-1 text-base leading-5 text-gray-600">Previewing does not release anything. Faculty access and project monitoring remain locked until the signed PDF is uploaded.</p>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        <button type="button" data-notice-to-proceed-preview-button @click="showProposalPreview()" :aria-expanded="previewPaneOpen" aria-controls="notice-to-proceed-preview-panel-<?php echo e($topic->id); ?>" aria-haspopup="dialog" :disabled="previewLoading || submitting" class="rh-button-secondary !min-h-12 !text-base gap-2 disabled:cursor-wait disabled:opacity-60">
                            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                            <span x-show="!previewLoading">Preview notice</span>
                            <span x-show="previewLoading" x-cloak>Generating preview...</span>
                        </button>
                        <button type="submit" :disabled="submitting || previewLoading" class="rh-button !min-h-12 !text-base gap-2 disabled:cursor-wait disabled:opacity-60">
                            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M5 3h12l4 4v14H3V3h2Zm2 0v6h10V3M7 21v-8h10v8" stroke-linejoin="round"/></svg>
                            <span x-show="!submitting">Save notice details</span>
                            <span x-show="submitting" x-cloak>Saving details...</span>
                        </button>
                    </div>
                </div>

                <p x-show="previewError" x-cloak x-text="previewError" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-base font-semibold text-red-700"></p>

                <?php if (isset($component)) { $__componentOriginal45f53eba72ddc2e934dbcbe4dd398776 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal45f53eba72ddc2e934dbcbe4dd398776 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-document-preview','data' => ['panelId' => 'notice-to-proceed-preview-panel-'.e($topic->id).'','title' => 'Notice to Proceed preview','description' => 'Unsigned notice generated from the current form details.','frameTitle' => 'Notice to Proceed document preview']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-document-preview'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['panel-id' => 'notice-to-proceed-preview-panel-'.e($topic->id).'','title' => 'Notice to Proceed preview','description' => 'Unsigned notice generated from the current form details.','frame-title' => 'Notice to Proceed document preview']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal45f53eba72ddc2e934dbcbe4dd398776)): ?>
<?php $attributes = $__attributesOriginal45f53eba72ddc2e934dbcbe4dd398776; ?>
<?php unset($__attributesOriginal45f53eba72ddc2e934dbcbe4dd398776); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal45f53eba72ddc2e934dbcbe4dd398776)): ?>
<?php $component = $__componentOriginal45f53eba72ddc2e934dbcbe4dd398776; ?>
<?php unset($__componentOriginal45f53eba72ddc2e934dbcbe4dd398776); ?>
<?php endif; ?>
            </form>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($noticePreparedForSigning): ?>
                <section data-notice-signed-upload class="mt-8 rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                    <div class="border-b border-slate-200 bg-slate-50 px-5 py-4 dark:border-slate-700 dark:bg-slate-800">
                        <h4 class="text-lg font-semibold text-slate-900 dark:text-white">Signed notice and release</h4>
                        <p class="mt-1 text-base text-slate-600 dark:text-slate-300">After reviewing and saving the details above, download the unsigned PDF, obtain signatures, and upload the signed copy here.</p>
                    </div>
                    <form method="POST" action="<?php echo e(route('topics.notice-to-proceed.upload-signed', $topic)); ?>" enctype="multipart/form-data" class="p-5">
                        <?php echo csrf_field(); ?>
                        <div class="space-y-5">
                            <div class="min-w-0 flex-1">
                                <?php if (isset($component)) { $__componentOriginal9971cea5196fd2d938203cf5dd1a3337 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9971cea5196fd2d938203cf5dd1a3337 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.file-dropzone','data' => ['id' => 'signed-notice-to-proceed','name' => 'signed_notice_to_proceed','label' => 'Signed Notice to Proceed PDF','accept' => '.pdf','required' => true,'maxBytes' => 25 * 1024 * 1024]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('file-dropzone'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'signed-notice-to-proceed','name' => 'signed_notice_to_proceed','label' => 'Signed Notice to Proceed PDF','accept' => '.pdf','required' => true,'max-bytes' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(25 * 1024 * 1024)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9971cea5196fd2d938203cf5dd1a3337)): ?>
<?php $attributes = $__attributesOriginal9971cea5196fd2d938203cf5dd1a3337; ?>
<?php unset($__attributesOriginal9971cea5196fd2d938203cf5dd1a3337); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9971cea5196fd2d938203cf5dd1a3337)): ?>
<?php $component = $__componentOriginal9971cea5196fd2d938203cf5dd1a3337; ?>
<?php unset($__componentOriginal9971cea5196fd2d938203cf5dd1a3337); ?>
<?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['signed_notice_to_proceed'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-2 text-base font-semibold text-red-700"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <div class="flex justify-end border-t border-slate-200 pt-5 dark:border-slate-700">
                            <button type="submit" class="rh-button !min-h-12 !text-base w-full min-w-0 gap-2 !whitespace-normal sm:w-auto"><svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M12 3v12m-4-4 4 4 4-4M5 15v5h14v-5" stroke-linecap="round" stroke-linejoin="round" /></svg><span>Release papers and Notice to Proceed</span></button>
                            </div>
                        </div>
                    </form>
                </section>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    <?php elseif(! $topic->hasIssuedNoticeToProceed()): ?>
        <div class="px-5 py-5 text-base text-gray-600 sm:px-7">
            Research office staff or the secretary will prepare the Notice to Proceed, obtain signatures, and upload the signed copy for release.
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</section>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/topics/partials/notice-to-proceed.blade.php ENDPATH**/ ?>