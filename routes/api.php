<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GroupChatController;
use App\Http\Controllers\ChatMessageController;
use App\Http\Controllers\PlagController;
use App\Http\Controllers\ReviewContentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\ScrumBoardController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewImageController;
use App\Http\Controllers\Api\ArticleController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Health check & branding
Route::get('/ping', function () {
    return ['message' => 'API is working!'];
});
Route::get('/branding', [BrandingController::class, 'index']);

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail']);
Route::post('/reset-password', [PasswordResetController::class, 'reset']);
Route::post('/plagiarism-webhook', [PlagController::class, 'webhook']);
Route::post('/auth/google', [AuthController::class, 'handleGoogleCallback']);
Route::post('/google/access-token', [\App\Http\Controllers\GoogleController::class, 'getAccessToken']);
Route::post('/login-as', [AuthController::class, 'loginAs']);
Route::get('/topics', [TopicController::class, 'index']);
Route::get('/topics/{topic}', [TopicController::class, 'show']);
Route::get('/users', [UserController::class, 'index']);
Route::get('/public/articles', [ArticleController::class, 'publicArticles']);
Route::get('/public/articles/{article}', [ArticleController::class, 'show']);

// Protected routes
Route::middleware('force.api.auth')->group(function () {
    // Auth/User
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', fn(Request $request) => $request->user());
    Route::get('/users/me', [UserController::class, 'me']);

    Route::get('/profile', [ProfileController::class, 'show']);
    
    // Articles - Moved to the top to ensure they're protected
    Route::apiResource('articles', ArticleController::class)->except(['index', 'show']);
    Route::get('/articles', [ArticleController::class, 'index']);
    Route::get('/articles/{article}', [ArticleController::class, 'show']);

    // Branding
    Route::post('/branding', [BrandingController::class, 'update']);

    // Group Chat & Messages
    Route::get('/group-chats', [GroupChatController::class, 'index']);
    Route::get('/group-chats/{groupChat}/messages', [ChatMessageController::class, 'index']);
    Route::post('/group-chats/{groupChat}/messages', [ChatMessageController::class, 'store']);

    // Plagiarism Scans
    Route::post('/plagiarism-scans', [PlagController::class, 'submitScan']);
    Route::get('/plagiarism-scans/{scanId}', [PlagController::class, 'checkStatus']);

    Route::post('/review-images', [ReviewImageController::class, 'store']);
    Route::patch('/review-images/{id}/approve', [ReviewImageController::class, 'approve']);
    Route::patch('/review-images/{id}/reject', [ReviewImageController::class, 'reject']);
    Route::get('/review-images/{id}', [ReviewImageController::class, 'show']);
    Route::get('/review-images', [ReviewImageController::class, 'index']);
    
    // Review Content
    Route::get('/review-content', [ReviewContentController::class, 'index']);
    Route::post('/review-content', [ReviewContentController::class, 'store']);
    Route::patch('/review-content/{id}/approve', [ReviewContentController::class, 'approve']);
    Route::patch('/review-content/{id}/reject', [ReviewContentController::class, 'reject']);
    Route::get('/review-content/preview/{id}', [ReviewContentController::class, 'preview']);

    // Module & Collaborators
    Route::get('/modules', [ModuleController::class, 'getModules']);
    Route::get('/collaborators', [ModuleController::class, 'getCollaborators']);
    Route::patch('/collaborators/{profile}', [ModuleController::class, 'updatePosition']);
    Route::post('/collaborators/{profile}/modules', [ModuleController::class, 'updateCollaboratorModules']);

    // Google Docs
    Route::post('/google/create-doc', [\App\Http\Controllers\GoogleController::class, 'createDoc']);

    // Scrum Board
    Route::post('/scrum-boards', [ScrumBoardController::class, 'store']);

    // Forum Topics & Comments
    Route::post('/topics', [TopicController::class, 'store']);
    Route::post('/topics/{topic}/comments', [CommentController::class, 'store']);

    // Posts
    Route::post('/posts', [PostController::class, 'store']);
});

?>