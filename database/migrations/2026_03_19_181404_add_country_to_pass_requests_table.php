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
            $table->enum('country', ['USA', 'Canada', 'Other'])->nullable()->after('promo_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pass_requests', function (Blueprint $table) {
            $table->dropColumn('country');
        });
    }
};
