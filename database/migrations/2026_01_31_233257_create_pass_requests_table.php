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
        Schema::create('pass_requests', function (Blueprint $table) {
            $table->string('id')->primary(); // cuid
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('request_season');
            $table->string('passholder_email');
            $table->string('pass_type');
            $table->string('passholder_first_name');
            $table->string('passholder_last_name');
            $table->date('passholder_birth_date');
            $table->boolean('is_renewal')->default(false);
            $table->string('renewal_pass_id')->nullable();
            $table->string('renewal_order_number', 30)->nullable();
            $table->string('promo_code')->nullable();
            $table->date('redemption_date')->nullable();
            $table->date('assign_code_date')->nullable();
            $table->dateTime('email_notify_time')->nullable();
            $table->timestamps();

            $table->index('user_id', 'pass_request_user_id_fkey');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pass_requests');
    }
};
