<?php

namespace App\Http\Requests;

use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Services\MonitoringQuarterService;
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
            && $this->user()?->id === $report->submitted_by
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

                if ($topic instanceof TopicProposal
                    && $report instanceof ProjectProgressReport
                    && $topic->research_secretary_id !== null
                    && ! $report->hasSecretaryPreparedBudget()) {
                    $validator->errors()->add(
                        'preparation',
                        'The assigned Research Secretary must complete the budget utilization before this Monitoring Tool can be submitted.',
                    );
                }
            },
        ];
    }
}
