<?php

namespace App\Http\Requests;

use App\Models\TopicProposal;
use App\Services\ApprovedWorkPlanMonitoringService;
use App\Services\MonitoringQuarterService;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProjectProgressReportRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $window = app(MonitoringQuarterService::class)->reportingWindow($this->route('topic'));

        return [
            'reporting_date' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$window['start']->toDateString(), ...($window['end'] ? ['before_or_equal:'.$window['end']->toDateString()] : [])],
            'source_report_id' => ['nullable', 'integer'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'work_plan' => ['required', 'array', 'min:1', 'max:11'],
            'work_plan.*.source_work_plan_index' => ['nullable', 'integer', 'min:0'],
            'work_plan.*.objective' => ['nullable', 'string', 'max:500'],
            'work_plan.*.activity' => ['required', 'string', 'max:1500'],
            'work_plan.*.percent_weight' => ['required', 'numeric', 'between:0,100'],
            'work_plan.*.physical_target' => ['required', 'string', 'max:500'],
            'work_plan.*.target_completion_date' => ['required', 'date'],
            'work_plan.*.work_plan_months' => ['nullable', 'array', 'max:120'],
            'work_plan.*.work_plan_months.*' => ['integer', 'min:1', 'max:120'],
            'work_plan.*.actual_accomplishment' => ['required', 'string', 'max:500'],
            'work_plan.*.accomplished_percentage' => ['required', 'numeric', 'between:0,100'],
            'work_plan.*.findings' => ['nullable', 'string', 'max:500'],
            'budget_utilization' => ['required', 'array', 'size:3'],
            'budget_utilization.*.type' => [
                'required',
                'distinct',
                Rule::in(['Purchase Request', 'Cash Advance', 'Request of Payment']),
            ],
            'budget_utilization.*.details' => ['nullable', 'string', 'max:300'],
            'budget_utilization.*.amount_requested' => ['required', 'numeric', 'min:0'],
            'budget_utilization.*.actual_amount' => ['required', 'numeric', 'min:0'],
            'budget_utilization.*.remarks' => ['nullable', 'string', 'max:300'],
            'prepared_by_date_signed' => ['nullable', 'date', 'before_or_equal:today'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:25600'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $validator->errors()->has('reporting_date') && ! app(MonitoringQuarterService::class)->canSubmitForDate($this->route('topic'), $this->input('reporting_date'))) {
                    $validator->errors()->add('reporting_date', 'This reporting period is still in progress. Submit after its end date.');
                }
                $workPlanInput = $this->input('work_plan', []);
                $budgetInput = $this->input('budget_utilization', []);
                $workPlan = collect(is_array($workPlanInput) ? $workPlanInput : [])
                    ->filter(fn ($entry): bool => is_array($entry));
                $budget = collect(is_array($budgetInput) ? $budgetInput : [])
                    ->filter(fn ($entry): bool => is_array($entry));
                $projectCost = (float) ($this->route('topic')?->estimated_budget ?? 0);

                if ($workPlan->sum(fn ($entry): float => (float) ($entry['percent_weight'] ?? 0)) > 100) {
                    $validator->errors()->add('work_plan', 'The total activity weight may not exceed 100%.');
                }

                if ($workPlan->sum(fn ($entry): float => (float) ($entry['accomplished_percentage'] ?? 0)) > 100) {
                    $validator->errors()->add('work_plan', 'The total accomplished percentage may not exceed 100%.');
                }

                foreach ($workPlan as $index => $entry) {
                    if ((float) ($entry['accomplished_percentage'] ?? 0) > (float) ($entry['percent_weight'] ?? 0) + 0.005) {
                        $validator->errors()->add("work_plan.{$index}.accomplished_percentage", 'An activity cannot contribute more than its assigned share of the project.');
                    }
                }

                foreach ($budget as $index => $entry) {
                    if ((float) ($entry['actual_amount'] ?? 0) > (float) ($entry['amount_requested'] ?? 0)) {
                        $validator->errors()->add(
                            "budget_utilization.{$index}.actual_amount",
                            'The actual amount may not exceed the amount requested.',
                        );
                    }
                }

                if ($projectCost > 0 && $budget->sum(fn ($entry): float => (float) ($entry['amount_requested'] ?? 0)) > $projectCost) {
                    $validator->errors()->add('budget_utilization', 'The total amount requested may not exceed the project cost.');
                }

                if ($projectCost > 0 && $budget->sum(fn ($entry): float => (float) ($entry['actual_amount'] ?? 0)) > $projectCost) {
                    $validator->errors()->add('budget_utilization', 'The total amount disbursed may not exceed the project cost.');
                }
            },
        ];
    }
}
