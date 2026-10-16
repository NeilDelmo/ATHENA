<?php

namespace App\Http\Requests;

use App\Models\ProjectNarrativeReport;
use App\Models\TopicProposal;
use App\Services\MonitoringQuarterService;
use App\Services\ProjectReportEvidence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SubmitPreparedProjectNarrativeReportRequest extends FormRequest
{
    protected $errorBag = 'narrativeProgress';

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

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $topic = $this->route('topic');
                $report = $this->route('report');

                if ($this->isMethod('DELETE') || ! $topic instanceof TopicProposal
                    || ! $report instanceof ProjectNarrativeReport || $report->report_type === 'terminal') {
                    return;
                }

                foreach ($report->accomplishments ?? [] as $row) {
                    if (filled($row['actual'] ?? null)
                        && ! collect($row['evidence'] ?? [])->contains(fn (mixed $file): bool => is_array($file)
                            && app(ProjectReportEvidence::class)->isStoredEvidence($topic, 'narrative', $file))) {
                        $validator->errors()->add(
                            'preparation',
                            'Attach supporting evidence for every reported accomplishment before submitting this Progress Report. Discard the prepared PDF and prepare it again with evidence.',
                        );

                        return;
                    }
                }
            },
        ];
    }
}
