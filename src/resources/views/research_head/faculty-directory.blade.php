<x-app-layout>
    @php
        $collegeDotClasses = [
            'CICS' => 'bg-green-500',
            'CTE' => 'bg-blue-500',
            'CABEIHM' => 'bg-yellow-400',
            'CCJE' => 'bg-black dark:bg-slate-950',
            'CAS' => 'bg-red-500',
            'CHS' => 'border border-gray-300 bg-white dark:border-slate-500',
        ];
    @endphp

    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-black tracking-tight text-gray-900 dark:text-white">Faculty Directory</h2>
            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Browse ATHENA members and assign one Research Coordinator per college.</p>
        </div>
    </x-slot>

    <div
        x-data="{
            selectedCollege: @js($selectedCollege),
            search: @js($search),
            colleges: @js($colleges),
            members: @js($memberFilters),
            dialogOpen: false,
            memberName: '',
            memberCollege: '',
            replacementCoordinator: '',
            actionUrl: '',
            isCoordinator: false,
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
            visibleMembers() {
                const query = this.search.trim().toLowerCase();

                return this.members.filter((member) => {
                    const matchesCollege = this.selectedCollege === 'all' || member.college === this.selectedCollege;
                    const matchesSearch = query === '' || member.search.includes(query);

                    return matchesCollege && matchesSearch;
                });
            },
            openCoordinatorDialog(name, url, coordinator, college, replacement) {
                this.memberName = name;
                this.actionUrl = url;
                this.isCoordinator = coordinator;
                this.memberCollege = college;
                this.replacementCoordinator = replacement;
                this.dialogOpen = true;
            },
        }"
        class="space-y-5"
        data-faculty-directory
        data-initial-college="{{ $selectedCollege }}"
        data-initial-search="{{ $search }}"
    >
        <nav class="overflow-x-auto rounded-2xl border border-gray-200 bg-white p-2 shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-label="Filter members by college">
            <div class="flex min-w-max gap-1" role="tablist" aria-label="College">
                <button
                    type="button"
                    role="tab"
                    x-on:click="setCollege('all')"
                    x-bind:aria-selected="selectedCollege === 'all'"
                    x-bind:class="selectedCollege === 'all' ? 'bg-red-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'"
                    class="rounded-xl px-4 py-2.5 text-xs font-black transition"
                    data-college-tab="all"
                >
                    All
                </button>
                @foreach ($colleges as $acronym => $college)
                    <button
                        type="button"
                        role="tab"
                        x-on:click="setCollege(@js($acronym))"
                        x-bind:aria-selected="selectedCollege === @js($acronym)"
                        x-bind:class="selectedCollege === @js($acronym) ? 'bg-red-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'"
                        class="rounded-xl px-4 py-2.5 text-xs font-black transition"
                        title="{{ $college }}"
                        data-college-tab="{{ $acronym }}"
                    >
                        <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full {{ $collegeDotClasses[$acronym] }}" aria-hidden="true"></span>{{ $acronym }}</span>
                    </button>
                @endforeach
            </div>
        </nav>

        @if (session('status'))
            <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700 dark:border-green-900 dark:bg-green-950/40 dark:text-green-300">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">{{ $errors->first() }}</div>
        @endif

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-4 border-b border-gray-100 px-5 py-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-base font-black text-gray-900 dark:text-white" x-text="selectedCollege === 'all' ? 'All members' : selectedCollege + ' members'"></h3>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-slate-400" x-text="selectedCollege === 'all' ? 'Every registered ATHENA account.' : colleges[selectedCollege]"></p>
                </div>
                <div class="flex flex-col gap-2 sm:items-end">
                    <div class="relative w-full sm:w-72">
                        <label for="faculty-search" class="sr-only">Search faculty</label>
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m2.1-5.4a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" /></svg>
                        <input
                            id="faculty-search"
                            type="search"
                            x-model.debounce.250ms="search"
                            placeholder="Search faculty"
                            autocomplete="off"
                            class="block w-full rounded-xl border-gray-200 py-2.5 pl-9 pr-3 text-sm shadow-sm focus:border-red-500 focus:ring-red-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500"
                            data-faculty-search
                        >
                    </div>
                    <span class="text-xs font-bold text-gray-500 dark:text-slate-400" x-text="visibleMembers().length + (visibleMembers().length === 1 ? ' member' : ' members')"></span>
                </div>
            </div>

            <div class="flex gap-3 border-b border-red-100 bg-red-50/70 px-5 py-3 text-xs leading-5 text-red-800 dark:border-red-950 dark:bg-red-950/30 dark:text-red-200">
                <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-1.5a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12V16.5Z" /></svg>
                <p><span class="font-black">Coordinator rules:</span> a member must have a college, and each college can have only one Research Coordinator. Assigning another member replaces the current coordinator for that college.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-slate-800">
                    <thead class="bg-gray-50 dark:bg-slate-950/50">
                        <tr>
                            <th scope="col" class="px-5 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-400">Member</th>
                            <th scope="col" class="px-5 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-400">Email</th>
                            <th x-show="selectedCollege === 'all'" scope="col" class="px-5 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-400">College</th>
                            <th scope="col" class="px-5 py-3 text-right text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-400">Coordinator assignment</th>
                        </tr>
                    </thead>
                    <tbody x-cloak class="divide-y divide-gray-100 dark:divide-slate-800">
                        @foreach ($members as $member)
                            @php
                                $isCoordinator = $member->hasRole('research_coordinator');
                                $memberCollege = array_search($member->college, $colleges, true) ?: '';
                                $existingCoordinator = $member->college ? $coordinatorsByCollege->get($member->college) : null;
                                $replacementCoordinator = $existingCoordinator && $existingCoordinator['id'] !== $member->getKey()
                                    ? $existingCoordinator['name']
                                    : '';
                                $canManageCoordinator = $isCoordinator || filled($member->college);
                            @endphp
                            <tr
                                x-show="visibleMembers().some((member) => member.id === @js($member->getKey()))"
                                class="transition hover:bg-gray-50 dark:hover:bg-slate-800/60"
                                data-member-row
                                data-college="{{ $memberCollege }}"
                            >
                                <td class="whitespace-nowrap px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        @if ($member->avatar)
                                            <img src="{{ $member->avatar }}" alt="" class="h-10 w-10 rounded-xl bg-gray-100 object-cover dark:bg-slate-800">
                                        @else
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-sm font-black uppercase text-red-700 dark:bg-red-950/50 dark:text-red-300">{{ Str::substr($member->name, 0, 1) }}</div>
                                        @endif
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $member->name }}</span>
                                                @if ($isCoordinator)
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-1 text-[9px] font-black uppercase tracking-wide text-red-700 dark:bg-red-950/50 dark:text-red-300" title="Research Coordinator">
                                                        <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M10.87 2.5a1 1 0 0 0-1.74 0L7.31 5.72l-3.65.74a1 1 0 0 0-.54 1.67l2.52 2.74-.43 3.7a1 1 0 0 0 1.41 1.03L10 14.05l3.38 1.55a1 1 0 0 0 1.41-1.03l-.43-3.7 2.52-2.74a1 1 0 0 0-.54-1.67l-3.65-.74-1.82-3.22Z" clip-rule="evenodd" /></svg>
                                                        Coordinator
                                                    </span>
                                                @elseif (! $member->college)
                                                    <span class="rounded-full bg-amber-50 px-2 py-1 text-[9px] font-black uppercase tracking-wide text-amber-700 dark:bg-amber-950/50 dark:text-amber-300">College required</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-slate-300">{{ $member->email }}</td>
                                <td x-show="selectedCollege === 'all'" class="max-w-sm px-5 py-4 text-xs font-semibold leading-5 text-gray-600 dark:text-slate-300">{{ $member->college ?: 'Not set' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    @if ($canManageCoordinator)
                                        <button
                                            type="button"
                                            x-on:click="openCoordinatorDialog(@js($member->name), @js(route('research_head.faculty-directory.coordinator', $member)), @js($isCoordinator), @js($member->college), @js($replacementCoordinator))"
                                            class="inline-flex items-center justify-center rounded-xl border px-3 py-2 text-[10px] font-black uppercase tracking-wider transition {{ $isCoordinator ? 'border-red-200 text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/40' : 'border-gray-200 text-gray-700 hover:border-red-200 hover:bg-red-50 hover:text-red-700 dark:border-slate-700 dark:text-slate-200 dark:hover:border-red-900 dark:hover:bg-red-950/40 dark:hover:text-red-300' }}"
                                        >
                                            {{ $isCoordinator ? 'Remove coordinator' : 'Assign coordinator' }}
                                        </button>
                                    @else
                                        <button
                                            type="button"
                                            disabled
                                            title="Set this member's college before assigning the Research Coordinator role."
                                            class="inline-flex cursor-not-allowed items-center justify-center rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-gray-400 dark:border-slate-800 dark:bg-slate-950/50 dark:text-slate-600"
                                            data-coordinator-ineligible
                                        >
                                            College required
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach

                        <tr x-show="visibleMembers().length === 0">
                            <td x-bind:colspan="selectedCollege === 'all' ? 4 : 3" class="px-5 py-12 text-center">
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
                    <h3 id="coordinator-dialog-title" class="mt-4 pr-8 text-lg font-black text-gray-900 dark:text-white" x-text="isCoordinator ? 'Remove Research Coordinator?' : 'Assign Research Coordinator?'"></h3>

                    <p x-show="isCoordinator" class="mt-2 text-sm leading-6 text-gray-600 dark:text-slate-300">
                        <span class="font-bold" x-text="memberName"></span>
                        will no longer be the Research Coordinator for <span class="font-bold" x-text="memberCollege"></span>.
                    </p>
                    <p x-show="!isCoordinator && replacementCoordinator === ''" class="mt-2 text-sm leading-6 text-gray-600 dark:text-slate-300">
                        <span class="font-bold" x-text="memberName"></span>
                        will become the Research Coordinator for <span class="font-bold" x-text="memberCollege"></span>.
                    </p>
                    <div x-show="!isCoordinator && replacementCoordinator !== ''" class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                        <span class="font-black">This replaces the current coordinator.</span>
                        Assigning <span class="font-bold" x-text="memberName"></span> will remove the role from <span class="font-bold" x-text="replacementCoordinator"></span>, keeping only one coordinator for this college.
                    </div>

                    <form method="POST" x-bind:action="actionUrl" class="mt-5 flex justify-end gap-2">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="action" x-bind:value="isCoordinator ? 'remove' : 'assign'">
                        <button type="button" x-on:click="dialogOpen = false" class="rounded-xl border border-gray-200 px-5 py-2.5 text-xs font-black text-gray-600 transition hover:bg-gray-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Cancel</button>
                        <button type="submit" class="rounded-xl bg-red-600 px-5 py-2.5 text-xs font-black text-white transition hover:bg-red-700" x-text="isCoordinator ? 'Remove coordinator' : 'Assign coordinator'"></button>
                    </form>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
