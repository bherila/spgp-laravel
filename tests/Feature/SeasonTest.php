<?php

namespace Tests\Feature;

use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeasonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    /**
     * Test creating a season with allow_renewals.
     */
    public function test_can_create_season_with_allow_renewals(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);

        $response = $this->actingAs($admin)->post('/api/admin/seasons', [
            'pass_name' => 'Test Season',
            'pass_year' => 2026,
            'start_date' => now()->toDateTimeString(),
            'early_spring_deadline' => now()->addMonth()->toDateTimeString(),
            'final_deadline' => now()->addMonths(2)->toDateTimeString(),
            'allow_renewals' => false,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('seasons', [
            'pass_name' => 'Test Season',
            'allow_renewals' => false,
        ]);
    }

    /**
     * Test updating a season's allow_renewals setting.
     */
    public function test_can_update_season_allow_renewals(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);

        $season = Season::create([
            'pass_name' => 'Test Season',
            'pass_year' => 2026,
            'start_date' => now(),
            'early_spring_deadline' => now()->addMonth(),
            'final_deadline' => now()->addMonths(2),
            'allow_renewals' => true,
        ]);

        $response = $this->actingAs($admin)->put("/api/admin/seasons/{$season->id}", [
            'allow_renewals' => false,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('seasons', [
            'id' => $season->id,
            'allow_renewals' => false,
        ]);
    }
}
