<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ThreadController;
use App\Http\Controllers\ReplyController;
use App\Http\Controllers\ReviewContentController;
use App\Http\Controllers\ReviewImageController;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

Route::get('/files/review_uploads/{filename}', function ($filename, Request $request) {
    $fullPath = storage_path('app/public/review_uploads/' . $filename);
    if (!file_exists($fullPath)) {
        return response("File not found: $fullPath", 404);
    }
    $content = file_get_contents($fullPath);
    $mime = mime_content_type($fullPath);

    return response($content, 200)
        ->header('Content-Type', $mime)
        ->header('Access-Control-Allow-Origin', '*');
})->where('filename', '.*');



// Review Images


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
