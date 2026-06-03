<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('bherila-auth.passkeys.table', 'auth_passkeys');

        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'last_used_at')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->timestamp('last_used_at')->nullable()->after('transports');
        });
    }

    public function down(): void
    {
        $tableName = config('bherila-auth.passkeys.table', 'auth_passkeys');

        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'last_used_at')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn('last_used_at');
        });
    }
};
