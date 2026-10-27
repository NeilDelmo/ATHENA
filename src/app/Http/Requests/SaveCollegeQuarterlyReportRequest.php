<?php

namespace App\Http\Requests;

use App\Models\CollegeQuarterlyReport;
use App\Models\TopicProposal;
use App\Services\MonitoringQuarterService;
use App\Support\ProposalRichText;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use JsonException;

class SaveCollegeQuarterlyReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('research_coordinator')
            && filled($this->user()->college);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'reporting_year' => ['required', 'integer', 'between:2000,2100'],
            'reporting_quarter' => ['required', 'integer', 'between:1,4'],
            'form_complete' => ['required', Rule::in(['1'])],
            'draft_token' => ['required', 'string', 'size:64'],
            'evidence_file_count' => ['required_with:report_details_json', 'integer', 'between:0,1510'],
            'intent' => ['required', Rule::in(['save', 'preview', 'download', 'submit'])],
            'other_accomplishments' => ['nullable', 'string', 'max:100000'],
            'reviewed_by' => ['nullable', 'string', 'max:255'],
            'reviewed_by_title' => ['nullable', 'string', 'max:255'],
            'evidence_images' => ['nullable', 'array', 'max:10'],
            'evidence_images.*' => ['required', 'image', 'mimetypes:image/jpeg,image/png', 'max:5120'],
            'evidence_captions' => ['nullable', 'array', 'max:10'],
            'evidence_captions.*' => ['nullable', 'string', 'max:1000'],
            'saved_evidence_captions' => ['nullable', 'array', 'max:1510'],
            'saved_evidence_captions.*' => ['nullable', 'string', 'max:1000'],
            'remove_evidence' => ['nullable', 'array', 'max:1510'],
            'remove_evidence.*' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/', 'distinct'],
            'row_evidence' => ['nullable', 'array:presentations,publications,seminars'],
            'row_evidence.*' => ['required', 'array', 'max:100'],
            'row_evidence.*.*' => ['required', 'array', 'min:1', 'max:5'],
            'row_evidence.*.*.*' => ['required', 'image', 'mimetypes:image/jpeg,image/png', 'max:5120'],
            'manual_research' => ['nullable', 'array', 'max:100'],
            'manual_research.*' => ['required', 'array:id,title,duration,personnel,status'],
            'manual_research.*.id' => ['required', 'uuid', 'distinct'],
            'manual_research.*.title' => ['required', 'string', 'max:1000'],
            'manual_research.*.duration' => ['required', 'string', 'max:255'],
            'manual_research.*.personnel' => ['required', 'string', 'max:2000'],
            'manual_research.*.status' => ['required', 'string', 'max:255'],
            'ip_assets' => ['nullable', 'array', 'max:100'],
            'ip_assets.*' => ['required', 'array:id,title,type,inventors,date,remarks,evidence_url'],
            'ip_assets.*.id' => ['required', 'uuid', 'distinct'],
            'ip_assets.*.title' => ['required', 'string', 'max:1000'],
            'ip_assets.*.type' => ['required', Rule::in(CollegeQuarterlyReport::IP_ASSET_TYPES)],
            'ip_assets.*.inventors' => ['required', 'string', 'max:2000'],
            'ip_assets.*.date' => ['required', 'date_format:Y-m-d'],
            'ip_assets.*.remarks' => ['nullable', 'string', 'max:2000'],
            'ip_assets.*.evidence_url' => ['nullable', 'url:http,https', 'max:2048'],
        ];
        foreach (['presentations', 'publications', 'seminars'] as $section) {
            $rules[$section] = ['nullable', 'array', 'max:100'];
            $rules[$section.'.*'] = ['required', $section === 'publications' ? 'array:id,event,research_title,date,personnel,project_id' : 'array:id,event,research_title,date,place,personnel,project_id'];
            $rules[$section.'.*.id'] = ['required', 'uuid', 'distinct'];
            $rules[$section.'.*.event'] = ['required', 'string', 'max:1000'];
            $rules[$section.'.*.research_title'] = [$section === 'seminars' ? 'nullable' : 'required', 'string', 'max:1000'];
            $rules[$section.'.*.date'] = ['required', 'date_format:Y-m-d'];
            $rules[$section.'.*.personnel'] = ['required', 'string', 'max:2000'];
            $rules[$section.'.*.project_id'] = ['nullable', 'integer', 'min:1'];
            if ($section !== 'publications') {
                $rules[$section.'.*.place'] = ['required', 'string', 'max:1000'];
            }
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('report_details_json')) {
            $json = $this->input('report_details_json');
            if (! is_string($json) || strlen($json) > 5 * 1024 * 1024 || ! str_starts_with(ltrim($json), '{')) {
                throw ValidationException::withMessages(['report_details_json' => 'The report details could not be read. Reload the report before saving.']);
            }
            try {
                $details = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw ValidationException::withMessages(['report_details_json' => 'The report details could not be read. Reload the report before saving.']);
            }
            if (array_diff(array_keys($details), ['presentations', 'publications', 'seminars', 'manual_research', 'ip_assets', 'saved_evidence_captions', 'remove_evidence', 'evidence_captions']) !== []) {
                throw ValidationException::withMessages(['report_details_json' => 'The report details contain unsupported fields. Reload the report before saving.']);
            }
            array_walk_recursive($details, function (mixed &$value): void {
                if (is_string($value)) {
                    $value = trim($value);
                    $value = $value === '' ? null : $value;
                }
            });
            $this->merge($details);
        }

        foreach (['presentations', 'publications', 'seminars', 'manual_research', 'ip_assets'] as $section) {
            if (is_array($this->input($section))) {
                $this->merge([$section => collect($this->input($section))->reject(
                    fn (mixed $row): bool => is_array($row) && collect($row)->except('id')->every(fn (mixed $value): bool => blank($value)),
                )->map(fn (mixed $row): mixed => is_array($row) ? [...$row, 'id' => $row['id'] ?? (string) Str::uuid()] : $row)->values()->all()]);
            }
        }
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if ($this->has('evidence_file_count') && $this->integer('evidence_file_count') !== count(Arr::flatten($this->allFiles()))) {
                $validator->errors()->add('evidence_file_count', 'Some selected files did not reach the server. Upload fewer files at a time before submitting.');

                return;
            }

            $value = $this->input('other_accomplishments', '') ?? '';
            $accomplishments = app(ProposalRichText::class)->sanitize($value);
            if (mb_strlen(html_entity_decode(strip_tags($accomplishments), ENT_QUOTES | ENT_HTML5, 'UTF-8')) > 20000) {
                $validator->errors()->add('other_accomplishments', 'The other accomplishments field must not be greater than 20000 visible characters.');
            }
            if (strlen(strip_tags($value) === $value ? $value : $accomplishments) > 65535) {
                $validator->errors()->add('other_accomplishments', 'The other accomplishments field exceeds the available document storage size.');
            }

            $period = app(MonitoringQuarterService::class)->forYearAndQuarter($this->integer('reporting_year'), $this->integer('reporting_quarter'));
            $projectIds = collect(['presentations', 'publications', 'seminars'])->flatMap(fn (string $section): array => $this->input($section, []) ?? [])->pluck('project_id')->filter()->unique();
            $projects = TopicProposal::query()->whereIn('status', [TopicProposal::STATUS_READY_FOR_SIGNATURE, 'approved'])
                ->forCollege($this->user()->college)->whereKey($projectIds)->get()->keyBy('id');
            foreach (['presentations', 'publications', 'seminars', 'ip_assets'] as $section) {
                $rows = $this->input($section, []) ?? [];
                foreach ($rows as $index => $row) {
                    if ($row['date'] < $period['start']->toDateString() || $row['date'] > $period['end']->toDateString()) {
                        $validator->errors()->add($section.'.'.$index.'.date', 'The activity date must fall within the selected calendar quarter.');
                    }
                    if ($section !== 'ip_assets' && filled($row['project_id'] ?? null) && (! $projects->has($row['project_id']) || ! $this->user()->can('view', $projects[$row['project_id']]))) {
                        $validator->errors()->add($section.'.'.$index.'.project_id', 'Choose an accessible approved project from your college.');
                    }
                }
                foreach (array_keys($this->file('row_evidence.'.$section, [])) as $rowId) {
                    if (! in_array($rowId, array_column($rows, 'id'), true)) {
                        $validator->errors()->add('row_evidence.'.$section, 'Evidence must belong to an activity in this report.');
                    }
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'form_complete.required' => 'The report form was incomplete. Reload the report before saving.',
            'draft_token.required' => 'This report form is out of date. Copy any work you need, then reload the report before saving.',
        ];
    }
}
