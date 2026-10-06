<?php
    $richText = app(\App\Support\ProposalRichText::class);
    $signatoryName = fn (string $key): string => \App\Support\DetailedProposalData::signatoryName($detailedProposal[$key] ?: 'NAME');
    $sdgs = config('detailed_proposal.sdgs');
    $sectionHeadings = config('detailed_proposal.section_headings');
?>
<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>BatStateU-FO-RES-02 - Detailed Research Proposal Preview</title>
        <?php echo app('Illuminate\Foundation\Vite')('resources/css/detailed-proposal-print.css'); ?>
    </head>
    <body class="detailed-proposal-preview-page">
        <main class="detailed-proposal-sheet" aria-label="BatStateU-FO-RES-02 Detailed Research Proposal">
            <table class="detailed-proposal-table">
                <colgroup><col class="detailed-proposal-col-seal"><col class="detailed-proposal-col-reference"><col class="detailed-proposal-col-effectivity"><col class="detailed-proposal-col-revision"></colgroup>
                <tbody>
                    <tr>
                        <th class="detailed-proposal-header-cell"><img class="detailed-proposal-seal" src="<?php echo e(asset('images/batstateu-logo.png')); ?>" alt="Batangas State University seal"></th>
                        <td class="detailed-proposal-header-cell">Reference No.: BatStateU-FO-RES-02</td>
                        <td class="detailed-proposal-header-cell">Effectivity Date: August 22, 2023</td>
                        <td class="detailed-proposal-header-cell">Revision No.: 04</td>
                    </tr>
                    <tr><th colspan="4" class="detailed-proposal-table-title">DETAILED RESEARCH PROPOSAL</th></tr>
                    <tr>
                        <td colspan="4">
                            <p class="detailed-proposal-section-heading"><?php echo e($sectionHeadings['project-information']); ?></p>
                            <p class="detailed-proposal-section-value"><?php echo e($detailedProposal['project_title']); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4">
                            <p><span class="detailed-proposal-section-heading"><?php echo e($sectionHeadings['research-agenda']); ?></span> <span class="detailed-proposal-section-value"><?php echo e($detailedProposal['research_agenda']); ?></span></p>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4">
                            <p><span class="detailed-proposal-section-heading"><?php echo e($sectionHeadings['sdgs']); ?></span> <span class="detailed-proposal-section-note is-plain">(Check all applicable SDG)</span></p>
                            <table class="detailed-proposal-sdg-table">
                                <tbody>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [[1, 10], [2, 11], [3, 12], [4, 13], [5, 14], [6, 15], [7, 16], [8, 17], [9, null]]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$leftSdg, $rightSdg]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <tr>
                                            <td><span class="detailed-proposal-sdg-box"><?php echo e(in_array($leftSdg, $detailedProposal['sdgs'], true) ? '☒' : '☐'); ?></span> SDG<?php echo e($leftSdg); ?>: <?php echo e($sdgs[$leftSdg]); ?></td>
                                            <td><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rightSdg !== null): ?><span class="detailed-proposal-sdg-box"><?php echo e(in_array($rightSdg, $detailedProposal['sdgs'], true) ? '☒' : '☐'); ?></span> SDG<?php echo e($rightSdg); ?>: <?php echo e($sdgs[$rightSdg]); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                                        </tr>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4">
                            <div class="detailed-proposal-member-block">
                                <p><span class="detailed-proposal-section-heading"><?php echo e($sectionHeadings['project-team']); ?></span> <span class="detailed-proposal-section-value"><?php echo e($detailedProposal['project_leader_display']); ?></span></p>
                                <p class="detailed-proposal-indent">Email Address: <?php echo e($detailedProposal['leader_email']); ?></p>
                                <p class="detailed-proposal-indent">Contact Number: <?php echo e($detailedProposal['leader_contact']); ?></p>
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $detailedProposal['staff']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $member): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <div class="detailed-proposal-member-block">
                                    <p><span class="detailed-proposal-section-heading">Project Staff (s):</span> <span class="detailed-proposal-section-value"><?php echo e($member['display_name']); ?></span></p>
                                    <p class="detailed-proposal-indent">Email Address: <?php echo e($member['email']); ?></p>
                                    <p class="detailed-proposal-indent">Contact Number: <?php echo e($member['contact']); ?></p>
                                </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4">
                            <p><span class="detailed-proposal-section-heading"><?php echo e($sectionHeadings['proponent']); ?></span> <span class="detailed-proposal-section-value"><?php echo e($detailedProposal['proponent_agency']); ?></span></p>
                            <p class="detailed-proposal-indent"><span class="detailed-proposal-section-heading">Department:</span> <span class="detailed-proposal-section-value"><?php echo e($detailedProposal['proponent_department']); ?></span></p>
                            <p class="detailed-proposal-indent"><span class="detailed-proposal-section-heading">College:</span> <span class="detailed-proposal-section-value"><?php echo e($detailedProposal['proponent_college']); ?></span></p>
                            <p class="detailed-proposal-indent"><span class="detailed-proposal-section-heading">Campus:</span> <span class="detailed-proposal-section-value"><?php echo e($detailedProposal['proponent_campus']); ?></span></p>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4">
                            <p><span class="detailed-proposal-section-heading"><?php echo e($sectionHeadings['cooperating-agency']); ?></span> <span class="detailed-proposal-section-note">(if any)</span> <?php echo e($detailedProposal['cooperating_agency'] ?: 'None'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" class="detailed-proposal-narrative" data-proposal-preview-section="executive-brief">
                            <p class="detailed-proposal-section-heading"><?php echo e($sectionHeadings['executive-brief']); ?></p>
                            <?php if (isset($component)) { $__componentOriginal9cebdecbe55d7dd58fce42fafa60f42d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9cebdecbe55d7dd58fce42fafa60f42d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-figures','data' => ['proposal' => $detailedProposal,'section' => 'executive_brief']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-figures'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['proposal' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($detailedProposal),'section' => 'executive_brief']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9cebdecbe55d7dd58fce42fafa60f42d)): ?>
<?php $attributes = $__attributesOriginal9cebdecbe55d7dd58fce42fafa60f42d; ?>
<?php unset($__attributesOriginal9cebdecbe55d7dd58fce42fafa60f42d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9cebdecbe55d7dd58fce42fafa60f42d)): ?>
<?php $component = $__componentOriginal9cebdecbe55d7dd58fce42fafa60f42d; ?>
<?php unset($__componentOriginal9cebdecbe55d7dd58fce42fafa60f42d); ?>
<?php endif; ?>
                            <?php echo $richText->sanitize($detailedProposal['executive_brief'], allowTables: true); ?>

                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" class="detailed-proposal-narrative" data-proposal-preview-section="rationale">
                            <p><span class="detailed-proposal-section-heading"><?php echo e($sectionHeadings['rationale']); ?></span> <span class="detailed-proposal-section-note">(include available statistics related to the problem)</span></p>
                            <?php if (isset($component)) { $__componentOriginal9cebdecbe55d7dd58fce42fafa60f42d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9cebdecbe55d7dd58fce42fafa60f42d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-figures','data' => ['proposal' => $detailedProposal,'section' => 'rationale']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-figures'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['proposal' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($detailedProposal),'section' => 'rationale']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9cebdecbe55d7dd58fce42fafa60f42d)): ?>
<?php $attributes = $__attributesOriginal9cebdecbe55d7dd58fce42fafa60f42d; ?>
<?php unset($__attributesOriginal9cebdecbe55d7dd58fce42fafa60f42d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9cebdecbe55d7dd58fce42fafa60f42d)): ?>
<?php $component = $__componentOriginal9cebdecbe55d7dd58fce42fafa60f42d; ?>
<?php unset($__componentOriginal9cebdecbe55d7dd58fce42fafa60f42d); ?>
<?php endif; ?>
                            <?php echo $richText->sanitize($detailedProposal['rationale'], allowTables: true); ?>

                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" class="detailed-proposal-narrative" data-proposal-preview-section="general-objective">
                            <p class="detailed-proposal-section-heading"><?php echo e($sectionHeadings['objectives']); ?></p>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($detailedProposal['general_objective'])): ?>
                                <p><strong>General Objective:</strong></p>
                                <?php echo $richText->sanitize($detailedProposal['general_objective'], allowTables: true); ?>

                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <p><strong>Specific Objectives:</strong></p>
                            <ol class="detailed-proposal-output-list">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $detailedProposal['specific_objectives']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $objective): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <li><?php echo $richText->sanitize($objective['description']); ?></li>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </ol>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4">
                            <p><span class="detailed-proposal-section-heading"><?php echo e($sectionHeadings['expected-outputs']); ?></span> <span class="detailed-proposal-section-note is-plain">(based on expanded 6Ps &amp; 2Is of research)</span></p>
                            <ol class="detailed-proposal-output-list">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = config('detailed_proposal.expected_outputs'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $detailedProposal['expected_outputs'][$key]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $output): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <li><strong><?php echo e($label); ?>:</strong> <?php echo e($output['description']); ?></li>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </ol>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" class="detailed-proposal-narrative">
                            <p><span class="detailed-proposal-section-heading"><?php echo e($sectionHeadings['literature']); ?></span> <span class="detailed-proposal-section-note">(minimum of ten literature/studies reviewed)</span></p>
                            <div data-proposal-preview-section="introduction">
                                <?php if (isset($component)) { $__componentOriginal9cebdecbe55d7dd58fce42fafa60f42d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9cebdecbe55d7dd58fce42fafa60f42d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-figures','data' => ['proposal' => $detailedProposal,'section' => 'introduction']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-figures'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['proposal' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($detailedProposal),'section' => 'introduction']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9cebdecbe55d7dd58fce42fafa60f42d)): ?>
<?php $attributes = $__attributesOriginal9cebdecbe55d7dd58fce42fafa60f42d; ?>
<?php unset($__attributesOriginal9cebdecbe55d7dd58fce42fafa60f42d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9cebdecbe55d7dd58fce42fafa60f42d)): ?>
<?php $component = $__componentOriginal9cebdecbe55d7dd58fce42fafa60f42d; ?>
<?php unset($__componentOriginal9cebdecbe55d7dd58fce42fafa60f42d); ?>
<?php endif; ?>
                                <?php echo $richText->sanitize($detailedProposal['introduction'], allowTables: true); ?>

                            </div>
                            <div data-proposal-preview-section="related-literature">
                                <?php if (isset($component)) { $__componentOriginal9cebdecbe55d7dd58fce42fafa60f42d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9cebdecbe55d7dd58fce42fafa60f42d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-figures','data' => ['proposal' => $detailedProposal,'section' => 'related_literature']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-figures'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['proposal' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($detailedProposal),'section' => 'related_literature']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9cebdecbe55d7dd58fce42fafa60f42d)): ?>
<?php $attributes = $__attributesOriginal9cebdecbe55d7dd58fce42fafa60f42d; ?>
<?php unset($__attributesOriginal9cebdecbe55d7dd58fce42fafa60f42d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9cebdecbe55d7dd58fce42fafa60f42d)): ?>
<?php $component = $__componentOriginal9cebdecbe55d7dd58fce42fafa60f42d; ?>
<?php unset($__componentOriginal9cebdecbe55d7dd58fce42fafa60f42d); ?>
<?php endif; ?>
                                <?php echo $richText->sanitize($detailedProposal['related_literature'], allowTables: true); ?>

                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" class="detailed-proposal-narrative">
                            <p class="detailed-proposal-section-heading"><?php echo e($sectionHeadings['methodology']); ?></p>
                            <ul class="detailed-proposal-methodology-list">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = config('detailed_proposal.methodology'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <?php if(blank($detailedProposal['methodology'][$key]) && ! collect($detailedProposal['methodology_images'])->contains('section', $key)) continue; ?>
                                    <li data-proposal-preview-section="methodology-<?php echo e($key); ?>">
                                        <p class="detailed-proposal-methodology-heading"><?php echo e($label); ?></p>
                                        <?php if (isset($component)) { $__componentOriginal9cebdecbe55d7dd58fce42fafa60f42d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9cebdecbe55d7dd58fce42fafa60f42d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-figures','data' => ['proposal' => $detailedProposal,'section' => $key]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-figures'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['proposal' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($detailedProposal),'section' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($key)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9cebdecbe55d7dd58fce42fafa60f42d)): ?>
<?php $attributes = $__attributesOriginal9cebdecbe55d7dd58fce42fafa60f42d; ?>
<?php unset($__attributesOriginal9cebdecbe55d7dd58fce42fafa60f42d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9cebdecbe55d7dd58fce42fafa60f42d)): ?>
<?php $component = $__componentOriginal9cebdecbe55d7dd58fce42fafa60f42d; ?>
<?php unset($__componentOriginal9cebdecbe55d7dd58fce42fafa60f42d); ?>
<?php endif; ?>
                                        <?php echo $richText->sanitize($detailedProposal['methodology'][$key], allowTables: true); ?>

                                    </li>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </ul>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" class="detailed-proposal-narrative" data-proposal-preview-section="responsibilities">
                            <p class="detailed-proposal-section-heading"><?php echo e($sectionHeadings['responsibilities']); ?></p>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $detailedProposal['responsibilities']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $responsibility): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <p class="detailed-proposal-responsibility-name"><?php echo e($loop->first ? 'Project Leader' : 'Project Staff (s)'); ?>: <span><?php echo e(\Illuminate\Support\Str::upper($responsibility['name'])); ?> (<?php echo e($responsibility['percentage']); ?>%)</span></p>
                                <?php echo $richText->sanitize($responsibility['duties'], allowTables: true); ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4">
                            <p><span class="detailed-proposal-section-heading"><?php echo e($sectionHeadings['work-plan']); ?></span> See attached Form A</p>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4">
                            <p><span class="detailed-proposal-section-heading"><?php echo e($sectionHeadings['budget']); ?></span> See attached Form B</p>
                            <table class="detailed-proposal-budget-table">
                                <tbody>
                                    <tr><td class="detailed-proposal-budget-number">1.</td><td><strong>Maintenance and Operating Expenses</strong></td><td class="detailed-proposal-budget-amount">Php <?php echo e(number_format($detailedProposal['mooe_total'], 2)); ?></td></tr>
                                    <tr><td class="detailed-proposal-budget-number">2.</td><td><strong>Capital Outlay and Equipment</strong></td><td class="detailed-proposal-budget-amount">Php <?php echo e(number_format($detailedProposal['co_total'], 2)); ?></td></tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" class="detailed-proposal-narrative" data-proposal-preview-section="references">
                            <p class="detailed-proposal-section-heading"><?php echo e($sectionHeadings['references']); ?></p>
                            <?php echo $richText->sanitize($detailedProposal['references'], allowTables: true); ?>

                        </td>
                    </tr>
                    <tr>
                        <td colspan="4">
                            <p><span class="detailed-proposal-section-heading"><?php echo e($sectionHeadings['curriculum-vitae']); ?></span> See attached Form C</p>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4">
                            <p><strong>*</strong>Required Attachment (use appropriate ISO Form): (A) Major Activities and Work Plan; (B) Line-item Budget; (C) Curriculum Vitae</p>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" rowspan="3" class="detailed-proposal-signature-cell">
                            <p class="detailed-proposal-signature-heading">Prepared by:</p>
                            <p class="detailed-proposal-signature-space">&nbsp;</p>
                            <p class="detailed-proposal-signature-name"><?php echo e(\Illuminate\Support\Str::upper($detailedProposal['project_leader'])); ?></p>
                            <p>Project Leader</p>
                            <p class="detailed-proposal-signature-date">Date Signed:</p>
                        </td>
                        <td colspan="2"><p>Department: <?php echo e($detailedProposal['proponent_department']); ?></p></td>
                    </tr>
                    <tr><td colspan="2"><p>College: <?php echo e($detailedProposal['proponent_college']); ?></p></td></tr>
                    <tr><td colspan="2"><p>Campus: <?php echo e($detailedProposal['proponent_campus']); ?></p></td></tr>
                    <tr>
                        <td colspan="4" class="detailed-proposal-privacy">Pursuant to Republic Act No. 10173, also known as the Data Privacy Act of 2012, the Batangas State University, the National Engineering University recognizes its commitment to protect and respect the privacy of its customers and/or stakeholders and ensure that all information collected from them are all processed in accordance with the principles of transparency, legitimate purpose and proportionality mandated under the Data Privacy Act of 2012.</td>
                    </tr>
                    <tr><td colspan="4" class="detailed-proposal-office-heading">To be accomplished by the Research Office</td></tr>
                    <tr>
                        <td colspan="2" class="detailed-proposal-checklist">
                            <p>Checklist:</p>
                            <p><?php echo e(($detailedProposal['document_checklist']['complete_documents'] ?? false) ? '☒' : '☐'); ?> Complete Documents</p>
                            <div class="detailed-proposal-sub-items">
                                <p>Detailed Proposal</p>
                                <p>LIB</p>
                                <p>Work Plan</p>
                            </div>
                            <p><?php echo e(($detailedProposal['document_checklist']['initial_screening_form'] ?? false) ? '☒' : '☐'); ?> Initial Screening Form</p>
                            <p>Score: _______</p>
                        </td>
                        <td colspan="2" class="detailed-proposal-checklist">
                            <p>Level of Call</p>
                    <p><?php echo e(($detailedProposal['level_of_call'] ?? null) === 'central_agency' ? '☒' : '☐'); ?> Central Agency (VPRDES, President)</p>
                    <p><?php echo e(($detailedProposal['level_of_call'] ?? null) === 'constituent_campus' ? '☒' : '☐'); ?> Constituent Campus (VCRDES, Chancellor)</p>
                        </td>
                    </tr>
                    <tr><td colspan="4" class="detailed-proposal-office-heading">To be accomplished by the Researcher/s</td></tr>
                    <tr>
                        <td colspan="2" class="detailed-proposal-signature-cell">
                            <p class="detailed-proposal-signature-heading">Checked and Verified by:</p>
                            <p class="detailed-proposal-signature-space">&nbsp;</p>
                            <p class="detailed-proposal-signature-name"><?php echo e($signatoryName('checked_verified_by_name')); ?></p>
                            <p>Head, Research Office</p>
                            <p class="detailed-proposal-signature-date">Date Signed:</p>
                        </td>
                        <td colspan="2" class="detailed-proposal-signature-cell">
                            <p class="detailed-proposal-signature-heading">Recommending Approval:</p>
                            <p class="detailed-proposal-signature-space">&nbsp;</p>
                            <p class="detailed-proposal-signature-name"><?php echo e($signatoryName('recommending_approval_name')); ?></p>
                            <p><?php echo e(config('notice_to_proceed.issuing_officer.title')); ?></p>
                            <p class="detailed-proposal-signature-date">Date Signed:</p>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" class="detailed-proposal-signature-cell">
                            <p class="detailed-proposal-signature-heading">Approved by the Research Council/Local Research Evaluation Committee-Chair (LREC-Chair) Represented by:</p>
                            <p class="detailed-proposal-signature-space">&nbsp;</p>
                            <p class="detailed-proposal-signature-name"><?php echo e($signatoryName('approved_by_name')); ?></p>
                            <p><?php echo e(config('notice_to_proceed.verifying_officer.title')); ?></p>
                            <p class="detailed-proposal-signature-date">Date Signed:</p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="detailed-proposal-notes">
                <p class="detailed-proposal-note-heading">Notes: The Signatories funded by:</p>
                <p class="detailed-proposal-note-indented"><u>Approval through Research Council</u></p>
                <p class="detailed-proposal-note-indented detailed-proposal-note-detail">Director, Research; Vice President for RDES: &amp; University President</p>
                <p class="detailed-proposal-note-indented"><u>Approval through Local Research Evaluation Committee</u></p>
                <p class="detailed-proposal-note-indented detailed-proposal-note-detail">Head, Research/Head Research &amp; Extension; Vice Chancellor for RDES; &amp; Vice President for RDES</p>
            </div>

            <footer>
                <span>Tracking No.________________</span>
                <span class="detailed-proposal-page-number">Page 1 of 1</span>
            </footer>
        </main>
    </body>
</html>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/faculty/detailed-proposals/preview.blade.php ENDPATH**/ ?>