@props(['reports', 'compact' => false])
<section class="secretary-panel" data-secretary-report-queue aria-labelledby="secretary-review-heading" @unless ($compact) data-automatic-assessment-preview x-data="proposalAssessmentPreview()" @resize.window.debounce.150ms="resizeProposalPaperPreview()" @endunless>
    <div @unless ($compact) :inert="previewFullscreen" @endunless>
        <header class="secretary-section-heading">
            <div><h2 id="secretary-review-heading">{{ $compact ? 'Report reviews' : 'Submitted reports' }}</h2></div>
            @if ($compact)<a href="{{ route('research_secretary.reports.index') }}" class="secretary-button-secondary">View all {{ $reports->count() }} <span aria-hidden="true">→</span></a>@else<span class="secretary-count">{{ $reports->count() }} waiting</span>@endif
        </header>
        <div class="secretary-report-list" data-secretary-priority-list>
            @forelse ($compact ? $reports->take(4) : $reports as $report)
                @php
                    $monitoring = $report instanceof \App\Models\ProjectProgressReport;
                    $previewUrl = route($monitoring ? 'project-progress.monitoring-tool.view' : 'project-narrative-reports.view', $report);
                    $reportUrl = route('topics.show', $report->topic).'#'.($monitoring ? 'monitoring-tool-' : 'narrative-report-').$report->id;
                @endphp
                <article class="secretary-report-row" data-secretary-report-kind="{{ $monitoring ? 'monitoring' : $report->report_type }}">
                    <span class="secretary-file-mark" aria-hidden="true"><x-report-library-icon name="{{ $monitoring ? 'list' : 'publication' }}" class="h-5 w-5" /></span>
                    <div class="min-w-0 flex-1">
                        <div class="secretary-row-meta"><span>{{ $monitoring ? 'Monitoring Tool' : ucfirst($report->report_label) }}</span><span>{{ $report->reporting_period_label ?: ($report->reporting_date ?? $report->submitted_at)?->format('M j, Y') }}</span></div>
                        <h3>{{ $report->topic->title }}</h3>
                        <p class="secretary-muted">{{ $report->submitter->name }} <span aria-hidden="true">·</span> Version {{ $report->version_number }}</p>
                        @if ($report->research_head_remarks)<p class="secretary-concern">Returned by Research Head · resolve concerns before forwarding</p>@endif
                    </div>
                    <div class="secretary-row-actions">
                        @unless ($compact)<button type="button" data-preview-url="{{ $previewUrl }}" data-preview-label="{{ $monitoring ? 'Monitoring Tool' : ucfirst($report->report_label) }}" @click="openAssessmentPreview({ label: $el.dataset.previewLabel, previewUrl: $el.dataset.previewUrl })" aria-haspopup="dialog" aria-controls="secretary-report-preview-panel" class="secretary-button-secondary">Preview PDF</button>@endunless
                        <a href="{{ $reportUrl }}" class="secretary-button-primary">Review report <span aria-hidden="true">→</span></a>
                    </div>
                </article>
            @empty
                <div class="secretary-empty"><span class="secretary-empty-icon" aria-hidden="true">✓</span><h3>No reports awaiting review.</h3><p>Submitted reports appear here when they need your review.</p></div>
            @endforelse
        </div>
    </div>
    @unless ($compact)<x-proposal-paper-preview panel-id="secretary-report-preview-panel" preview-label="Submitted report preview" frame-title="Submitted report preview" />@endunless
</section>
