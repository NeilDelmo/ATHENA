<?php

namespace App\Http\Requests;

use App\Models\ProjectNarrativeReport;
use App\Models\TopicProposal;
use App\Services\MonitoringQuarterService;
use Illuminate\Foundation\Http\FormRequest;

class SubmitPreparedProjectNarrativeReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $topic = $this->route('topic');
        $report = $this->route('report');

        return $topic instanceof TopicProposal
            && $report instanceof ProjectNarrativeReport
            && $report->topic_id === $topic->id
            && $report->isPrepared()
            && $this->user()?->id === $report->submitted_by
            && $topic->isMonitoringAvailable()
            && ($report->report_type === 'terminal'
                ? app(MonitoringQuarterService::class)->canSubmitTerminal($topic)
                    && ($this->isMethod('DELETE') || app(MonitoringQuarterService::class)->missingTerminalMonitoringPeriods($topic) === [])
                : app(MonitoringQuarterService::class)->projectPeriods($topic)->contains(fn (array $period): bool => now()->greaterThanOrEqualTo($period['opens_at'])))
            && $topic->isAccessibleTo($this->user());
    }

    public function rules(): array
    {
        return [];
    }
}
