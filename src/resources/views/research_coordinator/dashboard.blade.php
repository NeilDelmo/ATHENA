<x-app-layout>
    <x-slot name="header">
        <x-page-header variant="hero" eyebrow="Research office" title="Coordinator dashboard" :subtitle="($coordinator->college ?: 'Your college').' · '.now()->format('l, F j, Y')">
            <x-slot name="actions"><a href="{{ route('research_coordinator.proposals.index') }}" class="secretary-button-primary">Open LREC queue <span aria-hidden="true">→</span></a></x-slot>
        </x-page-header>
    </x-slot>
    @php($attentionTotal = $lrecReviewCount)
    <div class="secretary-workspace" data-coordinator-workspace="dashboard" data-dashboard-layout="office-overview">
        <section class="secretary-overview" aria-label="College workload">
            <div class="secretary-overview-main">
                <div class="secretary-overview-label"><span class="secretary-status-dot" aria-hidden="true"></span> Your college at a glance</div>
                <div class="secretary-attention-number">{{ $attentionTotal }}<span>items<br>need attention</span></div>
                <p>Record committee feedback, follow up on proposals, and keep your college’s research moving.</p>
                <a href="{{ route('research_coordinator.proposals.index', ['status' => 'review']) }}" class="secretary-button-primary">{{ $lrecReviewCount }} ready for committee comments <span aria-hidden="true">→</span></a>
            </div>
            <dl class="secretary-metrics">
                @foreach ([['LREC proposals', $lrecProposals->total(), 'Committee reviews and follow-up'], ['Ready for comments', $lrecReviewCount, 'Committee reviews needing your input'], ['College members', $memberCount, 'Faculty in your college directory']] as [$label, $count, $description])
                    <div><dt>{{ $label }}</dt><dd>{{ $count }}</dd><p>{{ $description }}</p></div>
                @endforeach
            </dl>
        </section>
        <div class="secretary-dashboard-columns" data-dashboard-columns>
            <x-coordinator-lrec-queue :proposals="$lrecProposals" :coordinator="$coordinator" compact />
            <aside class="secretary-office-links" aria-label="College work">
                <a href="{{ route('research_coordinator.reports.index') }}" class="secretary-portfolio-link"><span><span class="secretary-eyebrow">College accomplishment reporting</span><strong>Quarterly report</strong><small>Prepare activities, attach evidence, and submit your college report.</small></span><b>Q{{ now()->quarter }}</b></a>
            </aside>
        </div>
        <div class="grid gap-5 lg:grid-cols-2">
            <a href="{{ route('research_coordinator.targets.index') }}" class="secretary-target-link"><span class="secretary-file-mark" aria-hidden="true"><x-report-library-icon name="calendar" class="h-5 w-5" /></span><span><strong>Research targets</strong><small>Track your college’s annual goals and accomplishments.</small></span><span aria-hidden="true">→</span></a>
            <a href="{{ route('research_coordinator.members.index') }}" class="secretary-target-link"><span class="secretary-file-mark" aria-hidden="true"><x-report-library-icon name="college" class="h-5 w-5" /></span><span><strong>Faculty directory</strong><small>{{ $memberCount }} {{ str('member')->plural($memberCount) }} in your college. Find a colleague’s contact details.</small></span><span aria-hidden="true">→</span></a>
        </div>
    </div>
</x-app-layout>
