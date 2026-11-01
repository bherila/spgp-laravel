<?php

namespace Tests\Feature;

use App\Models\User;
use BWH\Auth\Contracts\LocalCredentialPolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
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

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'api_identity_issuer' => 'https://identity.example.test',
            'api_identity_subject' => 'provider-subject',
            'is_admin' => false,
        ]);

        $this->artisan('api:link-identity', ['user' => (string) $user->id, 'subject' => 'provider-subject'])->assertSuccessful();
    }

    public function test_linking_does_not_create_users_or_resolve_email_addresses(): void
    {
        $user = User::factory()->create();

        $this->artisan('api:link-identity', ['user' => $user->email, 'subject' => 'provider-subject'])->assertFailed();
        $this->artisan('api:link-identity', ['user' => '999999', 'subject' => 'provider-subject'])->assertFailed();

        $this->assertDatabaseCount('users', 1);
        $this->assertNull($user->fresh()->api_identity_subject);
    }

    public function test_existing_bindings_cannot_be_silently_replaced_or_reassigned(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $first->forceFill(['api_identity_issuer' => 'https://identity.example.test', 'api_identity_subject' => 'existing-subject'])->save();

        $this->artisan('api:link-identity', ['user' => (string) $first->id, 'subject' => 'replacement-subject'])->assertFailed();
        $this->artisan('api:link-identity', ['user' => (string) $second->id, 'subject' => 'existing-subject'])->assertFailed();

        $this->assertSame('existing-subject', $first->fresh()->api_identity_subject);
        $this->assertNull($second->fresh()->api_identity_subject);
    }

    public function test_linking_requires_a_trusted_configured_issuer(): void
    {
        config(['bherila-auth.oauth_resource_server.issuer' => null]);
        $user = User::factory()->create();

        $this->artisan('api:link-identity', ['user' => (string) $user->id, 'subject' => 'provider-subject'])->assertFailed();

        $this->assertNull($user->fresh()->api_identity_subject);
    }

    public function test_api_linking_leaves_a_sign_in_binding_and_local_credentials_unchanged(): void
    {
        if (! Schema::hasColumn('users', 'oauth_provider')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('oauth_provider')->nullable();
                $table->string('oauth_subject')->nullable();
            });
        }
        $user = User::factory()->create();
        $user->forceFill(['oauth_provider' => 'browser-provider', 'oauth_subject' => 'browser-subject'])->save();
        $password = $user->password;

        $this->artisan('api:link-identity', ['user' => (string) $user->id, 'subject' => 'api-subject'])->assertSuccessful();

        $user->refresh();
        $this->assertSame('https://identity.example.test', $user->api_identity_issuer);
        $this->assertSame('api-subject', $user->api_identity_subject);
        $this->assertSame('browser-provider', $user->oauth_provider);
        $this->assertSame('browser-subject', $user->oauth_subject);
        $this->assertSame($password, $user->password);
        $this->assertFalse($user->is_admin);
        $this->assertSame('allow', config('bherila-auth.local_credentials.provider_bound'));
        $this->assertTrue(app(LocalCredentialPolicy::class)->mayUseLocalCredentials($user, Request::create('/login', 'POST')));
    }
}
