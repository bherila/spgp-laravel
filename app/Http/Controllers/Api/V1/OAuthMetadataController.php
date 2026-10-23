<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PassRequests\ApiAccess;
use BWH\Auth\OAuth\Introspection\OAuthIntrospectionException;
use Illuminate\Http\JsonResponse;

class OAuthMetadataController extends Controller
{
    public function __invoke(): JsonResponse
    {
        if (! config('pass-request-api.enabled')) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        try {
            $configuration = ApiAccess::configuration();
        } catch (OAuthIntrospectionException) {
            return ApiAccess::unavailableResponse();
        }

        return response()->json([
            'resource' => $configuration['resource'],
            'authorization_servers' => [$configuration['issuer']],
            'scopes_supported' => array_keys(config('pass-request-api.scopes', [])),
            'bearer_methods_supported' => ['header'],
        ], 200, ['Cache-Control' => 'public, max-age=300', 'X-Content-Type-Options' => 'nosniff']);
    }
}
