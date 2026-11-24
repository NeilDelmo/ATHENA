<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
    <head>
        <?php if (isset($component)) { $__componentOriginal82e3f864bb766fbb95cb0a10b750823c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal82e3f864bb766fbb95cb0a10b750823c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.favicon','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('favicon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal82e3f864bb766fbb95cb0a10b750823c)): ?>
<?php $attributes = $__attributesOriginal82e3f864bb766fbb95cb0a10b750823c; ?>
<?php unset($__attributesOriginal82e3f864bb766fbb95cb0a10b750823c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal82e3f864bb766fbb95cb0a10b750823c)): ?>
<?php $component = $__componentOriginal82e3f864bb766fbb95cb0a10b750823c; ?>
<?php unset($__componentOriginal82e3f864bb766fbb95cb0a10b750823c); ?>
<?php endif; ?>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo e($commentResponseForm['form_label']); ?> — <?php echo e($commentResponseForm['project_title']); ?></title>
        <?php echo app('Illuminate\Foundation\Vite')(['resources/css/comment-response-form-print.css', 'resources/js/paper-preview-pagination.js']); ?>
    </head>
    <body class="<?php echo \Illuminate\Support\Arr::toCssClasses(['paper-preview-embedded' => $embedded ?? false]); ?>">
        <nav class="preview-toolbar" aria-label="Form actions">
            <span><?php echo e($commentResponseForm['form_label']); ?></span>
            <a href="<?php echo e(route('faculty.topics.comment-response-form.pdf', ['topic' => $topic, 'source' => $commentResponseForm['form_source'], 'review' => $commentResponseForm['review_id']])); ?>" target="_blank" rel="noopener">Open PDF</a>
        </nav>
        <main data-paper-sheet class="comment-response-sheet" aria-label="BatStateU Comment-Response Form">
            <header class="university-header">
                <img src="<?php echo e(asset('images/batstateu-logo.png')); ?>" alt="Batangas State University seal">
                <div>Republic of the Philippines<strong>BATANGAS STATE UNIVERSITY</strong><span>The National Engineering University</span></div>
            </header>
            <h1>MATRIX ON THE ACTIONS MADE FOR THE COMMENTS AND SUGGESTIONS</h1>
            <?php
                $stages = array_intersect(array_keys(\App\Services\CommentResponseFeedback::STAGE_LABELS), $commentResponseForm['evaluation_stages'] ?? []);
                $evaluationLevels = [
                    ['label' => 'Initial Screening', 'checked' => count(array_diff($stages, ['lrec'])) > 0],
                    ['label' => 'Local Research Evaluation', 'checked' => in_array('lrec', $stages, true)],
                ];
                $researchers = array_filter([$commentResponseForm['project_leader'], ...array_column($commentResponseForm['staff'], 'name')]);
            ?>
            <section class="evaluation" aria-label="Level of evaluation done">
                <p><strong>LEVEL OF EVALUATION DONE:</strong></p>
                <ul class="evaluation-levels">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $evaluationLevels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $level): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <li><span class="evaluation-box <?php echo e($level['checked'] ? 'is-checked' : ''); ?>" aria-hidden="true"></span><?php echo e($level['label']); ?><span class="sr-only"><?php echo e($level['checked'] ? ' — Selected' : ' — Not selected'); ?></span></li>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </ul>
            </section>
            <p><strong>TITLE OF RESEARCH PROPOSAL:</strong> <strong><?php echo e($commentResponseForm['project_title']); ?></strong></p>
            <p><strong>PROJECT STAFF:</strong> <strong><?php echo e(implode(', ', $researchers)); ?></strong></p>
            <table class="feedback">
                <colgroup><col class="number"><col class="comments"><col class="response"><col class="remarks"></colgroup>
                <thead><tr><th>NO.</th><th>COMMENTS AND SUGGESTIONS</th><th>ACTION AND RESPONSE<small>(Changes made in the revised proposal)</small></th><th>REMARKS<small>Page and paragraph number of the changes made</small></th></tr></thead>
                <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $commentResponseForm['feedback']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <tr data-comment-response-row="<?php echo e($item['key'] ?? ''); ?>">
                            <td><?php echo e($loop->iteration); ?>.</td>
                            <td class="comment"><?php echo e($item['comment']); ?></td>
                            <td class="comment" data-comment-response-answer><?php echo e($item['response']); ?></td><td class="comment" data-comment-response-remarks><?php echo e($item['remarks']); ?></td>
                        </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <tr><td>1.</td><td></td><td></td><td></td></tr>
                        <tr><td>2.</td><td></td><td></td><td></td></tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
            <section class="signatures">
                <p>Prepared by:</p>
                <p class="signature-name"><strong><?php echo e($commentResponseForm['project_leader']); ?></strong><br>Project Leader</p>
                <p>Checked and Reviewed by:</p>
                <div class="review-signatures">
                    <p><strong><?php echo e(($commentResponseForm['comment_response_head'] ?? '') ?: config('work_plan.verifier.name')); ?></strong><br>Research Head / RDES Head<br>Member, LREC</p>
                    <p><strong><?php echo e(($commentResponseForm['comment_response_vice_chancellor'] ?? '') ?: config('notice_to_proceed.issuing_officer.name')); ?></strong><br>Vice Chancellor for Research,<br>Development and Extension Services<br>Member, LREC</p>
                </div>
            </section>
        </main>
    </body>
</html>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\faculty\comment-response-form\preview.blade.php ENDPATH**/ ?>