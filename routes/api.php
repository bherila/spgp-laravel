<?php

use App\Http\Controllers\Admin\EmailLogController;
use App\Http\Controllers\Admin\InviteCodeController;
use App\Http\Controllers\Admin\PromoCodeRepositoryController;
use App\Http\Controllers\Admin\SeasonController;
use App\Http\Controllers\Admin\SeasonPassTypeController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Api\V1\AdminPassRequestController as AdminApi;
use App\Http\Controllers\Api\V1\UserPassRequestController as UserApi;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PassRequestController;
use App\Http\Controllers\QuestionController;
use App\Http\Middleware\AuthenticatePassRequestApi;
use App\Http\Middleware\RequireApiAdministrator;
use App\Http\Middleware\RequirePassRequestScope;
use App\Http\Middleware\ValidatePassRequestOrigin;
use App\Services\PassRequests\PassRequestOperations;
use Illuminate\Support\Facades\Route;

// Bearer-only public interfaces are independent of the browser's session/CSRF routes.
Route::prefix('v1')->middleware(['throttle:pass-request-api', AuthenticatePassRequestApi::class, ValidatePassRequestOrigin::class])->group(function () {
    $endpoint = function (string $method, string $uri, string $operation, bool $admin = false): void {
        Route::match([$method], $uri, $admin ? AdminApi::class : UserApi::class)
            ->defaults('operation', $operation)
            ->middleware(RequirePassRequestScope::class.':'.PassRequestOperations::scope($operation, $admin));
    };

    $endpoint('GET', 'seasons', 'list_seasons');
    $endpoint('GET', 'seasons/{season_id}/pass-types', 'list_pass_types');
    $endpoint('GET', 'pass-requests', 'list_pass_requests');
    $endpoint('POST', 'pass-requests', 'create_pass_request');
    $endpoint('PATCH', 'pass-requests/missing-country', 'set_missing_country');
    $endpoint('GET', 'pass-requests/{id}', 'get_pass_request');
    $endpoint('PATCH', 'pass-requests/{id}', 'update_pass_request');
    $endpoint('DELETE', 'pass-requests/{id}', 'cancel_pass_request');
    $endpoint('PUT', 'pass-requests/{id}/renewal-order', 'set_renewal_order');
    $endpoint('DELETE', 'pass-requests/{id}/renewal-order', 'remove_renewal_order');

    Route::prefix('admin')->middleware(RequireApiAdministrator::class)->group(function () use ($endpoint) {
        $endpoint('GET', 'seasons', 'list_seasons', true);
        $endpoint('GET', 'pass-requests', 'list_pass_requests', true);
        $endpoint('GET', 'pass-requests/{id}', 'get_pass_request', true);
        $endpoint('PATCH', 'pass-requests/{id}', 'update_pass_request', true);
        $endpoint('DELETE', 'pass-requests/{id}', 'delete_pass_request', true);
        foreach ([
            'assign-codes' => 'assign_promo_codes', 'auto-assign-codes' => 'auto_assign_promo_codes',
            'clear-codes' => 'clear_promo_codes', 'send-emails' => 'send_pass_code_emails',
            'bulk-delete' => 'bulk_delete_pass_requests',
        ] as $path => $operation) {
            $endpoint('POST', 'seasons/{season_id}/pass-requests/'.$path, $operation, true);
        }
    });
});

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// These routes use session-based authentication
Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
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
    Route::post('/pass-requests/update-country', [PassRequestController::class, 'updateCountry']);

    // Question (Q&A) routes
    Route::get('/season/{season}/questions', [QuestionController::class, 'getQuestions']);
    Route::post('/season/{season}/questions', [QuestionController::class, 'store']);
    Route::patch('/questions/{question}', [QuestionController::class, 'update']);
    Route::delete('/questions/{question}', [QuestionController::class, 'destroy']);
    Route::post('/questions/{question}/answer', [QuestionController::class, 'answer']);
    Route::post('/questions/{question}/upvote', [QuestionController::class, 'upvote']);
    Route::post('/questions/{question}/unvote', [QuestionController::class, 'unvote']);

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
        Route::post('/seasons/{id}/pass-requests/bulk-delete', [SeasonController::class, 'bulkDeletePassRequests']);
        Route::delete('/pass-requests/{id}/admin', [SeasonController::class, 'deletePassRequest']);

        // Promo code repository
        Route::get('/seasons/{id}/promo-codes/list', [PromoCodeRepositoryController::class, 'list']);
        Route::post('/seasons/{id}/promo-codes/import', [PromoCodeRepositoryController::class, 'import']);
        Route::post('/seasons/{id}/promo-codes/auto-assign', [PromoCodeRepositoryController::class, 'autoAssign']);

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
