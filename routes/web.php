<?php

use App\Http\Controllers\PublicProfileController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\ProfileController;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\UserSearchController;
use App\Http\Controllers\UserDirectoryController;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('correspondence.index');
    }

    return view('welcome');
});

Route::get('/about', function () {
    return view('about', [
        'deliveredCount' => Message::delivered()->sent()->count(),
        'inTransitCount' => Message::sent()->undelivered()->count(),
        // Members who finished onboarding — the same definition the directory uses.
        'memberCount' => User::whereNotNull('name')->count(),
    ]);
})->name('about');
Route::view('/privacy', 'privacy')->name('privacy');
// Pinged by the compose page so a long letter doesn't outlive its CSRF token.
Route::get('/csrf-token', fn () => response()->json(['token' => csrf_token()]))->name('csrf-token');
Route::get('/u/{username}', [PublicProfileController::class, 'show'])->name('profile.public');
Route::get('/profile/delete/{user}', [ProfileController::class, 'confirmDestroy'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('profile.destroy.confirm');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/profile/export', [ProfileController::class, 'export'])->name('profile.export');
    
    Route::get('/correspondence', [MessageController::class, 'index'])->name('correspondence.index');
    Route::get('/correspondence/{person}', [MessageController::class, 'show'])->name('correspondence.show');


    Route::get('/messages/drafts', [MessageController::class, 'drafts'])->name('messages.drafts');
    Route::get('/messages/create', [MessageController::class, 'create'])->name('messages.create');
    Route::get('/messages/{message}/edit', [MessageController::class, 'edit'])->name('messages.edit');
    Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
    Route::middleware('throttle:120,1')->group(function () {
        Route::post('/messages/autosave', [MessageController::class, 'autosaveStore'])->name('messages.autosave.store');
        Route::put('/messages/{message}/autosave', [MessageController::class, 'autosaveUpdate'])->name('messages.autosave.update');
    });
    Route::put('/messages/{message}', [MessageController::class, 'update'])->name('messages.update');
    Route::delete('/messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');

    Route::get('/users/search', UserSearchController::class)->name('users.search');
    Route::get('/directory', [UserDirectoryController::class, 'index'])->name('directory.index');

    Route::get('/feedback', [FeedbackController::class, 'create'])->name('feedback.create');
    Route::post('/feedback', [FeedbackController::class, 'store'])->name('feedback.store');
});

require __DIR__.'/auth.php';
