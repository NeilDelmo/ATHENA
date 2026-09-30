<?php

namespace App\Http\Requests;

use App\Models\ProjectJournalSubmission;
use App\Models\TopicProposal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveProjectJournalSubmissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $topic = $this->route('topic');
        $submission = $this->route('journalSubmission');

        return $this->user()?->isUsingWorkspace('faculty_researcher')
            && $topic instanceof TopicProposal
            && $topic->isDisseminationAvailable()
            && $topic->isAccessibleTo($this->user())
            && $this->user()->can('view', $topic)
            && (! $submission instanceof ProjectJournalSubmission || $submission->topic_id === $topic->id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'journal_name' => ['required', 'string', 'max:255'],
            'issn' => ['nullable', 'string', 'regex:/^\d{4}-\d{3}[\dX]$/i'],
            'journal_url' => ['nullable', 'url:http,https', 'max:2000'],
            'manuscript_title' => ['required', 'string', 'max:500'],
            'status' => ['required', Rule::in(array_keys(ProjectJournalSubmission::STATUSES))],
            'submission_reference' => ['nullable', 'string', 'max:255'],
            'submitted_on' => ['nullable', 'required_if:status,submitted,under_review,revision_requested,accepted,published,rejected', 'date_format:Y-m-d', 'before_or_equal:today'],
            'accepted_on' => ['nullable', 'required_if:status,accepted,published', 'date_format:Y-m-d', 'after_or_equal:submitted_on', 'before_or_equal:today'],
            'published_on' => ['nullable', 'required_if:status,published', 'date_format:Y-m-d', 'after_or_equal:accepted_on', 'before_or_equal:today'],
            'publication_url' => ['nullable', 'required_if:status,published', 'url:http,https', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'fingerprint' => [
                'required',
                Rule::unique('project_journal_submissions')->where('topic_id', $this->route('topic')?->id)->ignore($this->route('journalSubmission')),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $journal = $this->input('journal_name');
        $title = $this->input('manuscript_title');
        $journal = is_string($journal) ? Str::squish(strip_tags($journal)) : $journal;
        $title = is_string($title) ? Str::squish(strip_tags($title)) : $title;
        $this->merge([
            'journal_name' => $journal,
            'manuscript_title' => $title,
            'fingerprint' => hash('sha256', Str::lower((is_string($journal) ? $journal : '').'|'.(is_string($title) ? $title : ''))),
        ]);
    }

    public function messages(): array
    {
        return ['fingerprint.unique' => 'This manuscript is already tracked for this journal. Update its existing entry below.'];
    }
}
