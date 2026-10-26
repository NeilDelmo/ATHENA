<?php

namespace App\Http\Requests;

use App\Models\ResearchOutcome;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveResearchOutcomeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $context = $this->route('topic') ? 'project:'.$this->route('topic')->id
            : ($this->route('outcome') ? 'record:'.$this->route('outcome')->id : 'create');
        $this->session()->flash('research_outcome_validation_context', $context);
    }

    public function authorize(): bool
    {
        if ($topic = $this->route('topic')) {
            $outcome = $topic->researchOutcome ?? $topic->researchOutcome()->make(['leader_id' => $topic->user_id]);
            $outcome->setRelation('topic', $topic);

            return $this->user()->can('update', $outcome);
        }

        if ($outcome = $this->route('outcome')) {
            return $outcome->topic_id === null && $this->user()->can('update', $outcome);
        }

        return $this->user()->can('create', ResearchOutcome::class);
    }

    public function rules(): array
    {
        $rules = [
            'labels' => ['sometimes', 'array', 'list', 'max:5'],
            'labels.*' => ['required', 'string', 'distinct', Rule::in(array_keys(ResearchOutcome::LABELS))],
        ];

        if ($this->isMethod('put')) {
            $rules['lock_version'] = ['required', 'integer', 'min:0', 'max:4294967294'];
        }

        if (! $this->route('topic')) {
            $rules += [
                'title' => ['required', 'string', 'max:255'],
                'team_members' => ['nullable', 'string', 'max:2000'],
                'funding_source' => ['required', 'string', Rule::in(['external', 'unfunded'])],
            ];
        }

        return $rules;
    }
}
