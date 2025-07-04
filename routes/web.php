<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PageController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Auth\PasswordResetLinkController;

/* ─────────── Public Pages ─────────── */
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/about', [PageController::class, 'about'])->name('about');

/* ─────────── Auth Views ─────────── */
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');

/* ─────────── Auth Actions ─────────── */
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::post('/register', [AuthController::class, 'register']);

/* ─────────── Dashboards (Role-Based) ─────────── */
Route::middleware(['auth', 'role:admin'])->get('/admin', fn () => view('pages.admin'))->name('admin');
Route::middleware(['auth', 'role:journalist'])->get('/journalist', fn () => view('pages.journalist'))->name('journalist');
Route::middleware(['auth', 'role:user'])->get('/user', [PageController::class, 'user'])->name('user');

/* ─────────── Password Reset (Forgot/Reset) ─────────── */
Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');

    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});