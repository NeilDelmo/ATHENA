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
        <title>Notice to Proceed Preview</title>
        <?php echo app('Illuminate\Foundation\Vite')(['resources/css/notice-to-proceed-print.css', 'resources/js/paper-preview-pagination.js']); ?>
    </head>
    <body class="notice-to-proceed-preview-page">
        <main data-paper-sheet data-notice-to-proceed-sheet class="notice-to-proceed-sheet" aria-label="Notice to Proceed preview">
            <header class="notice-to-proceed-letterhead">
                <img src="<?php echo e(asset('images/batstateu-logo.png')); ?>" alt="Batangas State University seal">
                <div>
                    <p class="notice-to-proceed-republic">Republic of the Philippines</p>
                    <p class="notice-to-proceed-university">BATANGAS STATE UNIVERSITY</p>
                    <p class="notice-to-proceed-subtitle">The National Engineering University</p>
                    <p class="notice-to-proceed-campus">ARASOF-Nasugbu Campus</p>
                    <p class="notice-to-proceed-address">R. Martinez St., Brgy. Bucana, Nasugbu, Batangas, Philippines 4231</p>
                    <p>Tel Nos.: +63 43 416 0350 local 302</p>
                </div>
                <span aria-hidden="true"></span>
                <p class="notice-to-proceed-contact">E-mail Address: research.nasugbu@g.batstate-u.edu.ph &nbsp;|&nbsp; Website Address: www.batstate-u.edu.ph</p>
            </header>

            <div class="notice-to-proceed-office-heading">Research Office</div>

            <section class="notice-to-proceed-letter-body">
                <p class="notice-to-proceed-date"><?php echo e($notice['NOTICE_DATE']); ?></p>
                <p class="notice-to-proceed-recipients"><?php echo e($notice['RESEARCHER_NAMES']); ?></p>
                <p><?php echo e($notice['CAMPUS_LINE']); ?></p>

                <p class="notice-to-proceed-greeting">Dear Researchers:</p>

                <p>You are hereby awarded this <strong>NOTICE TO PROCEED</strong> for the institutionally approved research project entitled <strong>“<?php echo e($notice['PROJECT_TITLE']); ?>.”</strong></p>

                <p>Based on the Local Research Evaluation Committee (LREC) Resolution No. <?php echo e($notice['RESOLUTION_NUMBER']); ?>, S. <?php echo e($notice['RESOLUTION_YEAR']); ?>, the approved duration of the project is <?php echo e($notice['DURATION_WORDS']); ?> (<?php echo e($notice['DURATION_MONTHS']); ?>) <?php echo e($notice['DURATION_UNIT']); ?> which shall commence from <strong><?php echo e($notice['START_DATE']); ?> to <?php echo e($notice['END_DATE']); ?></strong>, with a budget amounting to <strong><?php echo e($notice['BUDGET_WORDS']); ?> (Php <?php echo e($notice['BUDGET_AMOUNT']); ?>)</strong> only. You are required to present monthly actual accomplishments to the assigned research monitoring personnel and submit quarterly monitoring reports to the Research Office of ARASOF-Nasugbu Campus during the conduct of this project.</p>

                <p>You are entitled to the reduction of teaching load during the approved duration of the project based on the University guidelines (please see the table below for your reference). Upon completion of the project, you shall be entitled to avail paper presentation support and publication support and incentives for the dissemination of your research outputs, and Technology Transfer support and incentives for the protection and commercialization of Intellectual Property assets that may be generated, subject to the existing policies of the University and availability of funds.</p>

                <table class="notice-to-proceed-load-table">
                    <thead>
                        <tr>
                            <th>Classification of Researcher</th>
                            <th>Reduction of Teaching Load</th>
                            <th>Number of Hours to be Rendered Weekly in the Conduct of Research</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Regular Faculty</td>
                            <td>6 units per research project</td>
                            <td>12 hours</td>
                        </tr>
                        <tr>
                            <td>Faculty with administrative assignment/local designation</td>
                            <td>3 units per research project*</td>
                            <td>6 hours</td>
                        </tr>
                    </tbody>
                </table>

                <p class="notice-to-proceed-note">*a maximum of 3 units must be retained for each faculty with administrative assignment/local designation<br>Note: maximum of two (2) projects at a time are allowed per faculty member</p>

                <p class="notice-to-proceed-closing">Any changes in the Line-Item Budget, project duration, scope, or composition of the project team shall be subject to the approval of the undersigned.</p>
                <p class="notice-to-proceed-closing">Please acknowledge receipt and acceptance of this NOTICE by affixing your signature below.</p>
                <p>Congratulations!</p>

                <div class="notice-to-proceed-signatures">
                    <section>
                        <p>Respectfully,</p>
                        <div class="notice-to-proceed-signature-space"></div>
                        <p class="notice-to-proceed-signatory"><?php echo e($notice['ISSUING_OFFICER_NAME']); ?></p>
                        <p><?php echo e($notice['ISSUING_OFFICER_TITLE']); ?></p>
                        <p><?php echo e($notice['ISSUING_OFFICER_COMMITTEE_ROLE']); ?></p>
                    </section>
                    <section>
                        <p>Checked and Verified by:</p>
                        <div class="notice-to-proceed-signature-space"></div>
                        <p class="notice-to-proceed-signatory"><?php echo e($notice['VERIFYING_OFFICER_NAME']); ?></p>
                        <p><?php echo e($notice['VERIFYING_OFFICER_TITLE']); ?></p>
                        <p><?php echo e($notice['VERIFYING_OFFICER_COMMITTEE_ROLE']); ?></p>
                    </section>
                </div>

                <section class="notice-to-proceed-conforme">
                    <p>Conforme:</p>
                    <div class="notice-to-proceed-conforme-line">____________________________________________</div>
                    <p class="notice-to-proceed-conforme-caption">Signature above printed name</p>
                    <p class="notice-to-proceed-conforme-date">Date:</p>
                </section>
            </section>
            <footer data-paper-footer class="notice-to-proceed-footer">Leading Innovations. Transforming Lives. Building the Nation.</footer>
        </main>
    </body>
</html>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\topics\notice-to-proceed-preview.blade.php ENDPATH**/ ?>