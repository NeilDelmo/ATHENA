@props(['proposals', 'coordinator', 'compact' => false])
<section id="office-lrec-proposals" data-coordinator-lrec-queue class="{{ $compact ? 'dashboard-panel scroll-mt-40' : 'secretary-panel' }}" aria-labelledby="office-lrec-heading">
    <header class="{{ $compact ? 'dashboard-panel-heading' : 'secretary-section-heading' }}">
        <div>@unless ($compact)<p class="secretary-eyebrow">College review queue</p>@endunless<h2 id="office-lrec-heading" @class(['dashboard-section-title' => $compact])>LREC proposals</h2></div>
        @if ($compact)<a href="{{ route('research_coordinator.proposals.index') }}" class="secondary-action">View all {{ $proposals->total() }} <span aria-hidden="true">→</span></a>@else<span class="secretary-count">{{ $proposals->total() }} in your college</span>@endif
    </header>
    @if (! $coordinator->college)
        <div class="{{ $compact ? 'dashboard-empty' : 'secretary-empty' }}"><h3 @class(['break-words font-semibold' => $compact])>Your account needs an assigned college</h3><p>Set your college in your account profile to see its LREC proposals.</p></div>
    @else
        <div data-coordinator-priority-list>
            @forelse ($compact ? $proposals->take(4) : $proposals as $proposal)
                @php
                    $waitingSince = $proposal->status_started_at;
                    $waitingDays = $waitingSince ? (int) max(0, $waitingSince->diffInDays(now(), false)) : null;
                    $actionable = $proposal->status === \App\Models\TopicProposal::STATUS_LREC_REVIEW;
                @endphp
                <article class="{{ $compact ? 'dashboard-queue-row' : 'secretary-report-row' }}">
                    <span class="{{ $compact ? 'dashboard-icon' : 'secretary-file-mark' }}" aria-hidden="true"><x-report-library-icon name="{{ $actionable ? 'publication' : 'history' }}" class="h-5 w-5" /></span>
                    <div class="min-w-0 flex-1">
                        <div class="{{ $compact ? 'mb-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm rh-muted' : 'secretary-row-meta' }}"><span>{{ $proposal->workflowStatusLabel($proposal->latestVersion) }}</span><span>Version {{ $proposal->latestVersion?->version_number ?? '—' }}</span></div>
                        <h3 @class(['break-words font-semibold' => $compact])>{{ $proposal->title }}</h3>
                        <p class="{{ $compact ? 'mt-1 text-sm rh-muted' : 'secretary-muted' }}">{{ $proposal->user?->name }}</p>
                        <p class="{{ $compact ? 'mt-1 text-sm font-medium text-brand dark:text-rose-300' : 'secretary-waiting' }}">{{ $waitingDays !== null ? $waitingDays.' '.str('day')->plural($waitingDays).' in this stage' : 'Stage start date unavailable' }}</p>
                    </div>
                    <div class="{{ $compact ? 'flex flex-wrap gap-2' : 'secretary-row-actions' }}"><a href="{{ route('topics.show', $proposal) }}#proposal-review" @class(['dashboard-action' => $compact && $actionable, 'secondary-action' => $compact && ! $actionable, 'secretary-button-primary' => ! $compact && $actionable, 'secretary-button-secondary' => ! $compact && ! $actionable])>{{ $actionable ? 'Record comments' : 'View review status' }} <span aria-hidden="true">→</span></a></div>
                </article>
            @empty
                <div class="{{ $compact ? 'dashboard-empty' : 'secretary-empty' }}">@unless ($compact)<span class="secretary-empty-icon" aria-hidden="true">✓</span>@endunless<h3 @class(['break-words font-semibold' => $compact])>{{ request()->filled('q') || request()->filled('status') ? 'No proposals match your filters.' : 'No proposals awaiting LREC follow-up.' }}</h3><p>{{ request()->filled('q') || request()->filled('status') ? 'Try another title, project leader, or review status.' : 'Proposals from your college appear here when they reach LREC.' }}</p></div>
            @endforelse
        </div>
        @if (! $compact && $proposals->hasPages())<footer class="border-t border-slate-100 p-5 dark:border-slate-800">{{ $proposals->links() }}</footer>@endif
    @endif
</section>
