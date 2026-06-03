<?php

namespace Tests\Feature;

use App\Models\User;
use BWH\Auth\Models\PasskeyCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SharedAuthPackageTest extends TestCase
{
    use RefreshDatabase;

    public function test_passkey_table_has_last_used_at_without_touching_existing_credentials(): void
    {
        $this->assertTrue(Schema::hasColumn('auth_passkeys', 'last_used_at'));
    }

    public function test_authenticated_user_can_list_passkeys_from_shared_package(): void
    {
        $user = User::factory()->create();

        PasskeyCredential::create([
            'user_id' => $user->id,
            'credential_id' => 'existing-passkey-id',
            'public_key' => base64_encode('existing-public-key'),
            'counter' => 0,
            'aaguid' => '00000000-0000-0000-0000-000000000000',
            'name' => 'Existing Passkey',
            'transports' => ['internal'],
        ]);

        $response = $this->actingAs($user)->getJson('/api/passkeys');

        $response->assertOk();
        $response->assertJsonFragment([
            'name' => 'Existing Passkey',
            'last_used_at' => null,
        ]);
    }

    public function test_registration_options_use_compatible_passkey_preferences(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('https://spgp.example.test/api/passkeys/register/options');

        $response->assertOk();
        $response->assertJsonPath('rp.id', 'spgp.example.test');
        $response->assertJsonPath('authenticatorSelection.residentKey', 'preferred');
        $response->assertJsonPath('authenticatorSelection.userVerification', 'preferred');
    }

    public function test_user_login_redirect_policy_preserves_dashboard_destination(): void
    {
        $user = User::factory()->create();

        $this->assertSame('/dashboard', $user->getLoginRedirectUrl());
    }
}
