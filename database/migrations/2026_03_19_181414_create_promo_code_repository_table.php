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
        Schema::create('promo_code_repository', function (Blueprint $table) {
            $table->string('promo_code')->primary();
            $table->unsignedBigInteger('season_id');
            $table->date('start_date');
            $table->date('expiration_date');
            $table->enum('country', ['USA', 'Canada'])->nullable();
            $table->boolean('is_suspended')->default(false);
            $table->timestamps();

            $table->foreign('season_id')->references('id')->on('seasons')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_code_repository');
    }
};
