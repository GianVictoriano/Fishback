<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PlagController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;

Route::get('/ping', function () {
    return ['message' => 'API is working!'];
});

Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/google', [AuthController::class, 'handleGoogleCallback']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/users', [UserController::class, 'index']);
});

Route::post('/posts', [PostController::class, 'store']);


Route::post('/copyleaks/webhook/{scanId}', [PlagController::class, 'webhook']);
Route::post('/check-plagiarism', [PlagController::class, 'check']);

Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail']);
Route::post('/reset-password', [PasswordResetController::class, 'reset']);

// Mga ruta ng Forum
Route::get('/topics', [\App\Http\Controllers\TopicController::class, 'index']);
Route::get('/topics/{topic}', [\App\Http\Controllers\TopicController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/topics', [\App\Http\Controllers\TopicController::class, 'store']);
    Route::post('/topics/{topic}/comments', [\App\Http\Controllers\CommentController::class, 'store']);
});

