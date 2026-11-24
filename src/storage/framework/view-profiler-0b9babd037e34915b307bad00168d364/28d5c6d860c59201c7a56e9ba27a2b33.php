<div class="space-y-6">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canEdit): ?>
        <section class="space-y-4 rounded-2xl border border-gray-200 p-5 dark:border-slate-700">
            <div><h3 class="text-lg font-bold">Find your publication profile</h3><p class="mt-1 text-sm text-gray-600 dark:text-slate-300">Search by name or ORCID, then check the affiliation and sample papers. Similar names can belong to different people.</p></div>
            <template x-if="profile?.openalex_id">
                <div class="rounded-xl bg-gray-50 p-4 dark:bg-slate-900">
                    <p class="text-xs text-gray-500">Profile confirmed by you</p>
                    <p class="font-bold" x-text="profile.display_name"></p>
                    <p class="text-sm" x-text="profile.affiliation || 'Affiliation not listed'"></p>
                    <button type="button" @click="loadPapers()" :disabled="!!busy" class="mt-3 rounded-lg bg-red-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-50">Find my papers</button>
                </div>
            </template>
            <form @submit.prevent="searchAuthors" class="grid gap-3 sm:grid-cols-2">
                <label class="text-sm font-medium">Name or ORCID
                    <input x-model="authorQuery" type="search" required minlength="3" maxlength="150" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900">
                </label>
                <label class="text-sm font-medium">Institution to prioritize (optional)
                    <input x-model="institution" maxlength="150" placeholder="e.g. Batangas State University" class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900">
                </label>
                <button :disabled="!!busy" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-bold disabled:opacity-50 dark:border-slate-600 sm:col-span-2">Search author profiles</button>
            </form>
            <p x-show="authorSearched && !authors.length" x-cloak class="text-sm">No author profiles found. Try another name spelling or ORCID, or look up a paper by DOI below.</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <template x-for="author in authors" :key="author.id">
                    <article class="rounded-xl border border-gray-200 p-4 dark:border-slate-700">
                        <p class="font-bold" x-text="author.name"></p>
                        <p class="mt-1 text-sm" x-text="author.affiliation || 'Affiliation not listed'"></p>
                        <p class="mt-1 text-xs text-gray-500" x-text="(author.orcid || 'No ORCID listed') + ' · ' + author.works_count + ' indexed works'"></p>
                        <button type="button" @click="previewAuthor(author)" :disabled="!!busy" class="mt-3 secondary-action">Check sample papers</button>
                    </article>
                </template>
            </div>
            <template x-if="selectedAuthor">
                <div class="space-y-3 rounded-xl border border-red-200 p-4 dark:border-red-900">
                    <p class="font-bold" x-text="'Is this you? ' + selectedAuthor.name"></p>
                    <ul class="list-disc space-y-1 pl-5 text-sm"><template x-for="paper in samplePapers" :key="paper.openalex_id"><li x-text="paper.title + ' (' + (paper.year || 'Year unknown') + ')'"></li></template></ul>
                    <p x-show="!samplePapers.length" class="text-sm text-gray-500">No sample papers available. Confirm only if you recognize this profile.</p>
                    <label class="flex items-start gap-2 text-sm"><input type="checkbox" x-model="authorConfirmed" class="mt-1 rounded border-gray-300 text-red-700">I have checked the profile and it identifies me.</label>
                    <button type="button" @click="confirmAuthor()" :disabled="!authorConfirmed || !!busy" class="rounded-lg bg-red-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-50">Link this profile</button>
                </div>
            </template>
            <form @submit.prevent="saveScholar" class="border-t border-gray-200 pt-4 dark:border-slate-700">
                <label class="text-sm font-medium">Google Scholar profile link (optional)
                    <input type="url" x-model="scholarUrl" maxlength="2000" placeholder="https://scholar.google.com/citations?user=..." class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900">
                </label>
                <p class="mt-2 text-xs text-gray-500 dark:text-slate-400">Saved as a link to your public profile. Publication discovery uses OpenAlex and may differ from Google Scholar; this does not synchronize Scholar citations.</p>
                <button :disabled="!!busy" class="mt-3 secondary-action">Save profile link</button>
                <a x-show="profile?.scholar_url" :href="profile?.scholar_url" target="_blank" rel="noopener noreferrer" class="ml-4 secondary-action">Open Google Scholar ↗</a>
            </form>
        </section>

        <section class="space-y-4 rounded-2xl border border-gray-200 p-5 dark:border-slate-700">
            <h3 class="text-lg font-bold">Review and link publications</h3>
            <form @submit.prevent="lookupPaper" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <label class="flex-1 text-sm font-medium">Find a specific paper by DOI
                    <input x-model="doi" required maxlength="255" placeholder="10.1234/example or https://doi.org/..." class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900">
                </label>
                <button :disabled="!!busy" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-bold dark:border-slate-600">Look up DOI</button>
            </form>
            <p class="text-xs leading-5 text-gray-500 dark:text-slate-400">Results are cached for six hours. Confirm authorship and the project connection for each paper. An indexed preprint is not a published journal article.</p>
            <p x-show="papersSearched && !papers.length" x-cloak class="text-sm">No papers were returned. You can still record a missing publication manually.</p>
            <template x-for="paper in papers" :key="paper.openalex_id">
                <article x-data="{ confirmed: false }" class="space-y-3 rounded-xl border border-gray-200 p-4 dark:border-slate-700">
                    <h4 class="font-bold" x-text="paper.title"></h4>
                    <p class="text-sm" x-text="paper.authors"></p>
                    <p class="text-xs text-gray-500" x-text="[paper.venue || 'Venue unknown', paper.year || 'Year unknown', paper.type].join(' · ')"></p>
                    <p class="text-xs text-gray-500" x-text="'OpenAlex · Retrieved ' + new Date(paper.source_checked_at).toLocaleString()"></p>
                    <a x-show="paper.url" :href="paper.url" target="_blank" rel="noopener noreferrer" class="secondary-action">Check publication source ↗</a>
                    <label class="flex items-start gap-2 text-sm"><input type="checkbox" x-model="confirmed" :disabled="paper.saved" class="mt-1 rounded border-gray-300 text-red-700">I authored this paper and it is an output of this project.</label>
                    <button type="button" @click="importPaper(paper, confirmed)" :disabled="!confirmed || paper.saved || !!busy" class="rounded-lg bg-red-700 px-4 py-2 text-sm font-bold text-white disabled:opacity-50" x-text="paper.saved ? 'Saved to project' : 'Confirm and link paper'"></button>
                </article>
            </template>
            <div x-show="papersSearched && !lookupDoi" x-cloak class="flex items-center gap-4 text-sm">
                <button type="button" @click="loadPapers(page - 1)" :disabled="page <= 1 || !!busy" class="secondary-action">Previous</button><span x-text="'Page ' + page"></span><button type="button" @click="loadPapers(page + 1)" :disabled="!hasMore || !!busy" class="secondary-action">Next</button>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ownPublications->isNotEmpty()): ?>
                <form method="POST" action="<?php echo e(route('research.dissemination.link', $topic)); ?>" class="space-y-3 border-t border-gray-200 pt-4 dark:border-slate-700">
                    <?php echo csrf_field(); ?>
                    <label class="text-sm font-medium">Link one of your saved publications (latest 100)
                        <select name="publication_id" required class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $ownPublications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $paper): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($paper->id); ?>"><?php echo e($paper->title); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                    </label>
                    <button class="secondary-action">Link selected publication to this project</button>
                </form>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <details <?php if($errors->has('authors') || $errors->has('year') || $errors->has('confirmed')): ?> open <?php endif; ?>>
                <summary class="disclosure-summary secondary-action w-full justify-start text-left">Add a missing publication manually</summary>
                <form method="POST" action="<?php echo e(route('research.dissemination.manual', $topic)); ?>" class="mt-4 grid gap-3 sm:grid-cols-2">
                    <?php echo csrf_field(); ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['title' => ['Paper title', 'text', true], 'authors' => ['Authors', 'text', true], 'venue' => ['Journal or proceedings', 'text', false], 'year' => ['Publication year', 'number', true], 'doi' => ['DOI (optional)', 'text', false], 'url' => ['Publication URL (optional)', 'url', false]]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $name => [$label, $type, $required]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <label class="text-sm font-medium"><?php echo e($label); ?>

                            <input name="<?php echo e($name); ?>" type="<?php echo e($type); ?>" value="<?php echo e(old($name)); ?>" <?php if($required): echo 'required'; endif; ?> <?php if($name === 'year'): ?> min="1800" max="<?php echo e(now()->year); ?>" <?php endif; ?> class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900">
                        </label>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <label class="text-sm font-medium">Work type<select name="type" required class="mt-1 w-full rounded-lg border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-900"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['journal-article' => 'Journal article', 'proceedings-article' => 'Conference proceedings paper', 'preprint' => 'Preprint', 'other' => 'Other']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($value); ?>" <?php if(old('type') === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></select></label>
                    <label class="flex items-start gap-2 text-sm sm:col-span-2"><input type="checkbox" name="confirmed" value="1" required class="mt-1 rounded border-gray-300 text-red-700">I authored this work and it is an output of this project.</label>
                    <button class="rounded-lg bg-red-700 px-4 py-2 text-sm font-bold text-white sm:col-span-2">Save publication</button>
                </form>
            </details>
        </section>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <section class="space-y-4">
        <h3 class="text-lg font-bold">Publications linked to this project</h3>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $publications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $publication): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <article class="space-y-2 rounded-2xl border border-gray-200 p-5 dark:border-slate-700">
                <h4 class="font-bold"><?php echo e($publication->title); ?></h4>
                <p class="text-sm"><?php echo e($publication->authors); ?></p>
                <p class="text-sm text-gray-600 dark:text-slate-300"><?php echo e($publication->venue ?: 'Venue not recorded'); ?> · <?php echo e($publication->year ?: 'Year not recorded'); ?> · <?php echo e($publication->type ?: 'Type not recorded'); ?></p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($publication->doi): ?><p class="break-all text-xs text-gray-500">DOI: <?php echo e($publication->doi); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <p class="text-xs text-gray-500"><?php echo e($publication->source); ?> · Authorship confirmed by <?php echo e($publication->user->name); ?> on <?php echo e($publication->confirmed_at->format('M j, Y')); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($publication->source_checked_at): ?> · Retrieved <?php echo e($publication->source_checked_at->format('M j, Y')); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($publication->url): ?><a href="<?php echo e($publication->url); ?>" target="_blank" rel="noopener noreferrer" class="secondary-action">View publication ↗</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canEdit && $publication->user_id === Auth::id()): ?>
                    <form method="POST" action="<?php echo e(route('research.dissemination.unlink', [$topic, $publication])); ?>">
                        <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                        <button class="secondary-action">Unlink from this project (keep saved paper)</button>
                    </form>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </article>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <p class="rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-600 dark:border-slate-700 dark:text-slate-300">No publications linked yet. Completed research can be connected to published outputs whenever they become available.</p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php echo e($publications->links()); ?>

    </section>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\research\dissemination-publications.blade.php ENDPATH**/ ?>