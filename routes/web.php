<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ThreadController;
use App\Http\Controllers\ReplyController;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

Route::get('/files/review_uploads/{filename}', function ($filename, Request $request) {
    $path = 'public/review_uploads/' . $filename;
    if (!Storage::exists($path)) {
        abort(404);
    }
    $content = Storage::get($path);
    $mime = Storage::mimeType($path);

    return response($content, 200)
        ->header('Content-Type', $mime)
        ->header('Access-Control-Allow-Origin', '*');
})->where('filename', '.*');

// Tahanan at Tungkol
Route::get('/home', [PageController::class, 'home'])->name('home');
Route::get('/about', [PageController::class, 'about'])->name('about');

// Forum
Route::get('/forum', [ThreadController::class, 'index'])->name('threads.index');
Route::get('/forum/create', [ThreadController::class, 'create'])->name('threads.create');
Route::post('/forum', [ThreadController::class, 'store'])->name('threads.store');
Route::get('/forum/{thread}', [ThreadController::class, 'show'])->name('threads.show');

// Mga Tugon
Route::post('/forum/{thread}/replies', [ReplyController::class, 'store'])->name('replies.store');
