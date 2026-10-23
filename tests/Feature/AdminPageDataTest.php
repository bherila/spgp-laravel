<?php

namespace Tests\Feature;

use App\Models\EmailLog;
use App\Models\InviteCode;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminPageDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_page_embeds_the_same_data_as_its_refresh_endpoint_without_secrets(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['oauth_provider' => 'https://identity.example.test', 'oauth_subject' => 'opaque-subject']);
        $this->actingAs($admin);

        $data = $this->pageData('/admin/users');

        $this->assertSame($this->getJson('/api/admin/users/list')->assertOk()->json(), $data['users']);
        foreach ($data['users'] as $user) {
            $this->assertArrayNotHasKey('password', $user);
            $this->assertArrayNotHasKey('oauth_subject', $user);
            $this->assertArrayNotHasKey('oauth_provider', $user);
        }
    }

    public function test_invite_and_season_pages_embed_default_lists_and_leave_archived_records_out(): void
    {
        $admin = User::factory()->admin()->create();
        $season = Season::factory()->create();
        $archivedSeason = Season::factory()->create();
        $archivedSeason->delete();
        InviteCode::create(['season_id' => $season->id, 'invite_code' => 'ACTIVE', 'max_number_of_uses' => 1]);
        $archivedInvite = InviteCode::create(['season_id' => $season->id, 'invite_code' => 'ARCHIVED', 'max_number_of_uses' => 1]);
        $archivedInvite->delete();
        $this->actingAs($admin);

        $invites = $this->pageData('/admin/invites');
        $seasons = $this->pageData('/admin/seasons');

        $this->assertSame($this->getJson('/api/admin/invites/list')->assertOk()->json(), $invites['inviteCodes']);
        $this->assertSame($this->getJson('/api/admin/seasons/list')->assertOk()->json(), $invites['seasons']);
        $this->assertSame($invites['seasons'], $seasons['seasons']);
        $this->assertCount(1, $invites['inviteCodes']);
        $this->assertCount(1, $seasons['seasons']);
        $this->getJson('/api/admin/invites/list?include_archived=true')->assertJsonCount(2);
        $this->getJson('/api/admin/seasons/list?include_archived=true')->assertJsonCount(2);
    }

    public function test_email_log_page_embeds_only_the_initial_page_and_safely_encodes_email_content(): void
    {
        $admin = User::factory()->admin()->create();
        $body = '</script><script>alert("email")</script>';
        foreach (range(1, 51) as $index) {
            EmailLog::create([
                'event' => 'test', 'email_to' => 'recipient@example.test', 'email_from' => 'sender@example.test',
                'subject' => "Message {$index}", 'body' => $body,
            ]);
        }
        $this->actingAs($admin);

        $data = $this->pageData('/admin/email-log');

        $this->assertSame($this->getJson('/api/admin/email-log/list')->assertOk()->json(), $data);
        $this->assertCount(50, $data['emailLogs']);
        $this->assertSame(51, $data['total']);
        $this->assertSame($body, $data['emailLogs'][0]['body']);
        $this->get('/admin/email-log')->assertDontSee($body, false);
    }

    #[DataProvider('adminPages')]
    public function test_bootstrap_data_is_not_exposed_to_non_admins(string $path): void
    {
        $this->get($path)->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get($path)->assertForbidden();
    }

    public static function adminPages(): array
    {
        return array_map(fn (string $path) => [$path], ['/admin/users', '/admin/invites', '/admin/seasons', '/admin/email-log']);
    }

    private function pageData(string $path): array
    {
        $response = $this->get($path)->assertOk();
        $matched = preg_match('/<script\b[^>]*\bid="page-data"[^>]*>(.*?)<\/script>/s', $response->getContent(), $matches);
        $this->assertSame(1, $matched);

        return json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
    }
}
