<?php

namespace App\Providers;

use App\Contracts\DocumentPdfConverter;
use App\Models\ProposalDraft;
use App\Models\ResearchAssistantConversation;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\LibreOfficeDocumentPdfConverter;
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
    public function boot(): void
    {
        View::composer('layouts.app', function ($view): void {
            $user = request()->user();

            $history = $user
                ? $user->researchAssistantConversations()
                    ->latest('updated_at')
                    ->get(['id', 'title', 'messages', 'updated_at'])
                    ->map(function (ResearchAssistantConversation $conversation): array {
                        $firstUserMessage = collect($conversation->messages ?? [])->firstWhere('role', 'user');

                        return [
                            'id' => $conversation->id,
                            'title' => $conversation->title,
                            'preview' => Str::limit(Str::squish((string) ($firstUserMessage['content'] ?? $conversation->title)), 160),
                            'updated_at' => $conversation->updated_at?->toISOString(),
                        ];
                    })
                : collect();

            $view->with('researchAssistantHistory', $history);

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
                && $routeDraft instanceof ProposalDraft
                && ProposalDraft::query()->accessibleTo($user)->whereKey($routeDraft->getKey())->exists()
                    ? $routeDraft->getKey()
                    : null;

            $view->with('researchAssistantProposalDraftId', $activeProposalDraftId);

            if (! $user || ! $user->isUsingWorkspace([
                User::WORKSPACE_FACULTY,
                User::WORKSPACE_FACULTY_RESEARCHER,
            ])) {
                $view->with('researchAssistantContexts', collect());
                $view->with('activeResearchAssistantContextId', null);

                return;
            }

            $contexts = $user->proposals()
                ->with(['category', 'researchCall', 'latestVersion'])
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (TopicProposal $topic) => [
                    'id' => $topic->id,
                    'label' => Str::limit($topic->title, 72),
                    'status' => str_replace('_', ' ', $topic->status),
                    'meta' => collect([
                        $topic->category?->name,
                        $topic->researchCall?->academic_year,
                        $topic->latestVersion ? 'v'.$topic->latestVersion->version_number : null,
                    ])->filter()->join(' · '),
                ]);

            $routeTopic = request()->route('topic');
            $activeContextId = match (true) {
                $routeTopic instanceof TopicProposal && $routeTopic->user_id === $user->id => $routeTopic->id,
                $routeDraft instanceof ProposalDraft && $routeDraft->topic?->user_id === $user->id => $routeDraft->topic_id,
                default => null,
            };

            $view->with('researchAssistantContexts', $contexts);
            $view->with('activeResearchAssistantContextId', $activeContextId);
        });
    }
}
