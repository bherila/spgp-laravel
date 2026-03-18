<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('id');
            $table->string('last_name')->nullable()->after('first_name');
        });

        // Migrate existing name data: split at first space using a single SQL statement.
        // INSTR and SUBSTR are supported by both SQLite and MySQL.
        DB::statement("
            UPDATE users
            SET
                first_name = CASE
                    WHEN INSTR(name, ' ') > 0 THEN SUBSTR(name, 1, INSTR(name, ' ') - 1)
                    ELSE name
                END,
                last_name = CASE
                    WHEN INSTR(name, ' ') > 0 THEN SUBSTR(name, INSTR(name, ' ') + 1)
                    ELSE NULL
                END
            WHERE name IS NOT NULL
        ");

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
        });

        // Restore name from first_name and last_name using a single SQL statement.
        // Use driver-specific concatenation syntax.
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            DB::statement("
                UPDATE users
                SET name = TRIM(COALESCE(first_name, '') || ' ' || COALESCE(last_name, ''))
            ");
        } else {
            DB::statement("
                UPDATE users
                SET name = TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')))
            ");
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
