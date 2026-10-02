<?php

namespace App\Http\Requests;

use App\Models\ProjectNarrativeReport;
use App\Models\TopicProposal;
use App\Services\MonitoringQuarterService;
use App\Support\ProgressReportData;
use App\Support\TerminalReportData;
use App\Support\TerminalReportRules;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProjectNarrativeReportRequest extends FormRequest
{
    protected $errorBag = 'narrativeProgress';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $topic = $this->route('topic');

        return $topic instanceof TopicProposal
            && $topic->isMonitoringAvailable()
            && ($this->input('report_type') === 'terminal'
                ? app(MonitoringQuarterService::class)->canSubmitTerminal($topic)
                : app(MonitoringQuarterService::class)->projectPeriods($topic)->contains(fn (array $period): bool => now()->greaterThanOrEqualTo($period['opens_at'])))
            && $this->user() !== null
            && $topic->isAccessibleTo($this->user());
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['report_type' => $this->input('report_type', 'progress')]);
        $accomplishments = collect($this->input('accomplishments', []))
            ->filter(fn (mixed $row): bool => is_array($row) && collect($row)->contains(fn (mixed $value): bool => filled($value)))
            ->values()
            ->all();

        $this->merge(['accomplishments' => $accomplishments]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'reporting_date' => [Rule::excludeIf($this->input('report_type') === 'terminal'), 'required', 'date_format:Y-m-d'],
            'submission_date' => ['required', 'date', 'before_or_equal:today'],
            'report_type' => ['required', Rule::in(['progress', 'terminal'])],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'researchers' => ['required', 'string', 'max:1000'],
            'implementation_start' => ['required', 'date'],
            'implementation_end' => ['required', 'date', 'after_or_equal:implementation_start'],
            'funding_agency' => ['required', 'string', 'max:255'],
            'accomplishments' => ['required', 'array', 'min:1', 'max:1000'],
            'accomplishments.*.objective' => ['required', 'string', 'max:1000'],
            'accomplishments.*.target' => ['required', 'string', 'max:2000'],
            'accomplishments.*.actual' => ['required', 'string', 'max:2000'],
            'introduction' => ['required', 'string', 'max:'.config('detailed_proposal.maximum_narrative_length')],
            'rationale' => ['required', 'string', 'max:'.config('detailed_proposal.maximum_narrative_length')],
            'objectives' => ['required', 'string', 'max:'.config('detailed_proposal.maximum_narrative_length')],
            'methodology' => ['required', 'string', 'max:'.config('detailed_proposal.maximum_narrative_length')],
            'results_discussion' => ['required', 'string', 'max:'.config('detailed_proposal.maximum_narrative_length')],
            'prepared_by_date_signed' => ['nullable', 'date', 'before_or_equal:today'],
        ];

        if ($this->input('report_type') !== 'terminal') {
            $rules['figures'] = ['nullable', 'array'];
            $rules['figures.*'] = ['array:image,caption,section,after_paragraph'];
            $rules['figures.*.image'] = ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:10240'];
            $rules['figures.*.caption'] = ['nullable', 'string', 'max:1000', 'required_with:figures.*.image'];
            $rules['figures.*.section'] = ['nullable', Rule::in(['methodology', 'results_discussion']), 'required_with:figures.*.image'];
            $rules['figures.*.after_paragraph'] = ['nullable', 'integer', 'min:0', 'max:100000'];
        }
        foreach (app(ProgressReportData::class)->legacyFigureIndexes($this->all()) as $index) {
            $rules['photo_'.$index] = ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:10240'];
            $rules['photo_caption_'.$index] = ['nullable', 'string', 'max:1000', 'required_with:photo_'.$index];
            $rules['photo_section_'.$index] = ['nullable', Rule::in(['methodology', 'results_discussion']), 'required_with:photo_'.$index];
            $rules['photo_after_paragraph_'.$index] = ['nullable', 'integer', 'min:0', 'max:100000'];
        }

        if ($this->input('report_type') === 'terminal') {
            $topic = $this->route('topic');
            $approvedBudget = $topic instanceof TopicProposal
                ? (float) ($topic->latestVersion?->estimated_budget ?? $topic->estimated_budget ?? 0)
                : null;
            $rules = array_merge($rules, TerminalReportRules::rules(false, $approvedBudget));
            foreach (['introduction', 'rationale', 'methodology', 'results_discussion'] as $field) {
                $rules[$field] = ['required', 'string', 'max:100000'];
            }
            $rules['implementation_start'][] = 'before_or_equal:today';
            $rules['implementation_end'][] = 'before_or_equal:today';
            $rules['implementation_end'][] = 'before_or_equal:submission_date';
            $rules['researchers'] = ['nullable', 'string', 'max:10000'];
            $rules['funding_agency'] = ['nullable', 'string', 'max:255'];
            $rules['objectives'] = ['nullable', 'string', 'max:10000'];
            $rules['accomplishments'] = ['required', 'array', 'max:30'];
            $evidenceKeys = array_keys(app(TerminalReportData::class)->evidence($this->route('topic')));
            $rules['cover_image'] = ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:10240'];
            $rules['reuse_cover_image'] = ['nullable', Rule::in($evidenceKeys)];
            $rules['cover_image_caption'] = ['nullable', 'string', 'max:200', 'required_with:cover_image,reuse_cover_image'];
            foreach (range(1, 30) as $index) {
                $rules['reuse_photo_'.$index] = ['nullable', Rule::in($evidenceKeys)];
                $rules['photo_after_paragraph_'.$index] = ['nullable', 'integer', 'min:0', 'max:1000'];
                $rules['photo_section_'.$index] = ['nullable', Rule::in(['methodology', 'results_discussion'])];
                $rules['photo_caption_'.$index] = ['nullable', 'string', 'max:200'];
                if (! false) {
                    $rules['photo_'.$index] = ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:10240'];
                    $rules['photo_caption_'.$index][] = 'required_with:photo_'.$index.',reuse_photo_'.$index;
                    $rules['photo_section_'.$index][] = 'required_with:photo_'.$index.',reuse_photo_'.$index;
                }
            }
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'terminal_data.total_expenditure.max' => 'The final total expenditure may not exceed the approved project budget.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('report_type') === 'progress' && $validator->errors()->isEmpty()) {
                $topic = $this->route('topic');
                $schedule = app(MonitoringQuarterService::class);
                if (! $schedule->canSubmitForDate($topic, $this->input('reporting_date'))) {
                    $validator->errors()->add('reporting_date', 'Choose an ended reporting quarter from the project schedule.');

                    return;
                }
                $period = $schedule->forDate($this->input('reporting_date'), $topic);
                if (CarbonImmutable::parse($this->input('submission_date'))->startOfDay()->lessThan($period['opens_at'])) {
                    $validator->errors()->add('submission_date', 'The submission date must be after the selected quarter ends.');
                }
                $existing = $topic->narrativeReports()->where('report_type', 'progress')->where('reporting_quarter', $period['quarter'])->latest('id')->first();
                if ($existing !== null && $existing->review_status !== ProjectNarrativeReport::STATUS_REVISION_REQUESTED) {
                    $validator->errors()->add('reporting_date', 'This quarter already has a prepared or submitted Progress Report. Open that report instead.');
                }
            }
            if ($this->input('report_type') !== 'terminal' || $validator->errors()->isNotEmpty()) {
                return;
            }
            $plain = app(TerminalReportData::class);
            foreach (['introduction', 'rationale', 'methodology', 'results_discussion', ...array_map(fn ($key) => 'terminal_data.'.$key, TerminalReportRules::NARRATIVES)] as $field) {
                if ($plain->plain((string) $this->input($field)) === '') {
                    $validator->errors()->add($field, 'Please enter substantive text for this section.');
                }
            }
            $authors = $this->input('terminal_data.authors', []);
            if (collect($authors)->where('role', 'Project Leader')->count() !== 1) {
                $validator->errors()->add('terminal_data.authors', 'Identify exactly one project leader.');
            }
            foreach ($this->input('terminal_data.tables', []) as $index => $table) {
                foreach ($table['rows'] ?? [] as $row) {
                    if (count($row) !== count($table['headers'] ?? [])) {
                        $validator->errors()->add('terminal_data.tables.'.$index, 'Every table row must have one cell per column.');
                        break;
                    }
                }
            }
        }];
    }
}
