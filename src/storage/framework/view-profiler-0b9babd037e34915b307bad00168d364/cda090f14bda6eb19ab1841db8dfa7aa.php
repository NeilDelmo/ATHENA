<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Quarterly Accomplishment Report <?php echo e($year); ?> Q<?php echo e($quarter); ?></title>
        <style>
            @page {
                size: 8.5in 13in;
                margin: 1in 0;
                @bottom-center {
                    content: "Leading Innovations, Transforming Lives, Building the Nation";
                    color: #d2363b;
                    font: italic bold 11pt "Century Gothic", Arial, sans-serif;
                    vertical-align: bottom;
                    padding-bottom: 35.4pt;
                    white-space: nowrap;
                }
            }
            @page:first { margin-top: 0; }
            * { box-sizing: border-box; }
            body { min-width: 8.5in; margin: 0; color: #000; background: #e8e8e8; font: 12pt "Times New Roman", Times, serif; line-height: 1.24; }
            main { display: flex; flex-direction: column; width: 8.5in; min-height: 13in; margin: 24px auto; padding: 1in 1in 35.4pt; background: #fff; }
            header { position: relative; margin-top: -50.7pt; text-align: center; font-size: 12pt; }
            header img { position: absolute; left: -11.85pt; top: -13.2pt; width: 1.375in; height: auto; }
            header p { margin: 0; line-height: 11.55pt; font-size: 10pt; }
            header .republic { font-size: 12pt; line-height: 14.4pt; font-weight: bold; }
            header .university { font-size: 16pt; line-height: 17.8pt; font-weight: bold; }
            header .tagline { color: #d2363b; font: bold 12pt/13.8pt Arial, sans-serif; }
            header .campus { font-size: 12pt; line-height: 13.5pt; font-weight: bold; }
            header .address { font-weight: bold; }
            h1, h2, h3 { margin: 0; font-size: 14pt; line-height: 17.35pt; break-after: avoid; }
            h1 { text-align: center; }
            h2 { padding-left: 18pt; }
            .report-table > h3 { padding-left: 54pt; }
            .report-research > h3 { padding-left: 18pt; }
            .period, .college { text-align: center; }
            header .college { min-height: 14.9pt; margin-top: 7pt; font-size: 12pt; line-height: 14.9pt; font-weight: bold; }
            .period { margin: 0; font-size: 14pt; line-height: 17.35pt; }
            table { width: 7.383in; margin-left: -21.55pt; border-collapse: collapse; table-layout: fixed; font-size: 11pt; line-height: 1.24; }
            th, td { height: 22.15pt; padding: 0 5.4pt 8pt; border: 0.5pt solid #000; vertical-align: top; text-align: left; white-space: pre-line; overflow-wrap: anywhere; }
            th { background: #d9d9d9; font-weight: bold; text-align: center; vertical-align: middle; print-color-adjust: exact; }
            .report-presentation th:nth-child(1) { width: 28.5661%; }
            .report-presentation th:nth-child(2) { width: 18.1219%; }
            .report-presentation th:nth-child(3) { width: 14.6782%; }
            .report-presentation th:nth-child(4) { width: 17.3316%; }
            .report-presentation th:nth-child(5) { width: 21.3022%; }
            .report-publication th:nth-child(1), .report-seminar th:nth-child(1) { width: 28.558%; }
            .report-publication th:nth-child(2), .report-seminar th:nth-child(2) { width: 23.4409%; }
            .report-publication th:nth-child(3), .report-seminar th:nth-child(3) { width: 20.0075%; }
            .report-publication th:nth-child(4), .report-seminar th:nth-child(4) { width: 27.9936%; }
            .report-publication th { height: 43.75pt; }
            .report-publication td, .report-research table { font-size: 12pt; }
            .report-research th:nth-child(1) { width: 29.3454%; }
            .report-research th:nth-child(2) { width: 14.447%; }
            .report-research th:nth-child(3) { width: 40.6321%; }
            .report-research th:nth-child(4) { width: 15.5756%; }
            .numbered-rows tbody { counter-reset: report-row; }
            .numbered-rows td:first-child { padding-left: 36pt; text-indent: -18pt; }
            .numbered-rows td:first-child::before { counter-increment: report-row; content: counter(report-row) ". "; display: inline-block; width: 18pt; text-indent: 0; }
            thead { display: table-header-group; }
            tr, figure { break-inside: avoid; }
            .signatories { font-size: 12pt; line-height: 14.9pt; }
            .signatories section { break-inside: avoid; }
            .signatories p { min-height: 14.9pt; margin: 0; }
            .signatories .signature-name { margin-top: 14.9pt; font-weight: bold; }
            .signature-label { font-weight: bold; }
            .narrative { min-height: 21.65pt; font-size: 11pt; }
            .narrative p { margin: 0 0 8pt; }
            .narrative ol { list-style: decimal outside; padding-left: 24px; }
            .narrative ul { list-style: disc outside; padding-left: 24px; }
            figure { margin: 20px 0; text-align: center; }
            figure img { max-width: 100%; max-height: 7in; object-fit: contain; }
            figcaption { margin-top: 8px; font-size: 10pt; }
            footer { margin-top: auto; padding-top: 24pt; color: #d2363b; text-align: center; font: italic bold 11pt "Century Gothic", Arial, sans-serif; }
            .toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 16px; padding: 16px; font: 14px Arial, sans-serif; }
            .toolbar button, .toolbar a { padding: 10px 14px; border: 1px solid #777; border-radius: 6px; background: #fff; color: #111; text-decoration: none; cursor: pointer; }
            .toolbar button { background: #800000; border-color: #800000; color: #fff; }
            @media print { body { min-width: 0; background: white; } main { display: block; width: auto; min-height: auto; margin: 0 1in; padding: 21.3pt 0 0; } header { margin-top: 0; } footer, .toolbar { display: none; } }
            @media screen and (max-width: 850px) { main { margin: 0; } .toolbar { width: 100vw; justify-content: flex-start; } }
        </style>
        <?php echo app('Illuminate\Foundation\Vite')('resources/js/paper-preview-pagination.js'); ?>
    </head>
    <body>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($embedded ?? request()->boolean('embedded'))): ?>
        <nav class="toolbar" aria-label="Report preview actions">
            <a href="<?php echo e($backUrl ?? route('research_coordinator.reports.index', ['year' => $year, 'quarter' => $quarter])); ?>">Back to report</a>
            <button type="button" onclick="window.print()">Print or save PDF</button>
            <span><?php echo e($report?->submitted_at ? 'Submitted report' : 'Draft preview · Save your draft to include recent changes'); ?></span>
        </nav>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <main data-paper-sheet>
            <header>
                <img src="<?php echo e(asset('images/batstateu-logo.png')); ?>" alt="Batangas State University logo">
                <p class="republic">Republic of the Philippines</p>
                <p class="university">BATANGAS STATE UNIVERSITY</p>
                <p class="tagline">The National Engineering University</p>
                <p class="campus">ARASOF-Nasugbu Campus</p>
                <p class="address">R. Martinez St., Brgy. Bucana, Nasugbu, Batangas, Philippines 4231</p>
                <p>Tel Nos.: +63 43 416 0350 local 207</p>
                <p>Website Address: http://www.batstate-u.edu.ph</p>
                <p class="college"><?php echo e($report?->college ?? $coordinator->college); ?></p>
            </header>
            <h1>ACCOMPLISHMENT REPORT IN RESEARCH</h1>
            <p class="period"><?php echo e($period['start']->format('F')); ?> to <?php echo e($period['end']->format('F Y')); ?> (<?php echo e([1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th'][$quarter]); ?> Quarter)</p>
            <h2>I. Participation in Research Activities</h2>
            <?php if (isset($component)) { $__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.college-quarterly-report-table','data' => ['class' => 'report-table report-presentation numbered-rows','title' => 'a. Presentation','columns' => ['Event/Activity', 'Research Title', 'Date', 'Place', 'Personnel(s) involved'],'rows' => $reportData['presentations'] ?: [array_fill(0, 5, '')]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('college-quarterly-report-table'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'report-table report-presentation numbered-rows','title' => 'a. Presentation','columns' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['Event/Activity', 'Research Title', 'Date', 'Place', 'Personnel(s) involved']),'rows' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($reportData['presentations'] ?: [array_fill(0, 5, '')])]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698)): ?>
<?php $attributes = $__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698; ?>
<?php unset($__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698)): ?>
<?php $component = $__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698; ?>
<?php unset($__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.college-quarterly-report-table','data' => ['class' => 'report-table report-publication','title' => 'b. Publication','columns' => ["Journal Name/\nConference", "Research Title", "Date", "Personnel(s) involved"],'rows' => $reportData['publications'] ?: [array_fill(0, 4, '')]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('college-quarterly-report-table'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'report-table report-publication','title' => 'b. Publication','columns' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(["Journal Name/\nConference", "Research Title", "Date", "Personnel(s) involved"]),'rows' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($reportData['publications'] ?: [array_fill(0, 4, '')])]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698)): ?>
<?php $attributes = $__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698; ?>
<?php unset($__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698)): ?>
<?php $component = $__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698; ?>
<?php unset($__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.college-quarterly-report-table','data' => ['class' => 'report-table report-seminar numbered-rows','title' => 'c. Seminar/Training','columns' => ['Event/Activity', 'Date', 'Place', 'Personnel(s) involved'],'rows' => $report?->activityRows('seminars') ?: [array_fill(0, 4, '')]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('college-quarterly-report-table'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'report-table report-seminar numbered-rows','title' => 'c. Seminar/Training','columns' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['Event/Activity', 'Date', 'Place', 'Personnel(s) involved']),'rows' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report?->activityRows('seminars') ?: [array_fill(0, 4, '')])]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698)): ?>
<?php $attributes = $__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698; ?>
<?php unset($__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698)): ?>
<?php $component = $__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698; ?>
<?php unset($__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.college-quarterly-report-table','data' => ['class' => 'report-table report-research numbered-rows','title' => 'II. Research of Faculty Members','columns' => ['Title', 'Duration', 'Personnel(s) involved', 'Status'],'rows' => $reportData['research'] ?: [array_fill(0, 4, '')]]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('college-quarterly-report-table'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'report-table report-research numbered-rows','title' => 'II. Research of Faculty Members','columns' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['Title', 'Duration', 'Personnel(s) involved', 'Status']),'rows' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($reportData['research'] ?: [array_fill(0, 4, '')])]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698)): ?>
<?php $attributes = $__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698; ?>
<?php unset($__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698)): ?>
<?php $component = $__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698; ?>
<?php unset($__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698); ?>
<?php endif; ?>
            <h2>III. Other Accomplishments</h2>
            <div class="narrative"><?php echo app(\App\Support\ProposalRichText::class)->sanitize($report?->other_accomplishments ?? ''); ?></div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($report?->activityRows('ip_assets')): ?>
                <?php if (isset($component)) { $__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.college-quarterly-report-table','data' => ['title' => 'Intellectual Property Assets','columns' => ['Title', 'Type', 'Inventors / authors', 'Date Submitted', 'Remarks / evidence link'],'rows' => $report->activityRows('ip_assets')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('college-quarterly-report-table'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Intellectual Property Assets','columns' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['Title', 'Type', 'Inventors / authors', 'Date Submitted', 'Remarks / evidence link']),'rows' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($report->activityRows('ip_assets'))]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698)): ?>
<?php $attributes = $__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698; ?>
<?php unset($__attributesOriginalb7c19b4af1a7b4c4c35273dfe4dcc698); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698)): ?>
<?php $component = $__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698; ?>
<?php unset($__componentOriginalb7c19b4af1a7b4c4c35273dfe4dcc698); ?>
<?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <div class="signatories">
                <section>
                    <p class="signature-label">Prepared and submitted by:</p>
                    <p class="signature-name"><?php echo e($reportData['prepared_by_name'] ?? $report?->preparedBy?->name ?? $coordinator->name); ?></p>
                    <p>Research Coordinator<?php echo e(filled($report?->college ?? $coordinator->college) ? ', '.($report?->college ?? $coordinator->college) : ''); ?></p>
                    <p>Date Signed:</p>
                </section>
                <section>
                    <p class="signature-label">Checked and reviewed by:</p>
                    <p class="signature-name"><?php echo e($report?->reviewed_by); ?></p>
                    <p><?php echo e($report?->reviewed_by_title); ?></p>
                    <p>Date Signed:</p>
                </section>
            </div>
            <h2>IV. Supporting Documentation</h2>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reportEvidence ?? $report?->evidence): ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $reportEvidence ?? $report->evidence; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $evidence): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php
                        $evidenceUrl = $evidence['url'] ?? (isset($submission)
                            ? route('college-quarterly-submissions.evidence', ['submission' => $submission, 'index' => $index])
                            : route('college-quarterly-reports.evidence', ['report' => $report, 'index' => $index]));
                        $evidenceLabel = $report?->evidenceLabel($evidence) ?: ($evidence['caption'] ?? 'Supporting documentation '.($index + 1));
                    ?>
                    <figure>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($evidence['mime_type'] ?? 'image/png', ['image/jpeg', 'image/png'], true)): ?>
                            <img src="<?php echo e($evidence['download_url'] ?? $evidenceUrl); ?>" alt="<?php echo e($evidenceLabel); ?>">
                        <?php else: ?>
                            <a href="<?php echo e($evidenceUrl); ?>"><?php echo e($evidence['original_filename']); ?></a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <figcaption><?php echo e($evidenceLabel); ?></figcaption>
                    </figure>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <footer data-paper-footer>Leading Innovations, Transforming Lives, Building the Nation</footer>
        </main>
    </body>
</html>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\research_coordinator\report-print.blade.php ENDPATH**/ ?>