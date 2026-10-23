<?php

namespace App\Providers;

use App\Contracts\DocumentPdfConverter;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Models\TopicProposal;
use App\Models\User;
use App\Observers\ProposalPdfPreparationObserver;
use App\Services\LibreOfficeDocumentPdfConverter;
use App\Services\NotificationInbox;
use App\Services\ProposalPdfPreparation;
use App\Services\ResearchAssistantContexts;
use App\Services\ResearchHeadReportQueue;
use App\Services\SidebarAttentionService;
use App\Services\WorkspaceActionItems;
use App\Support\ResearchCallDeadlineNotice;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\JobPopping;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DocumentPdfConverter::class, LibreOfficeDocumentPdfConverter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(
        SidebarAttentionService $sidebarAttention,
        ResearchCallDeadlineNotice $researchCallDeadlineNotice,
    ): void {
        DB::listen(function (QueryExecuted $query): void {
            $metrics = request()->attributes->get('athena.request_performance');
            if ($metrics !== null) {
                $metrics->query_count++;
                $metrics->database_ms += $query->time;
            }
        });

        ProposalDraft::observe(ProposalPdfPreparationObserver::class);
        ProposalDraftDocument::observe(ProposalPdfPreparationObserver::class);

        Event::listen(JobPopping::class, function (JobPopping $event): void {
            if ($event->connectionName === 'proposal-pdfs') {
                app(ProposalPdfPreparation::class)->heartbeat();
            }
        });

        View::composer('layouts.navigation', function ($view) use ($sidebarAttention): void {
            $user = request()->user();

            $view->with('sidebarAttentionCounts', $user
                ? $sidebarAttention->countsFor($user)
                : []);
            $view->with('reportReviewCount', $user?->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD)
                ? app(ResearchHeadReportQueue::class)->pendingCount()
                : 0);
        });

        View::composer('components.notification-menu', function ($view): void {
            $user = auth()->user();
            $actions = request()->attributes->get('athena.workspace_actions') ?? app(WorkspaceActionItems::class)->for($user);
            $items = app(NotificationInbox::class)->for($user, $actions, recentOnly: true);
            $view->with('notificationItems', $items->take(15)->map(fn (array $item): array => Arr::except($item, 'history')));
            $view->with('unreadNotificationCount', request()->attributes->get('athena.unread_notification_count')
                ?? $user->visibleNotificationsQuery()->whereNull('read_at')->count());
        });

        View::composer('layouts.app', function ($view) use ($researchCallDeadlineNotice): void {
            $user = request()->user();

            $view->with(
                'researchCallDeadlineNotice',
                $researchCallDeadlineNotice->forUser($user),
            );

            $view->with('researchAssistantHistory', []);

            $paperSlug = collect(config('proposal_field_guidance.route_patterns', []))
                ->first(fn (string $configuredPaperSlug, string $routePattern): bool => request()->routeIs($routePattern));
            $paperGuide = is_string($paperSlug)
                ? config('proposal_field_guidance.papers.'.$paperSlug)
                : null;

            $view->with('researchAssistantPaperContext', is_array($paperGuide)
                ? [
                    'paper_slug' => $paperSlug,
                    'paper_label' => $paperGuide['label'] ?? Str::headline($paperSlug),
                ]
                : null);

            $routeDraft = request()->route('proposalDraft');
            $activeProposalDraftId = $user
                && $user->isUsingWorkspace(User::WORKSPACE_FACULTY)
                && $routeDraft instanceof ProposalDraft
                && ProposalDraft::query()->accessibleTo($user)->whereKey($routeDraft->getKey())->exists()
                    ? $routeDraft->getKey()
                    : null;

            $view->with('researchAssistantProposalDraftId', $activeProposalDraftId);
            $routeTopic = request()->route('topic') ?? request()->attributes->get('journalFinderTopic');

            if (! $user || ! $user->isUsingWorkspace([
                User::WORKSPACE_FACULTY,
                User::WORKSPACE_FACULTY_RESEARCHER,
                User::WORKSPACE_RESEARCH_HEAD,
            ])) {
                $view->with('researchAssistantContexts', collect());
                $view->with('activeResearchAssistantContextId', null);
                $view->with('researchAssistantPageActions', collect());

                return;
            }

            $view->with(
                'researchAssistantPageActions',
                $this->assistantPageActions($user, $activeProposalDraftId, $paperSlug, $routeTopic),
            );

            $draftTopic = $activeProposalDraftId && $routeDraft instanceof ProposalDraft
                ? $routeDraft->topic
                : null;
            $activeContextTopic = match (true) {
                $routeTopic instanceof TopicProposal && $user->can('view', $routeTopic) => $routeTopic,
                $draftTopic instanceof TopicProposal && $user->can('view', $draftTopic) => $draftTopic,
                default => null,
            };

            $contexts = $activeContextTopic
                ? collect([app(ResearchAssistantContexts::class)->summary($activeContextTopic, $user)])
                : collect();
            $view->with('researchAssistantContexts', $contexts);
            $view->with('activeResearchAssistantContextId', $activeContextTopic?->getKey());
        });
    }

    /**
     * @return list<array{label: string, description: string, evidence: string, prompt: string, scope?: string}>
     */
    private function assistantPageActions(
        User $user,
        ?int $proposalDraftId,
        ?string $paperSlug,
        mixed $routeTopic,
    ): array {
        if ($proposalDraftId && is_string($paperSlug)) {
            $paperLabel = (string) data_get(
                config('proposal_field_guidance.papers.'.$paperSlug),
                'label',
                Str::headline($paperSlug),
            );

            $actions = [
                [
                    'label' => 'Review saved paper',
                    'description' => 'Check this paper for incomplete, conflicting, or unclear information.',
                    'evidence' => 'Uses saved proposal data and current-paper rules',
                    'prompt' => "Review the saved proposal and the current {$paperLabel}. Identify incomplete, conflicting, or unclear information. Give a short prioritized checklist and do not invent missing facts.",
                ],
                [
                    'label' => 'Check linked values',
                    'description' => 'Find saved values that should agree across the proposal papers.',
                    'evidence' => 'Uses saved proposal values and official paper relationships',
                    'prompt' => "Check the saved {$paperLabel} against its linked proposal papers. Identify values that should agree, any saved mismatch that is available, and the correct paper to update. Do not change anything automatically.",
                ],
            ];

            if ($paperSlug === 'detailed-proposal') {
                $actions[] = [
                    'label' => 'Check methods and evidence',
                    'description' => 'Identify what factual method or evidence information still needs to be supplied.',
                    'evidence' => 'Uses saved detailed-proposal values and paper relationships',
                    'prompt' => 'Assess the saved methodology and evidence sections in this detailed proposal. Point out only gaps supported by the saved record, then tell me what factual information I need to supply. Do not invent research details or citations.',
                ];
            }

            return $actions;
        }

        if (! request()->routeIs('topics.show', 'research.show')
            || ! $routeTopic instanceof TopicProposal
            || ! $user->can('view', $routeTopic)) {
            return [];
        }

        return [
            [
                'scope' => 'details',
                'label' => 'Check proposal next steps',
                'description' => 'Summarize the proposal status and the next concrete action.',
                'evidence' => 'Uses the saved proposal status and submission record',
                'prompt' => 'Review this saved proposal record. State its current status, the next concrete action, and any saved information that still needs attention. Do not infer an approval decision or missing institutional rule.',
            ],
            [
                'scope' => 'review',
                'label' => 'Make a revision plan',
                'description' => 'Turn reviewer feedback into a clear, prioritized checklist.',
                'evidence' => 'Uses saved reviewer comments for this proposal',
                'prompt' => 'Turn the saved reviewer comments for this proposal into a precise revision plan. Separate required changes from recommendations, put the work in a sensible order, and do not invent reviewer feedback.',
            ],
            [
                'scope' => 'monitoring',
                'label' => 'Summarize project progress',
                'description' => 'Create a status summary from submitted monitoring and narrative reports.',
                'evidence' => 'Uses saved monitoring tools, progress reports, and remarks',
                'prompt' => 'Draft a concise project-status summary from the saved monitoring-tool and narrative-report records. State progress, accomplishments, issues or delays, pending review items, and missing information without inventing details.',
            ],
        ];
    }
}
