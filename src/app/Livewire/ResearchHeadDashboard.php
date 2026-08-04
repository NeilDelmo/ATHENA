<?php

namespace App\Livewire;

use App\Models\ProposalDraft;
use App\Models\TopicProposal;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ResearchHeadDashboard extends Component
{
    use WithPagination;

    #[Url]
    public string $pipeline = '';

    #[Url]
    public string $search = '';

    public string $status = '';

    public function mount(): void
    {
        $this->status = (string) request()->query('status', '');
    }

    public function setPipeline(string $pipeline): void
    {
        $this->pipeline = $pipeline === $this->pipeline ? '' : $pipeline;
        $this->resetPage();
    }

    public function clearPipeline(): void
    {
        $this->pipeline = '';
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $allowedStatuses = ['pending', 'expert_review', 'for_final_decision', 'revision_requested', 'resubmitted', TopicProposal::STATUS_READY_FOR_SIGNATURE, 'approved', 'rejected'];

        $summary = [
            'awaiting_review' => TopicProposal::whereIn('status', ['pending', 'resubmitted', 'expert_review', 'for_final_decision'])->count(),
            'ready_for_signature' => TopicProposal::where('status', TopicProposal::STATUS_READY_FOR_SIGNATURE)->count(),
            'awaiting_notice' => TopicProposal::where('status', 'approved')->whereNull('notice_to_proceed_issued_at')->count(),
            'approved' => TopicProposal::monitoringAvailable()->count(),
        ];

        $topics = TopicProposal::with([
            'user', 'researchCall', 'category', 'versions.submitter', 'versions.files', 'reviews.reviewer', 'progressReports:id,topic_id,review_status',
        ])
            ->when(in_array($this->status, $allowedStatuses, true), fn ($query) => $query->where('status', $this->status))
            ->when($this->pipeline === 'awaiting_review', fn ($query) => $query->whereIn('status', ['pending', 'resubmitted', 'expert_review', 'for_final_decision']))
            ->when($this->pipeline === 'ready_for_signature', fn ($query) => $query->where('status', TopicProposal::STATUS_READY_FOR_SIGNATURE))
            ->when($this->pipeline === 'awaiting_notice', fn ($query) => $query->where('status', 'approved')->whereNull('notice_to_proceed_issued_at'))
            ->when($this->pipeline === 'approved', fn ($query) => $query->monitoringAvailable())
            ->when($this->search !== '', function ($query) {
                $search = $this->search;
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('livewire.research-head-dashboard', [
            'topics' => $topics,
            'summary' => $summary,
        ]);
    }
}
