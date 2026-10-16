<?php

namespace App\Http\Requests;

use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Services\MonitoringQuarterService;
use App\Services\ProjectReportEvidence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SubmitPreparedProjectProgressReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $topic = $this->route('topic');
        $report = $this->route('report');

        return $topic instanceof TopicProposal
            && $report instanceof ProjectProgressReport
            && $report->topic_id === $topic->id
            && $report->isPrepared()
            && ($this->isMethod('DELETE')
                ? in_array($this->user()?->id, [$report->submitted_by, $topic->user_id], true)
                : $this->user()?->id === $topic->user_id)
            && $topic->isMonitoringAvailable()
            && app(MonitoringQuarterService::class)->canSubmitForDate($topic, $report->reporting_date)
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

                if ($this->isMethod('DELETE')) {
                    return;
                }

                if ($topic instanceof TopicProposal
                    && $report instanceof ProjectProgressReport
                    && $topic->research_secretary_id !== null
                    && ! $report->hasPreparedBudget()) {
                    $validator->errors()->add(
                        'preparation',
                        'Complete the budget utilization before this Monitoring Tool is submitted. The assigned project secretary has priority, but any authorized project member may complete it.',
                    );
                }

                if (! $topic instanceof TopicProposal || ! $report instanceof ProjectProgressReport) {
                    return;
                }

                foreach ($report->work_plan ?? [] as $row) {
                    if (((float) ($row['accomplished_percentage'] ?? 0) > 0 || (float) ($row['completed_units'] ?? 0) > 0)
                        && ! collect($row['evidence'] ?? [])->contains(fn (mixed $file): bool => is_array($file)
                            && app(ProjectReportEvidence::class)->isStoredEvidence($topic, 'monitoring', $file))) {
                        $validator->errors()->add(
                            'preparation',
                            'Attach supporting evidence for every activity with completed work before submitting this Monitoring Tool. Discard the prepared PDF and prepare it again with evidence.',
                        );

                        return;
                    }
                }
            },
        ];
    }
}
