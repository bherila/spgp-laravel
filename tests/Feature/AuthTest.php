<?php

namespace Tests\Feature;

use App\Models\InviteCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    /**
     * Test that the login page is accessible.
     */
    public function test_login_page_is_accessible(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    /**
     * Test that the login page includes the Vite entrypoint script.
     */
    public function test_login_page_includes_vite_entrypoint(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        // Assert that the compiled script tag for the login entrypoint is present
        $response->assertSee('resources/js/auth/login.tsx', false);
    }

    /**
     * Test that the register page is accessible.
     */
    public function test_register_page_is_accessible(): void
    {
        $response = $this->get('/register');
        $response->assertStatus(200);
    }

    /**
     * Test that unauthenticated users are redirected from dashboard.
     */
    public function test_unauthenticated_users_cannot_access_dashboard(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    /**
     * Test that registration requires a valid invite code.
     */
    public function test_registration_requires_valid_invite_code(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invite_code' => 'INVALID_CODE',
            'agreement' => 'on',
        ]);

        $response->assertSessionHasErrors('invite_code');
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    /**
     * Test that registration works with a valid invite code.
     */
    public function test_registration_works_with_valid_invite_code(): void
    {
        $inviteCode = InviteCode::create([
            'invite_code' => 'VALID_CODE',
            'max_number_of_uses' => 10,
        ]);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invite_code' => 'VALID_CODE',
            'agreement' => 'on',
        ]);

        $response->assertRedirect('/dashboard');
        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue(
            $user->inviteCodes()->where('invite_codes.id', $inviteCode->id)->exists(),
            'User should be linked to the invite code via pivot table'
        );
    }

    /**
     * Test that exhausted invite codes cannot be used.
     */
    public function test_exhausted_invite_code_cannot_be_used(): void
    {
        $inviteCode = InviteCode::create([
            'invite_code' => 'LIMITED_CODE',
            'max_number_of_uses' => 1,
        ]);

        // First user uses the code (via the pivot table)
        $firstUser = User::create([
            'name' => 'First User',
            'email' => 'first@example.com',
            'password' => bcrypt('password'),
        ]);
        $firstUser->inviteCodes()->attach($inviteCode->id);

        // Second user tries to use same code
        $response = $this->post('/register', [
            'name' => 'Second User',
            'email' => 'second@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invite_code' => 'LIMITED_CODE',
            'agreement' => 'on',
        ]);

        $response->assertSessionHasErrors('invite_code');
        $this->assertDatabaseMissing('users', ['email' => 'second@example.com']);
    }

    /**
     * Test that registration requires agreement checkbox to be checked.
     */
    public function test_registration_requires_agreement(): void
    {
        InviteCode::create([
            'invite_code' => 'AGREE_CODE',
            'max_number_of_uses' => 5,
        ]);

        $response = $this->post('/register', [
            'name' => 'Agree User',
            'email' => 'agree@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invite_code' => 'AGREE_CODE',
            // agreement omitted on purpose
        ]);

        $response->assertSessionHasErrors('agreement');
        $this->assertDatabaseMissing('users', ['email' => 'agree@example.com']);
    }

    /**
     * Test that authenticated users can access dashboard.
     */
    public function test_authenticated_users_can_access_dashboard(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
    }

    /**
     * Test that authenticated users can logout.
     */
    public function test_users_can_logout(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($user)->post('/logout');
        $response->assertRedirect('/');
        $this->assertGuest();
    }
}
