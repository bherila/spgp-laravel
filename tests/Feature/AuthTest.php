<?php

namespace Tests\Feature;

use App\Models\InviteCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the login page is accessible.
     */
    public function test_login_page_is_accessible(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
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
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'invite_code_id' => $inviteCode->id,
        ]);
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

        // First user uses the code
        User::create([
            'name' => 'First User',
            'email' => 'first@example.com',
            'password' => bcrypt('password'),
            'invite_code_id' => $inviteCode->id,
        ]);

        // Second user tries to use same code
        $response = $this->post('/register', [
            'name' => 'Second User',
            'email' => 'second@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invite_code' => 'LIMITED_CODE',
        ]);

        $response->assertSessionHasErrors('invite_code');
        $this->assertDatabaseMissing('users', ['email' => 'second@example.com']);
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
