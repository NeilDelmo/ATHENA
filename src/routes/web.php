<?php

use App\Http\Controllers\Auth\ProviderController;
use App\Http\Controllers\ConferenceSearchController;
use App\Http\Controllers\ExpertReviewController;
use App\Http\Controllers\FacultyDirectoryController;
use App\Http\Controllers\LiteratureSearchController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectMonitoringController;
use App\Http\Controllers\ProposalDraftController;
use App\Http\Controllers\ProposalDraftCurriculumVitaeController;
use App\Http\Controllers\ProposalDraftDetailedProposalController;
use App\Http\Controllers\ProposalDraftDetailsController;
use App\Http\Controllers\ProposalDraftDocumentVersionController;
use App\Http\Controllers\ProposalDraftExpenseBreakdownController;
use App\Http\Controllers\ProposalDraftGADChecklistController;
use App\Http\Controllers\ProposalDraftInitialScreeningFormController;
use App\Http\Controllers\ProposalDraftLineItemBudgetController;
use App\Http\Controllers\ProposalDraftMemberController;
use App\Http\Controllers\ProposalDraftPaperController;
use App\Http\Controllers\ProposalDraftSubmissionController;
use App\Http\Controllers\ProposalDraftWorkPlanController;
use App\Http\Controllers\ProposalTemplateController;
use App\Http\Controllers\ResearchAssistantController;
use App\Http\Controllers\ResearchCallController;
use App\Http\Controllers\ResearchCoordinatorController;
use App\Http\Controllers\ResearchHeadTopicController;
use App\Http\Controllers\ResearchKnowledgeController;
use App\Http\Controllers\RoleSelectionController;
use App\Http\Controllers\TopicCommentResponseFormController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\WorkPlanController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    $user = Auth::user();
    $dashboardRoute = $user->dashboardRouteName();

    if ($user->hasRole('research_coordinator') && $user->hasAnyRole(['faculty', 'faculty_researcher'])) {
        return match (session('active_role')) {
            'faculty' => redirect()->route('faculty.dashboard'),
            'research_coordinator' => redirect()->route('research_coordinator.dashboard'),
            default => redirect()->route('role-selection.show'),
        };
    }

    if ($user->hasRole('research_coordinator')) {
        return redirect()->route('research_coordinator.dashboard');
    }

    if ($dashboardRoute) {
        return redirect()->route($dashboardRoute);
    }

    Auth::logout();

    return redirect()->route('login')->withErrors([
        'google' => 'Your account does not have an ATHENA role yet. Please contact the system administrator.',
    ]);
})->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/select-role', [RoleSelectionController::class, 'show'])->name('role-selection.show');
    Route::post('/select-role', [RoleSelectionController::class, 'store'])->name('role-selection.store');
    Route::get('/choose-workspace', [WorkspaceController::class, 'index'])->name('workspace.select');
    Route::post('/choose-workspace', [WorkspaceController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('workspace.store');
});

// FACULTY ROUTES
Route::middleware(['auth', 'workspace:faculty|faculty_researcher'])->group(function () {
    Route::get('/faculty/dashboard', [TopicController::class, 'index'])->name('faculty.dashboard');
    Route::get('/faculty/topics/create', [TopicController::class, 'create'])->name('faculty.topics.create');

    Route::prefix('/faculty/proposal-drafts')->name('faculty.proposal-drafts.')->group(function () {
        Route::get('/', [ProposalDraftController::class, 'index'])->name('index');
        Route::get('/create', [ProposalDraftController::class, 'create'])->name('create');
        Route::post('/', [ProposalDraftController::class, 'store'])->name('store');
        Route::get('/{proposalDraft}/details', [ProposalDraftDetailsController::class, 'edit'])->name('details.edit');
        Route::put('/{proposalDraft}/details', [ProposalDraftDetailsController::class, 'update'])->name('details.update');
        Route::get('/{proposalDraft}/detailed-proposal', [ProposalDraftDetailedProposalController::class, 'edit'])->name('detailed-proposal.edit');
        Route::put('/{proposalDraft}/detailed-proposal', [ProposalDraftDetailedProposalController::class, 'update'])->name('detailed-proposal.update');
        Route::post('/{proposalDraft}/detailed-proposal/preview', [ProposalDraftDetailedProposalController::class, 'preview'])->name('detailed-proposal.preview');
        Route::post('/{proposalDraft}/detailed-proposal/download', [ProposalDraftDetailedProposalController::class, 'download'])->name('detailed-proposal.download');
        Route::get('/{proposalDraft}/work-plan', [ProposalDraftWorkPlanController::class, 'edit'])->name('work-plan.edit');
        Route::put('/{proposalDraft}/work-plan', [ProposalDraftWorkPlanController::class, 'update'])->name('work-plan.update');
        Route::post('/{proposalDraft}/work-plan/preview', [ProposalDraftWorkPlanController::class, 'preview'])->name('work-plan.preview');
        Route::post('/{proposalDraft}/work-plan/download', [ProposalDraftWorkPlanController::class, 'download'])->name('work-plan.download');
        Route::get('/{proposalDraft}/line-item-budget', [ProposalDraftLineItemBudgetController::class, 'edit'])->name('line-item-budget.edit');
        Route::put('/{proposalDraft}/line-item-budget', [ProposalDraftLineItemBudgetController::class, 'update'])->name('line-item-budget.update');
        Route::post('/{proposalDraft}/line-item-budget/preview', [ProposalDraftLineItemBudgetController::class, 'preview'])->name('line-item-budget.preview');
        Route::post('/{proposalDraft}/line-item-budget/download', [ProposalDraftLineItemBudgetController::class, 'download'])->name('line-item-budget.download');
        Route::get('/{proposalDraft}/expense-breakdown', [ProposalDraftExpenseBreakdownController::class, 'edit'])->name('expense-breakdown.edit');
        Route::put('/{proposalDraft}/expense-breakdown', [ProposalDraftExpenseBreakdownController::class, 'update'])->name('expense-breakdown.update');
        Route::post('/{proposalDraft}/expense-breakdown/preview', [ProposalDraftExpenseBreakdownController::class, 'preview'])->name('expense-breakdown.preview');
        Route::post('/{proposalDraft}/expense-breakdown/download', [ProposalDraftExpenseBreakdownController::class, 'download'])->name('expense-breakdown.download');
        Route::get('/{proposalDraft}/curriculum-vitae', [ProposalDraftCurriculumVitaeController::class, 'edit'])->name('curriculum-vitae.edit');
        Route::put('/{proposalDraft}/curriculum-vitae', [ProposalDraftCurriculumVitaeController::class, 'update'])->name('curriculum-vitae.update');
        Route::post('/{proposalDraft}/curriculum-vitae/preview', [ProposalDraftCurriculumVitaeController::class, 'preview'])->name('curriculum-vitae.preview');
        Route::post('/{proposalDraft}/curriculum-vitae/download', [ProposalDraftCurriculumVitaeController::class, 'download'])->name('curriculum-vitae.download');
        Route::get('/{proposalDraft}/gad-checklist', [ProposalDraftGADChecklistController::class, 'show'])->name('gad-checklist.show');
        Route::get('/{proposalDraft}/gad-checklist/preview', [ProposalDraftGADChecklistController::class, 'preview'])->name('gad-checklist.preview');
        Route::get('/{proposalDraft}/gad-checklist/download', [ProposalDraftGADChecklistController::class, 'download'])->name('gad-checklist.download');
        Route::get('/{proposalDraft}/initial-screening-form', [ProposalDraftInitialScreeningFormController::class, 'show'])->name('initial-screening-form.show');
        Route::get('/{proposalDraft}/initial-screening-form/preview', [ProposalDraftInitialScreeningFormController::class, 'preview'])->name('initial-screening-form.preview');
        Route::get('/{proposalDraft}/initial-screening-form/download', [ProposalDraftInitialScreeningFormController::class, 'download'])->name('initial-screening-form.download');
        Route::post('/{proposalDraft}/members', [ProposalDraftMemberController::class, 'store'])->name('members.store');
        Route::post('/{proposalDraft}/members/{member}/invitation', [ProposalDraftMemberController::class, 'resend'])->middleware('throttle:6,1')->name('members.invitation');
        Route::delete('/{proposalDraft}/members/{member}', [ProposalDraftMemberController::class, 'destroy'])->name('members.destroy');
        Route::get('/{proposalDraft}/history', [ProposalDraftDocumentVersionController::class, 'index'])->name('history.index');
        Route::get('/{proposalDraft}/history/{documentVersion}/download', [ProposalDraftDocumentVersionController::class, 'download'])->name('history.download');
        Route::post('/{proposalDraft}/history/{documentVersion}/restore', [ProposalDraftDocumentVersionController::class, 'restore'])->name('history.restore');
        Route::get('/{proposalDraft}/papers/{paper}', [ProposalDraftPaperController::class, 'edit'])->name('papers.edit');
        Route::put('/{proposalDraft}/papers/{paper}', [ProposalDraftPaperController::class, 'update'])->name('papers.update');
        Route::get('/{proposalDraft}/papers/{paper}/{document}/download', [ProposalDraftPaperController::class, 'download'])->name('papers.download');
        Route::delete('/{proposalDraft}/papers/{paper}/{document}', [ProposalDraftPaperController::class, 'remove'])->name('papers.remove');
        Route::get('/{proposalDraft}/review', [ProposalDraftSubmissionController::class, 'show'])->name('review');
        Route::post('/{proposalDraft}/submit', [ProposalDraftSubmissionController::class, 'store'])->name('submit');
        Route::get('/{proposalDraft}', [ProposalDraftController::class, 'show'])->name('show');
        Route::delete('/{proposalDraft}', [ProposalDraftController::class, 'destroy'])->name('destroy');
    });

    Route::post('/faculty/work-plans/preview', [WorkPlanController::class, 'preview'])->name('faculty.work-plans.preview');
    Route::post('/faculty/work-plans/download', [WorkPlanController::class, 'download'])->name('faculty.work-plans.download');
    Route::post('/faculty/topics', [TopicController::class, 'store'])->name('faculty.topics');
    Route::get('/faculty/topics/{topic}/comment-response-form/preview', [TopicCommentResponseFormController::class, 'preview'])->name('faculty.topics.comment-response-form.preview');
    Route::get('/faculty/topics/{topic}/comment-response-form/download', [TopicCommentResponseFormController::class, 'download'])->name('faculty.topics.comment-response-form.download');
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
Route::get('/topics/{topic}/versions/{version}/files/{file}/view', [TopicController::class, 'viewVersionFile'])
    ->middleware('auth')
    ->name('topics.versions.files.view');
Route::get('/topics/{topic}/draft-history', [ProposalDraftDocumentVersionController::class, 'archived'])
    ->middleware('auth')
    ->name('topics.draft-history.index');
Route::get('/topics/{topic}/draft-history/{documentVersion}/download', [ProposalDraftDocumentVersionController::class, 'downloadArchived'])
    ->middleware('auth')
    ->name('topics.draft-history.download');
Route::get('/topics/{topic}/approval', [TopicController::class, 'downloadApproval'])
    ->middleware('auth')
    ->name('topics.approval');
Route::get('/topics/{topic}', [TopicController::class, 'show'])
    ->middleware('auth')
    ->name('topics.show');

Route::get('/topics/{topic}/head-uploads', [TopicController::class, 'headUploads'])
    ->middleware(['auth', 'workspace:research_head'])
    ->name('topics.head-uploads.index');
Route::post('/topics/{topic}/head-uploads', [TopicController::class, 'storeHeadUpload'])
    ->middleware(['auth', 'workspace:research_head'])
    ->name('topics.head-uploads.store');

Route::get('/research-calls', [ResearchCallController::class, 'index'])
    ->middleware('auth')
    ->name('research-calls.index');

Route::middleware('auth')->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::patch('/read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
    Route::patch('/{notification}/read', [NotificationController::class, 'markRead'])->name('read');
});

Route::middleware(['auth', 'workspace:faculty_researcher'])->group(function () {
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

Route::middleware(['auth', 'workspace:faculty|faculty_researcher'])->group(function () {
    Route::post('/research-support/literature-search', LiteratureSearchController::class)
        ->middleware('throttle:20,1')
        ->name('research-support.literature-search');
    Route::post('/research-support/conference-search', ConferenceSearchController::class)
        ->middleware('throttle:12,1')
        ->name('research-support.conference-search');
});

// RESEARCH HEAD ROUTES
Route::middleware(['auth', 'workspace:research_head'])->group(function () {
    Route::get('/research-head/dashboard', [ResearchHeadTopicController::class, 'index'])->name('research_head.dashboard');
    Route::get('/research-head/faculty-directory', [FacultyDirectoryController::class, 'index'])->name('research_head.faculty-directory.index');
    Route::patch('/research-head/faculty-directory/{member}/coordinator', [FacultyDirectoryController::class, 'updateCoordinator'])->name('research_head.faculty-directory.coordinator');
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

Route::get('/research-coordinator/dashboard', [ResearchCoordinatorController::class, 'index'])
    ->middleware(['auth', 'role:research_coordinator'])
    ->name('research_coordinator.dashboard');

Route::middleware(['auth', 'workspace:expert'])->group(function () {
    Route::get('/expert/dashboard', [ExpertReviewController::class, 'index'])->name('expert.dashboard');
    Route::patch('/expert/assignments/{assignment}', [ExpertReviewController::class, 'submit'])->name('expert.assignments.submit');
});

// PROFILE ROUTES
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile/college', [ProfileController::class, 'updateCollege'])->name('profile.college.update');
});

// GOOGLE AUTHENTICATION ROUTES
Route::middleware(['guest', 'throttle:20,1'])->group(function () {
    Route::get('/auth/google', [ProviderController::class, 'redirectToGoogle'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [ProviderController::class, 'handleGoogleCallback'])->name('auth.google.callback');
});

require __DIR__.'/auth.php';
