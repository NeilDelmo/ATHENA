<?php

namespace App\Support;

use App\Models\ProposalSignatory;
use App\Models\TopicProposal;
use App\Services\MonitoringQuarterService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TerminalReportData
{
    public function defaults(TopicProposal $topic): array
    {
        $topic->loadMissing(['user', 'latestVersion.files', 'revisionDraft.members']);
        $files = $topic->latestVersion?->files ?? collect();
        $proposal = $files->firstWhere('document_type', 'detailed_proposal')?->source_data ?? [];
        $workPlan = $files->firstWhere('document_type', 'work_plan')?->source_data ?? [];
        $previous = $topic->narrativeReports()->where('report_type', 'progress')->where('review_status', '!=', 'revision_requested')->latest('id')->first();
        $monitoring = $topic->progressReports()
            ->submitted()
            ->whereDoesntHave('nextVersion', fn ($query) => $query->submitted())
            ->orderBy('period_start')
            ->orderBy('reporting_date')
            ->get();
        $authors = collect([[
            'name' => $proposal['project_leader_display'] ?? $proposal['project_leader'] ?? $topic->user->name,
            'role' => 'Project Leader',
        ]])->merge(collect($proposal['staff'] ?? [])->map(fn (array $staff): array => [
            'name' => $staff['display_name'] ?? $staff['name'] ?? '', 'role' => 'Project Staff',
        ]))->map(fn (array $author): array => [
            ...$author, 'rank' => '', 'campus' => $proposal['proponent_campus'] ?? '',
            'college' => $proposal['proponent_college'] ?? '', 'date_signed' => '',
        ])->all();
        if (count($authors) === 1 && $topic->revisionDraft?->members->isNotEmpty()) {
            foreach ($topic->revisionDraft->members as $member) {
                $authors[] = ['name' => $member->name, 'role' => 'Project Staff', 'rank' => '', 'campus' => '', 'college' => '', 'date_signed' => ''];
            }
        }
        $workPlanEntries = collect($workPlan['entries'] ?? [])
            ->filter(fn (mixed $entry): bool => is_array($entry))
            ->values()
            ->map(fn (array $entry, int $index): array => [
                ...$entry,
                'source_work_plan_index' => $index,
            ]);
        $accomplishments = $workPlanEntries->groupBy('objective')->map(function ($entries, string $objective) use ($previous, $monitoring): array {
            $prior = collect($previous?->accomplishments ?? [])->firstWhere('objective', $objective);
            $sourceIndexes = $entries->pluck('source_work_plan_index');
            $activities = $entries->pluck('activity')->filter()->map(
                fn (mixed $activity): string => trim((string) $activity),
            );
            $monitoringActuals = $monitoring->flatMap(function ($report) use ($objective, $sourceIndexes, $activities): array {
                return collect($report->work_plan ?? [])
                    ->filter(function (mixed $row) use ($objective, $sourceIndexes, $activities): bool {
                        if (! is_array($row)) {
                            return false;
                        }

                        return (isset($row['source_work_plan_index']) && $sourceIndexes->contains((int) $row['source_work_plan_index']))
                            || trim((string) ($row['objective'] ?? '')) === $objective
                            || $activities->contains(trim((string) ($row['activity'] ?? '')));
                    })
                    ->pluck('actual_accomplishment')
                    ->filter(fn (mixed $actual): bool => filled($actual))
                    ->map(fn (mixed $actual): string => $report->quarter_label.': '.trim((string) $actual))
                    ->all();
            })->unique()->values();

            return [
                'objective' => $objective,
                'target' => $entries->pluck('expected_output')->filter()->unique()->implode("\n"),
                'actual' => $monitoringActuals->isNotEmpty()
                    ? $monitoringActuals->implode("\n")
                    : ($prior['actual'] ?? ''),
            ];
        })->values();
        if ($accomplishments->isEmpty()) {
            $accomplishments = collect($proposal['specific_objectives'] ?? [])->map(fn (array $row): array => [
                'objective' => $this->plain($row['description'] ?? ''), 'target' => '', 'actual' => '',
            ]);
        }
        if ($accomplishments->isEmpty() && $previous !== null) {
            $accomplishments = collect($previous->accomplishments ?? []);
        }
        $approvedStart = $workPlan['planned_start'] ?? $topic->revisionDraft?->planned_start?->toDateString();
        $approvedEnd = $workPlan['planned_end'] ?? $topic->revisionDraft?->planned_end?->toDateString();
        $window = app(MonitoringQuarterService::class)->reportingWindow($topic);
        $approvedStart ??= $window['start']->toDateString();
        $approvedEnd ??= $window['end']->toDateString();
        $snapshot = [
            'project_title' => $proposal['project_title'] ?? $topic->title,
            'approved_start' => filled($approvedStart) ? Carbon::parse($approvedStart)->toDateString() : null,
            'approved_end' => filled($approvedEnd) ? Carbon::parse($approvedEnd)->toDateString() : null,
            'approved_duration_months' => $workPlan['total_duration_months'] ?? $topic->estimated_duration_months,
            'approved_budget' => $topic->latestVersion?->estimated_budget ?? $topic->estimated_budget,
            'template_reference' => 'BatStateU-REC-RES-04', 'template_revision' => '02', 'template_effectivity' => 'May 18, 2022',
            'source_proposal_version_id' => $topic->latestVersion?->id,
            'source_narrative_report_id' => $previous?->id,
            'source_monitoring_report_ids' => $monitoring->pluck('id')->all(),
        ];
        $defaults = [
            'missing_monitoring_periods' => app(MonitoringQuarterService::class)->missingTerminalMonitoringPeriods($topic),
            'monitoring_reference' => $monitoring->map(fn ($report): array => ['id' => $report->id, 'period' => $report->reporting_period_label, 'accomplishments' => $report->accomplishments, 'issues' => $report->issues, 'work_plan' => $report->work_plan, 'budget_utilization' => $report->budget_utilization])->all(),
            'objectives_from_work_plan' => $workPlanEntries->isNotEmpty(),
            'signatory_options' => ProposalSignatory::where('active', true)->orderBy('name')->pluck('name')->unique()->values()->all(),
            'report_type' => 'terminal', 'submission_date' => now()->toDateString(),
            'researchers' => collect($authors)->pluck('name')->implode("\n"),
            'implementation_start' => '',
            'implementation_end' => '',
            'funding_agency' => $previous?->funding_agency ?? '',
            'cover_image_caption' => 'Project poster for '.$snapshot['project_title'],
            'accomplishments' => $accomplishments->all(),
            'introduction' => $proposal['introduction'] ?? $previous?->introduction ?? '',
            'rationale' => $proposal['rationale'] ?? $previous?->rationale ?? '',
            'objectives' => $this->plain($proposal['general_objective'] ?? ''),
            'methodology' => $previous?->methodology ?? implode("\n\n", $proposal['methodology'] ?? []),
            'results_discussion' => $previous?->results_discussion ?? '',
            'terminal_data' => [
                ...$snapshot, 'authors' => $authors,
                'collaborating_agency' => $proposal['cooperating_agency'] ?? '',
                'total_expenditure' => '', 'abstract' => '',
                'literature_review' => $proposal['related_literature'] ?? '',
                'conclusions' => '', 'recommendations' => '', 'bibliography' => $proposal['references'] ?? '',
                'signatories' => collect(TerminalReportRules::SIGNATORY_ROLES)->map(fn () => ['name' => '', 'date_signed' => ''])->all(),
                'tables' => [],
            ],
        ];
        $lastTerminal = $topic->narrativeReports()->where('report_type', 'terminal')->latest('id')->first();
        if ($lastTerminal !== null && is_array($lastTerminal->terminal_data)) {
            foreach (['implementation_start', 'implementation_end'] as $field) {
                $defaults[$field] = $lastTerminal->$field?->toDateString();
            }
            foreach (['accomplishments', 'introduction', 'rationale', 'objectives', 'methodology', 'results_discussion'] as $field) {
                $defaults[$field] = $lastTerminal->$field;
            }
            foreach ([...TerminalReportRules::NARRATIVES, 'authors', 'signatories', 'tables', 'collaborating_agency', 'total_expenditure'] as $field) {
                $defaults['terminal_data'][$field] = $lastTerminal->terminal_data[$field] ?? $defaults['terminal_data'][$field];
            }
            foreach (['authors', 'signatories'] as $group) {
                foreach ($defaults['terminal_data'][$group] as &$signatory) {
                    $signatory['date_signed'] = '';
                }
                unset($signatory);
            }
            $figureIndex = 1;
            foreach ($lastTerminal->photos ?? [] as $index => $photo) {
                if (($photo['section'] ?? null) === 'cover') {
                    $defaults['reuse_cover_image'] = $lastTerminal->id.':'.$index;
                    $defaults['cover_image_caption'] = $photo['caption'] ?? $defaults['cover_image_caption'];

                    continue;
                }
                $defaults['reuse_photo_'.$figureIndex] = $lastTerminal->id.':'.$index;
                $defaults['photo_caption_'.$figureIndex] = $photo['caption'];
                $defaults['photo_section_'.$figureIndex] = $photo['section'];
                $defaults['photo_after_paragraph_'.$figureIndex] = $photo['after_paragraph'] ?? 0;
                $figureIndex++;
            }
            $defaults['terminal_data']['supersedes_report_id'] = $lastTerminal->id;
        }

        return $defaults;
    }

    public function normalize(TopicProposal $topic, array $data): array
    {
        if (($data['report_type'] ?? 'progress') !== 'terminal') {
            return $data;
        }
        $defaults = $this->defaults($topic);
        if ($defaults['objectives_from_work_plan']) {
            $submittedAccomplishments = collect($data['accomplishments'] ?? []);
            $data['accomplishments'] = collect($defaults['accomplishments'])
                ->map(function (array $approved, int $index) use ($submittedAccomplishments): array {
                    $submitted = $submittedAccomplishments->firstWhere('objective', $approved['objective'])
                        ?? $submittedAccomplishments->get($index, []);

                    return [
                        ...$approved,
                        'actual' => is_array($submitted)
                            ? trim((string) ($submitted['actual'] ?? $approved['actual']))
                            : $approved['actual'],
                    ];
                })
                ->all();
        }
        $data['terminal_data'] = [...$defaults['terminal_data'], ...($data['terminal_data'] ?? []), 'tables' => $data['terminal_data']['tables'] ?? []];
        $richText = new ProposalRichText;
        foreach (['introduction', 'rationale', 'methodology', 'results_discussion'] as $field) {
            $data[$field] = $richText->sanitize((string) ($data[$field] ?? ''));
        }
        foreach (TerminalReportRules::NARRATIVES as $field) {
            $data['terminal_data'][$field] = $richText->sanitize((string) ($data['terminal_data'][$field] ?? ''));
        }
        $data['researchers'] = collect($data['terminal_data']['authors'])->map(fn (array $author): string => implode(', ', array_filter([
            $author['name'] ?? '', $author['rank'] ?? '', $author['campus'] ?? '', $author['college'] ?? '',
        ])))->implode("\n");
        $data['objectives'] = $data['objectives'] ?? '';
        $data['funding_agency'] = $data['funding_agency'] ?? '';

        return $data;
    }

    public function evidence(TopicProposal $topic): array
    {
        $evidence = [];
        foreach ($topic->narrativeReports()->get() as $report) {
            foreach ($report->photos ?? [] as $index => $photo) {
                if (Storage::disk('local')->exists($photo['path'] ?? '')) {
                    $evidence[$report->id.':'.$index] = [...$photo, 'report_id' => $report->id, 'index' => $index,
                        'preview_url' => route('project-narrative-reports.photos.download', [$report, $index]),
                        'label' => $report->report_label.' '.$report->submission_date->format('M j, Y').' — '.($photo['caption'] ?? 'Figure')];
                }
            }
        }

        return $evidence;
    }

    public function photos(TopicProposal $topic, array $data, array $files, bool $preview, array &$storedPaths = []): array
    {
        $evidence = $this->evidence($topic);
        $photos = [];
        $coverFile = $files['cover_image'] ?? null;
        $coverSource = $evidence[$data['reuse_cover_image'] ?? ''] ?? null;
        if ($coverFile instanceof UploadedFile || $coverSource !== null) {
            $cover = [
                'caption' => $data['cover_image_caption'] ?? 'Project poster',
                'section' => 'cover',
                'after_paragraph' => 0,
            ];
            if ($preview) {
                $photos[] = $coverFile instanceof UploadedFile
                    ? [...$cover, 'preview_file_input' => 'cover_image']
                    : [...$cover, 'preview_url' => $coverSource['preview_url']];
            } elseif ($coverFile instanceof UploadedFile) {
                $path = $coverFile->store('narrative-progress-reports/'.$topic->id, 'local');
                $storedPaths[] = $path;
                $photos[] = [...$cover, 'path' => $path, 'original_name' => $coverFile->getClientOriginalName(), 'mime_type' => $coverFile->getMimeType(), 'size' => $coverFile->getSize()];
            } else {
                $path = 'narrative-progress-reports/'.$topic->id.'/'.Str::uuid().'.'.pathinfo($coverSource['path'], PATHINFO_EXTENSION);
                if (! Storage::disk('local')->copy($coverSource['path'], $path)) {
                    throw new \RuntimeException('The selected cover image could not be copied.');
                }
                $storedPaths[] = $path;
                $photos[] = [...$cover, 'path' => $path, 'original_name' => $coverSource['original_name'], 'mime_type' => $coverSource['mime_type'], 'size' => $coverSource['size'], 'source_report_id' => $coverSource['report_id'], 'source_photo_index' => $coverSource['index']];
            }
        }
        foreach (range(1, 30) as $index) {
            $file = $files['photo_'.$index] ?? null;
            $source = $evidence[$data['reuse_photo_'.$index] ?? ''] ?? null;
            if (! $file instanceof UploadedFile && $source === null) {
                continue;
            }
            $photo = [
                'caption' => $data['photo_caption_'.$index] ?? '',
                'section' => $data['photo_section_'.$index] ?? 'results_discussion',
                'after_paragraph' => (int) ($data['photo_after_paragraph_'.$index] ?? 0),
            ];
            if ($preview) {
                $photos[] = $file instanceof UploadedFile
                    ? [...$photo, 'preview_file_input' => 'photo_'.$index]
                    : [...$photo, 'preview_url' => route('project-narrative-reports.photos.download', [$source['report_id'], $source['index']])];

                continue;
            }
            if ($file instanceof UploadedFile) {
                $path = $file->store('narrative-progress-reports/'.$topic->id, 'local');
                $photo = [...$photo, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size' => $file->getSize()];
            } else {
                $path = 'narrative-progress-reports/'.$topic->id.'/'.Str::uuid().'.'.pathinfo($source['path'], PATHINFO_EXTENSION);
                if (! Storage::disk('local')->copy($source['path'], $path)) {
                    throw new \RuntimeException('The selected report evidence could not be copied.');
                }
                $photo = [...$photo, 'original_name' => $source['original_name'], 'mime_type' => $source['mime_type'], 'size' => $source['size'], 'source_report_id' => $source['report_id'], 'source_photo_index' => $source['index']];
            }
            $storedPaths[] = $path;
            $photos[] = [...$photo, 'path' => $path];
        }

        return $photos;
    }

    public function plain(string $value): string
    {
        return trim(html_entity_decode(strip_tags(preg_replace('/<(?:br\s*\/?>|\/p|\/li)>/iu', "\n", $value) ?? $value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }
}
