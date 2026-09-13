<?php

use App\Http\Controllers\AnnouncementImageController;
use App\Http\Controllers\Auth\ProviderController;
use App\Http\Controllers\ConferenceSearchController;
use App\Http\Controllers\FacultyDirectoryController;
use App\Http\Controllers\JournalSearchController;
use App\Http\Controllers\LiteratureCollectionController;
use App\Http\Controllers\LiteratureFullTextPreviewController;
use App\Http\Controllers\LiteratureSearchController;
use App\Http\Controllers\LiteratureSourceController;
use App\Http\Controllers\LiteratureSynthesisController;
use App\Http\Controllers\NoticeToProceedController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectDisseminationController;
use App\Http\Controllers\ProjectMonitoringController;
use App\Http\Controllers\ProjectNarrativeReportController;
use App\Http\Controllers\ProposalDraftController;
use App\Http\Controllers\ProposalDraftCurriculumVitaeController;
use App\Http\Controllers\ProposalDraftDetailedProposalController;
use App\Http\Controllers\ProposalDraftDetailsController;
use App\Http\Controllers\ProposalDraftDocumentVersionController;
use App\Http\Controllers\ProposalDraftEditStateController;
use App\Http\Controllers\ProposalDraftExpenseBreakdownController;
use App\Http\Controllers\ProposalDraftGADChecklistController;
use App\Http\Controllers\ProposalDraftInitialScreeningFormController;
use App\Http\Controllers\ProposalDraftLineItemBudgetController;
use App\Http\Controllers\ProposalDraftLiteratureSourceController;
use App\Http\Controllers\ProposalDraftMemberController;
use App\Http\Controllers\ProposalDraftPaperController;
use App\Http\Controllers\ProposalDraftSubmissionController;
use App\Http\Controllers\ProposalDraftWorkPlanController;
use App\Http\Controllers\ProposalFileAnnotationController;
use App\Http\Controllers\ProposalSignatoryController;
use App\Http\Controllers\ProposalSimilarityCheckController;
use App\Http\Controllers\ProposalTemplateController;
use App\Http\Controllers\ResearchAssistantController;
use App\Http\Controllers\ResearchAssistantDocumentController;
use App\Http\Controllers\ResearchCallController;
use App\Http\Controllers\ResearchCallDeadlineDismissalController;
use App\Http\Controllers\ResearchCoordinatorController;
use App\Http\Controllers\ResearchHeadProposalSubmissionController;
use App\Http\Controllers\ResearchHeadTopicController;
use App\Http\Controllers\ResearchKnowledgeController;
use App\Http\Controllers\ResearchSupportController;
use App\Http\Controllers\RoleSelectionController;
use App\Http\Controllers\SidebarAttentionController;
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
});

Route::middleware(['auth', 'workspace:faculty'])->group(function () {
    Route::get('/faculty/topics/create', [TopicController::class, 'create'])->name('faculty.topics.create');

    Route::prefix('/faculty/proposal-drafts')->name('faculty.proposal-drafts.')->group(function () {
        Route::get('/', [ProposalDraftController::class, 'index'])->name('index');
        Route::get('/create', [ProposalDraftController::class, 'create'])->name('create');
        Route::post('/', [ProposalDraftController::class, 'store'])->name('store');
        Route::get('/revision/{topic}', [ProposalDraftController::class, 'revision'])->name('revision');
        Route::post('/{proposalDraft}/revision-files', [ProposalDraftController::class, 'storeRevisionFile'])->name('revision-files.store');
        Route::get('/{proposalDraft}/details', [ProposalDraftDetailsController::class, 'edit'])->name('details.edit');
        Route::put('/{proposalDraft}/details', [ProposalDraftDetailsController::class, 'update'])->name('details.update');
        Route::get('/{proposalDraft}/detailed-proposal', [ProposalDraftDetailedProposalController::class, 'edit'])->name('detailed-proposal.edit');
        Route::put('/{proposalDraft}/detailed-proposal', [ProposalDraftDetailedProposalController::class, 'update'])->name('detailed-proposal.update');
        Route::get('/{proposalDraft}/detailed-proposal/methodology-images/{imageId}', [ProposalDraftDetailedProposalController::class, 'methodologyImage'])->name('detailed-proposal.methodology-images.show');
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
        Route::get('/{proposalDraft}/edit-state/{scope}/{position}', ProposalDraftEditStateController::class)
            ->whereNumber('position')
            ->name('edit-state');
        Route::get('/{proposalDraft}/papers/{paper}', [ProposalDraftPaperController::class, 'edit'])->name('papers.edit');
        Route::put('/{proposalDraft}/papers/{paper}', [ProposalDraftPaperController::class, 'update'])->name('papers.update');
        Route::get('/{proposalDraft}/papers/{paper}/{document}/download', [ProposalDraftPaperController::class, 'download'])->name('papers.download');
        Route::delete('/{proposalDraft}/papers/{paper}/{document}', [ProposalDraftPaperController::class, 'remove'])->name('papers.remove');
        Route::get('/{proposalDraft}/review', [ProposalDraftSubmissionController::class, 'show'])->name('review');
        Route::post('/{proposalDraft}/submission-files/prepare', [ProposalDraftSubmissionController::class, 'prepare'])->name('submission-files.prepare');
        Route::get('/{proposalDraft}/submission-files/{paper}', [ProposalDraftSubmissionController::class, 'download'])->name('submission-files.download');
        Route::put('/{proposalDraft}/submission-files/{paper}', [ProposalDraftSubmissionController::class, 'replace'])->name('submission-files.replace');
        Route::post('/{proposalDraft}/submit', [ProposalDraftSubmissionController::class, 'store'])->name('submit');
        Route::get('/{proposalDraft}', [ProposalDraftController::class, 'show'])->name('show');
        Route::delete('/{proposalDraft}', [ProposalDraftController::class, 'destroy'])->name('destroy');
    });

    Route::post('/faculty/work-plans/preview', [WorkPlanController::class, 'preview'])->name('faculty.work-plans.preview');
    Route::post('/faculty/work-plans/download', [WorkPlanController::class, 'download'])->name('faculty.work-plans.download');
    Route::post('/faculty/topics', [TopicController::class, 'store'])->name('faculty.topics');
    Route::patch('/faculty/topics/{topic}/resubmit', [TopicController::class, 'resubmit'])->name('faculty.topics.resubmit');
});

Route::middleware(['auth', 'workspace:faculty|faculty_researcher|research_head'])->group(function () {
    Route::get('/faculty/topics/{topic}/comment-response-form/preview', [TopicCommentResponseFormController::class, 'preview'])->name('faculty.topics.comment-response-form.preview');
    Route::get('/faculty/topics/{topic}/comment-response-form/pdf', [TopicCommentResponseFormController::class, 'downloadPdf'])->name('faculty.topics.comment-response-form.pdf');
    Route::get('/faculty/topics/{topic}/comment-response-form/download', [TopicCommentResponseFormController::class, 'download'])->name('faculty.topics.comment-response-form.download');
});

Route::middleware(['auth', 'workspace:research_head'])->group(function () {
    Route::get('/research-head/topics/{topic}/comment-response-form/pdf', [TopicCommentResponseFormController::class, 'downloadPdf'])->name('research_head.topics.comment-response-form.pdf');
    Route::get('/research-head/topics/{topic}/comment-response-form/download', [TopicCommentResponseFormController::class, 'download'])->name('research_head.topics.comment-response-form.download');
});

Route::get('/proposal-templates/{proposalTemplate}/download', [ProposalTemplateController::class, 'download'])
    ->middleware(['auth', 'workspace:faculty|research_head'])
    ->name('proposal-templates.download');
Route::get('/proposal-samples/{sample}', [ProposalTemplateController::class, 'showSample'])
    ->middleware(['auth', 'workspace:faculty|research_head'])
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
Route::get('/topics/{topic}/versions/{version}/files/{file}/annotations', [ProposalFileAnnotationController::class, 'index'])
    ->middleware('auth')
    ->name('topics.versions.files.annotations.index');
Route::post('/topics/{topic}/versions/{version}/files/{file}/annotations', [ProposalFileAnnotationController::class, 'store'])
    ->middleware(['auth', 'workspace:research_head'])
    ->name('topics.versions.files.annotations.store');
Route::patch('/topics/{topic}/versions/{version}/files/{file}/annotations/{annotation}', [ProposalFileAnnotationController::class, 'update'])
    ->middleware(['auth', 'workspace:research_head'])
    ->name('topics.versions.files.annotations.update');
Route::delete('/topics/{topic}/versions/{version}/files/{file}/annotations/{annotation}', [ProposalFileAnnotationController::class, 'destroy'])
    ->middleware(['auth', 'workspace:research_head'])
    ->name('topics.versions.files.annotations.destroy');
Route::get('/topics/{topic}/draft-history', [ProposalDraftDocumentVersionController::class, 'archived'])
    ->middleware(['auth', 'workspace:faculty|research_head'])
    ->name('topics.draft-history.index');
Route::get('/topics/{topic}/draft-history/{documentVersion}/download', [ProposalDraftDocumentVersionController::class, 'downloadArchived'])
    ->middleware(['auth', 'workspace:faculty|research_head'])
    ->name('topics.draft-history.download');
Route::get('/topics/{topic}/approval', [TopicController::class, 'downloadApproval'])
    ->middleware('auth')
    ->name('topics.approval');
Route::get('/topics/{topic}/notice-to-proceed', [NoticeToProceedController::class, 'download'])
    ->middleware('auth')
    ->name('topics.notice-to-proceed.download');
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
    ->middleware(['auth', 'workspace:faculty|research_head'])
    ->name('research-calls.index');
Route::get('/research-calls/{researchCall}/reference-image', [ResearchCallController::class, 'sourceImage'])
    ->middleware(['auth', 'workspace:faculty|research_head'])
    ->name('research-calls.reference-image');

Route::middleware(['auth', 'workspace:research_head'])->prefix('announcement-images')->name('announcement-images.')->group(function () {
    Route::get('/', [AnnouncementImageController::class, 'index'])->name('index');
    Route::post('/', [AnnouncementImageController::class, 'store'])->name('store');
    Route::patch('/{announcementImage}', [AnnouncementImageController::class, 'update'])->name('update');
    Route::patch('/{announcementImage}/archive', [AnnouncementImageController::class, 'archive'])->name('archive');
    Route::patch('/{announcementImage}/restore', [AnnouncementImageController::class, 'restore'])->name('restore');
});
Route::get('/announcement-images/{announcementImage}/source', [AnnouncementImageController::class, 'show'])
    ->middleware('auth')
    ->name('announcement-images.show');

Route::middleware('auth')->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::patch('/read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
    Route::get('/proposal-invitations/{proposalDraftMember}', [NotificationController::class, 'showProposalInvitation'])
        ->name('proposal-invitations.show');
    Route::post('/proposal-invitations/{proposalDraftMember}/accept', [NotificationController::class, 'acceptProposalInvitation'])
        ->name('proposal-invitations.accept');
    Route::post('/{notification}/open', [NotificationController::class, 'open'])->name('open');
    Route::patch('/{notification}/read', [NotificationController::class, 'markRead'])->name('read');
});

Route::post('/research-calls/{researchCall}/deadline-dismissal', [ResearchCallDeadlineDismissalController::class, 'store'])
    ->middleware(['auth', 'throttle:30,1'])
    ->name('research-calls.deadline-dismissal.store');

Route::post('/sidebar-attention/{area}', [SidebarAttentionController::class, 'open'])
    ->middleware('auth')
    ->name('sidebar-attention.open');

Route::middleware(['auth', 'workspace:faculty_researcher'])->group(function () {
    Route::get('/research', [TopicController::class, 'researchIndex'])->name('research.index');
    Route::get('/research/{topic}', [TopicController::class, 'researchShow'])->name('research.show');
    Route::get('/research/{topic}/monitoring-tool', [ProjectMonitoringController::class, 'create'])->name('project-progress.create');
    Route::get('/research/{topic}/progress-report', [ProjectNarrativeReportController::class, 'create'])->name('project-narrative-reports.create');
    Route::post('/research/{topic}/progress-reports/preview', [ProjectMonitoringController::class, 'preview'])->name('project-progress.preview');
    Route::post('/research/{topic}/progress-reports/draft', [ProjectMonitoringController::class, 'saveDraft'])->name('project-progress.draft');
    Route::post('/research/{topic}/progress-reports/prepare', [ProjectMonitoringController::class, 'prepare'])->name('project-progress.prepare');
    Route::post('/research/{topic}/progress-reports', [ProjectMonitoringController::class, 'prepare'])->name('project-progress.store');
    Route::post('/research/{topic}/progress-reports/{report}/submit', [ProjectMonitoringController::class, 'submitPrepared'])->name('project-progress.submit-prepared');
    Route::delete('/research/{topic}/progress-reports/{report}/prepared', [ProjectMonitoringController::class, 'discardPrepared'])->name('project-progress.discard-prepared');
    Route::post('/research/{topic}/narrative-progress-reports/preview', [ProjectNarrativeReportController::class, 'preview'])->name('project-narrative-reports.preview');
    Route::post('/research/{topic}/narrative-progress-reports/draft', [ProjectNarrativeReportController::class, 'saveDraft'])->name('project-narrative-reports.draft');
    Route::post('/research/{topic}/narrative-progress-reports/prepare', [ProjectNarrativeReportController::class, 'prepare'])->name('project-narrative-reports.prepare');
    Route::post('/research/{topic}/narrative-progress-reports', [ProjectNarrativeReportController::class, 'prepare'])->name('project-narrative-reports.store');
    Route::post('/research/{topic}/narrative-progress-reports/{report}/submit', [ProjectNarrativeReportController::class, 'submitPrepared'])->name('project-narrative-reports.submit-prepared');
    Route::delete('/research/{topic}/narrative-progress-reports/{report}/prepared', [ProjectNarrativeReportController::class, 'discardPrepared'])->name('project-narrative-reports.discard-prepared');
});

Route::get('/progress-reports/{report}/attachment', [ProjectMonitoringController::class, 'download'])
    ->middleware('auth')
    ->name('project-progress.download');
Route::get('/progress-reports/{report}/monitoring-tool', [ProjectMonitoringController::class, 'downloadMonitoringTool'])
    ->middleware('auth')
    ->name('project-progress.monitoring-tool');
Route::get('/narrative-progress-reports/{report}/document', [ProjectNarrativeReportController::class, 'download'])
    ->middleware('auth')
    ->name('project-narrative-reports.download');
Route::get('/narrative-progress-reports/{report}/photos/{photoIndex}', [ProjectNarrativeReportController::class, 'downloadPhoto'])
    ->middleware('auth')
    ->whereNumber('photoIndex')
    ->name('project-narrative-reports.photos.download');

Route::middleware('auth')->group(function () {
    Route::get('/research-support', [ResearchSupportController::class, 'index'])->name('research-support.index');
    Route::get('/research-head/signatories', [ProposalSignatoryController::class, 'index'])->name('signatories.index');
    Route::post('/research-head/signatories', [ProposalSignatoryController::class, 'store'])->name('signatories.store');
    Route::patch('/research-head/signatories/{signatory}', [ProposalSignatoryController::class, 'update'])->name('signatories.update');
    Route::get('/faculty/proposal-drafts/{proposalDraft}/signatories', [ProposalSignatoryController::class, 'edit'])->name('signatories.edit');
    Route::put('/faculty/proposal-drafts/{proposalDraft}/signatories', [ProposalSignatoryController::class, 'select'])->name('signatories.select');
    Route::get('/research-support/similarity-checks', [ProposalSimilarityCheckController::class, 'index'])->name('similarity-checks.index');
    Route::post('/topics/{topic}/similarity-checks', [ProposalSimilarityCheckController::class, 'store'])->name('similarity-checks.store');
    Route::patch('/research-support/similarity-checks/{check}', [ProposalSimilarityCheckController::class, 'update'])->name('similarity-checks.update');
    Route::get('/research-support/similarity-checks/{check}/report', [ProposalSimilarityCheckController::class, 'download'])->name('similarity-checks.download');
    Route::get('/research-support/history', [ResearchAssistantController::class, 'history'])
        ->middleware('throttle:60,1')
        ->name('research-support.history');
    Route::get('/research-support/history/{conversation}', [ResearchAssistantController::class, 'showHistory'])
        ->middleware('throttle:120,1')
        ->name('research-support.history.show');
    Route::post('/research-support/history', [ResearchAssistantController::class, 'saveHistory'])
        ->middleware('throttle:60,1')
        ->name('research-support.history.save');
    Route::post('/research-support/chat', ResearchAssistantController::class)
        ->middleware('throttle:12,1')
        ->name('research-support.chat');
    Route::get('/research-support/documents', ResearchAssistantDocumentController::class)
        ->middleware('throttle:60,1')
        ->name('research-support.documents');
});

Route::middleware(['auth', 'workspace:faculty|faculty_researcher'])->group(function () {
    Route::post('/research-support/literature-search', LiteratureSearchController::class)
        ->middleware('throttle:20,1')
        ->name('research-support.literature-search');
    Route::post('/research-support/literature-synthesis', LiteratureSynthesisController::class)
        ->middleware('throttle:12,1')
        ->name('research-support.literature-synthesis');
    Route::post('/research-support/literature-full-text-preview', LiteratureFullTextPreviewController::class)
        ->middleware('throttle:12,1')
        ->name('research-support.literature-full-text-preview');
    Route::get('/research-support/literature-library', [LiteratureSourceController::class, 'index'])
        ->middleware('throttle:30,1')
        ->name('research-support.literature-library.index');
    Route::post('/research-support/literature-library', [LiteratureSourceController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('research-support.literature-library.store');
    Route::post('/research-support/literature-collections', [LiteratureCollectionController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('research-support.literature-collections.store');
});

Route::post('/faculty/proposal-drafts/{proposalDraft}/literature-sources/{literatureSource}', [ProposalDraftLiteratureSourceController::class, 'store'])
    ->middleware(['auth', 'workspace:faculty', 'throttle:30,1'])
    ->name('faculty.proposal-drafts.literature-sources.store');

Route::middleware(['auth', 'workspace:faculty', 'throttle:30,1'])->group(function () {
    Route::put('/faculty/proposal-drafts/{proposalDraft}/literature-links/{proposalDraftLiteratureSource}/draft', [ProposalDraftLiteratureSourceController::class, 'updateDraft'])
        ->name('faculty.proposal-drafts.literature-drafts.update');
    Route::delete('/faculty/proposal-drafts/{proposalDraft}/literature-links/{proposalDraftLiteratureSource}/draft', [ProposalDraftLiteratureSourceController::class, 'discardDraft'])
        ->name('faculty.proposal-drafts.literature-drafts.destroy');
});

Route::middleware(['auth', 'workspace:faculty_researcher'])->group(function () {
    Route::post('/research-support/journal-search', JournalSearchController::class)
        ->middleware('throttle:12,1')
        ->name('research-support.journal-search');
    Route::post('/research-support/conference-search', ConferenceSearchController::class)
        ->middleware('throttle:12,1')
        ->name('research-support.conference-search');
});

// RESEARCH HEAD ROUTES
Route::middleware(['auth', 'workspace:faculty_researcher|research_head'])->prefix('research/{topic}/dissemination')->name('research.dissemination.')->group(function () {
    Route::get('/', [ProjectDisseminationController::class, 'show'])->name('show');
    Route::middleware('throttle:12,1')->group(function () {
        Route::post('/journals/search', JournalSearchController::class)->name('journals.search');
        Route::post('/authors', [ProjectDisseminationController::class, 'authors'])->name('authors');
        Route::post('/profile', [ProjectDisseminationController::class, 'profile'])->name('profile');
        Route::post('/papers', [ProjectDisseminationController::class, 'papers'])->name('papers');
        Route::post('/doi', [ProjectDisseminationController::class, 'doi'])->name('doi');
        Route::post('/import', [ProjectDisseminationController::class, 'import'])->name('import');
        Route::post('/conferences/search', [ProjectDisseminationController::class, 'searchConferences'])->name('conferences.search');
    });
    Route::post('/publications', [ProjectDisseminationController::class, 'manual'])->name('manual');
    Route::post('/publications/link', [ProjectDisseminationController::class, 'link'])->name('link');
    Route::delete('/publications/{publication}', [ProjectDisseminationController::class, 'unlink'])->name('unlink');
    Route::post('/conferences', [ProjectDisseminationController::class, 'storeConference'])->name('conferences.store');
    Route::patch('/conferences/{conference}', [ProjectDisseminationController::class, 'updateConference'])->name('conferences.update');
});

// RESEARCH HEAD ROUTES
Route::middleware(['auth', 'workspace:research_head'])->group(function () {
    Route::get('/research-head/dashboard', [ResearchHeadTopicController::class, 'index'])->name('research_head.dashboard');
    Route::get('/research-head/faculty-directory', [FacultyDirectoryController::class, 'index'])->name('research_head.faculty-directory.index');
    Route::patch('/research-head/faculty-directory/{member}/coordinator', [FacultyDirectoryController::class, 'updateCoordinator'])->name('research_head.faculty-directory.coordinator');
    Route::get('/research-head/proposal-submissions', [ResearchHeadProposalSubmissionController::class, 'index'])->name('research_head.proposal-submissions.index');
    Route::get('/research-head/projects', [ProjectMonitoringController::class, 'index'])->name('research_head.projects.index');
    Route::patch('/research-head/topics/{topic}/status', [ResearchHeadTopicController::class, 'updateStatus'])->name('research_head.topics.updateStatus');
    Route::patch('/research-head/topics/{topic}/finalize-approval', [ResearchHeadTopicController::class, 'finalizeApproval'])->name('research_head.topics.finalizeApproval');
    Route::post('/research-head/topics/{topic}/notice-to-proceed/preview', [NoticeToProceedController::class, 'preview'])->name('research_head.topics.notice-to-proceed.preview');
    Route::post('/research-head/topics/{topic}/notice-to-proceed', [NoticeToProceedController::class, 'prepare'])->name('research_head.topics.notice-to-proceed.store');
    Route::get('/research-head/topics/{topic}/notice-to-proceed/unsigned', [NoticeToProceedController::class, 'downloadUnsigned'])->name('research_head.topics.notice-to-proceed.download-unsigned');
    Route::post('/research-head/topics/{topic}/notice-to-proceed/signed', [NoticeToProceedController::class, 'uploadSigned'])->name('research_head.topics.notice-to-proceed.upload-signed');
    Route::patch('/research-head/projects/{topic}/status', [ProjectMonitoringController::class, 'updateProjectStatus'])->name('research_head.projects.update-status');
    Route::patch('/research-head/progress-reports/{report}', [ProjectMonitoringController::class, 'review'])->name('research_head.progress-reports.review');
    Route::patch('/research-head/narrative-progress-reports/{report}', [ProjectNarrativeReportController::class, 'review'])->name('research_head.narrative-progress-reports.review');
    Route::post('/research-calls/extract-image', [ResearchCallController::class, 'extractImage'])->name('research-calls.extract-image');
    Route::post('/research-calls', [ResearchCallController::class, 'store'])->name('research-calls.store');
    Route::put('/research-calls/{researchCall}', [ResearchCallController::class, 'update'])->name('research-calls.update');
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
Route::get('/research-coordinator/faculty-members', [ResearchCoordinatorController::class, 'members'])
    ->middleware(['auth', 'role:research_coordinator'])
    ->name('research_coordinator.members.index');

// PROFILE ROUTES
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile/details', [ProfileController::class, 'updateDetails'])->name('profile.details.update');
    Route::patch('/profile/college', [ProfileController::class, 'updateCollege'])->name('profile.college.update');
    Route::patch('/profile/contact-number', [ProfileController::class, 'updateContactNumber'])->name('profile.contact-number.update');
});

// GOOGLE AUTHENTICATION ROUTES
Route::middleware(['guest', 'throttle:20,1'])->group(function () {
    Route::get('/auth/google', [ProviderController::class, 'redirectToGoogle'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [ProviderController::class, 'handleGoogleCallback'])->name('auth.google.callback');
});

require __DIR__.'/auth.php';
