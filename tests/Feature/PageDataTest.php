<?php

namespace Tests\Feature;

use App\Models\PassRequest;
use App\Models\Question;
use App\Models\Season;
use App\Models\SeasonPassType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class PageDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_json_data_cannot_escape_the_script_element_and_round_trips_unicode(): void
    {
        $data = ['name' => '</script><script>alert("x")</script> & café 🏔'];
        $html = Blade::render('<x-page-data :data="$data" />', compact('data'));

        $this->assertSame(1, substr_count($html, '<script'));
        $this->assertSame(1, substr_count($html, '</script>'));
        preg_match('/<script id="page-data" type="application\/json">(.*?)<\/script>/s', $html, $matches);
        $this->assertSame($data, json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('alert("x")', $matches[1]);
    }

    public function test_request_page_embeds_the_same_owner_specific_data_as_the_refresh_endpoint(): void
    {
        $user = User::factory()->create();
        $season = Season::factory()->create([
            'start_date' => now()->subDay(),
            'final_deadline' => now()->addMonth(),
        ]);
        $passType = SeasonPassType::factory()->create(['season_id' => $season->id]);
        PassRequest::create([
            'passholder_first_name' => 'Test',
            'passholder_last_name' => 'User',
            'passholder_email' => 'own@example.test',
            'passholder_birth_date' => '1990-01-01',
            'pass_type' => $passType->pass_type_name,
            'user_id' => $user->id,
            'season_id' => $season->id,
            'season_pass_type_id' => $passType->id,
        ]);
        $other = PassRequest::create([
            'user_id' => User::factory()->create()->id,
            'season_id' => $season->id,
            'passholder_first_name' => 'Other',
            'passholder_last_name' => 'User',
            'passholder_email' => 'other-private@example.test',
            'passholder_birth_date' => '1990-01-01',
            'pass_type' => $passType->pass_type_name,
        ]);

        $response = $this->actingAs($user)->get('/request/'.$season->id)->assertOk();
        preg_match('/<script id="page-data" type="application\/json">(.*?)<\/script>/s', $response->getContent(), $matches);
        $data = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
        $refresh = $this->getJson('/api/pass-requests/seasons')->assertOk()->json();

        $this->assertSame($refresh, $data['seasons']);
        $this->assertSame($passType->id, $data['seasons'][0]['user_previous_pass_type_id']);
        $response->assertDontSee($other->passholder_email);
    }

    public function test_questions_are_not_embedded_on_the_normal_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')
            ->assertOk()
            ->assertSee('id="dashboard-data"', false)
            ->assertDontSee('id="questions-data"', false);
    }

    public function test_questions_page_embeds_only_the_selected_seasons_questions(): void
    {
        $user = User::factory()->create();
        $season = Season::factory()->create([
            'start_date' => now()->subDay(),
            'final_deadline' => now()->addMonth(),
        ]);
        $question = Question::factory()->create(['user_id' => $user->id]);
        $question->seasons()->attach($season->id);
        $other = Question::factory()->create();
        $other->seasons()->attach(Season::factory()->create()->id);

        $response = $this->actingAs($user)->get('/season/'.$season->id.'/questions')->assertOk();
        preg_match('/<script id="questions-data" type="application\/json">(.*?)<\/script>/s', $response->getContent(), $matches);
        $questions = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
        $refresh = $this->getJson('/api/season/'.$season->id.'/questions')->assertOk()->json();

        $this->assertSame($refresh, $questions);
        $this->assertSame([$question->id], array_column($questions, 'id'));
    }
}
