<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PassRequestController;
use App\Http\Controllers\Admin\EmailLogController;
use App\Http\Controllers\Admin\InviteCodeController;
use App\Http\Controllers\Admin\SeasonController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

// Home page - redirect based on auth status
Route::get('/', function () {
    if (auth()->check()) {
        return redirect('/dashboard');
    }
    return redirect('/login');
});

// Auth routes (guest only)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

// Authenticated View routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/pass-requests-view', [DashboardController::class, 'index'])->name('dashboard.pass-requests'); // Alias for consistent naming if needed
    
    // New pass request page
    Route::get('/request/{season_id}', [PassRequestController::class, 'showRequestForm'])->name('request');
    
    // Admin View routes - protected by 'can:admin' gate
    Route::prefix('admin')->middleware('can:admin')->group(function () {
        Route::get('/invites', [InviteCodeController::class, 'index'])->name('admin.invites');
        Route::get('/seasons', [SeasonController::class, 'index'])->name('admin.seasons');
        Route::get('/seasons/{id}/pass-requests', [SeasonController::class, 'showPassRequests'])->name('admin.seasons.pass-requests');
        Route::get('/email-log', [EmailLogController::class, 'index'])->name('admin.email-log');
        Route::get('/users', [UserController::class, 'index'])->name('admin.users');
    });
});