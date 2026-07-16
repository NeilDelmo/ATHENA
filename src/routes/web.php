<?php

use App\Http\Controllers\Auth\ProviderController;
use App\Http\Controllers\ConferenceSearchController;
use App\Http\Controllers\ExpertReviewController;
use App\Http\Controllers\LiteratureSearchController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectMonitoringController;
use App\Http\Controllers\ProposalTemplateController;
use App\Http\Controllers\ResearchAssistantController;
use App\Http\Controllers\ResearchCallController;
use App\Http\Controllers\ResearchHeadTopicController;
use App\Http\Controllers\ResearchKnowledgeController;
use App\Http\Controllers\TopicController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    $user = Auth::user();

    if ($user->hasRole('research_head')) {
        return redirect()->route('research_head.dashboard');
    }

    if ($user->hasRole('expert')) {
        return redirect()->route('expert.dashboard');
    }

    if ($user->hasAnyRole(['faculty', 'faculty_researcher'])) {
        return redirect()->route('faculty.dashboard');
    }

    Auth::logout();

    return redirect()->route('login')->withErrors([
        'google' => 'Your account does not have an ATHENA role yet. Please contact the system administrator.',
    ]);
})->middleware('auth')->name('dashboard');

// FACULTY ROUTES
Route::middleware(['auth', 'role:faculty|faculty_researcher'])->group(function () {
    Route::get('/faculty/dashboard', [TopicController::class, 'index'])->name('faculty.dashboard');
    Route::get('/faculty/topics/create', [TopicController::class, 'create'])->name('faculty.topics.create');
    Route::post('/faculty/topics', [TopicController::class, 'store'])->name('faculty.topics');
    Route::patch('/faculty/topics/{topic}/resubmit', [TopicController::class, 'resubmit'])->name('faculty.topics.resubmit');
});

Route::get('/proposal-templates/{proposalTemplate}/download', [ProposalTemplateController::class, 'download'])
    ->middleware('auth')
    ->name('proposal-templates.download');
Route::get('/proposal-samples/{sample}', [ProposalTemplateController::class, 'showSample'])
    ->middleware('auth')
    ->where('sample', '[a-z0-9-]+')
    ->name('proposal-samples.show');

Route::get('/topics/{topic}/download', [TopicController::class, 'download'])
    ->middleware(['auth'])
    ->name('topics.download');
Route::get('/topics/{topic}/versions/{version}/download', [TopicController::class, 'downloadVersion'])
    ->middleware('auth')
    ->name('topics.versions.download');
Route::get('/topics/{topic}/versions/{version}/files/{file}/download', [TopicController::class, 'downloadVersionFile'])
    ->middleware('auth')
    ->name('topics.versions.files.download');
Route::get('/topics/{topic}/approval', [TopicController::class, 'downloadApproval'])
    ->middleware('auth')
    ->name('topics.approval');
Route::get('/topics/{topic}', [TopicController::class, 'show'])
    ->middleware('auth')
    ->name('topics.show');

Route::get('/research-calls', [ResearchCallController::class, 'index'])
    ->middleware('auth')
    ->name('research-calls.index');

Route::middleware('auth')->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::patch('/read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
    Route::patch('/{notification}/read', [NotificationController::class, 'markRead'])->name('read');
});

Route::middleware(['auth', 'role:faculty_researcher'])->group(function () {
    Route::get('/research', [TopicController::class, 'researchIndex'])->name('research.index');
    Route::get('/research/{topic}', [TopicController::class, 'researchShow'])->name('research.show');
    Route::post('/research/{topic}/progress-reports', [ProjectMonitoringController::class, 'store'])->name('project-progress.store');
});

Route::get('/progress-reports/{report}/attachment', [ProjectMonitoringController::class, 'download'])
    ->middleware('auth')
    ->name('project-progress.download');

Route::middleware('auth')->group(function () {
    Route::view('/research-support', 'faculty.research_support.index')->name('research-support.index');
    Route::post('/research-support/chat', ResearchAssistantController::class)
        ->middleware('throttle:12,1')
        ->name('research-support.chat');
});

Route::middleware(['auth', 'role:faculty|faculty_researcher'])->group(function () {
    Route::post('/research-support/literature-search', LiteratureSearchController::class)
        ->middleware('throttle:20,1')
        ->name('research-support.literature-search');
    Route::post('/research-support/conference-search', ConferenceSearchController::class)
        ->middleware('throttle:12,1')
        ->name('research-support.conference-search');
});

// RESEARCH HEAD ROUTES
Route::middleware(['auth', 'role:research_head'])->group(function () {
    Route::get('/research-head/dashboard', [ResearchHeadTopicController::class, 'index'])->name('research_head.dashboard');
    Route::get('/research-head/projects', [ProjectMonitoringController::class, 'index'])->name('research_head.projects.index');
    Route::patch('/research-head/topics/{topic}/status', [ResearchHeadTopicController::class, 'updateStatus'])->name('research_head.topics.updateStatus');
    Route::patch('/research-head/projects/{topic}/status', [ProjectMonitoringController::class, 'updateProjectStatus'])->name('research_head.projects.update-status');
    Route::patch('/research-head/progress-reports/{report}', [ProjectMonitoringController::class, 'review'])->name('research_head.progress-reports.review');
    Route::post('/research-calls', [ResearchCallController::class, 'store'])->name('research-calls.store');
    Route::patch('/research-calls/{researchCall}/status', [ResearchCallController::class, 'updateStatus'])->name('research-calls.update-status');
    Route::get('/research-head/proposal-templates', [ProposalTemplateController::class, 'index'])->name('research_head.proposal-templates.index');
    Route::post('/research-head/proposal-templates', [ProposalTemplateController::class, 'store'])->name('research_head.proposal-templates.store');
    Route::put('/research-head/proposal-templates/{proposalTemplate}', [ProposalTemplateController::class, 'update'])->name('research_head.proposal-templates.update');
    Route::patch('/research-head/proposal-templates/{proposalTemplate}/status', [ProposalTemplateController::class, 'updateStatus'])->name('research_head.proposal-templates.status');
    Route::get('/research-head/assistant-knowledge', [ResearchKnowledgeController::class, 'index'])->name('research_head.assistant-knowledge.index');
    Route::post('/research-head/assistant-knowledge', [ResearchKnowledgeController::class, 'store'])->name('research_head.assistant-knowledge.store');
    Route::put('/research-head/assistant-knowledge/{researchKnowledgeEntry}', [ResearchKnowledgeController::class, 'update'])->name('research_head.assistant-knowledge.update');
    Route::patch('/research-head/assistant-knowledge/{researchKnowledgeEntry}/status', [ResearchKnowledgeController::class, 'updateStatus'])->name('research_head.assistant-knowledge.status');
});

Route::middleware(['auth', 'role:expert'])->group(function () {
    Route::get('/expert/dashboard', [ExpertReviewController::class, 'index'])->name('expert.dashboard');
    Route::patch('/expert/assignments/{assignment}', [ExpertReviewController::class, 'submit'])->name('expert.assignments.submit');
});

// PROFILE ROUTES
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
});

// GOOGLE AUTHENTICATION ROUTES
Route::middleware(['guest', 'throttle:20,1'])->group(function () {
    Route::get('/auth/google', [ProviderController::class, 'redirectToGoogle'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [ProviderController::class, 'handleGoogleCallback'])->name('auth.google.callback');
});

require __DIR__.'/auth.php';
