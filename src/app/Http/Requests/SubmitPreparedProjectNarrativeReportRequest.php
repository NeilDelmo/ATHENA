<?php

namespace App\Http\Requests;

use App\Models\ProjectNarrativeReport;
use App\Models\TopicProposal;
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
            && $topic->isAccessibleTo($this->user());
    }

    public function rules(): array
    {
        return [];
    }
}
