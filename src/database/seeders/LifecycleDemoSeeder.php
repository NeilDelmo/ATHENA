<?php

namespace Database\Seeders;

use App\Actions\ArchiveProposalDraftDocumentHistory;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\ProposalDraft;
use App\Models\ResearchCall;
use App\Models\ResearchCategory;
use App\Models\ResearchPublication;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Permission\Models\Role;

class LifecycleDemoSeeder extends Seeder
{
    private const MARKER = '[lifecycle-demo:';

    /** @var list<array{key: string, label: string, final_status: string, project_status: ?string}> */
    private const SCENARIOS = [
        ['key' => 'pending', 'label' => 'Submitted for initial review', 'final_status' => 'pending', 'project_status' => null],
        ['key' => 'expert-review', 'label' => 'Under expert review', 'final_status' => 'expert_review', 'project_status' => null],
        ['key' => 'initial-revision', 'label' => 'Initial revision requested', 'final_status' => 'revision_requested', 'project_status' => null],
        ['key' => 'resubmitted', 'label' => 'Revision resubmitted', 'final_status' => 'resubmitted', 'project_status' => null],
        ['key' => 'final-decision', 'label' => 'For final decision', 'final_status' => 'for_final_decision', 'project_status' => null],
        ['key' => 'lrec-queue', 'label' => 'Awaiting LREC presentation', 'final_status' => TopicProposal::STATUS_LREC_QUEUED, 'project_status' => null],
        ['key' => 'lrec-review', 'label' => 'Under LREC review', 'final_status' => TopicProposal::STATUS_LREC_REVIEW, 'project_status' => null],
        ['key' => 'signing', 'label' => 'Ready for signature', 'final_status' => TopicProposal::STATUS_READY_FOR_SIGNATURE, 'project_status' => null],
        ['key' => 'rejected', 'label' => 'Rejected after evaluation', 'final_status' => 'rejected', 'project_status' => null],
        ['key' => 'approved', 'label' => 'Approved, awaiting Notice to Proceed', 'final_status' => 'approved', 'project_status' => null],
        ['key' => 'ongoing', 'label' => 'Ongoing implementation', 'final_status' => 'approved', 'project_status' => TopicProposal::PROJECT_STATUS_ONGOING],
        ['key' => 'delayed', 'label' => 'Delayed implementation', 'final_status' => 'approved', 'project_status' => TopicProposal::PROJECT_STATUS_DELAYED],
        ['key' => 'completed-presented', 'label' => 'Completed with conference presentation', 'final_status' => 'approved', 'project_status' => TopicProposal::PROJECT_STATUS_COMPLETED],
        ['key' => 'completed-published', 'label' => 'Completed with indexed publication', 'final_status' => 'approved', 'project_status' => TopicProposal::PROJECT_STATUS_COMPLETED],
        ['key' => 'pending-second', 'label' => 'Additional proposal in initial review', 'final_status' => 'pending', 'project_status' => null],
        ['key' => 'revision-second', 'label' => 'Additional proposal requiring revision', 'final_status' => 'revision_requested', 'project_status' => null],
        ['key' => 'resubmitted-second', 'label' => 'Additional revised proposal resubmitted', 'final_status' => 'resubmitted', 'project_status' => null],
        ['key' => 'lrec-review-second', 'label' => 'Additional proposal under LREC review', 'final_status' => TopicProposal::STATUS_LREC_REVIEW, 'project_status' => null],
        ['key' => 'ongoing-second', 'label' => 'Additional project under implementation', 'final_status' => 'approved', 'project_status' => TopicProposal::PROJECT_STATUS_ONGOING],
        ['key' => 'delayed-second', 'label' => 'Additional delayed project with monitoring', 'final_status' => 'approved', 'project_status' => TopicProposal::PROJECT_STATUS_DELAYED],
        ['key' => 'completed-published-second', 'label' => 'Additional completed project with dissemination', 'final_status' => 'approved', 'project_status' => TopicProposal::PROJECT_STATUS_COMPLETED],
    ];

    public function run(ArchiveProposalDraftDocumentHistory $archiveHistory): void
    {
        $head = User::role('research_head')->orderBy('id')->first();

        if (! $head) {
            throw new RuntimeException('Create a Research Head account before running LifecycleDemoSeeder.');
        }

        $call = $this->demoCall($head);
        $categories = ResearchCategory::query()->orderBy('id')->get();
        $call->categories()->sync($categories->modelKeys());

        foreach (self::SCENARIOS as $index => $scenario) {
            if (TopicProposal::query()->where('description', 'like', self::MARKER.$scenario['key'].']%')->exists()) {
                continue;
            }

            $draft = ProposalDraft::query()
                ->with(['documents', 'documentVersions', 'members'])
                ->where('status', ProposalDraft::STATUS_DRAFT)
                ->whereNull('topic_id')
                ->has('documents', '>=', 7)
                ->whereHas('documents', fn ($documents) => $documents->whereNotNull('file_path'), '>=', 7)
                ->whereHas('documents', fn ($documents) => $documents->where('document_type', 'detailed_proposal')->whereNotNull('file_path'))
                ->whereDoesntHave('topic')
                ->whereNotIn('project_title', TopicProposal::query()->select('title'))
                ->oldest('id')
                ->first();

            if (! $draft) {
                throw new RuntimeException('LifecycleDemoSeeder needs at least '.count(self::SCENARIOS).' prepared proposal drafts that have not already been submitted.');
            }

            $topic = $this->promoteDraft($draft, $call, $categories->get($index % max($categories->count(), 1)), $scenario, $archiveHistory);
            $this->advanceProposal($topic, $head, $scenario);
            $this->command?->info($scenario['label'].': '.$topic->title);
        }

        $this->command?->info('Lifecycle sample is ready. Existing records were preserved.');
    }

    private function demoCall(User $head): ResearchCall
    {
        return ResearchCall::query()->updateOrCreate(
            ['title' => 'Research Lifecycle Demonstration Cohort'],
            [
                'academic_year' => '2024-2027',
                'term' => 'Demonstration',
                'description' => 'Connected sample records covering proposal preparation through dissemination.',
                'opens_at' => '2024-01-08 08:00:00',
                'closes_at' => '2027-12-31 17:00:00',
                'initial_evaluation_start_date' => '2024-02-01',
                'initial_evaluation_end_date' => '2024-03-15',
                'paper_revisions_start_date' => '2024-03-16',
                'paper_revisions_end_date' => '2024-04-30',
                'lrec_start_date' => '2024-05-01',
                'lrec_end_date' => '2024-06-30',
                'implementation_start_date' => '2024-07-01',
                'implementation_end_date' => '2027-06-30',
                'max_active_research_per_faculty' => 20,
                'maximum_budget' => 150000,
                'status' => 'open',
                'created_by' => $head->id,
            ],
        );
    }

    /** @param array{key: string, label: string, final_status: string, project_status: ?string} $scenario */
    private function promoteDraft(ProposalDraft $draft, ResearchCall $call, ?ResearchCategory $category, array $scenario, ArchiveProposalDraftDocumentHistory $archiveHistory): TopicProposal
    {
        return DB::transaction(function () use ($draft, $call, $category, $scenario, $archiveHistory): TopicProposal {
            $topic = TopicProposal::query()->create([
                'user_id' => $draft->user_id,
                'research_call_id' => $call->id,
                'research_category_id' => $category?->id,
                'title' => $draft->project_title,
                'description' => self::MARKER.$scenario['key'].'] '.$scenario['label'].'. This record was promoted from a prepared proposal draft.',
                'estimated_budget' => $this->draftBudget($draft),
                'estimated_duration_months' => $draft->duration_months,
                'status' => 'pending',
            ]);

            $directory = 'proposal-packages/lifecycle-demo/'.$topic->id;
            $primaryDocument = $draft->documents->firstWhere('document_type', 'detailed_proposal') ?? $draft->documents->firstOrFail();
            $primaryPath = $this->copyDocument($primaryDocument->file_path, $directory.'/v1/'.$primaryDocument->document_type.'-'.$primaryDocument->position);
            if (! $primaryPath) {
                throw new RuntimeException('The prepared detailed proposal file is missing for '.$draft->project_title.'.');
            }
            $version = $topic->versions()->create([
                'submitted_by' => $draft->user_id,
                'version_number' => 1,
                'submission_type' => 'initial',
                'change_summary' => 'Initial package submitted from the proposal drafting workspace.',
                'title' => $draft->project_title,
                'estimated_budget' => $this->draftBudget($draft),
                'estimated_duration_months' => $draft->duration_months,
                'file_path' => $primaryPath,
                'original_filename' => $primaryDocument->original_filename,
                'mime_type' => $primaryDocument->mime_type,
                'file_size' => $primaryDocument->file_size,
                'checksum' => $primaryDocument->checksum,
            ]);

            foreach ($draft->documents as $document) {
                $path = $document->is($primaryDocument)
                    ? $primaryPath
                    : $this->copyDocument($document->file_path, $directory.'/v1/'.$document->document_type.'-'.$document->position);
                $version->files()->create([
                    'document_type' => $document->document_type,
                    'position' => $document->position,
                    'file_path' => $path,
                    'original_filename' => $document->original_filename,
                    'mime_type' => $document->mime_type,
                    'file_size' => $document->file_size,
                    'checksum' => $document->checksum,
                    'source_data' => $document->source_data,
                    'uploaded_by' => $draft->user_id,
                ]);
            }

            foreach ($draft->members as $member) {
                $topic->collaborators()->create([
                    'user_id' => $member->user_id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'accepted_at' => $member->accepted_at,
                ]);
            }

            if ($draft->documentVersions->isNotEmpty()) {
                $archiveHistory->handle($draft, $topic, $directory);
            }

            $draft->delete();

            return $topic;
        }, 3);
    }

    private function draftBudget(ProposalDraft $draft): float
    {
        $source = $draft->documents->firstWhere('document_type', 'line_item_budget')?->source_data;

        return (float) ($source['computed_project_total'] ?? $source['project_total'] ?? 75000);
    }

    private function copyDocument(?string $source, string $targetWithoutExtension): ?string
    {
        if (! $source || ! Storage::disk('local')->exists($source)) {
            return null;
        }

        $extension = pathinfo($source, PATHINFO_EXTENSION) ?: 'pdf';
        $target = $targetWithoutExtension.'.'.$extension;

        if (! Storage::disk('local')->exists($target)) {
            Storage::disk('local')->copy($source, $target);
        }

        return $target;
    }

    /** @param array{key: string, label: string, final_status: string, project_status: ?string} $scenario */
    private function advanceProposal(TopicProposal $topic, User $head, array $scenario): void
    {
        $paths = [
            'pending' => [],
            'expert-review' => ['expert_review'],
            'initial-revision' => ['expert_review', 'revision_requested'],
            'resubmitted' => ['expert_review', 'revision_requested', 'resubmitted'],
            'final-decision' => ['expert_review', 'revision_requested', 'resubmitted', 'for_final_decision'],
            'lrec-queue' => ['expert_review', 'revision_requested', 'resubmitted', TopicProposal::STATUS_LREC_QUEUED],
            'lrec-review' => ['expert_review', 'revision_requested', 'resubmitted', TopicProposal::STATUS_LREC_QUEUED, TopicProposal::STATUS_LREC_REVIEW],
            'signing' => ['expert_review', 'revision_requested', 'resubmitted', TopicProposal::STATUS_LREC_QUEUED, TopicProposal::STATUS_LREC_REVIEW, TopicProposal::STATUS_READY_FOR_SIGNATURE],
            'rejected' => ['expert_review', 'rejected'],
            'approved' => ['expert_review', 'revision_requested', 'resubmitted', TopicProposal::STATUS_LREC_QUEUED, TopicProposal::STATUS_LREC_REVIEW, TopicProposal::STATUS_READY_FOR_SIGNATURE, 'approved'],
            'ongoing' => ['expert_review', 'revision_requested', 'resubmitted', TopicProposal::STATUS_LREC_QUEUED, TopicProposal::STATUS_LREC_REVIEW, TopicProposal::STATUS_READY_FOR_SIGNATURE, 'approved'],
            'delayed' => ['expert_review', 'revision_requested', 'resubmitted', TopicProposal::STATUS_LREC_QUEUED, TopicProposal::STATUS_LREC_REVIEW, TopicProposal::STATUS_READY_FOR_SIGNATURE, 'approved'],
            'completed-presented' => ['expert_review', 'revision_requested', 'resubmitted', TopicProposal::STATUS_LREC_QUEUED, TopicProposal::STATUS_LREC_REVIEW, TopicProposal::STATUS_READY_FOR_SIGNATURE, 'approved'],
            'completed-published' => ['expert_review', 'revision_requested', 'resubmitted', TopicProposal::STATUS_LREC_QUEUED, TopicProposal::STATUS_LREC_REVIEW, TopicProposal::STATUS_READY_FOR_SIGNATURE, 'approved'],
            'pending-second' => [],
            'revision-second' => ['expert_review', 'revision_requested'],
            'resubmitted-second' => ['expert_review', 'revision_requested', 'resubmitted'],
            'lrec-review-second' => ['expert_review', 'revision_requested', 'resubmitted', TopicProposal::STATUS_LREC_QUEUED, TopicProposal::STATUS_LREC_REVIEW],
            'ongoing-second' => ['expert_review', 'revision_requested', 'resubmitted', TopicProposal::STATUS_LREC_QUEUED, TopicProposal::STATUS_LREC_REVIEW, TopicProposal::STATUS_READY_FOR_SIGNATURE, 'approved'],
            'delayed-second' => ['expert_review', 'revision_requested', 'resubmitted', TopicProposal::STATUS_LREC_QUEUED, TopicProposal::STATUS_LREC_REVIEW, TopicProposal::STATUS_READY_FOR_SIGNATURE, 'approved'],
            'completed-published-second' => ['expert_review', 'revision_requested', 'resubmitted', TopicProposal::STATUS_LREC_QUEUED, TopicProposal::STATUS_LREC_REVIEW, TopicProposal::STATUS_READY_FOR_SIGNATURE, 'approved'],
        ];

        foreach ($paths[$scenario['key']] as $status) {
            if ($status === 'resubmitted') {
                $this->addRevisionVersion($topic);
            }

            $reviewStage = in_array($status, [TopicProposal::STATUS_LREC_QUEUED, TopicProposal::STATUS_LREC_REVIEW, TopicProposal::STATUS_READY_FOR_SIGNATURE], true) ? 'lrec' : $topic->review_stage;
            $topic->update(['status' => $status, 'review_stage' => $reviewStage]);
            $this->recordReviewStep($topic, $head, $status);
        }

        if ($scenario['project_status']) {
            $topic->user->assignRole(Role::findOrCreate('faculty_researcher', 'web'));
            $topic->update([
                'notice_to_proceed_issued_by' => $head->id,
                'notice_to_proceed_issued_at' => now()->subMonths($scenario['project_status'] === TopicProposal::PROJECT_STATUS_COMPLETED ? 14 : 8),
                'notice_to_proceed_data' => [
                    'project_title' => $topic->title,
                    'principal_investigator' => $topic->user->name,
                    'approved_budget' => (float) $topic->estimated_budget,
                    'issued_by' => $head->name,
                ],
                'project_status' => $scenario['project_status'],
            ]);
            $this->seedImplementationRecords($topic, $head);
        }
    }

    private function addRevisionVersion(TopicProposal $topic): void
    {
        if ($topic->versions()->where('version_number', 2)->exists()) {
            return;
        }

        $initial = $topic->versions()->where('version_number', 1)->firstOrFail();
        $revision = $topic->versions()->create([
            'submitted_by' => $topic->user_id,
            'version_number' => 2,
            'submission_type' => 'revision',
            'change_summary' => 'Revised methodology, implementation schedule, and budget notes in response to evaluation feedback.',
            'title' => $initial->title,
            'description' => $initial->description,
            'estimated_budget' => $initial->estimated_budget,
            'estimated_duration_months' => $initial->estimated_duration_months,
            'file_path' => $initial->file_path,
            'original_filename' => $initial->original_filename,
            'mime_type' => $initial->mime_type,
            'file_size' => $initial->file_size,
            'checksum' => $initial->checksum,
        ]);

        foreach ($initial->files as $file) {
            $revision->files()->create([
                'source_version_file_id' => $file->id,
                'document_type' => $file->document_type,
                'position' => $file->position,
                'file_path' => $file->file_path,
                'original_filename' => $file->original_filename,
                'mime_type' => $file->mime_type,
                'file_size' => $file->file_size,
                'checksum' => $file->checksum,
                'source_data' => $file->source_data,
                'is_carried_forward' => true,
                'uploaded_by' => $topic->user_id,
            ]);
        }
    }

    private function recordReviewStep(TopicProposal $topic, User $head, string $status): void
    {
        if ($status === 'expert_review') {
            $topic->expertAssignments()->create([
                'expert_id' => $head->id,
                'assigned_by' => $head->id,
                'status' => 'completed',
                'recommendation' => 'recommend_revision',
                'comment' => 'The concept is relevant; clarify the sampling plan and measurable outcomes.',
                'reviewed_at' => now()->subMonths(10),
            ]);

            return;
        }

        $comments = [
            'revision_requested' => 'Revise the methodology and connect each activity to a measurable output.',
            TopicProposal::STATUS_LREC_QUEUED => 'Initial requirements are complete. Endorsed for LREC presentation.',
            TopicProposal::STATUS_LREC_REVIEW => 'Proposal presented to LREC and queued for committee deliberation.',
            TopicProposal::STATUS_READY_FOR_SIGNATURE => 'LREC requirements are satisfied. Prepare the final signed package.',
            'for_final_decision' => 'Expert responses are complete and the revision is ready for the final decision.',
            'approved' => 'Final proposal package approved.',
            'rejected' => 'The proposal does not meet the feasibility requirements for this cycle.',
        ];

        if (isset($comments[$status])) {
            $topic->reviews()->create([
                'reviewer_id' => $head->id,
                'review_stage' => $topic->review_stage,
                'decision' => $status,
                'comment' => $comments[$status],
            ]);
        }
    }

    private function seedImplementationRecords(TopicProposal $topic, User $head): void
    {
        $reportCount = match ($topic->project_status) {
            TopicProposal::PROJECT_STATUS_ONGOING => 2,
            TopicProposal::PROJECT_STATUS_DELAYED => 3,
            TopicProposal::PROJECT_STATUS_COMPLETED => 4,
            default => 0,
        };

        $start = $topic->notice_to_proceed_issued_at->copy()->startOfDay();
        $previous = null;

        for ($quarter = 1; $quarter <= $reportCount; $quarter++) {
            $periodStart = $start->copy()->addMonths(($quarter - 1) * 3);
            $periodEnd = $periodStart->copy()->addMonths(3)->subDay();
            $progress = min(100, $quarter * (int) floor(100 / $reportCount));
            $reviewStatus = $topic->project_status === TopicProposal::PROJECT_STATUS_DELAYED && $quarter === $reportCount
                ? ProjectNarrativeReport::STATUS_REVISION_REQUESTED
                : ProjectNarrativeReport::STATUS_REVIEWED;

            $previous = ProjectProgressReport::query()->create([
                'topic_id' => $topic->id,
                'submitted_by' => $topic->user_id,
                'reporting_date' => $periodEnd,
                'reporting_year' => $periodEnd->year,
                'reporting_quarter' => $quarter,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'version_number' => 1,
                'supersedes_report_id' => null,
                'tracking_number' => 'LIFE-'.$topic->id.'-Q'.$quarter,
                'progress_percentage' => $quarter === $reportCount && $topic->project_status === TopicProposal::PROJECT_STATUS_COMPLETED ? 100 : $progress,
                'accomplishments' => 'Completed milestone '.$quarter.': stakeholder coordination, field activities, validation, and documented outputs.',
                'issues' => $reviewStatus === ProjectNarrativeReport::STATUS_REVISION_REQUESTED ? 'Procurement delays affected the planned validation schedule.' : 'Minor scheduling adjustments were managed within the reporting period.',
                'work_plan' => [['activity' => 'Lifecycle milestone '.$quarter, 'expected_output' => 'Validated milestone output', 'status' => 'completed']],
                'budget_utilization' => [['category' => 'MOOE', 'allocated' => 25000, 'utilized' => 5000 * $quarter]],
                'submission_status' => ProjectProgressReport::SUBMISSION_STATUS_SUBMITTED,
                'submitted_at' => $periodEnd->copy()->addDay(),
                'review_status' => $reviewStatus,
                'research_head_remarks' => $reviewStatus === ProjectNarrativeReport::STATUS_REVISION_REQUESTED ? 'Explain the recovery schedule and attach updated procurement dates.' : 'Reviewed. Continue with the approved work plan.',
                'reviewed_by' => $head->id,
                'reviewed_at' => $periodEnd->copy()->addDays(3),
            ]);
        }

        if ($topic->project_status !== TopicProposal::PROJECT_STATUS_COMPLETED) {
            return;
        }

        ProjectNarrativeReport::query()->create([
            'topic_id' => $topic->id,
            'submitted_by' => $topic->user_id,
            'report_type' => 'progress',
            'submission_date' => $start->copy()->addMonths(6),
            'tracking_number' => 'LIFE-'.$topic->id.'-NARRATIVE',
            'researchers' => $topic->user->name,
            'implementation_start' => $start,
            'implementation_end' => $start->copy()->addMonths(12),
            'budget' => $topic->estimated_budget,
            'funding_agency' => 'Batangas State University',
            'accomplishment_summary' => 'The project completed field validation and produced its planned technical and community outputs.',
            'accomplishments' => [['activity' => 'Pilot implementation', 'output' => 'Validated system and evaluation dataset']],
            'introduction' => 'This narrative consolidates implementation activities and measured outcomes.',
            'rationale' => 'The project addressed a documented operational and community need.',
            'objectives' => 'Develop, pilot, and evaluate the proposed intervention.',
            'methodology' => 'The team used developmental research and mixed-method evaluation.',
            'results_discussion' => 'Results show that the principal objectives and performance targets were achieved.',
            'photos' => [],
            'submission_status' => ProjectNarrativeReport::SUBMISSION_STATUS_SUBMITTED,
            'submitted_at' => $start->copy()->addMonths(6),
            'review_status' => ProjectNarrativeReport::STATUS_REVIEWED,
            'reviewed_by' => $head->id,
            'reviewed_at' => $start->copy()->addMonths(6)->addDays(3),
        ]);

        ProjectNarrativeReport::query()->create([
            'topic_id' => $topic->id,
            'submitted_by' => $topic->user_id,
            'report_type' => 'terminal',
            'submission_date' => $start->copy()->addMonths(12),
            'tracking_number' => 'LIFE-'.$topic->id.'-TERMINAL',
            'researchers' => $topic->user->name,
            'implementation_start' => $start,
            'implementation_end' => $start->copy()->addMonths(12),
            'budget' => $topic->estimated_budget,
            'funding_agency' => 'Batangas State University',
            'accomplishment_summary' => 'All planned outputs were completed, validated, and prepared for dissemination.',
            'accomplishments' => [['activity' => 'Final evaluation', 'output' => 'Terminal findings and dissemination package']],
            'introduction' => 'The terminal report presents the complete implementation record.',
            'rationale' => 'The completed research responds to the needs identified in the approved proposal.',
            'objectives' => 'Complete and evaluate all approved project objectives.',
            'methodology' => 'Implementation evidence was analyzed using quantitative summaries and stakeholder feedback.',
            'results_discussion' => 'The project met its principal targets and generated publishable findings.',
            'photos' => [],
            'terminal_data' => [
                'abstract' => 'This completed study developed, implemented, and evaluated the proposed intervention. Findings demonstrate measurable operational and stakeholder benefits and support continued dissemination.',
                'literature_review' => 'The final analysis was interpreted alongside the literature cited in the approved proposal.',
                'conclusions' => 'The project objectives were achieved and the intervention is suitable for further adoption.',
                'recommendations' => 'Present the findings, pursue peer review, and continue implementation monitoring.',
                'bibliography' => 'References are retained in the approved detailed proposal and terminal package.',
                'source_monitoring_report_ids' => $topic->progressReports()->pluck('project_progress_reports.id')->all(),
            ],
            'submission_status' => ProjectNarrativeReport::SUBMISSION_STATUS_SUBMITTED,
            'submitted_at' => $start->copy()->addMonths(12),
            'review_status' => ProjectNarrativeReport::STATUS_REVIEWED,
            'research_head_remarks' => 'Terminal report reviewed and accepted. Proceed with dissemination tracking.',
            'reviewed_by' => $head->id,
            'reviewed_at' => $start->copy()->addMonths(12)->addDays(5),
        ]);

        $this->seedDissemination($topic);
    }

    private function seedDissemination(TopicProposal $topic): void
    {
        foreach (['shortlisted', 'submitted', 'accepted', 'presented'] as $index => $status) {
            $url = 'https://example.org/conferences/lifecycle-'.$topic->id.'-'.($index + 1);
            $topic->conferences()->create([
                'added_by' => $topic->user_id,
                'fingerprint' => hash('sha256', $url),
                'title' => ['Regional Research and Innovation Forum', 'International Conference on Applied Community Research', 'Sustainable Technology Research Congress', 'University Research Dissemination Colloquium'][$index],
                'url' => $url,
                'official_url' => $url,
                'source' => 'Researcher entry',
                'location' => $index % 2 === 0 ? 'Batangas City, Philippines' : 'Online',
                'submission_deadline' => now()->addMonths($index + 1),
                'event_date' => now()->addMonths($index + 3),
                'attendance_mode' => $index % 2 === 0 ? 'in_person' : 'online',
                'fees' => $index === 0 ? 'To be confirmed from the official call' : 'PHP '.number_format(2500 + $index * 500),
                'publication_details' => 'Proceedings and indexing details must be confirmed with the organizer.',
                'status' => $status,
                'submitted_on' => in_array($status, ['submitted', 'accepted', 'presented'], true) ? now()->subMonths(4 - $index) : null,
                'accepted_on' => in_array($status, ['accepted', 'presented'], true) ? now()->subMonths(2) : null,
                'presented_on' => $status === 'presented' ? now()->subMonth() : null,
                'notes' => 'Connected sample conference record for the completed project.',
            ]);
        }

        foreach (range(1, 3) as $number) {
            $doi = '10.5555/lifecycle.'.$topic->id.'.'.$number;
            $publication = ResearchPublication::query()->firstOrCreate(
                ['user_id' => $topic->user_id, 'fingerprint' => ResearchPublication::fingerprint(['doi' => $doi, 'title' => $topic->title])],
                [
                    'doi' => $doi,
                    'title' => $topic->title.': '.['design and baseline findings', 'implementation outcomes', 'community validation results'][$number - 1],
                    'authors' => $topic->user->name.', Research Lifecycle Demonstration Team',
                    'venue' => ['Journal of Applied University Research', 'Philippine Research and Innovation Review', 'Community Technology Proceedings'][$number - 1],
                    'year' => now()->year,
                    'type' => $number === 3 ? 'proceedings-article' : 'journal-article',
                    'url' => 'https://doi.org/'.$doi,
                    'source' => $number === 1 ? 'OpenAlex' : 'Researcher entry',
                    'source_checked_at' => now(),
                    'confirmed_at' => now(),
                ],
            );
            $publication->topics()->syncWithoutDetaching([$topic->id]);
        }
    }
}
