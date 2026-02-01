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
            $table->decimal('renewal_early_price', 8, 2)->after('regular_regular_price')->default(0);
            $table->decimal('renewal_regular_price', 8, 2)->after('renewal_early_price')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('season_pass_types', function (Blueprint $table) {
            $table->dropColumn(['renewal_early_price', 'renewal_regular_price']);
        });
    }
};