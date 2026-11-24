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
        <title>Attachment C - Curriculum Vitae Preview</title>
        <?php echo app('Illuminate\Foundation\Vite')(['resources/css/curriculum-vitae-print.css', 'resources/js/paper-preview-pagination.js']); ?>
    </head>
    <body class="cv-preview-page">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $curriculumVitae['people']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $personIndex => $person): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <main data-paper-sheet class="cv-sheet" data-proposal-preview-section="cv-person-<?php echo e($personIndex + 1); ?>" aria-label="BatStateU Attachment C Curriculum Vitae for <?php echo e($person['first_name']); ?> <?php echo e($person['last_name']); ?>">
                <div class="cv-form">
                    <header class="cv-title-block">
                        <p>Attachment C-BatStateU-FO-RES-02</p>
                        <h1>CURRICULUM VITAE</h1>
                        <p aria-hidden="true">&nbsp;</p>
                    </header>

                    <table class="cv-table cv-name-table">
                        <colgroup><col><col><col></colgroup>
                        <tbody>
                            <tr class="cv-section-heading"><th colspan="3">PERSONAL INFORMATION</th></tr>
                            <tr class="cv-label-row cv-borderless-columns"><th>Last Name</th><th>First Name</th><th>Middle Name</th></tr>
                            <tr class="cv-value-row cv-borderless-columns"><td><?php echo e($person['last_name']); ?></td><td><?php echo e($person['first_name']); ?></td><td><?php echo e($person['middle_name']); ?></td></tr>
                        </tbody>
                    </table>

                    <table class="cv-table cv-personal-details-table">
                        <colgroup><col><col><col></colgroup>
                        <tbody>
                            <tr class="cv-data-row"><td>Agency: <strong class="cv-output-value"><?php echo e($person['agency']); ?></strong></td><td>Gender: <?php echo e($person['gender'] === 'male' ? '☒ Male  ☐ Female' : ($person['gender'] === 'female' ? '☐ Male  ☒ Female' : '☐ Male  ☐ Female')); ?></td><td>Birthday (mm/dd/yyyy):&nbsp;&nbsp;&nbsp;<strong class="cv-output-value"><?php echo e($person['birthday']); ?></strong></td></tr>
                            <tr class="cv-data-row"><td colspan="3">&nbsp;</td></tr>
                        </tbody>
                    </table>

                    <table class="cv-table cv-address-table">
                        <colgroup><col><col><col><col></colgroup>
                        <tbody>
                            <tr class="cv-address-value-row cv-borderless-columns"><td><?php echo e($person['street']); ?></td><td><?php echo e($person['barangay']); ?></td><td><?php echo e($person['municipality']); ?></td><td><?php echo e($person['province']); ?></td></tr>
                            <tr class="cv-address-label-row cv-borderless-columns"><th>Street</th><th>Barangay</th><th>Municipality</th><th>Province</th></tr>
                        </tbody>
                    </table>

                    <table class="cv-table cv-contact-table">
                        <colgroup><col><col><col></colgroup>
                        <tbody>
                            <tr class="cv-data-row"><td>Landline no.: <strong class="cv-output-value"><?php echo e($person['landline']); ?></strong></td><td>Cellphone no.: <strong class="cv-output-value">(+63) <?php echo e(substr($person['cellphone'], 1)); ?></strong></td><td>Email Address: <strong class="cv-output-value"><?php echo e($person['email']); ?></strong></td></tr>
                        </tbody>
                    </table>

                    <table class="cv-table cv-detail-table cv-academic-table">
                        <colgroup><col><col><col><col><col><col><col><col></colgroup>
                        <thead>
                            <tr class="cv-section-heading"><th colspan="8">ACADEMIC BACKGROUND</th></tr>
                            <tr class="cv-detail-heading"><th>Degree Earned<span class="cv-header-hint">(from highest to lowest)</span></th><th>Major Field</th><th>Sector</th><th>Learning Institution</th><th>Status<span class="cv-header-hint">(graduated, ongoing, dropped, terminated)</span></th><th colspan="2">Year Taken<span class="cv-header-hint">(yyyy)</span></th><th>Thesis</th></tr>
                            <tr class="cv-detail-heading cv-detail-subheading"><th>&nbsp;</th><th>&nbsp;</th><th>&nbsp;</th><th>&nbsp;</th><th>&nbsp;</th><th>Start</th><th>End</th><th>&nbsp;</th></tr>
                        </thead>
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $person['academic_background']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <tr class="cv-data-row"><td><?php echo e($entry['degree'] ?? ''); ?></td><td><?php echo e($entry['major_field'] ?? ''); ?></td><td><?php echo e($entry['sector'] ?? ''); ?></td><td><?php echo e($entry['learning_institution'] ?? ''); ?></td><td><?php echo e($entry['status'] ?? ''); ?></td><td><?php echo e($entry['year_start'] ?? ''); ?></td><td><?php echo e($entry['year_end'] ?? ''); ?></td><td><?php echo e($entry['thesis'] ?? ''); ?></td></tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <tr class="cv-data-row"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>

                    <table class="cv-table cv-detail-table cv-scholarship-table">
                        <colgroup><col><col><col><col><col><col><col><col><col><col></colgroup>
                        <thead>
                            <tr class="cv-section-heading cv-section-heading-light"><th colspan="10">SCHOLARSHIP</th></tr>
                            <tr class="cv-detail-heading"><th>Sponsor</th><th>Primary Sponsor<span class="cv-header-hint">(Yes or No)</span></th><th colspan="2">Scholarship Period<span class="cv-header-hint">(yyyy-mm-dd)</span></th><th colspan="2">Extension Period<span class="cv-header-hint">(yyyy-mm-dd)</span></th><th colspan="4">Scholarship Grants</th></tr>
                            <tr class="cv-detail-heading cv-detail-subheading"><th>&nbsp;</th><th>&nbsp;</th><th>Start</th><th>End</th><th>Start</th><th>End</th><th>Item Expenses</th><th>Amount Approved</th><th>Amount Released</th><th>Date Released<span class="cv-header-hint">(yyyy-mm-dd)</span></th></tr>
                        </thead>
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $person['scholarships']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <tr class="cv-data-row"><td><?php echo e($entry['sponsor'] ?? ''); ?></td><td><?php echo e($entry['primary_sponsor'] ?? ''); ?></td><td><?php echo e($entry['period_start'] ?? ''); ?></td><td><?php echo e($entry['period_end'] ?? ''); ?></td><td><?php echo e($entry['extension_start'] ?? ''); ?></td><td><?php echo e($entry['extension_end'] ?? ''); ?></td><td><?php echo e($entry['item_expenses'] ?? ''); ?></td><td><?php echo e($entry['amount_approved'] ?? ''); ?></td><td><?php echo e($entry['amount_released'] ?? ''); ?></td><td><?php echo e($entry['date_released'] ?? ''); ?></td></tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <tr class="cv-data-row"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>

                    <table class="cv-table cv-detail-table cv-employment-table">
                        <colgroup><col><col><col><col><col><col></colgroup>
                        <thead>
                            <tr class="cv-section-heading"><th colspan="6">EMPLOYMENT</th></tr>
                            <tr class="cv-detail-heading"><th>Agency</th><th>Plantilla Position</th><th>Status of Appointment<span class="cv-header-hint">(permanent, temporary, contractual, casual, emergency)</span></th><th colspan="2">Date of Appointment<span class="cv-header-hint">(yyyy-mm-dd)</span></th><th>Monthly Salary</th></tr>
                            <tr class="cv-detail-heading cv-detail-subheading"><th>&nbsp;</th><th>&nbsp;</th><th>&nbsp;</th><th>Start</th><th>End</th><th>&nbsp;</th></tr>
                        </thead>
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $person['employment']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <tr class="cv-data-row"><td><?php echo e($entry['agency'] ?? ''); ?></td><td><?php echo e($entry['plantilla_position'] ?? ''); ?></td><td><?php echo e($entry['appointment_status'] ?? ''); ?></td><td><?php echo e($entry['start_date'] ?? ''); ?></td><td><?php echo e($entry['end_date'] ?? ''); ?></td><td><?php echo e($entry['monthly_salary'] ?? ''); ?></td></tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <tr class="cv-data-row"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>

                    <table class="cv-table cv-detail-table cv-specialization-table">
                        <colgroup><col><col></colgroup>
                        <thead>
                            <tr class="cv-section-heading"><th colspan="2">FIELD OF SPECIALIZATION</th></tr>
                            <tr class="cv-detail-heading"><th>Field of Specialization</th><th>Primary Field<span class="cv-header-hint">(Yes or No)</span></th></tr>
                        </thead>
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $person['specializations']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <tr class="cv-data-row"><td><?php echo e($entry['field'] ?? ''); ?></td><td><?php echo e($entry['primary_field'] ?? ''); ?></td></tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <tr class="cv-data-row"><td>&nbsp;</td><td>&nbsp;</td></tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>

                    <table class="cv-table cv-detail-table cv-awards-table">
                        <colgroup><col><col><col><col><col></colgroup>
                        <thead>
                            <tr class="cv-section-heading"><th colspan="5">R&amp;D AWARDS</th></tr>
                            <tr class="cv-detail-heading"><th>Title of R&amp;D Award</th><th>Rank</th><th>Category<span class="cv-header-hint">(Local - In-house, Local – Regional, Local – National, International)</span></th><th>Granting Institution</th><th>Year Granted<span class="cv-header-hint">(yyyy)</span></th></tr>
                        </thead>
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $person['awards']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <tr class="cv-data-row"><td><?php echo e($entry['title'] ?? ''); ?></td><td><?php echo e($entry['rank'] ?? ''); ?></td><td><?php echo e($entry['category'] ?? ''); ?></td><td><?php echo e($entry['granting_institution'] ?? ''); ?></td><td><?php echo e($entry['year_granted'] ?? ''); ?></td></tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <tr class="cv-data-row"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>

                    <table class="cv-table cv-detail-table cv-projects-table">
                        <colgroup><col><col><col><col><col><col></colgroup>
                        <thead>
                            <tr class="cv-section-heading"><th colspan="6">R&amp;D PROJECTS HEADED/CONDUCTED</th></tr>
                            <tr class="cv-detail-heading"><th>Title of R&amp;D Project</th><th>Designation<span class="cv-header-hint">(Co-Program Leader/ Co-project Leader/Program Coordinator/ Program Leader/Project Leader/ Research &amp; Development Staff)</span></th><th>Sector<span class="cv-header-hint">(Agricultural Resources Management, Crops, Forestry and Env’t, Livestock, Socio-economics)</span></th><th>Current Status<span class="cv-header-hint">(Approved/Completed with TR/Completed w/o TR/ Deferred (approval)/ Deferred (impl’n)/ Disapproved/Extended New/Ongoing/Proposed Reactivated/Recommended Rejected/Suspended Terminated/Terminated w/ TR)</span></th><th colspan="2">Year <span class="cv-header-hint">(yyyy)</span></th></tr>
                            <tr class="cv-detail-heading cv-detail-subheading cv-projects-subheading"><th>&nbsp;</th><th>&nbsp;</th><th>&nbsp;</th><th>&nbsp;</th><th>From</th><th>To</th></tr>
                        </thead>
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $person['projects']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <tr class="cv-data-row"><td><?php echo e($entry['title'] ?? ''); ?></td><td><?php echo e($entry['designation'] ?? ''); ?></td><td><?php echo e($entry['sector'] ?? ''); ?></td><td><?php echo e($entry['current_status'] ?? ''); ?></td><td><?php echo e($entry['year_from'] ?? ''); ?></td><td><?php echo e($entry['year_to'] ?? ''); ?></td></tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <tr class="cv-data-row"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>

                    <div class="cv-source-table-gap" aria-hidden="true"></div>

                    <table class="cv-table cv-detail-table cv-publications-table">
                        <colgroup><col><col><col><col><col></colgroup>
                        <thead>
                            <tr class="cv-section-heading cv-section-heading-centered"><th colspan="5">R&amp;D RELATED PUBLICATIONS (for the last 3 years)</th></tr>
                            <tr class="cv-detail-heading"><th>Title of R&amp;D Publication</th><th>Year Published<span class="cv-header-hint">(yyyy)</span></th><th>Place of Publication</th><th>Publication Group<span class="cv-header-hint">(R &amp; D Papers in Scientific Journals/Technical Reports/Research Abstracts/Papers Presented in Conferences/Books/ News Articles)</span></th><th>Authoring Type<span class="cv-header-hint">(Sole-Author/Co-Author/Editor/Co-Editor/Main Author)</span></th></tr>
                        </thead>
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $person['publications']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <tr class="cv-data-row"><td><?php echo e($entry['title'] ?? ''); ?></td><td><?php echo e($entry['year_published'] ?? ''); ?></td><td><?php echo e($entry['place'] ?? ''); ?></td><td><?php echo e($entry['publication_group'] ?? ''); ?></td><td><?php echo e($entry['authoring_type'] ?? ''); ?></td></tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <tr class="cv-data-row"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>

                    <table class="cv-table cv-detail-table cv-presentations-table">
                        <colgroup><col><col><col><col><col><col></colgroup>
                        <thead>
                            <tr class="cv-section-heading cv-section-heading-centered"><th colspan="6">R&amp;D PRESENTATION (for the last 3 years)</th></tr>
                            <tr class="cv-detail-heading"><th>Title of Research Paper</th><th>Conference Title</th><th>Category<span class="cv-header-hint">(Local - In-house, Local – Regional, Local – National, International)</span></th><th>Date</th><th>Venue</th><th>Sponsor</th></tr>
                        </thead>
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $person['presentations']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <tr class="cv-data-row"><td><?php echo e($entry['title'] ?? ''); ?></td><td><?php echo e($entry['conference_title'] ?? ''); ?></td><td><?php echo e($entry['category'] ?? ''); ?></td><td><?php echo e($entry['date'] ?? ''); ?></td><td><?php echo e($entry['venue'] ?? ''); ?></td><td><?php echo e($entry['sponsor'] ?? ''); ?></td></tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <tr class="cv-data-row"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </body>
</html>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\faculty\curriculum-vitae\preview.blade.php ENDPATH**/ ?>