<?php

namespace App\Http\Middleware;

use App\Services\PassRequests\ApiAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidatePassRequestOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->header('Origin');
        if ($origin !== null && $origin !== rtrim(ApiAccess::configuration()['resource'], '/')) {
            return response()->json(['message' => 'Origin is not allowed.'], 403, ['Cache-Control' => 'no-store']);
        }

        return $next($request);
    }
}
