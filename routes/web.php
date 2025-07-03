<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PageController;
use App\Http\Controllers\AuthController;

// Public pages
Route::get('/',        [PageController::class, 'home'])->name('home');
Route::get('/about',   [PageController::class, 'about'])->name('about');

// Auth views
Route::get('/login',    [AuthController::class, 'showLoginForm'])->name('login');
Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');

// Auth actions
Route::post('/login',    [AuthController::class, 'login']);
Route::post('/logout',   [AuthController::class, 'logout'])->name('logout');
Route::post('/register', [AuthController::class, 'register']);

// Dashboards (protected)
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin', function () {
        return view('pages.admin');
    })->name('admin');
});

Route::middleware(['auth', 'role:journalist'])->group(function () {
    Route::get('/journalist', function () {
        return view('pages.journalist');
    })->name('journalist');
});