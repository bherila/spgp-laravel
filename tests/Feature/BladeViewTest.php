<?php

namespace Tests\Feature;

use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\ViteTestCase;

/**
 * BladeViewTest - Verifies that every blade view renders without errors.
 *
 * These tests make real HTTP requests to each route and assert HTTP 200.
 * They intentionally do NOT call withoutVite(), so the actual Vite manifest
 * is consulted. A ViteException will be thrown (and the test will fail) if any
 * asset registered with @vite() in a blade template is missing from the manifest.
 *
 * Prerequisites: the Vite manifest must be built before running phpunit.
 *   pnpm run build && vendor/bin/phpunit --configuration phpunit.xml
 *
 * In CI (test.yml / deploy.yml) the build step runs before phpunit automatically.
 */
class BladeViewTest extends ViteTestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $regularUser;
    private Season $season;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $this->admin = User::factory()->admin()->create();
        $this->regularUser = User::factory()->create();
        $this->season = Season::factory()->create();
    }

    // ============================================================
    // Guest / Auth views
    // ============================================================

    public function test_login_view_loads(): void
    {
        $this->get('/login')->assertStatus(200);
    }

    public function test_register_view_loads(): void
    {
        $this->get('/register')->assertStatus(200);
    }

    public function test_forgot_password_view_loads(): void
    {
        $this->get('/forgot-password')->assertStatus(200);
    }

    // ============================================================
    // Authenticated user views
    // ============================================================

    public function test_dashboard_view_loads(): void
    {
        $this->actingAs($this->regularUser)
            ->get('/dashboard')
            ->assertStatus(200);
    }

    public function test_request_form_view_loads(): void
    {
        $this->actingAs($this->regularUser)
            ->get('/request/' . $this->season->id)
            ->assertStatus(200);
    }

    // ============================================================
    // Admin views
    // ============================================================

    public function test_admin_invites_view_loads(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/invites')
            ->assertStatus(200);
    }

    public function test_admin_seasons_view_loads(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/seasons')
            ->assertStatus(200);
    }

    public function test_admin_season_pass_requests_view_loads(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/seasons/' . $this->season->id . '/pass-requests')
            ->assertStatus(200);
    }

    public function test_admin_promo_code_repository_view_loads(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/seasons/' . $this->season->id . '/promo-codes')
            ->assertStatus(200);
    }

    public function test_admin_email_log_view_loads(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/email-log')
            ->assertStatus(200);
    }

    public function test_admin_users_view_loads(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/users')
            ->assertStatus(200);
    }
}
