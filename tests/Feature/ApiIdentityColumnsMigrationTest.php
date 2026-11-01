<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApiIdentityColumnsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_links_survive_the_rename_and_rollback_without_changing_accounts(): void
    {
        $migration = require database_path('migrations/2026_11_01_000000_separate_api_identity_links_from_sign_in_bindings.php');
        $migration->down();
        $linked = User::factory()->admin()->create();
        $linked->forceFill(['oauth_provider' => 'https://identity.example.test', 'oauth_subject' => 'Opaque-Subject/001'])->save();
        $unlinked = User::factory()->create();
        $password = $linked->password;

        $migration->up();

        $this->assertFalse(Schema::hasColumn('users', 'oauth_provider'));
        $this->assertFalse(Schema::hasColumn('users', 'oauth_subject'));
        $this->assertDatabaseHas('users', [
            'id' => $linked->id,
            'api_identity_issuer' => 'https://identity.example.test',
            'api_identity_subject' => 'Opaque-Subject/001',
            'password' => $password,
            'is_admin' => true,
        ]);
        $this->assertDatabaseHas('users', ['id' => $unlinked->id, 'api_identity_issuer' => null, 'api_identity_subject' => null]);
        $this->assertDatabaseCount('users', 2);

        $migration->down();

        $this->assertFalse(Schema::hasColumn('users', 'api_identity_issuer'));
        $this->assertDatabaseHas('users', [
            'id' => $linked->id,
            'oauth_provider' => 'https://identity.example.test',
            'oauth_subject' => 'Opaque-Subject/001',
            'password' => $password,
            'is_admin' => true,
        ]);
        $migration->up();
    }

    public function test_renamed_identity_pairs_remain_unique(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $first->forceFill(['api_identity_issuer' => 'https://identity.example.test', 'api_identity_subject' => 'opaque-subject'])->save();

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('users')->where('id', $second->id)->update([
            'api_identity_issuer' => 'https://identity.example.test',
            'api_identity_subject' => 'opaque-subject',
        ]);
    }
}
