<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drops users.oauth_provider / users.oauth_subject, which have been unused since API identity links
 * moved to api_identity_links (2026_11_01_000000). The names are freed for auth-laravel's provider
 * sign-in bindings (OAuthIdentityColumns), which will add them back with its own definition.
 *
 * up() refuses to run (changing nothing) if any user's legacy binding is not mirrored exactly in
 * api_identity_links, so no link can be lost. down() restores the columns as originally created by
 * 2026_10_22_000000 (nullable string, default collation, unique index
 * users_oauth_provider_oauth_subject_unique) and copies the values back from api_identity_links.
 *
 * A deploy rollback does not run down(). Rolling the app back to before #84 therefore needs a manual
 * `php artisan migrate:rollback --step=1` (twice to also drop api_identity_links) first.
 */
return new class extends Migration
{
    private const INDEX = 'users_oauth_provider_oauth_subject_unique';

    public function up(): void
    {
        $hasProvider = Schema::hasColumn('users', 'oauth_provider');
        $hasSubject = Schema::hasColumn('users', 'oauth_subject');
        if (! $hasProvider && ! $hasSubject) {
            return;
        }

        $this->assertEveryBindingIsMirrored($hasProvider, $hasSubject);

        Schema::table('users', function (Blueprint $table) use ($hasProvider, $hasSubject): void {
            if ($hasProvider && $hasSubject && Schema::hasIndex('users', self::INDEX)) {
                $table->dropUnique(self::INDEX);
            }
        });

        $columns = array_values(array_filter([
            $hasProvider ? 'oauth_provider' : null,
            $hasSubject ? 'oauth_subject' : null,
        ]));
        Schema::table('users', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }

    public function down(): void
    {
        $hasProvider = Schema::hasColumn('users', 'oauth_provider');
        $hasSubject = Schema::hasColumn('users', 'oauth_subject');

        Schema::table('users', function (Blueprint $table) use ($hasProvider, $hasSubject): void {
            if (! $hasProvider) {
                $table->string('oauth_provider')->nullable();
            }
            if (! $hasSubject) {
                $table->string('oauth_subject')->nullable();
            }
        });

        if (Schema::hasTable('api_identity_links')) {
            $this->copyLinksBack();
        }

        if (! Schema::hasIndex('users', self::INDEX)) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unique(['oauth_provider', 'oauth_subject'], self::INDEX);
            });
        }
    }

    private function copyLinksBack(): void
    {
        // Only fill users that have no binding of their own, so existing values are never overwritten.
        // The restored columns use the default (often case-insensitive) collation, so a link that would
        // collide with another user's value there cannot be represented; it is skipped rather than
        // failing the whole rollback half-applied, and remains only in api_identity_links.
        DB::table('api_identity_links')->orderBy('id')->chunkById(500, function ($links): void {
            foreach ($links as $link) {
                $taken = DB::table('users')
                    ->where('oauth_provider', $link->provider)
                    ->where('oauth_subject', $link->subject)
                    ->exists();
                if ($taken) {
                    continue;
                }

                DB::table('users')
                    ->where('id', $link->user_id)
                    ->whereNull('oauth_provider')
                    ->whereNull('oauth_subject')
                    ->update(['oauth_provider' => $link->provider, 'oauth_subject' => $link->subject]);
            }
        });
    }

    /**
     * Every user with a legacy value must have an api_identity_links row with the same user, provider
     * and subject. Compared in PHP so the check is byte-exact whatever the database collation.
     */
    private function assertEveryBindingIsMirrored(bool $hasProvider, bool $hasSubject): void
    {
        $mismatches = 0;

        $query = DB::table('users')->select('id')->where(function ($query) use ($hasProvider, $hasSubject): void {
            if ($hasProvider) {
                $query->orWhereNotNull('oauth_provider');
            }
            if ($hasSubject) {
                $query->orWhereNotNull('oauth_subject');
            }
        });
        if ($hasProvider) {
            $query->addSelect('oauth_provider');
        }
        if ($hasSubject) {
            $query->addSelect('oauth_subject');
        }

        $query->orderBy('id')->chunkById(500, function ($users) use (&$mismatches): void {
            $links = Schema::hasTable('api_identity_links')
                ? DB::table('api_identity_links')->whereIn('user_id', $users->pluck('id'))->get()->keyBy('user_id')
                : collect();

            foreach ($users as $user) {
                $link = $links->get($user->id);
                if ($link === null
                    || $link->provider !== ($user->oauth_provider ?? null)
                    || $link->subject !== ($user->oauth_subject ?? null)) {
                    $mismatches++;
                }
            }
        });

        if ($mismatches > 0) {
            throw new RuntimeException(
                "Refusing to drop users.oauth_provider/oauth_subject: {$mismatches} user(s) have a legacy binding without a matching api_identity_links row. No changes were made."
            );
        }
    }
};
