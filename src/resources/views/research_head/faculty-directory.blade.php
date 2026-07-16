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
            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Browse ATHENA members and filter them by college.</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        <nav class="overflow-x-auto rounded-2xl border border-gray-200 bg-white p-2 shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-label="Filter members by college">
            <div class="flex min-w-max gap-1">
                <a href="{{ route('research_head.faculty-directory.index', array_filter(['search' => $search])) }}"
                   @if ($selectedCollege === 'all') aria-current="page" @endif
                   class="rounded-xl px-4 py-2.5 text-xs font-black transition {{ $selectedCollege === 'all' ? 'bg-red-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                    All
                </a>
                @foreach ($colleges as $acronym => $college)
                    <a href="{{ route('research_head.faculty-directory.index', array_filter(['college' => $acronym, 'search' => $search])) }}"
                       title="{{ $college }}"
                       @if ($selectedCollege === $acronym) aria-current="page" @endif
                       class="rounded-xl px-4 py-2.5 text-xs font-black transition {{ $selectedCollege === $acronym ? 'bg-red-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                        <span class="inline-flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full {{ $collegeDotClasses[$acronym] }}" aria-hidden="true"></span>{{ $acronym }}</span>
                    </a>
                @endforeach
            </div>
        </nav>

        @if (session('status'))
            <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700 dark:border-green-900 dark:bg-green-950/40 dark:text-green-300">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">{{ $errors->first() }}</div>
        @endif

        <section
            x-data="{ dialogOpen: false, memberName: '', actionUrl: '', isCoordinator: false }"
            class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
        >
            <div class="flex flex-col gap-4 border-b border-gray-100 px-5 py-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-base font-black text-gray-900 dark:text-white">{{ $selectedCollege === 'all' ? 'All members' : $selectedCollege.' members' }}</h3>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-slate-400">
                        {{ $selectedCollege === 'all' ? 'Every registered ATHENA account.' : $colleges[$selectedCollege] }}
                    </p>
                </div>
                <div class="flex flex-col gap-2 sm:items-end">
                    <form x-ref="searchForm" method="GET" action="{{ route('research_head.faculty-directory.index') }}" class="relative w-full sm:w-72">
                        @if ($selectedCollege !== 'all')
                            <input type="hidden" name="college" value="{{ $selectedCollege }}">
                        @endif
                        <label for="faculty-search" class="sr-only">Search faculty</label>
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m2.1-5.4a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" /></svg>
                        <input id="faculty-search" name="search" type="search" value="{{ $search }}" placeholder="Search faculty" autocomplete="off"
                               x-on:input.debounce.500ms="$refs.searchForm.requestSubmit()"
                               class="block w-full rounded-xl border-gray-200 py-2.5 pl-9 pr-3 text-sm shadow-sm focus:border-red-500 focus:ring-red-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder:text-slate-500">
                    </form>
                    <span class="text-xs font-bold text-gray-500 dark:text-slate-400">{{ $members->total() }} {{ Str::plural('member', $members->total()) }}</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-slate-800">
                    <thead class="bg-gray-50 dark:bg-slate-950/50">
                        <tr>
                            <th scope="col" class="px-5 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-400">Member</th>
                            <th scope="col" class="px-5 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-400">Email</th>
                            @if ($selectedCollege === 'all')
                                <th scope="col" class="px-5 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-400">College</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                        @forelse ($members as $member)
                            @php($isCoordinator = $member->hasRole('research_coordinator'))
                            <tr
                                tabindex="0"
                                role="button"
                                x-on:click="memberName = @js($member->name); actionUrl = @js(route('research_head.faculty-directory.coordinator', $member)); isCoordinator = @js($isCoordinator); dialogOpen = true"
                                x-on:keydown.enter.prevent="memberName = @js($member->name); actionUrl = @js(route('research_head.faculty-directory.coordinator', $member)); isCoordinator = @js($isCoordinator); dialogOpen = true"
                                class="cursor-pointer transition hover:bg-gray-50 focus:bg-red-50 focus:outline-none dark:hover:bg-slate-800/60 dark:focus:bg-red-950/30"
                            >
                                <td class="whitespace-nowrap px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        @if ($member->avatar)
                                            <img src="{{ $member->avatar }}" alt="" class="h-10 w-10 rounded-xl bg-gray-100 object-cover dark:bg-slate-800">
                                        @else
                                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-sm font-black uppercase text-red-700 dark:bg-red-950/50 dark:text-red-300">{{ Str::substr($member->name, 0, 1) }}</div>
                                        @endif
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $member->name }}</span>
                                            @if ($isCoordinator)
                                                <span class="inline-flex items-center gap-1 rounded-full bg-purple-50 px-2 py-1 text-[9px] font-black uppercase tracking-wide text-purple-700 dark:bg-purple-950/50 dark:text-purple-300" title="Research Coordinator">
                                                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M10.87 2.5a1 1 0 0 0-1.74 0L7.31 5.72l-3.65.74a1 1 0 0 0-.54 1.67l2.52 2.74-.43 3.7a1 1 0 0 0 1.41 1.03L10 14.05l3.38 1.55a1 1 0 0 0 1.41-1.03l-.43-3.7 2.52-2.74a1 1 0 0 0-.54-1.67l-3.65-.74-1.82-3.22Z" clip-rule="evenodd" /></svg>
                                                    Coordinator
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-slate-300">{{ $member->email }}</td>
                                @if ($selectedCollege === 'all')
                                    <td class="max-w-sm px-5 py-4 text-xs font-semibold leading-5 text-gray-600 dark:text-slate-300">{{ $member->college ?: 'Not set' }}</td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $selectedCollege === 'all' ? 3 : 2 }}" class="px-5 py-12 text-center">
                                    <p class="text-sm font-bold text-gray-700 dark:text-slate-200">No members found</p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">No users have selected this college yet.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($members->hasPages())
                <div class="border-t border-gray-100 px-5 py-4 dark:border-slate-800">{{ $members->links() }}</div>
            @endif

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
                <div x-show="dialogOpen" x-transition class="relative w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900">
                    <button type="button" x-on:click="dialogOpen = false" class="absolute right-4 top-4 inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Cancel">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-purple-50 text-purple-700 dark:bg-purple-950/50 dark:text-purple-300">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.1a7.5 7.5 0 0 1 15 0" /></svg>
                    </div>
                    <h3 id="coordinator-dialog-title" class="mt-4 pr-8 text-lg font-black text-gray-900 dark:text-white" x-text="isCoordinator ? 'Remove Research Coordinator?' : 'Set as Research Coordinator?' "></h3>
                    <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-slate-300">
                        <span x-text="memberName" class="font-bold"></span>
                        <span x-text="isCoordinator ? ' will have the Research Coordinator role removed.' : ' will be assigned the Research Coordinator role.'"></span>
                    </p>
                    <form method="POST" x-bind:action="actionUrl" class="mt-5 flex justify-end">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="action" x-bind:value="isCoordinator ? 'remove' : 'assign'">
                        <button type="submit" class="rounded-xl px-5 py-2.5 text-xs font-black text-white transition" x-bind:class="isCoordinator ? 'bg-red-600 hover:bg-red-700' : 'bg-purple-700 hover:bg-purple-800'" x-text="isCoordinator ? 'Remove' : 'Yes'"></button>
                    </form>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
