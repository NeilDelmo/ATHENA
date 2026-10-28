@props(['proposals', 'coordinator', 'compact' => false])
<section id="office-lrec-proposals" data-coordinator-lrec-queue class="secretary-panel" aria-labelledby="office-lrec-heading">
    <header class="secretary-section-heading">
        <div><p class="secretary-eyebrow">{{ $compact ? 'Your committee worklist' : 'College review queue' }}</p><h2 id="office-lrec-heading">LREC proposals</h2></div>
        @if ($compact)<a href="{{ route('research_coordinator.proposals.index') }}" class="secretary-button-secondary">View all {{ $proposals->total() }} <span aria-hidden="true">→</span></a>@else<span class="secretary-count">{{ $proposals->total() }} in your college</span>@endif
    </header>
    @if (! $coordinator->college)
        <div class="secretary-empty"><h3>Your account needs an assigned college</h3><p>Set your college in your account profile to see its LREC proposals.</p></div>
    @else
        <div data-coordinator-priority-list>
            @forelse ($compact ? $proposals->take(4) : $proposals as $proposal)
                @php
                    $waitingSince = $proposal->status_started_at;
                    $waitingDays = $waitingSince ? (int) max(0, $waitingSince->diffInDays(now(), false)) : null;
                    $actionable = $proposal->status === \App\Models\TopicProposal::STATUS_LREC_REVIEW;
                @endphp
                <article class="secretary-report-row">
                    <span class="secretary-file-mark" aria-hidden="true"><x-report-library-icon name="{{ $actionable ? 'publication' : 'history' }}" class="h-5 w-5" /></span>
                    <div class="min-w-0 flex-1">
                        <div class="secretary-row-meta"><span>{{ $proposal->workflowStatusLabel($proposal->latestVersion) }}</span><span>Version {{ $proposal->latestVersion?->version_number ?? '—' }}</span></div>
                        <h3>{{ $proposal->title }}</h3>
                        <p class="secretary-muted">{{ $proposal->user?->name }}</p>
                        <p class="secretary-waiting">{{ $waitingDays !== null ? $waitingDays.' '.str('day')->plural($waitingDays).' in this stage' : 'Stage start date unavailable' }}</p>
                    </div>
                    <div class="secretary-row-actions"><a href="{{ route('topics.show', $proposal) }}#proposal-review" class="{{ $actionable ? 'secretary-button-primary' : 'secretary-button-secondary' }}">{{ $actionable ? 'Record comments' : 'View review status' }} <span aria-hidden="true">→</span></a></div>
                </article>
            @empty
                <div class="secretary-empty"><span class="secretary-empty-icon" aria-hidden="true">✓</span><h3>{{ request()->filled('q') || request()->filled('status') ? 'No proposals match your filters.' : 'No proposals awaiting LREC follow-up.' }}</h3><p>{{ request()->filled('q') || request()->filled('status') ? 'Try another title, project leader, or review status.' : 'Proposals from your college appear here when they reach LREC.' }}</p></div>
            @endforelse
        </div>
        @if (! $compact && $proposals->hasPages())<footer class="border-t border-slate-100 p-5 dark:border-slate-800">{{ $proposals->links() }}</footer>@endif
    @endif
</section>
