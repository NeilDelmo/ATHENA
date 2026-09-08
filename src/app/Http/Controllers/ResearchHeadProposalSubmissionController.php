<?php

namespace App\Http\Controllers;

use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Notifications\ProposalActivityNotification;
use App\Services\SidebarAttentionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ResearchHeadProposalSubmissionController extends Controller
{
    private const STATUSES = [
        'pending',
        'expert_review',
        'for_final_decision',
        'lrec_queued',
        'lrec_review',
        'revision_requested',
        'resubmitted',
        TopicProposal::STATUS_READY_FOR_SIGNATURE,
        'approved',
        'rejected',
    ];

    private const SUBMISSION_TYPES = ['initial', 'revision'];

    private const ACTIVE_STATUSES = [
        'pending',
        'expert_review',
        'for_final_decision',
        'lrec_queued',
        'lrec_review',
        'revision_requested',
        'resubmitted',
        TopicProposal::STATUS_READY_FOR_SIGNATURE,
    ];

    public function index(Request $request, SidebarAttentionService $sidebarAttention): View
    {
        Gate::authorize('viewAny', TopicProposal::class);

        $search = $request->string('search')->trim()->toString();
        $submissionType = $request->string('type')->toString();
        $status = $request->string('status')->toString();
        $unreadProposalTopicIds = $sidebarAttention->unreadTopicIdsFor(
            $request->user(),
            ProposalActivityNotification::SIDEBAR_AREA_PROPOSAL_SUBMISSIONS,
        );

        $summary = [
            'proposals' => ProposalVersion::query()->distinct()->count('topic_id'),
            'active' => TopicProposal::query()->whereIn('status', self::ACTIVE_STATUSES)->count(),
            'total' => ProposalVersion::query()->count(),
            'initial' => ProposalVersion::query()->where('submission_type', 'initial')->count(),
            'revision' => ProposalVersion::query()->where('submission_type', 'revision')->count(),
        ];

        $activeProposals = TopicProposal::query()
            ->select([
                'id',
                'user_id',
                'research_call_id',
                'title',
                'status',
                'created_at',
                'updated_at',
            ])
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->with([
                'user:id,name,email,college',
                'researchCall:id,title,academic_year',
                'latestVersion',
            ])
            ->when(in_array($status, self::STATUSES, true), fn (Builder $query): Builder => $query->where('status', $status))
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
                'topic:id,user_id,research_call_id,title,status',
                'topic.user:id,name,email,college',
                'topic.researchCall:id,title,academic_year',
            ])
            ->withCount([
                'files as package_files_count' => fn (Builder $query): Builder => $query->whereNotIn('document_type', [
                    ProposalVersionFile::TYPE_COMMENT_RESPONSE,
                    ProposalVersionFile::TYPE_HEAD_UPLOAD,
                ]),
            ])
            ->when(in_array($submissionType, self::SUBMISSION_TYPES, true), fn (Builder $query): Builder => $query->where('submission_type', $submissionType))
            ->when(in_array($status, self::STATUSES, true), fn (Builder $query): Builder => $query->whereHas('topic', fn (Builder $query): Builder => $query->where('status', $status)))
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
            'unreadProposalTopicIds',
        ));
    }
}
