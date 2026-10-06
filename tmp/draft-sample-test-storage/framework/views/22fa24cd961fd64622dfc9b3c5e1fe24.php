<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Attachment A - Work Plan Preview</title>
        <?php echo app('Illuminate\Foundation\Vite')('resources/css/work-plan-print.css'); ?>
    </head>
    <body class="work-plan-preview-page">
        <?php
            $yearCount = max(1, $workPlan['year_count']);
            $yearGroups = $yearCount > 1 ? array_chunk(range(1, $yearCount), 2) : [[1]];
        ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $yearGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $years): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <main
                class="<?php echo \Illuminate\Support\Arr::toCssClasses(['work-plan-sheet', 'is-extended-work-plan' => $yearCount > 1]); ?>"
                aria-label="BatStateU Attachment A Work Plan <?php echo e(count($years) === 1 ? 'year '.$years[0] : 'years '.$years[0].' and '.$years[1]); ?>"
                data-work-plan-sheet
            >
                <p class="work-plan-form-code">Attachment A-BatStateU-FO-RES-02</p>
                <h1>MAJOR ACTIVITIES/WORK PLAN</h1>

                <table class="work-plan-table">
                <colgroup>
                    <col style="width: 22.7691%">
                    <col style="width: 18.3508%">
                    <col style="width: 8.7903%">
                    <col style="width: 10.0110%">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php for($month = 1; $month <= 8; $month++): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <col style="width: 3.3992%">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <col style="width: 2.9996%">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php for($month = 10; $month <= 12; $month++): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <col style="width: 3.2949%">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </colgroup>
                <tbody>
                    <tr class="work-plan-title-row">
                        <th scope="row">Title:</th>
                        <td colspan="15" class="work-plan-value" data-work-plan-title-value></td>
                    </tr>
                    <tr class="work-plan-project-row">
                        <th scope="row">Project Title:</th>
                        <td colspan="15" class="work-plan-value" data-work-plan-project-title><?php echo e($workPlan['project_title']); ?></td>
                    </tr>
                    <tr class="work-plan-duration-row">
                        <th scope="row">
                            <span>Total Duration (in months):</span>
                            <span class="work-plan-metadata-value" data-work-plan-metadata-value><?php echo e($workPlan['total_duration_label']); ?></span>
                        </th>
                        <td colspan="3" class="work-plan-date-cell">
                            <span>Planned Start:</span>
                            <span class="work-plan-metadata-value" data-work-plan-metadata-value><?php echo e($workPlan['planned_start']); ?></span>
                        </td>
                        <td colspan="12" class="work-plan-date-cell">
                            <span>Planned End:</span>
                            <span class="work-plan-metadata-value" data-work-plan-metadata-value><?php echo e($workPlan['planned_end']); ?></span>
                        </td>
                    </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $years; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $year): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php ($yearEntries = $workPlan['entries_by_year'][$year] ?? []); ?>
                        <tr class="work-plan-heading-row" data-work-plan-year="<?php echo e($year); ?>">
                            <th rowspan="2" scope="col">Objectives</th>
                            <th rowspan="2" scope="col">Expected Output</th>
                            <th rowspan="2" colspan="2" scope="col">Activities or Workplan</th>
                            <th colspan="12" scope="colgroup">Y<?php echo e($year); ?></th>
                        </tr>
                        <tr class="work-plan-month-heading-row">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php for($month = 1; $month <= 12; $month++): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <th scope="col">M<?php echo e($month); ?></th>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $yearEntries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr class="work-plan-entry-row" data-work-plan-entry-row data-work-plan-entry-year="<?php echo e($year); ?>">
                                <td class="work-plan-objective-cell"><?php echo e($entry['objective']); ?></td>
                                <td class="work-plan-output-cell"><?php echo e($entry['expected_output']); ?></td>
                                <td colspan="2" class="work-plan-activity-cell"><?php echo e($entry['activity']); ?></td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php for($localMonth = 1; $localMonth <= 12; $localMonth++): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <?php ($globalMonth = (($year - 1) * 12) + $localMonth); ?>
                                    <td
                                        class="work-plan-month-mark <?php echo e(in_array($globalMonth, $entry['months'], true) ? 'is-active' : ''); ?>"
                                        <?php if(in_array($globalMonth, $entry['months'], true)): ?> data-scheduled-month="<?php echo e($globalMonth); ?>" <?php endif; ?>
                                    ></td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <tr class="work-plan-signature-row">
                        <td colspan="3">
                            <section class="work-plan-signature-block">
                                <p class="work-plan-signature-heading">Prepared by:</p>
                                <p class="work-plan-signature-name" data-signature-name><?php echo e($workPlan['prepared_by']); ?></p>
                                <p class="work-plan-signature-role">Project Leader</p>
                                <p class="work-plan-signature-date" data-signature-date>Date Signed:</p>
                            </section>
                        </td>
                        <td colspan="13">
                            <section class="work-plan-signature-block">
                                <p class="work-plan-signature-heading">Checked &amp; Verified by:</p>
                                <p class="work-plan-signature-name" data-signature-name><?php echo e($workPlan['verified_by']); ?></p>
                                <p class="work-plan-signature-role"><?php echo e($workPlan['verified_role']); ?></p>
                                <p class="work-plan-signature-date" data-signature-date>Date Signed:</p>
                            </section>
                        </td>
                    </tr>
                </tbody>
                </table>
            </main>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </body>
</html>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/faculty/work-plans/preview.blade.php ENDPATH**/ ?>