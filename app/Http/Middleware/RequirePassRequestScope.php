<?php

namespace App\Http\Middleware;

use App\Services\PassRequests\ApiAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePassRequestScope
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        if (! ApiAccess::allows($scope)) {
            return ApiAccess::challengeResponse(403, 'insufficient_scope', $scope);
        }

        return $next($request);
    }
}
