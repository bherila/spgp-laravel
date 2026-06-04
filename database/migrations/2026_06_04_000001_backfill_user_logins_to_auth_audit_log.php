<?php

use BWH\Auth\Models\AuthAuditLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $auditTable = config('bherila-auth.audit.table', 'auth_audit_log');

        if (! Schema::hasTable('user_logins') || ! Schema::hasTable($auditTable)) {
            return;
        }

        DB::table('user_logins')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($auditTable): void {
                $payload = [];

                foreach ($rows as $row) {
                    $isImpersonation = $row->successful
                        && is_string($row->failure_reason)
                        && Str::startsWith($row->failure_reason, 'Impersonated by admin: ');

                    $actingEmail = $isImpersonation
                        ? trim(Str::after($row->failure_reason, 'Impersonated by admin: '))
                        : null;

                    $payload[] = [
                        'user_id' => $row->user_id,
                        'acting_user_id' => $actingEmail
                            ? DB::table('users')->where('email', $actingEmail)->value('id')
                            : null,
                        'email' => $row->email,
                        'event' => $row->successful
                            ? AuthAuditLog::EVENT_LOGIN_SUCCEEDED
                            : AuthAuditLog::EVENT_LOGIN_FAILED,
                        'auth_method' => $isImpersonation ? 'impersonation' : 'password',
                        'succeeded' => (bool) $row->successful,
                        'reason' => $row->successful ? null : $row->failure_reason,
                        'ip_address' => $this->packIp($row->ip_address),
                        'user_agent' => $row->user_agent,
                        'session_id' => null,
                        'is_suspicious' => false,
                        'metadata' => $actingEmail ? json_encode(['impersonated_by' => $actingEmail]) : null,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ];
                }

                if ($payload !== []) {
                    DB::table($auditTable)->insert($payload);
                }
            });

        Schema::dropIfExists('user_logins');
    }

    public function down(): void
    {
        if (Schema::hasTable('user_logins')) {
            return;
        }

        Schema::create('user_logins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->index();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->boolean('successful')->default(false);
            $table->string('failure_reason')->nullable();
            $table->timestamps();
        });
    }

    private function packIp(?string $ipAddress): ?string
    {
        if ($ipAddress === null || $ipAddress === '') {
            return null;
        }

        $packed = inet_pton($ipAddress);

        return $packed === false ? null : $packed;
    }
};
