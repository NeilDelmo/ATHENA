<?php
    $figures = collect($report->photos ?? []);
    $figureNumber = 1;
    $trackingContent = '"'.str_replace(['\\', '"', "\n", "\r", "\f"], ['\\\\', '\\"', '\\a ', '\\d ', '\\c '], 'Tracking No.'.($report->tracking_number ?: '____________________')).'"';
?>

<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>" style="--report-tracking: <?php echo e($trackingContent); ?>">
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
        <title>Progress Report Preview</title>
        <?php echo app('Illuminate\Foundation\Vite')(['resources/css/progress-report-print.css', 'resources/js/paper-preview-pagination.js']); ?>
    </head>
    <body class="progress-report-preview-page">
        <main data-paper-sheet class="progress-report-sheet" aria-label="BatStateU progress report preview">
            <div data-paper-footer class="progress-report-paper-footer">Tracking No.<?php echo e($report->tracking_number ?: '____________________'); ?></div>
            <table class="progress-report-table progress-report-header">
                <colgroup><col style="width: 10.113%"><col style="width: 37.118%"><col style="width: 34.355%"><col style="width: 18.414%"></colgroup>
                <tbody>
                    <tr>
                        <td class="progress-report-logo"><img src="<?php echo e(asset('images/batstateu-logo.png')); ?>" alt="Batangas State University seal"></td>
                        <td><strong>Reference No.:</strong> BatStateU-REC-RES-02</td>
                        <td><strong>Effectivity Date:</strong> May 18, 2022</td>
                        <td><strong>Revision No.:</strong> 02</td>
                    </tr>
                    <tr><td colspan="4"><h1>PROGRESS REPORT ON RESEARCH PROJECT</h1></td></tr>
                </tbody>
            </table>

            <table class="progress-report-table progress-report-content">
                <tbody>
                    <tr><td>Submission Date: <?php echo e($report->submission_date?->format('F j, Y')); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report->reporting_period_label): ?><br>Reporting period: <?php echo e($report->reporting_period_label); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td></tr>
                    <tr><td><h2>Research Project Title</h2></td></tr>
                    <tr><td><?php echo e($report->topic->title); ?></td></tr>
                    <tr><td><h2>Researchers</h2></td></tr>
                    <tr><td class="progress-report-pre-line"><?php echo e($report->researchers); ?></td></tr>
                    <tr><td><h2>Duration (Approved Start &amp; End of Implementation)</h2></td></tr>
                    <tr><td><?php echo e($report->topic->estimated_duration_months ? $report->topic->estimated_duration_months.' months ' : ''); ?>(<?php echo e($report->implementation_start?->format('F j, Y')); ?> - <?php echo e($report->implementation_end?->format('F j, Y')); ?>)</td></tr>
                    <tr><td><h2>Budget</h2></td></tr>
                    <tr><td>P <?php echo e(number_format((float) $report->budget, 2)); ?></td></tr>
                    <tr><td><h2>Funding Agency</h2></td></tr>
                    <tr><td><?php echo e($report->funding_agency); ?></td></tr>
                    <tr><td><h2>Summary of Accomplishment for the Monitoring Period</h2></td></tr>
                    <tr>
                        <td class="progress-report-summary-cell">
                            <table class="progress-report-table progress-report-accomplishments-table">
                                <colgroup><col style="width: 34.862%"><col style="width: 33.303%"><col style="width: 31.835%"></colgroup>
                                <thead><tr><th scope="col">Objectives</th><th scope="col">Target Accomplishments</th><th scope="col">Actual Accomplishments</th></tr></thead>
                                <tbody>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $report->accomplishments ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $accomplishment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <tr>
                                            <td class="progress-report-pre-line"><?php echo e($accomplishment['objective']); ?></td>
                                            <td class="progress-report-pre-line"><?php echo e($accomplishment['target']); ?></td>
                                            <td class="progress-report-pre-line"><?php echo e($accomplishment['actual']); ?></td>
                                        </tr>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <h2>Introduction</h2>
                            <p class="progress-report-narrative"><?php echo e($report->introduction); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <h2 class="progress-report-rationale">VIII. Rationale: <span>(include available statistics related to the problem)</span></h2>
                            <p class="progress-report-narrative"><?php echo e($report->rationale); ?></p>
                        </td>
                    </tr>
                    <tr><td><h2>Objectives</h2></td></tr>
                    <tr><td class="progress-report-pre-line"><?php echo e($report->objectives); ?></td></tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['methodology' => 'Methodology', 'results_discussion' => 'Results and Discussion']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $heading): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php
                            $paragraphs = preg_split('/\n\s*\n/u', str_replace(["\r\n", "\r"], "\n", $report->$field ?? '')) ?: [''];
                        ?>
                        <tr><td><h2><?php echo e($heading); ?></h2></td></tr>
                        <tr>
                            <td class="progress-report-section">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $paragraphs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $paragraphIndex => $paragraph): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <p class="progress-report-narrative"><?php echo e($paragraph); ?></p>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $figures->where('section', $field); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $photo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <?php
                                            $position = (int) ($photo['after_paragraph'] ?? 0);
                                            $position = $position === 0 ? count($paragraphs) : min($position, count($paragraphs));
                                        ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($position === $paragraphIndex + 1): ?>
                                            <figure <?php if(isset($photo['preview_file_input'])): ?> hidden <?php endif; ?>>
                                                <picture><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($photo['original_url'])): ?><source media="print" srcset="<?php echo e($photo['original_url']); ?>"><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><img <?php if(isset($photo['preview_file_input'])): ?> data-preview-file-input="<?php echo e($photo['preview_file_input']); ?>" <?php else: ?> src="<?php echo e($photo['preview_url'] ?? ''); ?>" <?php endif; ?> alt="<?php echo e($photo['caption']); ?>" decoding="async"></picture>
                                                <figcaption>Figure <?php echo e($figureNumber++); ?>. <?php echo e($photo['caption']); ?></figcaption>
                                            </figure>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </td>
                        </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </tbody>
            </table>

            <table class="progress-report-table progress-report-sign-off">
                <colgroup><col style="width: 46.427%"><col style="width: 53.573%"></colgroup>
                <tbody>
                    <tr><td colspan="2"><strong>Note: Please attach high resolution pictures with captions.</strong></td></tr>
                    <tr>
                        <td colspan="2" class="progress-report-signature progress-report-prepared-by">
                            <p>Prepared by:</p>
                            <div class="progress-report-signature-line">_________________________</div>
                            <p><strong><?php echo e(\Illuminate\Support\Str::upper($report->topic->user->name)); ?></strong></p>
                            <p>Project Leader</p>
                            <p>Date Signed: <?php echo e($report->prepared_by_date_signed?->format('F j, Y')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <td class="progress-report-signature">
                            <p>Monitored by:</p>
                            <div class="progress-report-signature-line">_________________________</div>
                            <p><?php echo e(config('work_plan.verifier.name')); ?></p>
                            <p>Head, Research and Extension/ Head, Research/<br>Center Head/ Assistant Director, Research</p>
                            <p>Date Signed:</p>
                        </td>
                        <td class="progress-report-signature">
                            <p>Reviewed and Verified by:</p>
                            <div class="progress-report-signature-line">__________________________</div>
                            <p><?php echo e(config('notice_to_proceed.issuing_officer.name')); ?></p>
                            <p>Vice Chancellor for Research, Development and Extension Services/ Director, Research</p>
                            <p>Date Signed:</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </main>
    </body>
</html>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\faculty\progress-reports\preview.blade.php ENDPATH**/ ?>