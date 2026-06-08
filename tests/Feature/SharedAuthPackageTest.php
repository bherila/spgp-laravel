<?php

namespace Tests\Feature;

use App\Models\User;
use BWH\Auth\Mail\TwoFactorLoginMail;
use BWH\Auth\Models\AuthAuditLog;
use BWH\Auth\Models\PasskeyCredential;
use BWH\Auth\Services\TwoFactorService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SharedAuthPackageTest extends TestCase
{
    use RefreshDatabase;

    public function test_passkey_table_has_last_used_at_without_touching_existing_credentials(): void
    {
        $this->assertTrue(Schema::hasColumn('auth_passkeys', 'last_used_at'));
        $this->assertTrue(Schema::hasColumn('auth_passkeys', 'credential_id_hash'));
    }

    public function test_authenticated_user_can_list_passkeys_from_shared_package(): void
    {
        $user = User::factory()->create();

        $credential = PasskeyCredential::create([
            'user_id' => $user->id,
            'credential_id' => 'existing-passkey-id',
            'public_key' => base64_encode('existing-public-key'),
            'counter' => 0,
            'aaguid' => '00000000-0000-0000-0000-000000000000',
            'name' => 'Existing Passkey',
            'transports' => ['internal'],
        ]);

        $this->assertSame(hash('sha256', 'existing-passkey-id'), $credential->credential_id_hash);

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

    public function test_two_factor_email_links_use_app_url_not_spoofed_host(): void
    {
        // Regression guard for bherila/auth-laravel >= v0.4.3: the 2FA challenge
        // email links must be rooted at app.url, never the (attacker-controllable)
        // request Host header, to prevent host-header injection of the 2FA links.
        config(['app.url' => 'https://spgp.example.test']);
        Mail::fake();

        $user = User::factory()->create();

        // Simulate a login request carrying a spoofed Host header.
        $request = Request::create('https://evil.example.com/login', 'POST');
        $request->headers->set('Host', 'evil.example.com');
        $request->setLaravelSession($this->app['session']->driver());

        $this->app->make(TwoFactorService::class)->startChallenge($user, $request);

        Mail::assertSent(TwoFactorLoginMail::class, function (TwoFactorLoginMail $mail): bool {
            foreach ([$mail->confirmUrl, $mail->reportUrl] as $url) {
                $this->assertStringStartsWith('https://spgp.example.test/', $url);
                $this->assertStringNotContainsString('evil.example.com', $url);
            }

            return true;
        });
    }

    public function test_user_login_redirect_policy_preserves_dashboard_destination(): void
    {
        $user = User::factory()->create();

        $this->assertSame('/dashboard', $user->getLoginRedirectUrl());
    }

    public function test_legacy_user_logins_are_backfilled_to_package_audit_log(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'admin@example.com']);
        $user = User::factory()->create(['email' => 'member@example.com']);

        Schema::dropIfExists('user_logins');
        Schema::create('user_logins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->index();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->boolean('successful')->default(false);
            $table->string('failure_reason')->nullable();
            $table->timestamps();
        });

        DB::table('user_logins')->insert([
            [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => '203.0.113.20',
                'user_agent' => 'Success Agent',
                'successful' => true,
                'failure_reason' => null,
                'created_at' => now()->subMinutes(3),
                'updated_at' => now()->subMinutes(3),
            ],
            [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => '203.0.113.21',
                'user_agent' => 'Failure Agent',
                'successful' => false,
                'failure_reason' => 'Invalid credentials',
                'created_at' => now()->subMinutes(2),
                'updated_at' => now()->subMinutes(2),
            ],
            [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => '203.0.113.22',
                'user_agent' => 'Impersonation Agent',
                'successful' => true,
                'failure_reason' => 'Impersonated by admin: '.$admin->email,
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ],
        ]);

        $migration = require database_path('migrations/2026_06_04_000001_backfill_user_logins_to_auth_audit_log.php');
        $migration->up();

        $this->assertFalse(Schema::hasTable('user_logins'));
        $this->assertSame(3, AuthAuditLog::count());

        $failure = AuthAuditLog::query()
            ->where('event', AuthAuditLog::EVENT_LOGIN_FAILED)
            ->firstOrFail();

        $this->assertSame('password', $failure->auth_method);
        $this->assertSame('Invalid credentials', $failure->reason);
        $this->assertSame('203.0.113.21', $failure->ip_address);

        $impersonation = AuthAuditLog::query()
            ->where('auth_method', 'impersonation')
            ->firstOrFail();

        $this->assertSame($admin->id, $impersonation->acting_user_id);
        $this->assertSame(['impersonated_by' => $admin->email], $impersonation->metadata);
    }
}
