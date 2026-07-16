<?php

namespace App\Providers;

use App\Models\TopicProposal;
use App\Models\User;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', function ($view): void {
            $user = request()->user();

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
            $activeContextId = $routeTopic instanceof TopicProposal && $routeTopic->user_id === $user->id
                ? $routeTopic->id
                : null;

            $view->with('researchAssistantContexts', $contexts);
            $view->with('activeResearchAssistantContextId', $activeContextId);
        });
    }
}
