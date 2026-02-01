<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PassRequestController;
use App\Http\Controllers\Admin\EmailLogController;
use App\Http\Controllers\Admin\InviteCodeController;
use App\Http\Controllers\Admin\SeasonController;
use App\Http\Controllers\Admin\SeasonPassTypeController;
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
    Route::post('/change-password', [AuthController::class, 'changePassword'])->name('change-password');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/pass-requests', [DashboardController::class, 'passRequests'])->name('dashboard.pass-requests');
    
    // New pass request page
    Route::get('/request/{season_id}', [PassRequestController::class, 'showRequestForm'])->name('request');
    
    // Pass request routes (user can manage their own)
    Route::get('/pass-requests', [PassRequestController::class, 'list'])->name('pass-requests.list');
    Route::get('/pass-requests/seasons', [PassRequestController::class, 'getActiveSeasons'])->name('pass-requests.seasons');
    Route::get('/pass-requests/seasons/{id}/pass-types', [PassRequestController::class, 'getPassTypes'])->name('pass-requests.pass-types');
    Route::post('/pass-requests', [PassRequestController::class, 'store'])->name('pass-requests.store');
    Route::put('/pass-requests/{id}', [PassRequestController::class, 'update'])->name('pass-requests.update');
    Route::delete('/pass-requests/{id}', [PassRequestController::class, 'destroy'])->name('pass-requests.destroy');
    Route::post('/pass-requests/{id}/renewal-order', [PassRequestController::class, 'updateRenewalOrder'])->name('pass-requests.renewal-order');
    Route::delete('/pass-requests/{id}/renewal-order', [PassRequestController::class, 'removeRenewalOrder'])->name('pass-requests.renewal-order.remove');
    
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
        
        // Season pass types
        Route::get('/seasons/{id}/pass-types', [SeasonPassTypeController::class, 'list'])->name('admin.seasons.pass-types.list');
        Route::post('/seasons/{id}/pass-types', [SeasonPassTypeController::class, 'store'])->name('admin.seasons.pass-types.store');
        Route::put('/pass-types/{id}', [SeasonPassTypeController::class, 'update'])->name('admin.pass-types.update');
        Route::delete('/pass-types/{id}', [SeasonPassTypeController::class, 'destroy'])->name('admin.pass-types.destroy');
        
        // Season pass requests admin
        Route::get('/seasons/{id}/pass-requests', [SeasonController::class, 'showPassRequests'])->name('admin.seasons.pass-requests');
        Route::get('/seasons/{id}/pass-requests/list', [SeasonController::class, 'listPassRequests'])->name('admin.seasons.pass-requests.list');
        Route::post('/seasons/{id}/pass-requests/assign-codes', [SeasonController::class, 'assignCodes'])->name('admin.seasons.pass-requests.assign-codes');
        Route::post('/seasons/{id}/pass-requests/clear-codes', [SeasonController::class, 'clearCodes'])->name('admin.seasons.pass-requests.clear-codes');
        Route::post('/seasons/{id}/pass-requests/send-emails', [SeasonController::class, 'sendEmails'])->name('admin.seasons.pass-requests.send-emails');
        Route::delete('/pass-requests/{id}/admin', [SeasonController::class, 'deletePassRequest'])->name('admin.pass-requests.destroy');
        
        // Email logs
        Route::get('/email-log', [EmailLogController::class, 'index'])->name('admin.email-log');
        Route::get('/email-log/list', [EmailLogController::class, 'list'])->name('admin.email-log.list');
        
        // Users
        Route::get('/users', [UserController::class, 'index'])->name('admin.users');
        Route::get('/users/list', [UserController::class, 'list'])->name('admin.users.list');
        Route::post('/users', [UserController::class, 'store'])->name('admin.users.store');
        Route::put('/users/{id}', [UserController::class, 'update'])->name('admin.users.update');
        Route::delete('/users/{id}', [UserController::class, 'destroy'])->name('admin.users.destroy');
        Route::post('/users/{id}/impersonate', [AuthController::class, 'impersonate'])->name('admin.users.impersonate');
    });
});
