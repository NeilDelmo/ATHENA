<?php

namespace App\Services;

use App\Models\ProjectMonitoringDraft;
use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Throwable;

class MonitoringEvidenceService
{
    /** @var array<int, list<array<string, mixed>>> */
    private array $reports = [];

    /** @return array<string, array<string, mixed>> */
    public function previousRows(TopicProposal $topic, string $reportingDate): array
    {
        if (! app(ApprovedWorkPlanMonitoringService::class)->hasApprovedWorkPlan($topic)) {
            return [];
        }
        $start = app(MonitoringQuarterService::class)->forDate($reportingDate, $topic)['start'];
        $this->reports[$topic->id] ??= $topic->progressReports()->orderByDesc('version_number')->orderByDesc('id')->get(['id', 'reporting_date', 'work_plan'])->map(fn ($report): array => ['reporting_date' => $report->reporting_date->toDateString(), 'work_plan' => $report->work_plan])->all();
        $rows = [];
        foreach ($this->reports[$topic->id] as $report) {
            if (substr($report['reporting_date'], 0, 10) >= $start->toDateString()) {
                continue;
            }
            foreach ($report['work_plan'] ?? [] as $index => $row) {
                if (isset($row['source_work_plan_index'])) {
                    $rows[$this->activityKey($row, $index)] ??= $row;
                }
            }
        }

        return $rows;
    }

    /** @param list<array<string, mixed>> $rows @return list<array<string, mixed>> */
    public function initialRows(TopicProposal $topic, string $date, array $rows): array
    {
        $previous = $this->previousRows($topic, $date);

        return array_map(function (array $row, int $index) use ($previous): array {
            if (! array_key_exists('evidence', $row)) {
                $row = [...$row, ...array_intersect_key($previous[$this->activityKey($row, $index)] ?? [], array_flip(['completed_units', 'evidence']))];
            }

            return [...$row, ...$this->target($row['physical_target'] ?? '')];
        }, $rows, array_keys($rows));
    }

    /** @param list<array<string, mixed>> $rows */
    public function overallProgress(TopicProposal $topic, string $date, array $rows): int
    {
        if (! collect($rows)->contains(fn (array $row): bool => array_key_exists('evidence', $row))) {
            return (int) round(array_sum(array_column($rows, 'accomplished_percentage')));
        }
        $combined = $this->previousRows($topic, $date);
        foreach ($rows as $index => $row) {
            $combined[$this->activityKey($row, $index)] = $row;
        }

        return (int) round(min(100, array_sum(array_column($combined, 'accomplished_percentage'))));
    }

    /** @return array{target_units: int, progress_unit: string} */
    public function target(string $description): array
    {
        preg_match_all('/(?<![\d.,])([1-9]\d{0,5})\s+(interviews?|responses?|respondents?|participants?|sessions?|workshops?|surveys?|reports?|datasets?|prototypes?|modules?|samples?|records?|outputs?)\b/i', $description, $matches, PREG_SET_ORDER);

        if (count($matches) === 1) {
            return ['target_units' => (int) $matches[0][1], 'progress_unit' => strtolower($matches[0][2])];
        }

        return ['target_units' => 1, 'progress_unit' => 'completed output'];
    }

    public function prepareRequest(FormRequest $request, TopicProposal $topic): void
    {
        $savedRows = $this->savedRows($request, $topic);
        $rows = $request->input('work_plan', []);
        $hasEvidenceInput = collect(is_array($rows) ? $rows : [])->contains(fn (mixed $row): bool => is_array($row) && (array_key_exists('evidence', $row) || array_key_exists('evidence_ids', $row) || array_key_exists('completed_units', $row)))
            || array_key_exists('activity_evidence', $request->all());
        if ($request->input('progress_mode') !== 'evidence'
            && ! $hasEvidenceInput
            && ! app(ApprovedWorkPlanMonitoringService::class)->hasApprovedWorkPlan($topic)
            && ! collect($savedRows)->contains(fn (array $row): bool => array_key_exists('evidence', $row))) {
            return;
        }
        if (! is_array($rows)) {
            return;
        }
        foreach ($rows as $index => &$row) {
            if (! is_array($row)) {
                continue;
            }
            $key = $this->activityKey($row, $index);
            $available = $savedRows[$key]['evidence'] ?? [];
            $ids = is_array($row['evidence_ids'] ?? null) ? $row['evidence_ids'] : [];
            $row['evidence'] = array_values(array_filter($available, fn (array $file): bool => in_array($file['id'], $ids, true) && Storage::disk('local')->exists($file['path'])));
            $row = [...$row, ...$this->target(is_string($row['physical_target'] ?? null) ? $row['physical_target'] : '')];
            $hasEvidence = $row['evidence'] !== [] || $this->uploads($this->requestUploads($request), $key) !== [];
            $row['completed_units'] = $row['target_units'] === 1 ? ($hasEvidence ? 1 : 0) : ($row['completed_units'] ?? 0);
            $row['accomplished_percentage'] = $this->contribution($row, $hasEvidence);
        }
        unset($row);
        $request->merge(['progress_mode' => 'evidence', 'work_plan' => $rows]);
    }

    /** @return array<string, array<mixed>> */
    public function rules(FormRequest $request): array
    {
        if ($request->input('progress_mode') !== 'evidence') {
            return ['progress_mode' => ['nullable', Rule::in(['evidence'])]];
        }
        $rules = [
            'progress_mode' => ['required', Rule::in(['evidence'])],
            'work_plan.*.target_units' => ['required', 'integer', 'min:1'],
            'work_plan.*.activity_id' => ['nullable', 'uuid'],
            'work_plan.*.progress_unit' => ['required', 'string'],
            'work_plan.*.completed_units' => ['required', 'integer', 'min:0'],
            'work_plan.*.evidence' => ['array'],
            'work_plan.*.evidence.*' => ['array:id,path,name,mime_type,size,checksum'],
            'work_plan.*.evidence.*.id' => ['required', 'uuid'],
            'work_plan.*.evidence.*.path' => ['required', 'string'],
            'work_plan.*.evidence.*.name' => ['required', 'string'],
            'work_plan.*.evidence.*.mime_type' => ['required', 'string'],
            'work_plan.*.evidence.*.size' => ['required', 'integer'],
            'work_plan.*.evidence.*.checksum' => ['required', 'string'],
            'work_plan.*.evidence_ids' => ['nullable', 'array', 'max:5'],
            'activity_evidence' => ['nullable', 'array', 'max:11'],
            'activity_evidence.*' => ['array', 'max:5'],
            'activity_evidence.*.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:10240'],
        ];
        $savedRows = $this->savedRows($request, $request->route('topic'));
        foreach (is_array($request->input('work_plan')) ? $request->input('work_plan') : [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $ids = array_column($savedRows[$this->activityKey($row, $index)]['evidence'] ?? [], 'id');
            $rules["work_plan.{$index}.evidence_ids.*"] = ['uuid', 'distinct', Rule::in($ids)];
            $rules["work_plan.{$index}.completed_units"] = ['required', 'integer', 'min:0', 'max:'.($row['target_units'] ?? 1)];
        }

        return $rules;
    }

    public function validateEvidence(FormRequest $request, Validator $validator): void
    {
        if ($request->input('progress_mode') !== 'evidence') {
            return;
        }
        $keys = [];
        foreach (is_array($request->input('work_plan')) ? $request->input('work_plan') : [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = $this->activityKey($row, $index);
            $keys[] = $key;
            if (count($row['evidence'] ?? []) + count($this->uploads($this->requestUploads($request), $key)) > 5) {
                $validator->errors()->add("activity_evidence.{$key}", 'Keep up to five evidence files for each activity.');
            }
        }
        foreach (array_keys($this->requestUploads($request)) as $key) {
            if (! in_array((string) $key, $keys, true)) {
                $validator->errors()->add('activity_evidence', 'Attach evidence to an activity in this reporting period.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string|int, mixed>  $uploads
     */
    public function store(TopicProposal $topic, array $validated, array $uploads, Closure $save): mixed
    {
        $storedPaths = [];
        $rows = $validated['work_plan'] ?? [];
        try {
            foreach ($rows as $index => &$row) {
                if (($validated['progress_mode'] ?? null) !== 'evidence') {
                    continue;
                }
                foreach ($this->uploads($uploads, $this->activityKey($row, $index)) as $file) {
                    $checksum = hash_file('sha256', $file->getRealPath());
                    if (in_array($checksum, array_column($row['evidence'], 'checksum'), true)) {
                        continue;
                    }
                    $path = $file->store('progress-reports/'.$topic->id.'/evidence', 'local');
                    if (! is_string($path)) {
                        throw new \RuntimeException('The evidence file could not be stored.');
                    }
                    $storedPaths[] = $path;
                    $row['evidence'][] = [
                        'id' => (string) Str::uuid(), 'path' => $path,
                        'name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(), 'checksum' => $checksum,
                    ];
                }
                unset($row['evidence_ids']);
                $row['accomplished_percentage'] = $this->contribution($row, $row['evidence'] !== []);
            }
            unset($row, $validated['activity_evidence'], $validated['progress_mode']);
            $validated['work_plan'] = $rows;

            return $save($validated);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }
    }

    /** @param array<string, mixed> $row */
    public function contribution(array $row, bool $hasEvidence): string
    {
        $fraction = $hasEvidence && is_numeric($row['completed_units'] ?? null)
            ? min(1, max(0, (float) $row['completed_units'] / max(1, (int) $row['target_units'])))
            : 0;

        $weightInHundredths = (int) round((float) ($row['percent_weight'] ?? 0) * 100);

        return number_format(round($weightInHundredths * $fraction) / 100, 2, '.', '');
    }

    /** @param array<string, mixed> $row */
    public function activityKey(array $row, int|string $index): string
    {
        return isset($row['source_work_plan_index']) && is_scalar($row['source_work_plan_index'])
            ? 'plan-'.$row['source_work_plan_index']
            : 'row-'.(is_string($row['activity_id'] ?? null) && $row['activity_id'] !== '' ? $row['activity_id'] : $index);
    }

    /** @param list<string> $paths */
    public function deleteUnreferenced(TopicProposal $topic, array $paths): void
    {
        if ($paths === []) {
            return;
        }
        $referenced = [];
        $sources = ProjectMonitoringDraft::query()->whereBelongsTo($topic, 'topic')->get(['source_data'])
            ->map(fn ($draft): array => $draft->source_data['work_plan'] ?? [])
            ->concat(ProjectProgressReport::query()->whereBelongsTo($topic, 'topic')->pluck('work_plan'));
        foreach ($sources as $rows) {
            foreach ($rows ?? [] as $row) {
                array_push($referenced, ...array_column($row['evidence'] ?? [], 'path'));
            }
        }
        $unused = array_values(array_filter($paths, fn (string $path): bool => str_starts_with($path, 'progress-reports/'.$topic->id.'/evidence/') && ! in_array($path, $referenced, true)));
        Storage::disk('local')->delete($unused);
    }

    /** @param array<string|int, mixed> $files @return list<UploadedFile> */
    private function uploads(array $files, string $key): array
    {
        return array_values(array_filter(is_array($files[$key] ?? null) ? $files[$key] : [], fn (mixed $file): bool => $file instanceof UploadedFile && $file->isValid()));
    }

    /** @return array<string|int, mixed> */
    private function requestUploads(FormRequest $request): array
    {
        $files = $request->file('activity_evidence', []);

        return is_array($files) ? $files : [];
    }

    /** @return array<string, array<string, mixed>> */
    private function savedRows(FormRequest $request, TopicProposal $topic): array
    {
        if (! $request->user()) {
            return [];
        }
        if ($request->attributes->has('monitoring_evidence_rows')) {
            return $request->attributes->get('monitoring_evidence_rows');
        }
        $source = $request->integer('source_report_id') > 0 ? $topic->progressReports()->whereKey($request->integer('source_report_id'))->where('review_status', 'revision_requested')->first() : null;
        $draft = ProjectMonitoringDraft::query()->whereBelongsTo($topic, 'topic')->whereBelongsTo($request->user(), 'user')->forSource($source)->first();
        $rows = [];
        $savedDate = data_get($draft?->source_data, 'reporting_date');
        $date = $request->input('reporting_date');
        $parsedDate = is_string($date) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
        $validDate = $parsedDate && $parsedDate->format('Y-m-d') === $date;
        $samePeriod = $savedDate && $validDate && app(MonitoringQuarterService::class)->forDate($savedDate, $topic)['start']->eq(app(MonitoringQuarterService::class)->forDate($date, $topic)['start']);
        foreach ([$validDate ? array_values($this->previousRows($topic, $date)) : [], $source?->work_plan ?? [], $samePeriod ? data_get($draft?->source_data, 'work_plan', []) : []] as $saved) {
            foreach ($saved as $index => $row) {
                $rows[$this->activityKey($row, $index)] = $row;
            }
        }
        $request->attributes->set('monitoring_evidence_rows', $rows);

        return $rows;
    }
}
