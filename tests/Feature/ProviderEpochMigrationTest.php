<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProviderEpochMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_upgrade_preserves_remembered_logins_and_adds_nullable_epochs_without_recording_a_cutover(): void
    {
        $this->assertTrue(Schema::hasColumn('provider_remembered_logins', 'epoch'));
        $this->assertDatabaseCount('provider_identity_cutover', 0);

        // Model a pre-0.32 remembered login and credentials already stored by an OAuth server.
        Schema::table('provider_remembered_logins', function (Blueprint $table): void {
            $table->dropColumn('epoch');
        });
        DB::table('provider_remembered_logins')->insert([
            'guard' => 'web', 'user_id' => '42', 'provider' => 'bherila', 'subject' => '42',
            'generation' => 7, 'token_hash' => str_repeat('a', 64), 'recorded_at' => '2026-11-26 12:00:00',
        ]);
        foreach (['oauth_auth_codes', 'oauth_access_tokens', 'oauth_refresh_tokens'] as $table) {
            Schema::create($table, function (Blueprint $blueprint): void {
                $blueprint->id();
            });
            DB::table($table)->insert(['id' => 1]);
        }

        $migration = require database_path('migrations/2026_11_24_000000_add_provider_epoch_columns.php');
        $migration->up();
        $migration->up();
        $migration->down();

        $this->assertDatabaseHas('provider_remembered_logins', [
            'user_id' => '42', 'generation' => 7, 'token_hash' => str_repeat('a', 64),
            'recorded_at' => '2026-11-26 12:00:00', 'epoch' => null,
        ]);
        foreach (['oauth_auth_codes', 'oauth_access_tokens', 'oauth_refresh_tokens'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'provider_epoch'));
            $this->assertDatabaseHas($table, ['id' => 1, 'provider_epoch' => null]);
        }
        $this->assertDatabaseCount('provider_identity_cutover', 0);
    }
}
