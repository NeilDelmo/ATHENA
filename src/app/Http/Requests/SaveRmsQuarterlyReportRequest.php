<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Services\MonitoringQuarterService;
use App\Services\RmsQuarterlyReportData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use JsonException;

class SaveRmsQuarterlyReportRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->exists('row_details_json')) {
            return;
        }

        $json = $this->input('row_details_json');
        if (! is_string($json) || strlen($json) > 5 * 1024 * 1024 || ! str_starts_with(ltrim($json), '{')) {
            throw ValidationException::withMessages(['row_details_json' => 'The report details could not be read. Reload the report before saving.']);
        }

        try {
            $rows = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['row_details_json' => 'The report details could not be read. Reload the report before saving.']);
        }

        $this->merge(['row_details' => collect($rows)->map(fn (mixed $row): mixed => is_array($row)
            ? array_map(fn (mixed $value): mixed => is_string($value) ? trim($value) : $value, $row)
            : $row)->all()]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $allFields = array_keys(Arr::except(array_merge(...array_values(RmsQuarterlyReportData::FIELDS)), 'college'));
        $rules = [
            'reporting_year' => ['required', 'integer', 'between:2000,2100'],
            'reporting_quarter' => ['required', 'integer', 'between:1,4'],
            'source_token' => ['required', 'string', 'size:64'],
            'draft_token' => ['required', 'string', 'size:64'],
            'form_complete' => ['required', Rule::in(['1'])],
            'intent' => ['sometimes', Rule::in(['save', 'preview', 'download'])],
            'campus' => ['required', 'string', 'max:255'],
            'signatories' => ['nullable', 'array:prepared,reviewed,approved'],
            'signatories.*' => ['array:name,position,office'],
            'signatories.*.*' => ['nullable', 'string', 'max:255'],
            'row_details' => ['nullable', 'array', 'max:5000'],
            'row_details.*' => ['array:'.implode(',', $allFields)],
        ];

        foreach (is_array($this->input('row_details')) ? $this->input('row_details') : [] as $key => $row) {
            if (! is_string($key) || ! preg_match('/^(presentation|publication|ip)-[a-zA-Z0-9-]+$/D', $key)) {
                continue;
            }
            $section = match (strtok($key, '-')) {
                'presentation' => 'presentations', 'publication' => 'publications', default => 'ip_assets',
            };
            $fields = Arr::except(RmsQuarterlyReportData::FIELDS[$section], 'college');
            $rules['row_details.'.$key] = ['array:'.implode(',', array_keys($fields))];
            foreach ($fields as $field => $definition) {
                $rules['row_details.'.$key.'.'.$field] = match ($definition['type']) {
                    'select' => ['nullable', 'string', Rule::in($definition['options'])],
                    'url' => ['nullable', 'string', 'url:http,https', 'max:2048'],
                    'date' => ['nullable', 'date_format:Y-m-d'],
                    default => ['nullable', 'string', 'max:2000'],
                };
                if ($field === 'sdgs') {
                    $rules['row_details.'.$key.'.'.$field] = ['nullable', 'string', 'max:255', 'regex:/^(?:[1-9]|1[0-7])(?:\s*,\s*(?:[1-9]|1[0-7]))*$/D'];
                }
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'row_details.*.sdgs.regex' => 'Enter SDG numbers from 1 to 17, separated by commas.',
            'draft_token.required' => 'This report form is out of date. Copy any work you need, then reload the report before saving.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $period = app(MonitoringQuarterService::class)->forYearAndQuarter($this->integer('reporting_year'), $this->integer('reporting_quarter'));
            foreach ($this->input('row_details', []) ?? [] as $key => $row) {
                if ((str_starts_with($key, 'publication-') || str_starts_with($key, 'ip-')) && filled($row['date'] ?? null)
                    && ($row['date'] < $period['start']->toDateString() || $row['date'] > $period['end']->toDateString())) {
                    $validator->errors()->add('row_details.'.$key.'.date', 'The date must fall within the selected calendar quarter.');
                }
            }
        }];
    }

    public function attributes(): array
    {
        $attributes = [];
        foreach (RmsQuarterlyReportData::FIELDS as $fields) {
            foreach ($fields as $field => $definition) {
                $attributes['row_details.*.'.$field] = $definition['label'];
            }
        }

        return $attributes;
    }
}
