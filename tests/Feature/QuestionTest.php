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

    public function test_get_questions_returns_user_name()
    {
        $user = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);
        $season = Season::factory()->create([
            'start_date' => now()->subDay(),
            'final_deadline' => now()->addMonth(),
        ]);
        $question = Question::factory()->create(['user_id' => $user->id]);
        $question->seasons()->attach($season->id);

        $response = $this->actingAs($user)->getJson("/api/season/{$season->id}/questions");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'name' => 'Jane Doe',
        ]);
        // Ensure first_name and last_name are present on the user object
        $data = $response->json();
        $this->assertNotEmpty($data);
        $this->assertEquals('Jane', $data[0]['user']['first_name']);
        $this->assertEquals('Doe', $data[0]['user']['last_name']);
        $this->assertEquals('Jane Doe', $data[0]['user']['name']);
    }

    public function test_store_question_returns_user_name()
    {
        $user = User::factory()->create([
            'first_name' => 'Alice',
            'last_name' => 'Smith',
        ]);
        $season = Season::factory()->create([
            'start_date' => now()->subDay(),
            'final_deadline' => now()->addMonth(),
        ]);

        $response = $this->actingAs($user)->postJson("/api/season/{$season->id}/questions", [
            'content' => 'What are the group discount deadlines?',
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'name' => 'Alice Smith',
        ]);
        $data = $response->json();
        $this->assertEquals('Alice', $data['user']['first_name']);
        $this->assertEquals('Smith', $data['user']['last_name']);
        $this->assertEquals('Alice Smith', $data['user']['name']);
    }

    public function test_answer_question_returns_answered_by_user_name()
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'first_name' => 'Bob',
            'last_name' => 'Admin',
        ]);
        $question = Question::factory()->create();

        $response = $this->actingAs($admin)->postJson("/api/questions/{$question->id}/answer", [
            'answer' => 'Here is the official answer.',
        ]);

        $response->assertStatus(200);
        $data = $response->json();
        // The 'answeredBy' Eloquent relation serializes as 'answered_by' in JSON (snake_case).
        // It contains the user object (with 'name' accessor) rather than the raw FK integer.
        $this->assertIsArray($data['answered_by']);
        $this->assertEquals('Bob Admin', $data['answered_by']['name']);
        $this->assertEquals('Bob', $data['answered_by']['first_name']);
        $this->assertEquals('Admin', $data['answered_by']['last_name']);
    }

    public function test_user_name_accessor_combines_first_and_last()
    {
        $user = User::factory()->create([
            'first_name' => 'Charlie',
            'last_name' => 'Brown',
        ]);

        $this->assertEquals('Charlie Brown', $user->name);
    }

    public function test_user_name_accessor_handles_missing_last_name()
    {
        $user = User::factory()->create([
            'first_name' => 'Solo',
            'last_name' => null,
        ]);

        $this->assertEquals('Solo', $user->name);
    }

    public function test_user_name_accessor_handles_missing_first_name()
    {
        $user = User::factory()->create([
            'first_name' => null,
            'last_name' => 'LastOnly',
        ]);

        $this->assertEquals('LastOnly', $user->name);
    }

    public function test_get_questions_includes_upvote_status()
    {
        $user = User::factory()->create();
        $season = Season::factory()->create([
            'start_date' => now()->subDay(),
            'final_deadline' => now()->addMonth(),
        ]);
        $question = Question::factory()->create(['user_id' => $user->id]);
        $question->seasons()->attach($season->id);

        $response = $this->actingAs($user)->getJson("/api/season/{$season->id}/questions");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('user_has_upvoted', $data[0]);
        $this->assertArrayHasKey('upvotes_count', $data[0]);
    }

    public function test_user_cannot_delete_others_question()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $question = Question::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->deleteJson("/api/questions/{$question->id}");
        $response->assertStatus(403);
    }

    public function test_admin_can_delete_any_question()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $question = Question::factory()->create();

        $response = $this->actingAs($admin)->deleteJson("/api/questions/{$question->id}");
        $response->assertStatus(200);
        $this->assertSoftDeleted('questions', ['id' => $question->id]);
    }

    public function test_user_can_delete_own_question()
    {
        $user = User::factory()->create();
        $question = Question::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->deleteJson("/api/questions/{$question->id}");
        $response->assertStatus(200);
        $this->assertSoftDeleted('questions', ['id' => $question->id]);
    }

    public function test_non_admin_cannot_answer_question()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $question = Question::factory()->create();

        $response = $this->actingAs($user)->postJson("/api/questions/{$question->id}/answer", [
            'answer' => 'Sneaky answer.',
        ]);

        $response->assertStatus(403);
    }
}
