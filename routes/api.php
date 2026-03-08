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

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// These routes use session-based authentication
Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::get('/dashboard/pass-requests', [DashboardController::class, 'passRequests']);
    Route::post('/dashboard/redeem-invite', [DashboardController::class, 'redeemInviteCode']);
    
    // Pass request routes
    Route::get('/pass-requests', [PassRequestController::class, 'list']);
    Route::get('/pass-requests/seasons', [PassRequestController::class, 'getActiveSeasons']);
    Route::get('/pass-requests/seasons/{id}/pass-types', [PassRequestController::class, 'getPassTypes']);
    Route::post('/pass-requests', [PassRequestController::class, 'store']);
    Route::put('/pass-requests/{id}', [PassRequestController::class, 'update']);
    Route::delete('/pass-requests/{id}', [PassRequestController::class, 'destroy']);
    Route::post('/pass-requests/{id}/renewal-order', [PassRequestController::class, 'updateRenewalOrder']);
    Route::delete('/pass-requests/{id}/renewal-order', [PassRequestController::class, 'removeRenewalOrder']);
    
    // Question (Q&A) routes
    Route::get('/season/{season}/questions', [\App\Http\Controllers\QuestionController::class, 'getQuestions']);
    Route::post('/season/{season}/questions', [\App\Http\Controllers\QuestionController::class, 'store']);
    Route::patch('/questions/{question}', [\App\Http\Controllers\QuestionController::class, 'update']);
    Route::delete('/questions/{question}', [\App\Http\Controllers\QuestionController::class, 'destroy']);
    Route::post('/questions/{question}/answer', [\App\Http\Controllers\QuestionController::class, 'answer']);
    Route::post('/questions/{question}/upvote', [\App\Http\Controllers\QuestionController::class, 'upvote']);
    Route::post('/questions/{question}/unvote', [\App\Http\Controllers\QuestionController::class, 'unvote']);

    // Admin API routes
    Route::prefix('admin')->middleware('can:admin')->group(function () {
        // Invite codes
        Route::get('/invites/list', [InviteCodeController::class, 'list']);
        Route::post('/invites', [InviteCodeController::class, 'store']);
        Route::put('/invites/{id}', [InviteCodeController::class, 'update']);
        Route::delete('/invites/{id}', [InviteCodeController::class, 'archive']);
        Route::post('/invites/{id}/restore', [InviteCodeController::class, 'restore']);
        
        // Seasons
        Route::get('/seasons/list', [SeasonController::class, 'list']);
        Route::post('/seasons', [SeasonController::class, 'store']);
        Route::put('/seasons/{id}', [SeasonController::class, 'update']);
        Route::delete('/seasons/{id}', [SeasonController::class, 'archive']);
        Route::post('/seasons/{id}/restore', [SeasonController::class, 'restore']);
        
        // Season pass types
        Route::get('/seasons/{id}/pass-types', [SeasonPassTypeController::class, 'list']);
        Route::post('/seasons/{id}/pass-types', [SeasonPassTypeController::class, 'store']);
        Route::put('/pass-types/{id}', [SeasonPassTypeController::class, 'update']);
        Route::delete('/pass-types/{id}', [SeasonPassTypeController::class, 'destroy']);
        
        // Season pass requests admin
        Route::get('/seasons/{id}/pass-requests/list', [SeasonController::class, 'listPassRequests']);
        Route::post('/seasons/{id}/pass-requests/assign-codes', [SeasonController::class, 'assignCodes']);
        Route::post('/seasons/{id}/pass-requests/clear-codes', [SeasonController::class, 'clearCodes']);
        Route::post('/seasons/{id}/pass-requests/send-emails', [SeasonController::class, 'sendEmails']);
        Route::delete('/pass-requests/{id}/admin', [SeasonController::class, 'deletePassRequest']);
        
        // Email logs
        Route::get('/email-log/list', [EmailLogController::class, 'list']);
        
        // Users
        Route::get('/users/list', [UserController::class, 'list']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        Route::post('/users/{id}/impersonate', [AuthController::class, 'impersonate']);
    });
});