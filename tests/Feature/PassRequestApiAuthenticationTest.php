<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\OAuthMetadataController;
use App\Http\Middleware\AuthenticatePassRequestApi;
use App\Http\Middleware\RequirePassRequestScope;
use App\Models\User;
use App\Services\PassRequests\ApiAccess;
use BWH\Auth\OAuth\Introspection\IntrospectedToken;
use BWH\Auth\OAuth\Introspection\OAuthIntrospectionException;
use BWH\Auth\OAuth\Introspection\OAuthTokenIntrospector;
use BWH\Auth\OAuth\Introspection\OAuthTokenValidationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PassRequestApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'pass-request-api.enabled' => true,
            'bherila-auth.oauth_resource_server.issuer' => 'https://identity.example.test',
            'bherila-auth.oauth_resource_server.resource' => 'https://spgp.example.test',
        ]);

        Route::get('/testing/pass-request-api', fn () => response()->json([
            'user_id' => ApiAccess::user()->id,
            'guard_user_id' => Auth::id(),
            'request_user_id' => request()->user()->id,
        ]))->middleware([AuthenticatePassRequestApi::class, RequirePassRequestScope::class.':pass-requests:read']);

        Route::get('/testing/pass-request-api/admin', fn () => response()->json(['ok' => true]))
            ->middleware([AuthenticatePassRequestApi::class, RequirePassRequestScope::class.':admin:pass-requests:read', 'can:admin']);

        Route::get('/testing/pass-request-api/metadata', OAuthMetadataController::class);
    }

    public function test_valid_token_resolves_only_the_explicitly_linked_local_user(): void
    {
        $user = $this->linkedUser();
        $this->fakeToken($this->token());

        $this->withToken('valid-bearer')->getJson('/testing/pass-request-api')
            ->assertOk()
            ->assertExactJson(['user_id' => $user->id, 'guard_user_id' => $user->id, 'request_user_id' => $user->id]);

        $this->assertNull(Auth::guard(ApiAccess::GUARD)->user());
        $this->assertSame('web', Auth::getDefaultDriver());
        $this->assertNull(request()->attributes->get(ApiAccess::TOKEN_ATTRIBUTE));
    }

    public function test_session_login_never_substitutes_for_a_bearer_token(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->mock(OAuthTokenIntrospector::class, fn (MockInterface $mock) => $mock->shouldNotReceive('introspect'));

        $this->get('/testing/pass-request-api')->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json')
            ->assertHeader('WWW-Authenticate', 'Bearer resource_metadata="https://spgp.example.test/.well-known/oauth-protected-resource"');
    }

    public function test_api_is_disabled_until_explicitly_enabled(): void
    {
        config(['pass-request-api.enabled' => false]);

        $this->getJson('/testing/pass-request-api')->assertNotFound();
        $this->getJson('/testing/pass-request-api/metadata')->assertNotFound();
    }

    public function test_tokens_in_query_parameters_are_never_accepted(): void
    {
        $this->linkedUser();
        $this->mock(OAuthTokenIntrospector::class, fn (MockInterface $mock) => $mock->shouldNotReceive('introspect'));

        $this->getJson('/testing/pass-request-api?access_token=valid-bearer&token=valid-bearer')
            ->assertUnauthorized();
    }

    #[DataProvider('invalidTokenClaims')]
    public function test_invalid_token_context_is_rejected_even_when_an_introspector_returns_it(array $claims): void
    {
        $this->linkedUser();
        $this->fakeToken($this->token($claims));

        $response = $this->withToken('valid-bearer')->getJson('/testing/pass-request-api');
        $response->assertUnauthorized()->assertJsonPath('error', 'invalid_token');
        $this->assertStringContainsString('resource_metadata="https://spgp.example.test/', $response->headers->get('WWW-Authenticate'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public static function invalidTokenClaims(): array
    {
        return [
            'revoked' => [['active' => false]],
            'wrong issuer' => [['issuer' => 'https://other.example.test']],
            'wrong resource' => [['resource' => 'https://other.example.test']],
            'wrong audience' => [['audiences' => ['https://other.example.test']]],
            'malformed audience' => [['audiences' => ['expected' => 'https://spgp.example.test']]],
            'missing expiry' => [['expiresAt' => null]],
            'expired' => [['expiresAt' => 0]],
            'not yet valid' => [['notBefore' => PHP_INT_MAX]],
            'missing subject' => [['subject' => null]],
            'blank subject' => [['subject' => ' ']],
            'oversized subject' => [['subject' => str_repeat('s', 256)]],
            'missing client' => [['clientId' => null]],
            'malformed scope' => [['scopes' => [42]]],
            'space in scope' => [['scopes' => ['pass-requests:read pass-requests:write']]],
        ];
    }

    public function test_unlinked_subject_does_not_gain_access_even_if_it_matches_a_local_id(): void
    {
        $user = User::factory()->create();
        $this->fakeToken($this->token(['subject' => (string) $user->id]));

        $this->withToken('valid-bearer')->getJson('/testing/pass-request-api')->assertUnauthorized();
    }

    public function test_an_existing_binding_to_another_issuer_does_not_grant_access(): void
    {
        $user = $this->linkedUser();
        $user->forceFill(['oauth_provider' => 'https://other.example.test'])->save();
        $this->fakeToken($this->token());

        $this->withToken('valid-bearer')->getJson('/testing/pass-request-api')->assertUnauthorized();
    }

    public function test_subjects_are_case_sensitive_even_on_case_insensitive_databases(): void
    {
        $this->linkedUser();
        $this->fakeToken($this->token(['subject' => 'Opaque-subject']));

        $this->withToken('valid-bearer')->getJson('/testing/pass-request-api')->assertUnauthorized();
    }

    public function test_missing_scope_returns_an_actionable_bearer_challenge(): void
    {
        $this->linkedUser();
        $this->fakeToken($this->token(['scopes' => ['pass-requests:write']]));

        $response = $this->withToken('valid-bearer')->getJson('/testing/pass-request-api');
        $response->assertForbidden()->assertJsonPath('error', 'insufficient_scope');
        $this->assertStringContainsString('scope="pass-requests:read"', $response->headers->get('WWW-Authenticate'));
    }

    public function test_admin_scope_does_not_turn_a_local_user_into_an_administrator(): void
    {
        $this->linkedUser();
        $this->fakeToken($this->token(['scopes' => ['admin:pass-requests:read']]));

        $this->withToken('valid-bearer')->getJson('/testing/pass-request-api/admin')->assertForbidden();
    }

    public function test_local_administrator_still_needs_the_admin_scope(): void
    {
        $this->linkedUser()->forceFill(['is_admin' => true])->save();
        $this->fakeToken($this->token());

        $this->withToken('valid-bearer')->getJson('/testing/pass-request-api/admin')
            ->assertForbidden()->assertJsonPath('error', 'insufficient_scope');
    }

    public function test_local_administrator_with_admin_scope_is_allowed(): void
    {
        $this->linkedUser()->forceFill(['is_admin' => true])->save();
        $this->fakeToken($this->token(['scopes' => ['admin:pass-requests:read']]));

        $this->withToken('valid-bearer')->getJson('/testing/pass-request-api/admin')->assertOk();
    }

    public function test_introspection_validation_exception_is_an_invalid_token(): void
    {
        $this->mock(OAuthTokenIntrospector::class, fn (MockInterface $mock) => $mock->shouldReceive('introspect')
            ->once()->andThrow(new OAuthTokenValidationException('Invalid claims.')));

        $this->withToken('valid-bearer')->getJson('/testing/pass-request-api')
            ->assertUnauthorized()->assertJsonPath('error', 'invalid_token');
    }

    public function test_introspection_outage_is_retryable_without_exposing_internal_errors(): void
    {
        $this->mock(OAuthTokenIntrospector::class, fn (MockInterface $mock) => $mock->shouldReceive('introspect')
            ->once()->andThrow(new OAuthIntrospectionException('Private upstream details.')));

        $this->withToken('valid-bearer')->getJson('/testing/pass-request-api')
            ->assertServiceUnavailable()->assertJsonPath('error', 'temporarily_unavailable')
            ->assertDontSee('Private upstream details')->assertHeader('Retry-After', '5');
    }

    public function test_real_introspection_rechecks_revocation_on_every_request(): void
    {
        $this->linkedUser();
        config([
            'bherila-auth.oauth_resource_server.introspection_endpoint' => 'https://identity.example.test/oauth/introspect',
            'bherila-auth.oauth_resource_server.client_id' => 'spgp-resource',
            'bherila-auth.oauth_resource_server.client_secret' => 'synthetic-secret',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://identity.example.test/oauth/introspect' => Http::sequence()->push([
                'active' => true,
                'iss' => 'https://identity.example.test',
                'sub' => 'opaque-subject',
                'client_id' => 'agent-client',
                'scope' => 'pass-requests:read',
                'aud' => ['https://spgp.example.test'],
                'resource' => 'https://spgp.example.test',
                'exp' => now()->addHour()->getTimestamp(),
            ])->push(['active' => false]),
        ]);

        $this->withToken('valid-bearer')->getJson('/testing/pass-request-api')->assertOk();
        $this->withToken('valid-bearer')->getJson('/testing/pass-request-api')->assertUnauthorized();

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->url() === 'https://identity.example.test/oauth/introspect'
            && $request['token'] === 'valid-bearer'
            && $request['token_type_hint'] === 'access_token'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('spgp-resource:synthetic-secret')));
    }

    public function test_real_introspection_rejects_malformed_envelopes_as_an_upstream_failure(): void
    {
        config([
            'bherila-auth.oauth_resource_server.introspection_endpoint' => 'https://identity.example.test/oauth/introspect',
            'bherila-auth.oauth_resource_server.client_id' => 'spgp-resource',
            'bherila-auth.oauth_resource_server.client_secret' => 'synthetic-secret',
        ]);
        Http::preventStrayRequests();
        Http::fake(['https://identity.example.test/oauth/introspect' => Http::response(['active' => 'yes'])]);

        $this->withToken('valid-bearer')->getJson('/testing/pass-request-api')->assertServiceUnavailable();
    }

    public function test_invalid_configuration_fails_closed_without_using_the_request_host(): void
    {
        config(['bherila-auth.oauth_resource_server.resource' => null]);

        $this->withHeader('Host', 'attacker.example.test')->getJson('/testing/pass-request-api')
            ->assertServiceUnavailable()->assertDontSee('attacker.example.test');
    }

    public function test_metadata_and_challenges_use_configured_origins_regardless_of_host_header(): void
    {
        $this->withHeader('Host', 'attacker.example.test')->getJson('/testing/pass-request-api/metadata')
            ->assertOk()->assertJsonPath('resource', 'https://spgp.example.test')
            ->assertJsonPath('authorization_servers', ['https://identity.example.test'])
            ->assertJsonPath('bearer_methods_supported', ['header'])
            ->assertJsonCount(4, 'scopes_supported')->assertDontSee('attacker.example.test');

        $this->withHeader('Host', 'attacker.example.test')->getJson('/testing/pass-request-api')
            ->assertUnauthorized()->assertHeader('WWW-Authenticate', 'Bearer resource_metadata="https://spgp.example.test/.well-known/oauth-protected-resource"');
    }

    private function linkedUser(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['oauth_provider' => 'https://identity.example.test', 'oauth_subject' => 'opaque-subject'])->save();

        return $user;
    }

    private function token(array $claims = []): IntrospectedToken
    {
        return new IntrospectedToken(...array_replace([
            'active' => true,
            'issuer' => 'https://identity.example.test',
            'subject' => 'opaque-subject',
            'clientId' => 'agent-client',
            'scopes' => ['pass-requests:read'],
            'audiences' => ['https://spgp.example.test'],
            'resource' => 'https://spgp.example.test',
            'expiresAt' => now()->addHour()->getTimestamp(),
        ], $claims));
    }

    private function fakeToken(IntrospectedToken $token): void
    {
        $this->mock(OAuthTokenIntrospector::class, fn (MockInterface $mock) => $mock->shouldReceive('introspect')
            ->with('valid-bearer')->once()->andReturn($token));
    }
}
