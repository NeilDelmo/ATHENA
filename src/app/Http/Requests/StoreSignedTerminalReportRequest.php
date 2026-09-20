<?php

namespace App\Http\Requests;

use App\Models\ProjectNarrativeReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreSignedTerminalReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $report = $this->route('report');

        if (! $report instanceof ProjectNarrativeReport
            || ! $this->user()?->isUsingWorkspace('research_head')
            || $report->report_type !== 'terminal'
            || ! $report->isSubmitted()
            || $report->review_status !== ProjectNarrativeReport::STATUS_REVIEWED) {
            return false;
        }

        $latestTerminalReportId = $report->topic
            ->narrativeReports()
            ->where('report_type', 'terminal')
            ->reorder()
            ->latest('id')
            ->value('id');

        return $latestTerminalReportId === $report->id;
    }

    public function rules(): array
    {
        return [
            'signed_report' => ['required', File::types(['pdf'])->max('25mb')],
        ];
    }

    public function messages(): array
    {
        return [
            'signed_report.required' => 'Select the fully signed Terminal Report PDF.',
            'signed_report.mimes' => 'The signed Terminal Report must be a PDF.',
            'signed_report.max' => 'The signed Terminal Report may not be larger than 25 MB.',
        ];
    }
}
