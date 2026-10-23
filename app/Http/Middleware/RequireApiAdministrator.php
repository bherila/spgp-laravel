<?php

namespace App\Http\Middleware;

use App\Services\PassRequests\ApiAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireApiAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(ApiAccess::user()->isAdmin(), 403, 'Administrator access is required.');

        return $next($request);
    }
}
