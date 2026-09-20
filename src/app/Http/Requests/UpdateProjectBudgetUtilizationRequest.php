<?php

namespace App\Http\Requests;

use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProjectBudgetUtilizationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $topic = $this->route('topic');
        $report = $this->route('report');
        $user = $this->user();

        return $topic instanceof TopicProposal
            && $report instanceof ProjectProgressReport
            && $report->topic_id === $topic->id
            && $report->isPrepared()
            && $user !== null
            && $user->isUsingWorkspace(User::WORKSPACE_RESEARCH_SECRETARY)
            && $topic->research_secretary_id === $user->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
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
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $budgetInput = $this->input('budget_utilization', []);
                $budget = collect(is_array($budgetInput) ? $budgetInput : [])
                    ->filter(fn (mixed $entry): bool => is_array($entry));
                $projectCost = (float) ($this->route('topic')?->estimated_budget ?? 0);

                foreach ($budget as $index => $entry) {
                    if ((float) ($entry['actual_amount'] ?? 0) > (float) ($entry['amount_requested'] ?? 0)) {
                        $validator->errors()->add(
                            "budget_utilization.{$index}.actual_amount",
                            'The actual amount may not exceed the amount requested.',
                        );
                    }
                }

                if ($projectCost > 0 && $budget->sum(fn (array $entry): float => (float) ($entry['amount_requested'] ?? 0)) > $projectCost) {
                    $validator->errors()->add('budget_utilization', 'The total amount requested may not exceed the project cost.');
                }

                if ($projectCost > 0 && $budget->sum(fn (array $entry): float => (float) ($entry['actual_amount'] ?? 0)) > $projectCost) {
                    $validator->errors()->add('budget_utilization', 'The total amount disbursed may not exceed the project cost.');
                }
            },
        ];
    }
}
