<?php


use App\Http\Controllers\PlagController;

use App\Http\Controllers\ScrumBoardController;
use App\Http\Controllers\ChatMessageController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\GroupChatController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/





Route::get('/ping', function () {
    return ['message' => 'API is working!'];
});

Route::get('/branding', [BrandingController::class, 'index']);

Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/google', [AuthController::class, 'handleGoogleCallback']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);

    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/me', [UserController::class, 'me']);
    Route::post('/branding', [BrandingController::class, 'update']);

    // Module Management Routes
    Route::get('/modules', [ModuleController::class, 'getModules']);
    Route::get('/collaborators', [ModuleController::class, 'getCollaborators']);
    Route::patch('/collaborators/{profile}', [ModuleController::class, 'updatePosition']);

    // Scrum Board Routes
    Route::post('/scrum-boards', [ScrumBoardController::class, 'store']); 
    Route::get('/group-chats', [GroupChatController::class, 'index']);
    Route::post('/collaborators/{profile}/modules', [ModuleController::class, 'updateCollaboratorModules']);
        // Chat Message Routes (scoped to group chat)
    Route::get('/group-chats/{groupChatId}/messages', [ChatMessageController::class, 'index']);
    Route::post('/group-chats/{groupChatId}/messages', [ChatMessageController::class, 'store']);

    // Standardized Plagiarism Routes
    Route::post('/plagiarism-scans', [PlagController::class, 'submitScan']);
    Route::get('/plagiarism-scans/{scanId}', [PlagController::class, 'checkStatus']);
});

Route::post('/posts', [PostController::class, 'store']);

// This route must be public to receive webhooks from Copyleaks
Route::post('/plagiarism-webhook', [PlagController::class, 'webhook']);

Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail']);
Route::post('/reset-password', [PasswordResetController::class, 'reset']);

// Mga ruta ng Forum
Route::get('/topics', [\App\Http\Controllers\TopicController::class, 'index']);
Route::get('/topics/{topic}', [\App\Http\Controllers\TopicController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/topics', [\App\Http\Controllers\TopicController::class, 'store']);
    Route::post('/topics/{topic}/comments', [\App\Http\Controllers\CommentController::class, 'store']);
});

