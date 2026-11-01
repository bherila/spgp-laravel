<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApiIdentityLinksMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_11_01_000000_create_api_identity_links_table.php');
    }

    protected function setUp(): void
    {
        parent::setUp();
        // The drop migration has already run; restore the legacy columns these tests seed.
        (require database_path('migrations/2026_11_01_000001_drop_legacy_oauth_columns_from_users.php'))->down();
    }

    private function user(string $email, ?string $provider, ?string $subject): int
    {
        return DB::table('users')->insertGetId([
            'first_name' => 'T', 'last_name' => 'U', 'email' => $email, 'password' => 'x',
            'oauth_provider' => $provider, 'oauth_subject' => $subject,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_it_copies_existing_links_and_is_idempotent(): void
    {
        $migration = $this->migration();
        $migration->down();
        $this->assertFalse(Schema::hasTable('api_identity_links'));

        $linked = $this->user('a@example.test', 'https://idp.example.test', 'Subject-A');
        $this->user('b@example.test', null, null);
        $this->user('c@example.test', 'https://idp.example.test', null);

        $migration->up();
        $migration->up();

        $this->assertSame(1, DB::table('api_identity_links')->count());
        $this->assertDatabaseHas('api_identity_links', ['user_id' => $linked, 'provider' => 'https://idp.example.test', 'subject' => 'Subject-A']);
    }

    public function test_it_leaves_the_old_columns_untouched_and_down_reverts_cleanly(): void
    {
        $migration = $this->migration();
        $migration->down();
        $this->user('a@example.test', 'https://idp.example.test', 'S');
        $migration->up();
        $migration->down();

        $this->assertFalse(Schema::hasTable('api_identity_links'));
        $this->assertDatabaseHas('users', ['oauth_provider' => 'https://idp.example.test', 'oauth_subject' => 'S']);
    }

    public function test_the_table_enforces_one_link_per_user_and_per_identity(): void
    {
        $a = $this->user('a@example.test', null, null);
        $b = $this->user('b@example.test', null, null);
        $row = fn (int $id, string $subject): array => ['user_id' => $id, 'provider' => 'p', 'subject' => $subject, 'created_at' => now(), 'updated_at' => now()];

        DB::table('api_identity_links')->insert($row($a, 's'));

        $this->assertSame(0, DB::table('api_identity_links')->insertOrIgnore($row($b, 's')));
        $this->assertSame(0, DB::table('api_identity_links')->insertOrIgnore($row($a, 's2')));
    }
}
