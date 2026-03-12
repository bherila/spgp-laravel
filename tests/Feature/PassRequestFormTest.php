<?php

namespace Tests\Feature;

use App\Models\PassRequest;
use App\Models\Season;
use App\Models\SeasonPassType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PassRequestFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_active_seasons_includes_user_previous_pass_type_id()
    {
        $user = User::factory()->create();
        $season = Season::factory()->create([
            'start_date' => now()->subDay(),
            'early_spring_deadline' => now()->addMonth(),
            'final_deadline' => now()->addMonths(2),
        ]);
        $passType = SeasonPassType::factory()->create(['season_id' => $season->id]);

        // Create a pass request by this user for this season
        PassRequest::create([
            'user_id' => $user->id,
            'season_id' => $season->id,
            'season_pass_type_id' => $passType->id,
            'pass_type' => $passType->pass_type_name,
            'passholder_first_name' => 'John',
            'passholder_last_name' => 'Doe',
            'passholder_email' => 'john@example.com',
            'passholder_birth_date' => '1990-01-01',
        ]);

        $response = $this->actingAs($user)->getJson('/api/pass-requests/seasons');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'user_previous_pass_type_id' => $passType->id,
        ]);
    }

    public function test_get_active_seasons_returns_null_previous_pass_type_when_no_prior_request()
    {
        $user = User::factory()->create();
        Season::factory()->create([
            'start_date' => now()->subDay(),
            'early_spring_deadline' => now()->addMonth(),
            'final_deadline' => now()->addMonths(2),
        ]);

        $response = $this->actingAs($user)->getJson('/api/pass-requests/seasons');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'user_previous_pass_type_id' => null,
        ]);
    }

    public function test_get_active_seasons_returns_most_recent_pass_type_for_season()
    {
        $user = User::factory()->create();
        $season = Season::factory()->create([
            'start_date' => now()->subDay(),
            'early_spring_deadline' => now()->addMonth(),
            'final_deadline' => now()->addMonths(2),
        ]);
        $passType1 = SeasonPassType::factory()->create(['season_id' => $season->id, 'sort_order' => 1]);
        $passType2 = SeasonPassType::factory()->create(['season_id' => $season->id, 'sort_order' => 2]);

        // Create two pass requests; the most recent should be returned
        $pr1 = new PassRequest([
            'user_id' => $user->id,
            'season_id' => $season->id,
            'season_pass_type_id' => $passType1->id,
            'pass_type' => $passType1->pass_type_name,
            'passholder_first_name' => 'John',
            'passholder_last_name' => 'Doe',
            'passholder_email' => 'john@example.com',
            'passholder_birth_date' => '1990-01-01',
        ]);
        $pr1->created_at = now()->subMinutes(10);
        $pr1->save();

        $pr2 = new PassRequest([
            'user_id' => $user->id,
            'season_id' => $season->id,
            'season_pass_type_id' => $passType2->id,
            'pass_type' => $passType2->pass_type_name,
            'passholder_first_name' => 'Jane',
            'passholder_last_name' => 'Doe',
            'passholder_email' => 'jane@example.com',
            'passholder_birth_date' => '1992-06-15',
        ]);
        $pr2->created_at = now();
        $pr2->save();

        $response = $this->actingAs($user)->getJson('/api/pass-requests/seasons');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'user_previous_pass_type_id' => $passType2->id,
        ]);
    }
}
