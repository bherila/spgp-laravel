<?php

namespace Tests\Feature;

use App\Models\PassRequest;
use App\Models\Season;
use App\Models\SeasonPassType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuplicatePassRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_cannot_create_duplicate_pass_request_same_user()
    {
        $user = User::factory()->create();
        $season = Season::factory()->create();
        $passType = SeasonPassType::factory()->create(['season_id' => $season->id]);

        $data = [
            'season_id' => $season->id,
            'season_pass_type_id' => $passType->id,
            'passholder_first_name' => 'John',
            'passholder_last_name' => 'Doe',
            'passholder_email' => 'john@example.com',
            'passholder_birth_date' => '1990-01-01',
        ];

        // First request
        $response = $this->actingAs($user)->postJson('/api/pass-requests', $data);
        $response->assertStatus(201);

        // Duplicate request (same user)
        $response = $this->actingAs($user)->postJson('/api/pass-requests', $data);
        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'You have already created a pass request for this person. Only one pass request can exist for a Name/Email/DOB per Season.'
        ]);
    }

    public function test_cannot_create_duplicate_pass_request_different_user()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $season = Season::factory()->create();
        $passType = SeasonPassType::factory()->create(['season_id' => $season->id]);

        $data = [
            'season_id' => $season->id,
            'season_pass_type_id' => $passType->id,
            'passholder_first_name' => 'John',
            'passholder_last_name' => 'Doe',
            'passholder_email' => 'john@example.com',
            'passholder_birth_date' => '1990-01-01',
        ];

        // First request by user1
        $response = $this->actingAs($user1)->postJson('/api/pass-requests', $data);
        $response->assertStatus(201);

        // Duplicate request by user2
        $response = $this->actingAs($user2)->postJson('/api/pass-requests', $data);
        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Another user has already created a pass request for this person. Please ensure they haven\'t already requested their own pass. Only one pass request can exist for a Name/Email/DOB per Season.'
        ]);
    }
}
