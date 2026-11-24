@props(['reports', 'compact' => false])
<section @if ($compact) id="secretary-report-reviews" @endif class="{{ $compact ? 'dashboard-panel scroll-mt-40' : 'secretary-panel' }}" data-secretary-report-queue aria-labelledby="secretary-review-heading" @unless ($compact) data-automatic-assessment-preview x-data="proposalAssessmentPreview({ canPrint: true })" @resize.window.debounce.150ms="resizeProposalPaperPreview()" @endunless>
    <div @unless ($compact) :inert="previewFullscreen" @endunless>
        <header class="{{ $compact ? 'dashboard-panel-heading' : 'secretary-section-heading' }}">
            <div><h2 id="secretary-review-heading" @class(['dashboard-section-title' => $compact, 'flex items-center gap-2.5' => ! $compact])>@unless ($compact)<x-report-library-icon name="list" class="h-5 w-5" />@endunless {{ $compact ? 'Report reviews' : 'Submitted reports' }}</h2></div>
            @if ($compact)<a href="{{ route('research_secretary.reports.index') }}" class="secondary-action">View all {{ $reports->count() }} <x-report-library-icon name="open" class="h-4 w-4" /></a>@else<span class="secretary-count">{{ $reports->count() }} waiting</span>@endif
        </header>
        <div class="secretary-report-list" data-secretary-priority-list>
            @forelse ($compact ? $reports->take(4) : $reports as $report)
                @php
                    $monitoring = $report instanceof \App\Models\ProjectProgressReport;
                    $previewUrl = route($monitoring ? 'project-progress.monitoring-tool.view' : 'project-narrative-reports.view', $report);
                    $reportUrl = ! $monitoring && $report->report_type === 'terminal'
                        ? route('project-narrative-reports.show', $report)
                        : route('topics.show', $report->topic).'#'.($monitoring ? 'monitoring-tool-' : 'narrative-report-').$report->id;
                @endphp
                <article class="{{ $compact ? 'dashboard-queue-row' : 'secretary-report-row' }}" data-secretary-report-kind="{{ $monitoring ? 'monitoring' : $report->report_type }}">
                    <span class="{{ $compact ? 'dashboard-icon' : 'secretary-file-mark' }}" aria-hidden="true"><x-report-library-icon name="{{ $monitoring ? 'list' : 'publication' }}" class="h-5 w-5" /></span>
                    <div class="min-w-0 flex-1">
                        <div class="{{ $compact ? 'mb-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm rh-muted' : 'secretary-row-meta' }}"><span>{{ $monitoring ? 'Monitoring Tool' : ucfirst($report->report_label) }}</span><span>{{ $report->reporting_period_label ?: ($report->reporting_date ?? $report->submitted_at)?->format('M j, Y') }}</span></div>
                        <h3 @class(['break-words font-semibold' => $compact])>{{ $report->topic->title }}</h3>
                        <p class="{{ $compact ? 'mt-1 text-sm rh-muted' : 'secretary-muted' }}">{{ $report->submitter->name }} <span aria-hidden="true">·</span> Version {{ $report->version_number }}</p>
                        @if ($report->research_head_remarks)<p class="{{ $compact ? 'mt-1 text-sm text-brand dark:text-rose-300' : 'secretary-concern' }}">Returned by Research Head · resolve concerns before forwarding</p>@endif
                    </div>
                    <div class="{{ $compact ? 'flex flex-wrap gap-2' : 'secretary-row-actions' }}">
                        @unless ($compact)<button type="button" data-preview-url="{{ $previewUrl }}" data-preview-label="{{ $monitoring ? 'Monitoring Tool' : ucfirst($report->report_label) }}" @click="openAssessmentPreview({ label: $el.dataset.previewLabel, previewUrl: $el.dataset.previewUrl })" aria-haspopup="dialog" aria-controls="secretary-report-preview-panel" class="secretary-button-secondary"><x-report-library-icon name="preview" class="h-4 w-4" /> Preview PDF</button>@endunless
                        <a href="{{ $reportUrl }}" class="{{ $compact ? 'dashboard-action' : 'secretary-button-primary' }}"><x-report-library-icon name="rename" class="h-4 w-4" /> Review report <x-report-library-icon name="open" class="h-4 w-4" /></a>
                    </div>
                </article>
            @empty
                <div class="{{ $compact ? 'dashboard-empty' : 'secretary-empty' }}">@unless ($compact)<span class="secretary-empty-icon" aria-hidden="true">✓</span>@endunless<h3 @class(['break-words font-semibold' => $compact])>No reports awaiting review.</h3><p>Submitted reports appear here when they need your review.</p></div>
            @endforelse
        </div>
    </div>
    @unless ($compact)<x-proposal-paper-preview panel-id="secretary-report-preview-panel" preview-label="Submitted report preview" frame-title="Submitted report preview" />@endunless
</section>
