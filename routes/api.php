<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PlagController;

Route::get('/ping', function () {
    return ['message' => 'API is working!'];
});

Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->get('/profile', function (Request $request) {
    return response()->json([
        'user' => $request->user()
    ]);
});

Route::post('/posts', [PostController::class, 'store']);


Route::post('/copyleaks/webhook/{scanId}', [PlagController::class, 'webhook']);
Route::post('/check-plagiarism', [PlagController::class, 'check']);

