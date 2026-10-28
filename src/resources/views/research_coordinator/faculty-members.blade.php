<x-app-layout>
    <x-slot name="header"><x-page-header variant="hero" eyebrow="Research office / Directory" title="Faculty Members" :subtitle="'Faculty members assigned to '.($coordinator->college ?: 'your college').'.'"><x-slot name="actions"><x-back-link href="{{ route('research_coordinator.dashboard') }}">Back to dashboard</x-back-link></x-slot></x-page-header></x-slot>
    <div class="secretary-workspace" data-coordinator-workspace="members">
        <section class="secretary-directory-heading" aria-label="College directory summary"><div><p class="secretary-eyebrow">Your college community</p><h2>The people behind the research.</h2><p>Find faculty members and their university contact details.</p></div><div class="secretary-directory-total"><strong>{{ $memberCount }}</strong><span>college<br>{{ str('member')->plural($memberCount) }}</span></div></section>
        <section class="secretary-panel" aria-label="Faculty directory">
            <form method="GET" action="{{ route('research_coordinator.members.index') }}" class="secretary-directory-filters" role="search">
                <div class="secretary-search"><label for="coordinator-member-search" class="sr-only">Search faculty members</label><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/></svg><input id="coordinator-member-search" type="search" name="q" value="{{ request('q') }}" placeholder="Search by name or university email" maxlength="200"></div>
                <button type="submit" class="secretary-button-primary">Find members</button>
                @if (request()->filled('q'))<a href="{{ route('research_coordinator.members.index') }}" class="secretary-button-secondary">Clear search</a>@endif
            </form>
            @if (! $coordinator->college)<p role="status" class="border-b border-amber-200 bg-amber-50 p-5 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">Set your college in your account profile to view your faculty members.</p>@endif
            <div class="coordinator-member-list">
                @forelse ($members as $member)
                    <article class="coordinator-member-row">
                        <x-user-avatar :user="$member" class="h-11 w-11 rounded-lg bg-brand text-sm" />
                        <div class="min-w-0"><h3>{{ $member->name }}</h3><p class="secretary-muted">{{ $coordinator->college }}</p></div>
                        <p class="coordinator-member-email">{{ $member->email }}</p>
                    </article>
                @empty
                    <div class="secretary-empty"><span class="secretary-empty-icon" aria-hidden="true"><x-report-library-icon name="college" class="h-6 w-6" /></span><h3>{{ request()->filled('q') ? 'No members match your search.' : 'No faculty members found yet' }}</h3><p>{{ request()->filled('q') ? 'Try another name or university email.' : 'Your college currently has '.$memberCount.' registered '.Str::plural('member', $memberCount).'.' }}</p></div>
                @endforelse
            </div>
            <footer class="secretary-directory-footer">{{ $members->total() }} {{ str('member')->plural($members->total()) }}{{ request()->filled('q') ? ' matching your search' : ' in your college' }}<span>{{ $coordinator->college ?: 'No college selected' }}</span></footer>
            @if ($members->hasPages())<div class="border-t border-slate-100 p-5 dark:border-slate-800">{{ $members->links() }}</div>@endif
        </section>
    </div>
</x-app-layout>
