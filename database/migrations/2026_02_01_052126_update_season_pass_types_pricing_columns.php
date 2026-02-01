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
        Schema::table('season_pass_types', function (Blueprint $table) {
            $table->renameColumn('regular_price', 'regular_regular_price');
            $table->renameColumn('group_price', 'group_regular_price');
            $table->decimal('regular_early_price', 8, 2)->after('pass_type_name')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('season_pass_types', function (Blueprint $table) {
            $table->renameColumn('regular_regular_price', 'regular_price');
            $table->renameColumn('group_regular_price', 'group_price');
            $table->dropColumn('regular_early_price');
        });
    }
};