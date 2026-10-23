<?php

namespace App\Services\PassRequests;

use App\Models\User;
use BWH\Auth\OAuth\Introspection\IntrospectedToken;
use BWH\Auth\OAuth\Introspection\OAuthIntrospectionException;
use BWH\Auth\OAuth\Server\OAuthProtectedResource;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ApiAccess
{
    public const GUARD = 'pass-request-api';

    public const TOKEN_ATTRIBUTE = 'pass-request-api.token';

    public static function user(): User
    {
        $token = request()->attributes->get(self::TOKEN_ATTRIBUTE);
        $user = Auth::guard(self::GUARD)->user();

        if (! $token instanceof IntrospectedToken || ! $token->active || ! $user instanceof User) {
            throw new AuthenticationException('A valid API bearer token is required.');
        }

        return $user;
    }

    public static function allows(string $scope): bool
    {
        $token = request()->attributes->get(self::TOKEN_ATTRIBUTE);

        return $token instanceof IntrospectedToken
            && $token->active
            && in_array($scope, $token->scopes, true);
    }

    public static function authorize(string $scope): User
    {
        $user = self::user();
        if (! self::allows($scope)) {
            throw new AuthorizationException('The bearer token does not grant this operation.');
        }

        return $user;
    }

    /** @return array{issuer: string, resource: string, metadata_url: string} */
    public static function configuration(): array
    {
        $issuer = config('bherila-auth.oauth_resource_server.issuer');
        $resource = config('bherila-auth.oauth_resource_server.resource');
        foreach (['issuer' => $issuer, 'resource' => $resource] as $name => $url) {
            if (! self::validOrigin($url)) {
                throw new OAuthIntrospectionException("The API OAuth {$name} must be a configured HTTPS origin.");
            }
        }

        return [
            'issuer' => $issuer,
            'resource' => $resource,
            'metadata_url' => OAuthProtectedResource::wellKnownFor($resource),
        ];
    }

    public static function challengeResponse(int $status = 401, ?string $error = null, ?string $scope = null): JsonResponse
    {
        $configuration = self::configuration();
        $parameters = ['resource_metadata="'.$configuration['metadata_url'].'"'];
        if ($error !== null) {
            $parameters[] = 'error="'.$error.'"';
        }
        if ($scope !== null) {
            $parameters[] = 'scope="'.addcslashes($scope, '\\"').'"';
        }

        return response()->json([
            'error' => $error ?? 'unauthorized',
            'message' => $status === 403 ? 'The bearer token does not grant this operation.' : 'A valid API bearer token is required.',
        ], $status, [
            'WWW-Authenticate' => 'Bearer '.implode(', ', $parameters),
            'Cache-Control' => 'private, no-store',
            'Pragma' => 'no-cache',
        ]);
    }

    public static function unavailableResponse(): JsonResponse
    {
        return response()->json([
            'error' => 'temporarily_unavailable',
            'message' => 'API authorization is currently unavailable.',
        ], 503, ['Cache-Control' => 'private, no-store', 'Retry-After' => '5']);
    }

    private static function validOrigin(mixed $url): bool
    {
        if (! is_string($url) || $url === '' || strlen($url) > 255 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])
            || ! in_array($parts['path'] ?? '', ['', '/'], true)) {
            return false;
        }

        return $parts['scheme'] === 'https'
            || ($parts['scheme'] === 'http'
                && app()->environment('local', 'testing')
                && in_array($parts['host'], ['localhost', '127.0.0.1', '[::1]'], true));
    }
}
