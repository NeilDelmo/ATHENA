<?php

namespace App\Http\Requests;

use App\Models\TopicProposal;
use App\Services\ApprovedWorkPlanMonitoringService;
use DateTimeImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProjectProgressReportDraftRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $topic = $this->route('topic');
        $reportingDate = $this->input('reporting_date');

        if (! $topic instanceof TopicProposal || ! is_string($reportingDate) || blank($reportingDate)) {
            return;
        }

        $parsedReportingDate = DateTimeImmutable::createFromFormat('!Y-m-d', $reportingDate);

        if (! $parsedReportingDate || $parsedReportingDate->format('Y-m-d') !== $reportingDate) {
            return;
        }

        $this->merge([
            'work_plan' => app(ApprovedWorkPlanMonitoringService::class)->synchronizeForDate(
                $topic,
                $parsedReportingDate,
                is_array($this->input('work_plan')) ? $this->input('work_plan') : [],
            ),
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $topic = $this->route('topic');

        return $topic instanceof TopicProposal
            && $topic->isMonitoringAvailable()
            && $this->user() !== null
            && $topic->isAccessibleTo($this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'draft_version' => ['required', 'integer', 'min:0'],
            'source_report_id' => ['nullable', 'integer'],
            'reporting_date' => ['nullable', 'date'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'work_plan' => ['nullable', 'array', 'max:11'],
            'work_plan.*.source_work_plan_index' => ['nullable', 'integer', 'min:0'],
            'work_plan.*.objective' => ['nullable', 'string', 'max:500'],
            'work_plan.*.activity' => ['nullable', 'string', 'max:1500'],
            'work_plan.*.percent_weight' => ['nullable', 'numeric', 'between:0,100'],
            'work_plan.*.physical_target' => ['nullable', 'string', 'max:500'],
            'work_plan.*.target_completion_date' => ['nullable', 'date'],
            'work_plan.*.work_plan_months' => ['nullable', 'array', 'max:120'],
            'work_plan.*.work_plan_months.*' => ['integer', 'min:1', 'max:120'],
            'work_plan.*.actual_accomplishment' => ['nullable', 'string', 'max:500'],
            'work_plan.*.accomplished_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'work_plan.*.findings' => ['nullable', 'string', 'max:500'],
            'budget_utilization' => ['nullable', 'array', 'max:3'],
            'budget_utilization.*.type' => [
                'nullable',
                'distinct',
                Rule::in(['Purchase Request', 'Cash Advance', 'Request of Payment']),
            ],
            'budget_utilization.*.details' => ['nullable', 'string', 'max:300'],
            'budget_utilization.*.amount_requested' => ['nullable', 'numeric', 'min:0'],
            'budget_utilization.*.actual_amount' => ['nullable', 'numeric', 'min:0'],
            'budget_utilization.*.remarks' => ['nullable', 'string', 'max:300'],
            'prepared_by_date_signed' => ['nullable', 'date'],
        ];
    }
}
