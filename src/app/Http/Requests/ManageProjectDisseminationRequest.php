<?php

namespace App\Http\Requests;

use App\Models\ProjectConference;
use App\Models\ResearchPublication;
use App\Models\TopicProposal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManageProjectDisseminationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $topic = $this->route('topic');
        if (! $topic instanceof TopicProposal || ! $topic->isDisseminationAvailable() || ! $this->user()?->can('view', $topic)) {
            return false;
        }

        if ($this->isMethod('GET')) {
            return $this->user()->isUsingWorkspace(['faculty_researcher', 'research_head']);
        }

        if (! $this->user()->isUsingWorkspace('faculty_researcher') || ! $topic->isAccessibleTo($this->user())) {
            return false;
        }

        $conference = $this->route('conference');
        $publication = $this->route('publication');

        return (! $conference || ($conference instanceof ProjectConference && $conference->topic_id === $topic->id))
            && (! $publication || ($publication instanceof ResearchPublication && $publication->user_id === $this->user()->id));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('doi'))) {
            $this->merge(['doi' => ResearchPublication::normalizeDoi($this->input('doi'))]);
        }
    }

    public function rules(): array
    {
        $url = ['nullable', 'url:http,https', 'max:2000'];

        return match (true) {
            $this->routeIs('research.dissemination.authors') => [
                'query' => ['required', 'string', 'min:3', 'max:150'],
                'institution' => ['nullable', 'string', 'max:150'],
            ],
            $this->routeIs('research.dissemination.profile') => [
                'author_id' => ['nullable', 'regex:/^A[0-9]{1,20}$/'],
                'confirmed' => ['required_with:author_id', 'accepted'],
                'scholar_url' => [...$url, 'regex:~^https://scholar\\.google\\.com/citations\\?[^#]*\\buser=[A-Za-z0-9_-]+(?:&[^#]*)?$~'],
            ],
            $this->routeIs('research.dissemination.papers') => [
                'page' => ['nullable', 'integer', 'min:1', 'max:500'],
                'author_id' => ['nullable', 'regex:/^A[0-9]{1,20}$/'],
            ],
            $this->routeIs('research.dissemination.doi') => ['doi' => ['required', 'string', 'max:255', 'regex:~^10\\.\\d{4,9}/\\S+$~']],
            $this->routeIs('research.dissemination.import') => [
                'work_id' => ['required', 'regex:/^W[0-9]{1,20}$/'],
                'confirmed' => ['required', 'accepted'],
                'lookup_doi' => ['nullable', 'string', 'max:255', 'regex:~^10\\.\\d{4,9}/\\S+$~'],
            ],
            $this->routeIs('research.dissemination.manual') => [
                'title' => ['required', 'string', 'max:1000'],
                'authors' => ['required', 'string', 'max:10000'],
                'venue' => ['nullable', 'string', 'max:500'],
                'year' => ['required', 'integer', 'min:1800', 'max:'.now()->year],
                'doi' => ['nullable', 'string', 'max:255', 'regex:~^10\\.\\d{4,9}/\\S+$~'],
                'url' => $url,
                'type' => ['required', Rule::in(['journal-article', 'proceedings-article', 'preprint', 'other'])],
                'confirmed' => ['required', 'accepted'],
            ],
            $this->routeIs('research.dissemination.link') => ['publication_id' => ['required', 'integer']],
            $this->routeIs('research.dissemination.conferences.search') => [
                'query' => ['required', 'string', 'min:3', 'max:140'],
                'scope' => ['nullable', Rule::in(['local', 'international', 'online'])],
                'open_only' => ['nullable', 'boolean'],
            ],
            $this->routeIs('research.dissemination.conferences.store', 'research.dissemination.conferences.update') => [
                'candidate_key' => ['nullable', 'string', 'size:64'],
                'title' => ['required', 'string', 'max:500'],
                'url' => ['required', 'url:http,https', 'max:2000'],
                'official_url' => $url,
                'location' => ['nullable', 'string', 'max:500'],
                'submission_deadline' => ['nullable', 'date_format:Y-m-d'],
                'event_date' => ['nullable', 'date_format:Y-m-d'],
                'attendance_mode' => ['nullable', Rule::in(['in_person', 'online', 'hybrid'])],
                'fees' => ['nullable', 'string', 'max:500'],
                'publication_details' => ['nullable', 'string', 'max:5000'],
                'status' => ['required', Rule::in(ProjectConference::STATUSES)],
                'submitted_on' => ['nullable', 'required_if:status,submitted,accepted,presented', 'date_format:Y-m-d', 'before_or_equal:today'],
                'accepted_on' => ['nullable', 'required_if:status,accepted,presented', 'date_format:Y-m-d', 'after_or_equal:submitted_on', 'before_or_equal:today'],
                'presented_on' => ['nullable', 'required_if:status,presented', 'date_format:Y-m-d', 'after_or_equal:accepted_on', 'before_or_equal:today'],
                'evidence_url' => $url,
                'notes' => ['nullable', 'string', 'max:5000'],
            ],
            default => [],
        };
    }
}
