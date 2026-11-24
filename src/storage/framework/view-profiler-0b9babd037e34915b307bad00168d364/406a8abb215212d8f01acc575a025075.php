<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

     <?php $__env->slot('header', null, []); ?> 
        <?php if (isset($component)) { $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => 'Journal Finder &amp; Conferences','subtitle' => $topic->title]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Journal Finder &amp; Conferences','subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($topic->title)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

             <?php $__env->slot('actions', null, []); ?> 
                <span class="rounded-full bg-slate-100 px-3 py-1.5 text-[11px] font-black uppercase tracking-wider text-slate-600 dark:bg-slate-800 dark:text-slate-300"><?php echo e($topic->project_status); ?> project</span>
                <?php if (isset($component)) { $__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.back-link','data' => ['href' => ''.e(route('topics.show', $topic)).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('back-link'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => ''.e(route('topics.show', $topic)).'']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>
Back to project <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01)): ?>
<?php $attributes = $__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01; ?>
<?php unset($__attributesOriginal5426bd0bea02df2e6dd2a60e50fa4c01); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01)): ?>
<?php $component = $__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01; ?>
<?php unset($__componentOriginal5426bd0bea02df2e6dd2a60e50fa4c01); ?>
<?php endif; ?>
             <?php $__env->endSlot(); ?>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $attributes = $__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__attributesOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e)): ?>
<?php $component = $__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e; ?>
<?php unset($__componentOriginalf8d4ea307ab1e58d4e472a43c8548d8e); ?>
<?php endif; ?>
     <?php $__env->endSlot(); ?>

    <div class="mx-auto max-w-6xl space-y-6">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-950 sm:p-6">
            <div class="flex items-start gap-4">
                <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.75 5.75A1.75 1.75 0 0 1 6.5 4h11A1.75 1.75 0 0 1 19.25 5.75v12.5A1.75 1.75 0 0 1 17.5 20h-11a1.75 1.75 0 0 1-1.75-1.75V5.75Z"></path><path stroke-linecap="round" d="M8 8h8M8 12h8M8 16h5"></path></svg>
                </span>
                <div>
                    <h3 class="text-lg font-bold text-gray-950 dark:text-white">Choose a suitable publishing venue</h3>
                    <p class="mt-1 text-base leading-7 text-gray-600 dark:text-slate-300">
                        Find journals that publish work related to your manuscript, check their indexing, and track the journal’s decision through to publication.
                    </p>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($topic->isCompletedProject()): ?>
                        <p class="mt-2 text-sm font-semibold text-gray-700 dark:text-slate-200">Research reporting is complete and remains archived. Journal searches do not alter those reports.</p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
            <ol class="mt-5 grid gap-2 text-sm sm:grid-cols-5">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['Choose a journal', 'Submit manuscript', 'Journal review', 'Acceptance', 'Publication']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $step): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <li class="flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 text-gray-700 dark:bg-slate-900 dark:text-slate-200"><span class="font-bold text-red-700 dark:text-red-300"><?php echo e($loop->iteration); ?>.</span><?php echo e($step); ?></li>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </ol>
            <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-slate-300">Submitting or receiving acceptance does not mean the paper is published. After publication, check Google Scholar separately; inclusion depends on Google’s discovery and indexing of the article.</p>
        </section>

        <?php if (isset($component)) { $__componentOriginalecd07a72f49f9557dc5b16fe7dc8b9c5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalecd07a72f49f9557dc5b16fe7dc8b9c5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.journal-finder','data' => ['endpoint' => route('research.dissemination.journals.search', $topic),'initialQuery' => $topic->title,'initialContext' => $projectAbstract,'canTrack' => $canTrackJournals,'finderUrl' => route('research-support.index', ['topic' => $topic->id]).'#journal-finder','heading' => 'Find a journal for this manuscript','description' => 'The project title and report abstract are prefilled. Refine them to match the manuscript you intend to submit.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('journal-finder'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['endpoint' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('research.dissemination.journals.search', $topic)),'initial-query' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($topic->title),'initial-context' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($projectAbstract),'can-track' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($canTrackJournals),'finder-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('research-support.index', ['topic' => $topic->id]).'#journal-finder'),'heading' => 'Find a journal for this manuscript','description' => 'The project title and report abstract are prefilled. Refine them to match the manuscript you intend to submit.']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalecd07a72f49f9557dc5b16fe7dc8b9c5)): ?>
<?php $attributes = $__attributesOriginalecd07a72f49f9557dc5b16fe7dc8b9c5; ?>
<?php unset($__attributesOriginalecd07a72f49f9557dc5b16fe7dc8b9c5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalecd07a72f49f9557dc5b16fe7dc8b9c5)): ?>
<?php $component = $__componentOriginalecd07a72f49f9557dc5b16fe7dc8b9c5; ?>
<?php unset($__componentOriginalecd07a72f49f9557dc5b16fe7dc8b9c5); ?>
<?php endif; ?>

        <?php if (isset($component)) { $__componentOriginalf1c856e9295e1bee83e9ad0561d765c2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf1c856e9295e1bee83e9ad0561d765c2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.journal-submission-tracker','data' => ['topic' => $topic,'journalSubmissions' => $journalSubmissions,'canTrackJournals' => $canTrackJournals]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('journal-submission-tracker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['topic' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($topic),'journal-submissions' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($journalSubmissions),'can-track-journals' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($canTrackJournals)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf1c856e9295e1bee83e9ad0561d765c2)): ?>
<?php $attributes = $__attributesOriginalf1c856e9295e1bee83e9ad0561d765c2; ?>
<?php unset($__attributesOriginalf1c856e9295e1bee83e9ad0561d765c2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf1c856e9295e1bee83e9ad0561d765c2)): ?>
<?php $component = $__componentOriginalf1c856e9295e1bee83e9ad0561d765c2; ?>
<?php unset($__componentOriginalf1c856e9295e1bee83e9ad0561d765c2); ?>
<?php endif; ?>

        <?php
            $validationContext = session('dissemination_validation_context', []);
            $restoreConferenceInput = $errors->any() && data_get($validationContext, 'topic_id') === $topic->id && data_get($validationContext, 'kind') === 'conference';
            $conferenceDraft = collect(['candidate_key', 'title', 'url', 'official_url', 'location', 'submission_deadline', 'event_date', 'attendance_mode', 'fees', 'publication_details'])
                ->mapWithKeys(fn ($field) => [$field => $restoreConferenceInput && data_get($validationContext, 'record_id') === null ? old($field, '') : ''])->all();
        ?>
        <section id="conferences" class="scroll-mt-28" x-data="projectDissemination(<?php echo e(Illuminate\Support\Js::from(['endpoints' => ['conferenceSearch' => route('research.dissemination.conferences.search', $topic)], 'query' => $conferenceQuery, 'conferenceDraft' => $conferenceDraft])); ?>)">
            <h2 class="mb-4 text-xl font-semibold text-gray-950 dark:text-white">Conference shortlist</h2>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
                <p role="status" class="mb-4 rounded-lg bg-gray-50 p-3 text-sm text-gray-700 dark:bg-slate-900 dark:text-slate-200"><?php echo e(session('success')); ?></p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($restoreConferenceInput && $errors->any()): ?>
                <ul role="alert" class="mb-4 list-inside list-disc rounded-lg bg-red-50 p-3 text-sm text-red-800 dark:bg-red-950/40 dark:text-red-200"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><li><?php echo e($error); ?></li><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></ul>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <p x-show="error" x-cloak role="alert" class="mb-4 text-sm text-red-700 dark:text-red-300" x-text="error"></p>
            <?php echo $__env->make('research.dissemination-conferences', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </section>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\research\dissemination.blade.php ENDPATH**/ ?>