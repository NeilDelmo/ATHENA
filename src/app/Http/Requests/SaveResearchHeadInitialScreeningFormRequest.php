<?php

namespace App\Http\Requests;

use App\Models\ProposalSignatory;
use App\Models\ProposalVersion;
use App\Models\TopicProposal;
use App\Support\InitialScreeningSubmissionOrder;
use App\Support\ResearchHeadScreeningData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveResearchHeadInitialScreeningFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $topic = $this->route('topic');
        $version = $this->route('version');

        return $topic instanceof TopicProposal && $version instanceof ProposalVersion
            && $this->user()->can('fillInitialScreeningForm', [$topic, $version]);
    }

    protected function prepareForValidation(): void
    {
        $defaults = ProposalSignatory::defaultSelections();
        $this->merge([
            'screening_head' => $defaults['screening_head']['name'],
            'screening_verifier' => $defaults['screening_verifier']['name'],
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'order_of_submission' => ['required', Rule::in([InitialScreeningSubmissionOrder::FIRST_SUBMISSION, InitialScreeningSubmissionOrder::REVISED_WITH_MINOR_CHANGES, InitialScreeningSubmissionOrder::REVISED_WITH_MAJOR_CHANGES])],
            'level_of_call' => ['nullable', Rule::in(['central_agency', 'constituent_campus'])],
            'requested_budget' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:1200'],
            'researcher_count' => ['required', 'integer', 'min:1', 'max:1000'],
            'department' => ['nullable', 'string', 'max:255'],
            'college' => ['nullable', 'string', 'max:255'],
            'campus' => ['nullable', 'string', 'max:255'],
            'screening_head' => ['required', 'string', 'max:160'],
            'screening_center' => ['nullable', 'string', 'max:160'],
            'screening_verifier' => ['nullable', 'string', 'max:160'],
            'recommended_action' => ['required', Rule::in(InitialScreeningSubmissionOrder::recommendations())],
            'narrative_evaluation' => ['required', 'string', 'max:5000'],
            'documents' => ['required', 'array:'.implode(',', array_keys(ResearchHeadScreeningData::DOCUMENTS))],
            'scores' => ['required', 'array:'.implode(',', array_keys(ResearchHeadScreeningData::CRITERIA))],
        ];
        foreach (ResearchHeadScreeningData::DOCUMENTS as $type => $label) {
            $rules['documents.'.$type] = ['required', 'array:attached,pages'];
            $rules['documents.'.$type.'.attached'] = ['required', 'boolean'];
            $rules['documents.'.$type.'.pages'] = ['nullable', 'integer', 'min:1', 'max:10000'];
        }
        foreach (ResearchHeadScreeningData::CRITERIA as $key => $criterion) {
            $rules['scores.'.$key] = ['required', 'integer', Rule::in(array_keys($criterion['scores']))];
        }

        return $rules;
    }
}
