<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add season_id to invite_codes
        Schema::table('invite_codes', function (Blueprint $table) {
            $table->foreignId('season_id')->nullable()->after('id')->constrained('seasons')->onDelete('cascade');
        });

        // 2. Create pivot table for invite codes and users
        Schema::create('invite_code_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invite_code_id')->constrained('invite_codes')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['invite_code_id', 'user_id']);
        });

        // 3. Migrate data from users.invite_code_id to invite_code_user
        $usersWithInvites = DB::table('users')
            ->whereNotNull('invite_code_id')
            ->select('id', 'invite_code_id', 'created_at', 'updated_at')
            ->get();

        foreach ($usersWithInvites as $user) {
            DB::table('invite_code_user')->insert([
                'invite_code_id' => $user->invite_code_id,
                'user_id' => $user->id,
                'created_at' => $user->created_at ?: now(),
                'updated_at' => $user->updated_at ?: now(),
            ]);
        }

        // 4. Drop invite_code_id from users
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['invite_code_id']);
            $table->dropColumn('invite_code_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Re-add invite_code_id to users
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('invite_code_id')->nullable()->after('remember_token')->constrained('invite_codes')->nullOnDelete();
        });

        // 2. Migrate data back (this is tricky as one user can now have multiple, we'll just take the latest one)
        $latestInvites = DB::table('invite_code_user')
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($latestInvites as $mapping) {
            DB::table('users')
                ->where('id', $mapping->user_id)
                ->update(['invite_code_id' => $mapping->invite_code_id]);
        }

        // 3. Drop pivot table
        Schema::dropIfExists('invite_code_user');

        // 4. Drop season_id from invite_codes
        Schema::table('invite_codes', function (Blueprint $table) {
            $table->dropForeign(['season_id']);
            $table->dropColumn('season_id');
        });
    }
};
