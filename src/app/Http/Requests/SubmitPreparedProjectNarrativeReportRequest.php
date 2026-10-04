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
            && ($this->isMethod('DELETE')
                ? in_array($this->user()?->id, [$report->submitted_by, $topic->user_id], true)
                : $this->user()?->id === $topic->user_id)
            && $topic->isMonitoringAvailable()
            && ($report->report_type === 'terminal'
                ? app(MonitoringQuarterService::class)->canSubmitTerminal($topic)
                    && ($this->isMethod('DELETE') || app(MonitoringQuarterService::class)->missingTerminalReportPeriods($topic) === [])
                : ($this->isMethod('DELETE') || $report->reporting_date !== null && app(MonitoringQuarterService::class)->canSubmitForDate($topic, $report->reporting_date)))
            && $topic->isAccessibleTo($this->user());
    }

    public function rules(): array
    {
        return [];
    }
}
