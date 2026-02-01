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
            $table->decimal('regular_early_price', 8, 2)->nullable()->change();
            $table->decimal('regular_regular_price', 8, 2)->nullable()->change();
            $table->decimal('renewal_early_price', 8, 2)->nullable()->change();
            $table->decimal('renewal_regular_price', 8, 2)->nullable()->change();
            $table->decimal('group_early_price', 8, 2)->nullable()->change();
            $table->decimal('group_regular_price', 8, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('season_pass_types', function (Blueprint $table) {
            $table->decimal('regular_early_price', 8, 2)->nullable(false)->change();
            $table->decimal('regular_regular_price', 8, 2)->nullable(false)->change();
            $table->decimal('renewal_early_price', 8, 2)->nullable(false)->change();
            $table->decimal('renewal_regular_price', 8, 2)->nullable(false)->change();
            $table->decimal('group_early_price', 8, 2)->nullable(false)->change();
            $table->decimal('group_regular_price', 8, 2)->nullable(false)->change();
        });
    }
};