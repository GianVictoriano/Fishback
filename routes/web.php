<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PageController;
use App\Http\Controllers\AuthController;

/* ─────────── Role Test Route ─────────── */
Route::get('/test-role', function () {
    return 'You are allowed!';
})->middleware('role:journalist');

/* ─────────── Public Pages ─────────── */
Route::get('/',        [PageController::class, 'home'])->name('home');
Route::get('/about',   [PageController::class, 'about'])->name('about');

/* ─────────── Auth Views ─────────── */
Route::get('/login',    [AuthController::class, 'showLoginForm'])->name('login');
Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');

/* ─────────── Auth Actions ─────────── */
Route::post('/login',    [AuthController::class, 'login']);
Route::post('/logout',   [AuthController::class, 'logout'])->name('logout');
Route::post('/register', [AuthController::class, 'register']);

/* ─────────── Dashboards (Protected) ─────────── */
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

Route::middleware(['auth', 'role:user'])->group(function () {
    Route::get('/user', [PageController::class, 'user'])->name('user');
});