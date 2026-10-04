<?php
    $detailedProposalDocument = $checklist->get('detailed-proposal')['documents']->first();
    $detailedProposalSource = $detailedProposalDocument?->source_data;
    $workPlanDocument = $checklist->get('work-plan')['documents']->first();
    $workPlanSource = $workPlanDocument?->source_data;
    $lineItemBudgetDocument = $checklist->get('line-item-budget')['documents']->first();
    $lineItemBudgetSource = $lineItemBudgetDocument?->source_data;
    $expenseBreakdownDocument = $checklist->get('expense-breakdown')['documents']->first();
    $expenseBreakdownSource = $expenseBreakdownDocument?->source_data;
    $curriculumVitaeDocument = $checklist->get('curriculum-vitae')['documents']->first();
    $curriculumVitaeSource = $curriculumVitaeDocument?->source_data;
    $reviewPapers = $checklist->reject(fn (array $item): bool => $item['paper']['mode'] === 'automatic');
    $assessmentForms = $checklist->filter(fn (array $item): bool => $item['paper']['mode'] === 'automatic');
?>

<section aria-labelledby="review-details-heading" class="rounded-2xl border <?php echo e($projectDetailsComplete ? 'border-green-200' : 'border-amber-200'); ?> bg-white p-5 shadow-sm sm:p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div><h3 id="review-details-heading" class="text-lg font-black text-gray-900">Project Details</h3><p class="mt-1 text-xs text-gray-500">Shared across the project.</p></div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($inModal ?? false): ?>
            <button type="button" x-on:click="$dispatch('close-modal', 'proposal-review'); window.location.hash = 'project-details'" class="inline-flex w-full items-center justify-center rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">Edit details</button>
        <?php else: ?>
            <a href="<?php echo e(route('faculty.proposal-drafts.show', $proposalDraft)); ?>#project-details" class="inline-flex w-full items-center justify-center rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">Edit details</a>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <dl class="mt-5 grid gap-4 border-t border-gray-100 pt-5 sm:grid-cols-2 lg:grid-cols-3">
        <div class="sm:col-span-2 lg:col-span-3"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Title</dt><dd class="mt-1 text-sm font-bold text-gray-900"><?php echo e($proposalDraft->project_title); ?></dd></div>
        <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Duration</dt><dd class="mt-1 text-sm font-semibold text-gray-900"><?php echo e($proposalDraft->duration_months ? $proposalDraft->duration_months.' '.Str::plural('month', $proposalDraft->duration_months) : 'Missing'); ?></dd></div>
        <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Leader</dt><dd class="mt-1 text-sm font-semibold text-gray-900"><?php echo e($proposalDraft->project_leader ?: 'Missing'); ?></dd></div>
        <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Planned Start</dt><dd class="mt-1 text-sm font-semibold text-gray-900"><?php echo e($proposalDraft->planned_start?->format('M j, Y') ?? 'Missing'); ?></dd></div>
        <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Planned End</dt><dd class="mt-1 text-sm font-semibold text-gray-900"><?php echo e($proposalDraft->planned_end?->format('M j, Y') ?? 'Missing'); ?></dd></div>
    </dl>
</section>

<?php if (isset($component)) { $__componentOriginal63eda02c255a6e88714864bdf2b39e97 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal63eda02c255a6e88714864bdf2b39e97 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.budget-consistency-warning','data' => ['comparison' => $budgetConsistency,'proposalDraft' => $proposalDraft]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('budget-consistency-warning'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['comparison' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($budgetConsistency),'proposal-draft' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($proposalDraft)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal63eda02c255a6e88714864bdf2b39e97)): ?>
<?php $attributes = $__attributesOriginal63eda02c255a6e88714864bdf2b39e97; ?>
<?php unset($__attributesOriginal63eda02c255a6e88714864bdf2b39e97); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal63eda02c255a6e88714864bdf2b39e97)): ?>
<?php $component = $__componentOriginal63eda02c255a6e88714864bdf2b39e97; ?>
<?php unset($__componentOriginal63eda02c255a6e88714864bdf2b39e97); ?>
<?php endif; ?>

<section aria-labelledby="review-papers-heading" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
    <div><h3 id="review-papers-heading" class="text-lg font-black text-gray-900">Proposal papers to review</h3><p class="mt-1 text-xs text-gray-500">Review the five faculty papers below. You can replace a prepared PDF if needed before Turn in.</p></div>

    <div class="mt-5 divide-y divide-gray-100 rounded-xl border border-gray-200">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $reviewPapers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php
                $paper = $item['paper'];
                $submissionExtension = Str::upper(pathinfo($item['submission_filename'], PATHINFO_EXTENSION));
                $submissionFormat = 'PDF';
                $preparedDocument = $item['documents']->first(fn ($document) => filled($document->file_path) && $document->mime_type === 'application/pdf');
            ?>
            <article class="p-4 sm:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex min-w-0 gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-red-100 text-[10px] font-black text-red-700"><?php echo e($submissionExtension); ?></span>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h4 class="text-sm font-black text-gray-900"><?php echo e($paper['label']); ?></h4>
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider <?php echo e($item['needs_attention'] ? 'bg-red-100 text-red-800' : ($item['complete'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600')); ?>"><?php echo e($item['status']); ?></span>
                            </div>
                            <p class="mt-2 break-all text-xs font-bold text-gray-800"><?php echo e($item['submission_filename']); ?></p>
                            <div class="mt-2 text-xs leading-5 text-gray-500">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($preparedDocument): ?>
                                    <p class="font-semibold text-green-700">Prepared PDF ready: <?php echo e($preparedDocument->original_filename); ?></p>
                                    <p class="mt-1">This exact file will be sent to the Research Head.</p>
                                <?php elseif($item['documents']->isEmpty()): ?>
                                    <p>No <?php echo e($submissionFormat); ?> attachment is ready.</p>
                                <?php elseif($paper['mode'] === 'generated'): ?>
                                    <p>Saved form data is complete, but its final PDF has not been prepared yet.</p>
                                <?php else: ?>
                                    <p>Faculty-uploaded PDF attached <?php echo e($item['documents']->first()->updated_at->diffForHumans()); ?>.</p>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <a href="<?php echo e(match ($paper['slug']) { 'detailed-proposal' => route('faculty.proposal-drafts.detailed-proposal.edit', $proposalDraft), 'work-plan' => route('faculty.proposal-drafts.work-plan.edit', $proposalDraft), 'line-item-budget' => route('faculty.proposal-drafts.line-item-budget.edit', $proposalDraft), 'expense-breakdown' => route('faculty.proposal-drafts.expense-breakdown.edit', $proposalDraft), 'curriculum-vitae' => route('faculty.proposal-drafts.curriculum-vitae.edit', $proposalDraft), default => route('faculty.proposal-drafts.papers.edit', [$proposalDraft, $paper['slug']]) }); ?>" class="inline-flex w-full shrink-0 items-center justify-center rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto"><?php echo e($item['complete'] ? 'Edit' : 'Complete paper'); ?></a>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paper['slug'] === 'detailed-proposal' && is_array($detailedProposalSource)): ?>
                    <div class="mt-4 flex flex-col gap-2 border-t border-gray-100 pt-4 sm:flex-row">
                        <form action="<?php echo e(route('faculty.proposal-drafts.detailed-proposal.preview', $proposalDraft)); ?>" method="POST" target="_blank" class="w-full sm:w-auto">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-red-200 px-4 py-2.5 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">Preview Detailed Proposal</button>
                        </form>
                    </div>
                <?php elseif($paper['slug'] === 'work-plan' && is_array($workPlanSource)): ?>
                    <div class="mt-4 flex flex-col gap-2 border-t border-gray-100 pt-4 sm:flex-row">
                        <form action="<?php echo e(route('faculty.proposal-drafts.work-plan.preview', $proposalDraft)); ?>" method="POST" target="_blank" class="w-full sm:w-auto">
                            <?php echo csrf_field(); ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $workPlanSource['entries'] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entryIndex => $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <input type="hidden" name="entries[<?php echo e($entryIndex); ?>][objective]" value="<?php echo e($entry['objective']); ?>">
                                <input type="hidden" name="entries[<?php echo e($entryIndex); ?>][expected_output]" value="<?php echo e($entry['expected_output']); ?>">
                                <input type="hidden" name="entries[<?php echo e($entryIndex); ?>][activity]" value="<?php echo e($entry['activity']); ?>">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $entry['months']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $month): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><input type="hidden" name="entries[<?php echo e($entryIndex); ?>][months][]" value="<?php echo e($month); ?>"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-red-200 px-4 py-2.5 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">Preview Work Plan</button>
                        </form>
                    </div>
                <?php elseif($paper['slug'] === 'line-item-budget' && is_array($lineItemBudgetSource)): ?>
                    <div class="mt-4 flex flex-col gap-2 border-t border-gray-100 pt-4 sm:flex-row">
                        <form action="<?php echo e(route('faculty.proposal-drafts.line-item-budget.preview', $proposalDraft)); ?>" method="POST" target="_blank" class="w-full sm:w-auto">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-red-200 px-4 py-2.5 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">Preview Line-Item Budget</button>
                        </form>
                    </div>
                <?php elseif($paper['slug'] === 'expense-breakdown' && is_array($expenseBreakdownSource)): ?>
                    <div class="mt-4 flex flex-col gap-2 border-t border-gray-100 pt-4 sm:flex-row">
                        <form action="<?php echo e(route('faculty.proposal-drafts.expense-breakdown.preview', $proposalDraft)); ?>" method="POST" target="_blank" class="w-full sm:w-auto">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-red-200 px-4 py-2.5 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">Preview Estimated Expense Breakdown</button>
                        </form>
                    </div>
                <?php elseif($paper['slug'] === 'curriculum-vitae' && is_array($curriculumVitaeSource)): ?>
                    <div class="mt-4 flex flex-col gap-2 border-t border-gray-100 pt-4 sm:flex-row">
                        <form action="<?php echo e(route('faculty.proposal-drafts.curriculum-vitae.preview', $proposalDraft)); ?>" method="POST" target="_blank" class="w-full sm:w-auto">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-red-200 px-4 py-2.5 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">Preview CV Package</button>
                        </form>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($preparedDocument): ?>
                    <div class="mt-4 flex flex-col gap-2 border-t border-gray-100 pt-4 sm:flex-row sm:flex-wrap">
                        <a href="<?php echo e(route('faculty.proposal-drafts.submission-files.download', [$proposalDraft, $paper['slug']])); ?>" class="inline-flex w-full items-center justify-center rounded-xl bg-gray-950 px-4 py-2.5 text-xs font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">Download prepared PDF</a>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($paper['mode'] === 'generated'): ?>
                            <form action="<?php echo e(route('faculty.proposal-drafts.submission-files.replace', [$proposalDraft, $paper['slug']])); ?>" method="POST" enctype="multipart/form-data" class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                                <?php echo csrf_field(); ?>
                                <?php echo method_field('PUT'); ?>
                                <input type="hidden" name="document_version" value="<?php echo e($preparedDocument->lock_version); ?>">
                                <label class="inline-flex min-h-10 cursor-pointer items-center justify-center rounded-xl border border-red-200 px-4 py-2 text-xs font-bold text-red-700 hover:bg-red-50">
                                    <span>Choose replacement PDF</span>
                                    <input type="file" name="file" accept="application/pdf,.pdf" required class="sr-only" onchange="this.form.requestSubmit()">
                                </label>
                            </form>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </article>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>
</section>

<section data-review-assessment-forms aria-labelledby="review-assessment-forms-heading" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 id="review-assessment-forms-heading" class="text-base font-black text-gray-900">Assessment forms included automatically</h3>
            <p class="mt-1 max-w-2xl text-xs leading-5 text-gray-500">ATHENA creates these two blank forms from Project Details. Faculty do not need to fill, review, or replace them; completed assessments are recorded later in the review workflow.</p>
        </div>
        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-bold text-gray-600">2 forms</span>
    </div>
    <ul class="mt-4 divide-y divide-gray-100 rounded-xl border border-gray-200">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $assessmentForms; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
                <span class="font-semibold text-gray-800"><?php echo e($item['paper']['label']); ?></span>
                <span class="text-xs font-bold <?php echo e($item['complete'] ? 'text-green-700' : 'text-amber-700'); ?>"><?php echo e($item['complete'] ? 'Included automatically' : 'Waiting for Project Details'); ?></span>
            </li>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </ul>
</section>

<section aria-labelledby="review-collaborators-heading" class="rounded-2xl border border-blue-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h3 id="review-collaborators-heading" class="text-lg font-black text-gray-900">Project team</h3>
            <p class="mt-1 max-w-3xl text-xs leading-5 text-gray-500">Everyone listed here remains attached to the research project. Assigned roles continue into review, monitoring, and completion.</p>
        </div>
        <span class="inline-flex w-fit rounded-full bg-blue-100 px-3 py-1 text-xs font-black text-blue-800"><?php echo e(1 + $proposalDraft->members->count()); ?> <?php echo e(Str::plural('member', 1 + $proposalDraft->members->count())); ?></span>
    </div>

    <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <article class="rounded-xl border border-blue-200 bg-blue-50 p-4">
            <div class="flex items-start justify-between gap-3"><p class="font-black text-gray-900"><?php echo e($proposalDraft->owner->name); ?></p><span class="rounded-full bg-blue-700 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-white">Owner</span></div>
            <p class="mt-1 break-all text-xs text-gray-600"><?php echo e($proposalDraft->owner->email); ?></p>
        </article>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $proposalDraft->members; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $member): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <article class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0"><p class="truncate font-black text-gray-900"><?php echo e($member->user?->name ?? $member->name); ?></p><p class="mt-1 break-all text-xs text-gray-600"><?php echo e($member->user?->email ?? $member->email); ?></p></div>
                    <div class="flex shrink-0 flex-col items-end gap-1">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($member->isProjectSecretary()): ?>
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-amber-800">Project Secretary</span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-wider <?php echo e($member->isLinked() ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'); ?>"><?php echo e($member->isLinked() ? 'Joined' : 'Pending sign-in'); ?></span>
                    </div>
                </div>
            </article>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>
</section>

<section class="rounded-2xl border <?php echo e($readyToSubmit ? 'border-green-200 bg-green-50' : ($readyToPrepare ? 'border-amber-200 bg-amber-50' : 'border-gray-200 bg-gray-50')); ?> p-5 sm:p-6">
    <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="text-base font-black <?php echo e($readyToSubmit ? 'text-green-900' : 'text-gray-900'); ?>"><?php echo e($submissionFilesPrepared ? 'Turn in proposal' : 'Prepare submission PDFs'); ?></h3>
            <p class="mt-1 max-w-2xl text-sm leading-6 <?php echo e($readyToSubmit ? 'text-green-800' : 'text-gray-600'); ?>"><?php echo e($readyToSubmit ? 'Five proposal papers and two automatic assessment forms are ready. Turn in sends all seven PDFs to the Research Head.' : ($readyToPrepare ? 'Prepare seven PDFs, then review the five proposal papers before Turn in.' : 'Complete Project Details and the five proposal papers before preparing the submission files.')); ?></p>
        </div>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('submit', $proposalDraft)): ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($submissionFilesPrepared): ?>
                <form action="<?php echo e(route('faculty.proposal-drafts.submit', $proposalDraft)); ?>" method="POST" class="w-full shrink-0 sm:w-auto" data-proposal-confirm data-proposal-package-submit data-proposal-livewire-action="turnIn" data-confirm-title="Turn in project?" data-confirm-text="This sends five proposal papers and two auto-generated assessment forms to the Research Head." data-confirm-button="Turn in proposal" data-confirm-icon="question">
                    <?php echo csrf_field(); ?>
                    <button type="submit" wire:loading.attr="disabled" wire:target="turnIn" <?php if(! $readyToSubmit): echo 'disabled'; endif; ?> class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-6 py-3 text-sm font-black text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-gray-300 sm:w-auto">Turn in proposal</button>
                    <?php if (isset($component)) { $__componentOriginal710805e560231d3e50809ed74526b8b6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal710805e560231d3e50809ed74526b8b6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-submission-loading-screen','data' => ['livewireTarget' => 'turnIn']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-submission-loading-screen'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['livewire-target' => 'turnIn']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal710805e560231d3e50809ed74526b8b6)): ?>
<?php $attributes = $__attributesOriginal710805e560231d3e50809ed74526b8b6; ?>
<?php unset($__attributesOriginal710805e560231d3e50809ed74526b8b6); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal710805e560231d3e50809ed74526b8b6)): ?>
<?php $component = $__componentOriginal710805e560231d3e50809ed74526b8b6; ?>
<?php unset($__componentOriginal710805e560231d3e50809ed74526b8b6); ?>
<?php endif; ?>
                </form>
            <?php else: ?>
                <form action="<?php echo e(route('faculty.proposal-drafts.submission-files.prepare', $proposalDraft)); ?>" method="POST" class="w-full shrink-0 sm:w-auto" data-proposal-package-prepare data-proposal-livewire-action="prepare">
                    <?php echo csrf_field(); ?>
                    <button type="submit" wire:loading.attr="disabled" wire:target="prepare" <?php if(! $readyToPrepare): echo 'disabled'; endif; ?> class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-6 py-3 text-sm font-black text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-gray-300 sm:w-auto">Prepare seven PDFs</button>
                    <?php if (isset($component)) { $__componentOriginala8b1a60d21aad4769b1f9ca8d3b0c70d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8b1a60d21aad4769b1f9ca8d3b0c70d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-pdf-preparation-loading-screen','data' => ['livewireTarget' => 'prepare']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-pdf-preparation-loading-screen'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['livewire-target' => 'prepare']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala8b1a60d21aad4769b1f9ca8d3b0c70d)): ?>
<?php $attributes = $__attributesOriginala8b1a60d21aad4769b1f9ca8d3b0c70d; ?>
<?php unset($__attributesOriginala8b1a60d21aad4769b1f9ca8d3b0c70d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala8b1a60d21aad4769b1f9ca8d3b0c70d)): ?>
<?php $component = $__componentOriginala8b1a60d21aad4769b1f9ca8d3b0c70d; ?>
<?php unset($__componentOriginala8b1a60d21aad4769b1f9ca8d3b0c70d); ?>
<?php endif; ?>
                </form>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php else: ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($proposalDraft->topic_id && $proposalDraft->user_id === auth()->id()): ?>
                <p class="rounded-xl bg-blue-100 px-4 py-3 text-sm font-bold text-blue-900"><?php echo e($proposalDraft->topic?->status === 'revision_requested' ? 'Submit requested revisions from the proposal revision page.' : 'The Research Head has opened this proposal. Further submission requires a revision request.'); ?></p>
            <?php else: ?>
                <p class="rounded-xl bg-blue-100 px-4 py-3 text-sm font-bold text-blue-900">Only <?php echo e($proposalDraft->owner->name); ?> can submit this shared workspace.</p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/faculty/proposal-drafts/_review-package.blade.php ENDPATH**/ ?>