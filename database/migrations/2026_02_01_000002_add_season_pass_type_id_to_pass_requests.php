<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pass_requests', function (Blueprint $table) {
            // Add nullable foreign key to season_pass_types
            $table->foreignId('season_pass_type_id')->nullable()->after('pass_type')
                  ->constrained('season_pass_types')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pass_requests', function (Blueprint $table) {
            $table->dropForeign(['season_pass_type_id']);
            $table->dropColumn('season_pass_type_id');
        });
    }
};
