<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves API identity links off users.oauth_provider/oauth_subject (those names belong to
 * auth-laravel's provider sign-in bindings). The old columns are left in place and unused;
 * a later migration drops them so this deploy stays reversible. Safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('api_identity_links')) {
            // Opaque identifiers must compare byte-for-byte; MySQL's default collation is case insensitive.
            $binary = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);

            Schema::create('api_identity_links', function (Blueprint $table) use ($binary): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $provider = $table->string('provider');
                $subject = $table->string('subject');
                if ($binary) {
                    $provider->collation('utf8mb4_bin');
                    $subject->collation('utf8mb4_bin');
                }
                $table->timestamps();
                $table->unique(['provider', 'subject']);
            });
        }

        if (! Schema::hasColumns('users', ['oauth_provider', 'oauth_subject'])) {
            return;
        }

        DB::table('users')
            ->whereNotNull('oauth_provider')
            ->whereNotNull('oauth_subject')
            ->orderBy('id')
            ->chunk(500, function ($users): void {
                $now = now();
                DB::table('api_identity_links')->insertOrIgnore($users->map(fn (object $user): array => [
                    'user_id' => $user->id,
                    'provider' => $user->oauth_provider,
                    'subject' => $user->oauth_subject,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });
    }

    public function down(): void
    {
        // The old columns were never modified, so dropping the table fully reverts this change.
        Schema::dropIfExists('api_identity_links');
    }
};
