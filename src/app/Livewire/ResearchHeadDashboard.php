<?php

namespace App\Livewire;

use App\Models\ResearchCall;
use App\Models\User;
use App\Services\DashboardCalendar;
use App\Services\ResearchDashboardAnalytics;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
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

    #[Url]
    public string $status = '';

    #[Url]
    public string $call = '';

    #[Url]
    public string $attention = '';

    public function setPipeline(string $pipeline): void
    {
        $this->pipeline = $pipeline === $this->pipeline ? '' : $pipeline;
        $this->reset('status', 'attention');
        $this->resetPage();
    }

    public function toggleRepeatedRevisions(): void
    {
        $this->attention = $this->attention === 'repeat' ? '' : 'repeat';
        $this->reset('pipeline', 'status', 'search');
        $this->resetPage();
    }

    public function clearPipeline(): void
    {
        $this->reset('pipeline', 'status', 'attention');
        $this->resetPage();
    }

    public function updated(string $property): void
    {
        if ($property === 'status') {
            $this->reset('pipeline');
        }
        if (in_array($property, ['search', 'call', 'status', 'attention'], true)) {
            $this->resetPage();
        }
    }

    public function render(ResearchDashboardAnalytics $analytics, DashboardCalendar $calendar): View
    {
        abort_unless(auth()->user()?->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD), 403);
        $callId = ctype_digit($this->call) ? (int) $this->call : null;
        $allowedStatuses = [...array_keys(ResearchDashboardAnalytics::STAGES), 'approved', 'rejected'];
        $summary = [
            'awaiting_review' => $analytics->topics($callId)->whereIn('status', ['pending', 'resubmitted', 'expert_review', 'for_final_decision', 'lrec_queued', 'lrec_review'])->count(),
            'revision_requested' => $analytics->topics($callId)->where('status', 'revision_requested')->count(),
            'approved' => $analytics->topics($callId)->monitoringAvailable()->count(),
        ];
        $deadlines = $calendar->events(auth()->user(), CarbonImmutable::now(), CarbonImmutable::now()->addDays(14)->endOfDay(), $callId)
            ->filter(fn (array $event) => $event['deadline'] && ! $event['draft'])->values();
        $summary['deadlines'] = $deadlines->count();
        $topics = $analytics->topics($callId)
            ->with(['user:id,name', 'researchCall:id,title', 'latestVersion' => fn ($query) => $query->withCount(['files' => fn (Builder $files) => $files->where('document_type', '!=', 'head_upload')])])
            ->when(in_array($this->status, $allowedStatuses, true), fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->pipeline === 'awaiting_review', fn (Builder $query) => $query->whereIn('status', ['pending', 'resubmitted', 'expert_review', 'for_final_decision', 'lrec_queued', 'lrec_review']))
            ->when(in_array($this->pipeline, ['revision_requested', 'ready_for_signature'], true), fn (Builder $query) => $query->where('status', $this->pipeline))
            ->when($this->pipeline === 'awaiting_notice', fn (Builder $query) => $query->where('status', 'approved')->whereNull('notice_to_proceed_issued_at'))
            ->when($this->pipeline === 'approved', fn (Builder $query) => $query->monitoringAvailable())
            ->when($this->attention === 'repeat', fn (Builder $query) => $query->whereIn('status', array_keys(ResearchDashboardAnalytics::STAGES))->whereHas('reviews', fn (Builder $reviews) => $reviews->where('decision', 'revision_requested'), '>=', 2))
            ->when($this->search !== '', function (Builder $query): void {
                $query->where(function (Builder $search): void {
                    $search->where('title', 'like', '%'.$this->search.'%')->orWhere('description', 'like', '%'.$this->search.'%')
                        ->orWhereHas('user', fn (Builder $users) => $users->where('name', 'like', '%'.$this->search.'%'));
                });
            })->latest()->paginate(15)->withQueryString();

        return view('livewire.research-head-dashboard', [
            'topics' => $topics, 'summary' => $summary, 'deadlines' => $deadlines,
            'calls' => ResearchCall::orderByDesc('opens_at')->get(['id', 'title']),
            'analytics' => $analytics->summarize($callId), 'stageLabels' => ResearchDashboardAnalytics::STAGES,
        ]);
    }
}
