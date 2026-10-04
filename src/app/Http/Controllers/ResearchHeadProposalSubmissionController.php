<?php

namespace App\Http\Controllers;

use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ResearchHeadProposalSubmissionController extends Controller
{
    private const FILTER_STATUSES = [
        'pending',
        'gad_assessment',
        'co_evaluator_review',
        'lrec_queued',
        'lrec_review',
        'revision_requested',
        'resubmitted',
        TopicProposal::STATUS_READY_FOR_SIGNATURE,
    ];

    private const SUBMISSION_TYPES = ['initial', 'revision', 'update'];

    private const ACTIVE_STATUSES = [
        'pending',
        'expert_review',
        'for_final_decision',
        TopicProposal::STATUS_GAD_REVIEW,
        'lrec_queued',
        'lrec_review',
        'revision_requested',
        'resubmitted',
        TopicProposal::STATUS_READY_FOR_SIGNATURE,
    ];

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', TopicProposal::class);

        $receivedOnly = $request->routeIs('research_head.received-submissions.index');
        $search = $request->string('search')->trim()->toString();
        $submissionType = $request->string('type')->toString();
        $status = $request->string('status')->toString();
        $summary = [
            'proposals' => ProposalVersion::query()->distinct()->count('topic_id'),
            'active' => TopicProposal::query()->whereIn('status', self::ACTIVE_STATUSES)->count(),
            'total' => ProposalVersion::query()->count(),
            'initial' => ProposalVersion::query()->where('submission_type', 'initial')->count(),
            'revision' => ProposalVersion::query()->where('submission_type', 'revision')->count(),
        ];

        $activeProposals = $receivedOnly ? null : TopicProposal::query()
            ->select([
                'id',
                'user_id',
                'research_call_id',
                'title',
                'status',
                'review_stage',
                'research_head_viewed_version_id',
                'created_at',
                'updated_at',
            ])
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->with([
                'user:id,name,email,college',
                'researchCall:id,title,academic_year',
                'latestVersion.files',
            ])
            ->when(in_array($status, self::FILTER_STATUSES, true), fn (Builder $query): Builder => $this->applyStatusFilter($query, $status))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhereHas('user', fn (Builder $query): Builder => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('researchCall', fn (Builder $query): Builder => $query->where('title', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(12, ['*'], 'queue-page')
            ->withQueryString();

        $submissions = ProposalVersion::query()
            ->select([
                'id',
                'topic_id',
                'submitted_by',
                'version_number',
                'submission_type',
                'change_summary',
                'file_path',
                'title',
                'created_at',
            ])
            ->with([
                'submitter:id,name,email',
                'topic:id,user_id,research_call_id,title,status,review_stage,research_head_viewed_version_id,notice_to_proceed_issued_at',
                'topic.user:id,name,email,college',
                'topic.researchCall:id,title,academic_year',
                'topic.latestVersion.files',
            ])
            ->withCount([
                'files as package_files_count' => fn (Builder $query): Builder => $query->whereNotIn('document_type', [
                    ProposalVersionFile::TYPE_COMMENT_RESPONSE,
                    ProposalVersionFile::TYPE_HEAD_UPLOAD,
                ]),
            ])
            ->when(in_array($submissionType, self::SUBMISSION_TYPES, true), fn (Builder $query): Builder => $query->where('submission_type', $submissionType))
            ->when(in_array($status, self::FILTER_STATUSES, true), fn (Builder $query): Builder => $query->whereHas('topic', fn (Builder $query): Builder => $this->applyStatusFilter($query, $status)))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhereHas('topic.user', fn (Builder $query): Builder => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('topic.researchCall', fn (Builder $query): Builder => $query->where('title', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('research_head.proposal-submissions.index', compact(
            'search',
            'status',
            'submissionType',
            'activeProposals',
            'submissions',
            'summary',
            'receivedOnly',
        ));
    }

    private function applyStatusFilter(Builder $query, string $status): Builder
    {
        if ($status === 'gad_assessment') {
            return $query
                ->where('status', TopicProposal::STATUS_GAD_REVIEW)
                ->whereDoesntHave('latestVersion.files', fn (Builder $query): Builder => $this->passingGadAssessmentQuery($query));
        }

        if ($status === 'co_evaluator_review') {
            return $query
                ->where('status', TopicProposal::STATUS_GAD_REVIEW)
                ->whereHas('latestVersion.files', fn (Builder $query): Builder => $this->passingGadAssessmentQuery($query));
        }

        return $query->where('status', $status);
    }

    private function passingGadAssessmentQuery(Builder $query): Builder
    {
        return $query
            ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
            ->where('source_data->purpose', ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT)
            ->where('source_data->target_document_type', ProposalVersionFile::TYPE_GAD_CHECKLIST)
            ->where('source_data->gad_signature_confirmed', true)
            ->whereIn('source_data->gad_outcome', ['passed', 'commended']);
    }
}
