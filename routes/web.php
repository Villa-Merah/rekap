<?php

use App\Http\Controllers\CommunicationController;
use App\Http\Controllers\WorkController;
use App\Http\Middleware\EnsurePasswordIsChanged;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [CommunicationController::class, 'loginForm'])->name('login');
    Route::post('/login', [CommunicationController::class, 'login'])->middleware('throttle:5,1');
});
Route::middleware(['auth', EnsurePasswordIsChanged::class])->group(function () {
    Route::get('/password/change', [CommunicationController::class, 'changePassword'])->name('password.change');
    Route::put('/password/change', [CommunicationController::class, 'updatePassword'])->name('password.update');
    Route::get('/tasks', [WorkController::class, 'index'])->name('tasks.index');
    Route::get('/tasks/create', [WorkController::class, 'create'])->name('tasks.create');
    Route::post('/tasks', [WorkController::class, 'store'])->name('tasks.store');
    Route::get('/tasks/{task}', [WorkController::class, 'show'])->whereNumber('task')->name('tasks.show');
    Route::post('/assignments/{assignment}/start', [WorkController::class, 'start'])->name('assignments.start');
    Route::post('/assignments/{assignment}/submit', [WorkController::class, 'submit'])->middleware('throttle:10,1')->name('assignments.submit');
    Route::post('/submissions/{submission}/review', [WorkController::class, 'review'])->name('submissions.review');
    Route::get('/evidence/{evidence}', [WorkController::class, 'evidence'])->name('evidence.show');
    Route::get('/accounts', [WorkController::class, 'accounts'])->name('accounts.index');
    Route::post('/accounts', [WorkController::class, 'storeAccount'])->name('accounts.store');
    Route::post('/account-quotas', [WorkController::class, 'quota'])->name('accounts.quota');
    Route::get('/users/create', [WorkController::class, 'createUser'])->name('users.create');
    Route::post('/users', [WorkController::class, 'storeUser'])->name('users.store');
    Route::post('/supervisors', [WorkController::class, 'supervisor'])->name('users.supervisor');
    Route::get('/', [CommunicationController::class, 'index'])->name('dashboard');
    Route::post('/logout', [CommunicationController::class, 'logout'])->name('logout');
    Route::get('/communication', [CommunicationController::class, 'feed'])->name('communication.feed');
    Route::get('/chat/{user}', [CommunicationController::class, 'conversation'])->name('chat.show');
    Route::post('/chat/{user}', [CommunicationController::class, 'send'])->middleware('throttle:30,1')->name('chat.send');
    Route::post('/chat/{user}/read', [CommunicationController::class, 'readConversation'])->name('chat.read');
    Route::post('/notifications/read', [CommunicationController::class, 'readNotifications'])->name('notifications.read');
});
