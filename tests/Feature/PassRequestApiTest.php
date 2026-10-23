<?php

namespace Tests\Feature;

use App\Mail\PassCodeNotification;
use App\Models\PassRequest;
use App\Models\SeasonPassType;
use App\Models\User;
use App\Services\PassRequests\UserPassRequestService;
use BWH\Auth\OAuth\Introspection\IntrospectedToken;
use BWH\Auth\OAuth\Introspection\OAuthTokenIntrospector;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PassRequestApiTest extends TestCase
{
    use RefreshDatabase;

    private const ISSUER = 'https://identity.example.test';

    private const RESOURCE = 'https://passes.example.test';

    /** @var array<string, IntrospectedToken> */
    private array $tokens = [];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'pass-request-api.enabled' => true,
            'bherila-auth.oauth_resource_server.issuer' => self::ISSUER,
            'bherila-auth.oauth_resource_server.resource' => self::RESOURCE,
        ]);
        $resolve = fn (string $token): IntrospectedToken => $this->tokens[$token] ?? IntrospectedToken::inactive();
        $this->app->instance(OAuthTokenIntrospector::class, new class($resolve) implements OAuthTokenIntrospector
        {
            public function __construct(private Closure $resolve) {}

            public function introspect(string $token): IntrospectedToken
            {
                return ($this->resolve)($token);
            }
        });
    }

    public function test_api_rejects_cookies_and_uses_the_bearer_identity_even_when_another_user_is_logged_in(): void
    {
        $owner = $this->boundUser();
        $other = $this->boundUser();
        $own = $this->record($owner);
        $this->record($other);

        $this->actingAs($other)->getJson('/api/v1/pass-requests')->assertUnauthorized();
        $response = $this->getJson('/api/v1/pass-requests', $this->bearer($owner, ['pass-requests:read']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('meta.total', 1);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_read_and_write_scopes_are_enforced_independently(): void
    {
        $owner = $this->boundUser();
        $input = $this->input();

        $this->postJson('/api/v1/pass-requests', $input, $this->bearer($owner, ['pass-requests:read']))
            ->assertForbidden()->assertJsonPath('error', 'insufficient_scope');
        $this->getJson('/api/v1/pass-requests', $this->bearer($owner, ['pass-requests:write']))
            ->assertForbidden()->assertJsonPath('error', 'insufficient_scope');
        $this->postJson('/api/v1/pass-requests', $input, $this->bearer($owner, ['pass-requests:write']))
            ->assertCreated()->assertJsonPath('data.user_id', $owner->id);
    }

    public function test_user_endpoints_keep_an_administrator_owner_scoped(): void
    {
        $owner = $this->boundUser();
        $admin = $this->boundUser(true);
        $foreign = $this->record($owner);
        $own = $this->record($admin);
        $headers = $this->bearer($admin, ['pass-requests:read', 'pass-requests:write', 'admin:pass-requests:read', 'admin:pass-requests:write']);

        $this->getJson('/api/v1/pass-requests', $headers)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        $this->getJson('/api/v1/pass-requests/'.$foreign->id, $headers)->assertForbidden();
        $this->patchJson('/api/v1/pass-requests/'.$foreign->id, ['passholder_first_name' => 'Changed'], $headers)->assertForbidden();
        $this->deleteJson('/api/v1/pass-requests/'.$foreign->id, [], $headers)->assertForbidden();
        $this->assertModelExists($foreign);
    }

    public function test_admin_endpoints_require_both_local_admin_permission_and_admin_scope(): void
    {
        $member = $this->boundUser();
        $admin = $this->boundUser(true);
        $record = $this->record($member);

        $this->getJson('/api/v1/admin/pass-requests', $this->bearer($member, ['admin:pass-requests:read']))->assertForbidden();
        $this->getJson('/api/v1/admin/pass-requests', $this->bearer($admin, ['pass-requests:read']))->assertForbidden();
        $this->getJson('/api/v1/admin/pass-requests', $this->bearer($admin, ['admin:pass-requests:read']))
            ->assertOk()->assertJsonPath('data.0.id', $record->id);
        $this->patchJson('/api/v1/admin/pass-requests/'.$record->id, ['passholder_first_name' => 'Changed'], $this->bearer($admin, ['admin:pass-requests:read']))
            ->assertForbidden();
        $this->patchJson('/api/v1/admin/pass-requests/'.$record->id, ['passholder_first_name' => 'Changed'], $this->bearer($admin, ['admin:pass-requests:write']))
            ->assertOk()->assertJsonPath('data.passholder_first_name', 'Changed');
    }

    public function test_create_rejects_a_pass_type_from_a_different_season_and_ignores_privileged_attributes(): void
    {
        $owner = $this->boundUser();
        $other = $this->boundUser();
        $input = $this->input();
        $unrelatedType = SeasonPassType::factory()->create();
        $headers = $this->bearer($owner, ['pass-requests:write']);

        $this->postJson('/api/v1/pass-requests', [...$input, 'season_pass_type_id' => $unrelatedType->id], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('season_pass_type_id');
        $this->postJson('/api/v1/pass-requests', [...$input, 'season_id' => []], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('season_id');
        $this->postJson('/api/v1/pass-requests', [
            ...$input,
            'user_id' => $other->id,
            'promo_code' => 'INJECTED',
            'email_notify_time' => '2026-01-01T00:00:00Z',
            'redemption_date' => '2026-01-01',
        ], $headers)->assertCreated()->assertJsonPath('data.user_id', $owner->id)->assertJsonPath('data.promo_code', null);
        $record = PassRequest::sole();
        $this->assertNull($record->email_notify_time);
        $this->assertNull($record->redemption_date);
    }

    public function test_route_id_overrides_a_conflicting_body_id(): void
    {
        $owner = $this->boundUser();
        $other = $this->boundUser();
        $own = $this->record($owner);
        $foreign = $this->record($other);

        $this->patchJson('/api/v1/pass-requests/'.$own->id, [
            'id' => $foreign->id,
            'user_id' => $other->id,
            'passholder_first_name' => 'Changed',
        ], $this->bearer($owner, ['pass-requests:write']))
            ->assertOk()->assertJsonPath('data.id', $own->id)->assertJsonPath('data.passholder_first_name', 'Changed');
        $this->assertSame('John', $foreign->fresh()->passholder_first_name);
        $this->assertSame($owner->id, $own->fresh()->user_id);
    }

    public function test_admin_action_season_route_cannot_be_overridden_by_the_body(): void
    {
        $owner = $this->boundUser();
        $admin = $this->boundUser(true);
        $first = $this->record($owner);
        $second = $this->record($owner);

        $this->postJson('/api/v1/admin/seasons/'.$first->season_id.'/pass-requests/assign-codes', [
            'season_id' => $second->season_id,
            'pass_request_ids' => [$second->id],
            'codes' => 'WRONG-SEASON',
        ], $this->bearer($admin, ['admin:pass-requests:write']))->assertUnprocessable();
        $this->assertNull($first->fresh()->promo_code);
        $this->assertNull($second->fresh()->promo_code);
    }

    #[DataProvider('invalidPagination')]
    public function test_lists_reject_unbounded_or_invalid_pagination(string $query): void
    {
        $admin = $this->boundUser(true);
        $headers = $this->bearer($admin, ['pass-requests:read', 'admin:pass-requests:read']);

        $this->getJson('/api/v1/pass-requests?'.$query, $headers)->assertUnprocessable();
        $this->getJson('/api/v1/admin/pass-requests?'.$query, $headers)->assertUnprocessable();
    }

    public static function invalidPagination(): array
    {
        return [
            'zero page size' => ['per_page=0'],
            'unbounded page size' => ['per_page=101'],
            'zero page' => ['page=0'],
            'fractional page' => ['page=1.5'],
        ];
    }

    public function test_paginated_responses_and_admin_user_data_do_not_expose_authentication_fields(): void
    {
        $owner = $this->boundUser();
        $admin = $this->boundUser(true);
        $this->record($owner);
        $this->record($owner);
        $headers = $this->bearer($owner, ['pass-requests:read']);

        $this->getJson('/api/v1/pass-requests?per_page=1', $headers)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.per_page', 1)->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2)
            ->assertJsonMissingPath('data.0.user');
        $response = $this->getJson('/api/v1/admin/pass-requests?per_page=1', $this->bearer($admin, ['admin:pass-requests:read']))
            ->assertOk()->assertJsonPath('data.0.user.email', $owner->email)
            ->assertJsonMissingPath('data.0.user.password')->assertJsonMissingPath('data.0.user.remember_token')
            ->assertJsonMissingPath('data.0.user.oauth_provider')->assertJsonMissingPath('data.0.user.oauth_subject');
        $this->assertSame(['id', 'first_name', 'last_name', 'email'], array_keys($response->json('data.0.user')));
    }

    public function test_seasons_and_renewal_country_routes_use_the_same_user_records(): void
    {
        $owner = $this->boundUser();
        $record = $this->record($owner);
        $headers = $this->bearer($owner, ['pass-requests:read', 'pass-requests:write']);

        $this->getJson('/api/v1/seasons', $headers)->assertOk()->assertJsonPath('data.0.id', $record->season_id);
        $this->getJson('/api/v1/seasons/'.$record->season_id.'/pass-types', $headers)
            ->assertOk()->assertJsonPath('data.0.id', $record->season_pass_type_id);
        $this->putJson('/api/v1/pass-requests/'.$record->id.'/renewal-order', ['renewal_order_number' => 'ORDER-123'], $headers)
            ->assertOk()->assertJsonPath('data.renewal_order_number', 'ORDER-123');
        $this->assertNotNull($record->fresh()->assign_code_date);
        $this->deleteJson('/api/v1/pass-requests/'.$record->id.'/renewal-order', [], $headers)
            ->assertOk()->assertJsonPath('data.renewal_order_number', null)->assertJsonPath('data.assign_code_date', null);
        $record->update(['country' => null]);
        $this->patchJson('/api/v1/pass-requests/missing-country', ['country' => 'Canada'], $headers)
            ->assertOk()->assertJsonPath('updated', 1);
        $this->assertSame('Canada', $record->fresh()->country);
    }

    public function test_admin_assignment_email_and_clear_round_trip_are_visible_in_the_browser_and_user_api(): void
    {
        Mail::fake();
        $owner = $this->boundUser();
        $admin = $this->boundUser(true);
        $record = $this->record($owner);
        $userHeaders = $this->bearer($owner, ['pass-requests:read', 'pass-requests:write']);
        $adminHeaders = $this->bearer($admin, ['admin:pass-requests:write']);
        $selection = ['pass_request_ids' => [$record->id]];
        $actions = '/api/v1/admin/seasons/'.$record->season_id.'/pass-requests/';

        $this->postJson($actions.'assign-codes', $selection + ['codes' => 'API-CODE'], $adminHeaders)
            ->assertOk()->assertJsonPath('assigned', 1);
        $this->getJson('/api/v1/pass-requests/'.$record->id, $userHeaders)
            ->assertOk()->assertJsonPath('data.promo_code', 'API-CODE');
        $this->actingAs($owner)->getJson('/api/pass-requests')
            ->assertOk()->assertJsonPath('0.pass_requests.0.promo_code', 'API-CODE');
        $this->deleteJson('/api/v1/pass-requests/'.$record->id, [], $userHeaders)->assertUnprocessable();

        $this->postJson($actions.'send-emails', $selection, $adminHeaders)->assertOk()->assertJsonPath('sent', 1);
        $this->postJson($actions.'send-emails', $selection, $adminHeaders)->assertOk()->assertJsonPath('sent', 0);
        Mail::assertSent(PassCodeNotification::class, 1);
        $this->assertNotNull($record->fresh()->email_notify_time);

        $this->postJson($actions.'clear-codes', $selection, $adminHeaders)->assertOk()->assertJsonPath('cleared', 1);
        $this->getJson('/api/v1/pass-requests/'.$record->id, $userHeaders)
            ->assertOk()->assertJsonPath('data.promo_code', null)->assertJsonPath('data.email_notify_time', null);
        $this->deleteJson('/api/v1/pass-requests/'.$record->id, [], $userHeaders)->assertOk();
        $this->assertModelMissing($record);
    }

    private function boundUser(bool $admin = false): User
    {
        $user = User::factory()->create(['is_admin' => $admin]);
        $user->forceFill(['oauth_provider' => self::ISSUER, 'oauth_subject' => 'subject-'.$user->id])->save();

        return $user;
    }

    private function bearer(User $user, array $scopes): array
    {
        $token = 'token-'.count($this->tokens);
        $this->tokens[$token] = new IntrospectedToken(
            active: true,
            issuer: self::ISSUER,
            subject: $user->oauth_subject,
            clientId: 'test-client',
            scopes: $scopes,
            audiences: [self::RESOURCE],
            resource: self::RESOURCE,
            expiresAt: now()->addHour()->getTimestamp(),
        );

        return ['Authorization' => 'Bearer '.$token];
    }

    private function record(User $owner): PassRequest
    {
        return $this->app->make(UserPassRequestService::class)->create($owner, $this->input());
    }

    private function input(): array
    {
        $type = SeasonPassType::factory()->create();

        return [
            'season_id' => $type->season_id,
            'season_pass_type_id' => $type->id,
            'passholder_first_name' => 'John',
            'passholder_last_name' => 'Doe',
            'passholder_email' => 'john@example.test',
            'passholder_birth_date' => '1990-01-01',
            'country' => 'USA',
        ];
    }
}
