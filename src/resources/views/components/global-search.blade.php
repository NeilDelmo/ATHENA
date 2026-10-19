<div {{ $attributes->merge(['class' => 'shrink-0']) }} data-global-search>
    <form method="GET" action="{{ route('search.index') }}" role="search" aria-label="Quick search" class="hidden items-center rounded-xl border border-white/30 bg-white/10 lg:flex">
        <label for="header-search-query" class="sr-only">Search ATHENA</label>
        <input id="header-search-query" name="q" type="search" maxlength="120" placeholder="Search ATHENA" value="{{ request()->routeIs('search.index') && is_string(request('q')) ? request('q') : '' }}" class="h-11 w-44 rounded-l-xl border-0 bg-transparent text-sm text-white placeholder:text-red-100 focus:ring-2 focus:ring-inset focus:ring-white xl:w-56" />
        <button type="submit" aria-label="Submit quick search" class="inline-flex h-11 w-11 items-center justify-center rounded-r-xl text-white hover:bg-white/15 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" /><path stroke-linecap="round" d="m16 16 4 4" /></svg>
        </button>
    </form>
    <a href="{{ route('search.index') }}" aria-label="Search ATHENA" title="Search ATHENA" class="inline-flex h-11 w-11 items-center justify-center rounded-xl text-white hover:bg-white/15 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white lg:hidden">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" /><path stroke-linecap="round" d="m16 16 4 4" /></svg>
    </a>
</div>
