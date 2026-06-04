<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('bherila-auth.audit.table', 'auth_audit_log');

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('acting_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('event', 64);
            $table->string('auth_method', 32)->nullable();
            $table->boolean('succeeded');
            $table->string('reason')->nullable();
            $table->binary('ip_address', 16)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('session_id', 64)->nullable();
            $table->boolean('is_suspicious')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('event');
            $table->index('email');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        if (config('bherila-auth.migrations.drop_tables_on_rollback', false)) {
            Schema::dropIfExists(config('bherila-auth.audit.table', 'auth_audit_log'));
        }
    }
};
