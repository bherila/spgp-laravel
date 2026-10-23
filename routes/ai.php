<?php

use App\Http\Controllers\Api\V1\OAuthMetadataController;
use App\Http\Middleware\AuthenticatePassRequestApi;
use App\Http\Middleware\RequireApiAdministrator;
use App\Http\Middleware\ValidatePassRequestOrigin;
use App\Mcp\AdminPassRequestServer;
use App\Mcp\UserPassRequestServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Middleware\AddWwwAuthenticateHeader;

Route::get('/.well-known/oauth-protected-resource', OAuthMetadataController::class);

Route::middleware(['throttle:pass-request-api', AuthenticatePassRequestApi::class, ValidatePassRequestOrigin::class])->group(function () {
    Mcp::web('/mcp/user', UserPassRequestServer::class)
        ->withoutMiddleware(AddWwwAuthenticateHeader::class);
    Route::middleware(RequireApiAdministrator::class)->group(function () {
        Mcp::web('/mcp/admin', AdminPassRequestServer::class)
            ->withoutMiddleware(AddWwwAuthenticateHeader::class);
    });
});
