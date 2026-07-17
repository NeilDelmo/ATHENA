<?php

namespace App\Http\Requests;

use App\Models\ProposalDraft;
use App\Support\CurriculumVitaeRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateProposalDraftCurriculumVitaeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $draft = $this->route('proposalDraft');

        return $draft instanceof ProposalDraft
            && ($this->user()?->can('update', $draft) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $draft = $this->route('proposalDraft');

        if (! $draft instanceof ProposalDraft) {
            return;
        }

        $savedSource = $draft->documents()
            ->where('document_type', config('proposal_papers.curriculum-vitae.document_type'))
            ->where('position', 0)
            ->value('source_data');

        if (is_array($savedSource)) {
            $this->merge(array_replace($savedSource, $this->all()));
        }

        $this->normalizeAcademicStatuses();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            ...CurriculumVitaeRules::rules(),
            'document_version' => [$this->isMethod('PUT') ? 'required' : 'nullable', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return CurriculumVitaeRules::attributes();
    }

    private function normalizeAcademicStatuses(): void
    {
        $people = $this->input('people');

        if (! is_array($people)) {
            return;
        }

        foreach ($people as &$person) {
            if (! is_array($person) || ! is_array($person['academic_background'] ?? null)) {
                continue;
            }

            foreach ($person['academic_background'] as &$entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $status = Str::of((string) ($entry['status'] ?? ''))
                    ->trim()
                    ->lower()
                    ->remove([' ', '-', '_'])
                    ->toString();

                if ($status === 'ongoing') {
                    $entry['status'] = 'Ongoing';
                    $entry['year_end'] = null;
                } elseif ($status === 'graduated') {
                    $entry['status'] = 'Graduated';
                } elseif ($status === 'dropped') {
                    $entry['status'] = 'Dropped';
                } elseif ($status === 'terminated') {
                    $entry['status'] = 'Terminated';
                }
            }
            unset($entry);
        }
        unset($person);

        $this->merge(['people' => $people]);
    }
}
