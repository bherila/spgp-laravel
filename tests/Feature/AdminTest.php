<?php

namespace Tests\Feature;

use App\Models\EmailLog;
use App\Models\InviteCode;
use App\Models\PassRequest;
use App\Models\Season;
use App\Models\SeasonPassType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
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
    // Admin Authorization Tests
    // ============================================================

    public function test_non_admin_cannot_access_admin_users_list(): void
    {
        $response = $this->actingAs($this->regularUser)->get('/api/admin/users/list');
        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_admin_users_list(): void
    {
        $response = $this->get('/api/admin/users/list');
        $response->assertRedirect('/login');
    }

    // ============================================================
    // Admin User Listing Tests (Bug #1 fix validation)
    // ============================================================

    public function test_admin_can_fetch_users_list(): void
    {
        $response = $this->actingAs($this->admin)->get('/api/admin/users/list');

        $response->assertStatus(200)
            ->assertJsonIsArray();
    }

    public function test_admin_users_list_returns_correct_fields(): void
    {
        $response = $this->actingAs($this->admin)->get('/api/admin/users/list');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data);

        $adminData = collect($data)->firstWhere('id', $this->admin->id);
        $this->assertNotNull($adminData);
        $this->assertArrayHasKey('name', $adminData);
        $this->assertArrayHasKey('email', $adminData);
        $this->assertArrayHasKey('is_admin', $adminData);
        $this->assertArrayHasKey('pass_request_count', $adminData);
        $this->assertArrayHasKey('invite_codes', $adminData);
    }

    public function test_admin_users_list_includes_invite_codes(): void
    {
        $inviteCode = InviteCode::create([
            'invite_code' => 'TEST_CODE',
            'max_number_of_uses' => 5,
            'season_id' => $this->season->id,
        ]);
        $this->regularUser->inviteCodes()->attach($inviteCode->id);

        $response = $this->actingAs($this->admin)->get('/api/admin/users/list');

        $response->assertStatus(200);
        $data = $response->json();

        $userWithCode = collect($data)->firstWhere('id', $this->regularUser->id);
        $this->assertNotNull($userWithCode);
        $this->assertArrayHasKey('invite_codes', $userWithCode);
        $this->assertNotEmpty($userWithCode['invite_codes']);
        $this->assertEquals('TEST_CODE', $userWithCode['invite_codes'][0]['invite_code']);
    }

    public function test_admin_users_list_shows_pass_request_count(): void
    {
        // Create a pass request for the regular user
        $passType = SeasonPassType::create([
            'season_id' => $this->season->id,
            'pass_type_name' => 'Adult',
            'sort_order' => 0,
        ]);

        PassRequest::create([
            'user_id' => $this->regularUser->id,
            'season_id' => $this->season->id,
            'season_pass_type_id' => $passType->id,
            'passholder_email' => $this->regularUser->email,
            'pass_type' => 'Adult',
            'passholder_first_name' => 'John',
            'passholder_last_name' => 'Doe',
            'passholder_birth_date' => '1990-01-01',
        ]);

        $response = $this->actingAs($this->admin)->get('/api/admin/users/list');
        $response->assertStatus(200);

        $userData = collect($response->json())->firstWhere('id', $this->regularUser->id);
        $this->assertNotNull($userData);
        $this->assertEquals(1, $userData['pass_request_count']);
    }

    // ============================================================
    // Admin User CRUD Tests
    // ============================================================

    public function test_admin_can_create_user(): void
    {
        $response = $this->actingAs($this->admin)->post('/api/admin/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'is_admin' => false,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    public function test_admin_created_user_is_email_verified(): void
    {
        $response = $this->actingAs($this->admin)->post('/api/admin/users', [
            'name' => 'Verified User',
            'email' => 'verified@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(201);
        $user = User::where('email', 'verified@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_admin_can_update_user(): void
    {
        $response = $this->actingAs($this->admin)->put("/api/admin/users/{$this->regularUser->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $this->regularUser->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_admin_can_delete_user(): void
    {
        $userToDelete = User::factory()->create();

        $response = $this->actingAs($this->admin)->delete("/api/admin/users/{$userToDelete->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('users', ['id' => $userToDelete->id]);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $response = $this->actingAs($this->admin)->delete("/api/admin/users/{$this->admin->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    // ============================================================
    // Admin Pass Request Delete Tests (Bug #2 fix validation)
    // ============================================================

    public function test_admin_can_delete_pass_request(): void
    {
        $passType = SeasonPassType::create([
            'season_id' => $this->season->id,
            'pass_type_name' => 'Adult',
            'sort_order' => 0,
        ]);

        $passRequest = PassRequest::create([
            'user_id' => $this->regularUser->id,
            'season_id' => $this->season->id,
            'season_pass_type_id' => $passType->id,
            'passholder_email' => 'test@example.com',
            'pass_type' => 'Adult',
            'passholder_first_name' => 'John',
            'passholder_last_name' => 'Doe',
            'passholder_birth_date' => '1990-01-01',
        ]);

        $passRequestId = $passRequest->id;

        $response = $this->actingAs($this->admin)
            ->delete("/api/admin/pass-requests/{$passRequestId}/admin");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Pass request deleted successfully']);

        $this->assertDatabaseMissing('pass_requests', ['id' => $passRequestId]);
    }

    public function test_admin_delete_pass_request_returns_404_for_nonexistent(): void
    {
        $response = $this->actingAs($this->admin)
            ->delete('/api/admin/pass-requests/NONEXISTENT_ULID_ID/admin');

        $response->assertStatus(404);
    }

    public function test_non_admin_cannot_delete_pass_request(): void
    {
        $passType = SeasonPassType::create([
            'season_id' => $this->season->id,
            'pass_type_name' => 'Adult',
            'sort_order' => 0,
        ]);

        $passRequest = PassRequest::create([
            'user_id' => $this->regularUser->id,
            'season_id' => $this->season->id,
            'season_pass_type_id' => $passType->id,
            'passholder_email' => 'test@example.com',
            'pass_type' => 'Adult',
            'passholder_first_name' => 'John',
            'passholder_last_name' => 'Doe',
            'passholder_birth_date' => '1990-01-01',
        ]);

        $response = $this->actingAs($this->regularUser)
            ->delete("/api/admin/pass-requests/{$passRequest->id}/admin");

        $response->assertForbidden();
        $this->assertDatabaseHas('pass_requests', ['id' => $passRequest->id]);
    }

    // ============================================================
    // Admin Pass Request Listing Tests
    // ============================================================

    public function test_admin_can_list_pass_requests_for_season(): void
    {
        $response = $this->actingAs($this->admin)
            ->get("/api/admin/seasons/{$this->season->id}/pass-requests/list");

        $response->assertStatus(200)
            ->assertJsonStructure(['season', 'pass_requests']);
    }

    public function test_admin_pass_request_list_filters_by_season(): void
    {
        $otherSeason = Season::factory()->create();
        $passType = SeasonPassType::create([
            'season_id' => $this->season->id,
            'pass_type_name' => 'Adult',
            'sort_order' => 0,
        ]);

        PassRequest::create([
            'user_id' => $this->regularUser->id,
            'season_id' => $this->season->id,
            'season_pass_type_id' => $passType->id,
            'passholder_email' => 'test@example.com',
            'pass_type' => 'Adult',
            'passholder_first_name' => 'John',
            'passholder_last_name' => 'Doe',
            'passholder_birth_date' => '1990-01-01',
        ]);

        $response = $this->actingAs($this->admin)
            ->get("/api/admin/seasons/{$otherSeason->id}/pass-requests/list");

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEmpty($data['pass_requests']);
    }

    // ============================================================
    // Admin Invite Codes Tests
    // ============================================================

    public function test_admin_can_list_invite_codes(): void
    {
        InviteCode::create([
            'invite_code' => 'LIST_TEST',
            'max_number_of_uses' => 5,
            'season_id' => $this->season->id,
        ]);

        $response = $this->actingAs($this->admin)->get('/api/admin/invites/list');

        $response->assertStatus(200)
            ->assertJsonIsArray();
        $data = $response->json();
        $this->assertNotEmpty($data);
    }

    public function test_admin_can_create_invite_code(): void
    {
        $response = $this->actingAs($this->admin)->post('/api/admin/invites', [
            'season_id' => $this->season->id,
            'invite_code' => 'NEW_CODE_123',
            'max_number_of_uses' => 10,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('invite_codes', ['invite_code' => 'NEW_CODE_123']);
    }

    public function test_admin_can_update_invite_code(): void
    {
        $inviteCode = InviteCode::create([
            'invite_code' => 'UPDATE_ME',
            'max_number_of_uses' => 5,
            'season_id' => $this->season->id,
        ]);

        $response = $this->actingAs($this->admin)->put("/api/admin/invites/{$inviteCode->id}", [
            'max_number_of_uses' => 20,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('invite_codes', [
            'id' => $inviteCode->id,
            'max_number_of_uses' => 20,
        ]);
    }

    public function test_admin_can_archive_invite_code(): void
    {
        $inviteCode = InviteCode::create([
            'invite_code' => 'ARCHIVE_ME',
            'max_number_of_uses' => 5,
            'season_id' => $this->season->id,
        ]);

        $response = $this->actingAs($this->admin)->delete("/api/admin/invites/{$inviteCode->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('invite_codes', ['id' => $inviteCode->id]);
    }

    public function test_admin_can_restore_archived_invite_code(): void
    {
        $inviteCode = InviteCode::create([
            'invite_code' => 'RESTORE_ME',
            'max_number_of_uses' => 5,
            'season_id' => $this->season->id,
        ]);
        $inviteCode->delete();

        $response = $this->actingAs($this->admin)->post("/api/admin/invites/{$inviteCode->id}/restore");

        $response->assertStatus(200);
        $this->assertDatabaseHas('invite_codes', [
            'id' => $inviteCode->id,
            'deleted_at' => null,
        ]);
    }

    public function test_admin_invite_list_excludes_archived_by_default(): void
    {
        InviteCode::create([
            'invite_code' => 'ACTIVE_CODE',
            'max_number_of_uses' => 5,
            'season_id' => $this->season->id,
        ]);
        $archived = InviteCode::create([
            'invite_code' => 'ARCHIVED_CODE',
            'max_number_of_uses' => 5,
            'season_id' => $this->season->id,
        ]);
        $archived->delete();

        $response = $this->actingAs($this->admin)->get('/api/admin/invites/list');

        $response->assertStatus(200);
        $data = $response->json();
        $codes = array_column($data, 'invite_code');
        $this->assertContains('ACTIVE_CODE', $codes);
        $this->assertNotContains('ARCHIVED_CODE', $codes);
    }

    public function test_admin_invite_list_includes_archived_when_requested(): void
    {
        $archived = InviteCode::create([
            'invite_code' => 'ARCHIVED_CODE_2',
            'max_number_of_uses' => 5,
            'season_id' => $this->season->id,
        ]);
        $archived->delete();

        $response = $this->actingAs($this->admin)->get('/api/admin/invites/list?include_archived=1');

        $response->assertStatus(200);
        $data = $response->json();
        $codes = array_column($data, 'invite_code');
        $this->assertContains('ARCHIVED_CODE_2', $codes);
    }

    // ============================================================
    // Admin Season Tests
    // ============================================================

    public function test_admin_can_archive_season(): void
    {
        $response = $this->actingAs($this->admin)->delete("/api/admin/seasons/{$this->season->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('seasons', ['id' => $this->season->id]);
    }

    public function test_admin_can_restore_archived_season(): void
    {
        $this->season->delete();

        $response = $this->actingAs($this->admin)->post("/api/admin/seasons/{$this->season->id}/restore");

        $response->assertStatus(200);
        $this->assertDatabaseHas('seasons', [
            'id' => $this->season->id,
            'deleted_at' => null,
        ]);
    }

    // ============================================================
    // Admin Pass Type Tests
    // ============================================================

    public function test_admin_can_create_pass_type(): void
    {
        $response = $this->actingAs($this->admin)->post("/api/admin/seasons/{$this->season->id}/pass-types", [
            'pass_type_name' => 'Senior',
            'regular_early_price' => 299.99,
            'regular_regular_price' => 349.99,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('season_pass_types', [
            'season_id' => $this->season->id,
            'pass_type_name' => 'Senior',
        ]);
    }

    public function test_admin_can_update_pass_type(): void
    {
        $passType = SeasonPassType::create([
            'season_id' => $this->season->id,
            'pass_type_name' => 'Adult',
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($this->admin)->put("/api/admin/pass-types/{$passType->id}", [
            'pass_type_name' => 'Adult Updated',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('season_pass_types', [
            'id' => $passType->id,
            'pass_type_name' => 'Adult Updated',
        ]);
    }

    public function test_admin_can_delete_pass_type_without_requests(): void
    {
        $passType = SeasonPassType::create([
            'season_id' => $this->season->id,
            'pass_type_name' => 'To Delete',
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($this->admin)->delete("/api/admin/pass-types/{$passType->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('season_pass_types', ['id' => $passType->id]);
    }

    public function test_admin_cannot_delete_pass_type_with_requests(): void
    {
        $passType = SeasonPassType::create([
            'season_id' => $this->season->id,
            'pass_type_name' => 'Has Requests',
            'sort_order' => 0,
        ]);

        PassRequest::create([
            'user_id' => $this->regularUser->id,
            'season_id' => $this->season->id,
            'season_pass_type_id' => $passType->id,
            'passholder_email' => 'test@example.com',
            'pass_type' => 'Has Requests',
            'passholder_first_name' => 'John',
            'passholder_last_name' => 'Doe',
            'passholder_birth_date' => '1990-01-01',
        ]);

        $response = $this->actingAs($this->admin)->delete("/api/admin/pass-types/{$passType->id}");

        $response->assertStatus(400);
        $this->assertDatabaseHas('season_pass_types', ['id' => $passType->id]);
    }

    // ============================================================
    // Admin Email Log Tests
    // ============================================================

    public function test_admin_can_list_email_logs(): void
    {
        EmailLog::create([
            'event' => 'test',
            'email_to' => 'test@example.com',
            'email_from' => 'noreply@example.com',
            'subject' => 'Test Email',
            'body' => 'Test body',
        ]);

        $response = $this->actingAs($this->admin)->get('/api/admin/email-log/list');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'emailLogs',
                'total',
                'page',
                'limit',
            ]);
    }

    public function test_admin_email_log_pagination(): void
    {
        for ($i = 0; $i < 5; $i++) {
            EmailLog::create([
                'event' => 'test',
                'email_to' => "user{$i}@example.com",
                'email_from' => 'noreply@example.com',
                'subject' => 'Test Email',
                'body' => 'Test body',
            ]);
        }

        $response = $this->actingAs($this->admin)->get('/api/admin/email-log/list?limit=3&page=1');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertCount(3, $data['emailLogs']);
        $this->assertEquals(5, $data['total']);
    }

    public function test_admin_email_log_filter_by_recipient(): void
    {
        EmailLog::create([
            'event' => 'test',
            'email_to' => 'findme@example.com',
            'email_from' => 'noreply@example.com',
            'subject' => 'Test',
            'body' => 'Body',
        ]);
        EmailLog::create([
            'event' => 'test',
            'email_to' => 'other@example.com',
            'email_from' => 'noreply@example.com',
            'subject' => 'Test',
            'body' => 'Body',
        ]);

        $response = $this->actingAs($this->admin)->get('/api/admin/email-log/list?to=findme');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals(1, $data['total']);
        $this->assertEquals('findme@example.com', $data['emailLogs'][0]['email_to']);
    }

    public function test_non_admin_cannot_access_email_logs(): void
    {
        $response = $this->actingAs($this->regularUser)->get('/api/admin/email-log/list');
        $response->assertForbidden();
    }

    // ============================================================
    // Admin Assign/Clear Pass Codes Tests
    // ============================================================

    public function test_admin_can_assign_promo_codes(): void
    {
        $passType = SeasonPassType::create([
            'season_id' => $this->season->id,
            'pass_type_name' => 'Adult',
            'sort_order' => 0,
        ]);

        $passRequest = PassRequest::create([
            'user_id' => $this->regularUser->id,
            'season_id' => $this->season->id,
            'season_pass_type_id' => $passType->id,
            'passholder_email' => 'test@example.com',
            'pass_type' => 'Adult',
            'passholder_first_name' => 'John',
            'passholder_last_name' => 'Doe',
            'passholder_birth_date' => '1990-01-01',
        ]);

        $response = $this->actingAs($this->admin)
            ->post("/api/admin/seasons/{$this->season->id}/pass-requests/assign-codes", [
                'pass_request_ids' => [$passRequest->id],
                'codes' => 'PROMO123',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('pass_requests', [
            'id' => $passRequest->id,
            'promo_code' => 'PROMO123',
        ]);
    }

    public function test_admin_can_clear_promo_codes(): void
    {
        $passType = SeasonPassType::create([
            'season_id' => $this->season->id,
            'pass_type_name' => 'Adult',
            'sort_order' => 0,
        ]);

        $passRequest = PassRequest::create([
            'user_id' => $this->regularUser->id,
            'season_id' => $this->season->id,
            'season_pass_type_id' => $passType->id,
            'passholder_email' => 'test@example.com',
            'pass_type' => 'Adult',
            'passholder_first_name' => 'John',
            'passholder_last_name' => 'Doe',
            'passholder_birth_date' => '1990-01-01',
            'promo_code' => 'EXISTING_CODE',
            'assign_code_date' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->post("/api/admin/seasons/{$this->season->id}/pass-requests/clear-codes", [
                'pass_request_ids' => [$passRequest->id],
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('pass_requests', [
            'id' => $passRequest->id,
            'promo_code' => null,
        ]);
    }

    public function test_assign_codes_fails_when_not_enough_codes(): void
    {
        $passType = SeasonPassType::create([
            'season_id' => $this->season->id,
            'pass_type_name' => 'Adult',
            'sort_order' => 0,
        ]);

        $passRequest1 = PassRequest::create([
            'user_id' => $this->regularUser->id,
            'season_id' => $this->season->id,
            'season_pass_type_id' => $passType->id,
            'passholder_email' => 'test1@example.com',
            'pass_type' => 'Adult',
            'passholder_first_name' => 'John',
            'passholder_last_name' => 'Doe',
            'passholder_birth_date' => '1990-01-01',
        ]);
        $passRequest2 = PassRequest::create([
            'user_id' => $this->admin->id,
            'season_id' => $this->season->id,
            'season_pass_type_id' => $passType->id,
            'passholder_email' => 'test2@example.com',
            'pass_type' => 'Adult',
            'passholder_first_name' => 'Jane',
            'passholder_last_name' => 'Doe',
            'passholder_birth_date' => '1990-01-01',
        ]);

        // Only 1 code provided for 2 requests
        $response = $this->actingAs($this->admin)
            ->post("/api/admin/seasons/{$this->season->id}/pass-requests/assign-codes", [
                'pass_request_ids' => [$passRequest1->id, $passRequest2->id],
                'codes' => 'ONLY_ONE_CODE',
            ]);

        $response->assertStatus(422);
    }
}
