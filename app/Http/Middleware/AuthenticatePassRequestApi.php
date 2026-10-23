<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\PassRequests\ApiAccess;
use BWH\Auth\OAuth\Introspection\IntrospectedToken;
use BWH\Auth\OAuth\Introspection\OAuthIntrospectionException;
use BWH\Auth\OAuth\Introspection\OAuthTokenIntrospector;
use BWH\Auth\OAuth\Introspection\OAuthTokenValidationException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePassRequestApi
{
    public function __construct(private readonly OAuthTokenIntrospector $introspector) {}

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->remove(ApiAccess::TOKEN_ATTRIBUTE);
        $guard = Auth::guard(ApiAccess::GUARD);
        $guard->forgetUser();

        if (! config('pass-request-api.enabled')) {
            return response()->json(['message' => 'Not found.'], 404, ['Cache-Control' => 'private, no-store']);
        }

        try {
            $configuration = ApiAccess::configuration();
            $bearer = $request->bearerToken();
            if (! is_string($bearer) || trim($bearer) === '' || strlen($bearer) > 32_768) {
                return ApiAccess::challengeResponse();
            }

            $token = $this->introspector->introspect($bearer);
            if (! $this->validToken($token, $configuration)) {
                return ApiAccess::challengeResponse(error: 'invalid_token');
            }
        } catch (OAuthTokenValidationException) {
            return ApiAccess::challengeResponse(error: 'invalid_token');
        } catch (OAuthIntrospectionException) {
            return ApiAccess::unavailableResponse();
        }

        $user = User::query()
            ->where('oauth_provider', $configuration['issuer'])
            ->where('oauth_subject', $token->subject)
            ->first();
        // Compare exact opaque identifiers even if the production collation is case insensitive.
        if ($user === null || $user->oauth_provider !== $configuration['issuer'] || $user->oauth_subject !== $token->subject) {
            return ApiAccess::challengeResponse(error: 'invalid_token');
        }

        $previousGuard = Auth::getDefaultDriver();
        $guard->setUser($user);
        Auth::shouldUse(ApiAccess::GUARD);
        $request->setUserResolver(fn (?string $guardName = null) => Auth::guard($guardName ?? ApiAccess::GUARD)->user());
        $request->attributes->set(ApiAccess::TOKEN_ATTRIBUTE, $token);

        try {
            $response = $next($request);
            $response->headers->set('Cache-Control', 'private, no-store');

            return $response;
        } finally {
            $guard->forgetUser();
            Auth::shouldUse($previousGuard);
            $request->attributes->remove(ApiAccess::TOKEN_ATTRIBUTE);
        }
    }

    /** @param array{issuer: string, resource: string, metadata_url: string} $configuration */
    private function validToken(IntrospectedToken $token, array $configuration): bool
    {
        return $token->active
            && $token->issuer === $configuration['issuer']
            && $token->resource === $configuration['resource']
            && array_is_list($token->audiences)
            && array_all($token->audiences, fn (mixed $audience): bool => is_string($audience) && $audience !== '')
            && in_array($configuration['resource'], $token->audiences, true)
            && is_string($token->subject) && trim($token->subject) !== '' && strlen($token->subject) <= 255
            && is_string($token->clientId) && trim($token->clientId) !== ''
            && $token->expiresAt !== null && $token->expiresAt > now()->getTimestamp()
            && ($token->notBefore === null || $token->notBefore <= now()->getTimestamp())
            && array_is_list($token->scopes)
            && array_all($token->scopes, fn (mixed $scope): bool => is_string($scope) && preg_match('/^[\x21\x23-\x5B\x5D-\x7E]+$/D', $scope) === 1);
    }
}
