<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\ProviderController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\ResearchHeadTopicController;
use App\Http\Controllers\ResearchCallController;
use App\Http\Controllers\ExpertReviewController;
use Illuminate\Support\Facades\Auth;
Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    if (Auth::check()) {
        $user = Auth::user();

        if ($user->hasRole('research_head')) {
            return redirect()->route('research_head.dashboard');
        }
        if ($user->hasRole('expert')) {
            return redirect()->route('expert.dashboard');
        }
    }
    
    return redirect()->route('faculty.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

//FACULTY ROUTES
Route::middleware(['auth', 'role:faculty|faculty_researcher'])->group(function () {
    Route::get('/faculty/dashboard', [TopicController::class, 'index'])-> name('faculty.dashboard');
    Route::post('/faculty/topics', [TopicController::class, 'store'])-> name('faculty.topics');
    Route::patch('/faculty/topics/{topic}/resubmit', [TopicController::class, 'resubmit'])->name('faculty.topics.resubmit');
});

Route::get('/topics/{topic}/download', [TopicController::class, 'download'])
    ->middleware(['auth'])
    ->name('topics.download');
Route::get('/topics/{topic}/approval', [TopicController::class, 'downloadApproval'])
    ->middleware('auth')
    ->name('topics.approval');

Route::get('/research-calls', [ResearchCallController::class, 'index'])
    ->middleware('auth')
    ->name('research-calls.index');

Route::middleware(['auth', 'role:faculty_researcher'])->group(function () {
    Route::get('/research', [TopicController::class, 'researchIndex'])->name('research.index');
    Route::get('/research/{topic}', [TopicController::class, 'researchShow'])->name('research.show');
    Route::view('/research-support', 'research_support.index')->name('research-support.index');
});

//RESEARCH HEAD ROUTES
Route::middleware(['auth', 'role:research_head'])->group(function () {
    Route::get('/research-head/dashboard', [ResearchHeadTopicController::class, 'index'])->name('research_head.dashboard');
    Route::patch('/research-head/topics/{topic}/status', [ResearchHeadTopicController::class, 'updateStatus'])->name('research_head.topics.updateStatus');
    Route::post('/research-calls', [ResearchCallController::class, 'store'])->name('research-calls.store');
    Route::patch('/research-calls/{researchCall}/status', [ResearchCallController::class, 'updateStatus'])->name('research-calls.update-status');
});

Route::middleware(['auth', 'role:expert'])->group(function () {
    Route::get('/expert/dashboard', [ExpertReviewController::class, 'index'])->name('expert.dashboard');
    Route::patch('/expert/assignments/{assignment}', [ExpertReviewController::class, 'submit'])->name('expert.assignments.submit');
});


//PROFILE ROUTES
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

//GOOGLE AUTHENTICATION ROUTES
//route to redirect the user to google
Route::get('/auth/google', [ProviderController::class, 'redirectToGoogle']);
//route to handle the callback from google
Route::get('/auth/google/callback', [ProviderController::class, 'handleGoogleCallback']);

require __DIR__.'/auth.php';
