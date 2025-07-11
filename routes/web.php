<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ThreadController;
use App\Http\Controllers\ReplyController;

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
