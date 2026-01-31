<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PassRequestController;
use App\Http\Controllers\Admin\InviteCodeController;
use App\Http\Controllers\Admin\SeasonController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

// Home page - redirect based on auth status (like page.tsx)
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

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/pass-requests', [DashboardController::class, 'passRequests'])->name('dashboard.pass-requests');
    
    // Pass request routes (user can manage their own)
    Route::get('/pass-requests', [PassRequestController::class, 'list'])->name('pass-requests.list');
    Route::get('/pass-requests/seasons', [PassRequestController::class, 'getActiveSeasons'])->name('pass-requests.seasons');
    Route::post('/pass-requests', [PassRequestController::class, 'store'])->name('pass-requests.store');
    Route::put('/pass-requests/{id}', [PassRequestController::class, 'update'])->name('pass-requests.update');
    Route::delete('/pass-requests/{id}', [PassRequestController::class, 'destroy'])->name('pass-requests.destroy');
    
    // Admin routes - protected by 'can:admin' gate
    Route::prefix('admin')->middleware('can:admin')->group(function () {
        // Invite codes
        Route::get('/invites', [InviteCodeController::class, 'index'])->name('admin.invites');
        Route::get('/invites/list', [InviteCodeController::class, 'list'])->name('admin.invites.list');
        Route::post('/invites', [InviteCodeController::class, 'store'])->name('admin.invites.store');
        Route::put('/invites/{id}', [InviteCodeController::class, 'update'])->name('admin.invites.update');
        Route::delete('/invites/{id}', [InviteCodeController::class, 'archive'])->name('admin.invites.archive');
        Route::post('/invites/{id}/restore', [InviteCodeController::class, 'restore'])->name('admin.invites.restore');
        
        // Seasons
        Route::get('/seasons', [SeasonController::class, 'index'])->name('admin.seasons');
        Route::get('/seasons/list', [SeasonController::class, 'list'])->name('admin.seasons.list');
        Route::post('/seasons', [SeasonController::class, 'store'])->name('admin.seasons.store');
        Route::put('/seasons/{id}', [SeasonController::class, 'update'])->name('admin.seasons.update');
        Route::delete('/seasons/{id}', [SeasonController::class, 'archive'])->name('admin.seasons.archive');
        Route::post('/seasons/{id}/restore', [SeasonController::class, 'restore'])->name('admin.seasons.restore');
        
        // Users
        Route::get('/users', [UserController::class, 'index'])->name('admin.users');
        Route::get('/users/list', [UserController::class, 'list'])->name('admin.users.list');
        Route::put('/users/{id}', [UserController::class, 'update'])->name('admin.users.update');
        Route::delete('/users/{id}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    });
});
