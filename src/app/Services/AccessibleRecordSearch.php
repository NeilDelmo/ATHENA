<?php

namespace App\Services;

use App\Models\ProjectDocument;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Models\ResearchManual;
use App\Models\TopicProposal;
use App\Models\User;
use App\Support\ProposalPaperCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use RuntimeException;

class AccessibleRecordSearch
{
    public const CATEGORIES = [
        'all' => 'Everything',
        'projects' => 'Projects & proposals',
        'reports' => 'Reports',
        'files' => 'Files',
        'manuals' => 'Research manuals',
    ];

    private const TOPIC_COLUMNS = 'id,user_id,research_manual_id,title,status,project_status,notice_to_proceed_issued_at,research_secretary_id,review_stage';

    private const DRAFT_COLUMNS = 'id,user_id,research_manual_id,project_title,deleted_at';

    private const VERSION_COLUMNS = 'id,topic_id,title,version_number';

    private const SOURCES = [
        'topic' => [TopicProposal::class, 'projects', ['user:id,college', 'researchManual:id,title,edition,version']],
        'draft' => [ProposalDraft::class, 'projects', ['researchManual:id,title,edition,version']],
        'draft_file' => [ProposalDraftDocument::class, 'files', ['draft:'.self::DRAFT_COLUMNS, 'draft.researchManual:id,title,edition,version']],
        'version' => [ProposalVersion::class, 'projects', ['topic:'.self::TOPIC_COLUMNS, 'topic.user:id,college', 'topic.researchManual:id,title,edition,version']],
        'version_file' => [ProposalVersionFile::class, 'files', ['version:'.self::VERSION_COLUMNS, 'version.topic:'.self::TOPIC_COLUMNS, 'version.topic.user:id,college', 'version.topic.researchManual:id,title,edition,version']],
        'document' => [ProjectDocument::class, 'files', ['topic:'.self::TOPIC_COLUMNS, 'topic.user:id,college', 'topic.researchManual:id,title,edition,version']],
        'monitoring' => [ProjectProgressReport::class, 'reports', ['topic:'.self::TOPIC_COLUMNS, 'topic.user:id,college', 'topic.researchManual:id,title,edition,version']],
        'narrative' => [ProjectNarrativeReport::class, 'reports', ['topic:'.self::TOPIC_COLUMNS, 'topic.user:id,college', 'topic.researchManual:id,title,edition,version']],
        'manual' => [ResearchManual::class, 'manuals', []],
    ];

    public function __construct(private ResearchManualLibrary $manualLibrary, private ProposalPaperCatalog $paperCatalog) {}

    public function search(User $user, string $term, string $category = 'all'): LengthAwarePaginator
    {
        $union = null;
        foreach ($this->queries($user, $term) as $kind => $query) {
            if ($category !== 'all' && self::SOURCES[$kind][1] !== $category) {
                continue;
            }

            $query->select('id', 'updated_at')->selectRaw('? as kind', [$kind]);
            if ($union === null) {
                $union = $query->toBase();
            } else {
                $union->unionAll($query->toBase());
            }
        }

        $legacy = in_array($category, ['all', 'manuals'], true) ? $this->legacyManual($term) : null;
        if ($legacy !== null) {
            $union->unionAll(DB::query()->selectRaw('0 as id, ? as updated_at, ? as kind', [null, 'legacy_manual']));
        }

        // shortcut: database substring search; add a dedicated index when measured search latency warrants it.
        $results = DB::query()->fromSub($union, 'matches')->orderByDesc('updated_at')->orderBy('kind')->orderByDesc('id')->paginate(20);
        $records = [];
        $needsCollege = $user->isUsingWorkspace([User::WORKSPACE_RESEARCH_OFFICE, User::WORKSPACE_RESEARCH_SECRETARY]);
        foreach ($results->getCollection()->groupBy('kind') as $kind => $matches) {
            if ($kind === 'legacy_manual') {
                continue;
            }
            [$model, , $relations] = self::SOURCES[$kind];
            $topicRelation = match ($kind) {
                'version_file' => 'version.topic',
                'version', 'document', 'monitoring', 'narrative' => 'topic',
                default => null,
            };
            $records[$kind] = $model::query()->with($relations)->whereKey($matches->pluck('id'))
                ->when($needsCollege && $kind === 'topic', fn (Builder $query): Builder => $query->select(explode(',', self::TOPIC_COLUMNS))->withProjectCollege())
                ->when($needsCollege && $topicRelation !== null, fn (Builder $query): Builder => $query->with([
                    ...($kind === 'version_file' ? ['version:'.self::VERSION_COLUMNS] : []),
                    $topicRelation => fn (BelongsTo $topic): BelongsTo => $topic->select(explode(',', self::TOPIC_COLUMNS))->withProjectCollege(),
                ]))
                ->get(match ($kind) {
                    'topic' => explode(',', self::TOPIC_COLUMNS),
                    'draft' => explode(',', self::DRAFT_COLUMNS),
                    'version' => explode(',', self::VERSION_COLUMNS),
                    'document' => ['id', 'topic_id', 'title', 'original_filename', 'category'],
                    'draft_file' => ['id', 'proposal_draft_id', 'document_type', 'original_filename', 'lock_version'],
                    'version_file' => ['id', 'proposal_version_id', 'document_type', 'original_filename'],
                    'monitoring' => ['id', 'topic_id', 'reporting_date', 'reporting_year', 'reporting_quarter', 'period_start', 'period_end', 'version_number', 'official_pdf_filename'],
                    'narrative' => ['id', 'topic_id', 'report_type', 'reporting_date', 'reporting_quarter', 'period_start', 'period_end', 'submission_date', 'version_number', 'official_pdf_filename'],
                    'manual' => ['id', 'title', 'edition', 'version', 'activated_at'],
                })->keyBy('id');
        }

        $authorized = [];
        $results->setCollection($results->getCollection()->map(function (object $match) use ($records, $user, $legacy, &$authorized): ?array {
            if ($match->kind === 'legacy_manual') {
                return $legacy;
            }
            $record = $records[$match->kind]->get($match->id);
            if ($record === null) {
                return null;
            }

            $parent = match ($match->kind) {
                'topic', 'draft' => $record,
                'draft_file' => $record->draft,
                'version_file' => $record->version?->topic,
                'manual' => null,
                default => $record->topic,
            };
            if ($match->kind !== 'manual') {
                if ($parent === null) {
                    return null;
                }
                $key = $parent::class.':'.$parent->id;
                $authorized[$key] ??= Gate::forUser($user)->allows('view', $parent);
                if (! $authorized[$key]) {
                    return null;
                }
            }

            return $this->result($record, $match->kind, $parent);
        })->filter()->values());

        return $results;
    }

    /** @return array<string, Builder> */
    private function queries(User $user, string $term): array
    {
        $topics = $this->topics($user)->select('id');
        $drafts = ProposalDraft::query()->accessibleTo($user);
        if (Gate::forUser($user)->denies('viewAny', ProposalDraft::class)) {
            $drafts->whereRaw('1 = 0');
        }

        $queries = [
            'topic' => $this->matching(clone $topics, ['title', 'description'], $term),
            'draft' => $this->matching(clone $drafts, ['project_title', 'project_leader'], $term),
            'draft_file' => $this->matching(ProposalDraftDocument::query()->whereIn('proposal_draft_id', (clone $drafts)->select('id')), ['original_filename', 'document_type'], $term),
            'version' => $this->matching(ProposalVersion::query()->whereIn('topic_id', clone $topics), ['title', 'description', 'original_filename', 'change_summary'], $term),
            'document' => $this->matching(ProjectDocument::query()->whereIn('topic_id', clone $topics), ['title', 'original_filename', 'note'], $term),
            'manual' => $this->matching(ResearchManual::query()->when(! $user->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD), fn (Builder $query): Builder => $query->whereNotNull('activated_at')), ['title', 'edition', 'version'], $term),
        ];

        $files = ProposalVersionFile::query()->whereIn('proposal_version_id', ProposalVersion::query()->whereIn('topic_id', clone $topics)->select('id'));
        if (! $user->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD)) {
            $files->where(function (Builder $visible) use ($user): void {
                $visible->where('document_type', '!=', ProposalVersionFile::TYPE_HEAD_UPLOAD)
                    ->orWhereNull('source_data->purpose')
                    ->orWhere('source_data->purpose', '!=', ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED)
                    ->orWhere(fn (Builder $released): Builder => $released->whereNull('superseded_at')->whereIn('proposal_version_id', ProposalVersion::query()->whereIn('topic_id', TopicProposal::query()->withIssuedNotice()->select('id'))->select('id')))
                    ->orWhereIn('proposal_version_id', ProposalVersion::query()->whereIn('topic_id', TopicProposal::query()->accessibleForDocumentRelease($user)->whereIn('status', [TopicProposal::STATUS_READY_FOR_SIGNATURE, 'approved'])->select('id'))->select('id'));
            });
        }
        $queries['version_file'] = $this->matching($files, ['original_filename', 'document_type', 'source_data->document_title', 'source_data->note'], $term);

        foreach (['monitoring' => ProjectProgressReport::class, 'narrative' => ProjectNarrativeReport::class] as $kind => $model) {
            $reports = $model::query()->submitted()->whereIn('topic_id', clone $topics);
            if ($user->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD)) {
                $reports->visibleToResearchHead();
            } elseif ($user->isUsingWorkspace(User::WORKSPACE_RESEARCH_SECRETARY)) {
                $reports->whereIn('topic_id', TopicProposal::query()->forImplementationReportReview($user)->select('id'));
                if ($kind === 'narrative') {
                    $reports->where('report_type', 'progress');
                }
            } else {
                $reports->whereIn('topic_id', TopicProposal::query()->accessibleTo($user)->select('id'));
            }
            $columns = ['tracking_number', 'official_pdf_filename'];
            $columns = [...$columns, ...($kind === 'narrative' ? ['funding_agency', 'accomplishment_summary'] : ['accomplishments', 'issues'])];
            $queries[$kind] = $reports->where(function (Builder $matches) use ($columns, $term, $kind): void {
                $this->matching($matches, $columns, $term)
                    ->orWhereHas('topic', fn (Builder $topic): Builder => $this->matching($topic, ['title'], $term));
                if ($kind === 'monitoring' && Str::contains('monitoring tool', Str::lower($term))) {
                    $matches->orWhereRaw('1 = 1');
                }
                if ($kind === 'narrative') {
                    foreach (['progress' => 'progress report', 'terminal' => 'terminal report'] as $type => $label) {
                        if (Str::contains($label, Str::lower($term))) {
                            $matches->orWhere('report_type', $type);
                        }
                    }
                }
            });
        }

        return $queries;
    }

    private function topics(User $user): Builder
    {
        $query = TopicProposal::query();
        if ($user->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD)) {
            return $query;
        }
        if ($user->isUsingWorkspace(User::WORKSPACE_FACULTY)) {
            return $query->accessibleTo($user);
        }
        if ($user->isUsingWorkspace(User::WORKSPACE_FACULTY_RESEARCHER)) {
            return $query->accessibleTo($user)->visibleInResearcherWorkspace();
        }

        return $query->where(function (Builder $visible) use ($user): void {
            $visible->accessibleForDocumentRelease($user)->whereIn('status', [TopicProposal::STATUS_READY_FOR_SIGNATURE, 'approved']);
            if ($user->isUsingWorkspace(User::WORKSPACE_RESEARCH_SECRETARY)) {
                $visible->orWhere(fn (Builder $reports): Builder => $reports->withIssuedNotice()->forImplementationReportReview($user));
            }
            if ($user->isUsingWorkspace(User::WORKSPACE_RESEARCH_OFFICE) && filled($user->college)) {
                $visible->orWhere(fn (Builder $lrec): Builder => $lrec->forCollege($user->college)
                    ->where('review_stage', 'lrec')->whereIn('status', [TopicProposal::STATUS_LREC_QUEUED, TopicProposal::STATUS_LREC_REVIEW, 'revision_requested', 'resubmitted', 'pending', 'expert_review', 'for_final_decision']));
            }
        });
    }

    /** @param list<string> $columns */
    private function matching(Builder $query, array $columns, string $term): Builder
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';

        return $query->where(function (Builder $matches) use ($columns, $pattern, $term): void {
            foreach ($columns as $column) {
                $wrapped = $matches->getQuery()->getGrammar()->wrap($column);
                $matches->orWhereRaw($wrapped." LIKE ? ESCAPE '!'", [$pattern]);
            }
            if (in_array('document_type', $columns, true)) {
                $types = $this->paperCatalog->all()
                    ->filter(fn (array $paper): bool => Str::contains(Str::lower($paper['label']), Str::lower($term)))
                    ->pluck('document_type');
                $matches->orWhereIn('document_type', $types);
            }
        });
    }

    /** @return array{title: string, type: string, context: string, location: string, url: string, edition: ?string}|null */
    private function legacyManual(string $term): ?array
    {
        try {
            $index = $this->manualLibrary->activeIndex();
            if ($index === null || ResearchManual::query()->where('version', $index['version'])->exists()) {
                return null;
            }
            $metadata = ($index['title'] ?? '').' '.($index['edition'] ?? '').' '.$index['version'];
            if (! Str::contains(Str::lower($metadata), Str::lower($term))) {
                return null;
            }

            return [
                'title' => $index['title'], 'type' => 'Research manual', 'context' => 'Current manual',
                'location' => 'Manual library · Version '.$index['version'],
                'url' => route('research-manuals.read', $index['version']),
                'edition' => filled($index['edition'] ?? null) ? 'Manual edition '.$index['edition'] : null,
            ];
        } catch (RuntimeException) {
            return null;
        }
    }

    /** @return array{title: string, type: string, context: string, location: string, url: string, edition: ?string} */
    private function result(Model $record, string $kind, ?Model $parent): array
    {
        $manual = $kind === 'manual' ? $record : $parent?->researchManual;
        $title = match ($kind) {
            'topic', 'manual' => $record->title,
            'draft' => $record->project_title ?: 'Untitled proposal draft',
            'version' => $record->title,
            'draft_file', 'version_file' => $record->original_filename ?: Str::headline($record->document_type),
            'document' => $record->title ?: $record->original_filename,
            'monitoring' => 'Monitoring Tool · '.$record->quarter_label.' · '.($record->reporting_year ?? $record->reporting_date->year),
            'narrative' => $record->report_label.' · '.($record->reporting_period_label ?? $record->submission_date->format('M j, Y')),
        };
        $version = match ($kind) {
            'version' => $record->version_number,
            'version_file' => $record->version->version_number,
            'monitoring', 'narrative' => $record->version_number,
            'draft_file' => $record->lock_version,
            default => null,
        };
        $type = match ($kind) {
            'topic' => 'Project / proposal', 'draft' => 'Proposal draft', 'draft_file' => 'Draft file',
            'version' => 'Submitted proposal', 'version_file' => 'Submitted file', 'document' => 'Project file',
            'monitoring' => 'Monitoring report', 'narrative' => $record->report_label, 'manual' => 'Research manual',
        };
        $context = match ($kind) {
            'manual' => 'Edition '.$record->edition.' · '.($record->activated_at ? 'Activated edition' : 'Staged edition'),
            'draft', 'draft_file' => $parent->project_title ?: 'Untitled proposal draft',
            default => $parent->title,
        };
        $location = match ($kind) {
            'draft', 'draft_file' => 'Proposal workspace',
            'version', 'version_file' => 'Submitted proposals',
            'topic' => Str::headline($record->status),
            'document' => 'Project files · '.$record->categoryLabel().' · '.$record->original_filename,
            'monitoring', 'narrative' => 'Project reports'.($record->official_pdf_filename ? ' · '.$record->official_pdf_filename : ''),
            'manual' => 'Manual library · Version '.$record->version,
        };
        if ($version !== null) {
            $location .= ' · Version '.$version;
        }
        $url = match ($kind) {
            'draft', 'draft_file' => route('faculty.proposal-drafts.show', $parent),
            'version' => route('topics.versions.download', [$parent, $record]),
            'version_file' => route('topics.versions.files.download', [$parent, $record->version, $record]),
            'document' => route('topics.documents.download', [$parent, $record]),
            'monitoring' => route('project-progress.monitoring-tool.view', $record),
            'narrative' => route('project-narrative-reports.show', $record),
            'manual' => route('research-manuals.read', $record->version),
            default => route('topics.show', $record),
        };

        return ['title' => $title, 'type' => $type, 'context' => $context, 'location' => $location, 'url' => $url, 'edition' => $manual ? 'Manual edition '.$manual->edition : null];
    }
}
