<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Example feature test demonstrating safe database usage.
 *
 * This test uses RefreshDatabase, which will run migrations on each test.
 * Because we enforce SQLite in-memory via SafeTestCase, this is safe
 * and will never accidentally affect a MySQL database.
 */
class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that unauthenticated users are redirected to login.
     */
    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get('/');

        // Home page redirects to login for unauthenticated users
        $response->assertRedirect('/login');
    }

    /**
     * Test that authenticated users are redirected to dashboard.
     */
    public function test_authenticated_user_redirected_to_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        // Home page redirects to dashboard for authenticated users
        $response->assertRedirect('/dashboard');
    }

    /**
     * Test that we are using SQLite in-memory database.
     *
     * This test verifies our safety mechanism is working.
     */
    public function test_database_is_sqlite_in_memory(): void
    {
        $this->assertEquals('sqlite', $this->getDatabaseDriver());
        $this->assertEquals(':memory:', $this->getDatabaseName());
    }

    /**
     * Test that database tables can be created via migrations.
     *
     * This confirms RefreshDatabase is working with SQLite.
     */
    public function test_migrations_create_expected_tables(): void
    {
        // These tables should exist after RefreshDatabase runs migrations
        $this->assertTrue(
            \Schema::hasTable('users'),
            'Users table should exist after migrations'
        );
        $this->assertTrue(
            \Schema::hasTable('sessions'),
            'Sessions table should exist after migrations'
        );
        $this->assertTrue(
            \Schema::hasTable('cache'),
            'Cache table should exist after migrations'
        );
        $this->assertTrue(
            \Schema::hasTable('jobs'),
            'Jobs table should exist after migrations'
        );
    }
}
