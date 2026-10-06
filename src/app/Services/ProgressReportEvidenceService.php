<?php

namespace App\Services;

use App\Models\ProjectNarrativeReport;
use App\Models\ProjectNarrativeReportDraft;
use App\Models\TopicProposal;
use Closure;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use RuntimeException;
use Throwable;

class ProgressReportEvidenceService
{
    public function __construct(private readonly MonitoringQuarterService $schedule) {}

    public function prepareRequest(FormRequest $request, TopicProposal $topic): void
    {
        $rows = $request->input('accomplishments', []);
        if (! is_array($rows)) {
            return;
        }
        $savedRows = $this->savedRows($request, $topic);
        foreach ($rows as $index => &$row) {
            if (! is_array($row)) {
                continue;
            }
            if ($request->input('report_type', 'progress') !== 'progress') {
                unset($row['evidence'], $row['evidence_ids'], $row['evidence_key']);

                continue;
            }
            $objective = is_string($row['objective'] ?? null) ? $row['objective'] : '';
            $row['evidence_key'] ??= 'objective-'.substr(hash('sha256', $objective."\0".$index), 0, 24);
            $available = is_string($row['evidence_key']) ? ($savedRows[$row['evidence_key']]['evidence'] ?? []) : [];
            $ids = is_array($row['evidence_ids'] ?? null) ? $row['evidence_ids'] : [];
            $row['evidence'] = array_values(array_filter($available, fn (array $file): bool => in_array($file['id'], $ids, true)
                && $this->isStoredEvidence($topic, $file)));
        }
        unset($row);
        $request->merge(['accomplishments' => $rows]);
    }

    /** @return array<string, array<mixed>> */
    public function rules(FormRequest $request): array
    {
        if ($request->input('report_type', 'progress') !== 'progress') {
            return ['accomplishment_evidence' => ['prohibited']];
        }
        $rules = [
            'accomplishments.*.evidence_key' => ['required', 'string', 'distinct', 'regex:/^(?:objective-[a-f0-9]{24}|row-[a-f0-9-]{36})$/'],
            'accomplishments.*.evidence_ids' => ['nullable', 'array', 'max:5'],
            'accomplishments.*.evidence' => ['array', 'max:5'],
            'accomplishments.*.evidence.*' => ['array:id,path,name,mime_type,size,checksum'],
            'accomplishments.*.evidence.*.id' => ['required', 'uuid'],
            'accomplishments.*.evidence.*.path' => ['required', 'string'],
            'accomplishments.*.evidence.*.name' => ['required', 'string'],
            'accomplishments.*.evidence.*.mime_type' => ['required', 'string'],
            'accomplishments.*.evidence.*.size' => ['required', 'integer'],
            'accomplishments.*.evidence.*.checksum' => ['required', 'string'],
            'accomplishment_evidence' => ['nullable', 'array', 'max:1000'],
            'accomplishment_evidence.*' => ['array', 'max:5'],
            'accomplishment_evidence.*.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:10240'],
        ];
        $savedRows = $this->savedRows($request, $request->route('topic'));
        foreach (is_array($request->input('accomplishments')) ? $request->input('accomplishments') : [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $available = is_string($row['evidence_key'] ?? null) ? ($savedRows[$row['evidence_key']]['evidence'] ?? []) : [];
            $rules["accomplishments.{$index}.evidence_ids.*"] = ['uuid', 'distinct', Rule::in(array_column($available, 'id'))];
        }

        return $rules;
    }

    public function validateEvidence(FormRequest $request, Validator $validator): void
    {
        if ($request->input('report_type', 'progress') !== 'progress') {
            return;
        }
        $keys = [];
        $uploads = $this->requestUploads($request);
        $hasRetainedEvidence = collect(is_array($request->input('accomplishments')) ? $request->input('accomplishments') : [])
            ->contains(fn (mixed $row): bool => is_array($row) && ! empty($row['evidence_ids']));
        if (($uploads !== [] || $hasRetainedEvidence) && blank($request->input('reporting_date'))) {
            $validator->errors()->add('reporting_date', 'Choose a reporting quarter before adding evidence.');
        }
        foreach (is_array($request->input('accomplishments')) ? $request->input('accomplishments') : [] as $index => $row) {
            if (! is_array($row) || ! is_string($row['evidence_key'] ?? null)) {
                continue;
            }
            $key = $row['evidence_key'];
            $keys[] = $key;
            if (count($row['evidence'] ?? []) + count($this->uploads($uploads, $key)) > 5) {
                $validator->errors()->add("accomplishment_evidence.{$key}", 'Keep up to five evidence files for each objective.');
            }
        }
        foreach (array_keys($uploads) as $key) {
            if (! in_array((string) $key, $keys, true)) {
                $validator->errors()->add('accomplishment_evidence', 'Attach evidence to an objective in this report.');
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
        try {
            if (($validated['report_type'] ?? 'progress') === 'progress') {
                foreach ($validated['accomplishments'] ?? [] as $index => $row) {
                    $row['evidence'] ??= [];
                    foreach ($this->uploads($uploads, $row['evidence_key']) as $file) {
                        $checksum = hash_file('sha256', $file->getRealPath());
                        if (in_array($checksum, array_column($row['evidence'], 'checksum'), true)) {
                            continue;
                        }
                        $path = $file->store('narrative-progress-reports/'.$topic->id.'/evidence', 'local');
                        if (! is_string($path)) {
                            throw new RuntimeException('The evidence file could not be stored.');
                        }
                        $storedPaths[] = $path;
                        $row['evidence'][] = [
                            'id' => (string) Str::uuid(), 'path' => $path,
                            'name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(),
                            'size' => $file->getSize(), 'checksum' => $checksum,
                        ];
                    }
                    unset($row['evidence_ids']);
                    $validated['accomplishments'][$index] = $row;
                }
            }
            unset($validated['accomplishment_evidence']);

            return $save($validated);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);

            throw $exception;
        }
    }

    /** @param list<array<string, mixed>> $rows @return list<string> */
    public function paths(array $rows): array
    {
        return collect($rows)->flatMap(fn (array $row): array => array_column($row['evidence'] ?? [], 'path'))->unique()->values()->all();
    }

    /** @param list<string> $paths */
    public function deleteUnreferenced(TopicProposal $topic, array $paths): void
    {
        if ($paths === []) {
            return;
        }
        $referenced = ProjectNarrativeReportDraft::query()->whereBelongsTo($topic, 'topic')->get(['source_data'])
            ->map(fn ($draft): array => $draft->source_data['accomplishments'] ?? [])
            ->concat(ProjectNarrativeReport::query()->whereBelongsTo($topic, 'topic')->pluck('accomplishments'))
            ->flatMap(fn (?array $rows): array => $this->paths($rows ?? []))->all();
        $unused = array_values(array_filter($paths, fn (string $path): bool => $this->hasEvidencePath($topic, $path) && ! in_array($path, $referenced, true)));
        Storage::disk('local')->delete($unused);
    }

    /** @param array<string, mixed> $file */
    public function isStoredEvidence(TopicProposal $topic, array $file): bool
    {
        return is_string($file['path'] ?? null) && $this->hasEvidencePath($topic, $file['path']) && Storage::disk('local')->exists($file['path']);
    }

    private function hasEvidencePath(TopicProposal $topic, string $path): bool
    {
        return str_starts_with($path, 'narrative-progress-reports/'.$topic->id.'/evidence/') && ! str_contains($path, '..');
    }

    /** @param array<string|int, mixed> $files @return list<UploadedFile> */
    private function uploads(array $files, string $key): array
    {
        return array_values(array_filter(is_array($files[$key] ?? null) ? $files[$key] : [], fn (mixed $file): bool => $file instanceof UploadedFile && $file->isValid()));
    }

    /** @return array<string|int, mixed> */
    private function requestUploads(FormRequest $request): array
    {
        $files = $request->file('accomplishment_evidence', []);

        return is_array($files) ? $files : [];
    }

    /** @return array<string, array<string, mixed>> */
    private function savedRows(FormRequest $request, TopicProposal $topic): array
    {
        if ($request->attributes->has('progress_report_evidence_rows')) {
            return $request->attributes->get('progress_report_evidence_rows');
        }
        $rows = [];
        $draft = $request->user() && $request->input('report_type', 'progress') === 'progress'
            ? ProjectNarrativeReportDraft::query()->whereBelongsTo($topic, 'topic')->whereBelongsTo($request->user(), 'user')->where('report_type', 'progress')->first()
            : null;
        $paths = $this->paths(data_get($draft?->source_data, 'accomplishments', []) ?? []);
        $date = $request->input('reporting_date');
        $parsed = is_string($date) ? DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
        if ($request->user() && $parsed && $parsed->format('Y-m-d') === $date && $request->input('report_type', 'progress') === 'progress') {
            $period = $this->schedule->forDate($date, $topic);
            $source = $topic->narrativeReports()->submitted()->where('report_type', 'progress')->where('reporting_quarter', $period['quarter'])
                ->where('review_status', ProjectNarrativeReport::STATUS_REVISION_REQUESTED)->latest('id')->first();
            $paths = array_unique([...$paths, ...$this->paths($source?->accomplishments ?? [])]);
            $savedDate = data_get($draft?->source_data, 'reporting_date');
            $samePeriod = $savedDate && $this->schedule->forDate($savedDate, $topic)['start']->eq($period['start']);
            foreach ([$source?->accomplishments ?? [], $samePeriod ? (data_get($draft?->source_data, 'accomplishments', []) ?? []) : []] as $saved) {
                foreach ($saved as $row) {
                    if (is_string($row['evidence_key'] ?? null)) {
                        $rows[$row['evidence_key']] = $row;
                    }
                }
            }
        }
        $request->attributes->set('progress_report_evidence_rows', $rows);
        $request->attributes->set('progress_report_evidence_paths', $paths);

        return $rows;
    }
}
