<?php

namespace App\Livewire;

use App\Models\ResearchAnnualTarget;
use App\Models\ResearchCall;
use App\Models\User;
use App\Services\DashboardCalendar;
use App\Services\ResearchDashboardAnalytics;
use App\Services\ResearchHeadAnalytics;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ResearchHeadDashboard extends Component
{
    use WithPagination;

    #[Locked]
    public bool $overview = false;

    #[Url]
    public string $pipeline = '';

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $attention = '';

    #[Url]
    public string $academicYear = '';

    #[Url]
    public string $fromDate = '';

    #[Url]
    public string $toDate = '';

    #[Url]
    public string $projectStatus = '';

    #[Url]
    public string $submissionMonth = '';

    public array $targetForm = ['academic_year' => '', 'starts_on' => '', 'ends_on' => '', 'projects_target' => '', 'publications_target' => '', 'faculty_target' => ''];

    public bool $editingTargets = false;

    public function boot(): void
    {
        abort_unless(auth()->user()?->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD), 403);
    }

    public function mount(bool $overview = false): void
    {
        $this->overview = $overview;
        if ($this->overview && $this->pipeline === '') {
            $this->pipeline = 'awaiting_review';
        }
        if ($this->academicYear !== '' && $this->fromDate === '' && $this->toDate === '') {
            $target = ResearchAnnualTarget::where('academic_year', $this->academicYear)->first();
            $this->fromDate = $target?->starts_on?->toDateString() ?? '';
            $this->toDate = $target?->ends_on?->toDateString() ?? '';
        }
    }

    public function applyFilters(): void
    {
        $this->validateFilters();
        $this->resetDashboardPages();
    }

    private function validateFilters(): void
    {
        $this->filterValidator()->validate();
    }

    private function filterValidator(): \Illuminate\Validation\Validator
    {
        return Validator::make([
            'academicYear' => $this->academicYear, 'fromDate' => $this->fromDate, 'toDate' => $this->toDate, 'submissionMonth' => $this->submissionMonth,
        ], [
            'academicYear' => ['nullable', 'string', 'max:50'],
            'fromDate' => ['nullable', 'date_format:Y-m-d'],
            'toDate' => ['nullable', 'date_format:Y-m-d', ...($this->fromDate !== '' ? ['after_or_equal:fromDate'] : [])],
            'submissionMonth' => ['nullable', 'date_format:Y-m'],
        ])->after(function (\Illuminate\Validation\Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $target = ResearchAnnualTarget::where('academic_year', $this->academicYear)->first();
            $end = CarbonImmutable::parse($this->toDate ?: $target?->ends_on?->toDateString() ?: now()->toDateString());
            $start = CarbonImmutable::parse($this->fromDate ?: $target?->starts_on?->toDateString() ?: $end->startOfMonth()->subMonths(11)->toDateString());
            if ($end->lt($start) || $end->gt($start->addYears(5))) {
                $validator->errors()->add('toDate', 'Choose an end date after the start and a range of five years or less.');
            }
        });
    }

    public function resetAnalyticsFilters(): void
    {
        $this->reset('academicYear', 'fromDate', 'toDate', 'projectStatus', 'submissionMonth', 'pipeline', 'status', 'attention', 'search');
        $this->resetErrorBag();
        $this->resetDashboardPages();
    }

    public function editTargets(): void
    {
        $this->editingTargets = ! $this->editingTargets;
        if (! $this->editingTargets) {
            return;
        }
        $target = ResearchAnnualTarget::where('academic_year', $this->academicYear)->first();
        $this->targetForm = [
            'academic_year' => $target?->academic_year ?? $this->academicYear,
            'starts_on' => $target?->starts_on?->toDateString() ?? '', 'ends_on' => $target?->ends_on?->toDateString() ?? '',
            'projects_target' => $target?->projects_target ?? '', 'publications_target' => $target?->publications_target ?? '',
            'faculty_target' => $target?->faculty_target ?? '',
        ];
    }

    public function saveTargets(): void
    {
        abort_unless(auth()->user()?->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD), 403);
        $this->targetForm['academic_year'] = trim($this->targetForm['academic_year'] ?? '');
        $validated = $this->validate([
            'targetForm.academic_year' => ['required', 'string', 'max:50'],
            'targetForm.starts_on' => ['required', 'date_format:Y-m-d'],
            'targetForm.ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:targetForm.starts_on'],
            'targetForm.projects_target' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'targetForm.publications_target' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'targetForm.faculty_target' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ])['targetForm'];
        $this->validate(['targetForm.ends_on' => ['before_or_equal:'.CarbonImmutable::parse($validated['starts_on'])->addYears(2)->toDateString()]]);
        foreach (['projects_target', 'publications_target', 'faculty_target'] as $key) {
            $validated[$key] = $validated[$key] === '' ? null : ($validated[$key] ?? null);
        }
        ResearchAnnualTarget::updateOrCreate(['academic_year' => trim($validated['academic_year'])], [...$validated, 'academic_year' => trim($validated['academic_year']), 'updated_by' => auth()->id()]);
        $this->academicYear = trim($validated['academic_year']);
        $this->fromDate = $validated['starts_on'];
        $this->toDate = $validated['ends_on'];
        $this->resetDashboardPages();
        session()->flash('targets_saved', 'Academic-year targets saved.');
    }

    public function showProjects(string $status): void
    {
        $this->projectStatus = in_array($status, ['active', 'ongoing', 'delayed', 'awaiting', 'completed'], true) ? $status : '';
        $this->resetPage('projectPage');
    }

    private function resetDashboardPages(): void
    {
        $this->resetPage();
        $this->resetPage('attentionPage');
        $this->resetPage('projectPage');
    }

    public function setPipeline(string $pipeline): void
    {
        $this->pipeline = $pipeline === $this->pipeline ? '' : $pipeline;
        $this->reset('status', 'attention');
        $this->resetPage();
    }

    public function showReviewQueue(): void
    {
        $this->pipeline = 'awaiting_review';
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
        if ($property === 'academicYear') {
            $target = ResearchAnnualTarget::where('academic_year', $this->academicYear)->first();
            $this->fromDate = $target?->starts_on?->toDateString() ?? '';
            $this->toDate = $target?->ends_on?->toDateString() ?? '';
            $this->reset('submissionMonth', 'projectStatus');
        }
        if ($property === 'status') {
            $this->reset('pipeline');
        }
        if ($property === 'submissionMonth') {
            $this->reset('pipeline', 'status', 'attention', 'search');
        }
        if (in_array($property, ['search', 'status', 'attention', 'academicYear', 'fromDate', 'toDate', 'submissionMonth'], true)) {
            $this->resetPage();
            $this->resetPage('attentionPage');
            $this->resetPage('projectPage');
        }
        if ($property === 'projectStatus') {
            $this->resetPage('projectPage');
        }
    }

    public function render(ResearchHeadAnalytics $analytics, DashboardCalendar $calendar): View
    {
        abort_unless(auth()->user()?->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD), 403);
        $callId = null;
        $allowedStatuses = array_keys(ResearchDashboardAnalytics::STAGES);
        $filterValidation = $this->filterValidator();
        $validFilters = $filterValidation->passes();
        if (! $validFilters) {
            $this->setErrorBag($filterValidation->errors());
        } else {
            $this->resetErrorBag(['academicYear', 'fromDate', 'toDate', 'submissionMonth']);
        }
        $year = $validFilters ? $this->academicYear : '__invalid_filters__';
        $from = $validFilters ? $this->fromDate : '';
        $to = $validFilters ? $this->toDate : '';
        $base = $analytics->topics($year, $from, $to);
        $data = $analytics->summarize($year, $from, $to);
        $monthStart = $validFilters && $this->submissionMonth !== '' ? CarbonImmutable::parse($this->submissionMonth.'-01')->startOfMonth()->max(CarbonImmutable::parse($data['trendStart'])->startOfDay()) : null;
        $monthEnd = $validFilters && $this->submissionMonth !== '' ? CarbonImmutable::parse($this->submissionMonth.'-01')->endOfMonth()->min(CarbonImmutable::parse($data['trendEnd'])->endOfDay()) : null;
        $deadlines = $calendar->events(auth()->user(), CarbonImmutable::now(), CarbonImmutable::now()->addDays(14)->endOfDay(), $callId)
            ->filter(fn (array $event) => $event['deadline'] && ! $event['draft'])->values();
        $topics = ($validFilters && $this->submissionMonth !== '' ? $analytics->topics($year) : clone $base)
            ->when($this->submissionMonth === '', fn (Builder $query) => $query->whereIn('status', in_array($this->pipeline, ['approved', 'signing'], true) ? [...$allowedStatuses, 'approved'] : $allowedStatuses))
            ->with(['user:id,name', 'researchCall:id,title', 'latestVersion' => fn ($query) => $query
                ->with('files')
                ->withCount(['files' => fn (Builder $files) => $files->where('document_type', '!=', 'head_upload')])])
            ->when(in_array($this->status, $allowedStatuses, true), fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->pipeline !== '', fn (Builder $query) => $analytics->filterPipeline($query, $this->pipeline))
            ->when($monthStart !== null, fn (Builder $query) => $query->whereHas('versions', fn (Builder $versions) => $versions->whereBetween('created_at', [$monthStart, $monthEnd])))
            ->when($this->attention === 'repeat', fn (Builder $query) => $query->whereIn('status', array_keys(ResearchDashboardAnalytics::STAGES))->whereHas('reviews', fn (Builder $reviews) => $reviews->where('decision', 'revision_requested'), '>=', 2))
            ->when($this->search !== '', function (Builder $query): void {
                $query->where(function (Builder $search): void {
                    $search->where('title', 'like', '%'.$this->search.'%')->orWhere('description', 'like', '%'.$this->search.'%')
                        ->orWhereHas('user', fn (Builder $users) => $users->where('name', 'like', '%'.$this->search.'%'));
                });
            })->latest()->paginate($this->overview ? 4 : 5)->withQueryString();

        $projectRows = $data['projects']->when($this->projectStatus !== '', fn (Collection $rows) => $this->projectStatus === 'active' ? $rows->where('status', '!=', 'completed') : $rows->where('status', $this->projectStatus))->values();
        $attentionRows = $this->overview
            ? $data['attention']->reject(fn (array $issue): bool => $issue['type'] === 'head_review')->values()
            : $data['attention'];

        return view($this->overview ? 'livewire.research-head-overview' : 'livewire.research-head-dashboard', [
            'topics' => $topics, 'deadlines' => $deadlines,
            'analytics' => $data, 'stageLabels' => ResearchDashboardAnalytics::STAGES,
            'attentionItems' => $this->paginateRows($attentionRows, 'attentionPage', $this->overview ? 4 : 5),
            'projectItems' => $this->paginateRows($projectRows, 'projectPage', 6),
            'academicYears' => ResearchCall::whereNotNull('academic_year')->pluck('academic_year')->merge(ResearchAnnualTarget::pluck('academic_year'))->unique()->sortDesc()->values(),
        ]);
    }

    private function paginateRows(Collection $rows, string $pageName, int $perPage): LengthAwarePaginator
    {
        $page = max(1, (int) $this->getPage($pageName));

        return new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page, ['path' => route($this->overview ? 'research_head.dashboard' : 'research_head.analytics'), 'pageName' => $pageName]);
    }
}
