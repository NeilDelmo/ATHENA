<?php

namespace App\Support;

use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Services\MonitoringQuarterService;
use Illuminate\Http\UploadedFile;

class ProgressReportData
{
    public function __construct(
        private readonly MonitoringQuarterService $schedule,
        private readonly ProposalRichText $richText,
    ) {}

    /** @return array<string, mixed> */
    public function defaults(TopicProposal $topic): array
    {
        $topic->loadMissing(['user', 'latestVersion.files', 'revisionDraft.members']);
        $files = $topic->latestVersion?->files ?? collect();
        $proposal = $files->firstWhere('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL)?->source_data ?? [];
        $workPlan = $files->firstWhere('document_type', ProposalVersionFile::TYPE_WORK_PLAN)?->source_data ?? [];
        $entries = collect($workPlan['entries'] ?? [])
            ->filter(fn (mixed $entry): bool => is_array($entry) && filled($entry['objective'] ?? null));
        $accomplishments = $entries->groupBy(fn (array $entry): string => $this->plain((string) $entry['objective']))
            ->map(fn ($rows, string $objective): array => [
                'objective' => $objective,
                'target' => $rows->map(fn (array $row): string => $this->plain((string) ($row['expected_output'] ?? '')))->filter()->unique()->implode("\n"),
                'activities' => $rows->map(fn (array $row): string => $this->plain((string) ($row['activity'] ?? '')))->filter()->unique()->implode("\n"),
                'actual' => '',
            ])->values();
        $specificObjectives = collect($proposal['specific_objectives'] ?? [])
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(fn (array $row): string => $this->plain((string) ($row['description'] ?? '')))
            ->filter()->values();
        if ($accomplishments->isEmpty()) {
            $accomplishments = $specificObjectives->map(fn (string $objective): array => [
                'objective' => $objective, 'target' => '', 'actual' => '', 'activities' => '',
            ]);
        }
        $objectives = collect([$this->plain((string) ($proposal['general_objective'] ?? ''))])
            ->merge($specificObjectives->isNotEmpty() ? $specificObjectives : $accomplishments->pluck('objective'))
            ->filter()->unique()->implode("\n\n");
        $methodology = $proposal['methodology'] ?? [];
        $methods = is_array($methodology)
            ? collect(config('detailed_proposal.methodology'))->map(function (string $label, string $key) use ($methodology): string {
                $narrative = $this->plain((string) ($methodology[$key] ?? ''));

                return $narrative === '' ? '' : $label."\n".$narrative;
            })->filter()->implode("\n\n")
            : $this->plain((string) $methodology);
        $window = $this->schedule->reportingWindow($topic);
        $researchers = collect([$proposal['project_leader_display'] ?? $proposal['project_leader'] ?? $topic->revisionDraft?->project_leader ?? $topic->user->name])
            ->merge(collect($proposal['staff'] ?? [])->filter(fn (mixed $row): bool => is_array($row))->map(fn (array $staff): string => $staff['display_name'] ?? $staff['name'] ?? ''))
            ->merge(empty($proposal['staff']) ? ($topic->revisionDraft?->members?->pluck('name') ?? []) : [])
            ->filter()->unique()->implode("\n");

        return [
            'source_proposal_version' => $topic->latestVersion?->version_number,
            'objectives_from_work_plan' => $entries->isNotEmpty(),
            'researchers' => $researchers,
            'implementation_start' => $workPlan['planned_start'] ?? $window['start']->toDateString(),
            'implementation_end' => $workPlan['planned_end'] ?? $window['end']->toDateString(),
            'funding_agency' => 'Batangas State University',
            'accomplishments' => $accomplishments->all(),
            'introduction' => $this->plain((string) ($proposal['introduction'] ?? '')),
            'rationale' => $this->plain((string) ($proposal['rationale'] ?? '')),
            'objectives' => $objectives,
            'methodology' => $methods,
            'results_discussion' => '',
        ];
    }

    public function plain(string $value): string
    {
        return collect($this->richText->blocks($value))->map(fn (array $block): string => collect($block['runs'])
            ->map(fn (array $run): string => $run['break'] ? "\n" : $run['text'])
            ->implode(''))->implode("\n\n");
    }

    /** @return list<int> */
    public function legacyFigureIndexes(array $data): array
    {
        return collect(array_keys($data))->map(function (int|string $key): ?int {
            return preg_match('/^photo_(?:(?:caption|section|after_paragraph)_)?(\d+)$/', $key, $matches) === 1 ? (int) $matches[1] : null;
        })->filter(fn (?int $index): bool => $index !== null)->unique()->sort()->values()->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $files
     * @param  list<string>  $storedPaths
     * @return list<array<string, mixed>>
     */
    public function photos(TopicProposal $topic, array $data, array $files, bool $preview, array &$storedPaths = []): array
    {
        $figures = collect($data['figures'] ?? [])->map(fn (array $row, int|string $index): array => [
            ...$row, 'file' => $files['figures'][$index]['image'] ?? null,
            'preview_file_input' => 'figures['.$index.'][image]',
        ])->values();
        foreach ($this->legacyFigureIndexes($data) as $index) {
            $figures->push([
                'file' => $files['photo_'.$index] ?? null,
                'preview_file_input' => 'photo_'.$index,
                'caption' => $data['photo_caption_'.$index] ?? '',
                'section' => $data['photo_section_'.$index] ?? 'results_discussion',
                'after_paragraph' => $data['photo_after_paragraph_'.$index] ?? 0,
            ]);
        }

        return $figures->filter(fn (array $row): bool => ($row['file'] ?? null) instanceof UploadedFile)
            ->map(function (array $row) use ($topic, $preview, &$storedPaths): array {
                $photo = [
                    'caption' => $row['caption'], 'section' => $row['section'],
                    'after_paragraph' => (int) ($row['after_paragraph'] ?? 0),
                ];
                if ($preview) {
                    return [...$photo, 'preview_file_input' => $row['preview_file_input']];
                }
                $file = $row['file'];
                $path = $file->store('narrative-progress-reports/'.$topic->id, 'local');
                $storedPaths[] = $path;

                return [...$photo, 'path' => $path, 'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(), 'size' => $file->getSize()];
            })->values()->all();
    }
}
