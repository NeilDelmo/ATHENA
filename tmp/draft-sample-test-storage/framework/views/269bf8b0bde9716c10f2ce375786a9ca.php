<?php
    $workPlan = collect($report->work_plan ?? []);
    $budget = collect($report->budget_utilization ?? [])->keyBy('type');
    $budgetTypes = ['Purchase Request', 'Cash Advance', 'Request of Payment'];
    $projectCost = (float) ($report->topic->estimated_budget ?? 0);
    $requestedTotal = $budget->sum(fn (array $entry): float => (float) ($entry['amount_requested'] ?? 0));
    $actualTotal = $budget->sum(fn (array $entry): float => (float) ($entry['actual_amount'] ?? 0));
    $money = fn (float $amount): string => 'PHP '.number_format($amount, 2);
    $percentage = fn (float $value): string => number_format($value, 2, '.', '').'%';
?>

<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Monitoring Tool Preview</title>
        <?php echo app('Illuminate\Foundation\Vite')('resources/css/monitoring-tool-print.css'); ?>
    </head>
    <body class="monitoring-preview-page">
        <main class="monitoring-sheet" aria-label="BatStateU monitoring tool preview">
            <header class="monitoring-header">
                <p>BatStateU-REC-RES-03</p>
                <div>
                    <h1>MONITORING TOOL</h1>
                    <p>Research Project Progress Report</p>
                </div>
                <p>Revision 03</p>
            </header>

            <table class="monitoring-table monitoring-metadata-table">
                <tbody>
                    <tr>
                        <th scope="row">Reporting Date</th>
                        <td><?php echo e($report->reporting_date?->format('F j, Y')); ?></td>
                        <th scope="row">Tracking No.</th>
                        <td>__________________________</td>
                    </tr>
                    <tr>
                        <th scope="row">Research Project Title</th>
                        <td colspan="3" class="monitoring-project-title"><?php echo e($report->topic->title); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Project Leader</th>
                        <td><?php echo e(\Illuminate\Support\Str::upper($report->topic->user->name)); ?></td>
                        <th scope="row">Project Duration</th>
                        <td><?php echo e($report->topic->estimated_duration_months); ?> months</td>
                    </tr>
                    <tr>
                        <th scope="row">Approved Project Cost</th>
                        <td><?php echo e($money($projectCost)); ?></td>
                        <th scope="row">Overall Progress</th>
                        <td class="monitoring-emphasis"><?php echo e($report->progress_percentage); ?>%</td>
                    </tr>
                </tbody>
            </table>

            <section class="monitoring-section">
                <h2>A. WORK PLAN</h2>
                <table class="monitoring-table monitoring-work-plan-table">
                    <colgroup>
                        <col class="activity-column">
                        <col class="weight-column">
                        <col class="target-column">
                        <col class="date-column">
                        <col class="accomplishment-column">
                        <col class="progress-column">
                        <col class="findings-column">
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col">Activity</th>
                            <th scope="col">Percent Weight</th>
                            <th scope="col">Physical Target<br>(Quantifiable)</th>
                            <th scope="col">Target Completion Date</th>
                            <th scope="col">Actual Accomplishment</th>
                            <th scope="col">Percentage of Accomplished Tasks</th>
                            <th scope="col">Notable Findings / Challenges</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $workPlan; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr>
                                <td>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($entry['objective'] ?? null)): ?>
                                        <strong>Objective:</strong> <?php echo e($entry['objective']); ?><br>
                                        <strong>Activity:</strong>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php echo e($entry['activity']); ?>

                                </td>
                                <td class="monitoring-number"><?php echo e($percentage((float) $entry['percent_weight'])); ?></td>
                                <td><?php echo e($entry['physical_target']); ?></td>
                                <td class="monitoring-date"><?php echo e(\Illuminate\Support\Carbon::parse($entry['target_completion_date'])->format('M j, Y')); ?></td>
                                <td><?php echo e($entry['actual_accomplishment']); ?></td>
                                <td class="monitoring-number"><?php echo e($percentage((float) $entry['accomplished_percentage'])); ?></td>
                                <td><?php echo e(($entry['findings'] ?? '') ?: '—'); ?></td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <tr class="monitoring-total-row">
                            <th scope="row">TOTAL</th>
                            <td class="monitoring-number"><?php echo e($percentage($workPlan->sum(fn (array $entry): float => (float) $entry['percent_weight']))); ?></td>
                            <td colspan="3"></td>
                            <td class="monitoring-number"><?php echo e($report->progress_percentage); ?>%</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="monitoring-section">
                <h2>B. BUDGET UTILIZATION</h2>
                <table class="monitoring-table monitoring-budget-table">
                    <thead>
                        <tr>
                            <th scope="col">Type of Request</th>
                            <th scope="col">Details of Request</th>
                            <th scope="col">Amount Requested</th>
                            <th scope="col">Actual Amount Disbursed</th>
                            <th scope="col">Utilization</th>
                            <th scope="col">Remarks / Challenges</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $budgetTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php
                                $entry = $budget->get($type, []);
                                $amountRequested = (float) ($entry['amount_requested'] ?? 0);
                                $actualAmount = (float) ($entry['actual_amount'] ?? 0);
                                $utilization = $amountRequested > 0 ? ($actualAmount / $amountRequested) * 100 : 0;
                            ?>
                            <tr>
                                <th scope="row"><?php echo e($type); ?></th>
                                <td class="monitoring-request-details"><?php echo e($entry['details'] ?? ''); ?></td>
                                <td class="monitoring-money"><?php echo e($money($amountRequested)); ?></td>
                                <td class="monitoring-money"><?php echo e($money($actualAmount)); ?></td>
                                <td class="monitoring-number"><?php echo e($percentage($utilization)); ?></td>
                                <td><?php echo e(($entry['remarks'] ?? '') ?: '—'); ?></td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <tr class="monitoring-total-row">
                            <th scope="row" colspan="2">TOTAL</th>
                            <td class="monitoring-money"><?php echo e($money($requestedTotal)); ?></td>
                            <td class="monitoring-money"><?php echo e($money($actualTotal)); ?></td>
                            <td class="monitoring-number"><?php echo e($percentage($requestedTotal > 0 ? ($actualTotal / $requestedTotal) * 100 : 0)); ?></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <footer class="monitoring-signatures">
                <section class="monitoring-signature">
                <p>Prepared by:</p>
                <div class="monitoring-signature-line"><?php echo e(strtoupper($report->submitter->name)); ?></div>
                <p>Project Leader</p>
                <p>Date Signed: <?php echo e($report->prepared_by_date_signed?->format('F j, Y') ?: '____________________'); ?></p>
                </section>
                <section class="monitoring-signature">
                    <p>Monitored by:</p>
                    <div class="monitoring-signature-line"><?php echo e(config('work_plan.verifier.name')); ?></div>
                    <p>Head, Research</p>
                    <p>Date Signed: ____________________</p>
                </section>
                <section class="monitoring-signature">
                    <p>Reviewed &amp; Evaluated by:</p>
                    <div class="monitoring-signature-line"><?php echo e(config('notice_to_proceed.issuing_officer.name')); ?></div>
                    <p>Vice Chancellor for Research, Development and Extension Services</p>
                    <p>Date Signed: ____________________</p>
                </section>
            </footer>
        </main>
    </body>
</html>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/faculty/monitoring-tools/preview.blade.php ENDPATH**/ ?>