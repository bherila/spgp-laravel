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
        Schema::create('season_pass_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('seasons')->onDelete('cascade');
            $table->string('pass_type_name');
            $table->decimal('regular_price', 8, 2);
            $table->decimal('group_early_price', 8, 2);
            $table->decimal('group_price', 8, 2);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('season_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('season_pass_types');
    }
};
