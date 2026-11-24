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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-header','data' => ['title' => 'User Directory','subtitle' => 'Browse all ATHENA users and manage Research Coordinator and Research Office Secretary assignments. Each college can have one Research Coordinator.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'User Directory','subtitle' => 'Browse all ATHENA users and manage Research Coordinator and Research Office Secretary assignments. Each college can have one Research Coordinator.']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

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

    <div
        x-data="{
            selectedCollege: <?php echo \Illuminate\Support\Js::from($selectedCollege)->toHtml() ?>,
            search: <?php echo \Illuminate\Support\Js::from($search)->toHtml() ?>,
            colleges: <?php echo \Illuminate\Support\Js::from($colleges)->toHtml() ?>,
            members: <?php echo \Illuminate\Support\Js::from($memberFilters)->toHtml() ?>,
            dialogOpen: false,
            memberName: '',
            memberCollege: '',
            replacementCoordinator: '',
            actionUrl: '',
            isAssigned: false,
            roleLabel: 'Research Coordinator',
            init() {
                this.$watch('search', () => this.syncUrl());
            },
            setCollege(college) {
                this.selectedCollege = college;
                this.syncUrl();
            },
            syncUrl() {
                const url = new URL(window.location.href);
                const query = this.search.trim();

                if (this.selectedCollege === 'all') {
                    url.searchParams.delete('college');
                } else {
                    url.searchParams.set('college', this.selectedCollege);
                }

                if (query === '') {
                    url.searchParams.delete('search');
                } else {
                    url.searchParams.set('search', query);
                }

                window.history.replaceState(null, '', url.toString());
            },
            matchesMember(member) {
                const query = this.search.trim().toLowerCase();
                const matchesCollege = this.selectedCollege === 'all' || member.college === this.selectedCollege;
                const matchesSearch = query === '' || member.search.includes(query);

                return matchesCollege && matchesSearch;
            },
            visibleMembers() {
                return this.members.filter((member) => this.matchesMember(member));
            },
            openRoleDialog(name, url, assigned, role, college = '', replacement = '') {
                this.memberName = name;
                this.actionUrl = url;
                this.isAssigned = assigned;
                this.roleLabel = role;
                this.memberCollege = college;
                this.replacementCoordinator = replacement;
                this.dialogOpen = true;
            },
        }"
        class="space-y-5"
        data-faculty-directory
        data-initial-college="<?php echo e($selectedCollege); ?>"
        data-initial-search="<?php echo e($search); ?>"
    >
        <nav class="overflow-x-auto rounded-2xl border border-gray-200 bg-white p-2 shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-label="Filter members by college">
            <div class="flex min-w-max gap-1" role="tablist" aria-label="College">
                <button
                    type="button"
                    role="tab"
                    x-on:click="setCollege('all')"
                    x-bind:aria-selected="selectedCollege === 'all'"
                    x-bind:class="selectedCollege === 'all' ? 'bg-gray-900 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'"
                    class="border border-slate-400 dark:border-slate-500 rounded-xl px-4 py-2.5 text-xs font-black transition"
                    data-college-tab="all"
                >
                    All
                </button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $colleges; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $acronym => $college): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <button
                        type="button"
                        role="tab"
                        x-on:click="setCollege(<?php echo \Illuminate\Support\Js::from($acronym)->toHtml() ?>)"
                        x-bind:aria-selected="selectedCollege === <?php echo \Illuminate\Support\Js::from($acronym)->toHtml() ?>"
                        x-bind:class="selectedCollege === <?php echo \Illuminate\Support\Js::from($acronym)->toHtml() ?> ? 'bg-gray-900 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'"
                        class="border border-slate-400 dark:border-slate-500 rounded-xl px-4 py-2.5 text-xs font-black transition"
                        title="<?php echo e($college); ?>"
                        data-college-tab="<?php echo e($acronym); ?>"
                    >
                        <?php echo e($acronym); ?>

                    </button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        </nav>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('status')): ?>
            <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700 dark:border-green-900 dark:bg-green-950/40 dark:text-green-300"><?php echo e(session('status')); ?></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"><?php echo e($errors->first()); ?></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-4 border-b border-gray-100 px-5 py-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-base font-black text-gray-900 dark:text-white" x-text="selectedCollege === 'all' ? 'All members' : selectedCollege + ' members'"></h3>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-slate-400" x-text="selectedCollege === 'all' ? 'Every registered ATHENA account.' : colleges[selectedCollege]"></p>
                </div>
                <div class="flex flex-col gap-2 sm:items-end">
                    <div class="relative w-full sm:w-72">
                        <label for="faculty-search" class="sr-only">Search users</label>
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m2.1-5.4a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" /></svg>
                        <input
                            id="faculty-search"
                            type="search"
                            x-model.debounce.250ms="search"
                            placeholder="Search users"
                            autocomplete="off"
                            class="block w-full rounded-xl border-gray-200 py-2.5 pl-9 pr-3 text-sm shadow-sm focus:border-red-500 focus:ring-red-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500"
                            data-faculty-search
                        >
                    </div>
                    <span class="text-xs font-bold text-gray-500 dark:text-slate-400" x-text="visibleMembers().length + (visibleMembers().length === 1 ? ' member' : ' members')"></span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[64rem] table-fixed divide-y divide-gray-100 dark:divide-slate-800">
                    <thead class="bg-gray-50 dark:bg-slate-950/50">
                        <tr>
                            <th scope="col" class="w-64 px-5 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-400">Member</th>
                            <th scope="col" class="w-64 px-5 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-400">Email</th>
                            <th x-show="selectedCollege === 'all'" scope="col" class="px-5 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-400">College</th>
                            <th scope="col" class="w-40 px-5 py-3 text-center text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-400">Research Coordinator</th>
                            <th scope="col" class="w-40 px-5 py-3 text-center text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-400">Research Office Secretary</th>
                        </tr>
                    </thead>
                    <tbody x-cloak class="divide-y divide-gray-100 dark:divide-slate-800">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $members; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $member): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php
                                $isCoordinator = $member->hasRole('research_coordinator');
                                $isSecretary = $member->hasRole('research_secretary');
                                $isResearchHead = $member->hasRole('research_head');
                                $memberCollege = array_search($member->college, $colleges, true) ?: '';
                                $memberFilter = ['college' => $memberCollege, 'search' => Str::lower($member->name.' '.$member->email)];
                                $existingCoordinator = $member->college ? $coordinatorsByCollege->get($member->college) : null;
                                $replacementCoordinator = $existingCoordinator && $existingCoordinator['id'] !== $member->getKey()
                                    ? $existingCoordinator['name']
                                    : '';
                                $canManageCoordinator = $isCoordinator || (filled($member->college) && ! $isResearchHead);
                            ?>
                            <tr
                                x-show="matchesMember(<?php echo \Illuminate\Support\Js::from($memberFilter)->toHtml() ?>)"
                                class="transition hover:bg-gray-50 dark:hover:bg-slate-800/60"
                                data-member-row
                                data-college="<?php echo e($memberCollege); ?>"
                            >
                                <td class="px-5 py-4 align-middle">
                                    <div class="flex items-center gap-3">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($member->avatar): ?>
                                            <img src="<?php echo e($member->avatar); ?>" alt="" class="h-10 w-10 shrink-0 rounded-xl bg-gray-100 object-cover dark:bg-slate-800">
                                        <?php else: ?>
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-sm font-black uppercase text-red-700 dark:bg-red-950/50 dark:text-red-300"><?php echo e(Str::substr($member->name, 0, 1)); ?></div>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <div class="min-w-0">
                                            <span class="block break-words text-sm font-bold text-gray-900 dark:text-white"><?php echo e($member->name); ?></span>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isResearchHead || $isCoordinator || $isSecretary): ?>
                                                <div class="mt-1.5 flex flex-col items-start gap-1" data-member-roles>
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isResearchHead): ?>
                                                        <span class="rounded-full bg-slate-100 px-2 py-1 text-[9px] font-black uppercase tracking-wide text-slate-700 dark:bg-slate-800 dark:text-slate-200">Research Head</span>
                                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isCoordinator): ?>
                                                        <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-1 text-[9px] font-black uppercase tracking-wide text-red-700 dark:bg-red-950/50 dark:text-red-300" title="Research Coordinator">
                                                            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M10.87 2.5a1 1 0 0 0-1.74 0L7.31 5.72l-3.65.74a1 1 0 0 0-.54 1.67l2.52 2.74-.43 3.7a1 1 0 0 0 1.41 1.03L10 14.05l3.38 1.55a1 1 0 0 0 1.41-1.03l-.43-3.7 2.52-2.74a1 1 0 0 0-.54-1.67l-3.65-.74-1.82-3.22Z" clip-rule="evenodd" /></svg>
                                                            Research Coordinator
                                                        </span>
                                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isSecretary): ?>
                                                        <span class="rounded-full bg-blue-50 px-2 py-1 text-[9px] font-black uppercase tracking-wide text-blue-700 dark:bg-blue-950/50 dark:text-blue-300">Research Office Secretary</span>
                                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                </div>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="break-all px-5 py-4 align-middle text-sm text-gray-600 dark:text-slate-300"><?php echo e($member->email); ?></td>
                                <td x-show="selectedCollege === 'all'" class="px-5 py-4 align-middle text-xs font-semibold leading-5 text-gray-600 dark:text-slate-300">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($member->college)): ?>
                                        <?php echo e($member->college); ?>

                                    <?php else: ?>
                                        <span class="block">Not set</span>
                                        <span class="mt-1.5 inline-flex rounded-full bg-amber-50 px-2 py-1 text-[9px] font-black uppercase tracking-wide text-amber-700 dark:bg-amber-950/50 dark:text-amber-300">College required</span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                                <td class="px-5 py-4 align-middle">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canManageCoordinator): ?>
                                        <button
                                            type="button"
                                            x-on:click="openRoleDialog(<?php echo \Illuminate\Support\Js::from($member->name)->toHtml() ?>, <?php echo \Illuminate\Support\Js::from(route('research_head.faculty-directory.coordinator', $member))->toHtml() ?>, <?php echo \Illuminate\Support\Js::from($isCoordinator)->toHtml() ?>, 'Research Coordinator', <?php echo \Illuminate\Support\Js::from($member->college)->toHtml() ?>, <?php echo \Illuminate\Support\Js::from($replacementCoordinator)->toHtml() ?>)"
                                            aria-label="<?php echo e($isCoordinator ? 'Remove Research Coordinator from ' : 'Assign Research Coordinator to '); ?><?php echo e($member->name); ?>"
                                            class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border px-3 py-2 text-xs font-bold transition <?php echo e($isCoordinator ? 'border-red-200 text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/40' : 'border-gray-200 text-gray-700 hover:border-red-200 hover:bg-red-50 hover:text-red-700 dark:border-slate-700 dark:text-slate-200 dark:hover:border-red-900 dark:hover:bg-red-950/40 dark:hover:text-red-300'); ?>"
                                        >
                                            <?php echo e($isCoordinator ? 'Remove' : 'Assign'); ?>

                                        </button>
                                    <?php else: ?>
                                        <button
                                            type="button"
                                            disabled
                                            title="<?php echo e($isResearchHead ? 'Research Heads cannot be assigned as Research Coordinators.' : 'Set this member\'s college before assigning the Research Coordinator role.'); ?>"
                                            aria-label="Research Coordinator assignment unavailable for <?php echo e($member->name); ?>"
                                            class="inline-flex min-h-11 w-full cursor-not-allowed items-center justify-center rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-xs font-bold text-gray-500 dark:border-slate-800 dark:bg-slate-950/50 dark:text-slate-400"
                                            data-coordinator-ineligible
                                        >
                                            Unavailable
                                        </button>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                                <td class="px-5 py-4 align-middle">
                                    <button
                                        type="button"
                                        x-on:click="openRoleDialog(<?php echo \Illuminate\Support\Js::from($member->name)->toHtml() ?>, <?php echo \Illuminate\Support\Js::from(route('research_head.faculty-directory.secretary', $member))->toHtml() ?>, <?php echo \Illuminate\Support\Js::from($isSecretary)->toHtml() ?>, 'Research Office Secretary')"
                                        aria-label="<?php echo e($isSecretary ? 'Remove Research Office Secretary from ' : 'Assign Research Office Secretary to '); ?><?php echo e($member->name); ?>"
                                        class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border px-3 py-2 text-xs font-bold transition <?php echo e($isSecretary ? 'border-blue-200 text-blue-700 hover:bg-blue-50 dark:border-blue-900 dark:text-blue-300 dark:hover:bg-blue-950/40' : 'border-gray-200 text-gray-700 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700 dark:border-slate-700 dark:text-slate-200 dark:hover:border-blue-900 dark:hover:bg-blue-950/40 dark:hover:text-blue-300'); ?>"
                                    >
                                        <?php echo e($isSecretary ? 'Remove' : 'Assign'); ?>

                                    </button>
                                </td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                        <tr x-show="visibleMembers().length === 0">
                            <td x-bind:colspan="selectedCollege === 'all' ? 5 : 4" class="px-5 py-12 text-center">
                                <p class="text-sm font-bold text-gray-700 dark:text-slate-200">No members found</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">No members match this college and search.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                x-cloak
                x-show="dialogOpen"
                x-trap.inert.noscroll="dialogOpen"
                x-on:keydown.escape.window="dialogOpen = false"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                role="dialog"
                aria-modal="true"
                aria-labelledby="coordinator-dialog-title"
            >
                <div x-on:click="dialogOpen = false" class="absolute inset-0 bg-gray-950/50 backdrop-blur-sm"></div>
                <div x-show="dialogOpen" x-transition class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900">
                    <button type="button" x-on:click="dialogOpen = false" class="absolute right-4 top-4 inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Cancel">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.1a7.5 7.5 0 0 1 15 0" /></svg>
                    </div>
                    <h3 id="coordinator-dialog-title" class="mt-4 pr-8 text-lg font-black text-gray-900 dark:text-white" x-text="(isAssigned ? 'Remove ' : 'Assign ') + roleLabel + '?'"></h3>

                    <p x-show="isAssigned && roleLabel === 'Research Coordinator'" class="mt-2 text-sm leading-6 text-gray-600 dark:text-slate-300">
                        <span class="font-bold" x-text="memberName"></span>
                        will no longer be the Research Coordinator for <span class="font-bold" x-text="memberCollege"></span>.
                    </p>
                    <p x-show="!isAssigned && roleLabel === 'Research Coordinator' && replacementCoordinator === ''" class="mt-2 text-sm leading-6 text-gray-600 dark:text-slate-300">
                        <span class="font-bold" x-text="memberName"></span>
                        will become the Research Coordinator for <span class="font-bold" x-text="memberCollege"></span>.
                    </p>
                    <p x-show="roleLabel === 'Research Office Secretary'" class="mt-2 text-sm leading-6 text-gray-600 dark:text-slate-300">
                        <span class="font-bold" x-text="memberName"></span>
                        <span x-text="isAssigned ? 'will lose access to the Research Office Secretary workspace.' : 'will gain access to the Research Office Secretary workspace.'"></span>
                        Their other roles will stay assigned.
                    </p>
                    <div x-show="!isAssigned && roleLabel === 'Research Coordinator' && replacementCoordinator !== ''" class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                        <span class="font-black">This replaces the current Research Coordinator.</span>
                        Assigning <span class="font-bold" x-text="memberName"></span> will remove the Coordinator role from <span class="font-bold" x-text="replacementCoordinator"></span>, keeping only one Research Coordinator for this college. Research Office Secretary assignments will stay assigned.
                    </div>

                    <form method="POST" x-bind:action="actionUrl" class="mt-5 flex justify-end gap-2">
                        <?php echo csrf_field(); ?>
                        <?php echo method_field('PATCH'); ?>
                        <input type="hidden" name="action" x-bind:value="isAssigned ? 'remove' : 'assign'">
                        <button type="button" x-on:click="dialogOpen = false" class="rounded-xl border border-gray-200 px-5 py-2.5 text-xs font-black text-gray-600 transition hover:bg-gray-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Cancel</button>
                        <button type="submit" class="rounded-xl bg-red-600 px-5 py-2.5 text-xs font-black text-white transition hover:bg-red-700" x-text="(isAssigned ? 'Remove ' : 'Assign ') + roleLabel"></button>
                    </form>
                </div>
            </div>
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
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\research_head\faculty-directory.blade.php ENDPATH**/ ?>