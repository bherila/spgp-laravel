<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class DropLegacyOauthColumnsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const INDEX = 'users_oauth_provider_oauth_subject_unique';

    private function migration(): object
    {
        return require database_path('migrations/2026_11_01_000001_drop_legacy_oauth_columns_from_users.php');
    }

    private function user(string $email, ?string $provider = null, ?string $subject = null): int
    {
        $row = ['first_name' => 'T', 'last_name' => 'U', 'email' => $email, 'password' => 'x', 'created_at' => now(), 'updated_at' => now()];
        if (Schema::hasColumn('users', 'oauth_provider')) {
            $row += ['oauth_provider' => $provider, 'oauth_subject' => $subject];
        }

        return DB::table('users')->insertGetId($row);
    }

    private function link(int $userId, string $provider, string $subject): void
    {
        DB::table('api_identity_links')->insert(['user_id' => $userId, 'provider' => $provider, 'subject' => $subject, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function columnsExist(): bool
    {
        return Schema::hasColumns('users', ['oauth_provider', 'oauth_subject']);
    }

    public function test_columns_are_already_gone_after_the_normal_migration_run(): void
    {
        $this->assertFalse(Schema::hasColumn('users', 'oauth_provider'));
        $this->assertFalse(Schema::hasColumn('users', 'oauth_subject'));
    }

    public function test_down_restores_columns_index_and_values_then_up_drops_them_again(): void
    {
        $migration = $this->migration();
        $a = $this->user('a@example.test');
        $b = $this->user('b@example.test');
        $this->user('c@example.test');
        $this->link($a, 'https://idp.example.test', 'Subject-A');
        $this->link($b, 'https://idp.example.test', 'subject-a');

        $migration->down();

        $this->assertTrue($this->columnsExist());
        $this->assertTrue(Schema::hasIndex('users', self::INDEX));
        $this->assertSame(['https://idp.example.test', 'Subject-A'], array_values((array) DB::table('users')->where('id', $a)->first(['oauth_provider', 'oauth_subject'])));
        $this->assertSame('subject-a', DB::table('users')->where('id', $b)->value('oauth_subject'));
        $this->assertSame(1, DB::table('users')->whereNull('oauth_provider')->whereNull('oauth_subject')->count());

        $migration->down(); // idempotent
        $this->assertSame('Subject-A', DB::table('users')->where('id', $a)->value('oauth_subject'));

        $migration->up();
        $this->assertFalse($this->columnsExist());
        $this->assertFalse(Schema::hasColumn('users', 'oauth_provider'));
        $this->assertSame(2, DB::table('api_identity_links')->count());

        $migration->down();
        $this->assertSame('Subject-A', DB::table('users')->where('id', $a)->value('oauth_subject'));
        $migration->up();
        $this->assertFalse(Schema::hasColumn('users', 'oauth_subject'));
    }

    public function test_up_is_a_no_op_when_columns_are_already_gone(): void
    {
        $migration = $this->migration();
        $user = $this->user('a@example.test');
        $this->link($user, 'p', 's');

        $migration->up();
        $migration->up();

        $this->assertFalse(Schema::hasColumn('users', 'oauth_provider'));
        $this->assertSame(1, DB::table('api_identity_links')->count());
        $this->assertDatabaseHas('users', ['id' => $user]);
    }

    public function test_guard_throws_without_touching_the_schema_when_a_binding_is_not_mirrored(): void
    {
        $migration = $this->migration();
        $migration->down();
        $ok = $this->user('ok@example.test', 'p', 's');
        $this->link($ok, 'p', 's');
        $this->user('missing@example.test', 'p', 'secret-missing');
        $differs = $this->user('differs@example.test', 'p', 'secret-Case');
        $this->link($differs, 'p', 'secret-case');
        $half = $this->user('half@example.test', 'p', null);
        $this->link($half, 'p', 'x');

        try {
            $migration->up();
            $this->fail('Expected the guard to throw.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('3 user(s)', $e->getMessage());
            $this->assertStringNotContainsString('secret', $e->getMessage());
        }

        $this->assertTrue($this->columnsExist());
        $this->assertTrue(Schema::hasIndex('users', self::INDEX));
        $this->assertSame('secret-missing', DB::table('users')->where('email', 'missing@example.test')->value('oauth_subject'));
    }
}
