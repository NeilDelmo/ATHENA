<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\ProviderController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\AdminTopicController;
use Illuminate\Support\Facades\Auth;
Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    if (Auth::check()) {
        $user = Auth::user();

        if ($user->hasRole('research_head')) {
            return redirect()->route('admin.dashboard');
        }
    }
    
    return redirect()->route('faculty.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

//FACULTY ROUTES
Route::middleware(['auth', 'role:faculty'])->group(function () {
    Route::get('/faculty/dashboard', [TopicController::class, 'index'])-> name('faculty.dashboard');
    Route::post('/faculty/topics', [TopicController::class, 'store'])-> name('faculty.topics');
});

//RESEARCH HEAD ROUTES
Route::middleware(['auth', 'role:research_head'])->group(function () {
    Route::get('/admin/dashboard', [AdminTopicController::class, 'index'])->name('admin.dashboard');
    Route::patch('/admin/topics/{topic}/status', [AdminTopicController::class, 'updateStatus'])->name('admin.topics.updateStatus');
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
