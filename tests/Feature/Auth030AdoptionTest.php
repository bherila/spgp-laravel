<?php

namespace Tests\Feature;

use App\Models\User;
use BWH\Auth\Mail\TwoFactorLoginMail;
use BWH\Auth\Models\TwoFactorAttempt;
use BWH\Auth\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class Auth030AdoptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrollment_requires_recent_confirmation_and_recovers_after_password_confirmation(): void
    {
        $user = User::factory()->create(['password' => Hash::make('current-password')]);
        $this->actingAs($user)->postJson('/api/passkeys/register/options')
            ->assertStatus(423)->assertJsonPath('error', 'password_confirmation_required');
        $this->postJson('/api/auth/confirm-password', ['password' => 'wrong-password'])->assertUnprocessable();
        $this->postJson('/api/passkeys/register/options')->assertStatus(423);
        $this->postJson('/api/auth/confirm-password', ['password' => 'current-password'])->assertOk();
        $this->postJson('/api/passkeys/register/options')->assertOk()->assertJsonStructure(['challenge']);
        $this->travel((int) config('auth.password_timeout', 10800) + 1)->seconds();
        $this->postJson('/api/passkeys/register/options')->assertStatus(423);
    }

    public function test_email_link_requires_a_separate_hashed_secret(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $request = Request::create('https://spgp.example.test/login', 'POST');
        $request->setLaravelSession($this->app['session']->driver());
        app(TwoFactorService::class)->startChallenge($user, $request);
        $attempt = TwoFactorAttempt::query()->sole();

        $this->postJson('/api/auth/two-factor/confirm/'.$attempt->token)->assertUnprocessable();
        $this->assertGuest();
        Mail::assertSent(TwoFactorLoginMail::class, function (TwoFactorLoginMail $mail) use ($attempt): bool {
            parse_str((string) parse_url((string) $mail->confirmUrl, PHP_URL_QUERY), $query);
            $secret = $query['secret'] ?? '';
            $this->assertSame(64, strlen($secret));
            $this->assertSame(hash('sha256', $secret), $attempt->link_secret_hash);

            return true;
        });
    }

    public function test_admin_password_change_retires_target_sessions_without_signing_in_as_target(): void
    {
        $this->databaseSessions();
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['remember_token' => 'old-target-token']);
        $this->device('target-device', $target->id);
        $this->device('admin-device', $admin->id);

        $this->actingAs($admin)->putJson('/api/admin/users/'.$target->id, ['password' => 'replacement-password'])->assertOk();

        $this->assertAuthenticatedAs($admin);
        $this->assertTrue(Hash::check('replacement-password', $target->fresh()->password));
        $this->assertNotSame('old-target-token', $target->fresh()->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'target-device']);
        $this->assertDatabaseHas('sessions', ['id' => 'admin-device']);
    }

    public function test_invalid_admin_password_update_preserves_credentials_and_sessions(): void
    {
        $this->databaseSessions();
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['password' => Hash::make('current-password'), 'remember_token' => 'old-target-token']);
        $this->device('target-device', $target->id);

        $this->actingAs($admin)->putJson('/api/admin/users/'.$target->id, ['password' => 'short'])->assertUnprocessable();

        $this->assertTrue(Hash::check('current-password', $target->fresh()->password));
        $this->assertSame('old-target-token', $target->fresh()->remember_token);
        $this->assertDatabaseHas('sessions', ['id' => 'target-device']);
    }

    public function test_custom_password_reset_deletes_old_sessions_and_keeps_other_accounts(): void
    {
        $this->databaseSessions();
        $user = User::factory()->create(['remember_token' => 'old-target-token']);
        $other = User::factory()->create();
        $this->device('target-device', $user->id);
        $this->device('foreign-device', $other->id);
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token, 'email' => $user->email,
            'password' => 'replacement-password', 'password_confirmation' => 'replacement-password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('replacement-password', $user->fresh()->password));
        $this->assertNotSame('old-target-token', $user->fresh()->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'target-device']);
        $this->assertDatabaseHas('sessions', ['id' => 'foreign-device']);
    }

    public function test_old_password_hash_session_is_refused(): void
    {
        $user = User::factory()->admin()->create();
        $oldHash = $user->password;
        $user->forceFill(['password' => Hash::make('replacement-password')])->save();

        $this->actingAs($user)->withSession(['password_hash_web' => $oldHash])
            ->getJson('/api/admin/users/list')->assertUnauthorized();
        $this->assertGuest();
    }

    private function databaseSessions(): void
    {
        config(['session.driver' => 'database', 'session.lottery' => [0, 100]]);
    }

    private function device(string $id, int $userId): void
    {
        DB::table('sessions')->insert([
            'id' => $id, 'user_id' => $userId, 'payload' => base64_encode(serialize([])), 'last_activity' => now()->timestamp,
        ]);
    }
}
