<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Search ATHENA" subtitle="Find projects, proposal titles, reports, filenames, and research manual editions in your current workspace." />
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-6" data-record-search>
        <section class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-6 dark:border-slate-700 dark:bg-slate-900" aria-label="Search accessible records">
            <p class="mb-4 text-sm text-slate-600 dark:text-slate-300">Searching in <strong class="text-red-700 dark:text-red-300">{{ auth()->user()->activeWorkspaceLabel() }}</strong>. Results follow your access in this workspace.</p>
            <form method="GET" action="{{ route('search.index') }}" role="search" aria-label="Search accessible records" class="flex flex-col gap-4 sm:flex-row sm:items-end">
                <div class="min-w-0 flex-1">
                    <label for="record-search-query" class="mb-1.5 block text-sm font-semibold text-slate-800 dark:text-slate-100">Search words</label>
                    <input id="record-search-query" type="search" name="q" value="{{ $query }}" maxlength="120" placeholder="Project title, report, filename, or edition" aria-describedby="record-search-help" class="block min-h-11 w-full rounded-lg border-slate-300 text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100" />
                    <x-input-error :messages="$errors->get('q')" class="mt-2" />
                </div>
                <div class="sm:w-48">
                    <label for="record-search-category" class="mb-1.5 block text-sm font-semibold text-slate-800 dark:text-slate-100">Look in</label>
                    <select id="record-search-category" name="category" class="block min-h-11 w-full rounded-lg border-slate-300 text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        @foreach ($categories as $key => $label)
                            <option value="{{ $key }}" @selected($category === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-red-700 px-5 py-2 text-sm font-bold text-white hover:bg-red-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600">Search</button>
            </form>
            <p id="record-search-help" class="mt-3 text-xs leading-5 text-slate-500 dark:text-slate-400">Searches saved titles, metadata, and filenames. To search inside a manual’s PDF, open the edition and use its reader search.</p>
        </section>

        @if ($results === null)
            <div class="rounded-xl border border-dashed border-slate-300 px-5 py-12 text-center dark:border-slate-700">
                <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">What are you looking for?</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Enter a project title, report reference, filename, or manual edition above.</p>
            </div>
        @else
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="min-w-0 break-words text-sm font-semibold text-slate-700 dark:text-slate-200">{{ number_format($results->total()) }} {{ str('result')->plural($results->total()) }} for “{{ $query }}”</h2>
                <a href="{{ route('search.index') }}" class="inline-flex min-h-11 items-center rounded-lg px-2 text-sm font-semibold text-red-700 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-600 dark:text-red-300">Clear search</a>
            </div>
            @if ($results->isEmpty())
                <div class="rounded-xl border border-slate-200 bg-white px-5 py-12 text-center dark:border-slate-700 dark:bg-slate-900">
                    <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">No matching records here</h3>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Try a shorter title or a different category. If you use several workspaces, switch workspace to search its records.</p>
                </div>
            @else
                <ol class="divide-y divide-slate-200 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:divide-slate-700 dark:border-slate-700 dark:bg-slate-900" aria-label="Search results">
                    @foreach ($results as $result)
                        <li>
                            <a href="{{ $result['url'] }}" class="group block min-w-0 p-4 hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-red-600 sm:p-5 dark:hover:bg-red-950/20" data-search-result>
                                <span class="text-xs font-bold uppercase tracking-wide text-red-700 dark:text-red-300">{{ $result['type'] }}</span>
                                <h3 class="mt-1 break-words text-base font-bold text-slate-900 group-hover:text-red-800 dark:text-slate-100 dark:group-hover:text-red-200">{{ $result['title'] }}</h3>
                                <p class="mt-1 break-words text-sm text-slate-700 dark:text-slate-300">{{ $result['context'] }}</p>
                                <p class="mt-2 break-all text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $result['location'] }}</p>
                                @if ($result['edition'])
                                    <p class="mt-1 text-xs font-medium text-slate-600 dark:text-slate-300">{{ $result['edition'] }}</p>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ol>
            @endif
            {{ $results->links() }}
        @endif
    </div>
</x-app-layout>
