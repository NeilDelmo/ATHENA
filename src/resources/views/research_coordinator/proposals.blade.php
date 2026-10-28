<x-app-layout>
    <x-slot name="header"><x-page-header variant="hero" eyebrow="Research office / Committee review" title="LREC proposals" subtitle="Record committee feedback and follow proposals through LREC review."><x-slot name="actions"><x-back-link href="{{ route('research_coordinator.dashboard') }}">Back to dashboard</x-back-link></x-slot></x-page-header></x-slot>
    <div class="secretary-workspace" data-coordinator-workspace="proposals">
        <section class="secretary-panel" aria-label="Filter LREC proposals">
            <form method="GET" action="{{ route('research_coordinator.proposals.index') }}" class="secretary-directory-filters" role="search">
                <div class="secretary-search"><label for="coordinator-proposal-search" class="sr-only">Search LREC proposals</label><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/></svg><input id="coordinator-proposal-search" type="search" name="q" value="{{ request('q') }}" placeholder="Search proposals or project leaders" maxlength="200"></div>
                <div><label for="coordinator-review-status" class="sr-only">Review status</label><select id="coordinator-review-status" name="status"><option value="">All review stages</option>@foreach (['review' => 'Ready for comments', 'queued' => 'Queued for LREC', 'revision' => 'Faculty revisions'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></div>
                <button type="submit" class="secretary-button-primary">Find proposals</button>
                @if (request()->filled('q') || request()->filled('status'))<a href="{{ route('research_coordinator.proposals.index') }}" class="secretary-button-secondary">Clear filters</a>@endif
            </form>
        </section>
        <x-coordinator-lrec-queue :proposals="$lrecProposals" :coordinator="$coordinator" />
    </div>
</x-app-layout>
