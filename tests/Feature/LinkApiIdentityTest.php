<?php

namespace Tests\Feature;

use App\Models\ApiIdentityLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinkApiIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'bherila-auth.oauth_resource_server.issuer' => 'https://identity.example.test',
            'bherila-auth.oauth_resource_server.resource' => 'https://spgp.example.test',
        ]);
    }

    public function test_operator_can_link_an_existing_user_without_changing_local_permissions(): void
    {
        $user = User::factory()->create();

        $this->artisan('api:link-identity', ['user' => (string) $user->id, 'subject' => 'provider-subject'])->assertSuccessful();

        $this->assertDatabaseHas('api_identity_links', [
            'user_id' => $user->id,
            'provider' => 'https://identity.example.test',
            'subject' => 'provider-subject',
        ]);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_admin' => false]);

        $this->artisan('api:link-identity', ['user' => (string) $user->id, 'subject' => 'provider-subject'])->assertSuccessful();
    }

    public function test_linking_does_not_create_users_or_resolve_email_addresses(): void
    {
        $user = User::factory()->create();

        $this->artisan('api:link-identity', ['user' => $user->email, 'subject' => 'provider-subject'])->assertFailed();
        $this->artisan('api:link-identity', ['user' => '999999', 'subject' => 'provider-subject'])->assertFailed();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('api_identity_links', 0);
    }

    public function test_existing_bindings_cannot_be_silently_replaced_or_reassigned(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        ApiIdentityLink::query()->create(['user_id' => $first->id, 'provider' => 'https://identity.example.test', 'subject' => 'existing-subject']);

        $this->artisan('api:link-identity', ['user' => (string) $first->id, 'subject' => 'replacement-subject'])->assertFailed();
        $this->artisan('api:link-identity', ['user' => (string) $second->id, 'subject' => 'existing-subject'])->assertFailed();

        $this->assertSame('existing-subject', $first->apiIdentityLink()->first()->subject);
        $this->assertNull($second->apiIdentityLink()->first());
    }

    public function test_linking_requires_a_trusted_configured_issuer(): void
    {
        config(['bherila-auth.oauth_resource_server.issuer' => null]);
        $user = User::factory()->create();

        $this->artisan('api:link-identity', ['user' => (string) $user->id, 'subject' => 'provider-subject'])->assertFailed();

        $this->assertDatabaseCount('api_identity_links', 0);
    }
}
