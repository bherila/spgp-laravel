<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SafeTestCase;

class QuestionTest extends SafeTestCase
{
    use RefreshDatabase;

    public function test_user_can_ask_question()
    {
        $user = User::factory()->create();
        $season = Season::factory()->create([
            'start_date' => now()->subDay(),
            'final_deadline' => now()->addMonth(),
        ]);

        $response = $this->actingAs($user)->postJson("/api/season/{$season->id}/questions", [
            'content' => '# My Question',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('questions', [
            'content' => '# My Question',
            'user_id' => $user->id,
        ]);
    }

    public function test_user_can_edit_unanswered_question()
    {
        $user = User::factory()->create();
        $question = Question::factory()->create(['user_id' => $user->id, 'content' => 'Old content']);

        $response = $this->actingAs($user)->patchJson("/api/questions/{$question->id}", [
            'content' => 'New content',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('questions', [
            'id' => $question->id,
            'content' => 'New content',
        ]);
    }

    public function test_user_cannot_edit_answered_question()
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $question = Question::factory()->create([
            'user_id' => $user->id,
            'content' => 'Question',
            'answer' => 'Answer',
            'answered_by' => $admin->id,
            'answered_at' => now(),
        ]);

        $response = $this->actingAs($user)->patchJson("/api/questions/{$question->id}", [
            'content' => 'Changed',
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_can_answer_question()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $question = Question::factory()->create();

        $response = $this->actingAs($admin)->postJson("/api/questions/{$question->id}/answer", [
            'answer' => 'This is the answer.',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('questions', [
            'id' => $question->id,
            'answer' => 'This is the answer.',
            'answered_by' => $admin->id,
        ]);
    }

    public function test_user_can_upvote_and_unvote()
    {
        $user = User::factory()->create();
        $question = Question::factory()->create();

        // Upvote
        $response = $this->actingAs($user)->postJson("/api/questions/{$question->id}/upvote");
        $response->assertStatus(200);
        $this->assertDatabaseHas('question_upvotes', [
            'question_id' => $question->id,
            'user_id' => $user->id,
        ]);

        // Unvote
        $response = $this->actingAs($user)->postJson("/api/questions/{$question->id}/unvote");
        $response->assertStatus(200);
        $this->assertDatabaseMissing('question_upvotes', [
            'question_id' => $question->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_user_cannot_access_inactive_season_questions()
    {
        $user = User::factory()->create();
        // Inactive season (past)
        $season = Season::factory()->create([
            'start_date' => now()->subMonths(6),
            'early_spring_deadline' => now()->subMonths(5),
            'final_deadline' => now()->subMonths(4),
        ]);

        $response = $this->actingAs($user)->get("/season/{$season->id}/questions");
        $response->assertStatus(403);
    }

    public function test_admin_can_access_inactive_season_questions()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $season = Season::factory()->create([
            'start_date' => now()->subMonths(6),
            'final_deadline' => now()->subMonths(4),
        ]);

        $response = $this->actingAs($admin)->get("/season/{$season->id}/questions");
        $response->assertStatus(200);
    }
}
