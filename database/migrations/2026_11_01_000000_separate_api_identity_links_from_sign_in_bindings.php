<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Rename the index before its columns so SQLite compiles it against the current names.
            $table->renameIndex('users_oauth_provider_oauth_subject_unique', 'users_api_identity_issuer_api_identity_subject_unique');
            $table->renameColumn('oauth_provider', 'api_identity_issuer');
            $table->renameColumn('oauth_subject', 'api_identity_subject');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->renameIndex('users_api_identity_issuer_api_identity_subject_unique', 'users_oauth_provider_oauth_subject_unique');
            $table->renameColumn('api_identity_issuer', 'oauth_provider');
            $table->renameColumn('api_identity_subject', 'oauth_subject');
        });
    }
};
